<?php
// ============================================================
// AUTH — REGISTRATION
// POST /api/auth/register.php
// Body: {"name": "...", "email": "...", "password": "...", "confirm_password": "..."}
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/rate_limit.php';
require_once dirname(__DIR__) . '/helpers/mailer.php';
require_once dirname(__DIR__) . '/helpers/email_templates.php';


header('Content-Type: application/json');

// 1. Rate limiting check (login brute-force + registration IP throttle)
$lockoutSecondsRemaining = loginRateLimitCheck();
if ($lockoutSecondsRemaining !== null && $lockoutSecondsRemaining > 0) {
    http_response_code(429);
    header('Retry-After: ' . $lockoutSecondsRemaining);
    $lockoutMinutes = (int) ceil($lockoutSecondsRemaining / 60);
    echo json_encode([
        'success' => false,
        'message' => "Too many attempts. Please try again in {$lockoutMinutes} minute(s).",
    ]);
    exit;
}

$registerWaitSeconds = registerRateLimitCheck();
if ($registerWaitSeconds !== null && $registerWaitSeconds > 0) {
    http_response_code(429);
    $waitMinutes = (int) ceil($registerWaitSeconds / 60);
    echo json_encode([
        'success' => false,
        'message' => "Too many registration attempts from this network. Please try again in {$waitMinutes} minute(s).",
    ]);
    exit;
}

// 2. HTTP Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// 3. Parse JSON Body
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

// 4. Verify CSRF
$sentCsrf     = isset($input['csrf_token']) ? $input['csrf_token'] : ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
_startSecureSession();
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($expectedCsrf) || empty($sentCsrf) || !hash_equals($expectedCsrf, $sentCsrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
    exit;
}

$name            = safe_trim($input['name']  ?? null);
$email           = safe_trim($input['email'] ?? null);
$password        = isset($input['password']) ? (string)$input['password'] : '';
$confirmPassword = isset($input['confirm_password']) ? (string)$input['confirm_password'] : '';

// 4. Basic Validation
if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}

if (strlen($name) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name cannot exceed 255 characters.']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid email address is required.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

// 5. Database execution
try {
    $pdo = getDB();

    // Check if email already registered
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'An account with this email address already exists. Please sign in.'
        ]);
        exit;
    }

    // Hash password with bcrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user with role='user'
    $insStmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')"
    );
    $insStmt->execute([$name, $email, $passwordHash]);
    $newUserId = (int)$pdo->lastInsertId();

    // Initialize user profile
    try {
        $pStmt = $pdo->prepare(
            "INSERT INTO user_profiles (user_id, bio, theme_preference, language_preference) 
             VALUES (?, '', 'dark', 'en')
             ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP"
        );
        $pStmt->execute([$newUserId]);
    } catch (Exception $e) {
        error_log('[register] Failed to create user_profiles row: ' . $e->getMessage());
    }

    // Log account creation activity
    try {
        $aStmt = $pdo->prepare(
            "INSERT INTO user_activities (user_id, activity_type, description) 
             VALUES (?, 'account_created', 'Created personal platform account.')"
        );
        $aStmt->execute([$newUserId]);
    } catch (Exception $e) {
        error_log('[register] Failed to log user_activity: ' . $e->getMessage());
    }

    // 6. Bootstrap Secure Session
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')
    );

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure',   $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);

    $_SESSION['user_id']           = $newUserId;
    $_SESSION['user_name']         = $name;
    $_SESSION['user_email']        = $email;
    $_SESSION['user_role']         = 'user';
    $_SESSION['login_time']        = time();
    $_SESSION['email_verified_at'] = null;

    // Record registration rate limit for this IP
    registerRateLimitRecord();

    // Generate 32-byte cryptographically secure verification token (valid for 24h)
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 hours
    $clientIp  = getClientIp();

    try {
        $vStmt = $pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)'
        );
        $vStmt->execute([$newUserId, $tokenHash, $expiresAt, $clientIp]);

        $siteUrl = 'https://mohammedalrashadi.com';
        try {
            $sUrlStmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
            if ($sUrlStmt && ($sUrlRow = $sUrlStmt->fetch(PDO::FETCH_ASSOC))) {
                if (!empty($sUrlRow['site_url'])) {
                    $siteUrl = rtrim($sUrlRow['site_url'], '/');
                }
            }
        } catch (Throwable $_) { error_log('[api/auth/register.php:209] non-fatal, fallback used: ' . get_class($_)); }

        $verifyUrl = $siteUrl . '/verify-email.php?token=' . urlencode($rawToken);
        $tpl = buildVerifyEmailEmail([
            'name'           => $name,
            'verify_url'     => $verifyUrl,
            'requested_time' => date('Y-m-d H:i:s T'),
            'requested_ip'   => $clientIp,
        ]);
        sendMail($email, 'Verify your email address — Mohammed Alrashadi Platform', $tpl['html'], $tpl['text']);
    } catch (Throwable $mailEx) {
        error_log('[register] Verification email failed: ' . $mailEx->getMessage());
    }

    echo json_encode([
        'success'        => true,
        'message'        => 'Account created! Please check your inbox to verify your email address.',
        'redirect'       => '/dashboard/',
        'name'           => $name,
        'email'          => $email,
        'email_verified' => false
    ]);

} catch (PDOException $e) {
    error_log('[register] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred during registration.']);
}

