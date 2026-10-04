<?php
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

$isAdmin = isAdminLoggedIn();
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$resourceId = isset($input['id']) ? (int)$input['id'] : 0;

if ($resourceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid resource ID.']);
    exit;
}

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare('SELECT * FROM product_resources WHERE id = ?');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$resource) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Resource not found.']);
        exit;
    }

    $pdo->beginTransaction();

    $delStmt = $pdo->prepare('DELETE FROM product_resources WHERE id = ?');
    $delStmt->execute([$resourceId]);
    
    $pdo->commit();
    
    logAdminAction('product.resource.delete', 'product', (string) $resource['product_id'], json_encode([
        'resource_id' => $resourceId,
        'title' => mb_substr($resource['title'] ?? '', 0, 100, 'UTF-8')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    
    // Only unlink AFTER database delete succeeds
    if (!empty($resource['file_path'])) {
        $resourcesBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/resources';
        $physicalPath = $resourcesBaseDir . '/' . basename($resource['file_path']);
        
        $realPath = realpath($physicalPath);
        $realBase = realpath($resourcesBaseDir);
        
        if ($realPath !== false && $realBase !== false && str_starts_with($realPath, $realBase . DIRECTORY_SEPARATOR)) {
            @unlink($realPath);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Resource deleted successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error deleting resource.']);
}
