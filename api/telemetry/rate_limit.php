<?php
// ============================================================
// TELEMETRY RATE LIMITING (SEC-07 / fixes S-5)
// ============================================================
// api/telemetry/visit.php and api/telemetry/view.php are, by
// design, unauthenticated and CSRF-exempt — telemetry has to stay
// anonymous, and a real visitor's browser is not carrying a CSRF
// token for a beacon fetch. The audit's finding was that this left
// them the only two anonymous write endpoints in the app with no
// throttling at all (api/reviews/submit.php already has one — see
// api/reviews/rate_limit.php, which this file deliberately mirrors:
// same file-based approach, same flock() concurrency pattern, same
// api/data/ storage directory, same fail-open philosophy).
//
// Policy: an IP may fire at most TELEMETRY_RATE_LIMIT_MAX beacons
// per TELEMETRY_RATE_LIMIT_WINDOW seconds, PER ENDPOINT (visit and
// view are tracked separately, since a real visit fires once per
// page load and a real view fires once per article read — treating
// them as one shared budget would let heavy browsing exhaust the
// article-view budget). The limit is intentionally generous: it
// exists to stop scripted flooding of the analytics counters, not
// to throttle an actual visitor clicking around the site quickly.
//
// Checked AFTER check_telemetry_guards() (so a DNT/GPC/bot request
// still costs nothing — no file I/O, no DB) but BEFORE any database
// work, matching the "cheap check before expensive work" ordering
// already used by the login and review rate limiters.
//
// Fails open: if the storage file can't be created or locked, the
// beacon is allowed through — an I/O hiccup should not silently
// stop analytics from recording, and this is abuse-prevention, not
// an account-security control.
// ============================================================

require_once dirname(__DIR__) . '/config.php';

const TELEMETRY_RATE_LIMIT_MAX    = 60;   // beacons allowed per window, per IP, per endpoint
const TELEMETRY_RATE_LIMIT_WINDOW = 300;  // 5 minutes, in seconds

// ---- INTERNAL — client IP via shared getClientIp() helper -----------------
function _telemetryRateLimitClientIp(): string {
    return getClientIp();
}

// ---- INTERNAL — path to the storage file ---------------------------------
function _telemetryRateLimitStorePath(): string {

    $dir = __DIR__ . '/../data';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir . '/telemetry_attempts.json';

}

// ---- INTERNAL — run $callback with exclusive lock on the store ----------
function _telemetryRateLimitWithLock(callable $callback): void {

    $path = _telemetryRateLimitStorePath();

    $fh = @fopen($path, 'c+');
    if ($fh === false) {
        error_log('[telemetry_rate_limit] Could not open ' . $path . ' — failing open');
        return;
    }

    try {

        if (!flock($fh, LOCK_EX)) {
            error_log('[telemetry_rate_limit] Could not lock ' . $path . ' — failing open');
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

// ---- INTERNAL — composite key: one bucket per (endpoint, IP) ------------
function _telemetryRateLimitKey(string $endpoint): string {
    return $endpoint . ':' . _telemetryRateLimitClientIp();
}

// ---- PUBLIC — check whether this endpoint+IP is currently rate-limited --
// Returns true if the request should be dropped (limit exceeded),
// false if it should proceed. Deliberately returns a bool rather
// than a "seconds remaining" value like the login/review limiters:
// telemetry callers respond with a silent 204 either way (per the
// existing telemetry design — never surface a 429 to a beacon), so
// there is nothing for a caller to do with a wait time.
function telemetryRateLimitExceeded(string $endpoint): bool {

    $key      = _telemetryRateLimitKey($endpoint);
    $exceeded = false;

    _telemetryRateLimitWithLock(function (array $data) use ($key, &$exceeded): array {

        if (!isset($data[$key]) || !is_array($data[$key])) {
            return $data;
        }

        $entry = $data[$key];
        $now   = time();

        // Expired window — clear it, request proceeds.
        if (
            !isset($entry['window_started']) ||
            ($now - $entry['window_started']) >= TELEMETRY_RATE_LIMIT_WINDOW
        ) {
            unset($data[$key]);
            return $data;
        }

        if (!empty($entry['count']) && $entry['count'] >= TELEMETRY_RATE_LIMIT_MAX) {
            $exceeded = true;
        }

        return $data;

    });

    return $exceeded;

}

// ---- PUBLIC — record one beacon for this endpoint+IP ---------------------
function telemetryRateLimitRecord(string $endpoint): void {

    $key = _telemetryRateLimitKey($endpoint);

    _telemetryRateLimitWithLock(function (array $data) use ($key): array {

        $now   = time();
        $entry = $data[$key] ?? null;

        if (
            !is_array($entry) ||
            !isset($entry['window_started']) ||
            ($now - $entry['window_started']) >= TELEMETRY_RATE_LIMIT_WINDOW
        ) {
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
