<?php
// ============================================================
// CRON — OPTIMIZE EXISTING IMAGES (CLI ONLY)
// cron/optimize_existing_images.php
//
// Batch processes existing legacy images into WebP and responsive variants:
//   --batch=N         Number of images to process per run (default: 50)
//   --dry-run         Simulate and report without modifying disk
//   --force           Regenerate variants from non-WebP originals only
//   --include-assets  Include images in public_html/assets/ (default: false)
//
// Invariants:
//   - Strictly CLI only.
//   - Resumable: skips already-optimized images.
//   - Never re-compresses existing WebP files.
//   - Atomic writes via temporary files then rename.
//   - Memory cleanup per image to prevent memory leaks across batches.
// ============================================================

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/helpers/image_optimizer.php';

// Parse command-line options
$longopts = [
    'batch::',
    'dry-run',
    'force',
    'include-assets',
    'help',
];
$options = getopt('', $longopts);

if (isset($options['help'])) {
    echo "Usage: php cron/optimize_existing_images.php [options]\n";
    echo "Options:\n";
    echo "  --batch=N         Number of images to process (default: 50)\n";
    echo "  --dry-run         Inspect and report without writing to disk\n";
    echo "  --force           Regenerate variants (from non-WebP originals only)\n";
    echo "  --include-assets  Also optimize non-upload images in /assets/\n";
    echo "  --help            Show this help message\n";
    exit(0);
}

$batchSize     = isset($options['batch']) ? max(1, (int) $options['batch']) : 50;
$dryRun        = isset($options['dry-run']);
$force         = isset($options['force']);
$includeAssets = isset($options['include-assets']);

echo "============================================================\n";
echo " IMAGE OPTIMIZATION PIPELINE — BATCH PROCESSOR\n";
echo "============================================================\n";

$caps = imageOptimizerCapabilities();
echo "PHP Version:        " . PHP_VERSION . "\n";
echo "GD Version:         " . $caps['gd_version'] . "\n";
echo "WebP Support:       " . ($caps['webp_support'] ? 'YES' : 'NO') . "\n";
echo "JPEG Support:       " . ($caps['jpeg_support'] ? 'YES' : 'NO') . "\n";
echo "PNG Support:        " . ($caps['png_support'] ? 'YES' : 'NO') . "\n";
echo "EXIF Support:       " . ($caps['exif_loaded'] ? 'YES' : 'NO') . "\n";
echo "Memory Limit:       " . $caps['memory_limit'] . " (" . round($caps['memory_bytes'] / 1024 / 1024) . " MB)\n";
echo "Execution Mode:     " . ($dryRun ? 'DRY-RUN (no changes made)' : 'EXECUTE') . "\n";
echo "Batch Limit:        {$batchSize}\n";
echo "Force Regenerate:   " . ($force ? 'YES (non-WebP originals only)' : 'NO') . "\n";
echo "Include /assets/:   " . ($includeAssets ? 'YES' : 'NO') . "\n";
echo "------------------------------------------------------------\n";

if (!$caps['can_optimize']) {
    echo "ERROR: GD extension or WebP support is missing. Aborting.\n";
    exit(1);
}

// Connect to DB if possible to sync media_assets
$pdo = null;
try {
    $dbFile = dirname(__DIR__) . '/api/db.php';
    if (file_exists($dbFile)) {
        require_once $dbFile;
        if (function_exists('getDB')) {
            $pdo = getDB();
        }
    }
} catch (Throwable $e) {
    echo "Notice: Database connection unavailable (" . $e->getMessage() . "). Skipping media_assets registry sync.\n";
}

// Scan directories
$scanDirs = [UPLOAD_DIR];
if ($includeAssets) {
    $assetsDir = dirname(__DIR__) . '/assets/';
    if (is_dir($assetsDir)) {
        $scanDirs[] = $assetsDir;
    }
}

$candidates = [];

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;

    $files = scandir($dir);
    if ($files === false) continue;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        // Skip variants themselves and temp files
        if (preg_match('/-(?:480|960)\.webp$/i', $file)) continue;
        if (str_ends_with($file, '.tmp')) continue;

        $filePath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
        if (!is_file($filePath)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) continue;

        $candidates[] = [
            'path'     => $filePath,
            'filename' => $file,
            'ext'      => $ext,
            'dir'      => $dir,
        ];
    }
}

