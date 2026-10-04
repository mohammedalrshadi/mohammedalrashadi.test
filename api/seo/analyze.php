<?php
// ============================================================
// SEO ANALYZER — API ENDPOINT
// GET /api/seo/analyze.php?id={postId}&focus_keyword={optional}
//
// Admin-only, read-only, GET. No CSRF token required (matches
// the project's existing convention: CSRF is only required on
// state-changing requests — see api/auth/guard.php).
//
// This is an authoring tool, so an authenticated admin CAN
// analyze a draft or hidden post (deleted_at IS NULL is still
// required — soft-deleted posts are unreachable here, matching
// how the editor itself treats deleted posts as gone). The
// analyzer distinguishes two different questions:
//   - "Is the CONTENT good?" — Metadata/Content/Headings/Images/
//     Links are evaluated the same regardless of status.
//   - "Is this CURRENTLY indexable on the live site?" — Social/
//     Structured Data/Technical SEO honestly report ERROR for a
//     draft/hidden post, because post.php really does emit
//     noindex and skip Open Graph/JSON-LD until status='published'.
// See analyzer.php's file header for the full rationale.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php'; // config.php + db.php pulled in transitively
require_once __DIR__ . '/analyzer.php';

header('Content-Type: application/json');

// ---- Method ----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Auth (admin only) -------------------------------------------------
requireAuth();

// ---- Validate post ID ---------------------------------------------------
$rawId = $_GET['id'] ?? null;
if ($rawId === null || !is_numeric($rawId) || (int) $rawId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المنشور غير صالح.']);
    exit;
}
$postId = (int) $rawId;

// ---- Validate optional focus keyword -------------------------------------
$rawKeyword = isset($_GET['focus_keyword']) ? (string) $_GET['focus_keyword'] : null;
$keywordResult = seo_normalizeFocusKeyword($rawKeyword);
if (!$keywordResult['ok']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $keywordResult['error']]);
    exit;
}
$focusKeyword = $keywordResult['value'];

// ---- Fetch the post — any non-deleted status; soft-deleted stays unreachable ----
try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, title, content, image_url, type, status, created_at, updated_at
         FROM posts
         WHERE id = :id
           AND deleted_at IS NULL
         LIMIT 1"
    );
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[api/seo/analyze] DB error fetching post: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during SEO analysis. Please try again.']);
    exit;
}

if (!$post) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم العثور على المنشور، أو أنه محذوف.',
    ]);
    exit;
}

// ---- Gallery images (projects only) ----------------------------------
$galleryImages = [];
if ($post['type'] === 'project') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, image_url FROM project_images WHERE post_id = :post_id ORDER BY id ASC'
        );
        $stmt->execute([':post_id' => $postId]);
        $galleryImages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('[api/seo/analyze] DB error fetching gallery images: ' . $e->getMessage());
        // Non-fatal: analyze without gallery data rather than failing the
        // whole request over a secondary lookup.
        $galleryImages = [];
    }
}

// ---- Other posts of the same type, for title/description uniqueness ------
// Single query, capped, and scoped to the same content type (comparing a
// blog post's title against an project's isn't "relevant" per the
// brief). This is O(1) round trips — not per-post — so it stays efficient
// even though it reads `content` to derive comparable descriptions in PHP.
$otherPosts = [];
try {
    $stmt = $pdo->prepare(
        'SELECT id, title, content FROM posts
         WHERE deleted_at IS NULL AND id != :id AND type = :type
         ORDER BY id DESC
         LIMIT 300'
    );
    $stmt->execute([':id' => $postId, ':type' => $post['type']]);
    $otherPosts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log('[api/seo/analyze] DB error fetching other posts for uniqueness check: ' . $e->getMessage());
    // Non-fatal: analyze without uniqueness data rather than failing the
    // whole request over an advisory, secondary lookup.
    $otherPosts = [];
}

// ---- Run the analyzer (pure function, no side effects) -------------------
try {
    $result = seo_analyzePost($post, $galleryImages, $focusKeyword, $otherPosts);
} catch (Throwable $e) {
    error_log('[api/seo/analyze] Analyzer error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'تعذّر إتمام تحليل السيو. حاول مرة أخرى.']);
    exit;
}

echo json_encode([
    'success' => true,
    'data'    => $result,
], JSON_UNESCAPED_UNICODE);
