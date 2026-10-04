<?php
// ============================================================
// ACHIEVEMENTS GALLERY — ADD
// POST /api/achievements/gallery_add.php
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/uploads/upload_helper.php';
require_once dirname(__DIR__) . '/helpers/image_optimizer.php';
require_once dirname(__DIR__) . '/helpers/achievements_schema.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$achievementId = isset($_POST['achievement_id']) ? (int) $_POST['achievement_id'] : 0;
$altText       = isset($_POST['alt_text']) ? trim($_POST['alt_text']) : null;

if ($achievementId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Achievement ID is required.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id FROM achievements WHERE id = ?' . (achievementsHasDeletedAt($pdo) ? ' AND deleted_at IS NULL' : '') . ' LIMIT 1');
    $stmt->execute([$achievementId]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement not found.']);
        exit;
    }
} catch (PDOException $e) {
    error_log('[achievements/gallery_add] DB error (check): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;
}

try {
    $imageUrl = processUploadedImage('image');
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM achievement_gallery WHERE achievement_id = ?");
    $stmt->execute([$achievementId]);
    $nextSort = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO achievement_gallery (achievement_id, image_url, alt_text, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([$achievementId, $imageUrl, $altText, $nextSort]);

    $newId = (int) $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'image'   => [
            'id'             => $newId,
            'achievement_id' => $achievementId,
            'image_url'      => $imageUrl,
            'alt_text'       => $altText,
            'sort_order'     => $nextSort
        ],
    ]);
} catch (PDOException $e) {
    if (!empty($imageUrl)) {
        deleteImageWithVariants(basename($imageUrl));
    }
    error_log('[achievements/gallery_add] DB error (insert): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
