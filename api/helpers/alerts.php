<?php
// ============================================================
// ADMIN NOTIFICATION CENTER (ALERTS) HELPER
// api/helpers/alerts.php
//
// Central engine for administrative alerts, system warnings,
// security lockouts, support tickets, reviews, and notifications.
//
// Key Invariants:
// 1. Silent degradation: Never fatals if admin_alerts table is missing.
// 2. Exception isolation: Never breaks primary user requests.
// 3. Sensitive data protection: Never stores passwords, tokens, or raw bodies.
// 4. Flood protection: Global cap of 500 unread alerts + deduplication.
// 5. Link whitelist: Only approved relative admin URLs; rejects schemes.
// 6. JSON concurrency: All file writes protected by flock.
// 7. Multi-byte UTF-8 safety: Truncates strings with mb_substr.
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';

/**
 * Executes a callback with an exclusive flock on an api/data/*.json file.
 *
 * @param string $filename Base filename in api/data/
 * @param callable $callback fn(array $data): ?array
 * @return mixed Result of the callback
 */
function _alertFileWithLock(string $filename, callable $callback)
{
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . '/' . $filename;

    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        error_log('[alerts] Could not open ' . $path);
        return null;
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            error_log('[alerts] Could not lock ' . $path);
            return null;
        }

        $raw  = stream_get_contents($fh);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $result = $callback($data);

        if (is_array($result)) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fh);
        }

        flock($fh, LOCK_UN);
        return $result;
    } finally {
        fclose($fh);
    }
}

/**
 * Feature-detects whether the admin_alerts table exists in the current database.
 * Result is cached statically in memory.
 */
function adminAlertsTableExists(PDO $pdo, bool $forceCheck = false): bool
{
    static $cache = [];
    $key = spl_object_hash($pdo);
    if (isset($cache[$key]) && !$forceCheck) {
        return $cache[$key];
    }

    try {
        $pdo->query('SELECT 1 FROM admin_alerts LIMIT 1');
        $cache[$key] = true;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

/**
 * Validates a target link against a strict whitelist of relative admin page paths.
 * Rejects any scheme (':'), protocol-relative links ('//'), or 'javascript' keywords.
 */
function validateAlertLink(?string $link): ?string
{
    if ($link === null) {
        return null;
    }

    $link = trim($link);
    if ($link === '') {
        return null;
    }

    // Reject schemes (':'), protocol-relative ('//'), and javascript
    if (strpos($link, ':') !== false || strpos($link, '//') !== false) {
        return null;
    }

    if (stripos($link, 'javascript') !== false) {
        return null;
    }

    // Disallow control characters and HTML tags
    if (preg_match('/[\x00-\x1F\x7F<>"\']/', $link)) {
        return null;
    }

    // Extract base page filename (before '?' or '#')
    $parts = explode('?', $link, 2);
    $page = ltrim($parts[0], '/');

    // Strict whitelist of relative admin pages
    $allowedPages = [
        'index.php',
        'articles.php',
        'articles-edit.php',
        'projects.php',
        'projects-edit.php',
        'labs.php',
        'lab-edit.php',
        'store.php',
        'store-edit.php',
        'journey.php',
        'journey-edit.php',
        'categories.php',
        'media.php',
        'showcase.php',
        'showcase-edit.php',
        'reviews.php',
        'seo.php',
        'analytics.php',
        'backups.php',
        'audit-log.php',
        'users.php',
        'support.php',
        'settings.php',
    ];

    if (!in_array($page, $allowedPages, true)) {
        return null;
    }

    // If query string exists, validate safe query characters: letters, digits, _, =, &, -
    if (isset($parts[1])) {
        $query = $parts[1];
        if (!preg_match('/^[a-zA-Z0-9_=&-]+$/', $query)) {
            return $page; // Return base page if query string has disallowed characters
        }
        return $page . '?' . $query;
    }

    return $page;
}

/**
 * Calculates the network prefix (/24 for IPv4, /64 for IPv6) for subnet-level IP comparisons.
 */
function getIpSubnet(string $ip): string
{
    $ip = trim($ip);

    // IPv4: match first 3 octets
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
        }
    }

    // IPv6: match first 64 bits (4 words)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $binary = @inet_pton($ip);
        if ($binary !== false && strlen($binary) === 16) {
            return bin2hex(substr($binary, 0, 8)) . '/64';
        }
    }

    return $ip;
}

