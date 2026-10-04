<?php
// ============================================================
// ACHIEVEMENTS — LIST
// GET /api/achievements/list.php
//
// Query parameters:
//   ?status=published|hidden|all
//   ?limit=20
//   ?offset=0
//   ?search=term
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/achievements_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$isAdmin = isAdminLoggedIn();

$requestedStatus = isset($_GET['status']) ? trim($_GET['status']) : 'published';
$limit  = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $pdo = getDB();

    $where = [];
    $params = [];

    // Trashed items (deleted_at set) are never listed here; see trash.php.
    // Probe keeps this public endpoint alive if the migration has not run yet.
    if (achievementsHasDeletedAt($pdo)) {
        $where[] = 'deleted_at IS NULL';
    }

    if ($isAdmin) {
        if ($requestedStatus !== 'all') {
            $where[] = 'status = ?';
            $params[] = $requestedStatus;
        }
    } else {
        $where[] = "status = 'published'";
    }
    
    if ($search !== '') {
        $where[] = '(title LIKE ? OR organization LIKE ?)';
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderBy = 'date_awarded DESC, id DESC';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM achievements $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataSql = "SELECT id, title, slug, organization, date_awarded, category, description, image_url, url, status, created_at, updated_at
                FROM achievements
                $whereClause
                ORDER BY $orderBy
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $achievements = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $achievements,
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
    ]);

} catch (PDOException $e) {
    error_log('[achievements/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
