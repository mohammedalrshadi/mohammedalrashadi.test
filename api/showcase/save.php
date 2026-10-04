<?php
// ============================================================
// HOME SHOWCASE — SAVE (CREATE / UPDATE)
// POST /api/showcase/save.php
//
// Requires: authenticated admin session + CSRF token
// Payload: JSON or form-data
//
// Reconciled with live schema:
//   Uses `reference_id` as the generic entity reference column:
//     - product: reference_id = products.id
//     - project: reference_id = posts.id (also sets post_id for backward compat)
//     - writing: reference_id = posts.id (also sets post_id for backward compat)
//     - image: uses image_url, alt_text, link_url (reference_id = NULL)
// ============================================================

require_once dirname(dirname(__DIR__)) . '/api/config.php';
require_once dirname(dirname(__DIR__)) . '/api/db.php';
require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once __DIR__ . '/helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
requireCSRF();

$rawBody = file_get_contents('php://input');
$input   = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id       = isset($input['id']) ? (int)$input['id'] : 0;
$itemType = normalizeShowcaseType($input['item_type'] ?? 'project');

// Extract generic reference_id (with fallback to post_id or product_id for compatibility)
$referenceId = null;
if (!empty($input['reference_id'])) {
    $referenceId = (int)$input['reference_id'];
} elseif (!empty($input['product_id']) && $itemType === 'product') {
    $referenceId = (int)$input['product_id'];
} elseif (!empty($input['post_id'])) {
    $referenceId = (int)$input['post_id'];
}

$isEnabled = isset($input['is_enabled']) ? ((int)$input['is_enabled'] ? 1 : 0) : 1;
$sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : null;

$titleOverride = isset($input['title_override']) ? sanitizePlain(trim((string)$input['title_override'])) : null;
$descOverride  = isset($input['description_override']) ? sanitizePlain(trim((string)$input['description_override'])) : null;
$imageUrl      = isset($input['image_url']) ? trim((string)$input['image_url']) : null;
$altText       = isset($input['alt_text']) ? sanitizePlain(trim((string)$input['alt_text'])) : null;
$linkUrl       = isset($input['link_url']) ? trim((string)$input['link_url']) : null;

// Empty strings converted to null for optional overrides
$titleOverride = ($titleOverride === '') ? null : $titleOverride;
$descOverride  = ($descOverride === '') ? null : $descOverride;
$imageUrl      = ($imageUrl === '') ? null : $imageUrl;
$altText       = ($altText === '') ? null : $altText;
$linkUrl       = ($linkUrl === '') ? null : $linkUrl;

$errors = [];
if ($linkUrl !== null && !isSafeShowcaseLink($linkUrl)) {
    $errors[] = 'Link URL must start with / or http:// or https://.'; // DC-003
}
$postId = null;

// Type-specific validations
if ($itemType === 'product') {
    if (!$referenceId || $referenceId <= 0) {
        $errors[] = 'A valid Store Product must be selected.';
    }
} elseif ($itemType === 'project') {
    if (!$referenceId || $referenceId <= 0) {
        $errors[] = 'A valid Engineering Project must be selected.';
    } else {
        $postId = $referenceId; // Preserve backward compatibility in post_id column
    }
} elseif ($itemType === 'writing') {
    if (!$referenceId || $referenceId <= 0) {
        $errors[] = 'A valid Writing post must be selected.';
    } else {
        $postId = $referenceId; // Preserve backward compatibility in post_id column
    }
} elseif ($itemType === 'image') {
    $referenceId = null;
    $postId = null;
    if (empty($imageUrl)) {
        $errors[] = 'An Image URL or asset path is required for image showcase items.';
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $errors),
        'errors'  => $errors
    ]);
    exit;
}

try {
    $pdo = getDB();

    // 1. Verify existence of referenced product if applicable
    if ($itemType === 'product' && $referenceId > 0) {
        $chk = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
        $chk->execute([$referenceId]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Selected product does not exist.']);
            exit;
        }
    }

    // 2. Verify existence of referenced project/writing if applicable
    if (($itemType === 'project' || $itemType === 'writing') && $referenceId > 0) {
        $allowedPostTypes = ($itemType === 'project') ? ['project', 'project'] : ['blog', 'article'];
        $placeholders = implode(',', array_fill(0, count($allowedPostTypes), '?'));
        
        $sql = "SELECT id FROM posts WHERE id = ? AND type IN ($placeholders) AND deleted_at IS NULL LIMIT 1";
        $params = array_merge([$referenceId], $allowedPostTypes);
        
        $chk = $pdo->prepare($sql);
        $chk->execute($params);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Selected ' . $itemType . ' content does not exist.']);
            exit;
        }
    }

    if ($id > 0) {
        // UPDATE existing showcase item
        $existing = $pdo->prepare("SELECT id FROM home_showcase_items WHERE id = ? LIMIT 1");
        $existing->execute([$id]);
        if (!$existing->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Showcase item not found.']);
            exit;
        }

        $sql = "UPDATE home_showcase_items 
                SET item_type = ?, reference_id = ?, post_id = ?, 
                    title_override = ?, description_override = ?, 
                    image_url = ?, alt_text = ?, link_url = ?, is_enabled = ? ";
        $params = [
            $itemType, $referenceId, $postId,
            $titleOverride, $descOverride,
            $imageUrl, $altText, $linkUrl, $isEnabled
        ];

        if ($sortOrder !== null) {
            $sql .= ", sort_order = ? ";
            $params[] = $sortOrder;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        logAdminAction('showcase.update', 'home_showcase_item', (string) $id, json_encode([
            'item_type' => $itemType,
            'title'     => $titleOverride,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true,
            'message' => 'Showcase item updated successfully.',
            'id'      => $id
        ]);

    } else {
        // CREATE new showcase item
        if ($sortOrder === null) {
            $ordStmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM home_showcase_items");
            $sortOrder = (int)$ordStmt->fetchColumn();
        }

        $sql = "INSERT INTO home_showcase_items 
                (item_type, reference_id, post_id, title_override, description_override, 
                 image_url, alt_text, link_url, is_enabled, sort_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $itemType, $referenceId, $postId,
            $titleOverride, $descOverride,
            $imageUrl, $altText, $linkUrl, $isEnabled, $sortOrder
        ]);

        $newId = (int)$pdo->lastInsertId();

        logAdminAction('showcase.create', 'home_showcase_item', (string) $newId, json_encode([
            'item_type' => $itemType,
            'title'     => $titleOverride,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true,
            'message' => 'Showcase item added successfully.',
            'id'      => $newId
        ]);
    }

} catch (Exception $e) {
    error_log('[api/showcase/save.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while saving showcase item.']);
}
