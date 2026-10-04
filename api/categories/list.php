<?php
// ============================================================
// CATEGORIES — LIST [REQ-015 Phase 2 / Phase 6 Overhaul]
// GET /api/categories/list.php?type=blog|project|project|lab|product|all
//
// Admin only. Returns all managed categories for the given type
// along with published, draft, deleted, and active item counts.
// Zero-post categories are included.
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$type = isset($_GET['type']) ? trim($_GET['type']) : '';
if ($type === 'achievement') {
    error_log("Deprecated API usage: 'achievement' type requested. Use 'project' instead.");
    $type = 'project';
}
$validTypes = ['blog', 'project', 'lab', 'product', 'all'];

if (!in_array($type, $validTypes, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid category type. Must specify type=blog, project, project, lab, product, or all.',
    ]);
    exit;
}

try {
    $pdo = getDB();

    // Determine query strategy based on type
    if ($type === 'lab') {
        $stmt = $pdo->prepare(
            "SELECT id, name, type, link_group FROM categories WHERE type = 'lab' ORDER BY name ASC"
        );
        $stmt->execute();
        $baseRows = $stmt->fetchAll();
        
        $labFile = dirname(__DIR__) . '/data/lab_experiments.json';
        $labs = file_exists($labFile) ? (json_decode(file_get_contents($labFile), true) ?: []) : [];

        $rows = [];
        foreach ($baseRows as $row) {
            $catName = $row['name'];
            $published = 0; $draft = 0; $deleted = 0; $active = 0;
            foreach ($labs as $lab) {
                if (isset($lab['category']) && strcasecmp($lab['category'], $catName) === 0) {
                    $active++;
                    $st = strtolower($lab['status'] ?? '');
                    if ($st === 'active') $published++;
                    elseif ($st === 'planned') $draft++;
                    elseif ($st === 'archived') $deleted++;
                }
            }
            $row['published_count'] = $published;
            $row['draft_count'] = $draft;
            $row['deleted_count'] = $deleted;
            $row['active_posts_count'] = $active;
            $rows[] = $row;
        }

    } elseif ($type === 'product') {
        $stmt = $pdo->prepare(
            "SELECT
                c.id,
                c.name,
                c.type,
                c.link_group,
                COUNT(CASE WHEN pr.status = 'published' THEN 1 END) AS published_count,
                COUNT(CASE WHEN pr.status = 'draft' THEN 1 END) AS draft_count,
                COUNT(CASE WHEN pr.status = 'archived' THEN 1 END) AS deleted_count,
                COUNT(pr.id) AS active_posts_count
             FROM categories c
             LEFT JOIN products pr ON pr.category = c.name
             WHERE c.type = 'product'
             GROUP BY c.id, c.name, c.type, c.link_group
             ORDER BY c.name ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

    } elseif ($type === 'all') {
        // Query all categories
        $stmt = $pdo->prepare(
            "SELECT c.id, c.name, c.type, c.link_group FROM categories c ORDER BY c.type ASC, c.name ASC"
        );
        $stmt->execute();
        $baseRows = $stmt->fetchAll();

        $labFile = dirname(__DIR__) . '/data/lab_experiments.json';
        $labs = file_exists($labFile) ? (json_decode(file_get_contents($labFile), true) ?: []) : [];

        // Calculate counts per category
        $rows = [];
        foreach ($baseRows as $row) {
            $catName = $row['name'];
            $catType = $row['type'];
            $published = 0;
            $draft = 0;
            $deleted = 0;
            $active = 0;

            if ($catType === 'lab') {
                foreach ($labs as $lab) {
                    if (isset($lab['category']) && strcasecmp($lab['category'], $catName) === 0) {
                        $active++;
                        $st = strtolower($lab['status'] ?? '');
                        if ($st === 'active') $published++;
                        elseif ($st === 'planned') $draft++;
                        elseif ($st === 'archived') $deleted++;
                    }
                }
            } elseif ($catType === 'product') {
                $countStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM products WHERE category = ? GROUP BY status");
                $countStmt->execute([$catName]);
                foreach ($countStmt->fetchAll(PDO::FETCH_KEY_PAIR) as $st => $c) {
                    $c = (int)$c;
                    $active += $c;
                    if ($st === 'published') $published += $c;
                    elseif ($st === 'draft') $draft += $c;
                    elseif ($st === 'archived') $deleted += $c;
                }
            } else {
                $countStmt = $pdo->prepare("
                    SELECT 
                        COUNT(CASE WHEN deleted_at IS NULL AND status = 'published' THEN 1 END) as pub,
                        COUNT(CASE WHEN deleted_at IS NULL AND status IN ('hidden', 'draft') THEN 1 END) as drf,
                        COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) as del,
                        COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) as act
                    FROM posts WHERE type = ? AND category = ?
                ");
                $countStmt->execute([$catType, $catName]);
                $c = $countStmt->fetch(PDO::FETCH_ASSOC);
                if ($c) {
                    $published = (int)$c['pub'];
                    $draft     = (int)$c['drf'];
                    $deleted   = (int)$c['del'];
                    $active    = (int)$c['act'];
                }
            }

            $row['published_count']    = $published;
            $row['draft_count']        = $draft;
            $row['deleted_count']      = $deleted;
            $row['active_posts_count'] = $active;
            $rows[] = $row;
        }

    } else {
        // Blog, project, or project
        $stmt = $pdo->prepare(
            "SELECT
                c.id,
                c.name,
                c.type,
                c.link_group,
                COUNT(CASE WHEN p.deleted_at IS NULL AND p.status = 'published' THEN 1 END) AS published_count,
                COUNT(CASE WHEN p.deleted_at IS NULL AND p.status IN ('hidden', 'draft') THEN 1 END) AS draft_count,
                COUNT(CASE WHEN p.deleted_at IS NOT NULL THEN 1 END) AS deleted_count,
                COUNT(CASE WHEN p.deleted_at IS NULL THEN 1 END) AS active_posts_count
             FROM categories c
             LEFT JOIN posts p ON p.type = c.type AND p.category = c.name
             WHERE c.type = ?
             GROUP BY c.id, c.name, c.type, c.link_group
             ORDER BY c.name ASC"
        );
        $stmt->execute([$type]);
        $rows = $stmt->fetchAll();
    }

    $data = array_map(function ($row) {
        return [
            'id'                 => (int) $row['id'],
            'name'               => $row['name'],
            'type'               => $row['type'],
            'link_group'         => $row['link_group'] ?? null,
            'published_count'    => (int) ($row['published_count'] ?? 0),
            'draft_count'        => (int) ($row['draft_count'] ?? 0),
            'deleted_count'      => (int) ($row['deleted_count'] ?? 0),
            'active_posts_count' => (int) ($row['active_posts_count'] ?? 0),
        ];
    }, $rows);

    echo json_encode([
        'success' => true,
        'data'    => $data,
    ]);

} catch (PDOException $e) {
    error_log('[categories/list] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error loading categories.']);
}
