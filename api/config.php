<?php
// ============================================================
// DATABASE CONFIGURATION
// ============================================================
// SECURITY (SEC-01 hardening):
//   Real database credentials are NEVER stored in this file, and
//   this file is safe to commit to Git.
//
//   Actual credentials live in api/config.local.php, which is:
//     - Excluded from Git via .gitignore
//     - Blocked from direct HTTP access via .htaccess
//     - Created once, by hand, directly on the server (Hostinger
//       File Manager) — it is never uploaded through Git/deploy.
//
//   See api/config.local.example.php for the required format.
// ============================================================

$__localConfig = __DIR__ . '/config.local.php';

if (!file_exists($__localConfig)) {
    http_response_code(500);
    error_log('[CONFIG] Missing api/config.local.php — see api/config.local.example.php');
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server configuration error. Please try again later.',
    ]);
    exit;
}

require_once $__localConfig;
unset($__localConfig);

// api/config.local.php is responsible for defining:
//   DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'] as $__requiredConst) {
    if (!defined($__requiredConst)) {
        http_response_code(500);
        error_log("[CONFIG] {$__requiredConst} not defined in api/config.local.php");
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Server configuration error. Please try again later.',
        ]);
        exit;
    }
}
unset($__requiredConst);
// ============================================================
// SESSION CONFIGURATION
// ============================================================
// Session cookie lifetime: 8 hours
define('SESSION_LIFETIME', 28800);

// ============================================================
// UPLOAD CONFIGURATION
// ============================================================
// Max file size: 20 MB (in bytes). Was 5 MB — raised because
// real-world cover photos routinely exceed 5 MB. Keep this in
// sync with MAX_UPLOAD_SIZE_BYTES in
// admin/js/common.js (the client-side pre-check) —
// this server-side value is the one that's actually enforced;
// the frontend check only avoids a wasted round-trip.
//
// IMPORTANT: Server-level PHP upload limits must accommodate this value.
// On Hostinger production (LiteSpeed / LSPHP), upload_max_filesize and
// post_max_size are configured at 2048M via hPanel PHP Options, which
// safely exceeds this application limit. (See HOSTINGER_SETUP.md).
// Note: .user.ini is not used in this environment.
define('UPLOAD_MAX_SIZE', 20 * 1024 * 1024);

// Allowed MIME types for image uploads
define('UPLOAD_ALLOWED_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
]);

// Allowed file extensions (lowercase)
define('UPLOAD_ALLOWED_EXTENSIONS', [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
]);

// Upload directory (relative to public_html root)
// This directory MUST have PHP execution disabled via .htaccess
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');

// Public URL path to uploaded files
define('UPLOAD_URL_PATH', '/uploads/');

// ============================================================
// TRUSTED PROXIES & CLIENT IP RESOLUTION (SEC-01 / Phase P0)
// ============================================================
// TRUSTED_PROXIES lists the reverse proxies / load balancers / CDNs (e.g.
// Cloudflare) that sit directly in front of this server. Each entry is either an
// exact IP ("203.0.113.7", "::1") or a CIDR range ("173.245.48.0/20").
//
// Default: loopback only. With no proxy configured, the client IP is always the
// TCP peer (REMOTE_ADDR) and X-Forwarded-For is ignored, so it cannot be spoofed.
// Only when REMOTE_ADDR itself is a trusted proxy is X-Forwarded-For consulted —
// and then the chain is read from the RIGHT, skipping trusted proxies, because
// proxies append to (never replace) any value the client sent; the leftmost
// entry is attacker-controlled.
//
// Deploying behind Cloudflare/another CDN? Set TRUSTED_PROXIES in
// api/config.local.php to that provider's published ranges. If you don't, every
// visitor appears as the proxy's IP, and the login rate limiter (which keys on
// getClientIp()) will lock out all users that share it.
if (!defined('TRUSTED_PROXIES')) {
    define('TRUSTED_PROXIES', ['127.0.0.1', '::1']);
}

if (!function_exists('isTrustedProxyIp')) {
    /**
     * True if $ip matches an entry (exact IP or CIDR) in TRUSTED_PROXIES.
     */
    function isTrustedProxyIp(string $ip): bool {
        $trusted = defined('TRUSTED_PROXIES') && is_array(TRUSTED_PROXIES) ? TRUSTED_PROXIES : ['127.0.0.1', '::1'];

        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }

        foreach ($trusted as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }

            // Exact IP
            if (strpos($entry, '/') === false) {
                $entryBin = @inet_pton($entry);
                if ($entryBin !== false && $entryBin === $ipBin) {
                    return true;
                }
                continue;
            }

            // CIDR range
            [$net, $bits] = explode('/', $entry, 2);
            $netBin = @inet_pton($net);
            if ($netBin === false || $bits === '' || !ctype_digit($bits)) {
                continue;
            }
            $bits = (int) $bits;
            if (strlen($netBin) !== strlen($ipBin) || $bits > strlen($netBin) * 8) {
                continue; // address-family mismatch or impossible prefix length
            }
            $fullBytes = intdiv($bits, 8);
            $remBits   = $bits % 8;
            if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
                continue;
            }
            if ($remBits > 0) {
                $mask = (0xFF << (8 - $remBits)) & 0xFF;
                if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($netBin[$fullBytes]) & $mask)) {
                    continue;
                }
            }
            return true;
        }

        return false;
    }
}

if (!function_exists('getClientIp')) {
    /**
     * Resolves client IP address safely, respecting TRUSTED_PROXIES to prevent
     * HTTP_X_FORWARDED_FOR header spoofing.
     */
    function getClientIp(): string {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

        // Only inspect X-Forwarded-For if REMOTE_ADDR is itself a trusted proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && isTrustedProxyIp($remoteAddr)) {
            // Walk the chain right-to-left; the first hop that is not a trusted
            // proxy is the closest address we can actually vouch for.
            $chain = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($chain as $candidate) {
                if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
                    break; // malformed entry — stop trusting the header, fall back to REMOTE_ADDR
                }
                if (isTrustedProxyIp($candidate)) {
                    continue;
                }
                return $candidate;
            }
        }

        if (filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }

        return !empty($remoteAddr) ? $remoteAddr : '0.0.0.0';
    }
}
