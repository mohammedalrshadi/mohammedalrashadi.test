<?php
// ============================================================
// ARTICLE VIEW TELEMETRY ENDPOINT [IMP-033]
// POST /api/telemetry/view.php
//
// Records an article read after client engagement threshold (3s active dwell),
// validates published article status, applies same-calendar-day per-article
// deduplication, and increments authoritative view counters.
// ============================================================

require_once __DIR__ . '/identity.php';
require_once __DIR__ . '/rate_limit.php';
require_once dirname(__DIR__) . '/db.php';

// 1. Ingestion Guardrails (Method, DNT, GPC, Bots)
check_telemetry_guards();

// 1b. Rate limiting (SEC-07) — same reasoning as visit.php, tracked
// in a separate bucket so heavy article reading can't exhaust a
// budget shared with page-visit beacons.
if (telemetryRateLimitExceeded('view')) {
    http_response_code(204);
    exit;
}
telemetryRateLimitRecord('view');

// 2. Parse and validate post_id payload
$input = json_decode(file_get_contents('php://input'), true);
$postId = isset($input['post_id']) && is_numeric($input['post_id'])
    ? (int) $input['post_id']
    : (isset($_POST['post_id']) && is_numeric($_POST['post_id']) ? (int) $_POST['post_id'] : 0);

if ($postId <= 0) {
    http_response_code(204);
    exit;
}

try {
    $pdo = getDB();

    // 3. Verify target article is currently published and not soft-deleted
    $stmtCheck = $pdo->prepare("
        SELECT id 
        FROM posts 
        WHERE id = :id 
          AND status = 'published' 
          AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmtCheck->execute([':id' => $postId]);
    if (!$stmtCheck->fetch()) {
        http_response_code(204);
        exit;
    }

    // 4. Resolve visitor identity in 'read_only' mode (never mints competing cookies)
    $identity = resolve_telemetry_visitor('read_only');
    $today = date('Y-m-d');

    $pdo->beginTransaction();

    // 5. Same-calendar-day per-article deduplication
    if (!empty($identity['visitor_hash'])) {
        $stmtDedup = $pdo->prepare("
            INSERT IGNORE INTO telemetry_dedup_articles (visitor_hash, post_id, view_date)
            VALUES (:hash, :post_id, :today)
        ");
        $stmtDedup->execute([
            ':hash'    => $identity['visitor_hash'],
            ':post_id' => $postId,
            ':today'   => $today,
        ]);

        // If duplicate view within the same calendar day, skip counter increment
        if ($stmtDedup->rowCount() === 0) {
            $pdo->commit();
            http_response_code(204);
            exit;
        }
    }

    // 6. Authoritative Multi-Table Counter Increments:
    // a. Active article lifetime views counter (posts.views)
    $stmtPost = $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = :post_id");
    $stmtPost->execute([':post_id' => $postId]);

    // b. Site-wide daily reads counter (historical source of truth)
    $stmtDailySite = $pdo->prepare("
        INSERT INTO daily_site_stats (stat_date, visitors_count, reads_count)
        VALUES (:today, 0, 1)
        ON DUPLICATE KEY UPDATE reads_count = reads_count + 1
    ");
    $stmtDailySite->execute([':today' => $today]);

    // c. Per-article daily views counter (historical source of truth)
    $stmtDailyArt = $pdo->prepare("
        INSERT INTO daily_article_stats (stat_date, post_id, views_count)
        VALUES (:today, :post_id, 1)
        ON DUPLICATE KEY UPDATE views_count = views_count + 1
    ");
    $stmtDailyArt->execute([':today' => $today, ':post_id' => $postId]);

    $pdo->commit();

    // 7. Probabilistic maintenance (1% chance)
    if (mt_rand(1, 100) === 1) {
        try {
            $pdo->exec("DELETE FROM telemetry_dedup_articles WHERE view_date < CURDATE() - INTERVAL 2 DAY");
        } catch (Throwable $ignore) {}
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[TELEMETRY_VIEW] Transaction error: ' . $e->getMessage());
}

// 8. Silent 204 No Content response
http_response_code(204);
exit;
