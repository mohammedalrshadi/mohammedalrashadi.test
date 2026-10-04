<?php
// ============================================================
// PROJECT IMAGES — DELETE  [REQ-006]
// POST /api/project_images/delete.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {"id": N, "post_id": N}
//
// Validation order:
//   1. Auth + CSRF
//   2. id and post_id present and > 0
//   3. Post is type='project' and not soft-deleted
//   4. Image row belongs to that post (AND post_id = ?) —
//      prevents cross-project deletion
//   5. Validate path + delete physical file
//   6. DELETE FROM project_images WHERE id = ?
//
// Physical file deletion:
//   Uses the same preg_match path guard as purge.php.
//   If the file is already missing, logs and continues
//   (does not error) — DB row is still removed.
//   Every uploaded image has a random hex filename unique
//   to one DB row, so deleting it is always safe.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Parse JSON body ----------------------------------------------------
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

$imageId = isset($input['id'])      ? (int) $input['id']      : 0;
$postId  = isset($input['post_id']) ? (int) $input['post_id'] : 0;

if ($imageId <= 0 || $postId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'المعرفات مطلوبة.']);
    exit;
}

try {

    $pdo = getDB();

    // ---- Verify the post is a non-deleted project ------------------
    $stmt = $pdo->prepare(
        "SELECT id FROM posts
          WHERE id = ?
            AND type = 'project'
            AND deleted_at IS NULL
          LIMIT 1"
    );
    $stmt->execute([$postId]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الإنجاز غير موجود.']);
        exit;
    }

    // ---- Fetch the image row; ownership check (AND post_id = ?) --------
    // This prevents an admin from deleting an image belonging to a
    // different project by sending a mismatched id/post_id pair.
    $stmt = $pdo->prepare(
        'SELECT image_url FROM project_images WHERE id = ? AND post_id = ? LIMIT 1'
    );
    $stmt->execute([$imageId, $postId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الصورة غير موجودة.']);
        exit;
    }

    $imageUrl = $row['image_url'];

    // ---- Delete physical file and variants ----------------------------
    $deleted = deleteImageWithVariants($imageUrl);
    if (empty($deleted)) {
        error_log('[project_images/delete] No files deleted for: ' . $imageUrl);
    }

    // ---- Remove DB row -------------------------------------------------
    $stmt = $pdo->prepare('DELETE FROM project_images WHERE id = ?');
    $stmt->execute([$imageId]);

    logAdminAction('achievement_image.delete', 'achievement_image', (string) $imageId, json_encode([
        'post_id'   => $postId,
        'image_url' => $imageUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'message' => 'تم حذف الصورة.']);

} catch (PDOException $e) {

    error_log('[project_images/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
