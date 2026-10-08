<?php
// ============================================================
// WORKSPACE EVENTS API
// Handles CRUD operations for Personal Workspace calendar events
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
// GET: Fetch events
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $start = isset($_GET['start']) ? $_GET['start'] : ''; // optional range
    $end = isset($_GET['end']) ? $_GET['end'] : '';

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM ws_events WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Event not found.']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $event]);
            exit;
        }

        $query = 'SELECT * FROM ws_events WHERE 1=1';
        $params = [];

        if ($start !== '') {
            $query .= ' AND event_date >= ?';
            $params[] = $start;
        }
        if ($end !== '') {
            $query .= ' AND event_date <= ?';
            $params[] = $end;
        }

        $query .= ' ORDER BY event_date ASC, event_time ASC';

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $events]);
        exit;
    } catch (PDOException $e) {
        error_log('[workspace/events GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error retrieving events.']);
        exit;
    }
}

// ------------------------------------------------------------
// POST: State-changing event operations
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

    // Create Event
    if ($action === 'create_event') {
        $title = trim((string)($input['title'] ?? ''));
        $eventDate = !empty($input['event_date']) ? $input['event_date'] : null;
        $eventTime = !empty($input['event_time']) ? $input['event_time'] : null;
        $location = trim((string)($input['location'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $entityType = !empty($input['entity_type']) ? trim($input['entity_type']) : null;
        $entityId = !empty($input['entity_id']) ? (int)$input['entity_id'] : null;

        if ($title === '' || !$eventDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Event title and date are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO ws_events (title, event_date, event_time, location, description, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $eventDate, $eventTime, $location, $description, $entityType, $entityId]);
            $newId = $pdo->lastInsertId();

            try {
                logAdminAction('workspace.create_event', 'ws_event', (string)$newId, json_encode(['title' => $title]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Event created successfully.', 'id' => $newId]);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/events create] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error creating event.']);
            exit;
        }
    }

    // Update Event
    if ($action === 'update_event') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Event ID is required.']);
            exit;
        }

        $title = trim((string)($input['title'] ?? ''));
        $eventDate = !empty($input['event_date']) ? $input['event_date'] : null;
        $eventTime = !empty($input['event_time']) ? $input['event_time'] : null;
        $location = trim((string)($input['location'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $entityType = !empty($input['entity_type']) ? trim($input['entity_type']) : null;
        $entityId = !empty($input['entity_id']) ? (int)$input['entity_id'] : null;

        if ($title === '' || !$eventDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Event title and date are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE ws_events SET title = ?, event_date = ?, event_time = ?, location = ?, description = ?, entity_type = ?, entity_id = ? WHERE id = ?');
            $stmt->execute([$title, $eventDate, $eventTime, $location, $description, $entityType, $entityId, $id]);

            try {
                logAdminAction('workspace.update_event', 'ws_event', (string)$id, json_encode(['title' => $title]));
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Event updated successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/events update] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error updating event.']);
            exit;
        }
    }

    // Delete Event
    if ($action === 'delete_event') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid Event ID is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM ws_events WHERE id = ?');
            $stmt->execute([$id]);

            try {
                logAdminAction('workspace.delete_event', 'ws_event', (string)$id, '');
            } catch (Throwable $e) {}

            echo json_encode(['success' => true, 'message' => 'Event deleted successfully.']);
            exit;
        } catch (PDOException $e) {
            error_log('[workspace/events delete] Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error deleting event.']);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
