<?php
// ============================================================
// PRODUCTS — LIST / DETAIL
// GET /api/products/list.php
//
// Query parameters:
//   ?slug=product-slug  → fetch single product by slug
//   ?id=123             → admin only: fetch single product by ID
//   ?category=...       → filter by category
//   ?product_type=...   → filter by product_type (external | free_download)
//   ?status=...         → filter by status (published | draft | archived | all)
//   ?featured=1         → filter featured products only
//   ?search=...         → keyword search in title & short_description
//   ?sort=...           → sort ordering (sort_order, newest, oldest, title_asc, title_desc)
//   ?limit=20           → pagination limit (default 20, max 100)
//   ?offset=0           → pagination offset (default 0)
//
// Security & Public Access Rules:
//   - Unauthenticated / public requests can ONLY see status='published'.
//   - Internal download_path is NEVER leaked in public responses; instead,
//     a secure download_url (/api/products/download.php?id={id}) is provided.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$isAdmin = isAdminLoggedIn();

$requestedId     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$requestedSlug   = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$requestedStatus = isset($_GET['status']) ? trim($_GET['status']) : 'published';
$requestedCat    = isset($_GET['category']) ? trim($_GET['category']) : null;
$requestedType   = isset($_GET['product_type']) ? trim($_GET['product_type']) : null;
$requestedFeat   = isset($_GET['featured']) ? (int)$_GET['featured'] : null;
$requestedSearch = isset($_GET['search']) ? trim($_GET['search']) : null;
$requestedSort   = isset($_GET['sort']) ? trim($_GET['sort']) : 'sort_order';

$limit  = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

try {
    $pdo = getDB();

    // -----------------------------------------------------------------
    // 1. Single Product by ID (Admin-only or verified published)
    // -----------------------------------------------------------------
    if ($requestedId !== null && $requestedId > 0) {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'SELECT id, title, slug, short_description, description, category, product_type,
                    platform, price_display, currency, thumbnail, external_url, download_path,
                    live_demo_url, live_demo_source, live_demo_path,
                    status, featured, sort_order, created_at, updated_at
             FROM products
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$requestedId]);
        $product = $stmt->fetch();

        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        $product['featured'] = (int) $product['featured'];
        $product['sort_order'] = (int) $product['sort_order'];
        $product['has_download_file'] = !empty($product['download_path']) && resolveProtectedDownloadFile($product['download_path']) !== null;

        echo json_encode(['success' => true, 'data' => $product]);
        exit;
    }

    // -----------------------------------------------------------------
    // 2. Single Product by Slug
    // -----------------------------------------------------------------
    if ($requestedSlug !== null && $requestedSlug !== '') {
        $whereStatus = $isAdmin ? '' : "AND status = 'published'";

        $stmt = $pdo->prepare(
            "SELECT id, title, slug, short_description, description, category, product_type,
                    platform, price_display, currency, thumbnail, external_url, download_path,
                    live_demo_url, live_demo_source, live_demo_path,
                    status, featured, sort_order, created_at, updated_at
             FROM products
             WHERE slug = ? $whereStatus
             LIMIT 1"
        );
        $stmt->execute([$requestedSlug]);
        $product = $stmt->fetch();

        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        $product['featured'] = (int) $product['featured'];
        $product['sort_order'] = (int) $product['sort_order'];

        if ($product['product_type'] === 'free_download') {
            $product['download_url'] = '/api/products/download.php?id=' . $product['id'];
        }

        // Hide internal filesystem path from non-admin users
        if (!$isAdmin) {
            unset($product['download_path']);
        } else {
            $product['has_download_file'] = !empty($product['download_path']) && resolveProtectedDownloadFile($product['download_path']) !== null;
        }

        echo json_encode(['success' => true, 'data' => $product]);
        exit;
    }

    // -----------------------------------------------------------------
    // 3. Product Listing
    // -----------------------------------------------------------------
    $where = [];
    $params = [];

    // Status filter
    if ($isAdmin) {
        if ($requestedStatus !== 'all' && in_array($requestedStatus, ALLOWED_PRODUCT_STATUSES, true)) {
            $where[] = 'status = ?';
            $params[] = $requestedStatus;
        }
    } else {
        $where[] = "status = 'published'";
    }

    // Category filter
    if ($requestedCat !== null && $requestedCat !== '' && $requestedCat !== 'all') {
        $where[] = 'category = ?';
        $params[] = $requestedCat;
    }

    // Product type filter
    if ($requestedType !== null && in_array($requestedType, ALLOWED_PRODUCT_TYPES, true)) {
        $where[] = 'product_type = ?';
        $params[] = $requestedType;
    }

    // Featured filter
    if ($requestedFeat !== null && ($requestedFeat === 0 || $requestedFeat === 1)) {
        $where[] = 'featured = ?';
        $params[] = $requestedFeat;
    }

    // Search filter
    if ($requestedSearch !== null && $requestedSearch !== '') {
        $where[] = '(title LIKE ? OR short_description LIKE ?)';
        $searchWildcard = '%' . $requestedSearch . '%';
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
    }

    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Sorting
    switch ($requestedSort) {
        case 'newest':
            $orderBy = 'created_at DESC, id DESC';
            break;
        case 'oldest':
            $orderBy = 'created_at ASC, id ASC';
            break;
        case 'title_asc':
            $orderBy = 'title ASC';
            break;
        case 'title_desc':
            $orderBy = 'title DESC';
            break;
        case 'sort_order':
        default:
            $orderBy = 'sort_order ASC, created_at DESC';
            break;
    }

    // Total count query
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products $whereClause");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Data query
    $dataSql = "SELECT id, title, slug, short_description, category, product_type,
                       platform, price_display, currency, thumbnail, external_url, live_demo_url,
                       status, featured, sort_order, created_at, updated_at
                       " . ($isAdmin ? ", download_path" : "") . "
                FROM products
                $whereClause
                ORDER BY $orderBy
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $dataStmt = $pdo->prepare($dataSql);
    $dataStmt->execute($params);
    $products = $dataStmt->fetchAll();

    // Format fields
    foreach ($products as &$p) {
        $p['featured'] = (int) $p['featured'];
        $p['sort_order'] = (int) $p['sort_order'];
        if ($p['product_type'] === 'free_download') {
            $p['download_url'] = '/api/products/download.php?id=' . $p['id'];
        }
        if (!$isAdmin) {
            unset($p['download_path']);
        }
    }
    unset($p);

    echo json_encode([
        'success' => true,
        'data'    => $products,
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
    ]);

} catch (PDOException $e) {
    error_log('[products/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}

