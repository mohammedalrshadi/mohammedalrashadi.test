<?php
// ============================================================
// SHARED HELPER — Session bootstrap + admin authorization
// Include this at the top of every protected endpoint or page.
// ============================================================

// Never DISPLAY errors — notices/warnings must not corrupt JSON output or leak
// internals to visitors. But keep them REPORTED so real bugs reach the server
// error log instead of being silently discarded by error_reporting(0).
// (E_DEPRECATED is excluded to keep third-party/vendor noise out of the log.)
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

// ============================================================
// INTERNAL — start the session with hardened cookie params
// (idempotent: safe to call even if a session is already active)
// ============================================================
function _startSecureSession(): void {

    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // Detect HTTPS correctly on Hostinger (SSL terminated at load balancer)
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')
    );

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure',   $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

}

// ============================================================
// INTERNAL — is the current session a valid role='admin' session?
//
// IMPORTANT: this re-checks the role against the DATABASE on
// every call, not just the cached session value. Without this,
// an admin who gets demoted to role='user' by another admin
// would keep full admin access for the rest of their session —
// a real privilege-escalation hole. This app is low-traffic
// (a personal admin panel), so the extra query per request is
// a non-issue; correctness matters more here than saving one
// SELECT.
// ============================================================
function _isAdminSession(): bool {

    _startSecureSession();

    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['user_email']) ||
        !isset($_SESSION['user_role']) ||
        !is_int($_SESSION['user_id']) ||
        $_SESSION['user_id'] <= 0
    ) {
        return false;
    }

    // Re-check the CURRENT role/existence from the database.
    // A cached role in $_SESSION is not trusted for the actual
    // authorization decision — only used as a fast pre-check above.
    try {

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT role, status, password_changed_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();

    } catch (PDOException $e) {

        error_log('[guard] DB error during role re-check: ' . $e->getMessage());
        // Fail closed — if we can't verify, don't grant admin access
        return false;

    }

    // User was deleted after the session was created
    if (!$row) {
        return false;
    }

    // Invalidate if account is suspended or banned
    if (isset($row['status']) && $row['status'] !== 'active') {
        session_unset();
        return false;
    }

    // Invalidate session if password changed after session was created
    if (!empty($row['password_changed_at'])) {
        $changedTime = strtotime((string)$row['password_changed_at']);
        $loginTime   = isset($_SESSION['login_time']) ? (int)$_SESSION['login_time'] : 0;
        if ($loginTime < $changedTime) {
            session_unset();
            return false;
        }
    }

    // Keep the session's cached copy in sync with the DB, so any
    // other code that reads $_SESSION['user_role'] directly still
    // sees the current, correct value.
    $_SESSION['user_role'] = $row['role'];

    return $row['role'] === 'admin';
}


// ============================================================
// PUBLIC — Require authenticated admin session (JSON API)
// Use at the top of any /api/*.php endpoint that must only be
// reachable by an authenticated admin. Responds 401 JSON and
// exits if the check fails.
// ============================================================
function requireAuth(): void {

    if (!_isAdminSession()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized. Please sign in.',
        ]);
        exit;
    }

}

// ============================================================
// PUBLIC — Require authenticated admin session (HTML page)
// Use at the very top of any server-rendered admin page
// (e.g. the private admin route's index.php), BEFORE any HTML
// is output.
// Unlike requireAuth() above (which is for JSON API endpoints
// and returns a 401 JSON body), this redirects the browser to
// the login page — the correct behavior for a page, not an API.
// ============================================================
function requireAdminPage(string $redirectTo = 'login.php'): void {

    if (!_isAdminSession()) {
        // Never cache a negative auth result anywhere in the chain
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: ' . $redirectTo);
        exit;
    }

}

// ============================================================
// PUBLIC — Non-blocking check (does NOT redirect/exit)
// Use on the login page itself to decide whether to bounce an
// already-authenticated admin straight to the dashboard.
// ============================================================
function isAdminLoggedIn(): bool {
    return _isAdminSession();
}

// ============================================================
// INTERNAL — is the current session a valid authenticated user?
// Matches both regular users ('user') and admins ('admin').
// ============================================================
function _isUserSession(): bool {

    _startSecureSession();

    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['user_email']) ||
        !is_int($_SESSION['user_id']) ||
        $_SESSION['user_id'] <= 0
    ) {
        return false;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, role, status, password_changed_at, email_verified_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[guard] DB error during user session check: ' . $e->getMessage());
        return false;
    }

    if (!$row) {
        return false;
    }

    // Invalidate if account is suspended or banned
    if (isset($row['status']) && $row['status'] !== 'active') {
        session_unset();
        return false;
    }

    // Invalidate session if password changed after session was created
    if (!empty($row['password_changed_at'])) {
        $changedTime = strtotime((string)$row['password_changed_at']);
        $loginTime   = isset($_SESSION['login_time']) ? (int)$_SESSION['login_time'] : 0;
        if ($loginTime < $changedTime) {
            session_unset();
            return false;
        }
    }

    $_SESSION['user_role']         = $row['role'];
    $_SESSION['email_verified_at'] = $row['email_verified_at'];
    if (!empty($row['name'])) {
        $_SESSION['user_name'] = $row['name'];
    }

    return true;
}


