<?php
// ============================================================
// ACHIEVEMENTS — CREATE
// POST /api/achievements/create.php
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$body = json_decode(file_get_contents('php://input'), true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$title        = trim($body['title'] ?? '');
$slug         = trim($body['slug'] ?? '');
$organization = trim($body['organization'] ?? '');
$dateAwarded  = trim($body['date_awarded'] ?? '');
$category     = trim($body['category'] ?? '');
$description  = trim($body['description'] ?? '');
$imageUrl     = trim($body['image_url'] ?? '');
$url          = trim($body['url'] ?? '');
$status       = trim($body['status'] ?? 'hidden');

if ($title === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title and Slug are required.']);
    exit;
}
if (mb_strlen($title) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title must not exceed 255 characters.']);
    exit;
}
if (mb_strlen($category) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category must not exceed 50 characters.']);
    exit;
}
if (mb_strlen($organization) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization must not exceed 255 characters.']);
    exit;
}
if ($status !== 'published' && $status !== 'hidden') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}
if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Slug can only contain lowercase letters, numbers, and hyphens.']);
    exit;
}
// /achievement/<digits> is routed to the numeric ID (see .htaccess), so an all-digit
// slug could never be reached through /achievement/<slug>. Keep the two namespaces apart.
if (ctype_digit($slug)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Slug cannot consist of numbers only. Add at least one letter or hyphen.']);
    exit;
}
if ($dateAwarded !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateAwarded)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Date awarded must be YYYY-MM-DD.']);
        exit;
    }
    if ($dateAwarded > date('Y-m-d')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Date awarded cannot be in the future.']);
        exit;
    }
}
if ($url !== '') {
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url) || mb_strlen($url) > 255) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'URL must be a valid http/https URL max 255 chars.']);
        exit;
    }
}
if ($imageUrl !== '') {
    if (mb_strlen($imageUrl) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image URL max 500 characters.']);
        exit;
    }
    if (!str_starts_with($imageUrl, '/uploads/') && !preg_match('/^https?:\/\//i', $imageUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image URL must be a valid https URL or a local /uploads/ path.']);
        exit;
    }
}

$organization = $organization === '' ? null : $organization;
$dateAwarded  = $dateAwarded === '' ? null : $dateAwarded;
$category     = $category === '' ? null : $category;
$description  = $description === '' ? null : $description;
$imageUrl     = $imageUrl === '' ? null : $imageUrl;
$url          = $url === '' ? null : $url;

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id FROM achievements WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'An achievement with this slug already exists.']);
        exit;
    }

    $sql = "INSERT INTO achievements (title, slug, organization, date_awarded, category, description, image_url, url, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $slug, $organization, $dateAwarded, $category, $description, $imageUrl, $url, $status]);

    $id = $pdo->lastInsertId();
    
    logAdminAction('achievement.create', 'achievement', (string)$id, ['title' => $title]);

    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Achievement created successfully.']);

} catch (PDOException $e) {
    error_log('[achievements/create] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
