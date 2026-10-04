<?php
// ============================================================
// USER LIBRARY — LIST
// GET /api/library/list.php
// Requires: authenticated user
// Returns: claimed digital products in the user's library
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
        "SELECT l.id AS library_id, l.product_id, l.access_type, l.created_at AS claimed_at,
                p.title, p.slug, p.short_description, p.category, p.thumbnail, p.product_type, p.status, p.price_display
         FROM user_library l
         INNER JOIN products p ON l.product_id = p.id
         WHERE l.user_id = ?
         ORDER BY l.created_at DESC"
    );
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format URLs and display fields
    foreach ($items as &$item) {
        $item['url'] = '/store/' . rawurlencode($item['slug']);
        $item['download_url'] = '/api/products/download.php?id=' . (int)$item['product_id'];
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'count'   => count($items),
        'data'    => $items
    ]);

} catch (Exception $e) {
    error_log('[library/list] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve library items.']);
}

