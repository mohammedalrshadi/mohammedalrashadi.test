<?php
// ============================================================
// SETTINGS — DIRECT AVATAR UPLOAD API
// POST /api/settings/upload_avatar.php
// Requires: authenticated admin session + valid CSRF token
// Multipart: 'avatar' (or 'image')
// Validates image, moves to /uploads/, updates site_settings,
// and refreshes settings_cache.json.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/uploads/upload_helper.php';
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Determine field name (avatar or image)
$field = isset($_FILES['avatar']) ? 'avatar' : (isset($_FILES['image']) ? 'image' : '');

if ($field === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image file uploaded.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // processUploadedImage() validates size (5MB), MIME (finfo), extension, and getimagesize()
    $publicUrl = processUploadedImage($field);
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// Update database (canonical site_profile and site_settings)
try {
    $pdo = getDB();

    // 1. Update canonical site_profile table if it exists (production schema)
    try {
        $profRow = $pdo->query("SELECT id, profile_image_url FROM site_profile LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($profRow && !empty($profRow['id'])) {
            $oldAvatar = $profRow['profile_image_url'] ?? '';
            $stmtProf = $pdo->prepare("UPDATE site_profile SET profile_image_url = ? WHERE id = ?");
            $stmtProf->execute([$publicUrl, $profRow['id']]);

            // Clean up previous avatar and its variants if stored in /uploads/
            if (!empty($oldAvatar) && $oldAvatar !== $publicUrl && str_starts_with($oldAvatar, '/uploads/')) {
                deleteImageWithVariants($oldAvatar);
            }
        }
    } catch (Throwable $e) {
        // site_profile does not exist or column mismatch
        error_log('[upload_avatar] site_profile update note: ' . $e->getMessage());
    }

    // Phase 3 (docs/DATABASE.md): site_settings is confirmed to be the
    // singleton wide-row schema only — the key_group key-value schema
    // this used to conditionally update was never actually deployed,
    // so this block always did nothing in production. Removed rather
    // than kept as dead tolerance (same fix as includes/settings.php).
    // The homepage/head avatar reference actually used in practice is
    // site_profile.profile_image_url, updated above.

    // Refresh settings cache
    $cacheFile = dirname(__DIR__) . '/data/settings_cache.json';
    try {
        $allGrouped = getAllGroupedSettings(false);
        $flattened = [];
        foreach ($allGrouped as $grp => $grpVals) {
            foreach ($grpVals as $k => $v) {
                $flattened["{$grp}.{$k}"] = $v;
            }
        }
        @file_put_contents($cacheFile, json_encode($flattened, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    } catch (Throwable $e) {
        error_log('[upload_avatar] Cache write error: ' . $e->getMessage());
    }

    logAdminAction('settings.avatar', 'settings', null, json_encode([
        'avatar_url' => $publicUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success'    => true,
        'message'    => 'Profile image updated successfully.',
        'avatar_url' => $publicUrl,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[upload_avatar] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving profile image: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
