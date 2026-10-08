<?php
// ============================================================
// WORKSPACE PROJECTS API
// Handles CRUD operations for Personal Workspace projects
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
// GET: Fetch projects
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM ws_projects WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Project not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $project]);
            exit;
        }

        $query = 'SELECT * FROM ws_projects';
        $params = [];

        if ($status !== '') {
            $query .= ' WHERE status = ?';
            $params[] = $status;
        }

        $query .= ' ORDER BY CASE 
                        WHEN status = "active" THEN 1
                        WHEN status = "planned" THEN 2
                        WHEN status = "paused" THEN 3
                        ELSE 4 END, updated_at DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $projects]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/projects GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving projects.']);
        exit;
    }
}

// ------------------------------------------------------------
// POST: State-changing project operations
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

    // Create Project
    if ($action === 'create_project') {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $status = trim((string)($input['status'] ?? 'planned'));
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $targetDate = !empty($input['target_date']) ? $input['target_date'] : null;
        $repoUrl = trim((string)($input['repo_url'] ?? ''));
        $demoUrl = trim((string)($input['demo_url'] ?? ''));
        $technologies = trim((string)($input['technologies'] ?? ''));

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Project name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_projects (name, description, status, start_date, target_date, repo_url, demo_url, technologies) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description, $status, $startDate, $targetDate, $repoUrl, $demoUrl, $technologies]);
            $newId = $pdo->lastInsertId();

            try {
                logAdminAction('workspace.create_project', 'ws_project', (string)$newId, json_encode(['name' => $name]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Project created successfully.', 'id' => $newId]);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/projects create] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error creating project.']);
            exit;
        }
    }

    // Update Project
    if ($action === 'update_project') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Project ID is required.']);
            exit;
        }

        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $status = trim((string)($input['status'] ?? 'planned'));
        $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
        $targetDate = !empty($input['target_date']) ? $input['target_date'] : null;
        $repoUrl = trim((string)($input['repo_url'] ?? ''));
        $demoUrl = trim((string)($input['demo_url'] ?? ''));
        $technologies = trim((string)($input['technologies'] ?? ''));

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Project name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_projects SET name = ?, description = ?, status = ?, start_date = ?, target_date = ?, repo_url = ?, demo_url = ?, technologies = ? WHERE id = ?');
            $stmt->execute([$name, $description, $status, $startDate, $targetDate, $repoUrl, $demoUrl, $technologies, $id]);

            try {
                logAdminAction('workspace.update_project', 'ws_project', (string)$id, json_encode(['name' => $name]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Project updated successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/projects update] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating project.']);
            exit;
        }
    }

    // Delete Project
    if ($action === 'delete_project') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Project ID is required.']);
            exit;
        }

        try {
            // Delete associated tasks first (polymorphic relationship cleanup)
            $cleanupStmt = $pdo->prepare("DELETE FROM ws_tasks WHERE entity_type = 'project' AND entity_id = ?");
            $cleanupStmt->execute([$id]);

            $stmt = $pdo->prepare('DELETE FROM ws_projects WHERE id = ?');
            $stmt->execute([$id]);

            try {
                logAdminAction('workspace.delete_project', 'ws_project', (string)$id, '');
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Project deleted successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/projects delete] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error deleting project.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
