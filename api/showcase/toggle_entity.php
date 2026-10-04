<?php
declare(strict_types=1);

// ============================================================
// HOME SHOWCASE — INLINE ENTITY TOGGLE & STATUS
// GET / POST /api/showcase/toggle_entity.php
//
// GET: Query whether an entity is featured on Home Showcase.
//   Params: ?item_type=writing|project|product&reference_id=123
//
// POST: Enable or disable showcase status directly from the entity editor.
//   Payload: JSON { item_type: "writing|project|product", reference_id: 123, is_enabled?: 0|1 }
//   - If row exists: updates is_enabled (preserves sort_order and overrides)
//   - If row does not exist & enabling: inserts new home_showcase_items row
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

requireAuth();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rawType = trim((string)($_GET['item_type'] ?? ''));
    $refId   = (int)($_GET['reference_id'] ?? 0);

    $itemType = normalizeShowcaseType($rawType);

    if ($refId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid reference_id is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, is_enabled, sort_order 
            FROM home_showcase_items 
            WHERE item_type = ? AND (reference_id = ? OR (post_id = ? AND item_type IN ('project', 'writing')))
            LIMIT 1
        ");
        $stmt->execute([$itemType, $refId, $refId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $isFeatured = $row && (int)$row['is_enabled'] === 1;

        echo json_encode([
            'success'      => true,
            'is_featured'  => $isFeatured,
            'is_enabled'   => $row ? (int)$row['is_enabled'] : 0,
            'showcase_id'  => $row ? (int)$row['id'] : null,
            'sort_order'   => $row ? (int)$row['sort_order'] : null,
        ], JSON_THROW_ON_ERROR);
        exit;

    } catch (PDOException $e) {
        error_log('[showcase/toggle_entity GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $rawBody = file_get_contents('php://input');
    $input   = json_decode($rawBody, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $rawType = trim((string)($input['item_type'] ?? ''));
    $refId   = (int)($input['reference_id'] ?? 0);
    $itemType = normalizeShowcaseType($rawType);

    if ($refId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid reference_id is required.']);
        exit;
    }

    try {
        // Find existing row
        $stmt = $pdo->prepare("
            SELECT id, is_enabled, sort_order 
            FROM home_showcase_items 
            WHERE item_type = ? AND (reference_id = ? OR (post_id = ? AND item_type IN ('project', 'writing')))
            LIMIT 1
        ");
        $stmt->execute([$itemType, $refId, $refId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $targetState = isset($input['is_enabled'])
            ? ((int)$input['is_enabled'] ? 1 : 0)
            : ($row ? ((int)$row['is_enabled'] ? 0 : 1) : 1);

        $showcaseId = null;

        if ($row) {
            // Update existing row (preserves sort_order and overrides)
            $showcaseId = (int)$row['id'];
            $upd = $pdo->prepare("UPDATE home_showcase_items SET is_enabled = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$targetState, $showcaseId]);
        } else {
            // Only insert if enabling
            if ($targetState === 1) {
                $maxSort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM home_showcase_items")->fetchColumn();
                $sortOrder = $maxSort + 1;
                $postId = in_array($itemType, ['project', 'writing'], true) ? $refId : null;

                $ins = $pdo->prepare("
                    INSERT INTO home_showcase_items (item_type, reference_id, post_id, is_enabled, sort_order)
                    VALUES (?, ?, ?, 1, ?)
                ");
                $ins->execute([$itemType, $refId, $postId, $sortOrder]);
                $showcaseId = (int)$pdo->lastInsertId();
            }
        }

        logAdminAction(
            'showcase.toggle_entity',
            'home_showcase_item',
            $showcaseId ? (string)$showcaseId : "entity:{$refId}",
            json_encode([
                'item_type'    => $itemType,
                'target_state' => $targetState,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        echo json_encode([
            'success'      => true,
            'is_featured'  => ($targetState === 1),
            'is_enabled'   => $targetState,
            'showcase_id'  => $showcaseId,
            'message'      => ($targetState === 1) 
                ? 'Featured on Home Showcase.' 
                : 'Removed from Home Showcase (history preserved).',
        ], JSON_THROW_ON_ERROR);
        exit;

    } catch (PDOException $e) {
        error_log('[showcase/toggle_entity POST] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error while toggling showcase item.']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

