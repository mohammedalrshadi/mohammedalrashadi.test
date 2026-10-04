<?php
// ============================================================
// PRODUCTS — HELPER FUNCTIONS
// api/products/helper.php
//
// Validation, normalization, and path security helpers for Store products.
// ============================================================

require_once dirname(__DIR__) . '/posts/sanitizer.php';

const ALLOWED_PRODUCT_CATEGORIES = [
    'Notion Templates',
    'Website Templates',
    'Developer Resources',
    'Study Resources',
    'Guides & PDFs',
    'UI / Design Resources',
    'Tools',
    'Other',
];

const ALLOWED_PRODUCT_TYPES = [
    'external',
    'free_download',
];

const ALLOWED_PRODUCT_STATUSES = [
    'draft',
    'published',
    'archived',
];

/**
 * Returns the protected directory path where downloadable product files are stored.
 */
function getProtectedDownloadDir(): string {
    return dirname(dirname(__DIR__)) . '/uploads/products/downloads/';
}

/**
 * Generates an SEO-friendly URL slug from a title string.
 */
function slugifyProductTitle(string $title): string {
    // Transliterate / replace non-letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $title);
    // Transliterate to ASCII where possible
    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($translit !== false) {
            $text = $translit;
        }
    }
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'product-' . bin2hex(random_bytes(4));
    }

    return substr($text, 0, 100);
}

/**
 * Validates a slug format.
 */
function isValidProductSlug(string $slug): bool {
    if (strlen($slug) < 1 || strlen($slug) > 500) {
        return false;
    }
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

/**
 * Validates and normalizes category.
 */
function normalizeProductCategory(string $raw): string {
    $trimmed = trim($raw);
    foreach (ALLOWED_PRODUCT_CATEGORIES as $cat) {
        if (strcasecmp($trimmed, $cat) === 0) {
            return $cat;
        }
    }
    return $trimmed !== '' ? substr($trimmed, 0, 255) : 'Other';
}

/**
 * Resolves and strictly validates that a relative or absolute download path
 * points to an actual file residing inside the protected downloads directory.
 * Prevents directory traversal attacks.
 *
 * @param string $storedPath
 * @return string|null Absolute path if valid and exists inside protected dir, or null.
 */
function resolveProtectedDownloadFile(?string $storedPath): ?string {
    if (empty($storedPath)) {
        return null;
    }

    $downloadDir = realpath(getProtectedDownloadDir());
    if ($downloadDir === false || !is_dir($downloadDir)) {
        return null;
    }

    // Clean stored path: extract filename only to prevent path traversal
    $filename = basename($storedPath);
    if ($filename === '' || $filename === '.' || $filename === '..') {
        return null;
    }

    // Verify filename contains only safe characters
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
        return null;
    }

    $fullPath = $downloadDir . DIRECTORY_SEPARATOR . $filename;
    $realPath = realpath($fullPath);

    if ($realPath === false || !is_file($realPath)) {
        return null;
    }

    // Ensure the resolved canonical path starts with the download directory
    if (!str_starts_with($realPath, $downloadDir . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return $realPath;
}

/**
 * Recursively deletes a directory and all its contents.
 * Silent on individual file/dir failures (uses @ suppression) — the final
 * @rmdir on the root will also silently fail if any children remain.
 *
 * @param string $dir Absolute path to the directory to delete.
 */
function removeDirRecursive(string $dir): void {
    if (!is_dir($dir)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        @$todo($fileinfo->getRealPath());
    }
    @rmdir($dir);
}
