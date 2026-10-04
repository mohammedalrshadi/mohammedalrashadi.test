<?php
namespace Domain\Content;

use PDO;
use PDOException;

final class PostRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getPublishedBlogs(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, slug, title, category, content, image_url, created_at, views
                 FROM posts
                 WHERE type = 'blog' AND status = 'published' AND deleted_at IS NULL
                 ORDER BY created_at DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback for missing 'slug' column in some environments
            try {
                $stmt = $this->pdo->query(
                    "SELECT id, title, category, content, image_url, created_at, views
                     FROM posts
                     WHERE type = 'blog' AND status = 'published' AND deleted_at IS NULL
                     ORDER BY created_at DESC"
                );
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return [];
            }
        }
    }

    public function getCategories(string $type = 'blog'): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM categories WHERE type = ? ORDER BY name ASC");
            $stmt->execute([$type]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Feature-detect the optional posts.published_at column (cached per request).
     */
    private function hasPublishedAtColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            try {
                $has = ($this->pdo->query("SELECT published_at FROM posts LIMIT 0") !== false);
            } catch (PDOException $e) {
                $has = false;
            }
        }
        return $has;
    }

    public function getPublishedBlogBySlug(string $slug): ?array
    {
        try {
            $hasPublishedAt = $this->hasPublishedAtColumn();

            $publishedCol = $hasPublishedAt ? ', published_at' : '';
            $stmt = $this->pdo->prepare(
                "SELECT id, slug, title, category, content, meta_title, meta_description, image_url, created_at, updated_at, views{$publishedCol}
                 FROM posts
                 WHERE slug = :slug AND type = 'blog' AND status = 'published' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slug]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            return $post ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getPublishedBlogById(int $id): ?array
    {
        try {
            $hasPublishedAt = $this->hasPublishedAtColumn();

            $publishedCol = $hasPublishedAt ? ', published_at' : '';
            $stmt = $this->pdo->prepare(
                "SELECT id, slug, title, category, content, meta_title, meta_description, image_url, created_at, updated_at, views{$publishedCol}
                 FROM posts
                 WHERE id = :id AND type = 'blog' AND status = 'published' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            return $post ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function incrementViews(int $id): void
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } catch (PDOException $e) { error_log('[src/Domain/Content/PostRepository.php:106] non-fatal, fallback used: ' . get_class($e)); }
    }

    public function getApprovedReviews(int $postId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT name, message, created_at FROM reviews WHERE post_id = :post_id AND status = 'approved' ORDER BY created_at DESC");
            $stmt->execute([':post_id' => $postId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getPublishedProjects(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, slug, title, category, content, image_url, created_at, views
                 FROM posts
                 WHERE type = 'project' AND status = 'published' AND deleted_at IS NULL
                 ORDER BY created_at DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getRedirect(string $sourcePath): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT destination_path FROM url_redirects WHERE source_path = :source LIMIT 1");
            $stmt->execute([':source' => $sourcePath]);
            $redir = $stmt->fetchColumn();
            return $redir ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getPublishedProjectBySlug(string $slug): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, slug, title, category, content, meta_title, meta_description, image_url, created_at, updated_at, views
                 FROM posts
                 WHERE slug = :slug AND type IN ('project', 'project') AND status = 'published' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slug]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            return $post ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getPublishedProjectById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT id, slug, title, category, content, meta_title, meta_description, image_url, created_at, updated_at, views FROM posts WHERE id = :id AND type IN ('project', 'project') AND status = 'published' AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([':id' => $id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            return $post ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getLegacyProjectSlug(int $id): ?string
    {
        try {
            $stmtLegacy = $this->pdo->prepare(
                "SELECT slug FROM posts
                 WHERE id = :id AND type IN ('project', 'project') AND status = 'published' AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmtLegacy->execute([':id' => $id]);
            $legacySlug = $stmtLegacy->fetchColumn();
            return $legacySlug ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getProjectGalleryImages(int $postId): array
    {
        try {
            $imgStmt = $this->pdo->prepare("SELECT image_url FROM project_images WHERE post_id = :id ORDER BY id ASC");
            $imgStmt->execute([':id' => $postId]);
            return $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getRecentPublishedProjects(int $limit = 4): array
    {
        try {
            // Note: HEAD has slug in SELECT, FEATURE removed it. I will keep slug just in case it is needed by the UI
            $stmt = $this->pdo->prepare(
                "SELECT id, slug, title, category, content, image_url, created_at
                 FROM posts
                 WHERE type = 'project' AND status = 'published' AND deleted_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getRecentPublishedBlogs(int $limit = 3): array
    {
        try {
            // Note: HEAD has slug in SELECT, FEATURE removed it. I will keep slug just in case it is needed by the UI
            $stmt = $this->pdo->prepare(
                "SELECT id, slug, title, category, content, image_url, created_at
                 FROM posts
                 WHERE type = 'blog' AND status = 'published' AND deleted_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function searchPublishedPosts(string $query, int $limit = 40): array
    {
        try {
            $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
            $searchPattern = '%' . $escapedQuery . '%';

            $sql = "SELECT id, title, category, content, type, image_url, created_at
                    FROM posts
                    WHERE status = 'published'
                      AND deleted_at IS NULL
                      AND (
                        title LIKE :p1 ESCAPE '\\\\'
                        OR category LIKE :p2 ESCAPE '\\\\'
                        OR quote_ar LIKE :p3 ESCAPE '\\\\'
                        OR quote_en LIKE :p4 ESCAPE '\\\\'
                        OR REGEXP_REPLACE(content, '<[^>]+>', ' ') LIKE :p5 ESCAPE '\\\\'
                      )
                    ORDER BY
                      CASE
                        WHEN title LIKE :p6 ESCAPE '\\\\' THEN 1
                        WHEN category LIKE :p7 ESCAPE '\\\\' THEN 2
                        ELSE 3
                      END,
                      created_at DESC
                    LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':p1', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':p2', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':p3', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':p4', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':p5', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':p6', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':p7', $searchPattern, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getPostsForSitemap(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, slug, type, updated_at, created_at
                 FROM posts
                 WHERE status = 'published'
                   AND deleted_at IS NULL
                 ORDER BY type ASC, id ASC"
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
