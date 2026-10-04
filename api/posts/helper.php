<?php
// ============================================================
// POSTS — HELPER FUNCTIONS (REQ-012)
// api/posts/helper.php
//
// Common validation and normalization helpers for posts:
//   - isSubstantivelyEmptyHtml: validates rich text content
//   - validateCreateStatus: validates status on create
// ============================================================

require_once __DIR__ . '/sanitizer.php';

/**
 * Checks if HTML content is substantively empty (e.g., whitespace,
 * empty tags, <p><br></p>, <div><br></div>, non-breaking spaces).
 *
 * Media tags such as <img>, <video>, <iframe>, <table>, <svg>, <hr>
 * are treated as substantive content.
 *
 * @param string|null $html
 * @return bool True if substantively empty, false if meaningful content exists.
 */
function isSubstantivelyEmptyHtml(?string $html): bool {
    if ($html === null) {
        return true;
    }

    $trimmed = trim($html);
    if ($trimmed === '') {
        return true;
    }

    // Check for substantive embedded media or structure
    if (preg_match('/<(img|video|iframe|table|svg|audio|object|embed|hr)\b/i', $trimmed)) {
        return false;
    }

    // Strip HTML tags to inspect inner text
    $text = strip_tags($trimmed);

    // Decode HTML entities (e.g. &nbsp;, &zwnj;)
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Strip non-breaking spaces and zero-width spaces
    $text = str_replace(["\xc2\xa0", "\xe2\x80\x8b", "\u{00A0}", "\u{200B}"], ' ', $text);

    // Normalize multiple whitespace characters
    $text = trim(preg_replace('/\s+/u', ' ', $text));

    return $text === '';
}

/**
 * Validates post status for creation (create.php).
 *
 * Rules:
 *   - Status omitted / empty string / null -> defaults to 'draft'
 *   - Status in ('draft', 'published', 'hidden') -> valid, returns the status
 *   - Any other value (e.g. 'banana') -> invalid, returns null (triggers HTTP 400)
 *
 * @param string|null $rawStatus
 * @return string|null Validated status or null if invalid.
 */
function validateCreateStatus(?string $rawStatus): ?string {
    if ($rawStatus === null || $rawStatus === '') {
        return 'draft';
    }

    $status = trim($rawStatus);
    if (in_array($status, ['draft', 'published', 'hidden'], true)) {
        return $status;
    }

    return null;
}

/**
 * Authoritative article body renderer.
 * Safely converts sanitized HTML/Markdown content into properly structured HTML.
 * Handles:
 *   - Server-side sanitization via ArticleHtmlSanitizer
 *   - Fenced code blocks with language badge and copy-code widget
 *   - Markdown headings (### and ##)
 *   - Native HTML block elements without invalid re-wrapping
 *   - Text paragraphs wrapped cleanly in styled <p> elements with nl2br
 *
 * @param string $rawContent
 * @return string Rendered HTML safe for display
 */
