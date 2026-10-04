<?php
// ============================================================
// ACHIEVEMENTS — PERMANENT PURGE (irreversible)
// POST /api/achievements/purge.php   Body (JSON): {"id": 123}
// Requires: authenticated admin session + CSRF token
//
// Only items ALREADY in the trash (deleted_at IS NOT NULL) can be purged.
// Deletes the row (achievement_gallery rows go via ON DELETE CASCADE) and then the
// cover/gallery image files under /uploads/.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/achievements_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$body = json_decode(file_get_contents('php://input'), true);
$id = (is_array($body) && isset($body['id'])) ? (int) $body['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ID.']);
    exit;
}

try {
    $pdo = getDB();
    achievementsRequireSoftDelete($pdo);

    $stmt = $pdo->prepare('SELECT title, image_url FROM achievements WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
    $stmt->execute([$id]);
    $achievement = $stmt->fetch();
    if (!$achievement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement is not in the trash.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT image_url FROM achievement_gallery WHERE achievement_id = ?');
    $stmt->execute([$id]);
    $galleryImages = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM achievements WHERE id = ? AND deleted_at IS NOT NULL');
    $stmt->execute([$id]);
    $pdo->commit();

    // Files are removed only after the DB delete succeeded.
    if (!empty($achievement['image_url']) && str_starts_with($achievement['image_url'], '/uploads/')) {
        deleteImageWithVariants(basename($achievement['image_url']));
    }
    foreach ($galleryImages as $gImage) {
        if (!empty($gImage) && str_starts_with($gImage, '/uploads/')) {
            deleteImageWithVariants(basename($gImage));
        }
    }

    logAdminAction('achievement.purge', 'achievement', (string) $id, ['title' => $achievement['title']]);

    echo json_encode(['success' => true, 'message' => 'Achievement permanently deleted.']);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[achievements/purge] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
