<?php
declare(strict_types=1);

/**
 * ============================================================
 * PLATFORM ENTITY COUNTS HELPER
 * ============================================================
 * Computes authentic counts and breakdowns across all active
 * platform entities from real database tables and the lab JSON store.
 *
 * Guaranteed ZERO simulated or fabricated numbers.
 */

function getPlatformEntityCounts(PDO $pdo): array
{
    $counts = [
        'articles' => [
            'total'     => 0,
            'published' => 0,
            'draft'     => 0,
            'hidden'    => 0,
            'trash'     => 0,
        ],
        'projects' => [
            'total'     => 0,
            'published' => 0,
            'draft'     => 0,
            'hidden'    => 0,
            'trash'     => 0,
        ],
        'labs' => [
            'total'     => 0,
            'active'    => 0,
            'planned'   => 0,
            'completed' => 0,
        ],
        'products' => [
            'total'     => 0,
            'published' => 0,
            'draft'     => 0,
            'archived'  => 0,
            'downloads' => 0,
        ],
        'reviews' => [
            'total'     => 0,
            'pending'   => 0,
            'approved'  => 0,
            'rejected'  => 0,
        ],
        'journey' => [
            'total'     => 0,
            'active'    => 0,
            'trash'     => 0,
        ],
        'users' => [
            'total'     => 0,
            'admin'     => 0,
            'user'      => 0,
        ],
        'categories' => [
            'total'     => 0,
            'blog'      => 0,
            'project'   => 0,
        ],
        'showcase' => [
            'total'     => 0,
            'enabled'   => 0,
        ],
        'social' => [
            'enabled'   => 0,
        ],
        'support' => [
            'total'       => 0,
            'new'         => 0,
            'in_progress' => 0,
            'resolved'    => 0,
            'archived'    => 0,
        ],
        'system' => [
            'settings'   => 0,
            'db_version' => 'Unknown',
            'php_version'=> PHP_VERSION,
        ],
    ];

    try {
        // Articles & Projects from posts table
        $postRows = $pdo->query("
            SELECT 
                type,
                status,
                CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END AS is_trash,
                COUNT(*) as cnt
            FROM posts
            GROUP BY type, status, is_trash
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($postRows as $r) {
            $type = ($r['type'] === 'project' || $r['type'] === 'project') ? 'projects' : 'articles';
            $cnt = (int) $r['cnt'];
            $isTrash = (int) $r['is_trash'];
            $status = (string) $r['status'];

            if ($isTrash === 1) {
                $counts[$type]['trash'] += $cnt;
            } else {
                $counts[$type]['total'] += $cnt;
                if (isset($counts[$type][$status])) {
                    $counts[$type][$status] += $cnt;
                }
            }
        }

        // Labs from JSON store
        $labsFile = dirname(__DIR__) . '/data/lab_experiments.json';
        if (file_exists($labsFile)) {
            $rawLabs = file_get_contents($labsFile);
            if ($rawLabs !== false) {
                $labsData = json_decode($rawLabs, true);
                if (is_array($labsData)) {
                    $counts['labs']['total'] = count($labsData);
                    foreach ($labsData as $exp) {
                        $st = strtolower((string) ($exp['status'] ?? 'active'));
                        if (isset($counts['labs'][$st])) {
                            $counts['labs'][$st]++;
                        }
                    }
                }
            }
        }

        // Products
        $productRows = $pdo->query("
            SELECT status, COUNT(*) as cnt
            FROM products
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productRows as $r) {
            $cnt = (int) $r['cnt'];
            $st = (string) $r['status'];
            $counts['products']['total'] += $cnt;
            if (isset($counts['products'][$st])) {
                $counts['products'][$st] += $cnt;
            }
        }

        // Total product downloads from user_downloads
        $counts['products']['downloads'] = (int) $pdo->query("SELECT COUNT(*) FROM user_downloads")->fetchColumn();

        // Reviews
        $reviewRows = $pdo->query("
            SELECT status, COUNT(*) as cnt
            FROM reviews
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reviewRows as $r) {
            $cnt = (int) $r['cnt'];
            $st = (string) $r['status'];
            $counts['reviews']['total'] += $cnt;
            if (isset($counts['reviews'][$st])) {
                $counts['reviews'][$st] += $cnt;
            }
        }

        // Journey Milestones
        $journeyRows = $pdo->query("
            SELECT 
                CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END AS is_trash,
                COUNT(*) as cnt
            FROM journey_milestones
            GROUP BY is_trash
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($journeyRows as $r) {
            $cnt = (int) $r['cnt'];
            if ((int) $r['is_trash'] === 1) {
                $counts['journey']['trash'] = $cnt;
            } else {
                $counts['journey']['active'] = $cnt;
                $counts['journey']['total'] += $cnt;
            }
        }

        // Users
        $userRows = $pdo->query("
            SELECT role, COUNT(*) as cnt
            FROM users
            GROUP BY role
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($userRows as $r) {
            $cnt = (int) $r['cnt'];
            $counts['users']['total'] += $cnt;
            $role = (string) $r['role'];
            if (isset($counts['users'][$role])) {
                $counts['users'][$role] += $cnt;
            }
        }

        // Categories
        $catRows = $pdo->query("
            SELECT type, COUNT(*) as cnt
            FROM categories
            GROUP BY type
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($catRows as $r) {
            $cnt = (int) $r['cnt'];
            $counts['categories']['total'] += $cnt;
            if ($r['type'] === 'blog') {
                $counts['categories']['blog'] += $cnt;
            } elseif ($r['type'] === 'project' || $r['type'] === 'project') {
                $counts['categories']['project'] += $cnt;
            }
        }

        // Home Showcase Items
        $showcaseRows = $pdo->query("
            SELECT is_enabled, COUNT(*) as cnt
            FROM home_showcase_items
            GROUP BY is_enabled
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($showcaseRows as $r) {
            $cnt = (int) $r['cnt'];
            $counts['showcase']['total'] += $cnt;
            if ((int) $r['is_enabled'] === 1) {
                $counts['showcase']['enabled'] += $cnt;
            }
        }

        // Support Messages
        $supportRows = $pdo->query("
            SELECT status, COUNT(*) as cnt
            FROM support_messages
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($supportRows as $r) {
            $cnt = (int) $r['cnt'];
            $st = (string) $r['status'];
            $counts['support']['total'] += $cnt;
            if (isset($counts['support'][$st])) {
                $counts['support'][$st] += $cnt;
            }
        }

        // Social links
        $counts['social']['enabled'] = (int) $pdo->query("SELECT COUNT(*) FROM social_links WHERE is_enabled = 1")->fetchColumn();

        // System Settings & Database Version
        $counts['system']['settings'] = (int) $pdo->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
        $counts['system']['db_version'] = (string) $pdo->query("SELECT VERSION()")->fetchColumn();

    } catch (Throwable $e) {
        error_log('[stats_helper] Failed to compute platform counts: ' . $e->getMessage());
    }

    return $counts;
}

