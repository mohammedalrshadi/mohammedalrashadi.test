<?php
// ============================================================
// JOURNEY MILESTONES — DELETE (soft) API
// POST /api/journey/delete.php
// Requires: authenticated admin session + valid CSRF token.
// Payload: { "id": 123 }
// Sets deleted_at rather than removing the row, matching the
// soft-delete convention already used for posts.
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

$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid milestone id is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "UPDATE journey_milestones SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL"
    );
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Milestone not found or already deleted.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    logAdminAction('journey.trash', 'journey_milestone', (string) $id, json_encode([
        'action' => 'soft_delete',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'message' => 'Milestone deleted.'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[journey/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while deleting the milestone.'], JSON_UNESCAPED_UNICODE);
}
