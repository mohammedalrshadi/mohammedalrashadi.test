<?php
// ============================================================
// POSTS — DISTINCT CATEGORIES  [V5 REQ-002]
// GET /api/posts/categories.php
//
// Returns every non-empty, non-duplicate category value that
// exists in the posts table, ordered alphabetically.
//
// Query parameters (all optional):
//   ?type=blog          → only categories used by blog posts
//   ?type=project   → only categories used by projects
//   (omit type)         → all categories across both types
//
// Authentication:
//   Read-only. No login required — category names are not
//   sensitive and must be available to the admin form without
//   an extra session check. The admin pages already enforce
//   requireAdminPage() at the PHP level before HTML is sent,
//   so this endpoint is effectively admin-only in practice.
//
// Response:
//   { "success": true, "data": ["cat1", "cat2", ...] }
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Optional type filter ----------------------------------------
// Accept only the two known post types; anything else is ignored.
$allowedTypes = ['blog', 'project'];
$typeFilter   = null;
if (isset($_GET['type'])) {
    $raw = trim($_GET['type']);
    if ($raw === 'achievement') {
        error_log("Deprecated API usage: 'achievement' type requested. Use 'project' instead.");
        $raw = 'project';
    }
    if (in_array($raw, $allowedTypes, true)) {
        $typeFilter = $raw;
    }
    // Unknown type value → silently ignore, return all categories
}

try {

    $pdo = getDB();

    // Query categories table as the single source of truth
    $where = [];
    $params = [];

    if ($typeFilter !== null) {
        $where[] = "type = ?";
        $params[] = $typeFilter;
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare(
        "SELECT DISTINCT name AS category
         FROM   categories
         $whereSql
         ORDER  BY name ASC"
    );
    $stmt->execute($params);

    // fetchAll returns rows like [['category' => 'foo'], ...]
    // Flatten to a plain array of strings.
    $rows       = $stmt->fetchAll();
    $categories = array_column($rows, 'category');

    echo json_encode([
        'success' => true,
        'data'    => $categories,
    ]);

} catch (PDOException $e) {

    error_log('[posts/categories] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
