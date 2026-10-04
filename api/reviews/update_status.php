<?php
// ============================================================
// REVIEWS — UPDATE STATUS (admin only)
// POST /api/reviews/update_status.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {id, status}   status: pending|approved|rejected
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

// Auth + CSRF checks (each exits on failure)
requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

$id     = isset($input['id'])     ? (int) $input['id']     : 0;
$status = isset($input['status']) ? trim($input['status']) : '';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرّف المراجعة غير صالح.']);
    exit;
}

// Never accept an arbitrary status string — whitelist only
if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'الحالة غير صالحة.']);
    exit;
}

try {

    $pdo = getDB();

    $checkStmt = $pdo->prepare('SELECT id FROM reviews WHERE id = ? LIMIT 1');
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المراجعة غير موجودة.']);
        exit;
    }

    $updateStmt = $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?');
    $updateStmt->execute([$status, $id]);

    logAdminAction('review.update_status', 'review', (string) $id, json_encode([
        'status' => $status,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث حالة المراجعة بنجاح.',
    ]);

} catch (PDOException $e) {

    error_log('[reviews/update_status] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
