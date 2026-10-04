<?php
// ============================================================
// POSTS — PUBLISH (REQ-012)
// POST /api/posts/publish.php
//
// Dedicated explicit publish transition endpoint.
// Requires:
//   - Authenticated admin session
//   - Valid CSRF token
//   - JSON body: { "id": <positive-int> }
// Rejects:
//   - Non-admin sessions (403)
//   - Invalid/missing ID (400)
//   - Non-existent or soft-deleted posts (404)
// Transitions:
//   - Sets status = 'published'
//   - Sets updated_at = NOW()
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Authentication & Authorization checks
requireAuth();
if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// CSRF validation
requireCSRF();

// Parse body
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

$id = isset($input['id']) ? (int) $input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المنشور غير صالح.']);
    exit;
}

try {
    $pdo = getDB();

    // Verify post exists and is not soft-deleted
    $stmt = $pdo->prepare('SELECT id, status, deleted_at FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post || $post['deleted_at'] !== null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المنشور غير موجود أو تم حذفه.']);
        exit;
    }

    // Explicit publish transition
    $updateStmt = $pdo->prepare("UPDATE posts SET status = 'published', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $updateStmt->execute([$id]);

    logAdminAction('post.publish', 'post', (string) $id, json_encode([
        'status' => 'published',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم نشر المنشور بنجاح.',
        'data'    => [
            'id'     => $id,
            'status' => 'published',
        ],
    ]);

} catch (PDOException $e) {
    error_log('[POSTS_PUBLISH] Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
