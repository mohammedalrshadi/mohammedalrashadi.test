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

// Read JSON payload
$input = json_decode(file_get_contents('php://input'), true);

$resourceId = isset($input['id']) ? (int)$input['id'] : 0;
if ($resourceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid resource ID.']);
    exit;
}

$title = isset($input['title']) ? trim($input['title']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
$isPublic = (isset($input['status']) && $input['status'] === 'public') ? 1 : 0;

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required.']);
    exit;
}

try {
    $pdo = getDB();
    
    // Ensure resource exists
    $stmt = $pdo->prepare('SELECT id, product_id FROM product_resources WHERE id = ?');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$resource) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Resource not found.']);
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE product_resources SET title = ?, description = ?, sort_order = ?, is_public = ? WHERE id = ?');
    $updateStmt->execute([$title, $description, $sortOrder, $isPublic, $resourceId]);

    logAdminAction('product.resource.update', 'product', (string) $resource['product_id'], json_encode([
        'resource_id' => $resourceId,
        'title' => mb_substr($title, 0, 100, 'UTF-8')
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'message' => 'Resource updated successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error updating resource.']);
}
