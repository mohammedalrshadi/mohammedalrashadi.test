<?php
// ============================================================
// USER PLATFORM — CONTENT RESOLVER HELPER
// api/user/content_resolver.php
//
// Resolves canonical content types (writing, project, lab, product)
// to live metadata for bookmarks, likes, history, and dashboard.
//
// Canonical Types:
//   - writing → posts WHERE type='blog' (URL: /post.php?id={id})
//   - project → posts WHERE type='project' (URL: /project.php?id={id})
//   - lab     → api/data/lab_experiments.json (URL: /lab-detail.php?id={id})
//   - product → products table (URL: /store/{slug})
// ============================================================

const ALLOWED_USER_CONTENT_TYPES = [
    'writing',
    'project',
    'lab',
    'product'
];

/**
 * Validates whether a content type string is a valid User Platform content type.
 */
function isValidUserContentType(string $type): bool {
    return in_array(strtolower(trim($type)), ALLOWED_USER_CONTENT_TYPES, true);
}

/**
 * Returns human-friendly badge label for a content type.
 */
function getUserContentTypeBadge(string $type): string {
    return match (strtolower(trim($type))) {
        'writing' => 'Writing',
        'project' => 'Project',
        'lab'     => 'Lab',
        'product' => 'Store Product',
        default   => 'Resource'
    };
}

/**
 * Resolves a content item to its presentation metadata.
 *
 * @param PDO|null $pdo
 * @param string $contentType
 * @param string $contentId
 * @return array
 */
function resolveUserContent(?PDO $pdo, string $contentType, string $contentId): array {
    $type = strtolower(trim($contentType));
    $id = trim($contentId);

    $base = [
        'content_type' => $type,
        'content_id'   => $id,
        'type_badge'   => getUserContentTypeBadge($type),
        'title'        => 'Untitled Resource',
        'category'     => 'General',
        'image'        => '',
        'url'          => '#',
        'status'       => 'published',
        'is_missing'   => false,
    ];

    if ($type !== 'lab' && !$pdo) {
        $base['title'] = 'Resource Unavailable';
        $base['is_missing'] = true;
        return $base;
    }

    switch ($type) {
        // -------------------------------------------------------------
        // 1. WRITING: posts WHERE type = 'blog'
        // -------------------------------------------------------------
        case 'writing':
            $numId = (int)$id;
            if ($numId <= 0) {
                $base['is_missing'] = true;
                $base['title'] = 'Essay Not Found';
                return $base;
            }

            try {
                $stmt = $pdo->prepare(
                    "SELECT id, title, category, image_url, status
                     FROM posts
                     WHERE id = ? AND type = 'blog' AND deleted_at IS NULL
                     LIMIT 1"
                );
                $stmt->execute([$numId]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$post) {
                    $base['is_missing'] = true;
                    $base['title'] = 'Essay Not Found';
                    return $base;
                }

                $base['title']    = $post['title'];
                $base['category'] = !empty($post['category']) ? $post['category'] : 'Technical Writing';
                $base['image']    = !empty($post['image_url']) ? $post['image_url'] : '';
                $base['url']      = '/post.php?id=' . (int)$post['id'];
                $base['status']   = $post['status'];
                $base['is_missing'] = false;
            } catch (Exception $e) {
                error_log('[resolveUserContent] Writing query error: ' . $e->getMessage());
                $base['is_missing'] = true;
            }
            return $base;

        // -------------------------------------------------------------
        // 2. PROJECT: posts WHERE type = 'project'
        // -------------------------------------------------------------
        case 'project':
            $numId = (int)$id;
            if ($numId <= 0) {
                $base['is_missing'] = true;
                $base['title'] = 'Project Not Found';
                return $base;
            }

            try {
                $stmt = $pdo->prepare(
                    "SELECT id, title, category, image_url, status
                     FROM posts
                     WHERE id = ? AND type IN ('project', 'project') AND deleted_at IS NULL
                     LIMIT 1"
                );
                $stmt->execute([$numId]);
                $project = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$project) {
                    $base['is_missing'] = true;
                    $base['title'] = 'Project Not Found';
                    return $base;
                }

                $base['title']    = $project['title'];
                $base['category'] = !empty($project['category']) ? $project['category'] : 'Engineering Project';
                $base['image']    = !empty($project['image_url']) ? $project['image_url'] : '';
                $base['url']      = '/project.php?id=' . (int)$project['id'];
                $base['status']   = $project['status'];
                $base['is_missing'] = false;
            } catch (Exception $e) {
                error_log('[resolveUserContent] Project query error: ' . $e->getMessage());
                $base['is_missing'] = true;
            }
            return $base;

        // -------------------------------------------------------------
        // 3. PRODUCT: products table
        // -------------------------------------------------------------
        case 'product':
            $numId = (int)$id;
            if ($numId <= 0) {
                $base['is_missing'] = true;
                $base['title'] = 'Product Not Found';
                return $base;
            }

            try {
                $stmt = $pdo->prepare(
                    "SELECT id, title, slug, short_description, thumbnail, category, price_display, product_type, status
                     FROM products
                     WHERE id = ?
                     LIMIT 1"
                );
                $stmt->execute([$numId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    $base['is_missing'] = true;
                    $base['title'] = 'Product Not Found';
                    return $base;
                }

                $base['title']    = $product['title'];
                $base['category'] = !empty($product['category']) ? $product['category'] : 'Digital Resource';
                $base['image']    = !empty($product['thumbnail']) ? $product['thumbnail'] : '/assets/store-placeholder.png';
                $base['url']      = '/store/' . rawurlencode($product['slug']);
                $base['status']   = $product['status'];
                $base['is_missing'] = false;
            } catch (Exception $e) {
                error_log('[resolveUserContent] Product query error: ' . $e->getMessage());
                $base['is_missing'] = true;
            }
            return $base;

        // -------------------------------------------------------------
        // 4. LAB: api/data/lab_experiments.json
        // -------------------------------------------------------------
        case 'lab':
            $experimentsFile = dirname(dirname(__DIR__)) . '/api/data/lab_experiments.json';
            if (file_exists($experimentsFile)) {
                $experiments = json_decode(file_get_contents($experimentsFile), true) ?: [];
                foreach ($experiments as $exp) {
                    if (isset($exp['id']) && strcasecmp($exp['id'], $id) === 0) {
                        $base['title']      = $exp['title'] ?? ('Experiment ' . $id);
                        $base['category']   = $exp['categoryLabel'] ?? ($exp['category'] ?? 'Lab Benchmark');
                        $base['image']      = '';
                        $base['url']        = '/lab-detail.php?id=' . rawurlencode($exp['id']);
                        $base['is_missing'] = false;
                        return $base;
                    }
                }
            }

            // If not found in lab JSON, content does not exist
            $base['is_missing'] = true;
            $base['title']    = 'Investigation Not Found';
            $base['category'] = 'Empirical Benchmark';
            $base['image']    = '';
            $base['url']      = '/lab.php';
            return $base;

        default:
            $base['is_missing'] = true;
            return $base;
    }
}