function renderArticleContent(string $rawContent): string {
    if (trim($rawContent) === '') {
        return '';
    }

    // 1. Authoritative server-side HTML sanitization (strips script, iframe, onclick, etc., preserves safe formatting)
    $clean = ArticleHtmlSanitizer::sanitize($rawContent);

    // 2. Extract and preserve fenced code blocks before any other processing
    $codeBlocks = [];
    $clean = preg_replace_callback('/```([a-zA-Z0-9_-]*)\r?\n([\s\S]*?)\r?\n```/', function ($m) use (&$codeBlocks) {
        $idx = count($codeBlocks);
        $lang = htmlspecialchars(trim($m[1]) ?: 'CODE', ENT_QUOTES, 'UTF-8');
        $rawCode = $m[2];
        $codeEscaped = htmlspecialchars($rawCode, ENT_QUOTES, 'UTF-8');
        $copyEscaped = htmlspecialchars($rawCode, ENT_QUOTES, 'UTF-8');

        $html = '<div class="my-space-md rounded-lg overflow-hidden bg-surface-container-lowest border border-outline-variant/20 shadow-md">'
              . '<div class="px-space-md py-1.5 bg-surface-container flex items-center justify-between font-label-code text-label-micro text-outline">'
              . '<span>' . $lang . '</span>'
              . '<button type="button" class="copy-code-btn hover:text-primary transition-colors flex items-center gap-1" data-code="' . $copyEscaped . '">'
              . '<span class="material-symbols-outlined text-[14px]">content_copy</span> Copy'
              . '</button>'
              . '</div>'
              . '<pre class="p-space-md overflow-x-auto font-label-code text-[13px] leading-relaxed text-on-surface"><code>' . $codeEscaped . '</code></pre>'
              . '</div>';

        $codeBlocks[$idx] = $html;
        return "\n\n<!--__CODE_BLOCK_{$idx}__-->\n\n";
    }, $clean);

    // 3. Process markdown headings (### Heading and ## Heading) that are outside code blocks
    $clean = preg_replace_callback('/^(#{2,3})\s+(.+)$/m', function ($m) {
        $level = strlen($m[1]);
        $tag = $level === 2 ? 'h2' : 'h3';
        $headingText = trim($m[2]);
        return '<' . $tag . ' class="font-headline-md text-headline-md text-on-surface font-semibold tracking-tight mt-space-lg pt-space-xs">' . $headingText . '</' . $tag . '>';
    }, $clean);

    // 4. Split by double newlines if present
    $hasDoubleNewline = strpos($clean, "\n\n") !== false;
    $chunks = $hasDoubleNewline ? explode("\n\n", $clean) : [$clean];

    $rendered = '';
    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') continue;

        // If chunk is a code block placeholder, output it directly
        if (preg_match('/<!--__CODE_BLOCK_(\d+)__-->/', $chunk, $cm)) {
            $idx = (int)$cm[1];
            $rendered .= ($codeBlocks[$idx] ?? '') . "\n";
            continue;
        }

        // Check if chunk starts with an HTML block element (already formatted HTML)
        if (preg_match('/^<\s*(p|h[1-6]|ul|ol|blockquote|pre|div|table|figure|hr|section|article)\b/i', $chunk)) {
            $rendered .= $chunk . "\n";
        } else {
            // Plain text or inline-only markup chunk: wrap in paragraph tag
            $rendered .= '<p class="text-on-surface-variant leading-relaxed">' . nl2br($chunk) . '</p>' . "\n";
        }
    }

    // 5. Restore any code blocks that might be embedded inside a chunk
    foreach ($codeBlocks as $idx => $html) {
        $placeholder = "<!--__CODE_BLOCK_{$idx}__-->";
        if (strpos($rendered, $placeholder) !== false) {
            $rendered = str_replace($placeholder, $html, $rendered);
        }
    }

    // 6. Enhance inline <img> tags with lazy loading, async decoding, and layout dimensions
    $rendered = preg_replace_callback('/<img\b([^>]*)>/i', function ($m) {
        $attrs = $m[1];

        // Add loading="lazy" if not present
        if (!preg_match('/\bloading\s*=/i', $attrs)) {
            $attrs .= ' loading="lazy"';
        }

        // Add decoding="async" if not present
        if (!preg_match('/\bdecoding\s*=/i', $attrs)) {
            $attrs .= ' decoding="async"';
        }

        // If width or height are missing, attempt to inspect local /uploads/ image
        if (!preg_match('/\bwidth\s*=/i', $attrs) || !preg_match('/\bheight\s*=/i', $attrs)) {
            if (preg_match('/src=([\'"])(.*?)\1/i', $attrs, $srcMatch)) {
                $src = $srcMatch[2];
                if (preg_match('#^/?uploads/([a-zA-Z0-9_\-.]+)$#', $src, $fnMatch)) {
                    $localPath = (defined('UPLOAD_DIR') ? UPLOAD_DIR : (dirname(dirname(__DIR__)) . '/uploads/')) . $fnMatch[1];
                    if (is_file($localPath)) {
                        $size = @getimagesize($localPath);
                        if ($size && !empty($size[0]) && !empty($size[1])) {
                            if (!preg_match('/\bwidth\s*=/i', $attrs)) {
                                $attrs .= ' width="' . (int)$size[0] . '"';
                            }
                            if (!preg_match('/\bheight\s*=/i', $attrs)) {
                                $attrs .= ' height="' . (int)$size[1] . '"';
                            }
                        }
                    }
                }
            }
        }

        return '<img' . $attrs . '>';
    }, $rendered);

    return $rendered;
}

/**
 * Generates a unique, URL-safe slug from a title.
 */
function generatePostSlug(PDO $pdo, string $title, int $excludeId = 0): string {
    $slug = trim(mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '-', $title), 'UTF-8'), '-');
    if (empty($slug)) $slug = 'post-' . time();
    $original = $slug;
    $count = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $excludeId]);
        if (!$stmt->fetch()) break;
        $slug = $original . '-' . $count;
        $count++;
    }
    return $slug;
}

/**
 * Resolves a category name by matching it or creating it.
 * 
 * @throws InvalidArgumentException if category creation fails.
 */
function resolvePostCategory(PDO $pdo, string $categoryName, string $postType): string {
    $cleanCategory = normalizeCategoryWhitespace($categoryName);
    if ($cleanCategory === '') {
        return '';
    }
    $matchedCat = findCategoryMatch($pdo, $cleanCategory, $postType);
    if (!$matchedCat) {
        $catRecord = findOrCreateCategory($pdo, $cleanCategory, $postType);
        return $catRecord['name'];
    }
    return $matchedCat['name'];
}

/**
 * Sanitizes and validates post content.
 * 
 * @throws InvalidArgumentException if content is substantively empty.
 */
function sanitizeAndValidatePostContent(string $rawContent): string {
    $cleanContent = ArticleHtmlSanitizer::sanitize($rawContent);
    if (isSubstantivelyEmptyHtml($cleanContent)) {
        throw new InvalidArgumentException('المحتوى لا يمكن أن يكون فارغاً.');
    }
    return $cleanContent;
}

