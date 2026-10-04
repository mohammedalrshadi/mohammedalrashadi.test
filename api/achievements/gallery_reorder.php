<?php
// ============================================================
// ACHIEVEMENTS GALLERY — REORDER
// POST /api/achievements/gallery_reorder.php
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['order']) || !is_array($body['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid order array.']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE achievement_gallery SET sort_order = ? WHERE id = ?");
    
    foreach ($body['order'] as $item) {
        if (isset($item['id'], $item['sort_order'])) {
            $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Gallery reordered.']);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[achievements/gallery_reorder] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
