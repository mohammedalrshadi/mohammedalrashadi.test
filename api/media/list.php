<?php
// ============================================================
// MEDIA ASSETS — LIST (ADMIN PROTECTED)
// GET /api/media/list.php
//
// Returns registered media assets with dimensions and file sizes.
// Falls back to scanning /uploads/ if registry table is empty.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

function formatBytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / (1024 * 1024), 2) . ' MB';
}

$items = [];
$pdo = null;

if (file_exists(dirname(dirname(__DIR__)) . '/api/config.local.php')) {
    try {
        require_once dirname(dirname(__DIR__)) . '/api/db.php';
        $pdo = getDB();
    } catch (Throwable $e) {
        $pdo = null;
    }
}

if ($pdo !== null) {
    try {
        $hasDim = mediaAssetsHasDimensions($pdo);
        $sql = $hasDim
            ? "SELECT id, file_name, file_path, file_size, mime_type, width, height, created_at FROM media_assets ORDER BY created_at DESC"
            : "SELECT id, file_name, file_path, file_size, mime_type, NULL as width, NULL as height, created_at FROM media_assets ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $row['file_size_formatted'] = formatBytes((int)$row['file_size']);
            $items[] = $row;
        }
    } catch (Throwable $e) {
        // Fallback to directory scan below
    }
}

// If no database rows, scan /uploads/ directory to provide metadata
if (empty($items) && is_dir(UPLOAD_DIR)) {
    $files = scandir(UPLOAD_DIR);
    if ($files !== false) {
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || str_ends_with($f, '.tmp')) continue;
            // Skip responsive variants in main listing
            if (preg_match('/-(?:480|960)\.webp$/i', $f)) continue;

            $full = UPLOAD_DIR . $f;
            if (!is_file($full)) continue;

            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) continue;

            $size = filesize($full);
            $info = @getimagesize($full);

            $items[] = [
                'id'                  => null,
                'file_name'           => $f,
                'file_path'           => (defined('UPLOAD_URL_PATH') ? UPLOAD_URL_PATH : '/uploads/') . $f,
                'file_size'           => $size,
                'file_size_formatted' => formatBytes((int)$size),
                'mime_type'           => $info['mime'] ?? ('image/' . $ext),
                'width'               => $info ? $info[0] : null,
                'height'              => $info ? $info[1] : null,
                'created_at'          => date('Y-m-d H:i:s', filemtime($full)),
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'data'    => $items,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