/**
 * Checks whether an admin login IP belongs to an unfamiliar network prefix.
 *
 * Rules:
 * - Compares by network prefix (IPv4 /24, IPv6 /64).
 * - If there is no known-IP history yet for this admin, records silently with NO alert or email.
 * - If history exists and the subnet was not seen in the last 30 days, reports as new IP.
 *
 * @return array{is_new: bool, is_initial_seed: bool, subnet: string}
 */
function checkAndRecordAdminLoginIp(int $adminId, string $ip): array
{
    if ($adminId <= 0 || empty($ip)) {
        return ['is_new' => false, 'is_initial_seed' => false, 'subnet' => ''];
    }

    $subnet = getIpSubnet($ip);
    $now = time();
    $cutoff = $now - (30 * 86400); // 30 days
    $isNew = false;
    $isInitialSeed = false;

    _alertFileWithLock('known_admin_ips.json', function (array $data) use ($adminId, $subnet, $now, $cutoff, &$isNew, &$isInitialSeed): array {
        $adminKey = (string)$adminId;
        $subnets = $data[$adminKey]['subnets'] ?? [];

        // Check if admin_audit_log exists for historical seed fallback
        $hasAuditLogHistory = false;
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                "SELECT 1 FROM admin_audit_log 
                 WHERE admin_id = ? AND action = 'auth.login' 
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                 LIMIT 1"
            );
            $stmt->execute([$adminId]);
            $hasAuditLogHistory = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            // Ignore audit log error
        }

        // 1. Initial seeding: No prior record in JSON and no prior record in audit log
        if (empty($subnets) && !$hasAuditLogHistory) {
            $subnets[$subnet] = $now;
            $data[$adminKey] = ['subnets' => $subnets];
            $isInitialSeed = true;
            $isNew = false;
            return $data;
        }

        // Prune entries older than 30 days
        foreach ($subnets as $s => $ts) {
            if ($ts < $cutoff) {
                unset($subnets[$s]);
            }
        }

        // Check if current subnet is known
        if (isset($subnets[$subnet])) {
            $subnets[$subnet] = $now; // Refresh last-seen
            $isNew = false;
        } else {
            // New network prefix detected
            $subnets[$subnet] = $now;
            $isNew = true;
        }

        $data[$adminKey] = ['subnets' => $subnets];
        return $data;
    });

    return [
        'is_new'          => $isNew,
        'is_initial_seed' => $isInitialSeed,
        'subnet'          => $subnet,
    ];
}

/**
 * Creates an administrative alert with deduplication, flood protection, and severity handling.
 *
 * @param string $type Machine identifier (e.g. 'support.new', 'login.failed_burst')
 * @param string $severity 'info' | 'warning' | 'critical'
 * @param string $title Short title (max 255 chars)
 * @param string|null $body Optional description (max 500 chars)
 * @param string|null $link Optional relative admin page (validated against whitelist)
 * @param string|null $dedupeKey Optional deduplication key (max 120 chars)
 * @return int|null Inserted/updated alert ID, or null on failure/degraded mode
 */
