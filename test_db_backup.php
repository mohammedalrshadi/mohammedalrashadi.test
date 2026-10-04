<?php
require_once __DIR__ . '/api/config.local.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/src/autoload.php';
$pdo = getDB();
$postRepo = new \Domain\Content\PostRepository($pdo);
$featuredProjects = $postRepo->getRecentPublishedProjects(4);
print_r($featuredProjects);
