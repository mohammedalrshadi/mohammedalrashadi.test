<?php
// ============================================================
// LOGIN RATE LIMITING (SEC-03 / IMP-009)
// Multi-tier brute-force protection for api/auth/login.php
// ============================================================
// Deliberately does NOT use the database — this is a security
// control, not application data, and must keep working even if
// database credentials are misconfigured. It also avoids schema changes.
//
// Storage: a single JSON file under api/data/, protected by .htaccess.
//
// Policy:
// - Tier 1 (IP + Email pair): max 5 failures per 15 min -> 15 min lockout
// - Tier 2 (IP overall):      max 15 failures per 15 min -> 15 min lockout
// - Tier 3 (Account overall): max 20 failures per 15 min -> 15 min lockout
//
// Un-spoofable IP identity: uses getClientIp() (api/config.php) — the TCP peer
// (REMOTE_ADDR) unless that peer is in TRUSTED_PROXIES, in which case the
// rightmost non-trusted X-Forwarded-For hop is used. Behind a CDN/proxy you MUST
// set TRUSTED_PROXIES in api/config.local.php, otherwise all visitors share the
// proxy's IP and the IP-wide tier below can lock everyone out.
//
// Concurrency: every read-modify-write is wrapped in an exclusive
// flock() on the same file to guarantee atomicity.
// ============================================================

require_once dirname(__DIR__) . '/config.php';

const LOGIN_RATE_LIMIT_PAIR_MAX     = 5;      // failed attempts allowed per (IP, email) pair
const LOGIN_RATE_LIMIT_IP_MAX       = 15;     // failed attempts allowed per IP across all accounts
const LOGIN_RATE_LIMIT_ACCOUNT_MAX  = 20;     // failed attempts allowed per email across all IPs
const LOGIN_RATE_LIMIT_WINDOW       = 900;    // 15 minutes, in seconds
const LOGIN_RATE_LIMIT_LOCKOUT      = 900;    // 15 minutes, in seconds

// ---- INTERNAL — client IP via shared getClientIp() helper -----------------
function _rateLimitClientIp(): string {
    return getClientIp();
}

// ---- INTERNAL — normalize email for consistent tracking ------------------
function _rateLimitNormalizeEmail(?string $email): string {
    if ($email === null) {
        return '';
    }
    return strtolower(trim($email));
}

// ---- INTERNAL — key generation -------------------------------------------
function _rateLimitPairKey(string $ip, string $email): string {
    return 'pair:' . hash('sha256', $ip . '|' . $email);
}

function _rateLimitIpKey(string $ip): string {
    return 'ip:' . hash('sha256', $ip);
}

function _rateLimitAccountKey(string $email): string {
    return 'account:' . hash('sha256', $email);
}

// ---- INTERNAL — path to storage file -------------------------------------
function _rateLimitStorePath(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/login_attempts.json';
}

// ---- INTERNAL — prune expired entries ------------------------------------
function _rateLimitPrune(array $data, int $now): array {
    foreach ($data as $key => $entry) {
        if (!is_array($entry)) {
            unset($data[$key]);
            continue;
        }

        // 60-second cooldown entries
        if (isset($entry['last_sent'])) {
            if (($now - $entry['last_sent']) >= 60) {
                unset($data[$key]);
            }
            continue;
        }

        // 24-hour windows (daily caps)
        if (str_starts_with((string)$key, 'resend:daily:')) {
            $windowStarted = $entry['window_started'] ?? 0;
            if (($now - $windowStarted) >= 86400) {
                unset($data[$key]);
            }
            continue;
        }

        // 1-hour windows (register IP or resend IP)
        if (str_starts_with((string)$key, 'register:ip:') || str_starts_with((string)$key, 'resend:ip:')) {
            $windowStarted = $entry['window_started'] ?? 0;
            if (($now - $windowStarted) >= 3600) {
                unset($data[$key]);
            }
            continue;
        }

        $windowStarted = $entry['window_started'] ?? 0;
        $lockedUntil   = $entry['locked_until'] ?? null;

        if ($lockedUntil !== null) {
            // Expired lockout
            if ($now >= $lockedUntil) {
                unset($data[$key]);
            }
        } else {
            // Expired un-locked window
            if (($now - $windowStarted) >= LOGIN_RATE_LIMIT_WINDOW) {
                unset($data[$key]);
            }
        }
    }
    return $data;
}