function createAdminAlert(
    string $type,
    string $severity,
    string $title,
    ?string $body = null,
    ?string $link = null,
    ?string $dedupeKey = null,
    ?PDO $pdo = null
): ?int {
    // Static re-entrancy guard: prevents infinite alert loops
    static $isEntering = false;
    if ($isEntering) {
        return null;
    }
    $isEntering = true;

    try {
        if ($pdo === null) {
            $pdo = getDB();
        }

        // 1. Feature detection: Silently degrade if admin_alerts table is missing
        if (!adminAlertsTableExists($pdo)) {
            return null;
        }

        // 2. Validate and sanitize inputs with multi-byte safety
        $type = mb_substr(trim($type), 0, 50, 'UTF-8');
        if ($type === '') {
            $type = 'system.general';
        }

        $allowedSeverities = ['info', 'warning', 'critical'];
        $severity = strtolower(trim($severity));
        if (!in_array($severity, $allowedSeverities, true)) {
            $severity = 'info';
        }

        $title = mb_substr(trim($title), 0, 255, 'UTF-8');
        if ($title === '') {
            $title = 'System Notification';
        }

        if ($body !== null) {
            $body = mb_substr(trim($body), 0, 500, 'UTF-8');
            if ($body === '') {
                $body = null;
            }
        }

        $link = validateAlertLink($link);

        if ($dedupeKey !== null) {
            $dedupeKey = mb_substr(trim($dedupeKey), 0, 120, 'UTF-8');
            if ($dedupeKey === '') {
                $dedupeKey = null;
            }
        }

        $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        $nowSql = $isSqlite ? "datetime('now')" : "NOW()";

        // 3. Deduplication check: Increment occurrences if unread alert with same key exists
        if ($dedupeKey !== null) {
            $checkStmt = $pdo->prepare(
                "SELECT id, occurrences FROM admin_alerts 
                 WHERE dedupe_key = ? AND is_read = 0 
                 ORDER BY id DESC LIMIT 1"
            );
            $checkStmt->execute([$dedupeKey]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $existingId = (int)$existing['id'];
                $updateStmt = $pdo->prepare(
                    "UPDATE admin_alerts 
                     SET occurrences = occurrences + 1, last_seen_at = {$nowSql} 
                     WHERE id = ?"
                );
                $updateStmt->execute([$existingId]);

                _dispatchAlertEmailIfEligible($pdo, $type, $severity, $title, $body, $link);
                return $existingId;
            }
        }

        // 4. Global unread cap check (Max 500 unread rows)
        $unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_alerts WHERE is_read = 0")->fetchColumn();
        if ($unreadCount >= 500) {
            $overflowKey = 'alerts_overflow_collapsed';
            $ofCheck = $pdo->prepare(
                "SELECT id FROM admin_alerts WHERE dedupe_key = ? AND is_read = 0 LIMIT 1"
            );
            $ofCheck->execute([$overflowKey]);
            $overflowRow = $ofCheck->fetch(PDO::FETCH_ASSOC);

            if ($overflowRow) {
                $ofId = (int)$overflowRow['id'];
                $pdo->prepare("UPDATE admin_alerts SET occurrences = occurrences + 1, last_seen_at = {$nowSql} WHERE id = ?")
                    ->execute([$ofId]);
                return $ofId;
            } else {
                // Insert the collapse summary row
                $insertOf = $pdo->prepare(
                    "INSERT INTO admin_alerts (type, severity, title, body, link, dedupe_key, occurrences, is_read, created_at, last_seen_at)
                     VALUES ('system.overflow', 'warning', 'Alert Queue Cap Reached (500+ unread)', 'Multiple incoming alerts collapsed to prevent database flooding. Please review and mark alerts as read.', 'audit-log.php', ?, 1, 0, {$nowSql}, {$nowSql})"
                );
                $insertOf->execute([$overflowKey]);
                return (int)$pdo->lastInsertId();
            }
        }

        // 5. Normal insert
        $insertStmt = $pdo->prepare(
            "INSERT INTO admin_alerts (type, severity, title, body, link, dedupe_key, occurrences, is_read, created_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, 0, {$nowSql}, {$nowSql})"
        );
        $insertStmt->execute([$type, $severity, $title, $body, $link, $dedupeKey]);
        $alertId = (int)$pdo->lastInsertId();

        // 6. Email dispatch for high severity or admin_new_ip
        _dispatchAlertEmailIfEligible($pdo, $type, $severity, $title, $body, $link);

        return $alertId;

    } catch (Throwable $e) {
        error_log('[alerts] createAdminAlert error: ' . $e->getMessage());
        return null;
    } finally {
        $isEntering = false;
    }
}

/**
 * Dispatches an email notification to the site owner for critical alerts or admin_new_ip.
 * Throttled to at most 1 email per alert type per hour.
 */
