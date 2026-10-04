<?php
// ============================================================
// TELEMETRY ANALYTICS — Server-Side Protected Admin Page
// Authentic reader telemetry, visitor aggregation, and article
// engagement rankings powered directly by api/analytics/dashboard.php.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'analytics';
$pageTitle = 'Platform Analytics';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- =====================================================
     1. WORKSPACE HEADER & CONTEXT BAR
====================================================== -->
<header class="admin-page-header">
    <div class="header-titles">
        <div class="breadcrumb" aria-label="breadcrumb">
            <a href="index.php">Admin Studio</a>
            <span class="breadcrumb-sep">/</span>
            <span>Audience &amp; Insights</span>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">Analytics</span>
        </div>
        <h1 class="page-title">Platform Telemetry &amp; Analytics</h1>
        <p class="page-description">Authoritative, privacy-preserving reader analytics, unique visitor estimation, and engagement patterns.</p>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-secondary" onclick="loadAnalyticsData()">
            <i class="fas fa-arrows-rotate"></i>
            <span>Refresh Telemetry</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. SUMMARY METRICS CARDS (REAL DATABASE TELEMETRY)
====================================================== -->
<section class="stats-grid" aria-label="Authoritative Metrics">
    <!-- Lifetime Unique Visitors -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Lifetime Unique Visitors</span>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span id="analyticsVisitorsTrend" class="stat-trend trend-neutral"><i class="fas fa-minus"></i> 0%</span>
                <i class="fas fa-users stat-icon" aria-hidden="true"></i>
            </div>
        </div>
        <div id="analyticsLifetimeVisitors" class="stat-value">0</div>
        <div class="stat-meta">
            <span>Authoritative deduplicated visitors</span>
        </div>
    </div>

    <!-- Lifetime Article Reads -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Lifetime Article Reads</span>
            <div style="display: flex; align-items: center; gap: 6px;">
                <span id="analyticsReadsTrend" class="stat-trend trend-neutral"><i class="fas fa-minus"></i> 0%</span>
                <i class="far fa-eye stat-icon" aria-hidden="true"></i>
            </div>
        </div>
        <div id="analyticsLifetimeReads" class="stat-value">0</div>
        <div class="stat-meta">
            <span>Aggregated read engagements</span>
        </div>
    </div>

    <!-- 7-Day Active Visitors -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">7-Day Visitors</span>
            <i class="fas fa-chart-line stat-icon" aria-hidden="true"></i>
        </div>
        <div id="analyticsSevenDayVisitors" class="stat-value">0</div>
        <div class="stat-meta">
            <span>Trailing 7-calendar days</span>
        </div>
    </div>

    <!-- 7-Day Total Reads -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">7-Day Reads</span>
            <i class="far fa-file-lines stat-icon" aria-hidden="true"></i>
        </div>
        <div id="analyticsSevenDayReads" class="stat-value">0</div>
        <div class="stat-meta">
            <span>Trailing 7-calendar days</span>
        </div>
    </div>

    <!-- Daily Average Visitors -->
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Daily Average Visitors</span>
            <i class="far fa-clock stat-icon" aria-hidden="true"></i>
        </div>
        <div id="analyticsAvgDailyVisitors" class="stat-value">0</div>
        <div class="stat-meta">
            <span>Visitors / day</span>
        </div>
    </div>
</section>


<!-- =====================================================
     3. 7-DAY TELEMETRY ACTIVITY & BREAKDOWN