// ---- INTERNAL — run callback with exclusive flock() ----------------------
function _rateLimitWithLock(callable $callback): void {
    $path = _rateLimitStorePath();
    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        error_log('[rate_limit] Could not open ' . $path . ' — failing open');
        return;
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            error_log('[rate_limit] Could not lock ' . $path . ' — failing open');
            return;
        }

        $raw  = stream_get_contents($fh);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $now  = time();
        $data = _rateLimitPrune($data, $now);

        $data = $callback($data, $now);

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);

        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
}

// ---- PUBLIC — check whether IP or Account is currently locked out --------
function loginRateLimitCheck(?string $email = null): ?int {
    $ip = _rateLimitClientIp();
    $normEmail = _rateLimitNormalizeEmail($email);

    $keys = [_rateLimitIpKey($ip)];
    if ($normEmail !== '') {
        $keys[] = _rateLimitPairKey($ip, $normEmail);
        $keys[] = _rateLimitAccountKey($normEmail);
    }

    $secondsRemaining = null;

    _rateLimitWithLock(function (array $data, int $now) use ($keys, &$secondsRemaining): array {
        foreach ($keys as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $entry = $data[$k];
                if (!empty($entry['locked_until']) && $entry['locked_until'] > $now) {
                    $diff = $entry['locked_until'] - $now;
                    if ($secondsRemaining === null || $diff > $secondsRemaining) {
                        $secondsRemaining = $diff;
                    }
                }
            }
        }
        return $data;
    });

    return $secondsRemaining;
}

// ---- PUBLIC — record a failed login attempt -----------------------------
function loginRateLimitRecordFailure(?string $email = null): void {
    $ip = _rateLimitClientIp();
    $normEmail = _rateLimitNormalizeEmail($email);
    $justLockedOut = false;

    _rateLimitWithLock(function (array $data, int $now) use ($ip, $normEmail, &$justLockedOut): array {
        $targets = [
            _rateLimitIpKey($ip) => LOGIN_RATE_LIMIT_IP_MAX,
        ];

        if ($normEmail !== '') {
            $targets[_rateLimitPairKey($ip, $normEmail)] = LOGIN_RATE_LIMIT_PAIR_MAX;
            $targets[_rateLimitAccountKey($normEmail)]   = LOGIN_RATE_LIMIT_ACCOUNT_MAX;
        }

        foreach ($targets as $key => $maxAttempts) {
            $entry = $data[$key] ?? null;

            if (
                !is_array($entry) ||
                !isset($entry['window_started']) ||
                ($now - $entry['window_started']) >= LOGIN_RATE_LIMIT_WINDOW
            ) {
                $entry = [
                    'count'          => 0,
                    'window_started' => $now,
                    'locked_until'   => null,
                ];
            }

            $entry['count']++;

            if ($entry['count'] === $maxAttempts) {
                $entry['locked_until'] = $now + LOGIN_RATE_LIMIT_LOCKOUT;
                $justLockedOut = true;
            } elseif ($entry['count'] > $maxAttempts) {
                $entry['locked_until'] = $now + LOGIN_RATE_LIMIT_LOCKOUT;
            }

            $data[$key] = $entry;
        }

        return $data;
    });

    // Fire burst alert ONLY for real login attempts (identifier must look like an email with '@')
    if ($justLockedOut && $normEmail !== '' && strpos($normEmail, '@') !== false) {
        _dispatchLoginBurstAlert($ip, $normEmail);
    }
}

/**
 * Dispatches login.failed_burst alert when a lockout threshold is first reached.
 * Wrapped defensively: rate limiter continues functioning even if DB is unavailable.
 * Only fires for real login attempts (normEmail must contain '@').
 *
 * @param string $ip
 * @param string $normEmail
 * @param PDO|null $pdo Optional PDO instance for testing
 * @return int|null Inserted alert ID or null
 */