// ============================================================
// PUBLIC — Check if any user (regular or admin) is logged in
// ============================================================
function isUserLoggedIn(): bool {
    return _isUserSession();
}

// ============================================================
// PUBLIC — Return authenticated user array or null
// Reads email_verified_at directly from the database on every request
// ============================================================
function currentUser(): ?array {
    if (!_isUserSession()) {
        return null;
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, role, status, email_verified_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'id'                => (int) $row['id'],
                'name'              => $row['name'] ?? '',
                'email'             => $row['email'] ?? '',
                'role'              => $row['role'] ?? 'user',
                'status'            => $row['status'] ?? 'active',
                'email_verified_at' => $row['email_verified_at'],
                'is_verified'       => !empty($row['email_verified_at']),
            ];
        }
    } catch (Throwable $e) {
        error_log('[guard] currentUser DB query error: ' . $e->getMessage());
    }

    return [
        'id'                => (int) $_SESSION['user_id'],
        'name'              => $_SESSION['user_name'] ?? '',
        'email'             => $_SESSION['user_email'] ?? '',
        'role'              => $_SESSION['user_role'] ?? 'user',
        'status'            => 'active',
        'email_verified_at' => null,
        'is_verified'       => false,
    ];
}

// ============================================================
// PUBLIC — Require authenticated user session (JSON API)
// Returns 401 JSON and exits if not signed in.
// ============================================================
function requireUserAuth(): void {
    if (!_isUserSession()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Please sign in to continue.',
        ]);
        exit;
    }
}

// ============================================================
// PUBLIC — Require authenticated user session (HTML page)
// Redirects to login page if unauthenticated.
// ============================================================
function requireUserPage(string $redirectTo = '/login.php'): void {
    if (!_isUserSession()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Location: ' . $redirectTo);
        exit;
    }
}

// ============================================================
// PUBLIC — Multi-byte-safe string length, with a fallback if the
// mbstring extension isn't installed (not guaranteed on every
// shared-hosting PHP build). Falls back to byte-length, which is
// stricter than character-length for UTF-8/Arabic text but never
// silently fatals the request the way an undefined function would.
// ============================================================
function safeStrlen(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

// ============================================================
// PUBLIC — Get the currently authenticated admin's user ID.
// Only meaningful after requireAuth()/requireAdminPage() has
// already confirmed a valid admin session — returns 0 otherwise.
// ============================================================
function currentUserId(): int {
    _startSecureSession();
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id'])
        ? $_SESSION['user_id']
        : 0;
}

// ============================================================
// CSRF PROTECTION
// ============================================================
// A random, per-session token. The dashboard page embeds it in
// the HTML (see index.php); every state-changing fetch() call
// from script.js sends it back in the X-CSRF-Token header.
// requireCSRF() rejects the request if it's missing or wrong.
//
// This is deliberately independent of SameSite cookies — SameSite
// alone is not treated as a complete CSRF defense here.
// ============================================================

// ---- PUBLIC — get (or create) this session's CSRF token ------------------
function getCsrfToken(): string {

    _startSecureSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];

}

// ---- PUBLIC — require a valid CSRF token on this request -----------------
// Call AFTER requireAuth() on every state-changing user-management
// endpoint (create/update/delete/change-role/change-password).
function requireCSRF(): void {

    _startSecureSession();

    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    $expected = $_SESSION['csrf_token'] ?? '';

    if (
        $sent === '' ||
        $expected === '' ||
        !hash_equals($expected, $sent)
    ) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'رمز الحماية (CSRF) غير صالح. أعد تحميل الصفحة والمحاولة مرة أخرى.',
        ]);
        exit;
    }

}

// ============================================================
// AUDIT LOGGING (SEC-AUDIT) — Phase P0
// ============================================================
// Record administrative mutations (content deletion, role updates,
// publishing changes, migrations) with who, what, when, and IP.
// Fails gracefully to error_log if the table doesn't exist yet, so
// audit logging never breaks the primary business transaction.
//
// Central redaction recursively removes keys matching sensitive
// terms (password, token, secret, etc.), truncates strings > 500 chars,
// and bounds JSON to 4 KB while preserving valid JSON.
// ============================================================

/**
 * Recursively sanitizes an associative or indexed array for audit logging:
 * - Removes keys matching sensitive patterns (password, token, secret, etc.)
 * - Truncates strings exceeding $maxStrLen characters.
 */
