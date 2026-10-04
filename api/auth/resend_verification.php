<?php
// ============================================================
// AUTH — RESEND EMAIL VERIFICATION
// POST /api/auth/resend_verification.php
// Body: {"email": "...", "csrf_token": "..."}
// Rate limits: 60s cooldown per email, daily cap (10/day), IP limit.
// Security: Generic response — never reveals whether an email exists or is verified.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/rate_limit.php';
require_once dirname(__DIR__) . '/helpers/mailer.php';
require_once dirname(__DIR__) . '/helpers/email_templates.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

_startSecureSession();

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
$expectedCsrf = $_SESSION['csrf_token'] ?? '';

if (empty($expectedCsrf) || empty($csrfSent) || !hash_equals($expectedCsrf, $csrfSent)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
    exit;
}

$clientIp = getClientIp();
$email    = strtolower(safe_trim($input['email'] ?? null));

// If logged in and email wasn't sent, fall back to session email
if ($email === '' && !empty($_SESSION['user_email'])) {
    $email = strtolower(trim((string)$_SESSION['user_email']));
}

$genericResponse = [
    'success' => true,
    'message' => 'If an account exists for that email address and requires verification, a new link has been dispatched.'
];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($genericResponse);
    exit;
}

// Check rate limits (60s cooldown, daily cap, IP cap)
$rateLimitBlocked = resendRateLimitCheck($email, $clientIp);
if ($rateLimitBlocked !== null) {
    // Return generic response so limits cannot be probed to guess valid addresses
    echo json_encode($genericResponse);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id, name, email, status, email_verified_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user doesn't exist, is suspended/banned, or is ALREADY verified:
    if (!$user || ($user['status'] ?? 'active') !== 'active' || !empty($user['email_verified_at'])) {
        echo json_encode($genericResponse);
        exit;
    }

    // User is active and unverified: record rate limit attempt
    resendRateLimitRecord($email, $clientIp);

    // Invalidate older unused verification tokens for this user
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND used_at IS NULL')
        ->execute([$user['id']]);

    // Issue new 24h verification token
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    $ins = $pdo->prepare(
        'INSERT INTO email_verifications (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$user['id'], $tokenHash, $expiresAt, $clientIp]);

    // Build site URL
    $siteUrl = 'https://mohammedalrashadi.com';
    try {
        $sUrlStmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
        if ($sUrlStmt && ($sUrlRow = $sUrlStmt->fetch(PDO::FETCH_ASSOC)) && !empty($sUrlRow['site_url'])) {
            $siteUrl = rtrim($sUrlRow['site_url'], '/');
        }
    } catch (Throwable $_) { error_log('[api/auth/resend_verification.php:108] non-fatal, fallback used: ' . get_class($_)); }

    $verifyUrl = $siteUrl . '/verify-email.php?token=' . urlencode($rawToken);

    $tpl = buildVerifyEmailEmail([
        'name'           => $user['name'],
        'verify_url'     => $verifyUrl,
        'requested_time' => date('Y-m-d H:i:s T'),
        'requested_ip'   => $clientIp,
    ]);

    sendMail($user['email'], 'Verify your email address — Mohammed Alrashadi Platform', $tpl['html'], $tpl['text']);

    echo json_encode($genericResponse);

} catch (Throwable $e) {
    error_log('[resend_verification] DB error: ' . $e->getMessage());
    echo json_encode($genericResponse);
}

