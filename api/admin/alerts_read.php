<?php
// ============================================================
// ADMIN ALERTS MARK AS READ ENDPOINT
// api/admin/alerts_read.php
//
// POST /api/admin/alerts_read.php
// Body: { "all": true } OR { "id": 123 }
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/alerts.php';

// Verify admin authentication
requireAuth();

// Enforce CSRF protection on mutating state change
requireCSRF();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode((string)$rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

try {
    $pdo = getDB();

    $markAll = !empty($data['all']);
    $alertId = isset($data['id']) ? (int)$data['id'] : null;

    if (!$markAll && ($alertId === null || $alertId <= 0)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing alert ID or all flag.']);
        exit;
    }

    $updatedCount = markAdminAlertsRead($pdo, $alertId, $markAll);
    $counts = getAdminAlertCounts($pdo);

    echo json_encode([
        'success'      => true,
        'updated'      => $updatedCount,
        'unread_count' => $counts['unread_alerts'],
        'counts'       => $counts,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log('[api/admin/alerts_read] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark alert as read.',
    ]);
}

