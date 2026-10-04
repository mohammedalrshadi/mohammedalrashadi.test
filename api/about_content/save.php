<?php
// ============================================================
// ABOUT CONTENT BLOCKS — CREATE / UPDATE API
// POST /api/about_content/save.php
// Requires: authenticated admin session + valid CSRF token.
// Payload (JSON): { id?, block_type: 'principle'|'focus_tag',
//                    group_label?, icon?, title, description?,
//                    sort_order }
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$id          = isset($input['id']) ? (int) $input['id'] : 0;
$blockType   = trim((string) ($input['block_type'] ?? ''));
$groupLabel  = trim((string) ($input['group_label'] ?? ''));
$icon        = trim((string) ($input['icon'] ?? ''));
$title       = trim((string) ($input['title'] ?? ''));
$description = trim((string) ($input['description'] ?? ''));
$sortOrder   = isset($input['sort_order']) ? (int) $input['sort_order'] : 0;

if (!in_array($blockType, ['principle', 'focus_tag'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'block_type must be "principle" or "focus_tag".'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($blockType === 'focus_tag' && $groupLabel === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Group label is required for a focus tag (e.g. "Languages & Core").'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($blockType === 'principle' && $description === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Description is required for a principle card.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$title      = mb_substr($title, 0, 150);
$groupLabel = $groupLabel !== '' ? mb_substr($groupLabel, 0, 100) : null;
$icon       = $icon !== '' ? mb_substr($icon, 0, 60) : null;
$description = $description !== '' ? $description : null;

try {
    $pdo = getDB();

    if ($id > 0) {
        $stmt = $pdo->prepare(
            "UPDATE about_content_blocks
                SET block_type = :block_type, group_label = :group_label, icon = :icon,
                    title = :title, description = :description, sort_order = :sort_order
              WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            ':block_type'  => $blockType,
            ':group_label' => $groupLabel,
            ':icon'        => $icon,
            ':title'       => $title,
            ':description' => $description,
            ':sort_order'  => $sortOrder,
            ':id'          => $id,
        ]);

        $check = $pdo->prepare("SELECT 1 FROM about_content_blocks WHERE id = :id AND deleted_at IS NULL");
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Content block not found.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        logAdminAction('about.update', 'about_block', (string) $id, json_encode([
            'title'      => $title,
            'block_type' => $blockType,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => 'Content block updated.', 'id' => $id], JSON_UNESCAPED_UNICODE);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO about_content_blocks (block_type, group_label, icon, title, description, sort_order)
             VALUES (:block_type, :group_label, :icon, :title, :description, :sort_order)"
        );
        $stmt->execute([
            ':block_type'  => $blockType,
            ':group_label' => $groupLabel,
            ':icon'        => $icon,
            ':title'       => $title,
            ':description' => $description,
            ':sort_order'  => $sortOrder,
        ]);

        $newId = (int) $pdo->lastInsertId();

        logAdminAction('about.create', 'about_block', (string) $newId, json_encode([
            'title'      => $title,
            'block_type' => $blockType,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'message' => 'Content block created.', 'id' => $newId], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    error_log('[about_content/save] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while saving the content block.'], JSON_UNESCAPED_UNICODE);
}
