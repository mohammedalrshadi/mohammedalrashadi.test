<?php
// ============================================================
// ACHIEVEMENTS — LIST TRASH
// GET /api/achievements/trash.php?limit=20&offset=0&search=
// Requires: authenticated admin session
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

requireAuth();

$limit  = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

try {
    $pdo = getDB();

    // Before the migration there can be no trash: answer with an empty list.
    if (!achievementsHasDeletedAt($pdo)) {
        echo json_encode(['success' => true, 'data' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset]);
        exit;
    }

    $where  = ['deleted_at IS NOT NULL'];
    $params = [];
    if ($search !== '') {
        $where[]  = '(title LIKE ? OR organization LIKE ?)';
        $like     = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM achievements $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $dataStmt = $pdo->prepare(
        "SELECT id, title, slug, organization, date_awarded, image_url, status, deleted_at
           FROM achievements $whereClause
          ORDER BY deleted_at DESC, id DESC
          LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
    );
    $dataStmt->execute($params);

    echo json_encode([
        'success' => true,
        'data'    => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
    ]);

} catch (PDOException $e) {
    error_log('[achievements/trash] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
