<?php
// ============================================================
// POSTS — SOFT DELETE  [V5 REQ-005]
// POST /api/posts/delete.php
// Requires: authenticated session + CSRF token
// Body (JSON): {"id": 123}
//
// CHANGE FROM V4:
//   Previously: hard DELETE + @unlink() of cover image.
//   Now:        Sets deleted_at = NOW() (soft delete).
//               The post is immediately invisible on the
//               public site and in normal admin lists.
//               The cover image file is NOT deleted — it is
//               retained so the post can be restored later.
//               Use api/posts/purge.php to permanently remove
//               a soft-deleted post and its cover image.
//
// Requires the deleted_at column added by:
//   database/migration_v5_soft_delete.sql
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

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

    // ---- Verify the post exists and is not already soft-deleted -----------
    $stmt = $pdo->prepare(
        'SELECT id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المنشور غير موجود.']);
        exit;
    }

    // ---- Soft delete — set deleted_at, keep the row and cover image -------
    $stmt = $pdo->prepare(
        'UPDATE posts SET deleted_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$id]);

    logAdminAction('post.trash', 'post', (string) $id, json_encode([
        'action' => 'soft_delete',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم نقل المنشور إلى سلة المحذوفات.',
    ]);

} catch (PDOException $e) {

    error_log('[posts/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
