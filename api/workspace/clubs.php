<?php
// ============================================================
// WORKSPACE CLUBS API
// Handles CRUD operations for Personal Workspace clubs/organizations
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
            $stmt = $pdo->prepare('SELECT * FROM ws_clubs WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $club = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$club) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Club not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $club]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT * FROM ws_clubs ORDER BY 
            CASE 
                WHEN status = "committee" THEN 1
                WHEN status = "member" THEN 2
                WHEN status = "volunteer" THEN 3
                WHEN status = "following" THEN 4
                WHEN status = "interested" THEN 5
                ELSE 6 
            END, created_at DESC');
        $stmt->execute();
        $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $clubs]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/clubs GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving clubs.']);
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

    if ($action === 'create_club') {
        $name = trim((string)($input['name'] ?? ''));
        $organization = trim((string)($input['organization'] ?? ''));
        $role = trim((string)($input['role'] ?? ''));
        $status = trim((string)($input['status'] ?? 'member'));
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
        $description = trim((string)($input['description'] ?? ''));

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_clubs (name, organization, role, status, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $organization, $role, $status, $startDate, $endDate, $description]);
            echo json_encode(['success' => true, 'message' => 'Club added successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    if ($action === 'update_club') {
        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $organization = trim((string)($input['organization'] ?? ''));
        $role = trim((string)($input['role'] ?? ''));
        $status = trim((string)($input['status'] ?? 'member'));
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
        $description = trim((string)($input['description'] ?? ''));

        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'ID and Name are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_clubs SET name=?, organization=?, role=?, status=?, start_date=?, end_date=?, description=? WHERE id=?');
            $stmt->execute([$name, $organization, $role, $status, $startDate, $endDate, $description, $id]);
            echo json_encode(['success' => true, 'message' => 'Club updated successfully.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    if ($action === 'delete_club') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        try {
            $pdo->prepare('DELETE FROM ws_clubs WHERE id=?')->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Club deleted.']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}
