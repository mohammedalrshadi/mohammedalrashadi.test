<?php
// ============================================================
// ACHIEVEMENTS — MOVE TO TRASH (soft delete)
// POST /api/achievements/delete.php   Body (JSON): {"id": 123}
// Requires: authenticated admin session + CSRF token
//
// Sets achievements.deleted_at = NOW(). The row, its gallery rows and all image
// files are KEPT so the item can be restored. Permanent removal (rows + files)
// only happens in /api/achievements/purge.php, on items already in the trash.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
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

if (!is_array($body) || !isset($body['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ID.']);
    exit;
}

$id = (int) $body['id'];
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ID.']);
    exit;
}

try {
    $pdo = getDB();
    achievementsRequireSoftDelete($pdo);

    $stmt = $pdo->prepare('SELECT title FROM achievements WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $achievement = $stmt->fetch();
    if (!$achievement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement not found.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE achievements SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);

    logAdminAction('achievement.trash', 'achievement', (string) $id, ['title' => $achievement['title']]);

    echo json_encode(['success' => true, 'message' => 'Achievement moved to trash.']);

} catch (PDOException $e) {
    error_log('[achievements/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
