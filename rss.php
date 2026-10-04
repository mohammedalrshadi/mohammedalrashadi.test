<?php
// ============================================================
// DYNAMIC RSS 2.0 FEED
// Served at /rss.xml via .htaccess rewrite.
//
// Compliant with RSS 2.0 and Atom 1.0 specifications.
// Validates on validator.w3.org/feed and opens cleanly in feed readers.
//
// Includes only published, non-deleted blog posts (type='blog').
// Excludes drafts, hidden posts, soft-deleted posts, and non-blog types.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/posts/sanitizer.php';

if (!function_exists('stripInvalidXmlChars')) {
    /**
     * Strip characters that are invalid in XML 1.0 Fourth/Fifth Edition documents.
     * Permitted: #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
     * All standard UTF-8 characters (Arabic, Latin, CJK, Emoji) are preserved.
     */
    function stripInvalidXmlChars(string $str): string {
        return preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $str) ?? '';
    }
}

if (!function_exists('xmlEscape')) {
    /**
     * Escape a string for safe inclusion in XML text nodes and attributes.
     */
    function xmlEscape(string $str): string {
        return htmlspecialchars(stripInvalidXmlChars($str), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

/**
 * Check if the posts table contains a published_at column (cached).
 */
function postsHasPublishedAt(PDO $pdo): bool {
    static $hasPublishedAt = null;
    if ($hasPublishedAt !== null) {
        return $hasPublishedAt;
    }
    try {
        $stmt = $pdo->query("SELECT published_at FROM posts LIMIT 0");
        $hasPublishedAt = ($stmt !== false);
    } catch (Throwable $e) {
        $hasPublishedAt = false;
    }
    return $hasPublishedAt;
}

/**
 * Resolve the canonical site base URL from site_settings with hard-coded fallback.
 */
function getFeedSiteUrl(?PDO $pdo = null): string {
    $siteUrl = 'https://mohammedalrashadi.com';
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
            if ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC)) && !empty($row['site_url'])) {
                $siteUrl = rtrim($row['site_url'], '/');
            }
        } catch (Throwable $e) { error_log('[rss.php:68] non-fatal, fallback used: ' . get_class($e)); }
    }
    return $siteUrl;
}

/**
 * Compute feed metadata (count & latest timestamp) cheaply for HTTP caching.
 *
 * @return array{count: int, last_modified_ts: int, etag: string}
 */
