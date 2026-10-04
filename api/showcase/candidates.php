<?php
// ============================================================
// HOME SHOWCASE — CANDIDATES
// GET /api/showcase/candidates.php
//
// Admin-only endpoint returning candidate entities (products, projects, writing)
// for quick assignment in the Home Showcase curation workspace.
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();

$type   = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
if ($type === 'achievement') {
    error_log("Deprecated API usage: 'achievement' type requested. Use 'project' instead.");
    $type = 'project';
}
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit  = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;

try {
    $pdo = getDB();
    $results = [
        'products' => [],
        'projects' => [],
        'writing'  => [],
        'articles' => [] // alias for backward compatibility
    ];

    // 1. Candidate Products
    if ($type === 'all' || $type === 'product') {
        $pSql = "SELECT id, title, slug, category, price_display, thumbnail, status 
                 FROM products 
                 WHERE status != 'archived'";
        $pParams = [];
        if ($search !== '') {
            $pSql .= " AND (title LIKE ? OR short_description LIKE ?)";
            $pParams[] = "%{$search}%";
            $pParams[] = "%{$search}%";
        }
        $pSql .= " ORDER BY id DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($pSql);
        $stmt->execute($pParams);
        $results['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. Candidate Projects (posts where type = 'project')
    if ($type === 'all' || $type === 'project') {
        $prSql = "SELECT id, title, category, image_url, status 
                  FROM posts 
                  WHERE type = 'project' AND deleted_at IS NULL";
        $prParams = [];
        if ($search !== '') {
            $prSql .= " AND (title LIKE ? OR content LIKE ?)";
            $prParams[] = "%{$search}%";
            $prParams[] = "%{$search}%";
        }
        $prSql .= " ORDER BY id DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($prSql);
        $stmt->execute($prParams);
        $results['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Candidate Writing (posts where type IN ('blog','article'))
    if ($type === 'all' || $type === 'writing' || $type === 'article') {
        $wSql = "SELECT id, title, category, image_url, status 
                 FROM posts 
                 WHERE type IN ('blog','article') AND deleted_at IS NULL";
        $wParams = [];
        if ($search !== '') {
            $wSql .= " AND (title LIKE ? OR content LIKE ?)";
            $wParams[] = "%{$search}%";
            $wParams[] = "%{$search}%";
        }
        $wSql .= " ORDER BY id DESC LIMIT {$limit}";
        $stmt = $pdo->prepare($wSql);
        $stmt->execute($wParams);
        $writingPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results['writing']  = $writingPosts;
        $results['articles'] = $writingPosts;
    }

    echo json_encode([
        'success' => true,
        'data'    => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log('[api/showcase/candidates.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch candidate items.']);
}
