<?php
// ============================================================
// USERS — MARK AS VERIFIED (ADMIN ACTION)
// POST /api/users/verify.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {"id": 123}
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

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

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data payload.']);
    exit;
}

$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, name, email, email_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    if (!empty($target['email_verified_at'])) {
        echo json_encode([
            'success' => true,
            'message' => 'User is already verified.',
            'verified_at' => $target['email_verified_at'],
        ]);
        exit;
    }

    $verifiedAt = date('Y-m-d H:i:s');
    $upStmt = $pdo->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?');
    $upStmt->execute([$verifiedAt, $id]);

    // Invalidate pending verification tokens
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND used_at IS NULL')->execute([$id]);

    // Audit log
    logAdminAction(
        'user.verify',
        'user',
        (string) $id,
        json_encode([
            'email'             => $target['email'],
            'manually_verified' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    echo json_encode([
        'success'     => true,
        'message'     => "User '{$target['email']}' has been marked as verified.",
        'verified_at' => $verifiedAt,
    ]);

} catch (PDOException $e) {
    error_log('[users/verify] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to mark user as verified.']);
}

