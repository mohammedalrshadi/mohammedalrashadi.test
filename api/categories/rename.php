<?php
// ============================================================
// CATEGORIES — RENAME [REQ-015 Phase 2]
// POST /api/categories/rename.php
//
// Admin only + CSRF protected.
// Body (JSON): { "id": 1, "new_name": "..." }
//
// Renames a category inside a single PDO transaction and updates
// all matching posts (published, hidden, and soft-deleted).
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
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body  = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير صالحة.']);
    exit;
}

$id = isset($input['id']) ? (int) $input['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف التصنيف مطلوب.']);
    exit;
}

$rawName = isset($input['new_name']) ? (string) $input['new_name'] : '';
$newName = normalizeCategoryWhitespace($rawName);

$validationError = validateCategoryName($newName);
if ($validationError !== null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $validationError]);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // 1. SELECT category FOR UPDATE
    $stmtSelect = $pdo->prepare('SELECT id, name, type FROM categories WHERE id = ? FOR UPDATE');
    $stmtSelect->execute([$id]);
    $category = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'التصنيف غير موجود.']);
        exit;
    }

    $oldName = $category['name'];
    $type    = $category['type'];

    // 2. If name is identical, no-op
    if ($oldName === $newName) {
        $pdo->rollBack();
        echo json_encode([
            'success' => true,
            'message' => 'لم يتم تغيير اسم التصنيف.',
            'data'    => [
                'id'            => $id,
                'name'          => $newName,
                'type'          => $type,
                'updated_posts' => 0,
            ],
        ]);
        exit;
    }

    // 3. Collision check within the same type (excluding current id)
    $collision = findCategoryMatch($pdo, $newName, $type, $id);
    if ($collision) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'التصنيف موجود بالفعل لنفس النوع (' . $collision['name'] . ').',
        ]);
        exit;
    }

    // 4. Update categories table
    $stmtUpdateCat = $pdo->prepare('UPDATE categories SET name = ?, updated_at = NOW() WHERE id = ?');
    $stmtUpdateCat->execute([$newName, $id]);

    // 5. Update ALL matching entity records scoped by type
    $updatedItems = 0;
    if ($type === 'lab') {
        $labFile = dirname(__DIR__) . '/data/lab_experiments.json';
        if (file_exists($labFile)) {
            $labs = json_decode(file_get_contents($labFile), true) ?: [];
            $changed = false;
            foreach ($labs as &$lab) {
                if (isset($lab['category']) && strcasecmp($lab['category'], $oldName) === 0) {
                    $lab['category'] = $newName;
                    if (isset($lab['categoryLabel'])) {
                        $lab['categoryLabel'] = $newName;
                    }
                    $updatedItems++;
                    $changed = true;
                }
            }
            if ($changed) {
                file_put_contents($labFile, json_encode($labs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
        }
    } elseif ($type === 'product') {
        $stmtUpdate = $pdo->prepare('UPDATE products SET category = ? WHERE category = ?');
        $stmtUpdate->execute([$newName, $oldName]);
        $updatedItems = $stmtUpdate->rowCount();
    } else {
        // blog, project, or project in posts table
        $stmtUpdate = $pdo->prepare('UPDATE posts SET category = ? WHERE type = ? AND category = ?');
        $stmtUpdate->execute([$newName, $type, $oldName]);
        $updatedItems = $stmtUpdate->rowCount();
    }

    $pdo->commit();

    logAdminAction('category.rename', 'category', (string) $id, json_encode([
        'old_name'      => $oldName,
        'new_name'      => $newName,
        'type'          => $type,
        'updated_items' => $updatedItems,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'message' => 'Category renamed and linked items updated successfully.',
        'data'    => [
            'id'            => $id,
            'name'          => $newName,
            'type'          => $type,
            'updated_items' => $updatedItems,
            'updated_posts' => $updatedItems,
        ],
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'التصنيف موجود بالفعل لنفس النوع.']);
        exit;
    }

    error_log('[categories/rename] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while renaming category.']);
}
