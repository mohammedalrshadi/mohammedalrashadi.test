<?php
declare(strict_types=1);

// ============================================================
// PLATFORM ENTITY COUNTS API
// GET /api/stats/counts.php
// Requires: authenticated admin session
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/stats_helper.php';

header('Content-Type: application/json');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();
    $data = getPlatformEntityCounts($pdo);

    echo json_encode([
        'success' => true,
        'data'    => $data,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('[stats/counts] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load platform stats.']);
}

