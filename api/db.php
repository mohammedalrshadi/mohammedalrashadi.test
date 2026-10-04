<?php
require_once dirname(__DIR__) . '/src/autoload.php';
require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO database connection instance.
 */
function getDB() {
    return \Infrastructure\Database::getConnection();
}
