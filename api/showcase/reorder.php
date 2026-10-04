<?php
// ============================================================
// HOME SHOWCASE — REORDER
// POST /api/showcase/reorder.php
//
// Updates the display order (sort_order) of showcase items.
// Payload: JSON { item_ids: [4, 1, 9, 2] }
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

$itemIds = $input['item_ids'] ?? [];
if (!is_array($itemIds) || empty($itemIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'An array of item_ids is required.']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE home_showcase_items SET sort_order = ? WHERE id = ?");

    $order = 1;
    foreach ($itemIds as $id) {
        $cleanId = (int)$id;
        if ($cleanId > 0) {
            $stmt->execute([$order, $cleanId]);
            $order++;
        }
    }

    $pdo->commit();

    logAdminAction('showcase.reorder', 'home_showcase_item', null, json_encode([
        'item_ids' => $itemIds,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Showcase order saved successfully.'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[api/showcase/reorder.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save showcase order.']);
}

