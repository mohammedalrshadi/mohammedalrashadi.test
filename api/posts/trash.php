<?php
// ============================================================
// POSTS — TRASH (SOFT-DELETED LIST)  [V5 REQ-005]
// GET /api/posts/trash.php
// Requires: authenticated admin session
//
// Returns all soft-deleted posts (deleted_at IS NOT NULL),
// ordered by deletion time (most recently deleted first).
// This endpoint is admin-only — deleted content must never
// be visible to the public.
//
// Optional query parameter:
//   ?type=blog           → only deleted blog posts
//   ?type=project    → only deleted projects
//   (omit)               → all deleted posts
//
// Response:
//   { "success": true, "data": [ ... ] }
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Admin-only
requireAuth();

// Optional type filter
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
}

try {

    $pdo = getDB();

    if ($typeFilter !== null) {
        $stmt = $pdo->prepare(
            "SELECT id, title, category, type, status, image_url, created_at, deleted_at
             FROM   posts
             WHERE  deleted_at IS NOT NULL
               AND  type = ?
             ORDER  BY deleted_at DESC"
        );
        $stmt->execute([$typeFilter]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, title, category, type, status, image_url, created_at, deleted_at
             FROM   posts
             WHERE  deleted_at IS NOT NULL
             ORDER  BY deleted_at DESC"
        );
        $stmt->execute();
    }

    $posts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data'    => $posts,
    ]);

} catch (PDOException $e) {

    error_log('[posts/trash] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
