<?php
// ============================================================
// AUTH — FORGOT PASSWORD
// POST /api/auth/forgot.php
// Body: {"email": "...", "csrf_token": "..."} or form data
// Generates single-use 60-minute reset token. Prevents user enumeration.
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

// ---- Rate Limiting (per IP) ---------------------------------
$clientIp = getClientIp();
$lockoutSeconds = loginRateLimitCheck('forgot_' . $clientIp);
if ($lockoutSeconds !== null && $lockoutSeconds > 0) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many reset requests from this network. Please try again later.'
    ]);
    exit;
}

// ---- Parse input --------------------------------------------
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$email = strtolower(safe_trim($input['email'] ?? null));
$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

// Verify CSRF
_startSecureSession();
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($expectedCsrf) || empty($csrfSent) || !hash_equals($expectedCsrf, $csrfSent)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
    exit;
}

// Generic success response to avoid user enumeration
$genericResponse = [
    'success' => true,
    'message' => 'If an account exists for that email address, a password reset link has been dispatched.'
];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Record rate limit attempt
    loginRateLimitRecordFailure('forgot_' . $clientIp);
    echo json_encode($genericResponse);
    exit;
}

// Rate limiting per email address
$emailLockout = loginRateLimitCheck('forgot_email_' . $email);
if ($emailLockout !== null && $emailLockout > 0) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many requests for this account. Please wait before trying again.'
    ]);
    exit;
}

try {
    $pdo = getDB();

    // 1. Periodic cleanup of expired resets older than 24h
    try {
        $pdo->exec("DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY");
    } catch (Throwable $e) {
        // Table may not exist yet or non-critical error
    }

    // 2. Query user by email
    $stmt = $pdo->prepare('SELECT id, name, email, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If user not found, or suspended/banned, record attempt and return generic response
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        loginRateLimitRecordFailure('forgot_' . $clientIp);
        loginRateLimitRecordFailure('forgot_email_' . $email);
        echo json_encode($genericResponse);
        exit;
    }

    // 3. Generate cryptographically secure token
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 60 minutes

    // 4. Invalidate any prior unused reset tokens for this user
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')
        ->execute([$user['id']]);

    // 5. Insert new token
    $ins = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$user['id'], $tokenHash, $expiresAt, $clientIp]);

    // 6. Build canonical site URL from database settings (NEVER from HTTP_HOST)
    $siteUrl = 'https://mohammedalrashadi.com';
    try {
        $sStmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
        if ($sStmt && ($sRow = $sStmt->fetch(PDO::FETCH_ASSOC))) {
            if (!empty($sRow['site_url'])) {
                $siteUrl = rtrim($sRow['site_url'], '/');
            }
        }
    } catch (Throwable $e) {
        // Fallback remains https://mohammedalrashadi.com
    }

    $resetUrl = $siteUrl . '/reset-password.php?token=' . urlencode($rawToken);

    // 7. Dispatch Email
    $firstName = explode(' ', trim($user['name']))[0];
    $firstName = ucfirst(strtolower($firstName));
    if (empty($firstName)) $firstName = 'there';

    $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $safeName = $esc($firstName);
    $safeUrl = $esc($resetUrl);

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device = 'Unknown Device';
    if (preg_match('/(iPhone|iPad|Macintosh|Android|Windows)/i', $ua, $os)) {
        if (preg_match('/(Chrome|Safari|Firefox|Edge|Opera)/i', $ua, $browser)) {
            if ($browser[1] === 'Safari' && str_contains($ua, 'Chrome')) $browser[1] = 'Chrome';
            $device = $browser[1] . ' on ' . ($os[1] === 'Macintosh' ? 'macOS' : $os[1]);
        } else {
            $device = $os[1];
        }
    }

    $tz = getSiteSetting('website.timezone', 'Asia/Riyadh');
    $dt = new DateTime('now', new DateTimeZone($tz));
    $reqTime = $dt->format('M j, Y, g:i A') . ' (Riyadh time)';

    $htmlContent = <<<HTML
<h1 style="margin: 0 0 24px 0; font-size: 24px; font-weight: 600;">Reset your password</h1>
<p style="margin: 0 0 16px 0;">Hi {$safeName},</p>
<p style="margin: 0 0 24px 0;">We received a request to reset the password for your account. Click the button below to choose a new password.</p>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0">
        <tr>
          <td align="center" bgcolor="#2563EB" style="border-radius: 8px;">
            <a href="{$safeUrl}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 14px 28px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; line-height: 20px;">Reset password</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<p style="margin: 0 0 24px 0;">This link expires in 60 minutes and can be used only once.</p>

<p style="margin: 0 0 8px 0;">If the button doesn't work, copy and paste this link into your browser:</p>
<div style="background-color: #F9FAFB; border: 1px solid #E3E7EC; border-radius: 6px; padding: 12px; margin-bottom: 32px; font-size: 13px; word-break: break-all; color: #6B7280;">
  <a href="{$safeUrl}" style="color: #6B7280; text-decoration: none;">{$safeUrl}</a>
</div>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 32px; font-size: 14px;">
  <tr>
    <td width="100" style="padding-bottom: 8px; color: #6B7280;">Requested</td>
    <td style="padding-bottom: 8px; color: #111827;">{$reqTime}</td>
  </tr>
  <tr>
    <td width="100" style="color: #6B7280;">Device</td>
    <td style="color: #111827;">{$device}</td>
  </tr>
</table>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F9FAFB; border-left: 3px solid #CBD2D9; border-radius: 0 4px 4px 0;">
  <tr>
    <td style="padding: 16px; font-size: 14px; color: #4B5563;">
      If you didn't request this, you can safely ignore this email. Your password won't change.
    </td>
  </tr>
</table>
HTML;

    $textContent = <<<TEXT
Reset your password

Hi {$safeName},

We received a request to reset the password for your account.

Use this link to choose a new password:
{$safeUrl}

This link expires in 60 minutes and can be used only once.

Requested: {$reqTime}
Device: {$device}

If you didn't request this, you can safely ignore this email. Your password won't change.
TEXT;

    $tpl = renderEmail(
        'Reset your password',
        'Use this link to choose a new password. It expires in 60 minutes.',
        $htmlContent,
        $textContent
    );

    $sent = sendMail($user['email'], 'Reset your password', $tpl['html'], $tpl['text']);
    if (!$sent) {
        error_log('[forgot.php] sendMail dispatch failed for user id=' . $user['id']);
    }

    echo json_encode($genericResponse);

} catch (Throwable $e) {
    error_log('[forgot.php] Error: ' . $e->getMessage());
    echo json_encode($genericResponse);
}

