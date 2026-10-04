<?php
// ============================================================
// BOOKMARKS — LIST
// GET /api/bookmarks/list.php
// Requires: authenticated user
// Returns: list of bookmarks with resolved live content metadata
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/user/content_resolver.php';

header('Content-Type: application/json');

requireUserAuth();

$userId = currentUserId();

try {
    $pdo = getDB();

    $stmt = $pdo->prepare(
        "SELECT id, content_type, content_id, created_at 
         FROM bookmarks 
         WHERE user_id = ? 
         ORDER BY created_at DESC"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resolvedList = [];
    foreach ($rows as $r) {
        $meta = resolveUserContent($pdo, $r['content_type'], $r['content_id']);
        $resolvedList[] = array_merge($r, [
            'item' => $meta
        ]);
    }

    echo json_encode([
        'success' => true,
        'count'   => count($resolvedList),
        'data'    => $resolvedList
    ]);

} catch (Exception $e) {
    error_log('[bookmarks/list] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve bookmarks.']);
}

