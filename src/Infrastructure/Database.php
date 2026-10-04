<?php
namespace Infrastructure;

use PDO;
use PDOException;

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            // Include config from the root api directory
            require_once dirname(__DIR__, 2) . '/api/config.php';
            
            $dbName = DB_NAME;
            if (php_sapi_name() === 'cli' && getenv('TEST_DB_NAME')) {
                $dbName = getenv('TEST_DB_NAME');
            }
            
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . $dbName . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('[Database.php] Connection failed: ' . $e->getMessage());
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        
        return self::$pdo;
    }
}
