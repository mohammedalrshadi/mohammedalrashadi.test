<?php
// ============================================================
// WORKSPACE TASKS API
// Handles CRUD operations for Personal Workspace tasks
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
// GET: Fetch tasks
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $entity_type = isset($_GET['entity_type']) ? $_GET['entity_type'] : '';
    $entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM ws_tasks WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $task]);
            exit;
        }

        $query = 'SELECT * FROM ws_tasks WHERE 1=1';
        $params = [];

        if ($status !== '') {
            $query .= ' AND status = ?';
            $params[] = $status;
        }
        if ($entity_type !== '') {
            $query .= ' AND entity_type = ?';
            $params[] = $entity_type;
            if ($entity_id > 0) {
                $query .= ' AND entity_id = ?';
                $params[] = $entity_id;
            }
        }

        // Order by pending first, then by priority, then by due date
        $query .= ' ORDER BY CASE 
                        WHEN status = "pending" THEN 1
                        WHEN status = "in_progress" THEN 2
                        WHEN status = "deferred" THEN 3
                        ELSE 4 END,
                    CASE 
                        WHEN priority = "urgent" THEN 1
                        WHEN priority = "high" THEN 2
                        WHEN priority = "medium" THEN 3
                        ELSE 4 END,
                    due_date ASC, created_at DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $tasks]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/tasks GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving tasks.']);
        exit;
    }
}

// ------------------------------------------------------------
// POST: State-changing task operations
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

    // Create Task
    if ($action === 'create_task') {
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $status = trim((string)($input['status'] ?? 'pending'));
        $priority = trim((string)($input['priority'] ?? 'medium'));
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $entityType = !empty($input['entity_type']) ? trim($input['entity_type']) : null;
        $entityId = !empty($input['entity_id']) ? (int)$input['entity_id'] : null;

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Task title is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_tasks (title, description, status, priority, due_date, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $status, $priority, $dueDate, $entityType, $entityId]);
            $newId = $pdo->lastInsertId();

            // if status is completed, update completed_at
            if ($status === 'completed') {
                $pdo->prepare('UPDATE ws_tasks SET completed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$newId]);
            }

            try {
                logAdminAction('workspace.create_task', 'ws_task', (string)$newId, json_encode(['title' => $title]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Task created successfully.', 'id' => $newId]);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/tasks create] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error creating task.']);
            exit;
        }
    }

    // Update Task
    if ($action === 'update_task') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Task ID is required.']);
            exit;
        }

        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $status = trim((string)($input['status'] ?? 'pending'));
        $priority = trim((string)($input['priority'] ?? 'medium'));
        $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
        $entityType = !empty($input['entity_type']) ? trim($input['entity_type']) : null;
        $entityId = !empty($input['entity_id']) ? (int)$input['entity_id'] : null;

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Task title is required.']);
            exit;
        }

        try {
            // Check old status to see if it just changed to completed
            $oldStmt = $pdo->prepare('SELECT status FROM ws_tasks WHERE id = ?');
            $oldStmt->execute([$id]);
            $oldTask = $oldStmt->fetch();

            $stmt = $pdo->prepare('UPDATE ws_tasks SET title = ?, description = ?, status = ?, priority = ?, due_date = ?, entity_type = ?, entity_id = ? WHERE id = ?');
            $stmt->execute([$title, $description, $status, $priority, $dueDate, $entityType, $entityId, $id]);

            if ($oldTask && $oldTask['status'] !== 'completed' && $status === 'completed') {
                $pdo->prepare('UPDATE ws_tasks SET completed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
            } else if ($oldTask && $oldTask['status'] === 'completed' && $status !== 'completed') {
                $pdo->prepare('UPDATE ws_tasks SET completed_at = NULL WHERE id = ?')->execute([$id]);
            }

            try {
                logAdminAction('workspace.update_task', 'ws_task', (string)$id, json_encode(['title' => $title]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Task updated successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/tasks update] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating task.']);
            exit;
        }
    }
    
    // Toggle Status Task
    if ($action === 'toggle_status') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Task ID is required.']);
            exit;
        }

        $status = trim((string)($input['status'] ?? 'completed'));

        try {
            $stmt = $pdo->prepare('UPDATE ws_tasks SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);

            if ($status === 'completed') {
                $pdo->prepare('UPDATE ws_tasks SET completed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
            } else {
                $pdo->prepare('UPDATE ws_tasks SET completed_at = NULL WHERE id = ?')->execute([$id]);
            }

            echo json_encode(['success' => true, 'message' => 'Task status updated.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/tasks toggle] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating task status.']);
            exit;
        }
    }

    // Delete Task
    if ($action === 'delete_task') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Task ID is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM ws_tasks WHERE id = ?');
            $stmt->execute([$id]);

            try {
                logAdminAction('workspace.delete_task', 'ws_task', (string)$id, '');
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/tasks delete] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error deleting task.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
