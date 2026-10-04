<?php
// ============================================================
// SUPPORT — UPDATE MESSAGE STATUS (ADMIN)
// POST /api/support/update.php
// Requires: authenticated admin session + CSRF token
// Payload (JSON): { id: int, status: string }
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

$id     = isset($input['id']) ? (int)$input['id'] : 0;
$status = strtolower(trim($input['status'] ?? ''));

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID.']);
    exit;
}

$validStatuses = ['new', 'in_progress', 'resolved', 'archived'];
if (!in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)]);
    exit;
}

try {
    $pdo = getDB();

    // Check message existence
    $checkStmt = $pdo->prepare("SELECT id, status, subject FROM support_messages WHERE id = ? LIMIT 1");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Support message not found.']);
        exit;
    }

    $oldStatus = $existing['status'];
    $stmt = $pdo->prepare("UPDATE support_messages SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);

    // Audit log
    if (function_exists('logAdminAction')) {
        logAdminAction('support.update', 'support_message', (string) $id, json_encode([
            'old_status' => $oldStatus,
            'new_status' => $status,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket status updated successfully.',
    ]);

} catch (Throwable $e) {
    error_log('[api/support/update] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update ticket status.']);
}

