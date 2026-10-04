<?php
namespace Domain\Media;

use PDO;
use PDOException;

final class GalleryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getProjectImages(int $postId): array
    {
        try {
            $table = 'achievement_images';
            $check = $this->pdo->query("SHOW TABLES LIKE 'project_images'");
            if ($check && $check->fetchColumn()) {
                $table = 'project_images';
            }
            $imgStmt = $this->pdo->prepare(
                "SELECT image_url 
                 FROM {$table} 
                 WHERE post_id = :id 
                 ORDER BY id ASC"
            );
            $imgStmt->execute([':id' => $postId]);
            return $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
}
