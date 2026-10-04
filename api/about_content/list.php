<?php
// ============================================================
// ABOUT CONTENT BLOCKS — LIST API
// GET /api/about_content/list.php
// Returns Principles cards and Focus Area tags shown on
// about.php. No admin/public distinction needed — this content
// has no draft state, it's either present or absent.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();

    $stmt = $pdo->query(
        "SELECT id, block_type, group_label, icon, title, description, sort_order
           FROM about_content_blocks
          WHERE deleted_at IS NULL
          ORDER BY block_type ASC, group_label ASC, sort_order ASC, id ASC"
    );
    $rows = $stmt->fetchAll();

    $principles = [];
    $focusGroups = []; // group_label => [tags]

    foreach ($rows as $row) {
        if ($row['block_type'] === 'principle') {
            $principles[] = $row;
        } else {
            $group = $row['group_label'] ?? 'Other';
            if (!isset($focusGroups[$group])) {
                $focusGroups[$group] = [];
            }
            $focusGroups[$group][] = $row;
        }
    }

    echo json_encode([
        'success'     => true,
        'principles'  => $principles,
        'focus_groups' => $focusGroups,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[about_content/list] DB error: ' . $e->getMessage());
    echo json_encode(['success' => true, 'principles' => [], 'focus_groups' => []], JSON_UNESCAPED_UNICODE);
}
