<?php
// ============================================================
// HOME SHOWCASE — LIST
// GET /api/showcase/list.php
//
// Query parameters:
//   ?admin=1  → Admin mode: returns all items (enabled & disabled)
//               with editing fields and validation flags.
//               Requires active admin session.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$forAdmin = !empty($_GET['admin']) && isAdminLoggedIn();

try {
    $pdo = getDB();

    $sql = "SELECT id, item_type, reference_id, post_id, title_override, description_override, 
                   image_url, alt_text, link_url, is_enabled, sort_order, created_at, updated_at 
            FROM home_showcase_items ";
    
    if (!$forAdmin) {
        $sql .= " WHERE is_enabled = 1 ";
    }
    
    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $pdo->query($sql);
    $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rawItems as $raw) {
        $resolved = resolveShowcaseItem($pdo, $raw, $forAdmin);
        if ($resolved !== null) {
            $items[] = $resolved;
        }
    }

    echo json_encode([
        'success' => true,
        'count'   => count($items),
        'admin'   => $forAdmin,
        'data'    => $items
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log('[api/showcase/list.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load showcase items.'
    ]);
}
