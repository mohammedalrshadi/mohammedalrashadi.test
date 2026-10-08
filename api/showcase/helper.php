<?php
// ============================================================
// HOME SHOWCASE — HELPER FUNCTIONS
// api/showcase/helper.php
//
// Validation, normalization, and content resolution for Home Showcase.
// The Home Showcase is a curated presentation layer directly below hero.
//
// Supported Showcase Types (strictly matching live schema):
//   1. product  → resolves from products.id (reference_id = products.id)
//   2. image    → standalone visual asset (uses image_url, alt_text)
//   3. project  → resolves from posts.id (reference_id = posts.id)
//   4. writing  → resolves from posts.id (reference_id = posts.id)
// ============================================================

require_once dirname(__DIR__) . '/posts/sanitizer.php';

const ALLOWED_SHOWCASE_TYPES = [
    'product',
    'image',
    'project',
    'writing'
];

/**
 * Normalizes input type string to the 4 agreed showcase types.
 * Maps legacy 'article' -> 'writing' and 'project' -> 'project'.
 */
/**
 * DC-003: Only same-site paths ("/x", not "//host") and http(s) URLs are allowed
 * as showcase links. Anything else (javascript:, data:, vbscript:, mailto:, ...)
 * is rejected. Empty string is treated as "no link" by callers.
 */
