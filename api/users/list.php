<?php
// ============================================================
// USERS — LIST
// GET /api/users/list.php
// Requires: authenticated admin session
// Supports optional ?role=admin|user (defaults to all)
// Returns accounts with separate subqueries for bookmarks/likes/downloads counts
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();

    $roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
    if (!in_array($roleFilter, ['admin', 'user'], true)) {
        $roleFilter = '';
    }

    $sql = "
        SELECT 
            u.id, 
            u.name, 
            u.email, 
            u.role, 
            u.status, 
            u.email_verified_at,
            u.last_login_at, 
            u.created_at, 
            u.updated_at,
            (SELECT COUNT(*) FROM bookmarks b WHERE b.user_id = u.id) AS bookmarks_count,
            (SELECT COUNT(*) FROM likes l WHERE l.user_id = u.id) AS likes_count,
            (SELECT COUNT(*) FROM user_downloads d WHERE d.user_id = u.id) AS downloads_count
        FROM users u
    ";

    $params = [];
    if ($roleFilter !== '') {
        $sql .= " WHERE u.role = ?";
        $params[] = $roleFilter;
    }

    $sql .= " ORDER BY u.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $users,
        'current_admin_id' => currentUserId(),
    ]);

} catch (PDOException $e) {
    error_log('[users/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error loading users.']);
}
