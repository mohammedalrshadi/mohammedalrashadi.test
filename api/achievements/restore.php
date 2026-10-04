<?php
// ============================================================
// ACHIEVEMENTS — RESTORE FROM TRASH
// POST /api/achievements/restore.php   Body (JSON): {"id": 123}
// Requires: authenticated admin session + CSRF token
// Only operates on items that are in the trash (deleted_at IS NOT NULL).
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
$id = (is_array($body) && isset($body['id'])) ? (int) $body['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ID.']);
    exit;
}

try {
    $pdo = getDB();
    achievementsRequireSoftDelete($pdo);

    $stmt = $pdo->prepare('SELECT title FROM achievements WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
    $stmt->execute([$id]);
    $achievement = $stmt->fetch();
    if (!$achievement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement is not in the trash.']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE achievements SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL');
    $stmt->execute([$id]);

    logAdminAction('achievement.restore', 'achievement', (string) $id, ['title' => $achievement['title']]);

    echo json_encode(['success' => true, 'message' => 'Achievement restored.']);

} catch (PDOException $e) {
    error_log('[achievements/restore] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