function _dispatchAlertEmailIfEligible(
    PDO $pdo,
    string $type,
    string $severity,
    string $title,
    ?string $body,
    ?string $link
): void {
    // 1. Never email on info alerts
    if ($severity === 'info') {
        return;
    }

    // 2. Never email if type is mail.failed (avoids recursive failures)
    if ($type === 'mail.failed') {
        return;
    }

    // 3. Only send email for critical alerts or login.admin_new_ip
    $isEligible = ($severity === 'critical' || $type === 'login.admin_new_ip');
    if (!$isEligible) {
        return;
    }

    // 4. Check throttle: at most 1 email per alert type per hour (3600 seconds)
    $now = time();
    $canSend = false;

    _alertFileWithLock('alert_email_throttles.json', function (array $data) use ($type, $now, &$canSend): array {
        $lastSent = $data[$type] ?? 0;
        if (($now - $lastSent) >= 3600) {
            $data[$type] = $now;
            $canSend = true;
        }
        return $data;
    });

    if (!$canSend) {
        return;
    }

    // 5. Send email via existing mailer & templates
    try {
        require_once __DIR__ . '/mailer.php';
        require_once __DIR__ . '/email_templates.php';

        // Resolve owner recipient email
        $pStmt = $pdo->query('SELECT public_email FROM site_profile LIMIT 1');
        $ownerEmail = ($pRow = $pStmt->fetch(PDO::FETCH_ASSOC)) ? ($pRow['public_email'] ?? '') : '';
        if (empty($ownerEmail)) {
            $ownerEmail = defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com';
        }

        $introLines = [];
        if ($body !== null && $body !== '') {
            $introLines[] = $body;
        }

        $securityNotice = '';
        if ($type === 'login.admin_new_ip') {
            $securityNotice = 'If this was not you, change your account password immediately in Admin Studio.';
        } elseif ($severity === 'critical') {
            $securityNotice = 'This is an urgent security or operational notification requiring administrative attention.';
        }

        $tpl = renderEmail([
            'preheader'       => $title,
            'eyebrow'         => '// ADMIN SECURITY ALERT',
            'title'           => $title,
            'greeting'        => 'Attention Administrator,',
            'intro'           => $introLines,
            'info_box'        => [
                'rows' => [
                    ['label' => 'Alert Type',  'value' => $type],
                    ['label' => 'Severity',    'value' => strtoupper($severity)],
                    ['label' => 'Triggered At', 'value' => date('Y-m-d H:i:s T')],
                ],
            ],
            'security_notice' => $securityNotice,
            'footer_note'     => 'Manage alerts in Admin Studio.',
        ]);

        sendMail($ownerEmail, '[Admin Studio Alert] ' . $title, $tpl['html'], $tpl['text']);

    } catch (Throwable $e) {
        error_log('[alerts] Email dispatch error: ' . $e->getMessage());
    }
}

/**
 * Runs opportunistic maintenance:
 * 1. Retention purge: Deletes read alerts older than 60 days.
 * 2. Stale backup check: Alerts if latest backup is older than 3 days (only if backups exist).
 *
 * Throttled to run at most once every 24 hours.
 */
