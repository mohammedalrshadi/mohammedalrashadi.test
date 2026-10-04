<?php
// ============================================================
// PRODUCTS — GALLERY IMAGES LIST
// GET /api/products/images_list.php?product_id=123
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();

if (!isAdminLoggedIn()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT id, product_id, image_path, alt_text, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $images
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == '42S02') {
        // Table doesn't exist yet
        echo json_encode(['success' => true, 'data' => []]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
}
