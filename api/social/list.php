<?php
// ============================================================
// SOCIAL — PUBLIC LIST [IMP-027 / IMP-035]
// GET /api/social/list.php
// Returns ONLY enabled platforms with non-empty URLs.
// Public access: requires NO authentication.
// Ignores all query parameters.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT platform, name, url, sort_order, icon_key
         FROM social_links
         WHERE is_enabled = 1
           AND url IS NOT NULL
           AND TRIM(url) <> ''
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $row) {
        $platform = (string) $row['platform'];
        $iconKey  = !empty($row['icon_key']) ? (string) $row['icon_key'] : $platform;
        $data[] = [
            'platform'   => $platform,
            'name'       => (string) $row['name'],
            'url'        => (string) $row['url'],
            'sort_order' => (int) $row['sort_order'],
            'icon_key'   => $iconKey,
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[social/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again later.',
    ], JSON_UNESCAPED_UNICODE);
}
