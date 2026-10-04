<?php
// ============================================================
// READING HISTORY — RECORD VIEW
// POST /api/history/record.php
// Requires: authenticated user
// Body: {"content_type": "writing|project|lab|product", "content_id": "...", "progress_percent": 0}
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

$contentType     = isset($input['content_type']) ? strtolower(trim((string)$input['content_type'])) : '';
$contentId       = isset($input['content_id']) ? trim((string)$input['content_id']) : '';
$progressPercent = isset($input['progress_percent']) ? (int)$input['progress_percent'] : 0;

if ($progressPercent < 0) $progressPercent = 0;
if ($progressPercent > 100) $progressPercent = 100;

if (!isValidUserContentType($contentType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid content type.']);
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

    // Resolve content metadata and validate existence
    $meta = resolveUserContent($pdo, $contentType, $contentId);
    if ($meta['is_missing']) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'The requested content does not exist or does not match the specified type.'
        ]);
        exit;
    }

    // Check if user has paused reading history
    $profStmt = $pdo->prepare("SELECT save_reading_history FROM user_profiles WHERE user_id = ?");
    $profStmt->execute([$userId]);
    $prof = $profStmt->fetch(PDO::FETCH_ASSOC);
    $saveHistory = !isset($prof['save_reading_history']) || (int)$prof['save_reading_history'] === 1;

    if (!$saveHistory) {
        echo json_encode([
            'success' => true,
            'message' => 'History paused. (Not recorded)'
        ]);
        exit;
    }

    // Check if viewed recently to avoid spamming activity logs
    $timeCheck = $pdo->prepare(
        "SELECT last_viewed_at FROM reading_history 
         WHERE user_id = ? AND content_type = ? AND content_id = ? 
         LIMIT 1"
    );
    $timeCheck->execute([$userId, $contentType, $contentId]);
    $lastViewRow = $timeCheck->fetch(PDO::FETCH_ASSOC);

    $shouldLogActivity = true;
    if ($lastViewRow && !empty($lastViewRow['last_viewed_at'])) {
        $lastViewTime = strtotime($lastViewRow['last_viewed_at']);
        // If viewed within the last 15 minutes, do not spam activity timeline
        if (time() - $lastViewTime < 900) {
            $shouldLogActivity = false;
        }
    }

    // UPSERT into reading_history
    $stmt = $pdo->prepare(
        "INSERT INTO reading_history (user_id, content_type, content_id, progress_percent, last_viewed_at) 
         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE 
            progress_percent = GREATEST(progress_percent, VALUES(progress_percent)), 
            last_viewed_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$userId, $contentType, $contentId, $progressPercent]);

    if ($shouldLogActivity) {
        logUserActivity($pdo, $userId, 'content_viewed', $contentType, $contentId, "Viewed {$meta['type_badge']}: {$meta['title']}");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reading history updated.'
    ]);

} catch (Exception $e) {
    error_log('[history/record] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to record history.']);
}

