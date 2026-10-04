<?php
// ============================================================
// LIKES & BOOKMARK STATUS — Mohammed Alrashadi Personal Platform
// GET /api/likes/status.php?content_type=...&content_id=...
//
// Safe endpoint for public content pages.
// Returns like count and whether current visitor has liked/bookmarked.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/user/content_resolver.php';

header('Content-Type: application/json');

$contentType = isset($_GET['content_type']) ? strtolower(trim((string)$_GET['content_type'])) : '';
$contentId   = isset($_GET['content_id']) ? trim((string)$_GET['content_id']) : '';

if (!isValidUserContentType($contentType) || $contentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid content type or ID.']);
    exit;
}

$isAuth = isUserLoggedIn();
$userId = currentUserId();

$liked = false;
$bookmarked = false;
$totalLikes = 0;

try {
    $pdo = getDB();

    // Total likes
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM likes WHERE content_type = ? AND content_id = ?"
    );
    $countStmt->execute([$contentType, $contentId]);
    $totalLikes = (int)$countStmt->fetchColumn();

    if ($isAuth && $userId > 0) {
        // Check like
        $likeStmt = $pdo->prepare(
            "SELECT 1 FROM likes WHERE user_id = ? AND content_type = ? AND content_id = ? LIMIT 1"
        );
        $likeStmt->execute([$userId, $contentType, $contentId]);
        $liked = (bool)$likeStmt->fetch();

        // Check bookmark
        $bmStmt = $pdo->prepare(
            "SELECT 1 FROM bookmarks WHERE user_id = ? AND content_type = ? AND content_id = ? LIMIT 1"
        );
        $bmStmt->execute([$userId, $contentType, $contentId]);
        $bookmarked = (bool)$bmStmt->fetch();
    }

    echo json_encode([
        'success'          => true,
        'is_authenticated' => $isAuth,
        'liked'            => $liked,
        'bookmarked'       => $bookmarked,
        'likes_count'      => $totalLikes,
        'csrf_token'       => $isAuth ? getCsrfToken() : ''
    ]);

} catch (Exception $e) {
    error_log('[likes/status] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to check status.']);
}

