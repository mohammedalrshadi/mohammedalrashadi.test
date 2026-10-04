<?php
// ============================================================
// PRODUCTS — GALLERY IMAGE DELETE
// POST /api/products/image_delete.php
// Requires: authenticated admin session + CSRF token
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';

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

$imageId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($imageId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid image ID.']);
    exit;
}

try {
    $pdo = getDB();
    
    // Find image
    $stmt = $pdo->prepare("SELECT product_id, image_path FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        throw new RuntimeException('Image not found.');
    }
    
    $productId = (int)$image['product_id'];
    $publicPath = $image['image_path'];
    
    // Safety check for path traversal on stored path
    if (strpos($publicPath, '..') !== false || !str_starts_with($publicPath, '/uploads/products/images/')) {
        throw new RuntimeException('Invalid stored image path.');
    }
    
    $physicalDir = dirname(dirname(__DIR__)) . '/uploads/products/images/' . $productId;

    $pdo->beginTransaction();
    $stmtDel = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
    $stmtDel->execute([$imageId]);
    $pdo->commit();
    
    logAdminAction('product.image.delete', 'product', (string) $productId, json_encode([
        'image_id' => $imageId,
        'image_path' => $publicPath
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    
    // Delete physical files only after DB commit
    deleteImageWithVariants($publicPath, $physicalDir);
    
    echo json_encode(['success' => true, 'message' => 'Image deleted successfully.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
