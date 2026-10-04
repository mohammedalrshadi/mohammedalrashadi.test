<?php
// ============================================================
// AUTH — VERIFY EMAIL (JSON API)
// POST /api/auth/verify_email.php
// Body: {"token": "...", "csrf_token": "..."}
// Validates single-use 24h verification token and activates account.
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

// Rate limit checks per IP
$clientIp = getClientIp();
$lockoutSeconds = loginRateLimitCheck('verify_' . $clientIp);
if ($lockoutSeconds !== null && $lockoutSeconds > 0) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many verification attempts from this network. Please wait.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$token    = safe_trim($input['token'] ?? null);
$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

// Verify CSRF
_startSecureSession();
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($expectedCsrf) || empty($csrfSent) || !hash_equals($expectedCsrf, $csrfSent)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
    exit;
}

if (empty($token) || strlen($token) !== 64) {
    loginRateLimitRecordFailure('verify_' . $clientIp);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing verification token.']);
    exit;
}

try {
    $pdo = getDB();
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT ev.id, ev.user_id, ev.expires_at, ev.used_at,
                u.name, u.email, u.status, u.email_verified_at
         FROM email_verifications ev
         JOIN users u ON u.id = ev.user_id
         WHERE ev.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        loginRateLimitRecordFailure('verify_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'The verification link is invalid or does not exist.']);
        exit;
    }

    if ($record['used_at'] !== null) {
        loginRateLimitRecordFailure('verify_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This verification link has already been used.']);
        exit;
    }

    if (strtotime($record['expires_at']) < time()) {
        loginRateLimitRecordFailure('verify_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This verification link has expired. Please request a new one.']);
        exit;
    }

    if (($record['status'] ?? 'active') !== 'active') {
        loginRateLimitRecordFailure('verify_' . $clientIp);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account is inactive or suspended.']);
        exit;
    }

    // Success: mark user verified and consume token
    $pdo->beginTransaction();

    $uStmt = $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?');
    $uStmt->execute([$record['user_id']]);

    $tStmt = $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?');
    $tStmt->execute([$record['id']]);

    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND id != ?')
        ->execute([$record['user_id'], $record['id']]);

    $pdo->commit();

    // Update active session
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$record['user_id']) {
        $_SESSION['email_verified_at'] = date('Y-m-d H:i:s');
    }

    // Log activity
    try {
        $aStmt = $pdo->prepare(
            "INSERT INTO user_activities (user_id, activity_type, description) 
             VALUES (?, 'email_verified', 'Verified primary email address.')"
        );
        $aStmt->execute([$record['user_id']]);
    } catch (Throwable $_) { error_log('[api/auth/verify_email.php:133] non-fatal, fallback used: ' . get_class($_)); }

    // Send welcome email
    try {
        $siteUrl = 'https://mohammedalrashadi.com';
        try {
            $sUrlStmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
            if ($sUrlStmt && ($sUrlRow = $sUrlStmt->fetch(PDO::FETCH_ASSOC)) && !empty($sUrlRow['site_url'])) {
                $siteUrl = rtrim($sUrlRow['site_url'], '/');
            }
        } catch (Throwable $_) { error_log('[api/auth/verify_email.php:143] non-fatal, fallback used: ' . get_class($_)); }

        $welcomeTpl = buildWelcomeEmail([
            'name'          => $record['name'],
            'dashboard_url' => $siteUrl . '/dashboard/',
        ]);
        sendMail($record['email'], 'Welcome to Mohammed Alrashadi Platform', $welcomeTpl['html'], $welcomeTpl['text']);
    } catch (Throwable $mailEx) {
        error_log('[verify_email] Welcome email failed: ' . $mailEx->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully! You now have full access to all platform features.',
        'redirect'=> '/dashboard/',
    ]);

} catch (Throwable $e) {
    error_log('[verify_email] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A server error occurred during verification.']);
}

