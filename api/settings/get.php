<?php
// ============================================================
// SETTINGS — GET ALL SETTINGS [IMP-034]
// GET /api/settings/get.php
// Requires: authenticated admin session
// Query params:
//   group (optional): 'profile' | 'website' | 'branding' | 'seo' | 'showcase'
// Returns: JSON object with settings grouped by category
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';

header('Content-Type: application/json; charset=utf-8');

// Enforce authenticated admin session
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $requestedGroup = isset($_GET['group']) ? strtolower(trim((string)$_GET['group'])) : null;
    $allGrouped = getAllGroupedSettings(false); // false = include all admin-managed fields

    if ($requestedGroup && $requestedGroup !== 'all') {
        if (!isset($allGrouped[$requestedGroup])) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => "Settings group '{$requestedGroup}' not found.",
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'group'   => $requestedGroup,
            'data'    => $allGrouped[$requestedGroup],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data'    => $allGrouped,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[api/settings/get] Error fetching settings: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error loading settings.',
    ], JSON_UNESCAPED_UNICODE);
}

