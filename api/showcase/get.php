<?php
// ============================================================
// HOME SHOWCASE — GET SINGLE ITEM
// GET /api/showcase/get.php?id=123
// Returns a single resolved showcase item for admin editing.
// Protected: requires admin authentication.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid showcase item ID is required.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, item_type, reference_id, post_id, title_override, description_override, 
                image_url, alt_text, link_url, is_enabled, sort_order, created_at, updated_at 
           FROM home_showcase_items 
          WHERE id = ? 
          LIMIT 1"
    );
    $stmt->execute([$id]);
    $raw = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$raw) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Showcase item not found.']);
        exit;
    }

    $resolved = resolveShowcaseItem($pdo, $raw, true);

    echo json_encode([
        'success' => true,
        'item'    => $resolved ?: $raw,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('[api/showcase/get.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load showcase item.']);
}

