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

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

$resourceId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
$isPublic = (isset($_POST['status']) && $_POST['status'] === 'public') ? 1 : 0;

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required.']);
    exit;
}

$pdo = getDB();

// Ensure product exists
$stmt = $pdo->prepare('SELECT id FROM products WHERE id = ?');
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

// Ensure resource belongs to product if updating
$existingResource = null;
if ($resourceId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM product_resources WHERE id = ? AND product_id = ?');
    $stmt->execute([$resourceId, $productId]);
    $existingResource = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingResource) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Resource not found or belongs to another product.']);
        exit;
    }
}

$fileField = 'resource_file';
if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file provided for upload.']);
    exit;
}

$file = $_FILES[$fileField];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error uploading file. Error code: ' . $file['error']]);
    exit;
}

$maxSize = 250 * 1024 * 1024; // 250 MB
if ($file['size'] > $maxSize || $file['size'] == 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds limit or is empty.']);
    exit;
}

// Allowed extensions
$allowedExts = ['zip', 'pdf', 'png', 'jpg', 'jpeg', 'svg', 'fig', 'docx', 'xlsx'];
$dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'inc', 'cgi', 'pl', 'py', 'pyc', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'ps1'];

$originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($originalExt, $allowedExts, true) || in_array($originalExt, $dangerousExtensions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File type not allowed.']);
    exit;
}

// Server-side MIME validation using Fileinfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = [
    'application/zip', 
    'application/pdf', 
    'image/png', 
    'image/jpeg', 
    'image/svg+xml', 
    'application/octet-stream', // Some .fig files
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file content (MIME mismatch).']);
    exit;
}

$resourcesBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/resources';
if (!is_dir($resourcesBaseDir)) {
    mkdir($resourcesBaseDir, 0755, true);
}

// Generate a completely safe, server-side filename
$newFileName = $productId . '_' . bin2hex(random_bytes(8)) . '.' . $originalExt;
$newFilePath = $resourcesBaseDir . '/' . $newFileName;
$relativeFilePath = $newFileName; // store only relative name in DB

if (!move_uploaded_file($file['tmp_name'], $newFilePath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
    exit;
}

$fileSize = (int)$file['size'];

try {
    $pdo->beginTransaction();

    if ($resourceId > 0) {
        // Update
        $stmt = $pdo->prepare('UPDATE product_resources SET title = ?, description = ?, file_path = ?, file_size = ?, sort_order = ?, is_public = ? WHERE id = ?');
        $stmt->execute([$title, $description, $relativeFilePath, $fileSize, $sortOrder, $isPublic, $resourceId]);
        

    } else {
        // Insert
        $stmt = $pdo->prepare('INSERT INTO product_resources (product_id, title, description, file_path, file_size, sort_order, is_public, resource_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$productId, $title, $description, $relativeFilePath, $fileSize, $sortOrder, $isPublic, 'file']);
    }

    $pdo->commit();

    $action = ($resourceId > 0) ? 'product.resource.update' : 'product.resource.upload';
    $targetResourceId = ($resourceId > 0) ? $resourceId : (int)$pdo->lastInsertId();
    logAdminAction($action, 'product', (string) $productId, json_encode([
        'resource_id' => $targetResourceId,
        'title' => mb_substr($title, 0, 100, 'UTF-8'),
        'file_path' => $relativeFilePath
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));


    if ($resourceId > 0 && $existingResource && !empty($existingResource['file_path'])) {
        $oldPhysicalPath = $resourcesBaseDir . '/' . basename($existingResource['file_path']);
        $realOld = realpath($oldPhysicalPath);
        $realBase = realpath($resourcesBaseDir);
        if ($realOld !== false && $realBase !== false && str_starts_with($realOld, $realBase . DIRECTORY_SEPARATOR)) {
            if ($realOld !== realpath($newFilePath)) {
                @unlink($realOld);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Resource saved successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    // Rollback: delete the newly uploaded file since DB failed
    @unlink($newFilePath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error saving resource.']);
}
