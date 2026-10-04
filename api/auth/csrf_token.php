<?php
// ============================================================
// CSRF — GET TOKEN (public)
// GET /api/auth/csrf_token.php
//
// The public site (index.html) is static HTML, so it can't embed
// a server-rendered CSRF token the way the PHP-rendered admin
// dashboard does. Instead, public forms (like Write a Review)
// fetch a token here once on page load, then send it back in the
// X-CSRF-Token header on submission.
//
// This does NOT require login — getCsrfToken() just needs *a*
// session (anonymous is fine), reusing the exact same CSRF
// mechanism already used for admin operations. No second/parallel
// CSRF system.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

echo json_encode([
    'success' => true,
    'token'   => getCsrfToken(),
]);
