<?php
// ============================================================
// POSTS — RESTORE  [V5 REQ-005]
// POST /api/posts/restore.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {"id": 123}
//
// Restores a soft-deleted post by setting deleted_at = NULL.
// The post is immediately visible again on the public site
// (if its status is 'published').
//
// Security:
//   - requireAuth(): enforces active admin session
//   - requireCSRF(): prevents cross-site request forgery
//   - Only operates on posts that are actually soft-deleted
//     (deleted_at IS NOT NULL) — cannot be used to change
//     the status of live posts
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المنشور مطلوب.']);
    exit;
}

try {

    $pdo = getDB();

    // Verify the post exists and IS soft-deleted (only restores from trash)
    $stmt = $pdo->prepare(
        'SELECT id FROM posts WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1'
    );
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المنشور غير موجود في سلة المحذوفات.']);
        exit;
    }

    // Restore: clear the deleted_at timestamp
    $stmt = $pdo->prepare(
        'UPDATE posts SET deleted_at = NULL WHERE id = ?'
    );
    $stmt->execute([$id]);

    logAdminAction('post.restore', 'post', (string) $id, json_encode([
        'action' => 'restore',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم استرجاع المنشور بنجاح.',
    ]);

} catch (PDOException $e) {

    error_log('[posts/restore] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
