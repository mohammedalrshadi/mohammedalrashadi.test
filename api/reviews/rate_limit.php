<?php
// ============================================================
// REVIEW SUBMISSION RATE LIMITING (SEC-06)
// ============================================================
// Lightweight, file-based rate limit for the public review
// submission endpoint (api/reviews/submit.php).
//
// Deliberately mirrors the architecture of api/auth/rate_limit.php
// (login rate limiting) — same file-based approach, same flock()
// concurrency pattern, same api/data/ storage directory.
//
// Policy: an IP may submit at most REVIEW_RATE_LIMIT_MAX per
// REVIEW_RATE_LIMIT_WINDOW seconds. Exceeding this threshold
// returns HTTP 429 and blocks further submissions until the
// window expires (no separate lockout period — the window IS
// the cooldown, which resets naturally).
//
// Separate from the duplicate-submission guard already in
// submit.php (which blocks identical content within 60 s).
// This limit guards against flooding with varied content.
//
// Fails open: if the storage file cannot be created or locked,
// the submission is allowed through rather than blocking a
// legitimate visitor because of a hosting I/O issue.
// ============================================================

require_once dirname(__DIR__) . '/config.php';

const REVIEW_RATE_LIMIT_MAX    = 5;    // submissions allowed per window
const REVIEW_RATE_LIMIT_WINDOW = 600;  // 10 minutes, in seconds

// ---- INTERNAL — client IP via shared getClientIp() helper -----------------
function _reviewRateLimitClientIp(): string {
    return getClientIp();
}

// ---- INTERNAL — path to the storage file --------------------------------
function _reviewRateLimitStorePath(): string {

    $dir = __DIR__ . '/../data';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir . '/review_attempts.json';

}

// ---- INTERNAL — run $callback with exclusive lock on the store ----------
function _reviewRateLimitWithLock(callable $callback): void {

    $path = _reviewRateLimitStorePath();

    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        error_log('[review_rate_limit] Could not open ' . $path . ' — failing open');
        return;
    }

    try {

        if (!flock($fh, LOCK_EX)) {
            error_log('[review_rate_limit] Could not lock ' . $path . ' — failing open');
            return;
        }

        $raw  = stream_get_contents($fh);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $data = $callback($data);

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);

        flock($fh, LOCK_UN);

    } finally {
        fclose($fh);
    }

}

// ---- PUBLIC — check whether the current IP is rate-limited --------------
// Returns the number of seconds until the window expires if limited,
// or null if the submission should be allowed.
function reviewRateLimitCheck(): ?int {

    $ip            = _reviewRateLimitClientIp();
    $waitRemaining = null;

    _reviewRateLimitWithLock(function (array $data) use ($ip, &$waitRemaining): array {

        if (!isset($data[$ip]) || !is_array($data[$ip])) {
            return $data;
        }

        $entry = $data[$ip];
        $now   = time();

        // If the window has expired, clear this IP's record.
        if (
            !isset($entry['window_started']) ||
            ($now - $entry['window_started']) >= REVIEW_RATE_LIMIT_WINDOW
        ) {
            unset($data[$ip]);
            return $data;
        }

        // Window is still active — check count.
        if (!empty($entry['count']) && $entry['count'] >= REVIEW_RATE_LIMIT_MAX) {
            $windowEnd     = $entry['window_started'] + REVIEW_RATE_LIMIT_WINDOW;
            $waitRemaining = max(1, $windowEnd - $now);
        }

        return $data;

    });

    return $waitRemaining;

}

// ---- PUBLIC — record a successful submission for the current IP ---------
function reviewRateLimitRecord(): void {

    $ip = _reviewRateLimitClientIp();

    _reviewRateLimitWithLock(function (array $data) use ($ip): array {

        $now   = time();
        $entry = $data[$ip] ?? null;

        // Start a fresh window if there's no entry, or the previous has expired.
        if (
            !is_array($entry) ||
            !isset($entry['window_started']) ||
            ($now - $entry['window_started']) >= REVIEW_RATE_LIMIT_WINDOW
        ) {
            $entry = [
                'count'          => 0,
                'window_started' => $now,
            ];
        }

        $entry['count']++;
        $data[$ip] = $entry;

        return $data;

    });

}