function runOpportunisticAlertMaintenance(PDO $pdo): void
{
    $now = time();
    $ran = false;

    _alertFileWithLock('alerts_maintenance.json', function (array $data) use ($now, &$ran): array {
        $lastRun = $data['last_run'] ?? 0;
        if (($now - $lastRun) >= 86400) {
            $data['last_run'] = $now;
            $ran = true;
        }
        return $data;
    });

    if (!$ran) {
        return;
    }

    try {
        // Step 1: Retention purge (read alerts older than 60 days)
        if (adminAlertsTableExists($pdo)) {
            $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            if ($isSqlite) {
                $pdo->exec("DELETE FROM admin_alerts WHERE is_read = 1 AND created_at < datetime('now', '-60 days')");
            } else {
                $pdo->exec("DELETE FROM admin_alerts WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
            }
        }

        // Step 2: Stale backup check (only if backups exist and at least one file exists)
        $backupHelperPath = __DIR__ . '/backup.php';
        if (file_exists($backupHelperPath)) {
            require_once $backupHelperPath;
            if (function_exists('listBackupFiles')) {
                $files = listBackupFiles();
                if (count($files) > 0) {
                    $newest = $files[0];
                    $threeDaysAgo = $now - (3 * 86400);
                    if ($newest['mtime'] < $threeDaysAgo) {
                        createAdminAlert(
                            'backup.stale',
                            'warning',
                            'Database Backups Outdated',
                            'The newest database snapshot is older than 3 days (' . ($newest['created_at'] ?? 'unknown date') . '). Please run a backup snapshot.',
                            'backups.php',
                            'backup_stale_' . date('Ymd')
                        );
                    }
                }
            }
        }

    } catch (Throwable $e) {
        error_log('[alerts] Maintenance error: ' . $e->getMessage());
    }
}

/**
 * Returns cheap badge counts for polling:
 * - unread_alerts
 * - new_support
 * - pending_reviews
 */
function getAdminAlertCounts(PDO $pdo): array
{
    $unreadAlerts = 0;
    $newSupport = 0;
    $pendingReviews = 0;

    try {
        if (adminAlertsTableExists($pdo)) {
            $unreadAlerts = (int)$pdo->query("SELECT COUNT(*) FROM admin_alerts WHERE is_read = 0")->fetchColumn();
        }
    } catch (Throwable $e) {
        $unreadAlerts = 0;
    }

    try {
        $newSupport = (int)$pdo->query("SELECT COUNT(*) FROM support_messages WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $e) {
        $newSupport = 0;
    }

    try {
        $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        $pendingReviews = 0;
    }

    return [
        'unread_alerts'   => $unreadAlerts,
        'new_support'     => $newSupport,
        'pending_reviews' => $pendingReviews,
    ];
}

/**
 * Fetches the latest administrative alerts with relative age in seconds.
 *
 * @param PDO $pdo
 * @param int $limit Maximum items (default 10)
 * @param string|null $since Optional ISO timestamp or integer ID
 * @return array
 */
function getAdminAlertsList(PDO $pdo, int $limit = 10, ?string $since = null): array
{
    if (!adminAlertsTableExists($pdo)) {
        return [];
    }

    $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
    $ageSql = $isSqlite
        ? "CAST((strftime('%s', 'now') - strftime('%s', created_at)) AS INTEGER) AS age_seconds"
        : "TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds";

    $sql = "SELECT id, type, severity, title, body, link, dedupe_key, occurrences, is_read, created_at, last_seen_at, {$ageSql}
            FROM admin_alerts ";
    $params = [];

    if ($since !== null && trim($since) !== '') {
        $since = trim($since);
        if (ctype_digit($since)) {
            $sql .= "WHERE id > ? ";
            $params[] = (int)$since;
        } else {
            $sql .= "WHERE created_at > ? ";
            $params[] = $since;
        }
    }

    $sql .= "ORDER BY is_read ASC, last_seen_at DESC, id DESC LIMIT " . (int)$limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): array {
            return [
                'id'          => (int)$row['id'],
                'type'        => (string)$row['type'],
                'severity'    => (string)$row['severity'],
                'title'       => (string)$row['title'],
                'body'        => $row['body'] !== null ? (string)$row['body'] : null,
                'link'        => $row['link'] !== null ? (string)$row['link'] : null,
                'occurrences' => (int)$row['occurrences'],
                'is_read'     => (bool)$row['is_read'],
                'created_at'  => (string)$row['created_at'],
                'last_seen_at'=> (string)$row['last_seen_at'],
                'age_seconds' => isset($row['age_seconds']) ? (int)$row['age_seconds'] : 0,
            ];
        }, $rows);
    } catch (Throwable $e) {
        error_log('[alerts] getAdminAlertsList error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Marks one or all admin alerts as read.
 */
function markAdminAlertsRead(PDO $pdo, ?int $id = null, bool $all = false): int
{
    if (!adminAlertsTableExists($pdo)) {
        return 0;
    }

    try {
        if ($all) {
            $stmt = $pdo->prepare("UPDATE admin_alerts SET is_read = 1 WHERE is_read = 0");
            $stmt->execute();
            return $stmt->rowCount();
        } elseif ($id !== null && $id > 0) {
            $stmt = $pdo->prepare("UPDATE admin_alerts SET is_read = 1 WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->rowCount();
        }
    } catch (Throwable $e) {
        error_log('[alerts] markAdminAlertsRead error: ' . $e->getMessage());
    }

    return 0;
}

