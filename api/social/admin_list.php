<?php
// ============================================================
// SOCIAL — ADMIN LIST [IMP-027 / IMP-035]
// GET /api/social/admin_list.php
// Returns ALL platforms (enabled & disabled) for management.
// Requires: authenticated admin session (enforced via guard.php)
// ============================================================

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

// Enforce authenticated admin session (exits with 401 if unauthenticated/non-admin)
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, platform, name, icon_key, url, is_enabled, sort_order
         FROM social_links
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $row) {
        $platform = (string) $row['platform'];
        $iconKey  = !empty($row['icon_key']) ? (string) $row['icon_key'] : $platform;
        $data[] = [
            'id'         => (int) $row['id'],
            'platform'   => $platform,
            'name'       => (string) $row['name'],
            'icon_key'   => $iconKey,
            'url'        => (string) $row['url'],
            'is_enabled' => (int) $row['is_enabled'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[social/admin_list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error. Please try again later.',
    ], JSON_UNESCAPED_UNICODE);
}
