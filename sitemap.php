<?php
// ============================================================
// DYNAMIC XML SITEMAP [SEO-01]
// Served at /sitemap.xml via .htaccess rewrite.
//
// Includes only publicly indexable content:
//   - homepage
//   - articles.php / projects.php listing pages
//   - published, non-deleted posts:
//     - blog posts rendered at /post.php?id={id}
//     - projects rendered at /project.php?id={id}
//   - published store products rendered at /store/{slug}
//   - lab experiments rendered at /lab-detail.php?id={id}
//
// Excludes: drafts, hidden posts, soft-deleted posts, admin routes,
// API routes, and any duplicate URLs.
//
// This file performs a read-only SELECT. It never writes to the
// database and never exposes admin/API/private data.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';

const SITE_URL = 'https://mohammedalrashadi.com';

if (!function_exists('stripInvalidXmlChars')) {
    function stripInvalidXmlChars(string $str): string {
        return preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $str) ?? '';
    }
}

if (!function_exists('xmlEscape')) {
    /**
     * Escape a string for safe inclusion inside XML text/attribute content.
     */
    function xmlEscape(string $value): string {
        return htmlspecialchars(stripInvalidXmlChars($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

if (!function_exists('formatLastmod')) {
    /**
     * Format a MySQL DATETIME value as a W3C-formatted <lastmod> date.
     * Falls back to null if the value is missing/unparseable.
     */
    function formatLastmod(?string $datetime): ?string {
        if (empty($datetime)) {
            return null;
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}

/**
 * Resolve the canonical site base URL from site_settings with hard-coded fallback.
 */
function getSitemapSiteUrl(?PDO $pdo = null): string {
    $siteUrl = SITE_URL;
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
            if ($stmt && ($row = $stmt->fetch(PDO::FETCH_ASSOC)) && !empty($row['site_url'])) {
                $siteUrl = rtrim($row['site_url'], '/');
            }
        } catch (Throwable $e) { error_log('[sitemap.php:73] non-fatal, fallback used: ' . get_class($e)); }
    }
    return $siteUrl;
}

/**
 * Generate dynamic sitemap XML.
 */
function generateSitemap(?PDO $pdo = null, ?string $overrideSiteUrl = null): string {
    $siteUrl = $overrideSiteUrl ?? getSitemapSiteUrl($pdo);

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // ---- Static public pages --------------------------------------------------
    $staticPages = [
        ['path' => '/',              'file' => '/index.php',    'changefreq' => 'daily',   'priority' => '1.0'],
        ['path' => '/projects.php',  'file' => '/projects.php', 'changefreq' => 'weekly',  'priority' => '0.9'],
        ['path' => '/articles.php',  'file' => '/articles.php', 'changefreq' => 'weekly',  'priority' => '0.8'],
        ['path' => '/lab.php',       'file' => '/lab.php',      'changefreq' => 'weekly',  'priority' => '0.8'],
        ['path' => '/store',         'file' => '/store.php',    'changefreq' => 'weekly',  'priority' => '0.8'],
        ['path' => '/gallery.php',   'file' => '/gallery.php',  'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/journey.php',   'file' => '/journey.php',  'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/about.php',     'file' => '/about.php',    'changefreq' => 'monthly', 'priority' => '0.7'],
        ['path' => '/support.php',   'file' => '/support.php',  'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => '/privacy.php',   'file' => '/privacy.php',  'changefreq' => 'monthly', 'priority' => '0.5'],
        ['path' => '/terms.php',     'file' => '/terms.php',    'changefreq' => 'monthly', 'priority' => '0.5'],
    ];

    foreach ($staticPages as $sp) {
        $filePath = __DIR__ . $sp['file'];
        $lastmod = file_exists($filePath) ? date('Y-m-d', filemtime($filePath)) : null;
        $loc = ($sp['path'] === '/store') ? (SITE_URL . '/store') : ($siteUrl . $sp['path']);

        $xml .= "  <url>\n";
        $xml .= '    <loc>' . xmlEscape($loc) . "</loc>\n";
        if ($lastmod) $xml .= '    <lastmod>' . xmlEscape($lastmod) . "</lastmod>\n";
        $xml .= '    <changefreq>' . xmlEscape($sp['changefreq']) . "</changefreq>\n";
        $xml .= '    <priority>' . xmlEscape($sp['priority']) . "</priority>\n";
        $xml .= "  </url>\n";
    }

    // ---- Published, non-deleted posts (projects + articles) --------------------
    if (file_exists(__DIR__ . '/api/config.local.php')) {
        require_once __DIR__ . '/src/autoload.php';
        try {
            $postRepo = new \Domain\Content\PostRepository($pdo);
            $posts = $postRepo->getPostsForSitemap();
            
            foreach ($posts as $post) {
                $id   = (int) $post['id'];
                $type = $post['type'];

                $loc = '';
                if ($type === 'blog' && !empty($post['slug'])) {
                    $loc = $siteUrl . '/articles/' . rawurlencode($post['slug']);
                } else if (($type === 'project' || $type === 'project') && !empty($post['slug'])) {
                    $loc = $siteUrl . '/projects/' . rawurlencode($post['slug']);
                } else {
                    $route = ($type === 'project' || $type === 'project') ? '/project.php?id=' : '/post.php?id=';
                    $loc = $siteUrl . $route . $id;
                }
                $priority = ($type === 'project' || $type === 'project') ? '0.8' : '0.7';
                $lastmod = formatLastmod($post['updated_at'] ?? $post['created_at']) ?? date('Y-m-d');

                $xml .= "  <url>\n";
                $xml .= '    <loc>' . xmlEscape($loc) . "</loc>\n";
                if ($lastmod) $xml .= '    <lastmod>' . xmlEscape($lastmod) . "</lastmod>\n";
                $xml .= '    <changefreq>monthly</changefreq>' . "\n";
                $xml .= '    <priority>' . xmlEscape($priority) . "</priority>\n";
                $xml .= "  </url>\n";
            }

            // ---- Published Store Products (Store MVP) ----------------------------
            $prodStmt = $pdo->prepare(
                "SELECT slug, updated_at, created_at
                 FROM products
                 WHERE status = 'published'
                 ORDER BY id ASC"
            );
            $prodStmt->execute();

            while ($sp = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
                $prodSlug = trim($sp['slug'] ?? '');
                if (!empty($prodSlug)) {
                    $loc = $siteUrl . '/store/' . rawurlencode($prodSlug);
                    $lastmod = formatLastmod($sp['updated_at'] ?? $sp['created_at']) ?? date('Y-m-d');
                    
                    $xml .= "  <url>\n";
                    $xml .= '    <loc>' . xmlEscape($loc) . "</loc>\n";
                    if ($lastmod) $xml .= '    <lastmod>' . xmlEscape($lastmod) . "</lastmod>\n";
                    $xml .= '    <changefreq>weekly</changefreq>' . "\n";
                    $xml .= '    <priority>0.8</priority>' . "\n";
                    $xml .= "  </url>\n";
                }
            }

            // ---- Published Achievements (Phase 5) --------------------------------
            $deletedAtCheck = '';
            try {
                $pdo->query("SELECT deleted_at FROM achievements LIMIT 1");
                $deletedAtCheck = " AND deleted_at IS NULL";
            } catch (Throwable $e) { error_log('[sitemap.php:175] non-fatal, fallback used: ' . get_class($e)); }

            $achStmt = $pdo->prepare(
                "SELECT id, slug, updated_at, created_at
                 FROM achievements
                 WHERE status = 'published'{$deletedAtCheck}
                 ORDER BY id ASC"
            );
            $achStmt->execute();

            while ($ach = $achStmt->fetch(PDO::FETCH_ASSOC)) {
                $achId = (int)$ach['id'];
                // Some older achievements might not have a clean slug, fall back to id route
                if (!empty($ach['slug'])) {
                    $loc = $siteUrl . '/achievements/' . rawurlencode($ach['slug']);
                } else {
                    $loc = $siteUrl . '/achievement.php?id=' . $achId;
                }
                $lastmod = formatLastmod($ach['updated_at'] ?? $ach['created_at']) ?? date('Y-m-d');
                
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . xmlEscape($loc) . "</loc>\n";
                if ($lastmod) $xml .= '    <lastmod>' . xmlEscape($lastmod) . "</lastmod>\n";
                $xml .= '    <changefreq>monthly</changefreq>' . "\n";
                $xml .= '    <priority>0.7</priority>' . "\n";
                $xml .= "  </url>\n";
            }

        } catch (Throwable $e) {
            error_log('[sitemap.php] DB error: ' . $e->getMessage());
        }
    }

    // ---- Lab Experiments (from JSON) -----------------------------------------
    $experimentsFile = __DIR__ . '/api/data/lab_experiments.json';
    if (file_exists($experimentsFile)) {
        $expJson = json_decode(file_get_contents($experimentsFile), true);
        if (is_array($expJson)) {
            $expLastmod = date('Y-m-d', filemtime($experimentsFile));
            foreach ($expJson as $exp) {
                if (!empty($exp['id'])) {
                    $loc = $siteUrl . '/labs/' . urlencode($exp['id']);
                    
                    $xml .= "  <url>\n";
                    $xml .= '    <loc>' . xmlEscape($loc) . "</loc>\n";
                    if ($expLastmod) $xml .= '    <lastmod>' . xmlEscape($expLastmod) . "</lastmod>\n";
                    $xml .= '    <changefreq>monthly</changefreq>' . "\n";
                    $xml .= '    <priority>0.6</priority>' . "\n";
                    $xml .= "  </url>\n";
                }
            }
        }
    }

    $xml .= '</urlset>' . "\n";
    return $xml;
}

// ============================================================
// HTTP EXECUTION
// ============================================================
if (!defined('SITEMAP_TEST_MODE')) {
    $pdo = null;
    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        // Continue with null PDO
    }

    header('Content-Type: application/xml; charset=UTF-8');
    echo generateSitemap($pdo);
}
