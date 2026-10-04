<?php
// ============================================================
// CATEGORIES — DELETE [REQ-015 Phase 2 / Phase 6 Overhaul]
// POST /api/categories/delete.php
//
// Admin only + CSRF protected. POST ONLY.
// Guards against orphaned rows: Blocks deletion when category
// is in use unless explicitly reassigned to another valid category.
// No cascading delete, no orphan rows, no GET deletion.
// ============================================================

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. POST required.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    $input = $_POST;
}

$id     = isset($input['id']) ? (int) $input['id'] : 0;
$action = isset($input['action']) ? trim((string) $input['action']) : 'delete';

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID is required.']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // 1. Verify category exists
    $stmtSource = $pdo->prepare('SELECT id, name, type FROM categories WHERE id = ? FOR UPDATE');
    $stmtSource->execute([$id]);
    $sourceCat = $stmtSource->fetch(PDO::FETCH_ASSOC);

    if (!$sourceCat) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Category not found.']);
        exit;
    }

    $sourceName = $sourceCat['name'];
    $sourceType = $sourceCat['type'];
    $affectedItems = 0;

    // 2. Count items currently using this category
    $inUseCount = 0;
    if ($sourceType === 'lab') {
        $labFile = dirname(__DIR__) . '/data/lab_experiments.json';
        $labs = file_exists($labFile) ? (json_decode(file_get_contents($labFile), true) ?: []) : [];
        foreach ($labs as $lab) {
            if (isset($lab['category']) && strcasecmp($lab['category'], $sourceName) === 0) {
                $inUseCount++;
            }
        }
    } elseif ($sourceType === 'product') {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category = ?');
        $countStmt->execute([$sourceName]);
        $inUseCount = (int) $countStmt->fetchColumn();
    } else {
        // blog, project, or project
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE type = ? AND category = ? AND deleted_at IS NULL');
        $countStmt->execute([$sourceType, $sourceName]);
        $inUseCount = (int) $countStmt->fetchColumn();
    }

    // 3. If in use, must reassign. Reject direct unlink/delete
    if ($inUseCount > 0 && $action !== 'reassign') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "Cannot delete category '{$sourceName}' because it is assigned to {$inUseCount} item(s). Please reassign items to another category before deleting.",
            'in_use_count' => $inUseCount,
        ]);
        exit;
    }

    // 4. Handle Reassign action
    if ($action === 'reassign' && $inUseCount > 0) {
        $rawTargetName = isset($input['target_category_name']) ? (string) $input['target_category_name'] : '';
        $cleanTargetName = normalizeCategoryWhitespace($rawTargetName);

        if ($cleanTargetName === '') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Target replacement category is required for reassignment.']);
            exit;
        }

        // Target must exist in the same taxonomy group
        $targetCat = findCategoryMatch($pdo, $cleanTargetName, $sourceType);
        if (!$targetCat) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Target category '{$cleanTargetName}' does not exist in the {$sourceType} group."]);
            exit;
        }

        if ((int) $targetCat['id'] === $id || $targetCat['name'] === $sourceName) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot reassign items to the category being deleted.']);
            exit;
        }

        $canonicalTargetName = $targetCat['name'];

        // Perform reassignment
        if ($sourceType === 'lab') {
            $labFile = dirname(__DIR__) . '/data/lab_experiments.json';
            $labs = file_exists($labFile) ? (json_decode(file_get_contents($labFile), true) ?: []) : [];
            $changed = false;
            foreach ($labs as &$lab) {
                if (isset($lab['category']) && strcasecmp($lab['category'], $sourceName) === 0) {
                    $lab['category'] = $canonicalTargetName;
                    if (isset($lab['categoryLabel'])) {
                        $lab['categoryLabel'] = $canonicalTargetName;
                    }
                    $affectedItems++;
                    $changed = true;
                }
            }
            if ($changed) {
                file_put_contents($labFile, json_encode($labs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
        } elseif ($sourceType === 'product') {
            $stmtReassign = $pdo->prepare('UPDATE products SET category = ? WHERE category = ?');
            $stmtReassign->execute([$canonicalTargetName, $sourceName]);
            $affectedItems = $stmtReassign->rowCount();
        } else {
            $stmtReassign = $pdo->prepare('UPDATE posts SET category = ? WHERE type = ? AND category = ?');
            $stmtReassign->execute([$canonicalTargetName, $sourceType, $sourceName]);
            $affectedItems = $stmtReassign->rowCount();
        }
    }

    // 5. Delete category row
    $stmtDelete = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $stmtDelete->execute([$id]);

    $pdo->commit();

    logAdminAction('category.delete', 'category', (string) $id, json_encode([
        'name'           => $sourceName,
        'type'           => $sourceType,
        'action'         => $action,
        'affected_items' => $affectedItems,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Category deleted successfully.',
        'data'    => [
            'id'             => $id,
            'name'           => $sourceName,
            'type'           => $sourceType,
            'action'         => $action,
            'affected_items' => $affectedItems,
        ],
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[categories/delete] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error deleting category.']);
}
