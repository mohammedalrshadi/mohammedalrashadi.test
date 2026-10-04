<?php
// ============================================================
// POSTS — INLINE IMAGE CLEANUP HELPER (IMP-012 / ARCH-02)
// Safe detection, reference extraction, cross-post validation,
// and deletion of orphaned inline images in /uploads/.
//
// Rules:
//   - NEVER delete an image referenced by another post.
//   - Check posts.content, posts.image_url, and project_images.image_url.
//   - Respect soft-deleted posts (deleted_at IS NOT NULL) to preserve restore.
//   - Strictly guard filesystem deletions: within UPLOAD_DIR, no traversal,
//     allowed image extensions only.
//   - Dry-run mode by default for broad orphan detection.
// ============================================================

if (!defined('UPLOAD_DIR')) {
    require_once dirname(dirname(__DIR__)) . '/api/config.php';
}
require_once dirname(dirname(__DIR__)) . '/api/helpers/image_optimizer.php';

/**
 * Extracts normalized canonical /uploads/<filename> references from HTML.
 *
 * Uses DOMDocument for safe HTML parsing. Ignores external images,
 * malformed URLs, traversal attempts, and unsupported formats.
 *
 * @param  string|null $html
 * @return array<string> Unique list of '/uploads/<filename>'
 */
function extractInlineImageUrls(?string $html): array {
    if ($html === null || trim($html) === '') {
        return [];
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // Wrap in utf-8 HTML structure for DOMDocument
    $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
    $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $images = $doc->getElementsByTagName('img');
    $urls = [];

    foreach ($images as $img) {
        $src = trim($img->getAttribute('src'));
        if ($src === '') {
            continue;
        }

        // Extract path component
        $path = parse_url($src, PHP_URL_PATH);
        if (!$path) {
            continue;
        }

        // Path must strictly start with /uploads/ or uploads/
        if (!preg_match('#^/?uploads/([a-zA-Z0-9_\-.]+)$#', $path, $matches)) {
            continue;
        }

        $filename = $matches[1];
        // Enforce safe filename format and allowed image extensions
        if (preg_match('/^[a-zA-Z0-9_\-]+\.(?:jpe?g|png|gif|webp)$/i', $filename)) {
            $canonical = '/uploads/' . $filename;
            $urls[$canonical] = $canonical;
        }
    }

    return array_values($urls);
}

/**
 * Safely extracts and validates a clean filename from a URL or path.
 *
 * @param  string $urlOrPath
 * @return string|null Safe filename, or null if invalid or dangerous.
 */
function extractCleanFilename(string $urlOrPath): ?string {
    $path = parse_url(trim($urlOrPath), PHP_URL_PATH);
    if (!$path) {
        $path = trim($urlOrPath);
    }
    $basename = basename($path);
    if (preg_match('/^[a-zA-Z0-9_\-]+\.(?:jpe?g|png|gif|webp)$/i', $basename)) {
        return $basename;
    }
    return null;
}

/**
 * Checks whether an image filename is referenced in the database.
 *
 * Checks:
 *   1. posts.content (as an inline image in ANY post, including drafts and soft-deleted)
 *   2. posts.image_url (as a cover image)
 *   3. project_images.image_url (as a gallery image)
 *
 * @param  PDO      $pdo
 * @param  string   $reference     The filename or /uploads/... path
 * @param  int|null $excludePostId Optional post ID to exclude from content/cover checks
 * @return bool                    True if referenced anywhere, false if unreferenced.
 */
function isImageReferencedInDatabase(PDO $pdo, string $reference, ?int $excludePostId = null): bool {
    $filename = extractCleanFilename($reference);
    if (!$filename) {
        return false;
    }

    // 1. Check posts.content across all posts
    // Use SQL LIKE for initial candidate matching, then verify via DOM extractor
    $contentSql = 'SELECT id, content FROM posts WHERE content LIKE ?';
    $contentParams = ['%/uploads/' . $filename . '%'];

    if ($excludePostId !== null && $excludePostId > 0) {
        $contentSql .= ' AND id != ?';
        $contentParams[] = $excludePostId;
    }

    try {
        $stmt = $pdo->prepare($contentSql);
        $stmt->execute($contentParams);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $extracted = extractInlineImageUrls($row['content'] ?? '');
            if (in_array('/uploads/' . $filename, $extracted, true)) {
                return true;
            }
        }
    } catch (PDOException $e) {
        error_log('[image_cleanup] DB error querying posts.content: ' . $e->getMessage());
        return true; // Fail safe: prefer keep
    }

    // 2. Check posts.image_url (cover image)
    $coverSql = 'SELECT id FROM posts WHERE (image_url = ? OR image_url LIKE ?)';
    $coverParams = ['/uploads/' . $filename, '%/' . $filename];

    if ($excludePostId !== null && $excludePostId > 0) {
        $coverSql .= ' AND id != ?';
        $coverParams[] = $excludePostId;
    }
    $coverSql .= ' LIMIT 1';

    try {
        $stmt = $pdo->prepare($coverSql);
        $stmt->execute($coverParams);
        if ($stmt->fetch()) {
            return true;
        }
    } catch (PDOException $e) {
        error_log('[image_cleanup] DB error querying posts.image_url: ' . $e->getMessage());
        return true; // Fail safe
    }

    // 3. Check project_images.image_url
    try {
        $gallerySql = 'SELECT id FROM project_images WHERE image_url = ? OR image_url LIKE ? LIMIT 1';
        $stmt = $pdo->prepare($gallerySql);
        $stmt->execute(['/uploads/' . $filename, '%/' . $filename]);
        if ($stmt->fetch()) {
            return true;
        }
    } catch (PDOException $e) {
        // Table might not exist in older mock/test schemas; if so, ignore table missing error
        if (!str_contains($e->getMessage(), 'no such table') && !str_contains($e->getMessage(), "doesn't exist")) {
            error_log('[image_cleanup] DB error querying project_images: ' . $e->getMessage());
            return true; // Fail safe
        }
    }

    return false;
}