function getFeedCacheMetadata(PDO $pdo, string $siteUrl): array {
    $hasPublishedAt = postsHasPublishedAt($pdo);

    if ($hasPublishedAt) {
        $sql = "SELECT COUNT(*) AS total_count,
                       MAX(updated_at) AS max_updated,
                       MAX(COALESCE(published_at, created_at)) AS max_published
                FROM posts
                WHERE status = 'published'
                  AND deleted_at IS NULL
                  AND type = 'blog'";
    } else {
        $sql = "SELECT COUNT(*) AS total_count,
                       MAX(updated_at) AS max_updated,
                       MAX(created_at) AS max_created
                FROM posts
                WHERE status = 'published'
                  AND deleted_at IS NULL
                  AND type = 'blog'";
    }

    $count = 0;
    $lastModifiedTs = time();

    try {
        $stmt = $pdo->query($sql);
        if ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $count = (int)($row['total_count'] ?? 0);
            $maxTimeStr = $row['max_updated'] ?? ($row['max_published'] ?? ($row['max_created'] ?? null));
            if (!empty($maxTimeStr)) {
                $ts = strtotime($maxTimeStr);
                if ($ts !== false) {
                    $lastModifiedTs = $ts;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[rss.php] Cache metadata query error: ' . $e->getMessage());
    }

    $etag = '"' . md5($count . '-' . $lastModifiedTs . '-' . $siteUrl) . '"';

    return [
        'count'            => $count,
        'last_modified_ts' => $lastModifiedTs,
        'etag'             => $etag,
    ];
}

/**
 * Generate RSS 2.0 XML string.
 */
function generateRssFeed(?PDO $pdo = null, ?string $overrideSiteUrl = null): string {
    $siteUrl = $overrideSiteUrl ?? getFeedSiteUrl($pdo);

    $siteTitle = 'Mohammed Alrashadi';
    $siteDescription = 'Systems, databases, computing fundamentals, and backend software engineering projects and research by Mohammed Alrashadi.';
    $language = 'en';

    $posts = [];
    $lastBuildTs = time();

    if ($pdo !== null) {
        try {
            // Read channel settings if present
            $setStmt = $pdo->query("SELECT site_name, site_description, default_language, default_seo_title, default_meta_description FROM site_settings LIMIT 1");
            if ($setStmt && ($sRow = $setStmt->fetch(PDO::FETCH_ASSOC))) {
                if (!empty($sRow['site_name'])) $siteTitle = $sRow['site_name'];
                if (!empty($sRow['default_seo_title'])) $siteTitle = $sRow['default_seo_title'];
                if (!empty($sRow['default_meta_description'])) $siteDescription = $sRow['default_meta_description'];
                elseif (!empty($sRow['site_description'])) $siteDescription = $sRow['site_description'];
                if (!empty($sRow['default_language'])) $language = $sRow['default_language'];
            }

            $hasPublishedAt = postsHasPublishedAt($pdo);
            $dateCol = $hasPublishedAt ? 'COALESCE(published_at, created_at)' : 'created_at';
            $selectCols = $hasPublishedAt
                ? 'id, title, category, content, meta_description, published_at, created_at, updated_at'
                : 'id, title, category, content, meta_description, created_at, updated_at';

            $stmt = $pdo->prepare(
                "SELECT {$selectCols}
                 FROM posts
                 WHERE status = 'published'
                   AND deleted_at IS NULL
                   AND type = 'blog'
                 ORDER BY {$dateCol} DESC, id DESC
                 LIMIT 20"
            );
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute lastBuildDate from most recent post timestamp
            if (!empty($posts)) {
                $newest = $posts[0];
                $newestDate = $newest['updated_at'] ?? ($newest['published_at'] ?? ($newest['created_at'] ?? null));
                if (!empty($newestDate)) {
                    $ts = strtotime($newestDate);
                    if ($ts !== false) {
                        $lastBuildTs = $ts;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[rss.php] DB query error: ' . $e->getMessage());
        }
    }

    $lastBuildDate = date(DATE_RSS, $lastBuildTs);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
    $xml .= "  <channel>\n";
    $xml .= '    <title>' . xmlEscape($siteTitle) . "</title>\n";
    $xml .= '    <link>' . xmlEscape($siteUrl . '/') . "</link>\n";
    $xml .= '    <description>' . xmlEscape($siteDescription) . "</description>\n";
    $xml .= '    <language>' . xmlEscape($language) . "</language>\n";
    $xml .= '    <lastBuildDate>' . xmlEscape($lastBuildDate) . "</lastBuildDate>\n";
    $xml .= '    <atom:link href="' . xmlEscape($siteUrl . '/rss.xml') . '" rel="self" type="application/rss+xml" />' . "\n";

    foreach ($posts as $post) {
        $id = (int)$post['id'];
        $postUrl = $siteUrl . '/post.php?id=' . $id;

        // pubDate in RFC 822 (use published_at if exists, otherwise created_at)
        $dateRaw = (!empty($post['published_at'])) ? $post['published_at'] : ($post['created_at'] ?? '');
        $postTs = !empty($dateRaw) ? strtotime($dateRaw) : false;
        $pubDate = ($postTs !== false) ? date(DATE_RSS, $postTs) : date(DATE_RSS);

        // Description: short plain-text excerpt (meta_description or first ~200 chars of stripped content)
        $metaDesc = trim($post['meta_description'] ?? '');
        if ($metaDesc !== '') {
            $excerpt = preg_replace('/\s+/', ' ', $metaDesc);
        } else {
            $stripped = trim(preg_replace('/\s+/', ' ', strip_tags($post['content'] ?? '')));
            if (mb_strlen($stripped, 'UTF-8') > 200) {
                $excerpt = mb_substr($stripped, 0, 197, 'UTF-8') . '...';
            } else {
                $excerpt = $stripped;
            }
        }

        // content:encoded: sanitized full HTML (reuse ArticleHtmlSanitizer)
        $cleanHtml = ArticleHtmlSanitizer::sanitize($post['content'] ?? '');

        // Convert root-relative URLs to absolute for external feed readers
        $cleanHtml = preg_replace_callback('/(src|href)=([\'"])(\/(?!\/)[^\'"]*)\2/i', function ($matches) use ($siteUrl) {
            return $matches[1] . '=' . $matches[2] . $siteUrl . $matches[3] . $matches[2];
        }, $cleanHtml);

        // Strip invalid XML control characters
        $cleanHtml = stripInvalidXmlChars($cleanHtml);

        // Escape CDATA termination sequence "]]>" -> "]]]]><![CDATA[>"
        $cdataContent = str_replace(']]>', ']]]]><![CDATA[>', $cleanHtml);

        $xml .= "    <item>\n";
        $xml .= '      <title>' . xmlEscape($post['title'] ?? '') . "</title>\n";
        $xml .= '      <link>' . xmlEscape($postUrl) . "</link>\n";
        $xml .= '      <guid isPermaLink="true">' . xmlEscape($postUrl) . "</guid>\n";
        $xml .= '      <pubDate>' . xmlEscape($pubDate) . "</pubDate>\n";

        $category = trim($post['category'] ?? '');
        if ($category !== '') {
            $xml .= '      <category>' . xmlEscape($category) . "</category>\n";
        }

        $xml .= '      <description>' . xmlEscape($excerpt) . "</description>\n";
        $xml .= '      <content:encoded><![CDATA[' . $cdataContent . "]]></content:encoded>\n";
        $xml .= "    </item>\n";
    }

    $xml .= "  </channel>\n";
    $xml .= "</rss>\n";

    return $xml;
}

// ============================================================
// HTTP EXECUTION & 304 CACHE HANDLING
// ============================================================
if (!defined('RSS_TEST_MODE')) {
    $pdo = null;
    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        // Continue with null PDO; will output valid empty channel
    }

    $siteUrl = getFeedSiteUrl($pdo);

    if ($pdo !== null) {
        // Step 1: Compute ETag & Last-Modified from cheap query
        $cache = getFeedCacheMetadata($pdo, $siteUrl);
        $etag = $cache['etag'];
        $lastModifiedTs = $cache['last_modified_ts'];

        // Step 2: Answer 304 BEFORE building the feed
        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime(trim($_SERVER['HTTP_IF_MODIFIED_SINCE'])) : false;

        $isNotModified = false;
        if ($ifNoneMatch !== '') {
            $clientTag = trim($ifNoneMatch);
            if ($clientTag === $etag || $clientTag === 'W/' . $etag || trim($clientTag, '"') === trim($etag, '"') || $clientTag === '*') {
                $isNotModified = true;
            }
        } elseif ($ifModifiedSince !== false && $lastModifiedTs > 0) {
            if ($ifModifiedSince >= $lastModifiedTs) {
                $isNotModified = true;
            }
        }

        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=600');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $lastModifiedTs));

        if ($isNotModified) {
            http_response_code(304);
            exit;
        }
    } else {
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=600');
    }

    // Step 3: Build and emit the full feed
    echo generateRssFeed($pdo, $siteUrl);
}
