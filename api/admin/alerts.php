<?php
// ============================================================
// ADMIN ALERTS LIST & COUNTS ENDPOINT
// api/admin/alerts.php
//
// GET /api/admin/alerts.php
// GET /api/admin/alerts.php?count_only=1
// GET /api/admin/alerts.php?since=<id|datetime>&limit=10
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/alerts.php';

// Verify admin authentication using existing guard function
requireAuth();

// Close session write lock immediately to prevent blocking polling / concurrent requests
session_write_close();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = getDB();

    // Opportunistic maintenance (purge read > 60d, stale backup check) throttled to once/day
    runOpportunisticAlertMaintenance($pdo);

    $counts = getAdminAlertCounts($pdo);

    // Cheap count-only polling mode
    if (!empty($_GET['count_only'])) {
        echo json_encode([
            'success' => true,
            'counts'  => $counts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 10;
    $since = isset($_GET['since']) ? trim((string)$_GET['since']) : null;

    $alerts = getAdminAlertsList($pdo, $limit, $since);

    echo json_encode([
        'success' => true,
        'counts'  => $counts,
        'alerts'  => $alerts,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log('[api/admin/alerts] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load alerts.',
    ]);
}

