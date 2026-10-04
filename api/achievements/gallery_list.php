<?php
// ============================================================
// ACHIEVEMENTS GALLERY — LIST
// GET /api/achievements/gallery_list.php?achievement_id={id}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/helpers/achievements_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();

if (!isset($_GET['achievement_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing achievement_id.']);
    exit;
}

$achievementId = (int)$_GET['achievement_id'];

try {
    $pdo = getDB();
    // Verify the achievement exists and is not soft-deleted (admin guard — prevents leaking gallery of trashed achievements)
    $chk = $pdo->prepare('SELECT id FROM achievements WHERE id = ?' . (achievementsHasDeletedAt($pdo) ? ' AND deleted_at IS NULL' : '') . ' LIMIT 1');
    $chk->execute([$achievementId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement not found.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT * FROM achievement_gallery WHERE achievement_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$achievementId]);
    $images = $stmt->fetchAll();

    echo json_encode(['success' => true, 'images' => $images]);
} catch (PDOException $e) {
    error_log('[achievements/gallery_list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
