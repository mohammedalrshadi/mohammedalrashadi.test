<?php
// ============================================================
// PRODUCTS — DELETE / ARCHIVE
// POST /api/products/delete.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {id: 123, action: "delete" | "archive"}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';
require_once __DIR__ . '/helper.php';

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

$id     = isset($input['id']) ? (int) $input['id'] : 0;
$action = isset($input['action']) ? trim((string)$input['action']) : 'delete';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, download_path, title, thumbnail FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    if ($action === 'archive') {
        $archiveStmt = $pdo->prepare("UPDATE products SET status = 'archived' WHERE id = ?");
        $archiveStmt->execute([$id]);

        logAdminAction('product.archive', 'product', (string) $id, json_encode([
            'title' => mb_substr($product['title'], 0, 100, 'UTF-8'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true,
            'message' => 'Product archived successfully.',
        ]);
        exit;
    }


    // Collect product resources for cleanup
    $resStmt = $pdo->prepare('SELECT file_path FROM product_resources WHERE product_id = ? AND file_path IS NOT NULL AND file_path != ""');
    $resStmt->execute([$id]);
    $resourceFiles = $resStmt->fetchAll(PDO::FETCH_COLUMN);

    // Collect gallery images for cleanup
    $galleryFiles = [];
    try {
        $galStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
        $galStmt->execute([$id]);
        $galleryFiles = $galStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        if ($e->getCode() != '42S02') throw $e;
    }


    $pdo->beginTransaction();

    // Clean up home showcase items referencing this product
    $delShowcase = $pdo->prepare("DELETE FROM home_showcase_items WHERE item_type = 'product' AND reference_id = ?");
    $delShowcase->execute([$id]);

    // Hard delete
    $delStmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $delStmt->execute([$id]);

    $pdo->commit();

    // Clean up product resources physically
    $resourcesBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/resources';
    $realBase = realpath($resourcesBaseDir);
    if ($realBase !== false) {
        foreach ($resourceFiles as $rPath) {
            $physicalPath = $resourcesBaseDir . '/' . basename($rPath);
            $realPath = realpath($physicalPath);
            if ($realPath !== false && str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
                @unlink($realPath);
            }
        }
    }

    logAdminAction('product.delete', 'product', (string) $id, json_encode([
        'title' => mb_substr($product['title'], 0, 100, 'UTF-8'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // Clean up protected download file if it exists
    if (!empty($product['download_path'])) {
        $realDownloadPath = resolveProtectedDownloadFile($product['download_path']);
        if ($realDownloadPath !== null && file_exists($realDownloadPath)) {
            @unlink($realDownloadPath);
        }
    }

    // Clean up uploaded demo directory if it exists
    $demosBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/demos';
    $demoDir = $demosBaseDir . '/' . $id;
    if (is_dir($demoDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($demoDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($demoDir);
    }

    // Clean up product thumbnail image and its variants if stored in /uploads/
    if (!empty($product['thumbnail'])) {
        deleteImageWithVariants($product['thumbnail']);
    }

    // Clean up product gallery images and their variants
    if (!empty($galleryFiles)) {
        $galleryDir = dirname(dirname(__DIR__)) . '/uploads/products/images/' . $id;
        foreach ($galleryFiles as $gPath) {
            deleteImageWithVariants($gPath, $galleryDir);
        }
        @rmdir($galleryDir);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product deleted successfully.',
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[products/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while deleting product.']);
}
