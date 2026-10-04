<?php
// ============================================================
// ACHIEVEMENTS GALLERY — DELETE
// POST /api/achievements/gallery_delete.php
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/helpers/image_optimizer.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ID.']);
    exit;
}

$id = (int)$body['id'];

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT image_url FROM achievement_gallery WHERE id = ?");
    $stmt->execute([$id]);
    $image = $stmt->fetchColumn();

    if (!$image) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Image not found.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM achievement_gallery WHERE id = ?");
    $stmt->execute([$id]);

    if (!empty($image) && str_starts_with($image, '/uploads/')) {
        deleteImageWithVariants(basename($image));
    }

    echo json_encode(['success' => true, 'message' => 'Image deleted.']);
} catch (PDOException $e) {
    error_log('[achievements/gallery_delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
