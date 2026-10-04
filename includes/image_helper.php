<?php
// ============================================================
// RESPONSIVE IMAGE HELPER
// includes/image_helper.php
//
// Renders high-performance, modern responsive <img> elements:
//   - WebP variants with srcset (480w, 960w) and media queries
//   - Automatic CLS prevention with width & height detection
//   - Native lazy loading (loading="lazy", decoding="async")
//   - LCP hero support with fetchpriority="high" and loading="eager"
// ============================================================

/**
 * Returns variant URL if it exists on disk, or null.
 *
 * @param  string $url          e.g., /uploads/image.png
 * @param  int    $variantWidth e.g., 480 or 960
 * @return string|null
 */
function getVariantUrl(string $url, int $variantWidth): ?string {
    if (!preg_match('#^/uploads/([a-zA-Z0-9_\-]+)\.(?:jpe?g|png|gif|webp)$#i', $url, $m)) {
        return null;
    }

    $base = $m[1];
    $variantFile = $base . '-' . $variantWidth . '.webp';
    $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : (dirname(__DIR__) . '/uploads/');
    $fullPath = $uploadDir . $variantFile;

    if (is_file($fullPath)) {
        $prefix = defined('UPLOAD_URL_PATH') ? UPLOAD_URL_PATH : '/uploads/';
        return $prefix . $variantFile;
    }

    return null;
}

/**
 * Renders an optimized responsive <img> HTML tag.
 *
 * Options:
 *   - 'class': CSS classes (string)
 *   - 'id': ID attribute (string)
 *   - 'style': Inline CSS (string)
 *   - 'sizes': Responsive sizes attribute (default: '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 960px')
 *   - 'width': Explicit width attribute (int|string)
 *   - 'height': Explicit height attribute (int|string)
 *   - 'priority': bool - if true, renders loading="eager" fetchpriority="high" (for LCP/hero images)
 *   - 'fetchpriority': 'high'|'low'|'auto'
 *   - 'loading': 'lazy'|'eager' (defaults to 'lazy' unless priority is true)
 *   - 'attr': array of key-value attributes (e.g. ['data-zoom' => 'true'])
 *
 * @param  string $url  Image URL (e.g. /uploads/photo.webp, /assets/img.png)
 * @param  string $alt  Accessible alternative description
 * @param  array  $opts Configuration options
 * @return string HTML <img> tag
 */
