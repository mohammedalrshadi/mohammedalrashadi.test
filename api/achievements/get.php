<?php
// ============================================================
// ACHIEVEMENTS — GET SINGLE
// GET /api/achievements/get.php?id={id}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/achievements_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing ID.']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM achievements WHERE id = ?' . (achievementsHasDeletedAt($pdo) ? ' AND deleted_at IS NULL' : ''));
    $stmt->execute([$id]);
    $achievement = $stmt->fetch();

    if (!$achievement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'achievement' => $achievement]);
} catch (PDOException $e) {
    error_log('[achievements/get] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
