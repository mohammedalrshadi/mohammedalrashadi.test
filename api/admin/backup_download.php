<?php
// ============================================================
// BACKUP DOWNLOAD ENDPOINT
// api/admin/backup_download.php
//
// POST /api/admin/backup_download.php
// Requires: authenticated admin session + CSRF token
// Body (JSON or form): { "filename": "db-YYYYMMDD-HHMMSS-<hex>.sql.gz" }
//
// Security:
//   - Admin auth + CSRF required
//   - Filename validated against strict regex whitelist
//   - basename() enforced (belt-and-suspenders)
//   - No caching headers
//   - Logs download via logAdminAction()
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/backup.php';

// Auth + CSRF checks
requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Parse filename (accept JSON body or form POST) ----------------------
$filename = '';

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $body     = file_get_contents('php://input');
    $input    = json_decode($body, true);
    $filename = isset($input['filename']) ? trim((string) $input['filename']) : '';
} else {
    // application/x-www-form-urlencoded (hidden-form approach from JS)
    $filename = isset($_POST['filename']) ? trim((string) $_POST['filename']) : '';
}

// ---- Validate filename (strict whitelist) --------------------------------
if (!preg_match('/^db-\d{8}-\d{6}-[0-9a-f]{8}\.sql\.gz$/', $filename)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid backup filename.']);
    exit;
}

// ---- Resolve path --------------------------------------------------------
$path = resolveBackupPath($filename);
if ($path === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Backup file not found.']);
    exit;
}

// ---- Stream the file -----------------------------------------------------
$fileSize = filesize($path);

// Prevent any caching of sensitive backup files
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Force download — do NOT set Content-Encoding: gzip (the file is already
// compressed; browsers should save it as-is, not decompress on the fly)
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . $fileSize);
header('X-Content-Type-Options: nosniff');

logAdminAction('backup.download', 'backup', $filename, "Downloaded backup: {$filename} ({$fileSize} bytes)");

// Flush all output buffers before streaming
if (ob_get_level()) {
    ob_end_clean();
}

readfile($path);
exit;

