<?php
// ============================================================
// PRODUCTS — SERVE STATIC DEMO
// GET /api/products/serve_demo.php?slug=...&file=...
//
// Securely proxies requested static files for uploaded product demos.
// Isolated routing to prevent any PHP execution of demo content.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$file = isset($_GET['file']) ? trim((string)$_GET['file']) : '';

if ($slug === '' || $file === '') {
    http_response_code(400);
    exit('Bad Request');
}

// 1. Prevent Path Traversal
if (strpos($file, '../') !== false || strpos($file, '..\\') !== false || str_starts_with($file, '/') || preg_match('#^[a-zA-Z]:\\\\#', $file)) {
    http_response_code(403);
    exit('Forbidden');
}

// 2. Fetch Product
$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, live_demo_source, live_demo_path FROM products WHERE slug = ? AND status = \'published\' LIMIT 1');
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product || $product['live_demo_source'] !== 'uploaded' || empty($product['live_demo_path'])) {
    http_response_code(404);
    exit('Demo not found or not configured as uploaded static demo.');
}

// 3. Resolve Physical File
$demosBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/demos';

// Strictly validate live_demo_path:
// Reject any value that contains '..', a backslash, a NUL byte, or looks like an absolute path.
// This is a belt-and-suspenders guard on top of the realpath containment check below.
$rawLiveDemoPath = (string)$product['live_demo_path'];
if (
    $rawLiveDemoPath === ''
    || str_contains($rawLiveDemoPath, '..')
    || str_contains($rawLiveDemoPath, '\\')
    || str_contains($rawLiveDemoPath, "\0")
    || str_starts_with($rawLiveDemoPath, '/')
    || preg_match('#^[a-zA-Z]:\\\\#', $rawLiveDemoPath)
) {
    http_response_code(404);
    exit('Demo not found or not configured properly.');
}
$safeLiveDemoPath = $rawLiveDemoPath;

$physicalDir = $demosBaseDir . '/' . $safeLiveDemoPath;
$requestedPath = $physicalDir . '/' . $file;

if (!file_exists($requestedPath) || !is_file($requestedPath)) {
    http_response_code(404);
    exit('File not found');
}

// Ensure the resolved realpath is strictly inside the product's demo directory
$realRequestedPath = realpath($requestedPath);
$realPhysicalDir = realpath($physicalDir);

if ($realPhysicalDir === false) {
    http_response_code(404);
    exit('Demo directory not found on server.');
}

$realPhysicalDirWithSeparator = rtrim($realPhysicalDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if ($realRequestedPath === false || !str_starts_with($realRequestedPath, $realPhysicalDirWithSeparator)) {
    http_response_code(403);
    exit('Forbidden path');
}

// 4. Send MIME Headers & Serve
$ext = strtolower(pathinfo($realRequestedPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'html' => 'text/html',
    'htm' => 'text/html',
    'css' => 'text/css',
    'js' => 'application/javascript',
    'json' => 'application/json',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'avif' => 'image/avif',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'otf' => 'font/otf',
    'xml' => 'application/xml',
    'txt' => 'text/plain'
];

$mime = $mimeTypes[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);

// Basic caching headers for static content
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN'); // Allow iframing if the site itself needs it, but the user requested opening in new tab.
// SEC-006: sandbox the demo without allow-same-origin so the iframe cannot
// read the parent's cookies or storage. allow-scripts and allow-forms are
// retained so HTML demos that use JS/forms continue to function.
// Note: this means inline <script> in demos runs sandboxed (no DOM cookies,
// no same-origin access). Demos that relied on same-origin access may break.
// That is the intended security posture.
//
// The sandbox is applied to EVERY served file, not only HTML. A directly-opened
// .svg (or an application/xml file carrying an XHTML namespace) is a script-capable
// document; without this it would run inside the site's own origin. Only HTML gets
// allow-scripts/allow-forms — every other type gets a plain `sandbox` (no scripts,
// opaque origin). Sub-resources (<img src=x.svg>, <script src>, <link>) are
// unaffected by a CSP on the sub-resource response itself.
//
// NOTE: the root .htaccess removes its global CSP for this script only (see the
// serve_demo.php block there); otherwise mod_headers `always set` can replace this
// value with the site-wide policy.
if ($ext === 'html' || $ext === 'htm') {
    header('Content-Security-Policy: sandbox allow-scripts allow-forms');
} else {
    header('Content-Security-Policy: sandbox');
}

// Read the file directly to output buffer
// This completely bypasses any PHP execution engine for .php files (if one slipped through validation)
readfile($realRequestedPath);
exit;
