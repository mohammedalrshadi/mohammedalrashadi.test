<?php
// ============================================================
// PRODUCTS — UPLOAD STATIC DEMO
// POST /api/products/upload_demo.php
//
// Safely uploads, validates, and extracts a ZIP containing a static website.
// Isolates execution, rejects dangerous files, protects against traversal.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

if (!function_exists('removeDirRecursive')) {
    function removeDirRecursive(string $dir): void {
        if (!is_dir($dir)) return;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }
        @rmdir($dir);
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$productId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid product ID is required.']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, live_demo_source, live_demo_path FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

$fileField = 'demo_file';
if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No ZIP file provided for upload.']);
    exit;
}

$file = $_FILES[$fileField];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error uploading file. Error code: ' . $file['error']]);
    exit;
}

// 1. Initial Limits
define('DEMO_MAX_ZIP_SIZE', 50 * 1024 * 1024); // 50 MB
define('DEMO_MAX_UNCOMPRESSED_SIZE', 100 * 1024 * 1024); // 100 MB
define('DEMO_MAX_FILES', 500);

if ($file['size'] > DEMO_MAX_ZIP_SIZE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ZIP file exceeds 50MB limit.']);
    exit;
}

$originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($originalExt !== 'zip') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Only ZIP archives are supported for demos.']);
    exit;
}

// 2. Open ZIP and Validate Entire Structure
$zip = new ZipArchive();
if ($zip->open($file['tmp_name']) !== true) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Failed to open ZIP archive. It may be corrupted.']);
    exit;
}

$totalUncompressedSize = 0;
$dangerousExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'inc', 'cgi', 'pl', 'py', 'pyc', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'ps1'];

$allNormalizedPaths = [];
$indexHtmlPaths = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    $filename = $stat['name'];

    // Resource limits
    if ($i > DEMO_MAX_FILES) {
        $zip->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Archive contains too many files (max ' . DEMO_MAX_FILES . ').']);
        exit;
    }

    $totalUncompressedSize += $stat['size'];
    if ($totalUncompressedSize > DEMO_MAX_UNCOMPRESSED_SIZE) {
        $zip->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Uncompressed contents exceed 100MB limit.']);
        exit;
    }

    // Path traversal & OS specific absolute paths protection
    if (
        strpos($filename, '../') !== false ||
        strpos($filename, '..\\') !== false ||
        str_starts_with($filename, '/') ||
        preg_match('#^[a-zA-Z]:\\\\#', $filename) // Windows drive letter
    ) {
        $zip->close();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Path traversal detected in archive. Upload rejected.']);
        exit;
    }

    $normalizedFilename = ltrim($filename, './\\');

    // Ignore macOS metadata for structural analysis, but keep checking it for dangerous extensions below
    if (!str_starts_with($normalizedFilename, '__MACOSX/') && $normalizedFilename !== '') {
        $allNormalizedPaths[] = $normalizedFilename;
    }

    if (basename($normalizedFilename) === 'index.html') {
        $indexHtmlPaths[] = $normalizedFilename;
    }

    // Dangerous extensions — check ALL files regardless of compression method
    if ($stat['size'] > 0 && substr($filename, -1) !== '/') {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $dangerousExtensions, true)) {
            $zip->close();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Executable server-side file detected ({$filename}). Upload rejected."]);
            exit;
        }
    }
}

$hasRootIndex = false;
$wrapperCandidates = [];

foreach ($indexHtmlPaths as $indexPath) {
    if ($indexPath === 'index.html') {
        $hasRootIndex = true;
    } else {
        $parts = explode('/', $indexPath);
        if (count($parts) === 2) {
            $wrapperCandidates[$parts[0]] = true;
        }
    }
}

if ($hasRootIndex && count($wrapperCandidates) > 0) {
    $zip->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The ZIP archive must contain either a root index.html or a single top-level folder containing index.html, not both.']);
    exit;
}

$validWrapper = null;

if (!$hasRootIndex) {
    $topLevelDirs = [];
    foreach ($allNormalizedPaths as $path) {
        $parts = explode('/', $path);
        $topLevelDirs[$parts[0]] = true;
    }

    // If there is exactly one top-level component, it could be a wrapper directory
    if (count($topLevelDirs) === 1) {
        $possibleWrapper = array_key_first($topLevelDirs);
        $expectedIndex = $possibleWrapper . '/index.html';

        foreach ($indexHtmlPaths as $indexPath) {
            if ($indexPath === $expectedIndex) {
                $validWrapper = $possibleWrapper;
                break;
            }
        }
    }
}

if (!$hasRootIndex && $validWrapper === null) {
    $zip->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The ZIP archive must contain index.html either at the root or inside a single top-level folder.']);
    exit;
}

// 3. Extract to Temporary Directory
$demosBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/demos';
if (!is_dir($demosBaseDir)) {
    mkdir($demosBaseDir, 0755, true);
}

$tempDir = $demosBaseDir . '/tmp_' . $productId . '_' . bin2hex(random_bytes(4));
if (!mkdir($tempDir, 0755, true)) {
    $zip->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create temporary extraction directory.']);
    exit;
}

if (!$zip->extractTo($tempDir)) {
    $zip->close();
    // Cleanup temp on failure
    removeDirRecursive($tempDir);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to extract ZIP contents.']);
    exit;
}
// Capture before close(): ZipArchive properties are unusable after close().
$zipFileCount = (int) $zip->numFiles;
$zip->close();

// 4. Atomic Swap
$finalDir = $demosBaseDir . '/' . $productId;
$backupDir = $demosBaseDir . '/backup_' . $productId . '_' . bin2hex(random_bytes(4));

if (is_dir($finalDir)) {
    if (!rename($finalDir, $backupDir)) {
        removeDirRecursive($tempDir);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to backup existing demo. Deployment aborted.']);
        exit;
    }
}

$sourceDir = $tempDir;
if ($validWrapper !== null) {
    $sourceDir = $tempDir . '/' . $validWrapper;
}

if (!rename($sourceDir, $finalDir)) {
    // Swap failed, restore backup if exists
    if (is_dir($backupDir)) {
        rename($backupDir, $finalDir);
    }
    removeDirRecursive($tempDir);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to finalize demo deployment.']);
    exit;
}

if (is_dir($tempDir)) {
    removeDirRecursive($tempDir);
}

// Cleanup backup
if (is_dir($backupDir)) {
    removeDirRecursive($backupDir);
}

// 5. Update Database
$liveDemoPath = (string)$productId;
$updateStmt = $pdo->prepare('UPDATE products SET live_demo_source = ?, live_demo_path = ? WHERE id = ?');
$updateStmt->execute(['uploaded', $liveDemoPath, $productId]);

// ADM-001: audit log — record the successful demo upload.
// Log only non-sensitive metadata: file count, file size, destination folder.
// Never log file contents, absolute paths, or any credential.
logAdminAction(
    'product.demo.upload',
    'product',
    (string) $productId,
    json_encode([
        'file_count'   => $zipFileCount,
        'size_kb'      => (int) round($file['size'] / 1024),
        'dest_folder'  => $productId,   // folder name = product id (numeric)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo json_encode([
    'success' => true,
    'message' => 'Static demo uploaded and deployed successfully.',
    'live_demo_source' => 'uploaded',
    'live_demo_path' => $liveDemoPath
]);
