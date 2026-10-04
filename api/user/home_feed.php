<?php
// ============================================================
// SHARED HOME FEED HELPER
// api/user/home_feed.php
// Provides data arrays for the Context-Aware Homepage.
// ============================================================

require_once __DIR__ . '/content_resolver.php';

/**
 * Returns a human-readable "time ago" string.
 */
function homeTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

/**
 * homeLatestItems: Fetch latest 5 published items (Writing, Projects, Labs if applicable).
 */
function homeLatestItems(PDO $pdo) {
    $items = [];

    // 1. Posts (Writing / Projects)
    $stmt = $pdo->prepare(
        "SELECT id, type, created_at FROM posts 
         WHERE status = 'published' AND deleted_at IS NULL 
         ORDER BY created_at DESC LIMIT 15"
    );
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cType = ($row['type'] === 'achievement' || $row['type'] === 'project') ? 'project' : 'writing';
        $meta = resolveUserContent($pdo, $cType, (string)$row['id']);
        if (!$meta['is_missing']) {
            $items[] = [
                'type_badge' => $meta['type_badge'],
                'title' => $meta['title'],
                'url' => $meta['url'],
                'date' => strtotime($row['created_at'])
            ];
        }
    }

    // 2. Labs (If date is trustworthy in lab_experiments.json)
    $labsFile = dirname(__DIR__) . '/data/lab_experiments.json';
    if (file_exists($labsFile)) {
        $labsData = json_decode(file_get_contents($labsFile), true);
        if (is_array($labsData)) {
            foreach ($labsData as $lab) {
                // Check if trustworthy date exists.
                if (isset($lab['created_at']) && strtotime($lab['created_at']) !== false) {
                    $meta = resolveUserContent($pdo, 'lab', $lab['id']);
                    if (!$meta['is_missing']) {
                        $items[] = [
                            'type_badge' => 'Studio Lab',
                            'title' => $meta['title'],
                            'url' => '/lab-detail.php?id=' . urlencode($lab['id']),
                            'date' => strtotime($lab['created_at'])
                        ];
                    }
                }
            }
        }
    }

    // Sort combined items by date descending
    usort($items, function($a, $b) {
        return $b['date'] <=> $a['date'];
    });

    return array_slice($items, 0, 5);
}

/**
 * homeContinueReading: Find exactly one 'writing' item with progress 5-94
 * Skip if paused history (checked outside or returns empty if history disabled).
 */
function homeContinueReading(PDO $pdo, int $userId, bool $saveHistory) {
    if (!$saveHistory) return null;

    $stmt = $pdo->prepare(
        "SELECT content_type, content_id, progress_percent, last_viewed_at 
         FROM reading_history 
         WHERE user_id = ? AND content_type = 'writing' AND progress_percent >= 5 AND progress_percent < 95
         ORDER BY last_viewed_at DESC LIMIT 5"
    );
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $meta = resolveUserContent($pdo, $row['content_type'], $row['content_id']);
        if (!$meta['is_missing']) {
            return [
                'type_badge' => $meta['type_badge'],
                'title' => $meta['title'],
                'url' => $meta['url'],
                'progress' => $row['progress_percent']
            ];
        }
    }
    return null;
}

/**
 * homeRecentlyViewed: Last 3 viewed items (any type), skipping the Continue Reading item if present.
 */
function homeRecentlyViewed(PDO $pdo, int $userId, bool $saveHistory, ?string $skipUrl = null) {
    if (!$saveHistory) return [];

    $items = [];
    $stmt = $pdo->prepare(
        "SELECT content_type, content_id, last_viewed_at 
         FROM reading_history 
         WHERE user_id = ?
         ORDER BY last_viewed_at DESC LIMIT 10"
    );
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $meta = resolveUserContent($pdo, $row['content_type'], $row['content_id']);
        if (!$meta['is_missing'] && $meta['url'] !== $skipUrl) {
            $items[] = [
                'type_badge' => $meta['type_badge'],
                'title' => $meta['title'],
                'url' => $meta['url'],
                'time_ago' => homeTimeAgo($row['last_viewed_at'])
            ];
            if (count($items) >= 3) break;
        }
    }
    return $items;
}

/**
 * homeSavedItems: Last 3 bookmarks.
 */
function homeSavedItems(PDO $pdo, int $userId) {
    $items = [];
    $stmt = $pdo->prepare(
        "SELECT content_type, content_id, created_at 
         FROM bookmarks 
         WHERE user_id = ?
         ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $meta = resolveUserContent($pdo, $row['content_type'], $row['content_id']);
        if (!$meta['is_missing']) {
            $items[] = [
                'type_badge' => $meta['type_badge'],
                'title' => $meta['title'],
                'url' => $meta['url'],
                'time_ago' => homeTimeAgo($row['created_at'])
            ];
            if (count($items) >= 3) break;
        }
    }
    return $items;
}

/**
 * homeUserCounts: Returns array of counts for Library, Saved, and History.
 * History count is omitted if user paused history, handled at caller.
 */
function homeUserCounts(PDO $pdo, int $userId): array {
    $counts = ['library' => 0, 'saved' => 0, 'history' => 0];
    try {
        // Library
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_library WHERE user_id = ?");
        $stmt->execute([$userId]);
        $counts['library'] = (int)$stmt->fetchColumn();

        // Saved (Bookmarks)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE user_id = ?");
        $stmt->execute([$userId]);
        $counts['saved'] = (int)$stmt->fetchColumn();

        // History
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reading_history WHERE user_id = ?");
        $stmt->execute([$userId]);
        $counts['history'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        // Return zeros on failure
    }
    return $counts;
}
