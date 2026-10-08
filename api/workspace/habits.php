<?php
// ============================================================
// WORKSPACE HABITS API
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
    $stmt = $pdo->prepare('SELECT * FROM ws_habits ORDER BY id DESC');
    $stmt->execute();
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $in = json_decode(file_get_contents('php://input'), true);
    $action = $in['action'] ?? '';

    if ($action === 'create_habit') {
        $stmt = $pdo->prepare('INSERT INTO ws_habits (name, frequency) VALUES (?, ?)');
        $stmt->execute([trim($in['name']??''), trim($in['frequency']??'')]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'update_habit') {
        $stmt = $pdo->prepare('UPDATE ws_habits SET name=?, frequency=? WHERE id=?');
        $stmt->execute([trim($in['name']??''), trim($in['frequency']??''), (int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'delete_habit') {
        $pdo->prepare('DELETE FROM ws_habits WHERE id=?')->execute([(int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
}