function responsiveImage(string $url, string $alt = '', array $opts = []): string {
    static $dimensionsCache = [];

    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $altEscaped = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
    $srcEscaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    // Priority and loading strategy
    $isPriority = !empty($opts['priority']) || (($opts['fetchpriority'] ?? '') === 'high');
    $loading = $isPriority ? 'eager' : ($opts['loading'] ?? 'lazy');
    $decoding = $opts['decoding'] ?? 'async';
    $fetchPriority = $isPriority ? 'high' : ($opts['fetchpriority'] ?? null);
    $width  = isset($opts['width'])  && $opts['width']  !== '' ? $opts['width']  : null;
    $height = isset($opts['height']) && $opts['height'] !== '' ? $opts['height'] : null;

    // Responsive srcset construction for /uploads/ images
    $srcsetEntries = [];
    $isUpload = (str_starts_with($url, '/uploads/') || str_starts_with($url, 'uploads/'));

    if ($isUpload && preg_match('#^/?uploads/(.+?)\.(?:jpe?g|png|gif|webp)$#i', $url, $m)) {
        $base = $m[1];
        $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : (dirname(__DIR__) . '/uploads/');
        $urlPrefix = defined('UPLOAD_URL_PATH') ? UPLOAD_URL_PATH : '/uploads/';

        // Check 480w
        $v480Path = $uploadDir . $base . '-480.webp';
        if (is_file($v480Path)) {
            $srcsetEntries[] = $urlPrefix . $base . '-480.webp 480w';
        }

        // Check 960w
        $v960Path = $uploadDir . $base . '-960.webp';
        if (is_file($v960Path)) {
            $srcsetEntries[] = $urlPrefix . $base . '-960.webp 960w';
        }

        // If main url is WebP or sibling WebP exists
        $mainWebpPath = $uploadDir . $base . '.webp';
        if (is_file($mainWebpPath)) {
            $srcsetEntries[] = $urlPrefix . $base . '.webp 1920w';
        }
    }

    $fileMissing = false;
    if ($isUpload) {
        $localFile = dirname(__DIR__) . '/' . ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        if (!is_file($localFile)) {
            $fileMissing = true;
            $dimensionsCache[$url] = null;
        } else {
            if (!isset($dimensionsCache[$url])) {
                $info = @getimagesize($localFile);
                if ($info && !empty($info[0]) && !empty($info[1])) {
                    $dimensionsCache[$url] = [(int)$info[0], (int)$info[1]];
                } else {
                    $dimensionsCache[$url] = null;
                }
            }
        }
        
        if (!empty($dimensionsCache[$url])) {
            $width  = $width  ?? $dimensionsCache[$url][0];
            $height = $height ?? $dimensionsCache[$url][1];
        }
    }

    if ($fileMissing) {
        $text = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $alt), 0, 3));
        if (!$text) $text = 'IMG';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600">
            <rect width="800" height="600" fill="#121212" stroke="#2d2d2d" stroke-width="4"/>
            <path d="M0,0 L800,600 M800,0 L0,600" stroke="#1f1f1f" stroke-width="2"/>
            <rect x="300" y="260" width="200" height="80" fill="#121212" stroke="#2d2d2d" stroke-width="2"/>
            <text x="400" y="300" fill="#666666" font-family="monospace" font-size="24" font-weight="bold" text-anchor="middle" dominant-baseline="middle">' . htmlspecialchars($text) . '</text>
        </svg>';
        $srcEscaped = 'data:image/svg+xml;base64,' . base64_encode($svg);
        $srcsetEntries = [];
        $width = $width ?? 800;
        $height = $height ?? 600;
        $altEscaped = 'Missing image placeholder';
    }

    // Build attributes
    $attrs = [];
    $attrs[] = 'src="' . $srcEscaped . '"';
    $attrs[] = 'alt="' . $altEscaped . '"';

    if (!empty($srcsetEntries)) {
        $attrs[] = 'srcset="' . htmlspecialchars(implode(', ', $srcsetEntries), ENT_QUOTES, 'UTF-8') . '"';
        $sizes = $opts['sizes'] ?? '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 960px';
        $attrs[] = 'sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"';
    }

    if ($width !== null) {
        $attrs[] = 'width="' . (int)$width . '"';
    }
    if ($height !== null) {
        $attrs[] = 'height="' . (int)$height . '"';
    }

    $attrs[] = 'loading="' . htmlspecialchars($loading, ENT_QUOTES, 'UTF-8') . '"';
    $attrs[] = 'decoding="' . htmlspecialchars($decoding, ENT_QUOTES, 'UTF-8') . '"';

    if ($fetchPriority !== null) {
        $attrs[] = 'fetchpriority="' . htmlspecialchars($fetchPriority, ENT_QUOTES, 'UTF-8') . '"';
    }

    if (!empty($opts['class'])) {
        $attrs[] = 'class="' . htmlspecialchars($opts['class'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if (!empty($opts['id'])) {
        $attrs[] = 'id="' . htmlspecialchars($opts['id'], ENT_QUOTES, 'UTF-8') . '"';
    }
    if (!empty($opts['style'])) {
        $attrs[] = 'style="' . htmlspecialchars($opts['style'], ENT_QUOTES, 'UTF-8') . '"';
    }

    if (!empty($opts['attr']) && is_array($opts['attr'])) {
        foreach ($opts['attr'] as $k => $v) {
            $attrs[] = htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }
    }

    return '<img ' . implode(' ', $attrs) . ' />';
}

