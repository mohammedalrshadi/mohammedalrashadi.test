<?php
// ============================================================
// HOME SHOWCASE — DELETE
// POST /api/showcase/delete.php
//
// Removes an item from the curated Home Showcase strip.
// Note: This only deletes the showcase presentation entry,
// NOT the underlying product, project, or article.
// Payload: JSON { id: 123 }
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid showcase item ID is required.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id FROM home_showcase_items WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Showcase item not found.']);
        exit;
    }

    $del = $pdo->prepare("DELETE FROM home_showcase_items WHERE id = ?");
    $del->execute([$id]);

    logAdminAction('showcase.delete', 'home_showcase_item', (string) $id, json_encode([
        'id' => $id,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Item removed from Home Showcase.'
    ]);

} catch (Exception $e) {
    error_log('[api/showcase/delete.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete showcase item.']);
}

