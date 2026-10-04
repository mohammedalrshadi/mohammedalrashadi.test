<?php
// ============================================================
// POSTS — LIST
// GET /api/posts/list.php
//
// Query parameters:
//   ?status=published   → public: only published posts
//   ?status=all         → admin only: all posts (requires auth)
//   ?id=123             → fetch single post by ID
//
// Authentication:
//   Unauthenticated requests can only see status=published.
//   Any request for non-published data requires a valid session.
//
// [V5 REQ-005] Soft delete:
//   Soft-deleted posts (deleted_at IS NOT NULL) are ALWAYS
//   excluded from every query in this file, including the
//   admin status=all view. Soft-deleted posts are only
//   accessible via api/posts/trash.php (admin only).
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Check if an authenticated ADMIN session is present (optional auth) --
// NOTE: this previously used a locally-defined isAdminSession() that only
// checked whether a user_id was set — meaning ANY authenticated user
// (including role='user') could pass. Fixed to use the shared guard.php
// check, which correctly requires role === 'admin'.

// ---- Parameters ----------------------------------------------------------
$requestedId     = isset($_GET['id'])     ? (int) $_GET['id']     : null;
$requestedStatus = isset($_GET['status']) ? trim($_GET['status'])  : 'published';

// Validate status value
$allowedStatuses = ['published', 'hidden', 'draft', 'all'];
if (!in_array($requestedStatus, $allowedStatuses, true)) {
    $requestedStatus = 'published';
}

