<?php
// ============================================================
// LAB EXPERIMENTS — LIST API
// GET /api/labs/list.php
// Returns JSON array of lab experiments.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

$dataFile = dirname(__DIR__) . '/data/lab_experiments.json';

if (!file_exists($dataFile)) {
    echo json_encode(['success' => true, 'experiments' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents($dataFile);
$experiments = json_decode($raw, true) ?: [];

// Optional filter by category
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
if ($category !== '' && strtolower($category) !== 'all') {
    $experiments = array_values(array_filter($experiments, function ($exp) use ($category) {
        return isset($exp['category']) && strcasecmp($exp['category'], $category) === 0;
    }));
}

// Optional filter by search query
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q !== '') {
    $experiments = array_values(array_filter($experiments, function ($exp) use ($q) {
        $searchSpace = ($exp['id'] ?? '') . ' ' . ($exp['title'] ?? '') . ' ' . ($exp['question'] ?? '') . ' ' . ($exp['category'] ?? '');
        return stripos($searchSpace, $q) !== false;
    }));
}

echo json_encode([
    'success'     => true,
    'count'       => count($experiments),
    'experiments' => $experiments,
], JSON_UNESCAPED_UNICODE);

