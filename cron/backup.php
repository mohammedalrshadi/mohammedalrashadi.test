<?php
// ============================================================
// CRON — DAILY DATABASE BACKUP RUNNER
// cron/backup.php
//
// Guards (one must pass):
//   1. php_sapi_name() === 'cli'   — run from command line / cron
//   2. $_GET['key'] === BACKUP_CRON_KEY  — HTTP trigger with secret
//
// On SUCCESS: logs to error_log, exits 0.
// On FAILURE: logs + sends email via sendMail(), exits 1.
//
// Hostinger hPanel cron line (hPanel → Advanced → Cron Jobs, daily 03:00):
//   0 3 * * * /usr/local/lsws/lsphp82/bin/php /home/u303927365/domains/mohammedalrashadi.com/public_html/cron/backup.php
//
// If using HTTP trigger (less preferred), add BACKUP_CRON_KEY to
// api/config.local.php then call:
//   https://mohammedalrashadi.com/cron/backup.php?key=YOUR_CRON_KEY
// ============================================================

// ---- Bootstrap ----------------------------------------------------------
$publicHtml = dirname(__DIR__);
require_once $publicHtml . '/api/config.php';
require_once $publicHtml . '/api/db.php';
require_once $publicHtml . '/api/helpers/backup.php';
require_once $publicHtml . '/api/helpers/mailer.php';

// ---- Access guard -------------------------------------------------------
$isCli  = (php_sapi_name() === 'cli');
$isHttp = false;

if (!$isCli) {
    // HTTP access: require a secret key defined in config.local.php
    if (defined('BACKUP_CRON_KEY') && BACKUP_CRON_KEY !== '' && BACKUP_CRON_KEY !== 'your_backup_cron_key') {
        // DC-008: accept the key from the X-Backup-Key header (preferred, stays out of
        // access logs) or, for backward compatibility, from ?key=. Malformed input such as
        // key[]=x is treated as "no key" instead of raising a TypeError (HTTP 500).
        $providedKey = '';
        $headerKey = $_SERVER['HTTP_X_BACKUP_KEY'] ?? null;
        $queryKey  = $_GET['key'] ?? null;
        if (is_string($headerKey) && $headerKey !== '') {
            $providedKey = $headerKey;
        } elseif (is_string($queryKey)) {
            $providedKey = $queryKey;
        }
        if ($providedKey !== '' && hash_equals((string) BACKUP_CRON_KEY, $providedKey)) {
            $isHttp = true;
        }
    }

    if (!$isHttp) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "Forbidden.\n";
        exit(1);
    }
}

// ---- Output helpers (CLI vs HTTP) ----------------------------------------
function cronLog(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    error_log("[backup-cron] {$message}");
    if (php_sapi_name() === 'cli') {
        echo "[{$ts}] {$message}\n";
    }
}

// ---- Run backup ----------------------------------------------------------
cronLog('Starting database backup...');

$startTime = microtime(true);
$result    = createDatabaseBackup();
$elapsed   = round(microtime(true) - $startTime, 2);

if ($result['success']) {

    $humanSize = _humanFileSize($result['size_bytes']);
    cronLog("SUCCESS — {$result['filename']} ({$humanSize}) in {$elapsed}s");

    // Record last successful run time in a small status file
    $statusFile = dirname(__DIR__) . '/api/data/backup_status.json';
    @file_put_contents($statusFile, json_encode([
        'last_run_at'   => date('Y-m-d H:i:s'),
        'last_run_ts'   => time(),
        'last_filename' => $result['filename'],
        'last_size'     => $result['size_bytes'],
        'status'        => 'success',
    ], JSON_PRETTY_PRINT));

    if ($isHttp) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'filename' => $result['filename'], 'size_bytes' => $result['size_bytes']]);
    }
    exit(0);

} else {

    $error = $result['message'] ?? 'Unknown error';
    cronLog("FAILED — {$error}");

    // Record failure status
    $statusFile = dirname(__DIR__) . '/api/data/backup_status.json';
    @file_put_contents($statusFile, json_encode([
        'last_run_at' => date('Y-m-d H:i:s'),
        'last_run_ts' => time(),
        'status'      => 'failed',
        'error'       => $error,
    ], JSON_PRETTY_PRINT));

    // Send failure notification email (only on failure, as per spec)
    $to      = defined('SMTP_FROM') ? SMTP_FROM : '';
    $subject = '[' . (defined('DB_NAME') ? DB_NAME : 'site') . '] Database Backup FAILED — ' . date('Y-m-d H:i:s');
    $html    = '<p>The scheduled database backup <strong>failed</strong> at ' . htmlspecialchars(date('Y-m-d H:i:s T'), ENT_QUOTES, 'UTF-8') . '.</p>'
             . '<p><strong>Error:</strong> ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
             . '<p>Please check the server error log and run a manual backup from the Admin Panel → Backups.</p>';
    $text    = "The scheduled database backup FAILED at " . date('Y-m-d H:i:s T') . ".\n\nError: {$error}\n\nPlease check the server error log and run a manual backup from the Admin Panel → Backups.";

    if (!empty($to)) {
        sendMail($to, $subject, $html, $text);
    }

    if ($isHttp) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error]);
    }
    exit(1);

}