function _dispatchLoginBurstAlert(string $ip, string $normEmail, ?PDO $pdo = null): ?int {
    // Fire alert ONLY for real login attempts: identifier must contain '@'
    if ($normEmail === '' || strpos($normEmail, '@') === false) {
        return null;
    }

    try {
        $isAdmin = false;
        if ($normEmail !== '') {
            try {
                $db = $pdo ?? (function_exists('getDB') ? getDB() : null);
                if ($db) {
                    $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
                    $stmt->execute([$normEmail]);
                    $isAdmin = (bool)$stmt->fetch();
                }
            } catch (Throwable $e) {
                // DB offline: fail safe, assume false
                $isAdmin = false;
            }
        }

        $alertsHelper = dirname(__DIR__) . '/helpers/alerts.php';
        if (!file_exists($alertsHelper)) {
            return null;
        }
        require_once $alertsHelper;

        if ($isAdmin) {
            // Bursts targeting an ADMIN account: per-IP alert, critical severity, dedupe 15m
            $timeSlot = floor(time() / 900);
            $dedupeKey = 'login_burst_admin_' . hash('sha256', $ip) . '_' . $timeSlot;
            return createAdminAlert(
                'login.failed_burst',
                'critical',
                'Critical: Brute-Force Lockout on Admin Account',
                'Lockout threshold reached for admin ' . mb_substr($normEmail, 0, 80, 'UTF-8') . ' after repeated failures from IP ' . $ip . '. Temporary lockout active.',
                'audit-log.php',
                $dedupeKey,
                $pdo
            );
        } else {
            // Non-admin / non-existent account bursts: aggregated ONE alert per hour, warning severity, NO email
            // Occurrences counts distinct IPs in the hour
            $hourBucket = date('YmdH');
            $dedupeKey = 'login_burst_nonadmin_' . $hourBucket;

            $isDistinctIp = false;
            $distinctCount = 1;

            if (function_exists('_alertFileWithLock')) {
                _alertFileWithLock('burst_ips_' . $hourBucket . '.json', function (array $data) use ($ip, &$isDistinctIp, &$distinctCount): array {
                    $seen = $data['ips'] ?? [];
                    if (!in_array($ip, $seen, true)) {
                        $seen[] = $ip;
                        $isDistinctIp = true;
                    }
                    $data['ips'] = $seen;
                    $distinctCount = count($seen);
                    return $data;
                });
            } else {
                $isDistinctIp = true;
            }

            // Only increment / insert alert if this is a distinct IP in this hour bucket
            if ($isDistinctIp) {
                return createAdminAlert(
                    'login.failed_burst',
                    'warning',
                    'Failed Login Bursts Detected (Non-Admin)',
                    'Lockout thresholds reached across non-admin or unregistered accounts. Unique IP addresses involved this hour: ' . $distinctCount . '.',
                    'audit-log.php',
                    $dedupeKey,
                    $pdo
                );
            }
            return null;
        }
    } catch (Throwable $e) {
        error_log('[rate_limit] Alert dispatch error: ' . $e->getMessage());
        return null;
    }
}

// ---- PUBLIC — clear counters upon successful admin login ----------------
function loginRateLimitClear(?string $email = null): void {
    $ip = _rateLimitClientIp();
    $normEmail = _rateLimitNormalizeEmail($email);

    _rateLimitWithLock(function (array $data) use ($ip, $normEmail): array {
        unset($data[_rateLimitIpKey($ip)]);
        if ($normEmail !== '') {
            unset($data[_rateLimitPairKey($ip, $normEmail)]);
            unset($data[_rateLimitAccountKey($normEmail)]);
        }
        return $data;
    });
}

// ============================================================
// REGISTRATION RATE LIMITING (5 signups per hour per IP)
// ============================================================
const REGISTER_RATE_LIMIT_MAX    = 5;
const REGISTER_RATE_LIMIT_WINDOW = 3600; // 1 hour

