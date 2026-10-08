<?php
// ============================================================
// WORKSPACE GOALS API
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');
requireAuth();

$pdo = getDB();
if (currentUserId() <= 0) { http_response_code(401); echo json_encode(['success'=>false]); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM ws_goals ORDER BY CASE WHEN status = "active" THEN 1 WHEN status = "paused" THEN 2 ELSE 3 END, target_date ASC');
    $stmt->execute();
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $in = json_decode(file_get_contents('php://input'), true);
    $action = $in['action'] ?? '';

    if ($action === 'create_goal') {
        $stmt = $pdo->prepare('INSERT INTO ws_goals (title, category, status, target_date, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([trim($in['title']??''), trim($in['category']??''), trim($in['status']??'active'), $in['target_date']?:null, trim($in['description']??'')]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'update_goal') {
        $stmt = $pdo->prepare('UPDATE ws_goals SET title=?, category=?, status=?, target_date=?, description=? WHERE id=?');
        $stmt->execute([trim($in['title']??''), trim($in['category']??''), trim($in['status']??'active'), $in['target_date']?:null, trim($in['description']??''), (int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'delete_goal') {
        $pdo->prepare('DELETE FROM ws_goals WHERE id=?')->execute([(int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
}
