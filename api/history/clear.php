<?php
// ============================================================
// READING HISTORY — CLEAR
// POST /api/history/clear.php
// Requires: authenticated user + CSRF token
// Deletes current user's reading history rows.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireUserAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = currentUserId();

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("DELETE FROM reading_history WHERE user_id = ?");
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Reading history cleared successfully.'
    ]);

} catch (Exception $e) {
    error_log('[history/clear] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to clear reading history.']);
}

