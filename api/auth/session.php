<?php
// ============================================================
// AUTH — CHECK SESSION
// GET /api/auth/session.php
// Returns: {success: true, id, name, email, role} or {success: false}
// Used by admin pages to verify authentication before rendering.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if (_isAdminSession()) {
    echo json_encode([
        'success' => true,
        'id'      => $_SESSION['user_id'],
        'name'    => $_SESSION['user_name']  ?? '',
        'email'   => $_SESSION['user_email'],
        'role'    => $_SESSION['user_role'],
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please sign in.',
    ]);
}

