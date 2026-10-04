<?php
// ============================================================
// JOURNEY MILESTONES — LIST API
// GET /api/journey/list.php
// Public callers (no admin session) get published-only, ordered
// by sort_order. Authenticated admin callers get everything,
// including drafts, when ?scope=admin is passed.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json; charset=utf-8');

$scope = isset($_GET['scope']) ? trim($_GET['scope']) : 'public';
$isAdminScope = ($scope === 'admin' && isAdminLoggedIn());

try {
    $pdo = getDB();

    if ($isAdminScope) {
        $stmt = $pdo->query(
            "SELECT id, title, period_label, description, category, icon, sort_order, status, created_at, updated_at
               FROM journey_milestones
              WHERE deleted_at IS NULL
              ORDER BY sort_order ASC, id ASC"
        );
    } else {
        $stmt = $pdo->query(
            "SELECT id, title, period_label, description, category, icon, sort_order
               FROM journey_milestones
              WHERE deleted_at IS NULL AND status = 'published'
              ORDER BY sort_order ASC, id ASC"
        );
    }

    $milestones = $stmt->fetchAll();

    echo json_encode([
        'success'    => true,
        'count'      => count($milestones),
        'milestones' => $milestones,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('[journey/list] DB error: ' . $e->getMessage());
    // Fail gracefully: an empty list means journey.php falls back
    // to its existing "honest empty state" — never a broken page.
    echo json_encode(['success' => true, 'count' => 0, 'milestones' => []], JSON_UNESCAPED_UNICODE);
}
