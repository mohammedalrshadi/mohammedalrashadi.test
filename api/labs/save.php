<?php
// ============================================================
// LAB EXPERIMENTS — CREATE / UPDATE API
// POST /api/labs/save.php
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
$originalId = strtoupper(trim($input['original_id'] ?? ''));
$title = trim($input['title'] ?? '');
$category = strtolower(trim($input['category'] ?? ''));
$categoryLabel = trim($input['categoryLabel'] ?? '');
$status = strtoupper(trim($input['status'] ?? 'ACTIVE'));
$readTime = trim($input['readTime'] ?? '5 min');
$subsystem = trim($input['subsystem'] ?? '');
$ref = trim($input['ref'] ?? '');
$question = trim($input['question'] ?? '');
$hypothesis = trim($input['hypothesis'] ?? '');
$environment = trim($input['environment'] ?? '');
$method = trim($input['method'] ?? '');
$outcomeTitle = trim($input['outcomeTitle'] ?? 'EMPIRICAL OUTCOME:');
$outcomeHeadline = trim($input['outcomeHeadline'] ?? '');
$outcomeDesc = trim($input['outcomeDesc'] ?? '');
$observations = trim($input['observations'] ?? '');
$conclusion = trim($input['conclusion'] ?? '');
$repro_command = trim($input['repro_command'] ?? '');
$image = trim($input['image'] ?? '');

// Validation
if ($id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Experiment ID is required (e.g., LAB-001).'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Experiment title is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($category === '') {
    $category = 'systems';
}

if ($categoryLabel === '') {
    $categoryLabels = [
        'database'    => 'Database & SQL',
        'concurrency' => 'Concurrency & Runtimes',
        'performance' => 'Performance & Cache',
        'systems'     => 'Systems Architecture',
        'network'     => 'Network Protocols',
        'security'    => 'Security & Cryptography',
    ];
    $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);
}

// Parse tech tags
$tech = [];
if (isset($input['tech'])) {
    if (is_array($input['tech'])) {
        $tech = array_values(array_filter(array_map('trim', $input['tech'])));
    } elseif (is_string($input['tech'])) {
        $parts = explode(',', $input['tech']);
        $tech = array_values(array_filter(array_map('trim', $parts)));
    }
}

// Parse metrics
$metrics = [];
if (isset($input['metrics'])) {
    if (is_string($input['metrics'])) {
        $decoded = json_decode($input['metrics'], true);
        if (is_array($decoded)) {
            $metrics = $decoded;
        }
    } elseif (is_array($input['metrics'])) {
        $metrics = $input['metrics'];
    }
}

// Clean metrics structure
$cleanMetrics = [];
foreach ($metrics as $m) {
    if (!empty($m['param'])) {
        $cleanMetrics[] = [
            'param'    => trim($m['param'] ?? ''),
            'baseline' => trim($m['baseline'] ?? ''),
            'tuned'    => trim($m['tuned'] ?? ''),
            'tradeoff' => trim($m['tradeoff'] ?? ''),
        ];
    }
}

$experiment = [
    'id'              => $id,
    'subsystem'       => $subsystem,
    'ref'             => $ref,
    'category'        => $category,
    'categoryLabel'   => $categoryLabel,
    'status'          => $status,
    'readTime'        => $readTime,
    'title'           => $title,
    'question'        => $question,
    'hypothesis'      => $hypothesis,
    'environment'     => $environment,
    'method'          => $method,
    'outcomeTitle'    => $outcomeTitle,
    'outcomeHeadline' => $outcomeHeadline,
    'outcomeDesc'     => $outcomeDesc,
    'tech'            => $tech,
    'image'           => $image,
    'metrics'         => $cleanMetrics,
    'observations'    => $observations,
    'conclusion'      => $conclusion,
    'repro_command'   => $repro_command,
];

require_once dirname(__DIR__) . '/helpers/lab_store.php';

$dataFile = dirname(__DIR__) . '/data/lab_experiments.json';

// DC-005: lookup, conflict check and write all happen under one lock, on the
// latest file contents, and the file is replaced atomically (temp file + rename).
$store = labStoreMutate($dataFile, function (array $experiments) use ($experiment, $originalId, $id): array {
    $targetLookupId = $originalId !== '' ? $originalId : $id;
    $foundIndex = -1;
    foreach ($experiments as $idx => $existing) {
        if (isset($existing['id']) && strcasecmp($existing['id'], $targetLookupId) === 0) {
            $foundIndex = $idx;
            break;
        }
    }

    // Changing the ID must not collide with another experiment
    if ($originalId !== '' && strcasecmp($originalId, $id) !== 0) {
        foreach ($experiments as $existing) {
            if (isset($existing['id']) && strcasecmp($existing['id'], $id) === 0) {
                return ['abort' => ['code' => 400, 'message' => "Experiment ID '{$id}' already exists."]];
            }
        }
    }

    if ($foundIndex >= 0) {
        $experiments[$foundIndex] = $experiment;
    } else {
        array_unshift($experiments, $experiment);
    }
    return ['experiments' => $experiments, 'result' => $foundIndex];
});

if (!$store['ok']) {
    http_response_code($store['code']);
    echo json_encode(['success' => false, 'message' => $store['code'] === 400 ? $store['message'] : 'Failed to save experiment to storage.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$foundIndex = (int) $store['result'];

$labAction = ($foundIndex >= 0) ? 'lab.update' : 'lab.create';
logAdminAction($labAction, 'lab', (string) $id, json_encode([
    'title'  => $experiment['title'] ?? '',
    'status' => $experiment['status'] ?? '',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'success'    => true,
    'message'    => 'Experiment saved successfully.',
    'experiment' => $experiment,
], JSON_UNESCAPED_UNICODE);

