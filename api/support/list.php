<?php
// ============================================================
// SUPPORT — LIST MESSAGES (ADMIN)
// GET /api/support/list.php
// Requires: authenticated admin session
// Query params:
//   status   : all|new|in_progress|resolved|archived (default: all)
//   category : general|technical|product|account|feedback (optional)
//   q        : search keyword (optional)
//   page     : integer >= 1 (default: 1)
//   limit    : integer between 1 and 100 (default: 50)
// ============================================================

declare(strict_types=1);

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

requireAuth();


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();

    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'support_messages'")->fetch();
    if (!$tableCheck) {
        echo json_encode([
            'success'     => true,
            'messages'    => [],
            'counts'      => ['all' => 0, 'new' => 0, 'in_progress' => 0, 'resolved' => 0, 'archived' => 0],
            'total'       => 0,
            'page'        => 1,
            'total_pages' => 0,
        ]);
        exit;
    }

    $status   = strtolower(trim($_GET['status'] ?? 'all'));
    $category = strtolower(trim($_GET['category'] ?? ''));
    $q        = trim($_GET['q'] ?? '');
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $limit    = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $offset   = ($page - 1) * $limit;

    $validStatuses = ['new', 'in_progress', 'resolved', 'archived'];
    $validCategories = ['general', 'technical', 'product', 'account', 'feedback'];

    // 1. Overall counts by status for tabs
    $countsStmt = $pdo->query("
        SELECT status, COUNT(*) as cnt
        FROM support_messages
        GROUP BY status
    ");
    $rawCounts = $countsStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $counts = [
        'all'         => (int)array_sum($rawCounts),
        'new'         => (int)($rawCounts['new'] ?? 0),
        'in_progress' => (int)($rawCounts['in_progress'] ?? 0),
        'resolved'    => (int)($rawCounts['resolved'] ?? 0),
        'archived'    => (int)($rawCounts['archived'] ?? 0),
    ];

    // 2. Build filtered query
    $where = [];
    $params = [];

    if ($status !== 'all' && in_array($status, $validStatuses, true)) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    if ($category !== '' && in_array($category, $validCategories, true)) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    if ($q !== '') {
        $where[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $searchTerm = '%' . $q . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Total count matching filter
    $countSql = "SELECT COUNT(*) FROM support_messages {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalFiltered = (int)$countStmt->fetchColumn();

    $totalPages = $totalFiltered > 0 ? (int)ceil($totalFiltered / $limit) : 1;

    // Fetch messages
    $selectSql = "
        SELECT id, user_id, name, email, category, subject, message, status, ip_address, created_at, updated_at
        FROM support_messages
        {$whereSql}
        ORDER BY created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute($params);
    $messages = $selectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success'     => true,
        'messages'    => $messages,
        'counts'      => $counts,
        'total'       => $totalFiltered,
        'page'        => $page,
        'total_pages' => $totalPages,
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    error_log('[api/support/list] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}

