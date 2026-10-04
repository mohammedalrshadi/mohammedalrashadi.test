<?php
// ============================================================
// CATEGORIES — CREATE [REQ-015 Phase 2]
// POST /api/categories/create.php
//
// Admin only + CSRF protected.
// Body (JSON): { "name": "...", "type": "blog" }
//
// Creates a new managed category without creating any post.
// Rejects duplicates (both exact and normalized Arabic).
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

$rawName = isset($input['name']) ? (string) $input['name'] : '';
$rawType = isset($input['type']) ? (string) $input['type'] : '';

$type = trim($rawType);
if (!isValidCategoryType($type)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid category type. Must be one of: ' . implode(', ', VALID_CATEGORY_TYPES),
    ]);
    exit;
}

$linkGroup = !empty($input['link_group']) ? trim((string)$input['link_group']) : null;
$name = normalizeCategoryWhitespace($rawName);
$validationError = validateCategoryName($name);
if ($validationError !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $validationError]);
    exit;
}

try {
    $pdo = getDB();

    // Check for exact or normalized Arabic duplicate within the same type
    $existing = findCategoryMatch($pdo, $name, $type);
    if ($existing) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Category already exists for this type (' . $existing['name'] . ').',
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO categories (name, type, link_group, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
    );
    $stmt->execute([$name, $type, $linkGroup]);
    $newId = (int) $pdo->lastInsertId();

    logAdminAction('category.create', 'category', (string) $newId, json_encode([
        'name' => $name,
        'type' => $type,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Category created successfully.',
        'data'    => [
            'id'   => $newId,
            'name' => $name,
            'type' => $type,
        ],
    ]);

} catch (PDOException $e) {
    // Catch unique constraint violation error code 23000
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'التصنيف موجود بالفعل لنفس النوع.']);
        exit;
    }

    error_log('[categories/create] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
}
