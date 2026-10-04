<?php
// ============================================================
// PRODUCTS — UPDATE
// POST /api/products/update.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {id, title, slug, short_description, description, category,
//               product_type, platform, price_display, currency, thumbnail,
//               external_url, download_path, status, featured, sort_order}
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

$id = isset($input['id']) ? (int) $input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required.']);
    exit;
}

try {
    $pdo = getDB();

    // Fetch existing product
    $existStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $existStmt->execute([$id]);
    $existing = $existStmt->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    // Title
    $title = isset($input['title']) ? trim((string)$input['title']) : (string)$existing['title'];
    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product title cannot be empty.',
            'errors' => [['field' => 'title', 'message' => 'Product title cannot be empty.']]]);
        exit;
    }
    if (strlen($title) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product title cannot exceed 500 characters.',
            'errors' => [['field' => 'title', 'message' => 'Product title cannot exceed 500 characters.']]]);
        exit;
    }

    // Slug
    $rawSlug = isset($input['slug']) ? trim((string)$input['slug']) : (string)$existing['slug'];
    $slug = $rawSlug !== '' ? strtolower($rawSlug) : slugifyProductTitle($title);

    if (!isValidProductSlug($slug)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Slug format is invalid. Use lowercase letters, numbers, and hyphens only.',
            'errors' => [['field' => 'slug', 'message' => 'Slug format is invalid. Use lowercase letters, numbers, and hyphens only.']]]);
        exit;
    }

    // Check slug uniqueness excluding self
    $slugStmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id <> ? LIMIT 1');
    $slugStmt->execute([$slug, $id]);
    if ($slugStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "The slug '{$slug}' is already in use by another product."]);
        exit;
    }

    // Category
    $category = isset($input['category']) ? normalizeProductCategory((string)$input['category']) : (string)$existing['category'];

    // Product Type
    if (isset($input['product_type']) && in_array($input['product_type'], ALLOWED_PRODUCT_TYPES, true)) {
        $productType = $input['product_type'];
    } elseif (!empty($existing['product_type'])) {
        $productType = $existing['product_type'];
    } else {
        $isExternalAccess = isset($input['external_url']) ? !empty(trim((string)$input['external_url'])) : !empty($existing['external_url']);
        $isDirectDownload = isset($input['download_path']) ? !empty(trim((string)$input['download_path'])) : !empty($existing['download_path']);
        $productType = (!$isExternalAccess && $isDirectDownload) ? 'free_download' : 'external';
    }
    if (!in_array($productType, ALLOWED_PRODUCT_TYPES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid product type.']);
        exit;
    }

    // Status
    $status = isset($input['status']) ? trim((string)$input['status']) : (string)$existing['status'];
    if (!in_array($status, ALLOWED_PRODUCT_STATUSES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    // Descriptions
    $shortDesc = array_key_exists('short_description', $input)
        ? (trim((string)$input['short_description']) !== '' ? trim((string)$input['short_description']) : null)
        : $existing['short_description'];

    $description = array_key_exists('description', $input)
        ? ArticleHtmlSanitizer::sanitize((string)$input['description'])
        : $existing['description'];

    // Platform & External URL
    $platform = array_key_exists('platform', $input)
        ? (trim((string)$input['platform']) !== '' ? trim((string)$input['platform']) : null)
        : $existing['platform'];

    $externalUrl = array_key_exists('external_url', $input)
        ? (trim((string)$input['external_url']) !== '' ? trim((string)$input['external_url']) : null)
        : $existing['external_url'];

    if ($productType === 'external') {
        if (empty($externalUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'External product URL is required for external products.']);
            exit;
        }
        if (!filter_var($externalUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $externalUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'External URL must be a valid HTTP or HTTPS address.',
                'errors' => [['field' => 'external_url', 'message' => 'External URL must be a valid HTTP or HTTPS address.']]]);
            exit;
        }
        if (empty($platform)) {
            $platform = 'External Platform';
        }
    } else {
        $externalUrl = null;
        $platform    = null;
    }

    // Download Path
    $downloadPath = array_key_exists('download_path', $input)
        ? (trim((string)$input['download_path']) !== '' ? trim((string)$input['download_path']) : null)
        : $existing['download_path'];

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
        if (isset($input['download_path']) && !empty(trim((string)$input['download_path']))) {
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
    $liveDemoUrl = array_key_exists('live_demo_url', $input)
        ? (trim((string)$input['live_demo_url']) !== '' ? trim((string)$input['live_demo_url']) : null)
        : $existing['live_demo_url'];

    if (!empty($liveDemoUrl)) {
        if (!filter_var($liveDemoUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $liveDemoUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Live Demo URL must be a valid HTTP or HTTPS web address.',
                'errors' => [['field' => 'live_demo_url', 'message' => 'Live Demo URL must be a valid HTTP or HTTPS web address.']]]);
            exit;
        }
    }

    // Live Demo Source (External vs Uploaded)
    $liveDemoSource = $existing['live_demo_source'];
    $liveDemoPath = $existing['live_demo_path'];
    $shouldDeleteDemoDir = false; // track whether to clean up the filesystem after DB commit
    if (array_key_exists('live_demo_source', $input)) {
        $suppliedSource = $input['live_demo_source'];
        if ($suppliedSource === 'external') {
            // Switching to external URL — clear the path reference but leave the directory
            // intact (admin may re-enable the uploaded demo later without re-uploading).
            $liveDemoSource = 'external';
            $liveDemoPath = null;
        } elseif ($suppliedSource === 'uploaded') {
            $liveDemoSource = 'uploaded';
            // keep existing live_demo_path if any
        } else {
            // Demo fully disabled — clear DB fields and schedule directory deletion
            // only if the prior state was 'uploaded' (there is a physical directory to clean).
            if ($existing['live_demo_source'] === 'uploaded') {
                $shouldDeleteDemoDir = true;
            }
            $liveDemoSource = null;
            $liveDemoPath = null;
        }
    }

    // Pricing & Presentation
    $priceDisplay = array_key_exists('price_display', $input)
        ? (trim((string)$input['price_display']) !== '' ? trim((string)$input['price_display']) : null)
        : $existing['price_display'];

    if ($productType === 'free_download' && empty($priceDisplay)) {
        $priceDisplay = 'Free';
    }

    $currency = array_key_exists('currency', $input) && trim((string)$input['currency']) !== ''
        ? strtoupper(substr(trim((string)$input['currency']), 0, 10))
        : (string)$existing['currency'];

    $thumbnail = array_key_exists('thumbnail', $input)
        ? (trim((string)$input['thumbnail']) !== '' ? trim((string)$input['thumbnail']) : null)
        : $existing['thumbnail'];

    $featured = array_key_exists('featured', $input)
        ? (!empty($input['featured']) ? 1 : 0)
        : (int)$existing['featured'];

    $sortOrder = array_key_exists('sort_order', $input)
        ? max(0, (int)$input['sort_order'])
        : (int)$existing['sort_order'];

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

    // Update query
    $updateStmt = $pdo->prepare(
        'UPDATE products SET
            title = ?,
            slug = ?,
            short_description = ?,
            description = ?,
            category = ?,
            product_type = ?,
            platform = ?,
            price_display = ?,
            currency = ?,
            thumbnail = ?,
            external_url = ?,
            download_path = ?,
            live_demo_url = ?,
            live_demo_source = ?,
            live_demo_path = ?,
            status = ?,
            featured = ?,
            sort_order = ?
        WHERE id = ?'
    );

    $updateStmt->execute([
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
        $liveDemoSource,
        $liveDemoPath,
        $status,
        $featured,
        $sortOrder,
        $id,
    ]);

    logAdminAction('product.update', 'product', (string) $id, json_encode([
        'title'        => mb_substr($title, 0, 100, 'UTF-8'),
        'slug'         => $slug,
        'product_type' => $productType,
        'status'       => $status,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // After a successful DB update, clean up the orphaned demo directory.
    // This runs AFTER the DB commit so a filesystem failure never rolls back
    // the DB change — the DB state clearing is the authoritative operation.
    if ($shouldDeleteDemoDir) {
        try {
            $demosBaseDir = dirname(dirname(__DIR__)) . '/uploads/products/demos';
            $productDemoDir = $demosBaseDir . '/' . $id;
            removeDirRecursive($productDemoDir);
        } catch (Throwable $fsErr) {
            error_log('[products/update] Failed to delete demo directory for product ' . $id . ': ' . $fsErr->getMessage());
            // Do not alter the response — DB is already updated, filesystem cleanup is best-effort.
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully.',
        'id'      => $id,
        'slug'    => $slug,
    ]);

} catch (PDOException $e) {
    error_log('[products/update] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while updating product.']);
}
