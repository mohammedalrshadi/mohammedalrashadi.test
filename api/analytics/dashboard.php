<?php
// ============================================================
// ADMIN ANALYTICS DASHBOARD API [IMP-033]
// GET /api/analytics/dashboard.php
//
// Generates authoritative summary metrics, zero-filled 7-calendar-day
// activity array, strict 7-day averages, piecewise trends, and
// published-only Most Read / Least Read rankings.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth/guard.php';

header('Content-Type: application/json');

// 1. Enforce Admin Authentication
requireAuth();

// 2. Only GET allowed
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $pdo = getDB();

    // --------------------------------------------------------
    // A. Authoritative Lifetime Totals
    // --------------------------------------------------------

    // 1. Estimated Lifetime Unique Visitors (distinct observed persistent IDs)
    $stmtVisitors = $pdo->query("SELECT COUNT(*) AS total FROM site_visitors");
    $lifetimeVisitors = (int) ($stmtVisitors->fetch()['total'] ?? 0);

    // 2. Lifetime Site-Wide Article Reads (survives article purge)
    $stmtReads = $pdo->query("SELECT COALESCE(SUM(reads_count), 0) AS total FROM daily_site_stats");
    $lifetimeReads = (int) ($stmtReads->fetch()['total'] ?? 0);

    // --------------------------------------------------------
    // B. Zero-Filled Activity Aggregation (English-First)
    // --------------------------------------------------------
    $requestedDays = (isset($_GET['days']) && (int) $_GET['days'] === 30) ? 30 : 7;
    $today = date('Y-m-d');

    if ($requestedDays === 30) {
        // Zero-Filled 30-Calendar-Day Aggregation (On-demand for Admin Studio 30d view)
        $chart30d = [];
        $startDay30 = date('Y-m-d', strtotime('-29 days'));
        for ($i = 29; $i >= 0; $i--) {
            $dateKey = date('Y-m-d', strtotime("-$i days"));
            $dayOfWeek = date('D', strtotime($dateKey));
            $chart30d[$dateKey] = [
                'date'     => $dateKey,
                'day_name' => $dayOfWeek,
                'visitors' => 0,
                'reads'    => 0,
            ];
        }
        $stmtChart30 = $pdo->prepare("
            SELECT stat_date, visitors_count, reads_count
            FROM daily_site_stats
            WHERE stat_date >= :start_date AND stat_date <= :end_date
        ");
        $stmtChart30->execute([
            ':start_date' => $startDay30,
            ':end_date'   => $today,
        ]);
        while ($row = $stmtChart30->fetch(PDO::FETCH_ASSOC)) {
            $d = $row['stat_date'];
            if (isset($chart30d[$d])) {
                $chart30d[$d]['visitors'] = (int) $row['visitors_count'];
                $chart30d[$d]['reads']    = (int) $row['reads_count'];
            }
        }
        $chart30dList = array_values($chart30d);
        $chart7dList  = array_slice($chart30dList, -7);
    } else {
        // Zero-Filled 7-Calendar-Day Aggregation (Original Default Path)
        $chart7d = [];
        $startDay = date('Y-m-d', strtotime('-6 days'));
        for ($i = 6; $i >= 0; $i--) {
            $dateKey = date('Y-m-d', strtotime("-$i days"));
            $dayOfWeek = date('D', strtotime($dateKey));
            $chart7d[$dateKey] = [
                'date'     => $dateKey,
                'day_name' => $dayOfWeek,
                'visitors' => 0,
                'reads'    => 0,
            ];
        }
        $stmtChart = $pdo->prepare("
            SELECT stat_date, visitors_count, reads_count
            FROM daily_site_stats
            WHERE stat_date >= :start_date AND stat_date <= :end_date
        ");
        $stmtChart->execute([
            ':start_date' => $startDay,
            ':end_date'   => $today,
        ]);
        while ($row = $stmtChart->fetch(PDO::FETCH_ASSOC)) {
            $d = $row['stat_date'];
            if (isset($chart7d[$d])) {
                $chart7d[$d]['visitors'] = (int) $row['visitors_count'];
                $chart7d[$d]['reads']    = (int) $row['reads_count'];
            }
        }
        $chart7dList = array_values($chart7d);
        $chart30dList = [];
    }

    // 7-day sums and strict 7-day averages (strict denominator 7)
    $sevenDayVisitors = array_sum(array_column($chart7dList, 'visitors'));
    $sevenDayReads    = array_sum(array_column($chart7dList, 'reads'));
    $avgDailyVisitors = round($sevenDayVisitors / 7, 1);
    $avgDailyReads    = round($sevenDayReads / 7, 1);

    // --------------------------------------------------------
    // C. Previous 7-Day Baseline & Piecewise Trend Calculation
    // --------------------------------------------------------
    $prevStart = date('Y-m-d', strtotime('-13 days'));
    $prevEnd   = date('Y-m-d', strtotime('-7 days'));

    $stmtPrev = $pdo->prepare("
        SELECT 
            COALESCE(SUM(visitors_count), 0) AS prev_visitors,
            COALESCE(SUM(reads_count), 0)    AS prev_reads
        FROM daily_site_stats
        WHERE stat_date >= :prev_start AND stat_date <= :prev_end
    ");
    $stmtPrev->execute([
        ':prev_start' => $prevStart,
        ':prev_end'   => $prevEnd,
    ]);
    $prevRow = $stmtPrev->fetch(PDO::FETCH_ASSOC);
    $prevVisitors = (int) ($prevRow['prev_visitors'] ?? 0);
    $prevReads    = (int) ($prevRow['prev_reads'] ?? 0);

    /**
     * Strictly computes trend without fabricating percentages for 0 baselines.
     */
    $resolveTrend = function (int $current, int $previous): array {
        if ($previous === 0 && $current === 0) {
            return [
                'pct'   => null,
                'state' => 'no_change',
                'label' => '—',
            ];
        }
        if ($previous === 0 && $current > 0) {
            return [
                'pct'   => null,
                'state' => 'new',
                'label' => 'New',
            ];
        }
        $pct = round((($current - $previous) / $previous) * 100, 1);
        $state = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'no_change');
        $label = ($pct > 0 ? '+' : '') . $pct . '%';
        return [
            'pct'   => $pct,
            'state' => $state,
            'label' => $label,
        ];
    };

    $visitorsTrend = $resolveTrend($sevenDayVisitors, $prevVisitors);
    $readsTrend    = $resolveTrend($sevenDayReads, $prevReads);

    // --------------------------------------------------------
    // D. Published-Only Most Read & Least Read Rankings
    // --------------------------------------------------------

    // Most Read (الأكثر قراءة)
    $stmtMost = $pdo->query("
        SELECT id, title, views, image_url
        FROM posts
        WHERE status = 'published'
          AND deleted_at IS NULL
        ORDER BY views DESC, id DESC
        LIMIT 5
    ");
    $mostRead = $stmtMost->fetchAll(PDO::FETCH_ASSOC);

    // Least Read (الأقل قراءة)
    $stmtLeast = $pdo->query("
        SELECT id, title, views, image_url
        FROM posts
        WHERE status = 'published'
          AND deleted_at IS NULL
        ORDER BY views ASC, id ASC
        LIMIT 5
    ");
    $leastRead = $stmtLeast->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------------
    // E. Assemble Standard Response
    // --------------------------------------------------------
        $responseData = [
            'summary' => [
                'estimated_unique_visitors' => $lifetimeVisitors,
                'lifetime_reads'            => $lifetimeReads,
                'seven_day_visitors'        => $sevenDayVisitors,
                'seven_day_reads'           => $sevenDayReads,
                'avg_daily_visitors'        => $avgDailyVisitors,
                'avg_daily_reads'           => $avgDailyReads,
                'visitors_trend'            => $visitorsTrend,
                'reads_trend'               => $readsTrend,
            ],
            'chart_7d'   => $chart7dList,
            'most_read'  => $mostRead,
            'least_read' => $leastRead,
        ];

        if (!empty($chart30dList)) {
            $responseData['chart_30d'] = $chart30dList;
        }

        echo json_encode([
            'success' => true,
            'data'    => $responseData,
        ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[ANALYTICS_DASHBOARD] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'تعذر تحميل بيانات التحليلات.',
    ], JSON_UNESCAPED_UNICODE);
}
