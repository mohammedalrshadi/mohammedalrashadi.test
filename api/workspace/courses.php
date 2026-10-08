<?php
// ============================================================
// WORKSPACE COURSES API
// Handles CRUD operations for Personal Workspace courses
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

// ------------------------------------------------------------
// GET: Fetch courses
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM ws_courses WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Course not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $course]);
            exit;
        }

        $query = 'SELECT * FROM ws_courses';
        $params = [];

        if ($status !== '') {
            $query .= ' WHERE status = ?';
            $params[] = $status;
        }

        $query .= ' ORDER BY CASE 
                        WHEN status = "in_progress" THEN 1
                        WHEN status = "planned" THEN 2
                        WHEN status = "completed" THEN 3
                        ELSE 4 END, start_date DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $courses]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/courses GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving courses.']);
        exit;
    }
}

// ------------------------------------------------------------
// POST: State-changing course operations
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $body = file_get_contents('php://input');
    $input = json_decode($body, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
        exit;
    }

    $action = isset($input['action']) ? trim((string)$input['action']) : '';

    // Create Course
    if ($action === 'create_course') {
        $code = trim((string)($input['code'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $semester = trim((string)($input['semester'] ?? ''));
        $instructor = trim((string)($input['instructor'] ?? ''));
        $status = trim((string)($input['status'] ?? 'planned'));
        $progress = (int)($input['progress'] ?? 0);
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $endDate = !empty($input['end_date']) ? $input['end_date'] : null;

        if ($name === '' || $code === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course code and name are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_courses (code, name, semester, instructor, status, progress, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$code, $name, $semester, $instructor, $status, $progress, $startDate, $endDate]);
            $newId = $pdo->lastInsertId();

            try {
                logAdminAction('workspace.create_course', 'ws_course', (string)$newId, json_encode(['code' => $code, 'name' => $name]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Course created successfully.', 'id' => $newId]);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/courses create] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error creating course.']);
            exit;
        }
    }

    // Update Course
    if ($action === 'update_course') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Course ID is required.']);
            exit;
        }

        $code = trim((string)($input['code'] ?? ''));
        $name = trim((string)($input['name'] ?? ''));
        $semester = trim((string)($input['semester'] ?? ''));
        $instructor = trim((string)($input['instructor'] ?? ''));
        $status = trim((string)($input['status'] ?? 'planned'));
        $progress = (int)($input['progress'] ?? 0);
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $endDate = !empty($input['end_date']) ? $input['end_date'] : null;

        if ($name === '' || $code === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Course code and name are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_courses SET code = ?, name = ?, semester = ?, instructor = ?, status = ?, progress = ?, start_date = ?, end_date = ? WHERE id = ?');
            $stmt->execute([$code, $name, $semester, $instructor, $status, $progress, $startDate, $endDate, $id]);

            try {
                logAdminAction('workspace.update_course', 'ws_course', (string)$id, json_encode(['code' => $code, 'name' => $name]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Course updated successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/courses update] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating course.']);
            exit;
        }
    }

    // Delete Course
    if ($action === 'delete_course') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Course ID is required.']);
            exit;
        }

        try {
            // Delete associated tasks first (polymorphic relationship cleanup)
            $cleanupStmt = $pdo->prepare("DELETE FROM ws_tasks WHERE entity_type = 'course' AND entity_id = ?");
            $cleanupStmt->execute([$id]);

            $stmt = $pdo->prepare('DELETE FROM ws_courses WHERE id = ?');
            $stmt->execute([$id]);

            try {
                logAdminAction('workspace.delete_course', 'ws_course', (string)$id, '');
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Course deleted successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/courses delete] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error deleting course.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
