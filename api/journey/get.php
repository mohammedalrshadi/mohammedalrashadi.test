<?php
// ============================================================
// JOURNEY MILESTONES — GET SINGLE API
// GET /api/journey/get.php?id=123
// Returns single milestone record for admin editing.
// Protected: requires admin authentication.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid milestone ID is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, title, period_label, description, category, icon, sort_order, status, created_at, updated_at
           FROM journey_milestones
          WHERE id = ? AND deleted_at IS NULL
          LIMIT 1"
    );
    $stmt->execute([$id]);
    $milestone = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$milestone) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Milestone not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'   => true,
        'milestone' => $milestone,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[journey/get] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while fetching milestone.'], JSON_UNESCAPED_UNICODE);
}

