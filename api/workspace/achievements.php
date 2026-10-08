<?php
// ============================================================
// WORKSPACE ACHIEVEMENTS API
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
    $stmt = $pdo->prepare('SELECT * FROM ws_achievements ORDER BY date_earned DESC, id DESC');
    $stmt->execute();
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $in = json_decode(file_get_contents('php://input'), true);
    $action = $in['action'] ?? '';

    if ($action === 'create_achievement') {
        $stmt = $pdo->prepare('INSERT INTO ws_achievements (title, date_earned, description) VALUES (?, ?, ?)');
        $stmt->execute([trim($in['title']??''), $in['date_earned']?:null, trim($in['description']??'')]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'update_achievement') {
        $stmt = $pdo->prepare('UPDATE ws_achievements SET title=?, date_earned=?, description=? WHERE id=?');
        $stmt->execute([trim($in['title']??''), $in['date_earned']?:null, trim($in['description']??''), (int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'delete_achievement') {
        $pdo->prepare('DELETE FROM ws_achievements WHERE id=?')->execute([(int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
}
