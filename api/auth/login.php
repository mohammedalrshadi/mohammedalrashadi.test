<?php
// ============================================================
// AUTH — LOGIN
// POST /api/auth/login.php
// Body: {"email": "...", "password": "..."}
// ============================================================

// Suppress PHP notices/warnings — must not corrupt JSON output.
// Errors are still written to the server log (log_errors = On)
// so nothing meaningful is silently discarded.
error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/guard.php';

header('Content-Type: application/json');

// ---- Rate limiting (SEC-03) -------------------------------------------
// Checked before any credential lookup, so a locked-out IP costs the
// server nothing more than a JSON response.
$lockoutSecondsRemaining = loginRateLimitCheck();
if ($lockoutSecondsRemaining !== null && $lockoutSecondsRemaining > 0) {
    http_response_code(429);
    header('Retry-After: ' . $lockoutSecondsRemaining);
    $lockoutMinutes = (int) ceil($lockoutSecondsRemaining / 60);
    echo json_encode([
        'success' => false,
        'message' => "Too many login attempts. Please try again in {$lockoutMinutes} minute(s).",
    ]);
    exit;
}

// ---- Only accept POST -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Start session (required for CSRF validation) --------------------
_startSecureSession();

// ---- Parse JSON body -------------------------------------------------
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

