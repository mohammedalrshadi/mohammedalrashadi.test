<?php
// ============================================================
// PROJECT IMAGES — LIST  [REQ-006]
// GET /api/project_images/list.php?post_id=N
//
// Public endpoint: returns ordered gallery images for an
// project. Visibility rules differ by caller:
//
//   Unauthenticated (public site):
//     post must be type='project', status='published',
//     deleted_at IS NULL.
//
//   Authenticated admin (isAdminLoggedIn()):
//     post must be type='project', deleted_at IS NULL.
//     The status check is relaxed so admins can load gallery
//     images for hidden projects. No separate endpoint is
//     needed; the same endpoint detects the session.
//
// This design keeps api/posts/list.php completely unmodified
// while satisfying both admin and public gallery load needs.
//
// Response:
//   {success: true, data: [{id, image_url}, ...]}  (id ASC)
//   {success: false, message: "..."}  on error
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ---- Validate post_id ---------------------------------------------------
$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الإنجاز مطلوب.']);
    exit;
}

// ---- Determine caller context ------------------------------------------
// isAdminLoggedIn() is non-blocking (does NOT redirect/exit on failure).
// It starts the session only if one was already set by a previous request.
$isAdmin = isAdminLoggedIn();

// ---- Query project_images join posts --------------------------------
try {

    $pdo = getDB();

    if ($isAdmin) {

        // Admin: bypass status check; still enforce type + not-deleted.
        $stmt = $pdo->prepare(
            "SELECT ai.id, ai.image_url
               FROM project_images ai
               JOIN posts p ON p.id = ai.post_id
              WHERE ai.post_id = ?
                AND p.type = 'project'
                AND p.deleted_at IS NULL
              ORDER BY ai.id ASC"
        );

    } else {

        // Public: full visibility check.
        $stmt = $pdo->prepare(
            "SELECT ai.id, ai.image_url
               FROM project_images ai
               JOIN posts p ON p.id = ai.post_id
              WHERE ai.post_id = ?
                AND p.type = 'project'
                AND p.status = 'published'
                AND p.deleted_at IS NULL
              ORDER BY ai.id ASC"
        );

    }

    $stmt->execute([$postId]);
    $rows = $stmt->fetchAll();

    // If the post is not a valid/visible project the join returns 0 rows.
    // Distinguish "no images yet" from "post doesn't exist / not visible":
    if (empty($rows)) {

        // Verify the post actually exists and satisfies the visibility rule.
        if ($isAdmin) {
            $check = $pdo->prepare(
                "SELECT id FROM posts WHERE id = ? AND type = 'project' AND deleted_at IS NULL LIMIT 1"
            );
        } else {
            $check = $pdo->prepare(
                "SELECT id FROM posts WHERE id = ? AND type = 'project' AND status = 'published' AND deleted_at IS NULL LIMIT 1"
            );
        }
        $check->execute([$postId]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'الإنجاز غير موجود أو غير متاح.']);
            exit;
        }

        // Post exists but has no gallery images yet — valid, return empty.
        echo json_encode(['success' => true, 'data' => []]);
        exit;

    }

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (PDOException $e) {

    error_log('[project_images/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
