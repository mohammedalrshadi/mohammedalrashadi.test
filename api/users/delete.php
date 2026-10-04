<?php
// ============================================================
// USERS — DELETE
// POST /api/users/delete.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {id}
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

// ---- Parse body ------------------------------------------------------
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرّف المستخدم غير صالح.']);
    exit;
}

// ---- SELF-DELETION PROTECTION -----------------------------------------
// An admin can never delete their own account through this endpoint,
// regardless of how many other admins exist.
if ($id === currentUserId()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'لا يمكنك حذف حسابك الخاص.',
    ]);
    exit;
}

try {

    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود.']);
        exit;
    }

    // ---- LAST-ADMIN PROTECTION -----------------------------------------
    if ($target['role'] === 'admin') {

        $countStmt = $pdo->query(
            "SELECT COUNT(*) AS admin_count FROM users WHERE role = 'admin'"
        );
        $adminCount = (int) $countStmt->fetch()['admin_count'];

        if ($adminCount <= 1) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'لا يمكن حذف آخر مسؤول في النظام. أضف مسؤولاً آخر أولاً.',
            ]);
            exit;
        }

    }

    $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $deleteStmt->execute([$id]);

    logAdminAction('user.delete', 'user', (string) $id, json_encode([
        'role' => $target['role'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف المستخدم بنجاح.',
    ]);

} catch (PDOException $e) {

    error_log('[users/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
