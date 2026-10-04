<?php
// ============================================================
// USER ACTIVITY — TIMELINE LIST
// GET /api/activity/list.php
// Requires: authenticated user
// Returns: chronological timeline of real actions by the user
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireUserAuth();

$userId = currentUserId();

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT id, activity_type, content_type, content_id, description, created_at 
         FROM user_activities 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 100"
    );
    $stmt->execute([$userId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($activities),
        'data'    => $activities
    ]);

} catch (Exception $e) {
    error_log('[activity/list] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve activity history.']);
}