function isSafeShowcaseLink(string $url): bool {
    $url = trim($url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F\x5C\s]/', $url)) {
        return false;
    }
    if ($url[0] === '/') {
        return !str_starts_with($url, '//');
    }
    if (preg_match('#^https?://#i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    return false;
}

function normalizeShowcaseType(string $type): string {
    $type = strtolower(trim($type));
    if ($type === 'article') {
        return 'writing';
    }
    if ($type === 'project') {
        return 'project';
    }
    return in_array($type, ALLOWED_SHOWCASE_TYPES, true) ? $type : 'project';
}

/**
 * Returns a human-friendly display label for each showcase item type.
 */
function getShowcaseTypeBadge(string $type): string {
    return match (normalizeShowcaseType($type)) {
        'product' => 'Product',
        'image'   => 'Image',
        'writing' => 'Writing',
        default   => 'Project',
    };
}

/**
 * Resolves a showcase item from home_showcase_items to its source entity.
 * Fails safely if the referenced post/product has been deleted or unpublished.
 *
 * @param PDO   $pdo
 * @param array $rawItem Row from home_showcase_items
 * @param bool  $forAdmin If true, returns missing/unpublished items flagged instead of null.
 * @return array|null Resolved item for presentation, or null if invalid for public.
 */
function resolveShowcaseItem(PDO $pdo, array $rawItem, bool $forAdmin = false): ?array {
    $itemType = normalizeShowcaseType($rawItem['item_type'] ?? 'project');
    $id       = (int) ($rawItem['id'] ?? 0);

    // Generic reference_id with fallback to post_id for backward compatibility
    $refId = null;
    if (!empty($rawItem['reference_id'])) {
        $refId = (int)$rawItem['reference_id'];
    } elseif (!empty($rawItem['post_id'])) {
        $refId = (int)$rawItem['post_id'];
    } elseif (!empty($rawItem['product_id'])) {
        $refId = (int)$rawItem['product_id'];
    }

    $postId   = !empty($rawItem['post_id']) ? (int)$rawItem['post_id'] : null;
    $enabled  = (int) ($rawItem['is_enabled'] ?? 1);
    $order    = (int) ($rawItem['sort_order'] ?? 0);

    $titleOverride = isset($rawItem['title_override']) ? trim((string)$rawItem['title_override']) : '';
    $descOverride  = isset($rawItem['description_override']) ? trim((string)$rawItem['description_override']) : '';
    $imageOverride = isset($rawItem['image_url']) ? trim((string)$rawItem['image_url']) : '';
    $altText       = isset($rawItem['alt_text']) ? trim((string)$rawItem['alt_text']) : '';
    $linkOverride  = isset($rawItem['link_url']) ? trim((string)$rawItem['link_url']) : '';
    // DC-003: defence in depth for rows stored before link validation existed.
    if ($linkOverride !== '' && !isSafeShowcaseLink($linkOverride)) {
        $linkOverride = '';
    }

    $resolved = [
        'id'                   => $id,
        'item_type'            => $itemType,
        'reference_id'         => $refId,
        'post_id'              => $postId,
        'is_enabled'           => $enabled,
        'sort_order'           => $order,
        'title_override'       => $titleOverride,
        'description_override' => $descOverride,
        'image_url'            => $imageOverride,
        'alt_text'             => $altText,
        'link_url'             => $linkOverride,
        'is_missing'           => false,
        'badge'                => getShowcaseTypeBadge($itemType),
        'title'                => '',
        'description'          => '',
        'image'                => '',
        'alt'                  => '',
        'url'                  => '',
        'category'             => '',
        'action_label'         => '',
        'meta'                 => []
    ];

    switch ($itemType) {
        // -------------------------------------------------------------
        // 1. PRODUCT: Resolves from products table where id = reference_id
        // -------------------------------------------------------------
        case 'product':
            if ($refId === null || $refId <= 0) {
                if ($forAdmin) {
                    $resolved['is_missing'] = true;
                    $resolved['title'] = $titleOverride !== '' ? $titleOverride : '[Unassigned Product]';
                    $resolved['description'] = 'No product reference ID assigned to this slot.';
                    $resolved['category'] = 'Store';
                    $resolved['image'] = $imageOverride !== '' ? $imageOverride : '/assets/store-placeholder.png';
                    $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
                    $resolved['url'] = $linkOverride !== '' ? $linkOverride : '/store.php';
                    $resolved['action_label'] = 'Configure Product';
                    return $resolved;
                }
                return null;
            }

            try {
                $pStmt = $pdo->prepare(
                    "SELECT id, title, slug, short_description, thumbnail, category, price_display, product_type, status 
                     FROM products 
                     WHERE id = ? LIMIT 1"
                );
                $pStmt->execute([$refId]);
                $product = $pStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('[Showcase Helper] Failed to query product: ' . $e->getMessage());
                $product = false;
            }

            // Omit if product does not exist or if not published on public site
            if (!$product || (!$forAdmin && $product['status'] !== 'published')) {
                if ($forAdmin) {
                    $resolved['is_missing'] = true;
                    $resolved['title'] = $titleOverride !== '' ? $titleOverride : ('[Missing / Draft Product #' . $refId . ']');
                    $resolved['description'] = 'Referenced product is unavailable or not published.';
                    $resolved['category'] = 'Store';
                    $resolved['image'] = $imageOverride !== '' ? $imageOverride : '/assets/store-placeholder.png';
                    $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
                    $resolved['url'] = $linkOverride !== '' ? $linkOverride : '/admin/store.php';
                    $resolved['action_label'] = 'View in Admin';
                    return $resolved;
                }
                return null;
            }

            $resolved['title'] = $titleOverride !== '' ? $titleOverride : $product['title'];
            $resolved['description'] = $descOverride !== '' ? $descOverride : ($product['short_description'] ?? '');
            $resolved['image'] = $imageOverride !== '' ? $imageOverride : (!empty($product['thumbnail']) ? $product['thumbnail'] : '/assets/store-placeholder.png');
            $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
            $resolved['url'] = $linkOverride !== '' ? $linkOverride : ('/store/' . rawurlencode($product['slug']));
            $resolved['category'] = !empty($product['category']) ? $product['category'] : 'Digital Resource';

            $priceText = trim($product['price_display'] ?? '');
            if ($priceText !== '') {
                $resolved['action_label'] = 'Get (' . $priceText . ')';
            } else {
                $resolved['action_label'] = $product['product_type'] === 'free_download' ? 'Download Free' : 'View Product';
            }
            $resolved['meta'] = [
                'slug' => $product['slug'],
                'price_display' => $priceText,
                'product_type' => $product['product_type'],
                'status' => $product['status']
            ];
            return $resolved;

        // -------------------------------------------------------------
        // 2. PROJECT: Resolves from posts table where id = reference_id (or post_id)
        // -------------------------------------------------------------
        case 'project':
            if ($refId === null || $refId <= 0) {
                if ($forAdmin) {
                    $resolved['is_missing'] = true;
                    $resolved['title'] = $titleOverride !== '' ? $titleOverride : '[Unassigned Project]';
                    $resolved['description'] = 'No project reference ID assigned.';
                    $resolved['category'] = 'Project';
                    $resolved['image'] = $imageOverride !== '' ? $imageOverride : '';
                    $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
                    $resolved['url'] = $linkOverride !== '' ? $linkOverride : '/projects.php';
                    $resolved['action_label'] = 'Configure Project';
                    return $resolved;
                }
                return null;
            }

            try {
                $pStmt = $pdo->prepare(
                    "SELECT id, title, category, content, image_url, status 
                     FROM posts 
                     WHERE id = ? AND type IN ('project','project') AND deleted_at IS NULL LIMIT 1"
                );
                $pStmt->execute([$refId]);
                $project = $pStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('[Showcase Helper] Failed to query project: ' . $e->getMessage());
                $project = false;
            }

            // Omit if project does not exist or if not published on public site
            if (!$project || (!$forAdmin && $project['status'] !== 'published')) {
                if ($forAdmin) {
                    $resolved['is_missing'] = true;
                    $resolved['title'] = $titleOverride !== '' ? $titleOverride : ('[Missing / Draft Project #' . $refId . ']');
                    $resolved['description'] = 'Referenced project was deleted or unpublished.';
                    $resolved['category'] = 'Software Engineering';
                    $resolved['image'] = $imageOverride !== '' ? $imageOverride : '';
                    $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
                    $resolved['url'] = $linkOverride !== '' ? $linkOverride : '/admin/projects.php';
                    $resolved['action_label'] = 'View in Admin';
                    return $resolved;
                }
                return null;
            }

            $resolved['title'] = $titleOverride !== '' ? $titleOverride : $project['title'];
            if ($descOverride !== '') {
                $resolved['description'] = $descOverride;
            } else {
                $cleanContent = trim(strip_tags((string)($project['content'] ?? '')));
                $resolved['description'] = mb_substr($cleanContent, 0, 150, 'UTF-8') . (mb_strlen($cleanContent, 'UTF-8') > 150 ? '...' : '');
            }
            $resolved['image'] = $imageOverride !== '' ? $imageOverride : (!empty($project['image_url']) ? $project['image_url'] : '');
            $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
            $resolved['url'] = $linkOverride !== '' ? $linkOverride : ('/project.php?id=' . (int)$project['id']);
            $resolved['category'] = !empty($project['category']) ? $project['category'] : 'Software Engineering';
            $resolved['action_label'] = 'View Case Study';
            $resolved['meta'] = [
                'status' => $project['status']
            ];
            return $resolved;

        // -------------------------------------------------------------
        // 3. WRITING: Resolves from posts table where id = reference_id (or post_id)
        // -------------------------------------------------------------
        case 'writing':
            if ($refId === null || $refId <= 0) {
                if ($forAdmin) {
                    $resolved['is_missing'] = true;
                    $resolved['title'] = $titleOverride !== '' ? $titleOverride : '[Unassigned Writing]';
                    $resolved['description'] = 'No writing reference ID assigned.';
                    $resolved['category'] = 'Writing';
                    $resolved['image'] = $imageOverride !== '' ? $imageOverride : '';
                    $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
                    $resolved['url'] = $linkOverride !== '' ? $linkOverride : '/blog.php';
                    $resolved['action_label'] = 'Configure Writing';
                    return $resolved;
                }
                return null;
            }

            try {
                $aStmt = $pdo->prepare(
                    "SELECT id, title, category, content, image_url, status 
                     FROM posts 
                     WHERE id = ? AND type IN ('blog','article') AND deleted_at IS NULL LIMIT 1"
                );
                $aStmt->execute([$refId]);
                $post = $aStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log('[Showcase Helper] Failed to query writing post: ' . $e->getMessage());
                $post = false;
            }

            // Omit if writing post does not exist or if not published on public site
            if (!$post || (!$forAdmin && $post['status'] !== 'published')) {
                if ($forAdmin) {
                    $resolved['is_missing'] = true;
                    $resolved['title'] = $titleOverride !== '' ? $titleOverride : ('[Missing / Draft Writing #' . $refId . ']');
                    $resolved['description'] = 'Referenced writing essay was deleted or unpublished.';
                    $resolved['category'] = 'Writing';
                    $resolved['image'] = $imageOverride !== '' ? $imageOverride : '';
                    $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
                    $resolved['url'] = $linkOverride !== '' ? $linkOverride : '/admin/articles.php';
                    $resolved['action_label'] = 'View in Admin';
                    return $resolved;
                }
                return null;
            }

            $resolved['title'] = $titleOverride !== '' ? $titleOverride : $post['title'];
            if ($descOverride !== '') {
                $resolved['description'] = $descOverride;
            } else {
                $cleanContent = trim(strip_tags((string)($post['content'] ?? '')));
                $resolved['description'] = mb_substr($cleanContent, 0, 150, 'UTF-8') . (mb_strlen($cleanContent, 'UTF-8') > 150 ? '...' : '');
            }
            $resolved['image'] = $imageOverride !== '' ? $imageOverride : (!empty($post['image_url']) ? $post['image_url'] : '');
            $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
            $resolved['url'] = $linkOverride !== '' ? $linkOverride : ('/post.php?id=' . (int)$post['id']);
            $resolved['category'] = !empty($post['category']) ? $post['category'] : 'Technical Writing';
            $resolved['action_label'] = 'Read Essay';
            $resolved['meta'] = [
                'status' => $post['status']
            ];
            return $resolved;

        // -------------------------------------------------------------
        // 4. IMAGE: Standalone visual asset (uses image_url, alt_text, link_url)
        // -------------------------------------------------------------
        case 'image':
            $resolved['title'] = $titleOverride !== '' ? $titleOverride : 'Visual Artifact';
            $resolved['description'] = $descOverride !== '' ? $descOverride : '';
            $resolved['image'] = $imageOverride !== '' ? $imageOverride : '';
            $resolved['alt'] = $altText !== '' ? $altText : $resolved['title'];
            $resolved['url'] = $linkOverride !== '' ? $linkOverride : $resolved['image'];
            $resolved['category'] = 'Visual Architecture';
            $resolved['action_label'] = $linkOverride !== '' ? 'Explore Asset' : 'View Image';
            return $resolved;

        default:
            return null;
    }
}