/**
 * Safely deletes a file and all its variants/siblings from UPLOAD_DIR with strict boundary checks.
 *
 * @param  string      $reference        Filename or /uploads/... path
 * @param  string|null $customUploadDir  Optional custom directory (for testing)
 * @return bool                          True if at least one file was deleted, false otherwise.
 */
function deleteUploadFileSafely(string $reference, ?string $customUploadDir = null): bool {
    $uploadDir = $customUploadDir ?? (defined('UPLOAD_DIR') ? UPLOAD_DIR : null);
    if (!$uploadDir) {
        error_log('[image_cleanup] deleteUploadFileSafely: UPLOAD_DIR not configured');
        return false;
    }

    $deleted = deleteImageWithVariants($reference, $uploadDir);
    return !empty($deleted);
}

/**
 * Cleans up orphaned inline images removed from a post.
 *
 * For each candidate URL:
 *   - Checks if still referenced in the database.
 *   - If referenced: preserves file.
 *   - If unreferenced: deletes file safely.
 *
 * @param  PDO         $pdo
 * @param  array       $candidateUrls    Array of candidate /uploads/... URLs
 * @param  int|null    $excludePostId    Optional post ID being updated/purged
 * @param  string|null $customUploadDir  Optional custom upload dir for testing
 * @return array                         ['deleted' => [...], 'preserved' => [...], 'skipped' => [...]]
 */
function cleanupOrphanedInlineImages(
    PDO $pdo,
    array $candidateUrls,
    ?int $excludePostId = null,
    ?string $customUploadDir = null
): array {
    $result = [
        'deleted'   => [],
        'preserved' => [],
        'skipped'   => [],
    ];

    $uniqueCandidates = array_unique(array_filter($candidateUrls));

    foreach ($uniqueCandidates as $url) {
        $filename = extractCleanFilename($url);
        if (!$filename) {
            $result['skipped'][] = $url;
            continue;
        }

        // Safety check: is it referenced anywhere else?
        if (isImageReferencedInDatabase($pdo, $filename, $excludePostId)) {
            $result['preserved'][] = $url;
            continue;
        }

        // Truly orphaned candidate: delete safely
        if (deleteUploadFileSafely($filename, $customUploadDir)) {
            $result['deleted'][] = $url;
        } else {
            $result['skipped'][] = $url;
        }
    }

    return $result;
}

/**
 * Reusable orphan detection utility.
 *
 * Scans UPLOAD_DIR, identifies files, and checks references.
 * Operates in DRY-RUN mode by default to prevent accidental deletions.
 *
 * @param  PDO         $pdo
 * @param  bool        $dryRun           True (default) to only report; false to delete
 * @param  string|null $customUploadDir  Optional custom directory for testing
 * @return array                         Diagnostic report
 */
function detectOrphanUploadFiles(
    PDO $pdo,
    bool $dryRun = true,
    ?string $customUploadDir = null
): array {
    $uploadDir = $customUploadDir ?? (defined('UPLOAD_DIR') ? UPLOAD_DIR : null);
    if (!$uploadDir || !is_dir($uploadDir)) {
        return [
            'mode'             => $dryRun ? 'dry_run' : 'execute',
            'scanned_count'    => 0,
            'referenced_count' => 0,
            'orphan_count'     => 0,
            'referenced'       => [],
            'orphans'          => [],
            'deleted'          => [],
            'errors'           => ['Upload directory does not exist or is not a directory.'],
        ];
    }

    $files = scandir($uploadDir);
    if ($files === false) {
        return [
            'mode'             => $dryRun ? 'dry_run' : 'execute',
            'scanned_count'    => 0,
            'referenced_count' => 0,
            'orphan_count'     => 0,
            'referenced'       => [],
            'orphans'          => [],
            'deleted'          => [],
            'errors'           => ['Failed to read upload directory.'],
        ];
    }

    $scanned = 0;
    $referenced = [];
    $orphans = [];
    $deleted = [];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $file;
        if (!is_file($filePath)) {
            continue;
        }

        $filename = extractCleanFilename($file);
        // Only inspect files that match valid upload image patterns
        if (!$filename) {
            continue;
        }

        $scanned++;
        if (isImageReferencedInDatabase($pdo, $filename)) {
            $referenced[] = '/uploads/' . $filename;
        } else {
            $orphans[] = '/uploads/' . $filename;
            if (!$dryRun) {
                if (deleteUploadFileSafely($filename, $uploadDir)) {
                    $deleted[] = '/uploads/' . $filename;
                }
            }
        }
    }

    return [
        'mode'             => $dryRun ? 'dry_run' : 'execute',
        'scanned_count'    => $scanned,
        'referenced_count' => count($referenced),
        'orphan_count'     => count($orphans),
        'referenced'       => $referenced,
        'orphans'          => $orphans,
        'deleted'          => $deleted,
        'errors'           => [],
    ];
}