// ---- CSRF check (SEC-CSRF) -------------------------------------------
// Both public and admin login forms seed a CSRF token via getCsrfToken().
// We strictly enforce that a valid token is provided and matches the session.
$sentCsrf     = isset($input['csrf_token']) ? $input['csrf_token'] : ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
// hash_equals() throws a TypeError on non-string input (e.g. a JSON array) — reject it as a 400 first.
if (!is_string($sentCsrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (empty($expectedCsrf) || empty($sentCsrf) || !hash_equals($expectedCsrf, $sentCsrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please refresh the page and try again.']);
    exit;
}

$email    = safe_trim($input['email']    ?? null);
$password = isset($input['password']) ? $input['password']       : '';

// password_verify() throws a TypeError on non-string input — reject it as a 400 first.
if (!is_string($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// ---- Basic validation ------------------------------------------------
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// ---- Rate limiting (Tier 1 & Tier 3 check for this account) -----------
// Checked before credential lookup / password_verify to avoid
// expensive bcrypt operations when the account/pair is already throttled.
$accountLockoutRemaining = loginRateLimitCheck($email);
if ($accountLockoutRemaining !== null && $accountLockoutRemaining > 0) {
    http_response_code(429);
    header('Retry-After: ' . $accountLockoutRemaining);
    $lockoutMinutes = (int) ceil($accountLockoutRemaining / 60);
    echo json_encode([
        'success' => false,
        'message' => "Too many login attempts. Please try again in {$lockoutMinutes} minute(s).",
    ]);
    exit;
}

// ---- Look up user — also fetch role, status, and verification state --
// The admin panel requires role = 'admin'.
try {

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role, status, email_verified_at FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

} catch (PDOException $e) {

    error_log('[login] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    exit;

} 

/**
 * Records failed admin-portal login attempt with admin_id 0, throttled to 1 row per IP per minute.
 * Never stores submitted password; truncates email to 100 chars.
 */
function _logFailedAdminLogin(string $email): void {
    try {
        $pdo = getDB();
        $ip = getClientIp();

        // Throttle to at most 1 row per IP per minute (60 seconds)
        $checkStmt = $pdo->prepare(
            "SELECT id FROM admin_audit_log 
             WHERE action = 'auth.login_failed' AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND) 
             LIMIT 1"
        );
        $checkStmt->execute([$ip]);
        if ($checkStmt->fetch()) {
            return; // Throttled
        }

        $truncatedEmail = mb_substr($email, 0, 100, 'UTF-8');
        $details = json_encode(['email' => $truncatedEmail], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $insertStmt = $pdo->prepare(
            "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address)
             VALUES (0, 'auth.login_failed', 'auth', NULL, ?, ?)"
        );
        $insertStmt->execute([$details, $ip]);
    } catch (Throwable $e) {
        error_log('[login] Failed to log admin login failure: ' . $e->getMessage());
    }
}

// ---- Verify password -------------------------------------------------
// Use the same generic error for wrong email OR wrong password.
// This prevents revealing whether an email account exists.
// Always run exactly one password_verify(), even when the email does not exist, so
// response time does not reveal whether an account exists. The dummy hash is a
// bcrypt hash of a random, discarded string (cost 10 = PASSWORD_DEFAULT on PHP <= 8.3);
// it is not a credential and cannot match a real login — the `!$user` guard below
// still rejects the request regardless of the verify result.
$dummyPasswordHash = '$2y$10$lC1s/9wmxK.JpY9m5NCloeV6e1lFGnI1.Ab8NMbhdQfpyxNM529h6';
$hashToVerify = ($user && is_string($user['password_hash']) && $user['password_hash'] !== '')
    ? $user['password_hash']
    : $dummyPasswordHash;
$passwordOk = password_verify($password, $hashToVerify);

if (!$user || !$passwordOk) {
    loginRateLimitRecordFailure($email);
    if (!empty($input['admin_only'])) {
        _logFailedAdminLogin($email);
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

// ---- Check account status ONLY AFTER password_verify succeeds ---------
if (isset($user['status']) && $user['status'] !== 'active') {
    loginRateLimitRecordFailure($email);
    if (!empty($input['admin_only'])) {
        _logFailedAdminLogin($email);
    }
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your account has been ' . ($user['status'] === 'suspended' ? 'suspended' : 'banned') . '. Please contact support.',
    ]);
    exit;
}

// ---- Enforce admin role if specifically requested by admin portal ----
$adminOnly = !empty($input['admin_only']);
if ($adminOnly && $user['role'] !== 'admin') {
    loginRateLimitRecordFailure($email);
    _logFailedAdminLogin($email);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Administrator privileges required.']);
    exit;
}

// ---- Successful login — reset failure counters for this IP & email --
loginRateLimitClear($email);

// ---- Session ---------------------------------------------------------
// Already started (with hardened cookie params) by _startSecureSession() above,
// which is required for the CSRF check — calling ini_set()/session_start() again
// here would be a no-op. Just rotate the ID.

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

$_SESSION['user_id']           = (int) $user['id'];
$_SESSION['user_name']         = $user['name'];
$_SESSION['user_email']        = $user['email'];
$_SESSION['user_role']         = $user['role'];
$_SESSION['email_verified_at'] = $user['email_verified_at'];
$_SESSION['login_time']        = time();

// Log auth.login ONLY for role=admin
if ($user['role'] === 'admin') {
    logAdminAction(
        'auth.login',
        'auth',
        (string) $user['id'],
        json_encode(['email' => mb_substr($user['email'], 0, 100, 'UTF-8')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    // Detect login from unfamiliar network prefix (IPv4 /24, IPv6 /64)
    try {
        require_once dirname(__DIR__) . '/helpers/alerts.php';
        $ip = getClientIp();
        $ipCheck = checkAndRecordAdminLoginIp((int)$user['id'], $ip);
        if ($ipCheck['is_new'] && !$ipCheck['is_initial_seed']) {
            createAdminAlert(
                'login.admin_new_ip',
                'warning',
                'Admin Login from Unfamiliar Network',
                'Admin ' . mb_substr($user['email'], 0, 80, 'UTF-8') . ' signed in from a new network prefix (' . $ipCheck['subnet'] . '). IP: ' . $ip . '.',
                'audit-log.php',
                'admin_new_ip_' . (int)$user['id'] . '_' . md5($ipCheck['subnet'])
            );
        }
    } catch (Throwable $alertEx) {
        error_log('[login] New IP alert error: ' . $alertEx->getMessage());
    }
}

// Record last_login_at
try {
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
} catch (Throwable $e) {
    // Non-critical, ignore
}

// Determine appropriate redirect destination
$redirectUrl = ($user['role'] === 'admin' && $adminOnly) ? '/admin/' : '/dashboard/';


echo json_encode([
    'success'  => true,
    'message'  => 'Authentication successful.',
    'name'     => $user['name'],
    'email'    => $user['email'],
    'role'     => $user['role'],
    'redirect' => $redirectUrl,
]);
