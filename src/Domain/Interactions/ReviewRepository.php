<?php
namespace Domain\Interactions;

use PDO;
use PDOException;

final class ReviewRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getApprovedReviews(int $postId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT name, message, created_at 
                 FROM reviews 
                 WHERE post_id = :post_id AND status = 'approved' 
                 ORDER BY created_at DESC"
            );
            $stmt->execute([':post_id' => $postId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
