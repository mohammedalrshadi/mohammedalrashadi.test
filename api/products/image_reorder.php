<?php
// ============================================================
// PRODUCTS — GALLERY IMAGE REORDER
// POST /api/products/image_reorder.php
// Requires: authenticated admin session + CSRF token
// Payload: json { "product_id": 123, "order": [4, 5, 2] }
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

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$productId = isset($data['product_id']) ? (int)$data['product_id'] : 0;
$order = isset($data['order']) && is_array($data['order']) ? $data['order'] : [];

if ($productId <= 0 || empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

// Enforce strict integer validation and remove duplicates
$cleanOrder = [];
foreach ($order as $id) {
    if (filter_var($id, FILTER_VALIDATE_INT) === false || (int)$id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All IDs must be valid positive integers.']);
        exit;
    }
    $cleanOrder[] = (int)$id;
}

if (count($cleanOrder) !== count(array_unique($cleanOrder))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Duplicate IDs in order array are not allowed.']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    // Get ALL existing image IDs for this product to verify completeness
    $stmtExisting = $pdo->prepare("SELECT id FROM product_images WHERE product_id = ?");
    $stmtExisting->execute([$productId]);
    $existingIds = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);
    $existingIds = array_map('intval', $existingIds);

    if (count($existingIds) !== count($cleanOrder)) {
        throw new RuntimeException('The submitted order must contain all existing gallery images exactly once.');
    }
    
    // Check if the submitted IDs exactly match the existing IDs
    $diff1 = array_diff($cleanOrder, $existingIds);
    $diff2 = array_diff($existingIds, $cleanOrder);
    
    if (!empty($diff1) || !empty($diff2)) {
        throw new RuntimeException('One or more submitted IDs do not belong to this product or the order is incomplete.');
    }
    
    // Update order
    $stmtUpd = $pdo->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?");
    $currentOrder = 1;
    foreach ($cleanOrder as $id) {
        $stmtUpd->execute([$currentOrder++, $id]);
    }
    
    $pdo->commit();

    logAdminAction('product.image.reorder', 'product', (string) $productId, json_encode([
        'new_order_count' => count($cleanOrder)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'message' => 'Images reordered successfully.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
