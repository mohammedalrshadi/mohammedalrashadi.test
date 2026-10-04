<?php
// ============================================================
// LAB EXPERIMENTS — GET SINGLE API
// GET /api/labs/get.php?id=LAB-001
// Returns JSON object for the requested lab experiment.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Experiment ID is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dataFile = dirname(__DIR__) . '/data/lab_experiments.json';

if (!file_exists($dataFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No experiments found.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents($dataFile);
$experiments = json_decode($raw, true) ?: [];

$found = null;
foreach ($experiments as $exp) {
    if (isset($exp['id']) && strcasecmp($exp['id'], $id) === 0) {
        $found = $exp;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Experiment not found.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success'    => true,
    'experiment' => $found,
], JSON_UNESCAPED_UNICODE);