echo "Found " . count($candidates) . " candidate image file(s).\n\n";

$processedCount = 0;
$skippedCount   = 0;
$errorCount     = 0;
$totalBytesIn   = 0;
$totalBytesOut  = 0;

foreach ($candidates as $cand) {
    if ($processedCount >= $batchSize) {
        echo "\nBatch limit of {$batchSize} reached. Run again to process subsequent files.\n";
        break;
    }

    $srcPath = $cand['path'];
    $file    = $cand['filename'];
    $ext     = $cand['ext'];
    $dir     = $cand['dir'];

    $baseName = preg_replace('/\.(?:jpe?g|png|gif|webp)$/i', '', $file);
    $destBase = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $baseName;

    // Resumable check
    // If original is non-WebP:
    // Check if destBase . '.webp' exists AND -480/-960 exist (or were checked)
    if (!$force) {
        if ($ext !== 'webp') {
            $webpTarget = $destBase . '.webp';
            if (is_file($webpTarget)) {
                $skippedCount++;
                continue;
            }
        } else {
            // Already a WebP file: check if variants exist
            $v480 = $destBase . '-480.webp';
            if (is_file($v480)) {
                $skippedCount++;
                continue;
            }
        }
    } else {
        // With --force:
        // Rule: regenerate from non-WebP originals only!
        if ($ext === 'webp') {
            $skippedCount++;
            continue;
        }
    }

    $fileSize = filesize($srcPath);
    $totalBytesIn += $fileSize;

    if ($dryRun) {
        echo "[DRY-RUN] Would process: {$file} (" . round($fileSize / 1024, 1) . " KB)\n";
        $processedCount++;
        continue;
    }

    try {
        $opt = optimizeImage($srcPath, $destBase);
        $processedCount++;

        $outSize = $opt['size'];
        $totalBytesOut += $outSize;
        $savedPct = ($fileSize > 0) ? round((($fileSize - $outSize) / $fileSize) * 100, 1) : 0;

        $variantList = array_keys($opt['variants'] ?? []);
        $variantStr  = empty($variantList) ? 'none' : implode('w, ', $variantList) . 'w';

        $actionNote = $opt['is_original'] ? 'kept original' : "-> WebP ({$savedPct}% saved)";
        echo "[OK] {$file} (" . round($fileSize / 1024, 1) . " KB) {$actionNote}, variants: {$variantStr}\n";

        // Sync with media_assets if DB is connected and this is an upload
        if ($pdo !== null && str_starts_with($dir, UPLOAD_DIR)) {
            try {
                recordMediaAsset($pdo, [
                    'file_name' => basename($opt['main_path']),
                    'file_path' => $opt['main_url'],
                    'file_size' => $opt['size'],
                    'mime_type' => $opt['mime'],
                    'width'     => $opt['width'],
                    'height'    => $opt['height'],
                ]);
            } catch (Throwable $e) {
                // Non-fatal
            }
        }

    } catch (Throwable $e) {
        $errorCount++;
        echo "[ERROR] Failed processing {$file}: " . $e->getMessage() . "\n";
    }
}

echo "\n============================================================\n";
echo " SUMMARY\n";
echo "============================================================\n";
echo "Processed:    {$processedCount}\n";
echo "Skipped:      {$skippedCount}\n";
echo "Errors:       {$errorCount}\n";

if (!$dryRun && $processedCount > 0 && $totalBytesIn > 0) {
    $netSaved = $totalBytesIn - $totalBytesOut;
    $overallPct = round(($netSaved / $totalBytesIn) * 100, 1);
    echo "Bytes In:     " . round($totalBytesIn / 1024, 1) . " KB\n";
    echo "Bytes Out:    " . round($totalBytesOut / 1024, 1) . " KB\n";
    echo "Net Savings:  " . round($netSaved / 1024, 1) . " KB ({$overallPct}%)\n";
}
echo "============================================================\n";