// Non-admin users can ONLY fetch published posts
$isAdmin = isAdminLoggedIn();
if (!$isAdmin && $requestedStatus !== 'published') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// ---- Build query ---------------------------------------------------------
try {

    $pdo = getDB();

    // Single post by ID (always returns full content)
    if ($requestedId !== null) {

        // [REQ-005] Always exclude soft-deleted posts (deleted_at IS NOT NULL).
        // Both public callers and authenticated admins get a 404 for soft-deleted
        // posts — the post must be restored first via api/posts/restore.php.
        $whereStatus = $isAdmin ? '' : "AND status = 'published'";

        $stmt = $pdo->prepare(
            "SELECT id, title, category, content, type, status, views, image_url, quote_ar, quote_en, created_at, updated_at
             FROM posts
             WHERE id = ? AND deleted_at IS NULL $whereStatus
             LIMIT 1"
        );
        $stmt->execute([$requestedId]);
        $post = $stmt->fetch();

        if (!$post) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'المقالة غير موجودة.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'data'    => $post,
        ]);
        exit;

    }

    // ---- Optional filters & sorting (REQ-015) ----------------------------
    $allowedTypes = ['blog', 'project'];
    $requestedType = isset($_GET['type']) ? trim($_GET['type']) : null;
    if ($requestedType === 'achievement') {
        error_log("Deprecated API usage: 'achievement' type requested. Use 'project' instead.");
        $requestedType = 'project';
    }
    if ($requestedType !== null && !in_array($requestedType, $allowedTypes, true)) {
        $requestedType = null;
    }

    $requestedCategory = isset($_GET['category']) ? trim($_GET['category']) : null;
    if ($requestedCategory === '') {
        $requestedCategory = null;
    }

    $requestedSearch = isset($_GET['search']) ? trim($_GET['search']) : null;
    if ($requestedSearch === '') {
        $requestedSearch = null;
    } elseif ($requestedSearch !== null) {
        // [REQ-018] Clamp to max 100 characters
        if (mb_strlen($requestedSearch, 'UTF-8') > 100) {
            $requestedSearch = mb_substr($requestedSearch, 0, 100, 'UTF-8');
        }
    }

    // Strict sort whitelist (never allow user input to become a SQL identifier)
    $sortMap = [
        'newest'     => 'created_at DESC, id DESC',
        'oldest'     => 'created_at ASC, id ASC',
        'title_asc'  => 'title ASC, id ASC',
        'title_desc' => 'title DESC, id DESC',
    ];
    $requestedSort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';
    if (!isset($sortMap[$requestedSort])) {
        $requestedSort = 'newest';
    }
    $orderBy = $sortMap[$requestedSort];

    // [REQ-018] Pagination parameters
    $hasPageParam  = isset($_GET['page']);
    $hasLimitParam = isset($_GET['limit']);
    $isLegacyAll   = $hasLimitParam && strtolower(trim($_GET['limit'])) === 'all';
    $isPaginated   = ($hasPageParam || ($hasLimitParam && !$isLegacyAll));

    $page = 1;
    if ($hasPageParam) {
        $page = max(1, (int) $_GET['page']);
    }

    // Default limit: 15 for admin, 9 for public (or clamped between 1 and 100)
    $limit = $isAdmin ? 15 : 9;
    if ($hasLimitParam && !$isLegacyAll) {
        $rawLimit = (int) $_GET['limit'];
        $limit = max(1, min(100, $rawLimit));
    }
    $offset = ($page - 1) * $limit;

    // [REQ-016] List queries omit `content` by default to minimize network payload.
    // The single-post endpoint (?id=123) always returns the complete `content`.
    $includeContent = isset($_GET['include_content']) && in_array(strtolower(trim($_GET['include_content'])), ['1', 'true', 'yes'], true);
    $columns = $includeContent
        ? "id, title, category, content, type, status, views, image_url, quote_ar, quote_en, created_at, updated_at"
        : "id, title, category, SUBSTRING(content, 1, 300) AS excerpt, type, status, views, image_url, quote_ar, quote_en, created_at, updated_at";

    // Build WHERE clauses and parameters safely
    // [REQ-005] Always exclude soft-deleted rows from all list queries
    $where = ["deleted_at IS NULL"];
    $params = [];

    if ($requestedStatus !== 'all') {
        $where[] = "status = ?";
        $params[] = $requestedStatus;
    }

    if ($requestedType !== null) {
        $where[] = "type = ?";
        $params[] = $requestedType;
    }

    if ($requestedCategory !== null) {
        $where[] = "category = ?";
        $params[] = $requestedCategory;
    }

    // [REQ-018] Search across title, category, quote_ar, quote_en, and sanitized content
    if ($requestedSearch !== null) {
        // Escape SQL LIKE special characters: backslash, percent, and underscore
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $requestedSearch);
        $searchParam = '%' . $escapedSearch . '%';

        $where[] = "(title LIKE ? ESCAPE '\\\\'
                    OR category LIKE ? ESCAPE '\\\\'
                    OR quote_ar LIKE ? ESCAPE '\\\\'
                    OR quote_en LIKE ? ESCAPE '\\\\'
                    OR REGEXP_REPLACE(content, '<[^>]+>', ' ') LIKE ? ESCAPE '\\\\')";
        for ($i = 0; $i < 5; $i++) {
            $params[] = $searchParam;
        }
    }

    $whereSql = implode(' AND ', $where);

    if ($isPaginated) {
        // Twin count query for pagination metadata
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM posts
             WHERE $whereSql"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        if ($total === 0) {
            $totalPages = 1;
            $hasPrev    = false;
            $hasNext    = false;
        } else {
            $totalPages = (int) ceil($total / $limit);
            $hasPrev    = $page > 1;
            $hasNext    = $page < $totalPages;
        }

        // Data query with LIMIT and OFFSET
        $stmt = $pdo->prepare(
            "SELECT $columns
             FROM posts
             WHERE $whereSql
             ORDER BY $orderBy
             LIMIT $limit OFFSET $offset"
        );
        $stmt->execute($params);
        $posts = $stmt->fetchAll();

        echo json_encode([
            'success'    => true,
            'data'       => $posts,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => $totalPages,
                'has_prev'    => $hasPrev,
                'has_next'    => $hasNext,
            ],
        ]);
        exit;
    }

    // Legacy unpaginated mode for callers that do not request pagination
    $stmt = $pdo->prepare(
        "SELECT $columns
         FROM posts
         WHERE $whereSql
         ORDER BY $orderBy"
    );
    $stmt->execute($params);

    $posts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $posts,
    ]);

} catch (PDOException $e) {

    error_log('[posts/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
