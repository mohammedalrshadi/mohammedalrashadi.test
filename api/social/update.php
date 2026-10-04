<?php
// ============================================================
// SOCIAL — UPDATE PLATFORMS [IMP-027 / IMP-035]
// POST /api/social/update.php
// Updates URLs, visibility, names, icon_key, and sort_order for social platforms.
// Requires: authenticated admin session + valid CSRF token.
// Body (JSON): {"platforms": [{"platform": "github", "name": "GitHub", "icon_key": "github", "url": "https://...", "is_enabled": 1, "sort_order": 0}, ...]}
// ============================================================

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

// Enforce authenticated admin session and CSRF token
requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$allowedPlatforms = ['github', 'x', 'tiktok', 'linkedin', 'instagram', 'facebook', 'youtube'];
$allowedIcons     = ['github', 'linkedin', 'x', 'instagram', 'facebook', 'tiktok', 'youtube', 'email', 'globe', 'external'];

// Helper to validate submitted social profile URL
function validateSocialUrl(string $url): bool {
    $trimmed = trim($url);
    if ($trimmed === '') {
        return true; // Empty string allowed (removes/clears the URL)
    }

    // Must be a valid URL format
    if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
        return false;
    }

    // Must have a valid web scheme (http or https) and host
    $parsed = parse_url($trimmed);
    if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
        return false;
    }

    $scheme = strtolower($parsed['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }

    // Reject protocol-relative URLs
    if (strpos($trimmed, '//') === 0) {
        return false;
    }

    return true;
}

$body = file_get_contents('php://input');
$input = json_decode($body, true);

$platformEntries = null;
if (is_array($input)) {
    if (isset($input['platforms']) && is_array($input['platforms'])) {
        $platformEntries = $input['platforms'];
    } elseif (isset($input['channels']) && is_array($input['channels'])) {
        $platformEntries = $input['channels'];
    }
}

if ($platformEntries === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'بيانات الطلب غير صالحة.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate each submitted platform entry
$updates = [];
foreach ($platformEntries as $item) {
    if (!is_array($item)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'تنسيق عنصر المنصة غير صالح.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $platform = isset($item['platform']) ? strtolower(trim((string)$item['platform'])) : '';
    if (!in_array($platform, $allowedPlatforms, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "منصة غير مدعومة: {$platform}",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $url = isset($item['url']) ? trim((string)$item['url']) : '';
    if (!validateSocialUrl($url)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "الرابط المدخل للمنصة ({$platform}) غير صالح. يجب أن يبدأ بـ http:// أو https://",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate and sanitize display name
    $name = isset($item['name']) ? trim((string)$item['name']) : '';
    if ($name === '') {
        $name = ucfirst($platform);
    }
    if (mb_strlen($name, 'UTF-8') > 100) {
        $name = mb_substr($name, 0, 100, 'UTF-8');
    }

    // Validate icon_key
    $iconKey = isset($item['icon_key']) ? strtolower(trim((string)$item['icon_key'])) : '';
    if ($iconKey === '') {
        $iconKey = in_array($platform, $allowedIcons, true) ? $platform : 'globe';
    } elseif (!in_array($iconKey, $allowedIcons, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "الأيقونة المحددة ({$iconKey}) غير مدعومة.",
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sort order
    $sortOrder = isset($item['sort_order']) ? max(0, (int)$item['sort_order']) : 0;

    // Visibility toggle
    $isEnabled = !empty($item['is_enabled']) ? 1 : 0;

    $updates[$platform] = [
        'name'       => $name,
        'icon_key'   => $iconKey,
        'url'        => $url,
        'is_enabled' => $isEnabled,
        'sort_order' => $sortOrder,
    ];
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم إرسال أي منصات للتحديث.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO social_links (platform, name, icon_key, url, is_enabled, sort_order)
         VALUES (:platform, :name, :icon_key, :url, :is_enabled, :sort_order)
         ON DUPLICATE KEY UPDATE
             name       = VALUES(name),
             icon_key   = VALUES(icon_key),
             url        = VALUES(url),
             is_enabled = VALUES(is_enabled),
             sort_order = VALUES(sort_order)"
    );

    foreach ($updates as $platform => $data) {
        $stmt->execute([
            ':platform'   => $platform,
            ':name'       => $data['name'],
            ':icon_key'   => $data['icon_key'],
            ':url'        => $data['url'],
            ':is_enabled' => $data['is_enabled'],
            ':sort_order' => $data['sort_order'],
        ]);
    }

    $pdo->commit();

    logAdminAction('social.update', 'social_links', null, json_encode([
        'platforms' => array_keys($updates),
        'count'     => count($updates),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم حفظ حسابات التواصل الاجتماعي بنجاح.',
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[social/update] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving settings.',
    ], JSON_UNESCAPED_UNICODE);
}
