<?php
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

_startSecureSession();

$resourceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($resourceId <= 0) {
    http_response_code(404);
    exit('Resource not found.');
}

$isAdmin = isAdminLoggedIn();

try {
    $pdo = getDB();

    // Join with products to check product visibility
    $stmt = $pdo->prepare('
        SELECT r.*, p.status as product_status
        FROM product_resources r
        JOIN products p ON r.product_id = p.id
        WHERE r.id = ?
    ');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        http_response_code(404);
        exit('Resource not found.');
    }

    if (!$isAdmin) {
        if ($resource['product_status'] !== 'published') {
            http_response_code(404);
            exit('Resource not found (product drafted).');
        }
        if (!$resource['is_public']) {
            http_response_code(404);
            exit('Resource not found (draft).');
        }

        // SECURITY: Verify the parent product is actually a free download product.
        // Paid external products must not leak their hosted resources to unauthenticated visitors.
        $prodStmt = $pdo->prepare('SELECT product_type FROM products WHERE id = ?');
        $prodStmt->execute([$resource['product_id']]);
        $prodType = $prodStmt->fetchColumn();
        if ($prodType !== 'free_download') {
            http_response_code(403);
            exit('Unauthorized: This resource belongs to a paid external product and cannot be downloaded directly.');
        }
    }

    $resourcesBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/resources';
    $physicalPath = $resourcesBaseDir . '/' . basename($resource['file_path']);

    $realPath = realpath($physicalPath);
    $realBase = realpath($resourcesBaseDir);

    if ($realPath === false || $realBase === false || !str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
        http_response_code(404);
        exit('Resource file not found on disk.');
    }

    $mime = mime_content_type($realPath);
    if (!$mime) {
        $mime = 'application/octet-stream';
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . basename($resource['file_path']) . '"');
    header('Content-Length: ' . filesize($realPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    readfile($realPath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit('Internal Server Error.');
}
