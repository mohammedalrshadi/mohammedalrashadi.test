<?php
declare(strict_types=1);

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';

header('Content-Type: application/json');
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 8;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $isGrouped = isset($_GET['grouped']) && $_GET['grouped'] === 'true';

    // Fetch the raw audit logs
    $fetchLimit = $isGrouped ? 100 : $limit; // Fetch more if grouping to ensure we have enough groups
    $sql = "
        SELECT aal.*, u.name as actor_name, u.email as actor_email,
            p.title as product_title,
            a.title as article_title,
            ach.title as achievement_title
        FROM admin_audit_log aal
        LEFT JOIN users u ON aal.admin_id = u.id
        LEFT JOIN products p ON aal.target_type = 'product' AND aal.target_id = p.id
        LEFT JOIN posts a ON aal.target_type = 'article' AND aal.target_id = a.id
        LEFT JOIN posts ach ON aal.target_type IN ('project', 'achievement') AND aal.target_id = ach.id
        ORDER BY aal.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $fetchLimit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group consecutive events if requested
    $rows = [];
    if ($isGrouped && !empty($rawRows)) {
        $currentGroup = null;
        foreach ($rawRows as $row) {
            $mergeKey = $row['action'] . '|' . $row['target_type'] . '|' . $row['target_id'] . '|' . $row['admin_id'];
            
            if ($currentGroup === null) {
                $currentGroup = $row;
                $currentGroup['merge_count'] = 1;
                $currentGroup['merge_key'] = $mergeKey;
            } else if ($currentGroup['merge_key'] === $mergeKey) {
                $currentGroup['merge_count']++;
                // Keep the most recent timestamp (which is the first one we saw due to ORDER BY DESC)
            } else {
                $rows[] = $currentGroup;
                $currentGroup = $row;
                $currentGroup['merge_count'] = 1;
                $currentGroup['merge_key'] = $mergeKey;
            }
            
            if (count($rows) >= $limit) {
                break;
            }
        }
        if ($currentGroup !== null && count($rows) < $limit) {
            $rows[] = $currentGroup;
        }
    } else {
        foreach ($rawRows as $row) {
            $row['merge_count'] = 1;
            $rows[] = $row;
        }
    }

    $eventMap = [
        'product.update'   => ['label' => 'Updated product', 'icon' => 'fa-pen', 'type' => 'update'],
        'product.create'   => ['label' => 'Created product', 'icon' => 'fa-plus', 'type' => 'create'],
        'product.delete'   => ['label' => 'Deleted product', 'icon' => 'fa-trash', 'type' => 'delete'],
        'article.update'   => ['label' => 'Updated article', 'icon' => 'fa-pen', 'type' => 'update'],
        'article.create'   => ['label' => 'Created article', 'icon' => 'fa-plus', 'type' => 'create'],
        'article.delete'   => ['label' => 'Deleted article', 'icon' => 'fa-trash', 'type' => 'delete'],
        'project.update' => ['label' => 'Updated project', 'icon' => 'fa-pen', 'type' => 'update'],
        'project.create' => ['label' => 'Created project', 'icon' => 'fa-plus', 'type' => 'create'],
        'project.delete' => ['label' => 'Deleted project', 'icon' => 'fa-trash', 'type' => 'delete'],
        'achievement.update' => ['label' => 'Updated project (legacy)', 'icon' => 'fa-pen', 'type' => 'update'],
        'achievement.create' => ['label' => 'Created project (legacy)', 'icon' => 'fa-plus', 'type' => 'create'],
        'achievement.delete' => ['label' => 'Deleted project (legacy)', 'icon' => 'fa-trash', 'type' => 'delete'],
        'achievement.trash' => ['label' => 'Moved achievement to trash', 'icon' => 'fa-trash', 'type' => 'delete'],
        'achievement.restore' => ['label' => 'Restored achievement', 'icon' => 'fa-rotate-left', 'type' => 'update'],
        'achievement.purge' => ['label' => 'Permanently deleted achievement', 'icon' => 'fa-trash', 'type' => 'delete'],
        'achievement_image.create' => ['label' => 'Uploaded project image (legacy)', 'icon' => 'fa-image', 'type' => 'create'],
        'achievement_image.delete' => ['label' => 'Deleted project image (legacy)', 'icon' => 'fa-trash', 'type' => 'delete'],
        'project_image.create' => ['label' => 'Uploaded project image', 'icon' => 'fa-image', 'type' => 'create'],
        'project_image.delete' => ['label' => 'Deleted project image', 'icon' => 'fa-trash', 'type' => 'delete'],

        'auth.login'       => ['label' => 'Signed in', 'icon' => 'fa-arrow-right-to-bracket', 'type' => 'login'],
        'auth.logout'      => ['label' => 'Signed out', 'icon' => 'fa-arrow-right-from-bracket', 'type' => 'default'],
        'settings.avatar'  => ['label' => 'Changed profile avatar', 'icon' => 'fa-image', 'type' => 'settings'],
        'settings.update'  => ['label' => 'Updated settings', 'icon' => 'fa-sliders', 'type' => 'settings'],
        'settings.seo'     => ['label' => 'Updated SEO settings', 'icon' => 'fa-magnifying-glass', 'type' => 'settings'],
        'user.create'      => ['label' => 'Created user', 'icon' => 'fa-user-plus', 'type' => 'create'],
        'user.update'      => ['label' => 'Updated user', 'icon' => 'fa-user-pen', 'type' => 'update'],
        'user.delete'      => ['label' => 'Deleted user', 'icon' => 'fa-user-minus', 'type' => 'delete']
    ];

    $events = array_map(function ($row) use ($eventMap) {
        $action = $row['action'];
        $mapped = $eventMap[$action] ?? null;
        
        // Determine the target name
        $targetName = '';
        $url = '#';
        
        if ($row['target_type'] === 'product' && !empty($row['product_title'])) {
            $targetName = $row['product_title'];
            $url = 'store-edit.php?id=' . $row['target_id'];
        } else if ($row['target_type'] === 'article' && !empty($row['article_title'])) {
            $targetName = $row['article_title'];
            $url = 'articles-edit.php?id=' . $row['target_id'];
        } else if (($row['target_type'] === 'project' || $row['target_type'] === 'achievement') && !empty($row['achievement_title'])) {
            $targetName = $row['achievement_title'];
            $url = 'projects-edit.php?id=' . $row['target_id'];
        } else if ($row['target_type'] === 'settings') {
            $targetName = 'System Settings';
            $url = 'settings.php';
        } else if ($row['target_id']) {
            $targetName = ucfirst($row['target_type']) . ' #' . $row['target_id'];
        }
        
        // Fallback for unknown actions
        if (!$mapped) {
            $cleanAction = ucwords(str_replace(['.', '_'], ' ', $action));
            $mapped = ['label' => $cleanAction, 'icon' => 'fa-bolt', 'type' => 'default'];
        }
        
        // Append count if merged
        $actionLabel = $mapped['label'];
        if ($row['merge_count'] > 1) {
            $actionLabel .= ' ×' . $row['merge_count'];
        }
        
        $actor = $row['actor_name'] ?: ($row['actor_email'] ?: 'Unknown User');

        return [
            'id' => $row['id'],
            'action_label' => $actionLabel,
            'target_name' => $targetName,
            'actor_name' => $actor,
            'icon' => $mapped['icon'],
            'type_class' => 'type-' . $mapped['type'],
            'url' => $url,
            'created_at' => $row['created_at']
        ];
    }, $rows);

    echo json_encode(['success' => true, 'data' => $events]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch activity feed']);
}
