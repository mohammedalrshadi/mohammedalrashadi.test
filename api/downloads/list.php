<?php
// ============================================================
// USER DOWNLOADS — LIST
// GET /api/downloads/list.php
// Requires: authenticated user
// Returns: history of downloaded free digital resources
// (Filesystem download paths are NEVER exposed)
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireUserAuth();

$userId = currentUserId();

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT d.id AS download_id, d.product_id, d.product_title, d.downloaded_at,
                p.title AS live_title, p.slug, p.thumbnail, p.status, p.product_type
         FROM user_downloads d
         LEFT JOIN products p ON d.product_id = p.id
         WHERE d.user_id = ?
         ORDER BY d.downloaded_at DESC"
    );
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $item['display_title'] = !empty($item['live_title']) ? $item['live_title'] : $item['product_title'];
        $item['url'] = !empty($item['slug']) ? ('/store/' . rawurlencode($item['slug'])) : '#';
        $item['download_url'] = !empty($item['product_id']) ? ('/api/products/download.php?id=' . (int)$item['product_id']) : null;
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'count'   => count($items),
        'data'    => $items
    ]);

} catch (Exception $e) {
    error_log('[downloads/list] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve download history.']);
}

