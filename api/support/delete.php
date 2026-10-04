<?php
// ============================================================
// SUPPORT — DELETE MESSAGE (ADMIN)
// POST /api/support/delete.php
// Requires: authenticated admin session + CSRF token
// Payload (JSON): { id: int }
// ============================================================

declare(strict_types=1);

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

requireAuth();


requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID.']);
    exit;
}

try {
    $pdo = getDB();

    // Check message existence
    $checkStmt = $pdo->prepare("SELECT id, subject, email FROM support_messages WHERE id = ? LIMIT 1");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Support message not found.']);
        exit;
    }

    $delStmt = $pdo->prepare("DELETE FROM support_messages WHERE id = ?");
    $delStmt->execute([$id]);

    // Audit log
    if (function_exists('logAdminAction')) {
        logAdminAction('support.delete', 'support_message', (string) $id, json_encode([
            'subject' => mb_substr($existing['subject'], 0, 100, 'UTF-8'),
            'email'   => mb_substr($existing['email'], 0, 100, 'UTF-8'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Support message deleted successfully.',
    ]);

} catch (Throwable $e) {
    error_log('[api/support/delete] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete support message.']);
}

