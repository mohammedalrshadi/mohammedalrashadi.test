<?php
// ============================================================
// POSTS — CREATE
// POST /api/posts/create.php
// Requires: authenticated session
// Body (JSON): {title, category, content, type, status, image_url, created_at}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/categories/helper.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

// Auth + CSRF checks (each exits on failure)
requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Parse body ----------------------------------------------------------
$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

// ---- Extract & validate fields ------------------------------------------
$title    = isset($input['title'])    ? trim($input['title'])    : '';
$category = isset($input['category']) ? normalizeCategoryWhitespace((string) $input['category']) : '';
$rawContent = isset($input['content']) ? (string) $input['content'] : '';
$type     = isset($input['type'])     ? trim($input['type'])     : 'blog';
$imageUrl = isset($input['image_url'])
            ? trim($input['image_url'])
            : null;
$quoteAr = isset($input['quote_ar']) ? trim($input['quote_ar']) : null;
$quoteEn = isset($input['quote_en']) ? trim($input['quote_en']) : null;
$quoteAr = ($quoteAr === '') ? null : $quoteAr;
$quoteEn = ($quoteEn === '') ? null : $quoteEn;
$createdAt = isset($input['created_at'])
             ? trim($input['created_at'])
             : date('Y-m-d H:i:s');

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'العنوان مطلوب.']);
    exit;
}

try {
    $content = sanitizeAndValidatePostContent($rawContent);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Whitelist type
if (!in_array($type, ['blog', 'project', 'project'], true)) {
    $type = 'blog';
}

// [REQ-012] Status validation: omitted -> 'draft', valid ('draft','published','hidden') -> accepted, invalid -> HTTP 400
$rawStatus = array_key_exists('status', $input) ? (string) $input['status'] : null;
$status = validateCreateStatus($rawStatus);
if ($status === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'حالة المنشور غير صالحة.']);
    exit;
}

// Validate image URL if provided
if ($imageUrl !== null && $imageUrl !== '') {
    // Must be a relative path starting with /uploads/ or an empty string
    if (!preg_match('#^/uploads/[a-zA-Z0-9_\-.]+$#', $imageUrl)) {
        // Accept absolute URLs from existing content (e.g., previously migrated)
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = null;
        }
    }
} else {
    $imageUrl = null;
}

// Normalize created_at to MySQL DATETIME
$createdAtParsed = date('Y-m-d H:i:s', strtotime($createdAt));
if ($createdAtParsed === '1970-01-01 00:00:00') {
    $createdAtParsed = date('Y-m-d H:i:s');
}

// ---- Insert --------------------------------------------------------------
try {

    $pdo  = getDB();

    // Validate category against categories table (REQ-015 Phase 2)
    // Validate / auto-create category against categories table (REQ-015 / REQ-019)
    if ($category !== '') {
        try {
            $category = resolvePostCategory($pdo, $category, $type);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Category error: ' . $e->getMessage(),
            ]);
            exit;
        }
    }

    $slug = generatePostSlug($pdo, $title);

    $stmt = $pdo->prepare(
        'INSERT INTO posts (title, slug, category, content, type, status, image_url, quote_ar, quote_en, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $slug,
        $category,
        $content,
        $type,
        $status,
        $imageUrl,
        $quoteAr,
        $quoteEn,
        $createdAtParsed,
    ]);

    $newId = (int) $pdo->lastInsertId();

    logAdminAction('post.create', 'post', (string) $newId, json_encode([
        'title'    => mb_substr($title, 0, 100, 'UTF-8'),
        'type'     => $type,
        'status'   => $status,
        'category' => $category,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء المنشور بنجاح.',
        'id'      => $newId,
    ]);

} catch (PDOException $e) {

    error_log('[posts/create] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
