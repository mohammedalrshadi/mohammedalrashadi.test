<?php
// ============================================================
// ACHIEVEMENTS — UPDATE
// POST /api/achievements/update.php
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/helpers/achievements_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$body = json_decode(file_get_contents('php://input'), true);

if (!$body || !isset($body['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input or missing ID.']);
    exit;
}

$id           = (int) $body['id'];
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

    // A trashed achievement cannot be edited: restore it first.
    $stmt = $pdo->prepare('SELECT id FROM achievements WHERE id = ?' . (achievementsHasDeletedAt($pdo) ? ' AND deleted_at IS NULL' : ''));
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Achievement not found.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM achievements WHERE slug = ? AND id != ? LIMIT 1');
    $stmt->execute([$slug, $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Another achievement with this slug already exists.']);
        exit;
    }

    $sql = "UPDATE achievements SET
            title = ?, slug = ?, organization = ?, date_awarded = ?, category = ?, description = ?, image_url = ?, url = ?, status = ?
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $slug, $organization, $dateAwarded, $category, $description, $imageUrl, $url, $status, $id]);
    
    logAdminAction('achievement.update', 'achievement', (string)$id, ['title' => $title]);

    echo json_encode(['success' => true, 'message' => 'Achievement updated successfully.']);

} catch (PDOException $e) {
    error_log('[achievements/update] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