function _sanitizeAuditArray(array $arr, int $maxStrLen = 500): array {
    $pattern = '/(password|passwd|token|hash|secret|key|cookie|csrf|authorization|body)/i';
    $clean = [];
    foreach ($arr as $k => $v) {
        // Redact key completely if key name matches sensitive pattern
        if (is_string($k) && preg_match($pattern, $k)) {
            continue;
        }
        if (is_array($v)) {
            $clean[$k] = _sanitizeAuditArray($v, $maxStrLen);
        } elseif (is_string($v)) {
            if (mb_strlen($v, 'UTF-8') > $maxStrLen) {
                $clean[$k] = mb_substr($v, 0, $maxStrLen, 'UTF-8') . '...[truncated]';
            } else {
                $clean[$k] = $v;
            }
        } else {
            $clean[$k] = $v;
        }
    }
    return $clean;
}

/**
 * Centrally sanitizes audit details, whether passed as array, JSON string, or plain text:
 * - Strips sensitive credentials/tokens/secrets.
 * - Truncates individual strings over 500 characters.
 * - Caps total JSON output under 4 KB (4096 bytes) while preserving valid JSON.
 */
function _sanitizeAuditDetails($details): ?string {
    if ($details === null) {
        return null;
    }

    $data = null;
    $wasArrayOrJson = false;

    if (is_array($details)) {
        $data = $details;
        $wasArrayOrJson = true;
    } elseif (is_string($details)) {
        $trimmed = trim($details);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = $decoded;
                $wasArrayOrJson = true;
            }
        }
        if (!$wasArrayOrJson) {
            // Plain text string: truncate if over 500 chars, cap at 4096 bytes
            if (mb_strlen($details, 'UTF-8') > 500) {
                $details = mb_substr($details, 0, 500, 'UTF-8') . '...[truncated]';
            }
            if (strlen($details) > 4096) {
                $details = mb_strcut($details, 0, 4000, 'UTF-8');
            }
            return $details;
        }
    } else {
        return (string)$details;
    }

    // Sanitize array data
    $clean = _sanitizeAuditArray($data, 500);
    $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // If over 4 KB (4096 bytes), truncate while maintaining valid JSON
    if (strlen($json) > 4096) {
        $clean = _sanitizeAuditArray($clean, 100);
        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    if (strlen($json) > 4096) {
        $pruned = ['_truncated' => true];
        foreach ($clean as $k => $v) {
            $pruned[$k] = is_array($v) ? '[Array...]' : $v;
            $candidate = json_encode($pruned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($candidate) > 3900) {
                unset($pruned[$k]);
                break;
            }
        }
        $json = json_encode($pruned, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    if (strlen($json) > 4096) {
        $json = json_encode([
            '_truncated' => true,
            '_message'   => 'Details exceeded 4KB limit and were truncated.',
            'preview'    => mb_strcut($json, 0, 3800, 'UTF-8')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    return $json;
}

/**
 * Neutralizes CSV formula injection (CWE-1236) by prefixing dangerous characters with a single quote.
 * Neutralizes cells starting with =, +, -, @, tab (\t), or carriage return (\r).
 */
function _neutralizeCsvFormula(?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    $trimmed = ltrim($value);
    if ($trimmed !== '' && preg_match('/^[\=\+\-\@\t\r]/', $trimmed)) {
        return "'" . $value;
    }
    if (preg_match('/^[\=\+\-\@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

function logAdminAction(
    string $action,
    string $targetType,
    ?string $targetId = null,
    $details = null
): void {
    $adminId = currentUserId();
    if ($adminId <= 0) {
        // Fall back to session user_id if present
        $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    }

    $ip = getClientIp();
    $sanitizedDetails = _sanitizeAuditDetails($details);

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $adminId,
            $action,
            $targetType,
            $targetId,
            $sanitizedDetails,
            $ip
        ]);
    } catch (Throwable $e) {
        error_log('[audit_log] Failed to write audit record: ' . $e->getMessage());
    }
}

/**
 * SEC-007 — Safe trim helper.
 *
 * PHP's trim() fatally errors (TypeError in strict mode) when passed a
 * non-string value such as an array, object, or null. All public endpoints
 * decode JSON bodies with json_decode(..., true) and fields can be any type
 * an attacker chooses. A crafted request sending `{"name": [1,2,3]}` would
 * trigger a TypeError and return HTTP 500 instead of a proper 400 validation
 * error, leaking stack information to the caller.
 *
 * This helper:
 *   - Returns '' (empty string) for null, arrays, objects, or resources.
 *   - Returns trim((string)$value) for int, float, and bool (same as trim
 *     with explicit cast — a cast is always safe for scalar types).
 *   - Returns trim($value) for strings (behavior is identical to trim()).
 *
 * Usage: replace `trim($input['field'] ?? '')` with
 *        `safe_trim($input['field'] ?? null)`  or
 *        `safe_trim($input['field'] ?? '')`.
 *
 * The function is defined here because api/auth/guard.php is the one file
 * every public endpoint (support, reviews, auth, forgot, reset, verify,
 * resend, login) and every admin endpoint already includes.
 */
if (!function_exists('safe_trim')) {
    function safe_trim(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value) || is_resource($value)) {
            return '';
        }
        return trim((string)$value);
    }
}
