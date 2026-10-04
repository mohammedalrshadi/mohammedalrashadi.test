<?php
// ============================================================
// SITE VISIT TELEMETRY ENDPOINT [IMP-033]
// POST /api/telemetry/visit.php
//
// Records a site visit, manages the persistent visitor cookie,
// registers the visitor in site_visitors, and increments daily_site_stats
// using same-calendar-day deduplication.
// ============================================================

require_once __DIR__ . '/identity.php';
require_once __DIR__ . '/rate_limit.php';
require_once dirname(__DIR__) . '/db.php';

// 1. Ingestion Guardrails (Method, DNT, GPC, Bots)
check_telemetry_guards();

// 1b. Rate limiting (SEC-07) — checked before any identity
// resolution or DB work, so a throttled IP costs one cheap file
// check. Same silent-204 response as every other guard on this
// endpoint; a beacon is never a place to surface a 429.
if (telemetryRateLimitExceeded('visit')) {
    http_response_code(204);
    exit;
}
telemetryRateLimitRecord('visit');

// 2. Resolve or create persistent visitor identity
$identity = resolve_telemetry_visitor('create_if_missing');

if (empty($identity['visitor_hash'])) {
    http_response_code(204);
    exit;
}

$today = date('Y-m-d');

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // 3. Upsert persistent visitor registry (Lifetime Unique Visitors)
    $stmtVisitor = $pdo->prepare("
        INSERT INTO site_visitors (visitor_hash, first_seen_date, last_seen_date)
        VALUES (:hash, :today_first, :today_last)
        ON DUPLICATE KEY UPDATE last_seen_date = :today_update
    ");
    $stmtVisitor->execute([
        ':hash'         => $identity['visitor_hash'],
        ':today_first'  => $today,
        ':today_last'   => $today,
        ':today_update' => $today,
    ]);

    // 4. Same-calendar-day visitor deduplication
    $stmtDedup = $pdo->prepare("
        INSERT IGNORE INTO telemetry_dedup_visitors (visitor_hash, visit_date)
        VALUES (:hash, :today)
    ");
    $stmtDedup->execute([
        ':hash'  => $identity['visitor_hash'],
        ':today' => $today,
    ]);

    // 5. If new calendar-day observation, increment daily_site_stats.visitors_count
    if ($stmtDedup->rowCount() > 0) {
        $stmtDaily = $pdo->prepare("
            INSERT INTO daily_site_stats (stat_date, visitors_count, reads_count)
            VALUES (:today, 1, 0)
            ON DUPLICATE KEY UPDATE visitors_count = visitors_count + 1
        ");
        $stmtDaily->execute([':today' => $today]);
    }

    $pdo->commit();

    // 6. Probabilistic maintenance (1% chance)
    if (mt_rand(1, 100) === 1) {
        try {
            $pdo->exec("DELETE FROM telemetry_dedup_visitors WHERE visit_date < CURDATE() - INTERVAL 2 DAY");
        } catch (Throwable $ignore) {}
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[TELEMETRY_VISIT] Transaction error: ' . $e->getMessage());
}

// 7. Silent 204 No Content response
http_response_code(204);
exit;