function registerRateLimitCheck(?string $ip = null): ?int {
    $clientIp = $ip ?: _rateLimitClientIp();
    $key      = 'register:ip:' . hash('sha256', $clientIp);
    $wait     = null;

    _rateLimitWithLock(function (array $data, int $now) use ($key, &$wait): array {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            return $data;
        }

        $entry = $data[$key];
        if (!isset($entry['window_started']) || ($now - $entry['window_started']) >= REGISTER_RATE_LIMIT_WINDOW) {
            unset($data[$key]);
            return $data;
        }

        if (!empty($entry['count']) && $entry['count'] >= REGISTER_RATE_LIMIT_MAX) {
            $windowEnd = $entry['window_started'] + REGISTER_RATE_LIMIT_WINDOW;
            $wait      = max(1, $windowEnd - $now);
        }

        return $data;
    });

    return $wait;
}

function registerRateLimitRecord(?string $ip = null): void {
    $clientIp = $ip ?: _rateLimitClientIp();
    $key      = 'register:ip:' . hash('sha256', $clientIp);

    _rateLimitWithLock(function (array $data, int $now) use ($key): array {
        $entry = $data[$key] ?? null;
        if (!is_array($entry) || !isset($entry['window_started']) || ($now - $entry['window_started']) >= REGISTER_RATE_LIMIT_WINDOW) {
            $entry = [
                'count'          => 0,
                'window_started' => $now,
            ];
        }

        $entry['count']++;
        $data[$key] = $entry;
        return $data;
    });
}

// ============================================================
// RESEND VERIFICATION RATE LIMITING (60s cooldown, daily cap, IP cap)
// ============================================================
const RESEND_COOLDOWN_SECONDS   = 60;   // 60-second cooldown per user/email
const RESEND_DAILY_MAX          = 10;   // Maximum 10 resends per 24 hours per user/email
const RESEND_DAILY_WINDOW       = 86400; // 24 hours
const RESEND_IP_MAX             = 15;   // Maximum 15 requests per hour per IP
const RESEND_IP_WINDOW          = 3600; // 1 hour

function resendRateLimitCheck(string $email, ?string $ip = null): ?string {
    $clientIp  = $ip ?: _rateLimitClientIp();
    $normEmail = _rateLimitNormalizeEmail($email);

    $cdKey    = 'resend:cd:' . hash('sha256', $normEmail);
    $dailyKey = 'resend:daily:' . hash('sha256', $normEmail);
    $ipKey    = 'resend:ip:' . hash('sha256', $clientIp);

    $blockedReason = null;

    _rateLimitWithLock(function (array $data, int $now) use ($cdKey, $dailyKey, $ipKey, &$blockedReason): array {
        // 1. Check 60s cooldown
        if (isset($data[$cdKey]) && is_array($data[$cdKey])) {
            $lastSent = $data[$cdKey]['last_sent'] ?? 0;
            if (($now - $lastSent) < RESEND_COOLDOWN_SECONDS) {
                $rem = RESEND_COOLDOWN_SECONDS - ($now - $lastSent);
                $blockedReason = "Please wait {$rem} seconds before requesting another email.";
                return $data;
            }
        }

        // 2. Check daily cap
        if (isset($data[$dailyKey]) && is_array($data[$dailyKey])) {
            $win = $data[$dailyKey]['window_started'] ?? 0;
            if (($now - $win) < RESEND_DAILY_WINDOW) {
                if (($data[$dailyKey]['count'] ?? 0) >= RESEND_DAILY_MAX) {
                    $blockedReason = "Daily verification request limit reached. Please check your spam folder or try again tomorrow.";
                    return $data;
                }
            } else {
                unset($data[$dailyKey]);
            }
        }

        // 3. Check IP cap
        if (isset($data[$ipKey]) && is_array($data[$ipKey])) {
            $win = $data[$ipKey]['window_started'] ?? 0;
            if (($now - $win) < RESEND_IP_WINDOW) {
                if (($data[$ipKey]['count'] ?? 0) >= RESEND_IP_MAX) {
                    $blockedReason = "Too many verification requests from this network. Please try again later.";
                    return $data;
                }
            } else {
                unset($data[$ipKey]);
            }
        }

        return $data;
    });

    return $blockedReason;
}

