<?php
// ============================================================
// PRODUCTS — UPLOAD
// POST /api/products/upload.php
// Requires: authenticated admin session + CSRF token
// Content-Type: multipart/form-data
//
// Handles both:
//   1. Product Thumbnail (images: jpg, png, webp, gif)
//      → uses existing processUploadedImage()
//   2. Free Download File (PDF, ZIP)
//      → stores into protected downloads directory with randomized filename
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/uploads/upload_helper.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

// -----------------------------------------------------------------
// 1. Thumbnail Image Upload
// -----------------------------------------------------------------
if (isset($_FILES['thumbnail']) || (isset($_POST['upload_type']) && $_POST['upload_type'] === 'thumbnail')) {
    $field = isset($_FILES['thumbnail']) ? 'thumbnail' : 'image';
    try {
        $imageUrl = processUploadedImage($field);
        logAdminAction('product.upload', 'product_media', null, json_encode([
            'type' => 'thumbnail',
            'url'  => $imageUrl,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        echo json_encode([
            'success'     => true,
            'upload_type' => 'thumbnail',
            'url'         => $imageUrl,
            'message'     => 'Thumbnail uploaded successfully.',
        ]);
        exit;
    } catch (RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// -----------------------------------------------------------------
// 2. Download File Upload (PDF, ZIP)
// -----------------------------------------------------------------
$fileField = isset($_FILES['download_file']) ? 'download_file' : (isset($_FILES['file']) ? 'file' : null);

if ($fileField === null || !isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file provided for upload.']);
    exit;
}

$file = $_FILES[$fileField];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds server upload_max_filesize directive.',
        UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds form MAX_FILE_SIZE directive.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $errorMessages[$file['error']] ?? 'Unknown error uploading file.',
    ]);
    exit;
}

// Size check (20 MB)
if ($file['size'] > UPLOAD_MAX_SIZE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'File size exceeds maximum permitted limit (' . (UPLOAD_MAX_SIZE / 1024 / 1024) . ' MB).',
    ]);
    exit;
}

// Extension check
$originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts = ['pdf', 'zip'];

if (!in_array($originalExt, $allowedExts, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Only PDF and ZIP archives are accepted for free digital downloads.',
    ]);
    exit;
}

// MIME type check via finfo
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$allowedMimes = [
    'pdf' => ['application/pdf', 'application/x-pdf'],
    'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'],
];

if (!isset($allowedMimes[$originalExt]) || !in_array($mimeType, $allowedMimes[$originalExt], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => "File content does not match the expected format for a .{$originalExt} file.",
    ]);
    exit;
}

// Magic bytes validation for extra defense-in-depth
$handle = fopen($file['tmp_name'], 'rb');
if (!$handle) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to inspect uploaded file contents.']);
    exit;
}
$headerBytes = fread($handle, 16);
fclose($handle);

if ($originalExt === 'pdf') {
    // PDF must start with %PDF
    if (!str_starts_with($headerBytes, '%PDF')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The uploaded file is not a valid PDF document.']);
        exit;
    }
} elseif ($originalExt === 'zip') {
    // Standard ZIP archive signatures start with PK\x03\x04 or PK\x05\x06 or PK\x07\x08
    $isZip = str_starts_with($headerBytes, "PK\x03\x04") ||
             str_starts_with($headerBytes, "PK\x05\x06") ||
             str_starts_with($headerBytes, "PK\x07\x08");
    if (!$isZip) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The uploaded file is not a valid ZIP archive.']);
        exit;
    }
}

// Save to protected directory with unpredictable randomized name
$protectedDir = getProtectedDownloadDir();
if (!is_dir($protectedDir)) {
    if (!mkdir($protectedDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to initialize protected storage directory.']);
        exit;
    }
}

$randomFilename = bin2hex(random_bytes(16)) . '.' . $originalExt;
$destPath       = $protectedDir . $randomFilename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log('[products/upload] move_uploaded_file failed: src=' . $file['tmp_name'] . ' dest=' . $destPath);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
}

logAdminAction('product.upload', 'product_file', null, json_encode([
    'type'          => 'download_file',
    'download_path' => $randomFilename,
    'filename'      => basename($file['name']),
    'size_bytes'    => (int) $file['size']
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success'       => true,
    'upload_type'   => 'download',
    'download_path' => $randomFilename,
    'filename'      => htmlspecialchars(basename($file['name']), ENT_QUOTES, 'UTF-8'),
    'size'          => (int) $file['size'],
    'message'       => 'Downloadable file uploaded securely.',
]);
