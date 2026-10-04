<?php
// ============================================================
// SMTP MAILER HELPER
// api/helpers/mailer.php
//
// sendMail(string $to, string $subject, string $html, string $text): bool
//
// Uses PHPMailer + SMTP when SMTP_HOST is defined and SMTP_PASS
// is non-empty and not a placeholder.
// Falls back to PHP mail() when not configured (local / dev).
// NEVER logs the password or any token value.
// NEVER exposes SMTP errors to callers / HTTP responses.
// ============================================================

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * DC-001: Neutralise header-injection characters in a value destined for an
 * email header (To / Subject / From name). CR, LF and NUL are replaced by a
 * single space so a caller can never smuggle extra headers.
 */
function mailerSanitizeHeaderValue(string $value): string
{
    return trim((string) preg_replace('/[\r\n\x00]+/', ' ', $value));
}

/**
 * Send a transactional email.
 *
 * @param string $to       Recipient address
 * @param string $subject  Email subject line
 * @param string $htmlBody Full HTML body
 * @param string $textBody Plain-text AltBody (always required)
 * @return bool            true on success, false on any failure
 */
function sendMail(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    // ---- DC-001: header-injection defence (applies to SMTP and mail() paths) ----
    $to      = mailerSanitizeHeaderValue($to);
    $subject = mailerSanitizeHeaderValue($subject);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[mailer] Refusing to send: invalid recipient address.');
        return false;
    }

    // ---- Detect SMTP configuration ----------------------------------
    $smtpConfigured = (
        defined('SMTP_HOST')
        && !empty(SMTP_HOST)
        && defined('SMTP_PASS')
        && !empty(SMTP_PASS)
        && SMTP_PASS !== 'your_smtp_password'   // guard against uncommitted placeholder
        && SMTP_PASS !== 'YOUR_SMTP_PASSWORD'
    );

    if (!$smtpConfigured) {
        // Log a clear, actionable error — no secret values
        $missingParts = [];
        if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
            $missingParts[] = 'SMTP_HOST not defined';
        }
        if (!defined('SMTP_PASS') || empty(SMTP_PASS)) {
            $missingParts[] = 'SMTP_PASS not defined or empty';
        } elseif (in_array(SMTP_PASS, ['your_smtp_password', 'YOUR_SMTP_PASSWORD'], true)) {
            $missingParts[] = 'SMTP_PASS is still a placeholder — set the real password in api/config.local.php';
        }

        if (!empty($missingParts)) {
            error_log('[mailer] SMTP not configured (' . implode('; ', $missingParts) . '). Falling back to mail().');
        }
    }

    // ---- SMTP path --------------------------------------------------
    if ($smtpConfigured) {
        $mail = new PHPMailer(true); // exceptions enabled

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host     = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = defined('SMTP_USER') ? SMTP_USER : SMTP_FROM;
            $mail->Password = SMTP_PASS;

            // Encryption: port 465 → SMTPS, port 587 → STARTTLS
            $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 465;
            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $port;
            }

            // Sender
            $fromAddr = defined('SMTP_FROM')      ? SMTP_FROM      : (defined('SMTP_USER') ? SMTP_USER : '');
            $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Mohammed Alrashadi';
            $mail->setFrom($fromAddr, $fromName);
            $mail->addReplyTo($fromAddr, $fromName);

            // Recipient
            $mail->addAddress($to);

            // Content
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
            return true;

        } catch (PHPMailerException $e) {
            // Log the exception message but strip any password traces
            $safeMsg = preg_replace('/pass(word)?[^:]*:[^\s]*/i', '[REDACTED]', $e->getMessage());
            error_log('[mailer] PHPMailer exception for <' . $to . '>: ' . $safeMsg);
            _recordMailFailure();
            return false;
        } catch (Throwable $e) {
            error_log('[mailer] Unexpected error for <' . $to . '>: ' . $e->getMessage());
            _recordMailFailure();
            return false;
        }
    }

    // ---- Fallback: PHP mail() ---------------------------------------
    $fromAddr = defined('SMTP_FROM')      ? SMTP_FROM      : 'noreply@mohammedalrashadi.com';
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Mohammed Alrashadi';

    $boundary = "----=_NextPart_" . md5(uniqid(time()));
    
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'From: "' . addslashes($fromName) . '" <' . $fromAddr . '>',
        'Reply-To: ' . $fromAddr,
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ]);

    $body = "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $textBody . "\r\n\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $htmlBody . "\r\n\r\n" .
            "--{$boundary}--";

    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        error_log('[mailer] mail() fallback dispatch failed for <' . $to . '>');
        _recordMailFailure();
    }
    return (bool) $sent;
}

/**
 * Records a mail delivery failure and triggers a critical admin alert if 3+ failures occur in 1 hour.
 */
function _recordMailFailure(): void {
    try {
        $alertsHelper = __DIR__ . '/alerts.php';
        if (!file_exists($alertsHelper)) {
            return;
        }
        require_once $alertsHelper;

        $now = time();
        $oneHourAgo = $now - 3600;
        $failureCount = 0;

        if (function_exists('_alertFileWithLock')) {
            _alertFileWithLock('mail_failures.json', function (array $data) use ($now, $oneHourAgo, &$failureCount): array {
                $timestamps = $data['failures'] ?? [];
                $timestamps = array_values(array_filter($timestamps, fn($ts) => $ts >= $oneHourAgo));
                $timestamps[] = $now;
                $data['failures'] = $timestamps;
                $failureCount = count($timestamps);
                return $data;
            });
        }

        if ($failureCount >= 3) {
            $hourSlot = date('YmdH');
            createAdminAlert(
                'mail.failed',
                'critical',
                'Critical: Repeated Mail Delivery Failures',
                'Outbound email delivery failed 3 or more times within the last hour. Password reset and verification emails may be impacted.',
                'settings.php',
                'mail_failed_' . $hourSlot
            );
        }
    } catch (Throwable $e) {
        error_log('[mailer] _recordMailFailure error: ' . $e->getMessage());
    }
}

