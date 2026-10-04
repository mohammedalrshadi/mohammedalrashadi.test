<?php
// ============================================================
// PRODUCTS — SECURE DOWNLOAD
// GET /api/products/download.php?id=123
//
// Controlled delivery endpoint for free website-hosted digital products.
//
// Security Requirements Enforced:
//   1. Validates product ID.
//   2. Loads product via prepared statement.
//   3. Verifies product exists.
//   4. Verifies product is published (admin preview permitted).
//   5. Verifies product_type === 'free_download'.
//   6. Resolves file strictly inside protected downloads directory.
//   7. Prevents path traversal and arbitrary filesystem reading.
//   8. Emits safe download headers and streams file contents.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : null;

if ($id <= 0 && empty($slug)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Valid product ID or slug is required.']);
    exit;
}

try {
    $pdo = getDB();

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, title, slug, product_type, download_path, status
             FROM products
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, title, slug, product_type, download_path, status
             FROM products
             WHERE slug = ?
             LIMIT 1'
        );
        $stmt->execute([$slug]);
    }

    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    // Check status: only published products can be downloaded by visitors
    $isAdmin = isAdminLoggedIn();
    if ($product['status'] !== 'published' && !$isAdmin) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This product is not currently available for download.']);
        exit;
    }

    // Check product type
    if ($product['product_type'] !== 'free_download') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This product is an external product and does not have a direct file download.']);
        exit;
    }

    // Check download path
    if (empty($product['download_path'])) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No download file has been configured for this product.']);
        exit;
    }

    // Resolve file and verify path traversal resistance
    $realFilePath = resolveProtectedDownloadFile($product['download_path']);
    if ($realFilePath === null || !file_exists($realFilePath) || !is_file($realFilePath)) {
        error_log("[products/download] Missing or unresolvable file for product ID {$product['id']}: " . $product['download_path']);
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'The requested file could not be found on the server.']);
        exit;
    }

    // Determine clean friendly output filename
    $fileExt = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
    $cleanBase = preg_replace('/[^a-z0-9_\-]/i', '-', $product['slug'] ?: 'download');
    $cleanBase = trim($cleanBase, '-');
    $downloadFilename = ($cleanBase ?: 'product-download') . '.' . $fileExt;

    // MIME type resolution
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($realFilePath);

    $contentType = match ($fileExt) {
        'pdf'   => 'application/pdf',
        'zip'   => 'application/zip',
        default => ($detectedMime ?: 'application/octet-stream'),
    };

    $fileSize = filesize($realFilePath);



    // Clean output buffer before streaming binary
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Headers
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"; filename*=UTF-8\'\'' . rawurlencode($downloadFilename));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: private, no-transform, no-store, must-revalidate');
    header('Pragma: no-cache');
    if ($fileSize !== false) {
        header('Content-Length: ' . $fileSize);
    }

    readfile($realFilePath);
    exit;

} catch (PDOException $e) {
    error_log('[products/download] DB error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    exit;
}

