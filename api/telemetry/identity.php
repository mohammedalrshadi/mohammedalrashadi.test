<?php
// ============================================================
// SHARED TELEMETRY IDENTITY HELPER [IMP-033]
// api/telemetry/identity.php
//
// Governs visitor identification, cookie issuance, and bot/privacy guards.
// Strict Fail-Closed Policy: If TELEMETRY_SECRET is missing or invalid,
// this file halts execution with HTTP 500. Zero fallback keys.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config.php';

// ------------------------------------------------------------
// 1. Mandatory Telemetry Secret Verification (Fail-Closed)
// ------------------------------------------------------------
if (!defined('TELEMETRY_SECRET') || empty(TELEMETRY_SECRET) || strlen(TELEMETRY_SECRET) < 32) {
    http_response_code(500);
    error_log('[TELEMETRY] CRITICAL: TELEMETRY_SECRET is missing, empty, or shorter than 32 characters in api/config.local.php.');
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Telemetry configuration error.',
    ]);
    exit;
}

// ------------------------------------------------------------
// 2. Telemetry Ingestion Guardrails (DNT, GPC, Bots, Method)
// ------------------------------------------------------------
function check_telemetry_guards(): void {
    // Only POST allowed for telemetry ingestion
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    // Honor Do Not Track (DNT) and Global Privacy Control (GPC)
    // Drops immediately with 204 No Content; zero cookies set, zero database writes
    if (
        (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1') ||
        (isset($_SERVER['HTTP_SEC_GPC']) && $_SERVER['HTTP_SEC_GPC'] === '1')
    ) {
        http_response_code(204);
        exit;
    }

    // Practical Bot & Automated Crawler Filtering
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $botPattern = '/(bot|crawler|spider|slurp|curl|wget|python|urllib|headless|facebookexternalhit|whatsapp|googlebot|bingbot|yandex|baidu)/i';
    if (empty($ua) || preg_match($botPattern, $ua)) {
        http_response_code(204);
        exit;
    }
}

// ------------------------------------------------------------
// 3. Visitor Identity Resolver
//
// Mode 'create_if_missing': used exclusively by visit.php.
// Mode 'read_only': used by view.php to avoid race-condition cookie clobbering.
// ------------------------------------------------------------
function resolve_telemetry_visitor(string $mode = 'read_only'): array {
    $cookieVid = $_COOKIE['ah_vid'] ?? null;

    // Validate incoming cookie format: strictly 32 hexadecimal characters (128-bit)
    if ($cookieVid && is_string($cookieVid) && preg_match('/^[0-9a-f]{32}$/i', $cookieVid)) {
        $rawVid = strtolower(trim($cookieVid));
        $visitorHash = hash_hmac('sha256', $rawVid, TELEMETRY_SECRET);
        return [
            'raw_vid'      => $rawVid,
            'visitor_hash' => $visitorHash,
            'is_new'       => false,
        ];
    }

    // If cookie is absent and mode is 'read_only', DO NOT mint a new cookie
    if ($mode !== 'create_if_missing') {
        return [
            'raw_vid'      => null,
            'visitor_hash' => null,
            'is_new'       => false,
        ];
    }

    // Mode is 'create_if_missing' (visit.php): generate new persistent anonymous identifier
    $newRawVid = bin2hex(random_bytes(16)); // 32 hex chars = 128 bits entropy
    $visitorHash = hash_hmac('sha256', $newRawVid, TELEMETRY_SECRET);

    // Detect HTTPS on Hostinger / reverse proxies
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && $_SERVER['HTTP_X_FORWARDED_SSL']   === 'on')
    );

    // Issue Secure, HttpOnly, SameSite=Lax 1-year first-party cookie
    // HttpOnly prevents JavaScript on the page from reading or modifying the cookie
    setcookie('ah_vid', $newRawVid, [
        'expires'  => time() + 31536000, // 365 days
        'path'     => '/',
        'domain'   => '', // Host-only cookie
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $_COOKIE['ah_vid'] = $newRawVid;

    return [
        'raw_vid'      => $newRawVid,
        'visitor_hash' => $visitorHash,
        'is_new'       => true,
    ];
}
