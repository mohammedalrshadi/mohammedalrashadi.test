<?php
// ============================================================
// POSTS — UPDATE
// POST /api/posts/update.php
// Requires: authenticated session
// Body (JSON): {id, title, category, content, type, status, image_url, created_at}
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(__DIR__) . '/categories/helper.php';
require_once __DIR__ . '/helper.php';
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

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

// ---- Extract & validate -------------------------------------------------
$id = isset($input['id']) ? (int) $input['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المنشور مطلوب.']);
    exit;
}

// [REQ-012 Strict Status Separation] Normal update must NEVER modify post status.
// Explicitly reject any status field with HTTP 400.
if (array_key_exists('status', $input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'لا يمكن تعديل حالة المنشور عبر هذا الرابط. استخدم إجراء النشر المخصص.',
    ]);
    exit;
}

try {
    $pdo = getDB();

    // Build update set dynamically — only update fields that are provided
    $fields = [];
    $params = [];

    if (isset($input['title'])) {
        $title = trim($input['title']);
        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'العنوان لا يمكن أن يكون فارغاً.']);
            exit;
        }
        $fields[] = 'title = ?';
        $params[]  = $title;
        // SEO Architecture: Slug is immutable by default during title updates.
    }
    
    // Explicit slug change logic (if the CMS sends it)
    $explicitSlugChange = false;
    $oldSlugForRedirect = '';
    $newSlugForRedirect = '';
    
    if (isset($input['slug'])) {
        $newSlug = trim($input['slug']);
        if (!empty($newSlug)) {
            $stmtSlug = $pdo->prepare('SELECT slug, type FROM posts WHERE id = ?');
            $stmtSlug->execute([$id]);
            $postInfo = $stmtSlug->fetch();
            
            if ($postInfo && $postInfo['slug'] !== $newSlug) {
                // Ensure new slug is unique
                $stmtCheck = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
                $stmtCheck->execute([$newSlug, $id]);
                if ($stmtCheck->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'هذا الرابط مستخدم بالفعل.']);
                    exit;
                }
                
                $fields[] = 'slug = ?';
                $params[] = $newSlug;
                $explicitSlugChange = true;
                $oldSlugForRedirect = $postInfo['slug'];
                $newSlugForRedirect = $newSlug;
                
                // Determine base path for the redirect based on type
                $basePath = ($postInfo['type'] === 'project' || $postInfo['type'] === 'project') ? '/projects/' : '/articles/';
                $oldSlugForRedirect = $basePath . $oldSlugForRedirect;
                $newSlugForRedirect = $basePath . $newSlugForRedirect;
            }
        }
    }

    $targetType = null;
    if (isset($input['type'])) {
        $type = trim($input['type']);
        if (!in_array($type, ['blog', 'project', 'project'], true)) {
            $type = 'blog';
        }
        $fields[] = 'type = ?';
        $params[]  = $type;
        $targetType = $type;
    }

    if (isset($input['category'])) {
        $cleanCategory = normalizeCategoryWhitespace((string) $input['category']);
        if ($cleanCategory === '') {
            $fields[] = 'category = ?';
            $params[]  = '';
        } else {
            if ($targetType === null) {
                $stmtType = $pdo->prepare('SELECT type FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1');
                $stmtType->execute([$id]);
                $postRow = $stmtType->fetch();
                if (!$postRow) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'المنشور غير موجود.']);
                    exit;
                }
                $targetType = $postRow['type'];
            }

            try {
                $categoryName = resolvePostCategory($pdo, $cleanCategory, $targetType);
                $fields[] = 'category = ?';
                $params[]  = $categoryName;
            } catch (InvalidArgumentException $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Category error: ' . $e->getMessage(),
                ]);
                exit;
            }
        } // end else (cleanCategory not empty)
    } // end if (isset($input['category']))

    $oldContent = null;
    if (isset($input['content'])) {
        // [IMP-012] Retrieve old content before update to detect removed images
        $stmtOld = $pdo->prepare('SELECT content FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmtOld->execute([$id]);
        $oldRow = $stmtOld->fetch();
        if (!$oldRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'المنشور غير موجود.']);
            exit;
        }
        $oldContent = (string) $oldRow['content'];

        try {
            $cleanContent = sanitizeAndValidatePostContent((string) $input['content']);
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $fields[] = 'content = ?';
        $params[]  = $cleanContent;
    }

    if (array_key_exists('image_url', $input)) {
        $imageUrl = trim($input['image_url']);
        // Accept empty string (clearing the image) or valid relative/absolute URL
        if ($imageUrl !== '' &&
            !preg_match('#^/uploads/[a-zA-Z0-9_\-.]+$#', $imageUrl) &&
            !filter_var($imageUrl, FILTER_VALIDATE_URL)
        ) {
            $imageUrl = null;
        }
        $fields[] = 'image_url = ?';
        $params[]  = ($imageUrl === '') ? null : $imageUrl;
    }

    if (array_key_exists('quote_ar', $input)) {
        $quoteAr = is_string($input['quote_ar']) ? trim($input['quote_ar']) : '';
        $fields[] = 'quote_ar = ?';
        $params[]  = ($quoteAr === '') ? null : $quoteAr;
    }

    if (array_key_exists('quote_en', $input)) {
        $quoteEn = is_string($input['quote_en']) ? trim($input['quote_en']) : '';
        $fields[] = 'quote_en = ?';
        $params[]  = ($quoteEn === '') ? null : $quoteEn;
    }

    if (isset($input['created_at'])) {
        $parsed = date('Y-m-d H:i:s', strtotime($input['created_at']));
        if ($parsed !== '1970-01-01 00:00:00') {
            $fields[] = 'created_at = ?';
            $params[]  = $parsed;
        }
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'لا توجد بيانات للتحديث.']);
        exit;
    }

    $params[] = $id; // for WHERE clause

    // ---- Execute UPDATE -----------------------------------------------------
    // [REQ-005] Only update non-deleted posts. Soft-deleted posts must be
    // restored via api/posts/restore.php before they can be edited.
    $sql  = 'UPDATE posts SET ' . implode(', ', $fields) . ' WHERE id = ? AND deleted_at IS NULL';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($explicitSlugChange) {
        // Record 301 redirect
        $stmtRedirect = $pdo->prepare('INSERT INTO url_redirects (source_path, destination_path, status_code) VALUES (?, ?, 301) ON DUPLICATE KEY UPDATE destination_path = VALUES(destination_path), updated_at = NOW()');
        $stmtRedirect->execute([$oldSlugForRedirect, $newSlugForRedirect]);
    }

    if ($stmt->rowCount() === 0) {
        // In MySQL, an UPDATE with unchanged data may return rowCount == 0. Verify if post exists.
        $stmtCheck = $pdo->prepare('SELECT id FROM posts WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmtCheck->execute([$id]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'المنشور غير موجود.']);
            exit;
        }
    }

    // [IMP-012] Clean up orphaned inline images removed from content
    if ($oldContent !== null && isset($cleanContent)) {
        try {
            $oldImages = extractInlineImageUrls($oldContent);
            $newImages = extractInlineImageUrls($cleanContent);
            $removedImages = array_diff($oldImages, $newImages);

            if (!empty($removedImages)) {
                cleanupOrphanedInlineImages($pdo, $removedImages, $id);
            }
        } catch (Throwable $e) {
            error_log('[posts/update] Error cleaning up orphaned images: ' . $e->getMessage());
        }
    }

    logAdminAction('post.update', 'post', (string) $id, json_encode([
        'updated_fields' => array_keys($input),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث المنشور بنجاح.',
    ]);

} catch (PDOException $e) {

    error_log('[posts/update] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);

}
