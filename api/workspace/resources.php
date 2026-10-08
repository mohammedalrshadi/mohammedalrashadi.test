<?php
// ============================================================
// WORKSPACE RESOURCES API
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');

requireAuth();

$pdo = getDB();
$adminId = currentUserId();

if ($adminId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM ws_resources WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$res) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Resource not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $res]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM ws_resources ORDER BY created_at DESC');
        $stmt->execute();
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $resources]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/resources GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving resources.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $action = isset($input['action']) ? trim((string)$input['action']) : '';

    if ($action === 'create_resource') {
        $title = trim((string)($input['title'] ?? ''));
        $url = trim((string)($input['url'] ?? ''));
        $type = trim((string)($input['type'] ?? 'link'));
        $status = trim((string)($input['status'] ?? 'saved'));
        $description = trim((string)($input['description'] ?? ''));

        if ($title === '' || $url === '') {
            echo json_encode(['success' => false, 'message' => 'Title and URL are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_resources (title, url, type, status, description) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $url, $type, $status, $description]);
            echo json_encode(['success' => true, 'message' => 'Resource added successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    if ($action === 'update_resource') {
        $id = (int)($input['id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $url = trim((string)($input['url'] ?? ''));
        $type = trim((string)($input['type'] ?? 'link'));
        $status = trim((string)($input['status'] ?? 'saved'));
        $description = trim((string)($input['description'] ?? ''));

        if ($id <= 0 || $title === '' || $url === '') {
            echo json_encode(['success' => false, 'message' => 'ID, Title, and URL are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_resources SET title=?, url=?, type=?, status=?, description=? WHERE id=?');
            $stmt->execute([$title, $url, $type, $status, $description, $id]);
            echo json_encode(['success' => true, 'message' => 'Resource updated successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    if ($action === 'delete_resource') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        try {
            $pdo->prepare('DELETE FROM ws_resources WHERE id=?')->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Resource deleted.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
