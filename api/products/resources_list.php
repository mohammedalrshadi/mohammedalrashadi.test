<?php
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

// Check admin auth (since this is called from store-edit.js which is admin)
$isAdmin = isAdminLoggedIn();
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

try {
    $pdo = getDB();
    // Check if table exists before querying
    $stmt = $pdo->prepare('SELECT id, title, description, file_path, file_size, sort_order, is_public FROM product_resources WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$productId]);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resources as &$r) {
        $r['status'] = $r['is_public'] ? 'public' : 'draft';
        $r['file_name'] = basename($r['file_path']);
    }

    echo json_encode(['success' => true, 'data' => $resources]);
} catch (PDOException $e) {
    // If table doesn't exist, return empty
    if ($e->getCode() == '42S02') {
        echo json_encode(['success' => true, 'data' => []]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
}
