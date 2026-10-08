<?php
// ============================================================
// WORKSPACE SKILLS API
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
            $stmt = $pdo->prepare('SELECT * FROM ws_skills WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $skill = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$skill) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Skill not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $skill]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM ws_skills ORDER BY 
            CASE 
                WHEN status = "learning" THEN 1
                WHEN status = "practicing" THEN 2
                WHEN status = "applied" THEN 3
                WHEN status = "strong" THEN 4
                ELSE 5 
            END, name ASC');
        $stmt->execute();
        $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $skills]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/skills GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving skills.']);
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

    if ($action === 'create_skill') {
        $name = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $status = trim((string)($input['status'] ?? 'learning'));

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_skills (name, category, status) VALUES (?, ?, ?)');
            $stmt->execute([$name, $category, $status]);
            echo json_encode(['success' => true, 'message' => 'Skill added successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    if ($action === 'update_skill') {
        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $status = trim((string)($input['status'] ?? 'learning'));

        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'ID and Name are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_skills SET name=?, category=?, status=? WHERE id=?');
            $stmt->execute([$name, $category, $status, $id]);
            echo json_encode(['success' => true, 'message' => 'Skill updated successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    if ($action === 'delete_skill') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        try {
            $pdo->prepare('DELETE FROM ws_skills WHERE id=?')->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Skill deleted.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
