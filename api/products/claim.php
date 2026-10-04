<?php
// ============================================================
// PRODUCTS — CLAIM / RECORD DOWNLOAD
// POST /api/products/claim.php
//
// Explicit POST endpoint to record a product download, granting
// library access to the authenticated user.
// Requires CSRF token and valid user session.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

// 1. Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// 2. Require Authentication and CSRF protection
requireUserAuth();
requireCSRF();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Valid product ID is required.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT id, title, product_type, status
         FROM products
         WHERE id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $isAdmin = isAdminLoggedIn();
    if ($product['status'] !== 'published' && !$isAdmin) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This product is not currently available.']);
        exit;
    }

    if ($product['product_type'] !== 'free_download') {
        error_log("[products/claim] Rejected claim for product ID {$id}: product_type is '{$product['product_type']}' (expected 'free_download')");
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This product is not a free download.']);
        exit;
    }

    $userId = currentUserId();
    
    // 1. Record historical download
    $dlStmt = $pdo->prepare(
        "INSERT INTO user_downloads (user_id, product_id, product_title, downloaded_at) 
         VALUES (?, ?, ?, CURRENT_TIMESTAMP)"
    );
    $dlStmt->execute([$userId, (int)$product['id'], (string)$product['title']]);

    // 2. Add to user library if not already present
    $libStmt = $pdo->prepare(
        "INSERT IGNORE INTO user_library (user_id, product_id, access_type, created_at) 
         VALUES (?, ?, 'free_claim', CURRENT_TIMESTAMP)"
    );
    $libStmt->execute([$userId, (int)$product['id']]);

    // 3. Log user activity
    require_once dirname(__DIR__) . '/user/activity_helper.php';
    logUserActivity($pdo, $userId, 'download_completed', 'product', (string)$product['id'], "Downloaded free resource: {$product['title']}");

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('[products/claim] DB error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    exit;
} catch (Exception $e) {
    error_log('[products/claim] error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    exit;
}
