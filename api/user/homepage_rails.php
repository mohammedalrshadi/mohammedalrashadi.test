<?php
// ============================================================
// HOMEPAGE RAILS API
// GET /api/user/homepage_rails.php
//
// Returns contextual data for the homepage:
// - User's first name
// - Recently viewed items (reading_history)
// - User's bookmarks
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

_startSecureSession();

if (!isUserLoggedIn()) {
    echo json_encode(['success' => true, 'isLoggedIn' => false]);
    exit;
}

$currentUser = currentUser();
$userId = (int)$currentUser['id'];
$firstName = explode(' ', trim($currentUser['name']))[0];

$recentlyViewed = [];
$userBookmarks = [];

try {
    $pdo = getDB();
    require_once __DIR__ . '/content_resolver.php';

    // 1. Fetch reading history (limit 3 for homepage)
    $hStmt = $pdo->prepare(
        "SELECT content_type, content_id, last_viewed_at 
         FROM reading_history 
         WHERE user_id = ? 
         ORDER BY last_viewed_at DESC 
         LIMIT 3"
    );
    $hStmt->execute([$userId]);
    $histRows = $hStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($histRows as $hr) {
        $meta = resolveUserContent($pdo, $hr['content_type'], $hr['content_id']);
        if (!$meta['is_missing']) {
            $recentlyViewed[] = array_merge($hr, ['item' => $meta]);
        }
    }

    // 2. Fetch bookmarks (limit 3 for homepage)
    $bStmt = $pdo->prepare(
        "SELECT id, content_type, content_id, created_at 
         FROM bookmarks 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 3"
    );
    $bStmt->execute([$userId]);
    $bookRows = $bStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookRows as $br) {
        $meta = resolveUserContent($pdo, $br['content_type'], $br['content_id']);
        if (!$meta['is_missing']) {
            $userBookmarks[] = array_merge($br, ['item' => $meta]);
        }
    }

} catch (Exception $e) {
    error_log('[homepage_rails] Query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}

echo json_encode([
    'success' => true,
    'isLoggedIn' => true,
    'user' => [
        'firstName' => $firstName
    ],
    'recentlyViewed' => $recentlyViewed,
    'userBookmarks' => $userBookmarks
], JSON_UNESCAPED_UNICODE);
