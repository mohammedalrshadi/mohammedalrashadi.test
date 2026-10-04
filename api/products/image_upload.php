<?php
// ============================================================
// PRODUCTS — GALLERY IMAGE UPLOAD
// POST /api/products/image_upload.php
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

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

$fileField = 'image';
if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file provided.']);
    exit;
}

$file = $_FILES[$fileField];

try {
    $pdo = getDB();
    
    // Verify product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Product not found.');
    }

    // Verify limit
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
    $stmtCount->execute([$productId]);
    if ($stmtCount->fetchColumn() >= 12) { // MAX_GALLERY_IMAGES = 12
        throw new RuntimeException('Maximum gallery limit (12) reached.');
    }

    // --- Validation (reused logic from upload_helper) ---
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload error code: ' . $file['error']);
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new RuntimeException('File exceeds max size.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES, true)) {
        throw new RuntimeException('Invalid MIME type. Only JPEG, PNG, GIF, WebP are allowed.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Invalid file extension.');
    }

    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Invalid image file.');
    }
    // ---------------------------------------------------

    $galleryDir = dirname(dirname(__DIR__)) . '/uploads/products/images/' . $productId . '/';
    if (!is_dir($galleryDir)) {
        if (!mkdir($galleryDir, 0755, true)) {
            throw new RuntimeException('Failed to create gallery storage directory.');
        }
    }

    $randomBase = bin2hex(random_bytes(16));
    
    // Optimize image (this generates WebP variants and main file)
    $opt = optimizeImage($file['tmp_name'], $galleryDir . $randomBase);
    
    $finalMainPath = $opt['main_path'];
    $publicPath = '/uploads/products/images/' . $productId . '/' . basename($finalMainPath);

    // Insert into DB
    $pdo->beginTransaction();
    
    // Get max sort_order — FOR UPDATE prevents race condition under concurrent uploads
    $stmtSort = $pdo->prepare("SELECT MAX(sort_order) FROM product_images WHERE product_id = ? FOR UPDATE");
    $stmtSort->execute([$productId]);
    $nextSort = (int)$stmtSort->fetchColumn() + 1;

    $stmtInsert = $pdo->prepare("INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)");
    if (!$stmtInsert->execute([$productId, $publicPath, $nextSort])) {
        throw new Exception('DB insert failed');
    }
    
    $imageId = (int)$pdo->lastInsertId();
    $pdo->commit();

    logAdminAction('product.image.upload', 'product', (string) $productId, json_encode([
        'image_id' => $imageId,
        'image_path' => $publicPath,
        'sort_order' => $nextSort
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'image' => [
            'id' => $imageId,
            'product_id' => $productId,
            'image_path' => $publicPath,
            'alt_text' => null,
            'sort_order' => $nextSort
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Cleanup any files written if DB failed
    if (isset($opt) && isset($opt['main_path']) && file_exists($opt['main_path'])) {
        require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';
        deleteImageWithVariants($opt['main_path'], dirname(dirname(__DIR__)) . '/uploads/products/images/' . $productId);
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
