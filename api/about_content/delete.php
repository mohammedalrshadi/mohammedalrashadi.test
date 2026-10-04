<?php
// ============================================================
// ABOUT CONTENT BLOCKS — DELETE (soft) API
// POST /api/about_content/delete.php
// Requires: authenticated admin session + valid CSRF token.
// Payload: { "id": 123 }
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid content block id is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "UPDATE about_content_blocks SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL"
    );
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Content block not found or already deleted.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    logAdminAction('about.delete', 'about_block', (string) $id, json_encode([
        'id' => $id,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'message' => 'Content block deleted.'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[about_content/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while deleting the content block.'], JSON_UNESCAPED_UNICODE);
}
