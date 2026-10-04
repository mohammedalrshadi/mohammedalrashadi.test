<?php
namespace Domain\Routing;

use PDO;
use PDOException;

final class RedirectRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getDestination(string $sourcePath): ?string
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT destination_path 
                 FROM url_redirects 
                 WHERE source_path = :source 
                 LIMIT 1"
            );
            $stmt->execute([':source' => $sourcePath]);
            $redir = $stmt->fetchColumn();
            return $redir ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}
