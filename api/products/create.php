<?php
// ============================================================
// PRODUCTS — CREATE
// POST /api/products/create.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {title, slug, short_description, description, category,
//               product_type, platform, price_display, currency, thumbnail,
//               external_url, download_path, live_demo_url, status, featured, sort_order}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';
require_once dirname(dirname(__DIR__)) . '/includes/content_quality.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);

if (!is_array($input)) {
    $input = $_POST;
}

if (!is_array($input) || empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

// -----------------------------------------------------------------
// Field Extraction & Validation
// -----------------------------------------------------------------
$title = isset($input['title']) ? trim((string)$input['title']) : '';
if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product title is required.',
        'errors' => [['field' => 'title', 'message' => 'Product title is required.']]]);
    exit;
}
if (strlen($title) > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product title cannot exceed 500 characters.',
        'errors' => [['field' => 'title', 'message' => 'Product title cannot exceed 500 characters.']]]);
    exit;
}

// Slug handling
$rawSlug = isset($input['slug']) ? trim((string)$input['slug']) : '';
$slug = $rawSlug !== '' ? strtolower($rawSlug) : slugifyProductTitle($title);

if (!isValidProductSlug($slug)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Slug format is invalid. Use lowercase letters, numbers, and hyphens only.',
        'errors' => [['field' => 'slug', 'message' => 'Slug format is invalid. Use lowercase letters, numbers, and hyphens only.']]]);
    exit;
}

// Category
$category = isset($input['category']) ? normalizeProductCategory((string)$input['category']) : 'Other';

// Product Type
if (isset($input['product_type']) && in_array($input['product_type'], ALLOWED_PRODUCT_TYPES, true)) {
    $productType = $input['product_type'];
} else {
    $isExternalAccess = isset($input['external_url']) && !empty(trim((string)$input['external_url']));
    $isDirectDownload = isset($input['download_path']) && !empty(trim((string)$input['download_path']));
    $productType = (!$isExternalAccess && $isDirectDownload) ? 'free_download' : 'external';
}
if (!in_array($productType, ALLOWED_PRODUCT_TYPES, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product type.']);
    exit;
}

// Status
$status = isset($input['status']) ? trim((string)$input['status']) : 'draft';
if (!in_array($status, ALLOWED_PRODUCT_STATUSES, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

// Description & Short description
$shortDesc = isset($input['short_description']) ? trim((string)$input['short_description']) : null;
$rawDesc   = isset($input['description']) ? (string)$input['description'] : '';
$description = ArticleHtmlSanitizer::sanitize($rawDesc);

// Platform & External URL
$platform    = isset($input['platform']) ? trim((string)$input['platform']) : null;
$externalUrl = isset($input['external_url']) ? trim((string)$input['external_url']) : null;

if ($productType === 'external') {
    if (empty($externalUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'External product URL is required for external products.']);
        exit;
    }
    if (!filter_var($externalUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $externalUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'External URL must be a valid HTTP or HTTPS web address.',
            'errors' => [['field' => 'external_url', 'message' => 'External URL must be a valid HTTP or HTTPS web address.']]]);
        exit;
    }
    if (empty($platform)) {
        $platform = 'External Platform';
    }
} else {
    // Free download product does not require external platform/URL
    $externalUrl = null;
    $platform    = null;
}

// Download Path
$downloadPath = isset($input['download_path']) ? trim((string)$input['download_path']) : null;
if ($productType === 'free_download') {
    if ($status === 'published') {
        if (empty($downloadPath) || resolveProtectedDownloadFile($downloadPath) === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'A valid downloadable file must be uploaded before publishing a free download product.']);
            exit;
        }
    }
} else {
    // Consistency check: external products cannot have a populated download_path
    if (!empty($downloadPath)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'External products cannot have a direct download file attached.',
            'errors'  => [['field' => 'download_path', 'message' => 'Direct download file is only allowed for free download products.']]
        ]);
        exit;
    }
    $downloadPath = null;
}

// Live Demo URL
$rawLiveDemo = isset($input['live_demo_url']) ? trim((string)$input['live_demo_url']) : null;
$liveDemoUrl = $rawLiveDemo !== '' ? $rawLiveDemo : null;
if (!empty($liveDemoUrl)) {
    if (!filter_var($liveDemoUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $liveDemoUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Live Demo URL must be a valid HTTP or HTTPS web address.',
            'errors' => [['field' => 'live_demo_url', 'message' => 'Live Demo URL must be a valid HTTP or HTTPS web address.']]]);
        exit;
    }
}

// Pricing & Presentation
$priceDisplay = isset($input['price_display']) ? trim((string)$input['price_display']) : null;
if ($productType === 'free_download' && empty($priceDisplay)) {
    $priceDisplay = 'Free';
}

$currency  = isset($input['currency']) && trim((string)$input['currency']) !== '' ? strtoupper(substr(trim((string)$input['currency']), 0, 10)) : 'USD';
$thumbnail = isset($input['thumbnail']) && trim((string)$input['thumbnail']) !== '' ? trim((string)$input['thumbnail']) : null;
$featured  = !empty($input['featured']) ? 1 : 0;
$sortOrder = isset($input['sort_order']) ? max(0, (int)$input['sort_order']) : 0;

if ($status === 'published') {
    $errors = [];
    
    if (trim((string)$title) === '') {
        $errors[] = ['field' => 'title', 'message' => 'Product title cannot be empty.'];
    }
    
    if ($productType === 'external' && empty($externalUrl)) {
        $errors[] = ['field' => 'external_url', 'message' => 'External product URL is required to publish an external product.'];
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Publish validation failed.', 'errors' => $errors]);
        exit;
    }
}

try {
    $pdo = getDB();

    // Check slug uniqueness
    $checkStmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
    $checkStmt->execute([$slug]);
    if ($checkStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "The slug '{$slug}' is already in use by another product."]);
        exit;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO products (
            title, slug, short_description, description, category,
            product_type, platform, price_display, currency, thumbnail,
            external_url, download_path, live_demo_url, status, featured, sort_order
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )'
    );

    $insertStmt->execute([
        $title,
        $slug,
        $shortDesc,
        $description,
        $category,
        $productType,
        $platform,
        $priceDisplay,
        $currency,
        $thumbnail,
        $externalUrl,
        $downloadPath,
        $liveDemoUrl,
        $status,
        $featured,
        $sortOrder,
    ]);

    $newId = (int)$pdo->lastInsertId();

    logAdminAction('product.create', 'product', (string) $newId, json_encode([
        'title'        => mb_substr($title, 0, 100, 'UTF-8'),
        'slug'         => $slug,
        'product_type' => $productType,
        'status'       => $status,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Product created successfully.',
        'id'      => $newId,
        'slug'    => $slug,
    ]);

} catch (PDOException $e) {
    error_log('[products/create] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while creating product.']);
}
