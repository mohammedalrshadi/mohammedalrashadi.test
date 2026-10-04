<?php
// ============================================================
// AUTH — LOGOUT
// POST /api/auth/logout.php
// ============================================================

// guard.php provides _startSecureSession() — the same hardened
// session bootstrap used by every other auth endpoint. Using it
// here ensures the Set-Cookie deletion header is issued with
// consistent params (Secure, HttpOnly, SameSite, strict mode).
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Start session with hardened params so we can destroy it
_startSecureSession();

// Log auth.logout ONLY for role=admin
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $adminEmail = isset($_SESSION['user_email']) ? (string) $_SESSION['user_email'] : '';
    logAdminAction(
        'auth.logout',
        'auth',
        (string) $adminId,
        json_encode(['email' => mb_substr($adminEmail, 0, 100, 'UTF-8')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'تم تسجيل الخروج بنجاح.',
]);
