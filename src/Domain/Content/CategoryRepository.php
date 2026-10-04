<?php
namespace Domain\Content;

use PDO;
use PDOException;

final class CategoryRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getNamesByType(string $type): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM categories WHERE type = ? ORDER BY name ASC");
            $stmt->execute([$type]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return [];
        }
    }
}
