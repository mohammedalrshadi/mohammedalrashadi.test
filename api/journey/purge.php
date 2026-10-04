<?php
declare(strict_types=1);

// ============================================================
// JOURNEY MILESTONES — PERMANENT PURGE
// POST /api/journey/purge.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {"id": 123}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = file_get_contents('php://input');
$input = json_decode($body, true);

$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Milestone ID is required.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, title FROM journey_milestones WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Milestone not found in trash.']);
        exit;
    }

    $delStmt = $pdo->prepare('DELETE FROM journey_milestones WHERE id = ?');
    $delStmt->execute([$id]);

    logAdminAction('journey.purge', 'journey_milestone', (string) $id, json_encode([
        'title' => $item['title'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Milestone permanently deleted.',
    ], JSON_THROW_ON_ERROR);

} catch (PDOException $e) {
    error_log('[journey/purge] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to purge milestone.']);
}

