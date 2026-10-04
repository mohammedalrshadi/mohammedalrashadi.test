<?php
// ============================================================
// REVIEWS — LIST
// GET /api/reviews/list.php
//
// Query parameters:
//   ?status=approved   → public: only approved reviews (default)
//   ?status=all        → admin only: all reviews (requires auth)
//   ?status=pending    → admin only
//   ?status=rejected   → admin only
//
//   ?post_id=<int>     → optional article filter
//
// Authentication:
//   Unauthenticated requests can only see status=approved.
//   Any request for non-approved data requires an admin session.
//
// post_id behaviour (added for article-specific reviews):
//   Public,  no post_id  → WHERE post_id IS NULL
//                          (global/homepage testimonials only)
//   Public,  post_id=N   → WHERE post_id = N
//                          (article-specific approved reviews)
//   Admin,   no post_id  → no post_id filter
//                          (admin sees all reviews regardless)
//   Admin,   post_id=N   → WHERE post_id = N
//                          (admin filtered to one article)
//
// Column visibility:
//   Public  → id, name, message, created_at
//             (email, post_id, status never exposed publicly)
//   Admin   → id, name, email, message, post_id, post_title,
//              status, created_at, updated_at
//             (post_title from LEFT JOIN posts — NULL when no
//              associated article or article was deleted)
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Status parameter ------------------------------------------------
$requestedStatus = isset($_GET['status']) ? trim($_GET['status']) : 'approved';

$allowedStatuses = ['approved', 'pending', 'rejected', 'all'];
if (!in_array($requestedStatus, $allowedStatuses, true)) {
    $requestedStatus = 'approved';
}

// Non-admin visitors can ONLY fetch approved reviews
$isAdmin = isAdminLoggedIn();
if (!$isAdmin && $requestedStatus !== 'approved') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// ---- Article filter (optional) ----------------------------------------
// Accept only a non-empty, purely numeric, positive-integer string.
// Invalid or absent post_id is silently ignored — see behaviour table
// in the header comment above.
$requestedPostId = null;
if (isset($_GET['post_id'])) {
    $rawPostId = $_GET['post_id'];
    if (ctype_digit($rawPostId) && (int) $rawPostId > 0) {
        $requestedPostId = (int) $rawPostId;
    }
    // Non-numeric / zero / negative → fall through as no filter
}

try {

    $pdo = getDB();

    // ---- Build query -------------------------------------------------
    // Admin: LEFT JOIN posts to include the article title so the admin
    //        dashboard can display which article each review belongs to.
    //        A LEFT JOIN (rather than INNER JOIN) means reviews whose
    //        associated article has since been deleted still appear —
    //        post_title will simply be NULL in that case.
    //
    // Public: plain SELECT on reviews only; email, post_id, post_title,
    //         status, updated_at are never exposed to unauthenticated
    //         callers regardless of any parameter they pass.
    if ($isAdmin) {

        $fromClause = 'reviews r LEFT JOIN posts p ON r.post_id = p.id';
        $columns    = 'r.id, r.name, r.email, r.message, r.post_id,
                        p.title AS post_title,
                        r.status, r.created_at, r.updated_at';
        $orderBy    = 'r.created_at DESC';

    } else {

        $fromClause = 'reviews r';
        $columns    = 'r.id, r.name, r.message, r.created_at';
        $orderBy    = 'r.created_at DESC';

    }

    // Build WHERE conditions and bound parameters dynamically so that
    // every user-supplied value goes through a prepared-statement
    // placeholder — no string interpolation of request data.
    $conditions = [];
    $params      = [];

    // Status filter (absent for status=all).
    // Column must be qualified with the table alias when using a JOIN.
    if ($requestedStatus !== 'all') {
        $conditions[] = 'r.status = ?';
        $params[]     = $requestedStatus;
    }

    // Post ID filter
    if ($requestedPostId !== null) {
        // Specific article requested by any authenticated level.
        // For unauthenticated callers, ensure the referenced post is active and published.
        if (!$isAdmin) {
            $fromClause = 'reviews r INNER JOIN posts p ON r.post_id = p.id AND p.status = "published" AND p.deleted_at IS NULL';
        }
        $conditions[] = 'r.post_id = ?';
        $params[]     = $requestedPostId;
    } elseif (!$isAdmin) {
        // Public caller with no post_id → return global reviews only.
        // IS NULL cannot use a bound parameter in standard SQL, so it is
        // appended as a literal. Safe: it is not derived from user input.
        $conditions[] = 'r.post_id IS NULL';
    }
    // Admin with no post_id → no post_id filter (admin sees all reviews)

    $where = empty($conditions)
        ? ''
        : 'WHERE ' . implode(' AND ', $conditions);

    $stmt = $pdo->prepare(
        "SELECT $columns FROM $fromClause $where ORDER BY $orderBy"
    );
    $stmt->execute($params);

    $reviews = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $reviews,
    ]);

} catch (PDOException $e) {

    error_log('[reviews/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
