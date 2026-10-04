<?php
// ============================================================
// PROJECT IMAGES — ADD  [REQ-006]
// POST /api/project_images/add.php
// Requires: authenticated admin session + CSRF token
// Content-Type: multipart/form-data
// Fields: image (file), post_id (integer)
//
// Validates the target post is a non-deleted project,
// uploads the image via the shared processUploadedImage()
// helper, and inserts a row into project_images.
//
// Response:
//   {success: true, image: {id, image_url}}
//   {success: false, message: "..."}  on error
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/uploads/upload_helper.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Validate post_id ---------------------------------------------------
$postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الإنجاز مطلوب.']);
    exit;
}

// ---- Verify the post is a non-deleted project ----------------------
try {

    $pdo  = getDB();
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
        echo json_encode([
            'success' => false,
            'message' => 'الإنجاز غير موجود أو لا يمكن إضافة صور إليه.',
        ]);
        exit;
    }

} catch (PDOException $e) {

    error_log('[project_images/add] DB error (post check): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;

}

// ---- Upload and validate the image ------------------------------------
// processUploadedImage() uses the same MIME/size/extension/decode
// validation as api/uploads/image.php. Throws RuntimeException on error.
try {

    $imageUrl = processUploadedImage('image');

} catch (RuntimeException $e) {

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;

}

// ---- Insert into project_images ------------------------------------
try {

    $stmt = $pdo->prepare(
        'INSERT INTO project_images (post_id, image_url) VALUES (?, ?)'
    );
    $stmt->execute([$postId, $imageUrl]);

    $newId = (int) $pdo->lastInsertId();

    logAdminAction('achievement_image.create', 'achievement_image', (string) $newId, json_encode([
        'post_id'   => $postId,
        'image_url' => $imageUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'image'   => [
            'id'        => $newId,
            'image_url' => $imageUrl,
        ],
    ]);

} catch (PDOException $e) {

    // Insert failed after upload — clean up the orphaned file and its variants
    if (!empty($imageUrl)) {
        deleteImageWithVariants($imageUrl);
    }

    error_log('[project_images/add] DB error (insert): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
