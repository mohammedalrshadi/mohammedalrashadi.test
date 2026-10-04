<?php
// ============================================================
// BOOKMARKS — TOGGLE
// POST /api/bookmarks/toggle.php
// Requires: authenticated user + CSRF token
// Body: {"content_type": "writing|project|lab|product", "content_id": "..."}
// Returns: {success: true, bookmarked: true|false}
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/user/content_resolver.php';
require_once dirname(__DIR__) . '/user/activity_helper.php';

header('Content-Type: application/json');

requireUserAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$contentType = isset($input['content_type']) ? strtolower(trim((string)$input['content_type'])) : '';
$contentId   = isset($input['content_id']) ? trim((string)$input['content_id']) : '';

if (!isValidUserContentType($contentType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid content type. Must be writing, project, lab, or product.']);
    exit;
}

if ($contentId === '' || strlen($contentId) > 64) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid content ID.']);
    exit;
}

$userId = currentUserId();

try {
    $pdo = getDB();

    // Check existing bookmark
    $checkStmt = $pdo->prepare(
        "SELECT id FROM bookmarks WHERE user_id = ? AND content_type = ? AND content_id = ? LIMIT 1"
    );
    $checkStmt->execute([$userId, $contentType, $contentId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Resolve content metadata and validate existence
    $meta = resolveUserContent($pdo, $contentType, $contentId);

    if ($existing) {
        // Remove bookmark
        $delStmt = $pdo->prepare(
            "DELETE FROM bookmarks WHERE user_id = ? AND content_type = ? AND content_id = ?"
        );
        $delStmt->execute([$userId, $contentType, $contentId]);

        logUserActivity($pdo, $userId, 'bookmark_removed', $contentType, $contentId, "Removed bookmark: {$meta['title']}");

        echo json_encode([
            'success'    => true,
            'bookmarked' => false,
            'message'    => 'Bookmark removed.'
        ]);
    } else {
        // Reject adding bookmark for nonexistent or mismatched content
        if ($meta['is_missing']) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'The requested content does not exist or does not match the specified type.'
            ]);
            exit;
        }

        // Add bookmark
        $insStmt = $pdo->prepare(
            "INSERT INTO bookmarks (user_id, content_type, content_id) VALUES (?, ?, ?)"
        );
        $insStmt->execute([$userId, $contentType, $contentId]);

        logUserActivity($pdo, $userId, 'bookmark_added', $contentType, $contentId, "Bookmarked {$meta['type_badge']}: {$meta['title']}");

        echo json_encode([
            'success'    => true,
            'bookmarked' => true,
            'message'    => 'Saved to bookmarks.'
        ]);
    }

} catch (Exception $e) {
    error_log('[bookmarks/toggle] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update bookmark.']);
}

