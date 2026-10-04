<?php
// ============================================================
// JOURNEY MILESTONES — CREATE / UPDATE API
// POST /api/journey/save.php
// Requires: authenticated admin session + valid CSRF token.
// Payload (JSON): { id?, title, period_label, description,
//                    category, icon, sort_order, status }
// If id is present and > 0, updates that row. Otherwise inserts.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id          = isset($input['id']) ? (int) $input['id'] : 0;
$title       = trim((string) ($input['title'] ?? ''));
$periodLabel = trim((string) ($input['period_label'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$category    = trim((string) ($input['category'] ?? 'milestone'));
$icon        = trim((string) ($input['icon'] ?? 'timeline'));
$sortOrder   = isset($input['sort_order']) ? (int) $input['sort_order'] : 0;
$status      = trim((string) ($input['status'] ?? 'draft'));

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($periodLabel === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Period label is required (e.g. "2023 — 2024").'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($description === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Description is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($status, ['draft', 'published'], true)) {
    $status = 'draft';
}
if ($category === '') {
    $category = 'milestone';
}
if ($icon === '') {
    $icon = 'timeline';
}

// Cap lengths defensively to match column sizes.
$title       = mb_substr($title, 0, 200);
$periodLabel = mb_substr($periodLabel, 0, 80);
$category    = mb_substr($category, 0, 60);
$icon        = mb_substr($icon, 0, 60);

try {
    $pdo = getDB();

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE journey_milestones
                SET title = :title, period_label = :period_label, description = :description,
                    category = :category, icon = :icon, sort_order = :sort_order, status = :status
              WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':title'        => $title,
            ':period_label' => $periodLabel,
            ':description'  => $description,
            ':category'     => $category,
            ':icon'         => $icon,
            ':sort_order'   => $sortOrder,
            ':status'       => $status,
            ':id'           => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            // Either nothing changed, or the row doesn't exist — check which.
            $check = $pdo->prepare("SELECT 1 FROM journey_milestones WHERE id = :id AND deleted_at IS NULL");
            $check->execute([':id' => $id]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Milestone not found.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        logAdminAction('journey.update', 'journey_milestone', (string) $id, json_encode([
            'title'  => $title,
            'status' => $status,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => 'Milestone updated.', 'id' => $id], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO journey_milestones (title, period_label, description, category, icon, sort_order, status)
             VALUES (:title, :period_label, :description, :category, :icon, :sort_order, :status)"
        );
        $stmt->execute([
            ':title'        => $title,
            ':period_label' => $periodLabel,
            ':description'  => $description,
            ':category'     => $category,
            ':icon'         => $icon,
            ':sort_order'   => $sortOrder,
            ':status'       => $status,
        ]);

        $newId = (int) $pdo->lastInsertId();

        logAdminAction('journey.create', 'journey_milestone', (string) $newId, json_encode([
            'title'  => $title,
            'status' => $status,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => 'Milestone created.', 'id' => $newId], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    error_log('[journey/save] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while saving the milestone.'], JSON_UNESCAPED_UNICODE);
}
