<?php
// ============================================================
// LAB EXPERIMENTS — DELETE API
// POST /api/labs/delete.php
// Requires: authenticated admin session + valid CSRF token
// Payload: JSON or POST form data
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

$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$id = strtoupper(trim($input['id'] ?? ''));

if ($id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Experiment ID is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/helpers/lab_store.php';

$dataFile = dirname(__DIR__) . '/data/lab_experiments.json';

if (!file_exists($dataFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No experiments found.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// DC-005: read-modify-write under one lock, atomic replace.
$store = labStoreMutate($dataFile, function (array $experiments) use ($id): array {
    $filtered = [];
    foreach ($experiments as $exp) {
        if (!isset($exp['id']) || strcasecmp($exp['id'], $id) !== 0) {
            $filtered[] = $exp;
        }
    }
    if (count($filtered) === count($experiments)) {
        return ['abort' => ['code' => 404, 'message' => "Experiment '{$id}' not found."]];
    }
    return ['experiments' => $filtered, 'result' => true];
});

if (!$store['ok']) {
    http_response_code($store['code']);
    echo json_encode(['success' => false, 'message' => $store['code'] === 404 ? $store['message'] : 'Failed to write changes to storage.'], JSON_UNESCAPED_UNICODE);
    exit;
}

logAdminAction('lab.delete', 'lab', $id, json_encode([
    'id' => $id,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success' => true,
    'message' => "Experiment '{$id}' deleted successfully.",
], JSON_UNESCAPED_UNICODE);

