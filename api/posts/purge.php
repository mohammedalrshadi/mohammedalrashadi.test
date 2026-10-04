<?php
// ============================================================
// POSTS — PERMANENT PURGE  [V5 REQ-005]
// POST /api/posts/purge.php
// Requires: authenticated admin session + CSRF token
// Body (JSON): {"id": 123}
//
// Permanently and irreversibly deletes a soft-deleted post:
//   1. Fetches the post's image_url from the database.
//   2. Validates the image_url against a strict path pattern.
//   3. Deletes the cover image file from /uploads/ (if any).
//   4. [REQ-006] If the post is an project, fetches all
//      gallery image URLs from project_images and deletes
//      their physical files from /uploads/.
//   5. Deletes the database row with DELETE FROM.
//      ON DELETE CASCADE removes project_images rows.
//
// This is the ONLY endpoint that performs a real database
// DELETE and real filesystem @unlink(). It replaces the
// previous hard-delete behavior that was in delete.php.
//
// Security:
//   - requireAuth(): enforces active admin session
//   - requireCSRF(): prevents cross-site request forgery
//   - Only operates on soft-deleted posts (deleted_at IS NOT
//     NULL) — cannot purge live posts without deleting first
//   - image_url validated against #^/uploads/([a-zA-Z0-9_\-.]+)$#
//     before any filesystem operation — prevents path traversal
//
// [IMP-012] Inline Image Cleanup:
//   Inline images (<img src="/uploads/...">) embedded inside
//   posts.content are extracted and safely deleted ONLY when no
//   other post references them. Shared images are always preserved.
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';
require_once __DIR__ . '/image_cleanup.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المنشور مطلوب.']);
    exit;
}

/**
 * Deletes a physical file and all its variants from /uploads/ using strict boundary checks.
 * Logs on failure but does not abort — DB cleanup must continue.
 */
function _purgeFile(string $imageUrl, string $context): void {
    if (empty($imageUrl)) return;
    $deleted = deleteImageWithVariants($imageUrl);
    if (empty($deleted)) {
        // File may already be missing or url unsafe — log for diagnosis
        error_log('[posts/purge] ' . $context . ' No files deleted for image_url: ' . $imageUrl);
    }
}

try {

    $pdo = getDB();

    // Fetch the post — must exist AND be soft-deleted
    $stmt = $pdo->prepare(
        'SELECT id, type, image_url, content FROM posts WHERE id = ? AND deleted_at IS NOT NULL LIMIT 1'
    );
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'المنشور غير موجود في سلة المحذوفات.']);
        exit;
    }

    // [IMP-012] Extract inline images from post content before deletion
    $inlineImages = extractInlineImageUrls($post['content'] ?? '');

    // ---- Delete cover image file from /uploads/ (if present and not shared) -
    $coverImage = $post['image_url'] ?? '';
    if (!empty($coverImage)) {
        if (!isImageReferencedInDatabase($pdo, $coverImage, $id)) {
            _purgeFile($coverImage, 'cover image:');
        } else {
            error_log('[posts/purge] Preserving shared cover image: ' . $coverImage);
        }
    }

    // ---- [REQ-006] Delete gallery image files for projects ----------
    // Only projects can have gallery images. Blog posts skip this.
    // ON DELETE CASCADE will remove the project_images rows when
    // DELETE FROM posts executes below — no explicit DB delete needed.
    if ($post['type'] === 'project') {

        $galleryStmt = $pdo->prepare(
            'SELECT image_url FROM project_images WHERE post_id = ?'
        );
        $galleryStmt->execute([$id]);
        $galleryRows = $galleryStmt->fetchAll();

        foreach ($galleryRows as $galleryRow) {
            $galleryUrl = $galleryRow['image_url'] ?? '';
            if (!empty($galleryUrl)) {
                if (!isImageReferencedInDatabase($pdo, $galleryUrl, $id)) {
                    _purgeFile($galleryUrl, 'gallery image:');
                } else {
                    error_log('[posts/purge] Preserving shared gallery image: ' . $galleryUrl);
                }
            }
        }

    }

    // ---- Multi-table mutations inside a transaction --------------------
    $pdo->beginTransaction();

    // Clean up home showcase items referencing this post
    $stmtShowcase = $pdo->prepare(
        "DELETE FROM home_showcase_items 
         WHERE (item_type IN ('writing', 'project') AND (reference_id = ? OR post_id = ?))
            OR post_id = ?"
    );
    $stmtShowcase->execute([$id, $id, $id]);

    // ---- Disassociate any article reviews ------------------------------
    // Preserves reader reviews as global testimonials (post_id = NULL)
    // Works in tandem with the database-level ON DELETE SET NULL constraint.
    $stmtReviews = $pdo->prepare('UPDATE reviews SET post_id = NULL WHERE post_id = ?');
    $stmtReviews->execute([$id]);

    // ---- [DC-007] Remove user likes/bookmarks pointing at this post ------
    // likes/bookmarks store (content_type, content_id) with no FK to posts, so they
    // would be left orphaned. Only these two tables are cleaned; user_activities and
    // reading_history are kept as logs (the content resolver tolerates missing items).
    $interactionType = ($post['type'] === 'blog') ? 'writing' : (($post['type'] === 'project') ? 'project' : null);
    if ($interactionType !== null) {
        $pdo->prepare('DELETE FROM likes WHERE content_type = ? AND content_id = ?')
            ->execute([$interactionType, (string) $id]);
        $pdo->prepare('DELETE FROM bookmarks WHERE content_type = ? AND content_id = ?')
            ->execute([$interactionType, (string) $id]);
    }

    // ---- Permanently delete the database row ----------------------------
    // project_images rows for this post are removed automatically
    // by the ON DELETE CASCADE FK constraint.
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);

    $pdo->commit();

    logAdminAction('post.purge', 'post', (string) $id, json_encode([
        'action' => 'permanent_purge',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    // ---- [IMP-012] Clean up orphaned inline images ----------------------
    if (!empty($inlineImages)) {
        try {
            cleanupOrphanedInlineImages($pdo, $inlineImages, $id);
        } catch (Throwable $e) {
            error_log('[posts/purge] Error cleaning up orphaned inline images: ' . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'تم حذف المنشور نهائياً.',
    ]);

} catch (PDOException $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[posts/purge] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}

