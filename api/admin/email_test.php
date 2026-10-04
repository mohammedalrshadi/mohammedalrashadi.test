<?php
// ============================================================
// ADMIN — SEND TEST EMAIL
// POST /api/admin/email_test.php
// Body: {"template": "...", "csrf_token": "..."}
//
// Sends a test email to the configured SMTP_FROM address.
// Admin session + CSRF required. Rate-limited (1 per 30s).
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(__DIR__) . '/auth/rate_limit.php';
require_once dirname(__DIR__) . '/helpers/mailer.php';
require_once dirname(__DIR__) . '/helpers/email_templates.php';

header('Content-Type: application/json');

// Admin session check
requireAuth();

// Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Parse JSON body
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

// CSRF check
_startSecureSession();
$csrfSent     = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($expectedCsrf) || empty($csrfSent) || !hash_equals($expectedCsrf, $csrfSent)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
    exit;
}

// Rate limit: max 1 test email per 30 seconds per admin session
$rateLimitKey    = 'email_test_admin_' . ($_SESSION['user_id'] ?? 'anon');
$rateLimitLocked = loginRateLimitCheck($rateLimitKey);
if ($rateLimitLocked !== null && $rateLimitLocked > 0) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Please wait a moment before sending another test email.']);
    exit;
}

// Resolve target template
$template = trim($input['template'] ?? 'password_reset');
$siteUrl  = 'https://mohammedalrashadi.com';
$toAddr   = defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com';

switch ($template) {

    case 'password_reset':
        $tpl     = buildPasswordResetEmail([
            'name'           => 'Mohammed (Test Preview)',
            'reset_url'      => $siteUrl . '/reset-password.php?token=TEST_TOKEN_NOT_REAL',
            'requested_time' => date('Y-m-d H:i:s') . ' UTC+3',
            'requested_ip'   => getClientIp(),
        ]);
        $subject = '[TEST] Reset your password';
        break;

    case 'password_changed':
        $tpl     = buildPasswordChangedEmail([
            'name'         => 'Mohammed (Test Preview)',
            'changed_time' => date('Y-m-d H:i:s') . ' UTC+3',
            'changed_ip'   => getClientIp(),
            'reply_address'=> $toAddr,
        ]);
        $subject = '[TEST] Your password was changed';
        break;

    case 'support_confirmation':
        $tpl     = buildSupportConfirmationEmail([
            'name'      => 'Test Visitor',
            'ticket_id' => 0,
            'subject'   => 'Test support submission',
            'category'  => 'general',
        ]);
        $subject = '[TEST] We received your message — Ticket #0';
        break;

    case 'support_owner':
        $tpl     = buildSupportOwnerNotificationEmail([
            'ticket_id' => 0,
            'name'      => 'Test Visitor',
            'email'     => 'test@example.com',
            'category'  => 'general',
            'subject'   => 'Test support submission',
            'message'   => 'This is a test message body for the admin notification template.',
            'ip'        => getClientIp(),
        ]);
        $subject = '[TEST] New Support Ticket #0';
        break;

    case 'welcome':
        $tpl     = buildWelcomeEmail([
            'name'          => 'Mohammed (Test Preview)',
            'dashboard_url' => $siteUrl . '/dashboard/',
        ]);
        $subject = '[TEST] Welcome to Mohammed Alrashadi Platform';
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown template name.']);
        exit;
}

// Send
$sent = sendMail($toAddr, $subject, $tpl['html'], $tpl['text']);

if ($sent) {
    logAdminAction('email.test', 'system', null, json_encode([
        'template' => $template,
        'to'       => $toAddr,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    loginRateLimitRecordFailure($rateLimitKey);
    echo json_encode([
        'success' => true,
        'message' => 'Test email sent to ' . $toAddr . '. Check your inbox.',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Email dispatch failed. Check server error logs for details.',
    ]);
}

