<?php
namespace Domain\Content;

use PDO;
use PDOException;

final class RelatedContentResolver
{
    /**
     * Reusable logic for posts table (blog and project types).
     */
    private function getRelatedPosts(PDO $pdo, string $type, int $excludeId, string $category, int $limit): array
    {
        $results = [];
        $usedIds = [$excludeId];

        // Tier 2: Same category, same type
        try {
            $stmt = $this->pdoQueryPosts($pdo, $type, "AND category = :category AND id NOT IN (" . implode(',', $usedIds) . ")", "ORDER BY created_at DESC", $limit);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $results[] = $this->normalizePost($row, $type);
                $usedIds[] = $row['id'];
            }
        } catch (PDOException $e) { error_log('[src/Domain/Content/RelatedContentResolver.php:27] non-fatal, fallback used: ' . get_class($e)); }

        // Tier 3: Same type, most recent
        if (count($results) < $limit) {
            try {
                $placeholders = implode(',', array_fill(0, count($usedIds), '?'));
                $remLimit = $limit - count($results);
                $sql = "SELECT id, title, slug, category, image_url, created_at FROM posts WHERE type = ? AND status = 'published' AND deleted_at IS NULL AND id NOT IN ($placeholders) ORDER BY created_at DESC LIMIT $remLimit";
                $stmt = $pdo->prepare($sql);
                
                $params = [$type];
                foreach ($usedIds as $id) $params[] = $id;
                
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $results[] = $this->normalizePost($row, $type);
                    $usedIds[] = $row['id'];
                }
            } catch (PDOException $e) { error_log('[src/Domain/Content/RelatedContentResolver.php:46] non-fatal, fallback used: ' . get_class($e)); }
        }

        // Tier 4: Cross type by category
        if (count($results) < $limit) {
            $crossType = ($type === 'blog') ? 'project' : 'blog';
            $crossTypeLabel = ($type === 'blog') ? 'project' : 'blog';
            try {
                $stmt = $this->pdoQueryPosts($pdo, $crossType, "AND category = :category", "ORDER BY created_at DESC", $limit - count($results));
                $stmt->bindValue(':category', $category, PDO::PARAM_STR);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $results[] = $this->normalizePost($row, $crossTypeLabel);
                }
            } catch (PDOException $e) { error_log('[src/Domain/Content/RelatedContentResolver.php:61] non-fatal, fallback used: ' . get_class($e)); }
        }

        return $results;
    }

    private function pdoQueryPosts(PDO $pdo, string $type, string $extraWhere, string $orderBy, int $limit)
    {
        $sql = "SELECT id, title, slug, category, image_url, created_at 
                FROM posts 
                WHERE type = '$type' AND status = 'published' AND deleted_at IS NULL 
                $extraWhere 
                $orderBy 
                LIMIT " . (int)$limit;
        return $pdo->prepare($sql);
    }

    public function getRelatedProjects(PDO $pdo, int $excludeId, string $category, int $limit = 3): array
    {
        return $this->getRelatedPosts($pdo, 'project', $excludeId, $category, $limit);
    }

    public function getRelatedBlogs(PDO $pdo, int $excludeId, string $category, int $limit = 3): array
    {
        return $this->getRelatedPosts($pdo, 'blog', $excludeId, $category, $limit);
    }

    public function getRelatedProducts(PDO $pdo, int $excludeId, string $category, int $limit = 3): array
    {
        $results = [];
        $usedIds = [$excludeId];

        // Tier 2: Same category
        try {
            $sql = "SELECT id, title, slug, category, thumbnail, product_type FROM products WHERE status = 'published' AND category = :cat AND id != :exId ORDER BY id DESC LIMIT " . (int)$limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':cat' => $category, ':exId' => $excludeId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $results[] = $this->normalizeProduct($row);
                $usedIds[] = $row['id'];
            }
        } catch (PDOException $e) { error_log('[src/Domain/Content/RelatedContentResolver.php:103] non-fatal, fallback used: ' . get_class($e)); }

        // Tier 3: Most recent
        if (count($results) < $limit) {
            try {
                $placeholders = implode(',', array_fill(0, count($usedIds), '?'));
                $remLimit = $limit - count($results);
                $sql = "SELECT id, title, slug, category, thumbnail, product_type FROM products WHERE status = 'published' AND id NOT IN ($placeholders) ORDER BY sort_order ASC, id DESC LIMIT $remLimit";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($usedIds);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $results[] = $this->normalizeProduct($row);
                }
            } catch (PDOException $e) { error_log('[src/Domain/Content/RelatedContentResolver.php:117] non-fatal, fallback used: ' . get_class($e)); }
        }
        
        return $results;
    }

    public static function getRelatedLabExperiments(string $excludeId, string $category, int $limit = 3): array
    {
        $path = __DIR__ . '/../../../api/data/lab_experiments.json';
        if (!file_exists($path)) return [];
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) return [];

        $results = [];
        $usedIds = [$excludeId];

        // Tier 2: Same category
        foreach ($data as $item) {
            if (count($results) >= $limit) break;
            if ($item['id'] !== $excludeId && isset($item['category']) && $item['category'] === $category) {
                $results[] = self::normalizeLab($item);
                $usedIds[] = $item['id'];
            }
        }

        // Tier 3: Most recent fallback
        if (count($results) < $limit) {
            foreach (array_reverse($data) as $item) { // Assuming JSON order is oldest to newest, reverse for recent
                if (count($results) >= $limit) break;
                if (!in_array($item['id'], $usedIds)) {
                    $results[] = self::normalizeLab($item);
                    $usedIds[] = $item['id'];
                }
            }
        }

        // Tier 4: Cross type? No PDO available statically here easily, let's keep Lab simple and just return what we have.
        // The instruction says "If you can justify the connection", for lab it's fine to just return empty if no labs left.
        
        return $results;
    }

    private function normalizePost(array $row, string $typeLabel): array
    {
        $isProject = ($typeLabel === 'project' || $typeLabel === 'project');
        return [
            'title' => $row['title'],
            'url' => $isProject ? '/project.php?slug=' . urlencode($row['slug']) : '/post.php?slug=' . urlencode($row['slug']),
            'image' => $row['image_url'] ?? '',
            'category' => $row['category'] ?? 'Engineering',
            'type_label' => $isProject ? 'Project' : 'Article'
        ];
    }

    private function normalizeProduct(array $row): array
    {
        return [
            'title' => $row['title'],
            'url' => '/product.php?slug=' . urlencode($row['slug']),
            'image' => $row['thumbnail'] ?? '',
            'category' => $row['category'] ?? 'Resource',
            'type_label' => ($row['product_type'] === 'free_download') ? 'Free Download' : 'Store Product'
        ];
    }

    private static function normalizeLab(array $row): array
    {
        return [
            'title' => $row['title'] ?? 'Experiment',
            'url' => '/lab-detail.php?id=' . urlencode($row['id'] ?? ''),
            'image' => $row['image'] ?? $row['thumbnail'] ?? '',
            'category' => $row['categoryLabel'] ?? $row['category'] ?? 'Lab',
            'type_label' => 'Lab Experiment'
        ];
    }
}
