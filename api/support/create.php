<?php
// ============================================================
// SUPPORT — CREATE MESSAGE
// POST /api/support/create.php
// Public customer support inquiry submission endpoint
// Anti-spam: Rate limiting + Honeypot + CSRF verification
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// 1. Rate Limiting (5 messages per hour per IP)
$clientIp = getClientIp();
$lockoutSeconds = formRateLimitCheck('support_' . $clientIp);
if ($lockoutSeconds !== null && $lockoutSeconds > 0) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'You have sent several messages recently. Please wait before submitting another inquiry.'
    ]);
    exit;
}

// 2. Parse input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

// Honeypot spam trap
if (!empty($input['website_url'])) {
    // Spambot trapped: pretend it succeeded without inserting
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for reaching out. Your message has been received.'
    ]);
    exit;
}

// 3. CSRF Verification
_startSecureSession();
$csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
$expectedCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($expectedCsrf) || empty($csrfSent) || !hash_equals($expectedCsrf, $csrfSent)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Security token expired. Please refresh the page and try again.'
    ]);
    exit;
}

// 4. Validate fields
$name     = mailerSanitizeHeaderValue(safe_trim($input['name'] ?? null)); // DC-001: no CR/LF
$email    = strtolower(safe_trim($input['email'] ?? null));
$category = safe_trim($input['category'] ?? 'general');
$subject  = mailerSanitizeHeaderValue(safe_trim($input['subject'] ?? null)); // DC-001: no CR/LF
$message  = safe_trim($input['message'] ?? null);

if (safeStrlen($name) < 2 || safeStrlen($name) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide your full name (2–255 characters).']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || safeStrlen($email) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

$validCategories = ['general', 'technical', 'product', 'account', 'feedback'];
if (!in_array($category, $validCategories, true)) {
    $category = 'general';
}

if (safeStrlen($subject) < 3 || safeStrlen($subject) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a brief subject line (3–255 characters).']);
    exit;
}

if (safeStrlen($message) < 10 || safeStrlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message must be between 10 and 5,000 characters.']);
    exit;
}

$userId = isset($_SESSION['user_id']) && is_int($_SESSION['user_id']) && $_SESSION['user_id'] > 0
    ? (int)$_SESSION['user_id']
    : null;

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'INSERT INTO support_messages (user_id, name, email, category, subject, message, status, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, "new", ?)'
    );
    $stmt->execute([$userId, $name, $email, $category, $subject, $message, $clientIp]);
    $ticketId = $pdo->lastInsertId();

    // Dispatch admin notification alert
    try {
        require_once dirname(__DIR__) . '/helpers/alerts.php';
        createAdminAlert(
            'support.new',
            'info',
            'New Support Ticket #' . $ticketId . ': ' . mb_substr($subject, 0, 100, 'UTF-8'),
            'From ' . $name . ' (' . $email . ') in ' . ucfirst($category),
            'support.php',
            'support_ticket_' . $ticketId
        );
    } catch (Throwable $alertEx) {
        error_log('[support/create] Alert error: ' . $alertEx->getMessage());
    }

    // Record rate limit attempt
    formRateLimitRecord('support_' . $clientIp);

    // 5. Email notifications
    try {
        // Resolve owner email
        $pStmt = $pdo->query('SELECT public_email FROM site_profile LIMIT 1');
        $ownerEmail = ($pRow = $pStmt->fetch(PDO::FETCH_ASSOC)) ? $pRow['public_email'] : '';
        if (empty($ownerEmail)) {
            $ownerEmail = defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com';
        }

        // 5a. Notify owner (always)
        $ownerTpl = buildSupportOwnerNotificationEmail([
            'ticket_id' => $ticketId,
            'name'      => $name,
            'email'     => $email,
            'category'  => $category,
            'subject'   => $subject,
            'message'   => $message,
            'ip'        => $clientIp,
        ]);
        sendMail($ownerEmail, "[Support Ticket #$ticketId] $subject", $ownerTpl['html'], $ownerTpl['text']);

        // 5b. Visitor confirmation — per-email rate limit (1 per 10 minutes)
        //     Prevents confirmation spam if the form is hammered with the same address.
        $visitorLimitKey = 'support_confirm_' . md5(strtolower($email));
        $visitorLocked   = formRateLimitCheck($visitorLimitKey);
        if ($visitorLocked === null || $visitorLocked <= 0) {
            $visitorTpl = buildSupportConfirmationEmail([
                'name'      => $name,
                'ticket_id' => $ticketId,
                'subject'   => $subject,
                'category'  => $category,
            ]);
            $visitorSent = sendMail($email, 'We received your message — Ticket #' . $ticketId, $visitorTpl['html'], $visitorTpl['text']);
            if ($visitorSent) {
                formRateLimitRecord($visitorLimitKey);
            }
        }
    } catch (Throwable $mailEx) {
        error_log('[support/create] Mail error: ' . $mailEx->getMessage());
    }

    echo json_encode([
        'success'   => true,
        'message'   => 'Thank you for reaching out! Your message has been received, and we will get back to you shortly.',
        'ticket_id' => $ticketId,
    ]);

} catch (Throwable $e) {
    error_log('[support/create] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A server error occurred while sending your message. Please try again.'
    ]);
}

