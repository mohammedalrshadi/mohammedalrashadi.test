<?php
// ============================================================
// WORKSPACE NOTES API
// Handles CRUD operations for Personal Workspace notes
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
// GET: Fetch notes
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $entity_type = isset($_GET['entity_type']) ? $_GET['entity_type'] : '';
    $entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : 0;

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM ws_notes WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$note) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Note not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $note]);
            exit;
        }

        $query = 'SELECT id, title, entity_type, entity_id, created_at, updated_at FROM ws_notes WHERE 1=1';
        $params = [];

        if ($entity_type !== '') {
            $query .= ' AND entity_type = ?';
            $params[] = $entity_type;
            if ($entity_id > 0) {
                $query .= ' AND entity_id = ?';
                $params[] = $entity_id;
            }
        }

        $query .= ' ORDER BY updated_at DESC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $notes]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/notes GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving notes.']);
        exit;
    }
}

// ------------------------------------------------------------
// POST: State-changing note operations
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

    // Create Note
    if ($action === 'create_note') {
        $title = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        $entityType = !empty($input['entity_type']) ? trim($input['entity_type']) : null;
        $entityId = !empty($input['entity_id']) ? (int)$input['entity_id'] : null;

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note title is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_notes (title, content, entity_type, entity_id, updated_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$title, $content, $entityType, $entityId]);
            $newId = $pdo->lastInsertId();

            try {
                logAdminAction('workspace.create_note', 'ws_note', (string)$newId, json_encode(['title' => $title]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Note created successfully.', 'id' => $newId]);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/notes create] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error creating note.']);
            exit;
        }
    }

    // Update Note
    if ($action === 'update_note') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Note ID is required.']);
            exit;
        }

        $title = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        $entityType = !empty($input['entity_type']) ? trim($input['entity_type']) : null;
        $entityId = !empty($input['entity_id']) ? (int)$input['entity_id'] : null;

        if ($title === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Note title is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_notes SET title = ?, content = ?, entity_type = ?, entity_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$title, $content, $entityType, $entityId, $id]);

            try {
                logAdminAction('workspace.update_note', 'ws_note', (string)$id, json_encode(['title' => $title]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Note updated successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/notes update] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating note.']);
            exit;
        }
    }

    // Delete Note
    if ($action === 'delete_note') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Note ID is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM ws_notes WHERE id = ?');
            $stmt->execute([$id]);

            try {
                logAdminAction('workspace.delete_note', 'ws_note', (string)$id, '');
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Note deleted successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/notes delete] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error deleting note.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