====================================================== -->
<div class="analytics-grid">

    <!-- Left: 7-Day Chart & Daily Table -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- SVG Visual Chart -->
        <section class="admin-card" style="margin-bottom: 0;">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">7-Calendar-Day Activity</h2>
                    <p class="card-subtitle">Zero-filled daily telemetry tracking visitors and article reading sessions.</p>
                </div>
            </div>

            <div style="padding: 10px 0; overflow-x: auto;">
                <svg class="weekly-bar-chart" viewBox="0 0 320 180" width="100%" style="max-height: 220px; display: block;" aria-label="7-Day Activity Chart">
                    <line x1="45" y1="24" x2="310" y2="24" stroke="var(--border-subtle)" stroke-dasharray="2 2" />
                    <text x="38" y="27" text-anchor="end" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">400</text>

                    <line x1="45" y1="59" x2="310" y2="59" stroke="var(--border-subtle)" stroke-dasharray="2 2" />
                    <text x="38" y="62" text-anchor="end" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">300</text>

                    <line x1="45" y1="94" x2="310" y2="94" stroke="var(--border-subtle)" stroke-dasharray="2 2" />
                    <text x="38" y="97" text-anchor="end" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">200</text>

                    <line x1="45" y1="129" x2="310" y2="129" stroke="var(--border-subtle)" stroke-dasharray="2 2" />
                    <text x="38" y="132" text-anchor="end" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">100</text>

                    <line x1="45" y1="150" x2="310" y2="150" stroke="var(--border-medium)" />
                    <text x="38" y="153" text-anchor="end" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">0</text>

                    <rect x="58" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 1</title></rect>
                    <rect x="94" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 2</title></rect>
                    <rect x="130" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 3</title></rect>
                    <rect x="166" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 4</title></rect>
                    <rect x="202" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 5</title></rect>
                    <rect x="238" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 6</title></rect>
                    <rect x="274" y="150" width="22" height="0" fill="var(--accent)" rx="2"><title>Day 7</title></rect>

                    <text x="69" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                    <text x="105" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                    <text x="141" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                    <text x="177" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                    <text x="213" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                    <text x="249" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                    <text x="285" y="168" text-anchor="middle" fill="var(--text-muted)" font-family="var(--font-mono)" font-size="10">—</text>
                </svg>
            </div>
        </section>

        <!-- Daily Table Breakdown -->
        <section class="table-container" style="margin-bottom: 0;">
            <div class="admin-card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-subtle);">
                <div>
                    <h3 class="card-title">Daily Telemetry Log</h3>
                    <p class="card-subtitle">Exact recorded visitor and reading values by date.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th style="text-align: right;">Unique Visitors</th>
                            <th style="text-align: right;">Article Reads</th>
                        </tr>
                    </thead>
                    <tbody id="analyticsDailyTableBody">
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 28px 20px; color: var(--text-muted);">
                                Loading daily metrics...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <!-- Right: Ranked Content & Privacy Architecture -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Most Read Articles -->
        <section class="admin-card" style="margin-bottom: 0;">
            <div class="admin-card-header">
                <div>
                    <h3 class="card-title" style="color: var(--warning);"><i class="fas fa-fire"></i> Most Read Articles</h3>
                    <p class="card-subtitle">Highest reader engagement.</p>
                </div>
            </div>

            <ul id="analyticsMostReadList" class="ranked-content-list">
                <li class="ranked-empty-state">Loading rankings...</li>
            </ul>
        </section>

        <!-- Least Read Articles / Needs Amplification -->
        <section class="admin-card" style="margin-bottom: 0;">
            <div class="admin-card-header">
                <div>
                    <h3 class="card-title" style="color: var(--danger);"><i class="fas fa-chart-line-down"></i> Needs Amplification</h3>
                    <p class="card-subtitle">Lowest reader engagement.</p>
                </div>
            </div>

            <ul id="analyticsLeastReadList" class="ranked-content-list">
                <li class="ranked-empty-state">Loading rankings...</li>
            </ul>
        </section>

        <!-- Privacy & Telemetry Architecture -->
        <section class="admin-card" style="margin-bottom: 0; background: rgba(34, 211, 238, 0.03); border: 1px solid rgba(34, 211, 238, 0.2);">
            <div class="admin-card-header">
                <h3 class="card-title" style="font-size: 13.5px; color: var(--accent); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-user-shield"></i> Privacy Architecture
                </h3>
            </div>
            <p style="font-size: 12px; color: var(--text-secondary); line-height: 1.6; margin: 0;">
                All metrics are computed server-side via salted SHA-256 hash deduplication (<code style="font-family: var(--font-mono); font-size: 11px;">telemetry_dedup_*</code>). No external tracking beacons, advertising cookies, or third-party analytics scripts run on any public page.
            </p>
        </section>

    </div>

</div>

<?php
$pageScripts = ['js/analytics.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
