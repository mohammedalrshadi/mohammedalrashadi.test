<?php
// ============================================================
// USERS — CREATE
// POST /api/users/create.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {name, email, password, role}
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

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

$name     = isset($input['name'])     ? trim($input['name'])     : '';
$email    = isset($input['email'])    ? trim($input['email'])    : '';
$password = isset($input['password']) ? $input['password']       : '';
$role     = isset($input['role'])     ? trim($input['role'])     : '';

// ---- Validate ----------------------------------------------------------

if ($name === '' || safeStrlen($name) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'الاسم مطلوب (وبحد أقصى 255 حرفاً).']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || safeStrlen($email) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني غير صالح.']);
    exit;
}

if (safeStrlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

// Never accept an arbitrary role string — whitelist only
if (!in_array($role, ['user', 'admin'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'الصلاحية غير صالحة.']);
    exit;
}

// ---- Insert --------------------------------------------------------------
try {

    $pdo = getDB();

    // Check for existing email first, for a clean error message
    // (the UNIQUE constraint is still the real guarantee — this is UX only)
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل.']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, email_verified_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$name, $email, $passwordHash, $role]);

    $newId = (int) $pdo->lastInsertId();

    logAdminAction('user.create', 'user', (string) $newId, json_encode([
        'name'  => $name,
        'email' => $email,
        'role'  => $role,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء المستخدم بنجاح.',
        'id'      => $newId,
    ]);

} catch (PDOException $e) {

    // Race-condition fallback if the UNIQUE constraint catches a
    // duplicate email that slipped past the check above
    if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل.']);
        exit;
    }

    error_log('[users/create] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
