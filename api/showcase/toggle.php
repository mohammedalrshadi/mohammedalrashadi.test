<?php
// ============================================================
// HOME SHOWCASE — TOGGLE VISIBILITY
// POST /api/showcase/toggle.php
//
// Toggles or sets the is_enabled status of a showcase item.
// Payload: JSON { id: 123, is_enabled: 0 | 1 }
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

    $stmt = $pdo->prepare("SELECT id, is_enabled FROM home_showcase_items WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Showcase item not found.']);
        exit;
    }

    $newState = isset($input['is_enabled']) 
        ? ((int)$input['is_enabled'] ? 1 : 0) 
        : ($item['is_enabled'] ? 0 : 1);

    $upd = $pdo->prepare("UPDATE home_showcase_items SET is_enabled = ? WHERE id = ?");
    $upd->execute([$newState, $id]);

    logAdminAction('showcase.toggle', 'home_showcase_item', (string) $id, json_encode([
        'is_enabled' => $newState,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success'    => true,
        'message'    => 'Showcase item visibility updated.',
        'id'         => $id,
        'is_enabled' => $newState
    ]);

} catch (Exception $e) {
    error_log('[api/showcase/toggle.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update visibility.']);
}

