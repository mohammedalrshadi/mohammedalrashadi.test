<?php
// ============================================================
// WORKSPACE READING ITEMS API
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
    $stmt = $pdo->prepare('SELECT * FROM ws_reading_items ORDER BY CASE WHEN status = "reading" THEN 1 WHEN status = "want_to_read" THEN 2 ELSE 3 END, created_at DESC');
    $stmt->execute();
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $in = json_decode(file_get_contents('php://input'), true);
    $action = $in['action'] ?? '';

    if ($action === 'create_reading') {
        $stmt = $pdo->prepare('INSERT INTO ws_reading_items (title, type, status, url) VALUES (?, ?, ?, ?)');
        $stmt->execute([trim($in['title']??''), trim($in['type']??'book'), trim($in['status']??'want_to_read'), trim($in['url']??'')]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'update_reading') {
        $stmt = $pdo->prepare('UPDATE ws_reading_items SET title=?, type=?, status=?, url=? WHERE id=?');
        $stmt->execute([trim($in['title']??''), trim($in['type']??'book'), trim($in['status']??'want_to_read'), trim($in['url']??''), (int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
    if ($action === 'delete_reading') {
        $pdo->prepare('DELETE FROM ws_reading_items WHERE id=?')->execute([(int)$in['id']]);
        echo json_encode(['success'=>true]); exit;
    }
}