function resendRateLimitRecord(string $email, ?string $ip = null): void {
    $clientIp  = $ip ?: _rateLimitClientIp();
    $normEmail = _rateLimitNormalizeEmail($email);

    $cdKey    = 'resend:cd:' . hash('sha256', $normEmail);
    $dailyKey = 'resend:daily:' . hash('sha256', $normEmail);
    $ipKey    = 'resend:ip:' . hash('sha256', $clientIp);

    _rateLimitWithLock(function (array $data, int $now) use ($cdKey, $dailyKey, $ipKey): array {
        // 1. Record cooldown
        $data[$cdKey] = ['last_sent' => $now];

        // 2. Increment daily cap
        $daily = $data[$dailyKey] ?? null;
        if (!is_array($daily) || !isset($daily['window_started']) || ($now - $daily['window_started']) >= RESEND_DAILY_WINDOW) {
            $daily = ['count' => 0, 'window_started' => $now];
        }
        $daily['count']++;
        $data[$dailyKey] = $daily;

        // 3. Increment IP cap
        $ipEntry = $data[$ipKey] ?? null;
        if (!is_array($ipEntry) || !isset($ipEntry['window_started']) || ($now - $ipEntry['window_started']) >= RESEND_IP_WINDOW) {
            $ipEntry = ['count' => 0, 'window_started' => $now];
        }
        $ipEntry['count']++;
        $data[$ipKey] = $ipEntry;

        return $data;
    });
}

// ============================================================
// FORM RATE LIMITING (e.g., support form)
// ============================================================
const FORM_RATE_LIMIT_MAX = 5;
const FORM_RATE_LIMIT_WINDOW = 900;
const FORM_RATE_LIMIT_LOCKOUT = 900;

function _formRateLimitStorePath(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/form_attempts.json';
}

function _formRateLimitWithLock(callable $callback): void {
    $path = _formRateLimitStorePath();
    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        error_log('[rate_limit] Could not open ' . $path . ' — failing open');
        return;
    }

    try {
        if (!flock($fh, LOCK_EX)) {
            error_log('[rate_limit] Could not lock ' . $path . ' — failing open');
            return;
        }

        $raw  = stream_get_contents($fh);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $now  = time();
        
        // Prune expired entries
        foreach ($data as $key => $entry) {
            if (!is_array($entry)) {
                unset($data[$key]);
                continue;
            }
            $windowStarted = $entry['window_started'] ?? 0;
            $lockedUntil   = $entry['locked_until'] ?? null;
            if ($lockedUntil !== null) {
                if ($now >= $lockedUntil) unset($data[$key]);
            } else {
                if (($now - $windowStarted) >= FORM_RATE_LIMIT_WINDOW) unset($data[$key]);
            }
        }

        $data = $callback($data, $now);

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);

        flock($fh, LOCK_UN);
    } finally {
        fclose($fh);
    }
}

function formRateLimitCheck(string $actionKey): ?int {
    $key = 'form:' . hash('sha256', $actionKey);
    $secondsRemaining = null;

    _formRateLimitWithLock(function (array $data, int $now) use ($key, &$secondsRemaining): array {
        if (isset($data[$key]) && is_array($data[$key])) {
            $entry = $data[$key];
            if (!empty($entry['locked_until']) && $entry['locked_until'] > $now) {
                $secondsRemaining = $entry['locked_until'] - $now;
            }
        }
        return $data;
    });

    return $secondsRemaining;
}

function formRateLimitRecord(string $actionKey): void {
    $key = 'form:' . hash('sha256', $actionKey);

    _formRateLimitWithLock(function (array $data, int $now) use ($key): array {
        $entry = $data[$key] ?? null;

        if (
            !is_array($entry) ||
            !isset($entry['window_started']) ||
            ($now - $entry['window_started']) >= FORM_RATE_LIMIT_WINDOW
        ) {
            $entry = [
                'count'          => 0,
                'window_started' => $now,
                'locked_until'   => null,
            ];
        }

        $entry['count']++;

        if ($entry['count'] >= FORM_RATE_LIMIT_MAX) {
            $entry['locked_until'] = $now + FORM_RATE_LIMIT_LOCKOUT;
        }

        $data[$key] = $entry;
        return $data;
    });
}

