<?php
// ============================================================
// REVIEWS — DELETE (admin only)
// POST /api/reviews/delete.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {id}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرّف المراجعة غير صالح.']);
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

    $deleteStmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
    $deleteStmt->execute([$id]);

    logAdminAction('review.delete', 'review', (string) $id, json_encode([
        'id' => $id,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف المراجعة بنجاح.',
    ]);

} catch (PDOException $e) {

    error_log('[reviews/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
