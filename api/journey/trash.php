<?php
declare(strict_types=1);

// ============================================================
// JOURNEY MILESTONES — TRASH LIST
// GET /api/journey/trash.php
// Requires: authenticated admin session
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, title, period_label, description, category, icon, sort_order, status, created_at, updated_at, deleted_at
         FROM journey_milestones
         WHERE deleted_at IS NOT NULL
         ORDER BY deleted_at DESC'
    );
    $stmt->execute();
    $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($milestones),
        'data'    => $milestones,
    ], JSON_THROW_ON_ERROR);

} catch (PDOException $e) {
    error_log('[journey/trash] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query failed.']);
}

