<?php
// ============================================================
// ADMIN — EMAIL PREVIEW
// GET /api/admin/email_preview.php?template=<name>
//
// Returns rendered HTML for a named template with sample data.
// Admin session required. Read-only — no side effects.
// Iframe must be sandboxed (no scripts). srcdoc is set by JS.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(__DIR__) . '/helpers/email_templates.php';

// Admin session check
requireAuth();

header('Content-Type: text/html; charset=UTF-8');
// Prevent clickjacking — only allow framing from same origin
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: frame-ancestors \'self\'');

$template = trim($_GET['template'] ?? 'password_reset');

// Sample data for every template
$siteUrl = 'https://mohammedalrashadi.com';

switch ($template) {

    case 'password_reset':
        $result = buildPasswordResetEmail([
            'name'           => 'Mohammed Alrashadi',
            'reset_url'      => $siteUrl . '/reset-password.php?token=SAMPLE_TOKEN_PREVIEW_ONLY_64CHARS',
            'requested_time' => date('Y-m-d H:i:s') . ' UTC+3',
            'requested_ip'   => '203.0.113.42',
        ]);
        break;

    case 'password_changed':
        $result = buildPasswordChangedEmail([
            'name'         => 'Mohammed Alrashadi',
            'changed_time' => date('Y-m-d H:i:s') . ' UTC+3',
            'changed_ip'   => '203.0.113.42',
            'reply_address'=> defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com',
        ]);
        break;

    case 'support_confirmation':
        $result = buildSupportConfirmationEmail([
            'name'      => 'Jane Doe',
            'ticket_id' => 42,
            'subject'   => 'Question about the Studio Lab',
            'category'  => 'technical',
        ]);
        break;

    case 'support_owner':
        $result = buildSupportOwnerNotificationEmail([
            'ticket_id' => 42,
            'name'      => 'Jane Doe',
            'email'     => 'jane@example.com',
            'category'  => 'technical',
            'subject'   => 'Question about the Studio Lab',
            'message'   => "Hi Mohammed,\n\nI was looking at your Studio Lab post and had a few questions about the database benchmarking methodology you used. Specifically, I'm curious about how you accounted for cache warm-up effects.\n\nThanks!",
            'ip'        => '203.0.113.88',
        ]);
        break;

    case 'welcome':
        $result = buildWelcomeEmail([
            'name'          => 'Mohammed Alrashadi',
            'dashboard_url' => $siteUrl . '/dashboard/',
        ]);
        break;

    default:
        http_response_code(404);
        echo '<p style="font-family:sans-serif;color:#f44;padding:20px;">Unknown template: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
}

echo $result['html'];

