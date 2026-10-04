<?php
require_once __DIR__ . '/api/config.local.php';
require_once __DIR__ . '/api/db.php';
$pdo = getDB();
$stmt = $pdo->query("SELECT count(*) FROM posts");
print_r($stmt->fetchColumn());
