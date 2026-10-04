<?php
// ============================================================
// AUTH — RESET PASSWORD
// POST /api/auth/reset.php
// Body: {"token": "...", "password": "...", "password_confirm": "...", "csrf_token": "..."}
// Validates single-use token, resets password, invalidates all existing sessions.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once __DIR__ . '/guard.php';
require_once __DIR__ . '/rate_limit.php';
require_once dirname(__DIR__) . '/helpers/mailer.php';
require_once dirname(dirname(__DIR__)) . '/includes/email_template.php';
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Rate limiting by client IP
$clientIp = getClientIp();
$lockoutSeconds = loginRateLimitCheck('reset_' . $clientIp);
if ($lockoutSeconds !== null && $lockoutSeconds > 0) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many attempts. Please try again later.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$token           = safe_trim($input['token'] ?? null);
$password        = $input['password'] ?? '';
$passwordConfirm = $input['password_confirm'] ?? '';
$csrfSent        = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

// Verify CSRF
_startSecureSession();
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($expectedCsrf) || empty($csrfSent) || !hash_equals($expectedCsrf, $csrfSent)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
    exit;
}

if (empty($token) || strlen($token) !== 64) {
    loginRateLimitRecordFailure('reset_' . $clientIp);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing reset token.']);
    exit;
}

if (empty($password) || safeStrlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
    exit;
}

if ($password !== $passwordConfirm) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match. Please re-enter.']);
    exit;
}

try {
    $pdo = getDB();

    $tokenHash = hash('sha256', $token);

    // Look up token joined with user
    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.status, u.email, u.name
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        loginRateLimitRecordFailure('reset_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired reset token. Please request a new link.']);
        exit;
    }

    if ($reset['used_at'] !== null) {
        loginRateLimitRecordFailure('reset_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This password reset link has already been used. Please request a new link.']);
        exit;
    }

    if (strtotime($reset['expires_at']) < time()) {
        loginRateLimitRecordFailure('reset_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This password reset link has expired. Reset links are valid for 60 minutes.']);
        exit;
    }

    if (($reset['status'] ?? 'active') !== 'active') {
        loginRateLimitRecordFailure('reset_' . $clientIp);
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'This account is currently suspended or inactive. Please contact support.']);
        exit;
    }

    // Hash password and update user in transaction
    $pdo->beginTransaction();

    // Atomically CONSUME the token first. The `used_at IS NULL` condition is part of
    // the UPDATE itself, so two simultaneous requests carrying the same token cannot
    // both succeed: the second one blocks on the row lock until the first commits,
    // then matches 0 rows. (The SELECT-then-check above is only a fast path for
    // friendly error messages — it is NOT what guarantees single use.)
    $consume = $pdo->prepare(
        'UPDATE password_resets
         SET used_at = NOW()
         WHERE id = ? AND used_at IS NULL'
    );
    $consume->execute([$reset['id']]);

    if ($consume->rowCount() !== 1) {
        $pdo->rollBack();
        loginRateLimitRecordFailure('reset_' . $clientIp);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This password reset link has already been used. Please request a new link.']);
        exit;
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    // Update password, password_changed_at (invalidates all existing sessions via guard.php),
    // and verify email address if unverified (possession of email proven via reset token)
    $upUser = $pdo->prepare(
        'UPDATE users 
         SET password_hash = ?, 
             password_changed_at = NOW(),
             email_verified_at = COALESCE(email_verified_at, NOW())
         WHERE id = ?'
    );
    $upUser->execute([$newHash, $reset['user_id']]);

    // Delete any other tokens for this user
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id != ?')
        ->execute([$reset['user_id'], $reset['id']]);

    $pdo->commit();

    // Log admin audit if target is admin
    logAdminAction('password_reset', 'user', (string)$reset['user_id'], 'Password reset via self-service recovery token');

    // Send password-changed confirmation email (best-effort — does not affect response)
    try {
        $firstName = explode(' ', trim($reset['name'] ?? 'there'))[0];
        $firstName = ucfirst(strtolower($firstName));
        $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $safeName = $esc($firstName);

        $tz = getSiteSetting('website.timezone', 'Asia/Riyadh');
        $dt = new DateTime('now', new DateTimeZone($tz));
        $changedTime = $dt->format('M j, Y, g:i A') . ' (Riyadh time)';

        $htmlContent = <<<HTML
<h1 style="margin: 0 0 24px 0; font-size: 24px; font-weight: 600;">Your password was changed</h1>
<p style="margin: 0 0 16px 0;">Hi {$safeName},</p>
<p style="margin: 0 0 24px 0;">Your password was changed on {$changedTime}.</p>
<p style="margin: 0;">If this wasn't you, reset your password right away and contact me.</p>
HTML;

        $textContent = <<<TEXT
Your password was changed

Hi {$safeName},

Your password was changed on {$changedTime}.

If this wasn't you, reset your password right away and contact me.
TEXT;

        $tpl = renderEmail(
            'Your password was changed',
            'Your account password was just updated.',
            $htmlContent,
            $textContent
        );
        sendMail($reset['email'], 'Your password was changed', $tpl['html'], $tpl['text']);
    } catch (Throwable $mailEx) {
        error_log('[reset.php] Notification email failed: ' . $mailEx->getMessage());
    }

    // Invalidate local session if any
    session_unset();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Your password has been successfully updated. You may now sign in with your new credentials.'
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[reset.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred while resetting password. Please try again.']);
}

