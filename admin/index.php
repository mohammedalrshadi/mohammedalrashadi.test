<?php
// ============================================================
// DASHBOARD / OVERVIEW — Server-Side Protected Entry Point
// Redesigned as MOHAMMED ALRASHADI — Admin Studio
// Aesthetic Direction: Editorial Systems Modernism
// Strictly maintains all DOM IDs expected by admin/js/dashboard.js.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$platformCounts = [];
try {
    $pdo = getDB();
    $platformCounts = getPlatformEntityCounts($pdo);
} catch (Throwable $e) {
    error_log('[admin/index.php] Error loading platform counts: ' . $e->getMessage());
}

$activeNav = 'dashboard';
$pageTitle = 'Dashboard';

require __DIR__ . '/partials/layout_top.php';

// $adminFirstName resolved in global top bar (layout_top.php)
$adminFirstName = isset($_gtbFirstName) ? $_gtbFirstName : 'Mohammed';
?>

<!-- =====================================================
     1. STUDIO WORKBENCH HEADER & CONTEXT STRIP
     Answers: "Where am I?" and "What actions are relevant now?"
====================================================== -->
<header class="admin-page-header studio-workbench-header">
    <div class="header-titles">
        <div class="studio-status-strip">
            <span class="studio-monogram-tag">SYS.OP-01</span>
            <span class="studio-status-dot"></span>
            <span class="studio-status-text">OPERATIONAL POSTURE: NOMINAL</span>
            <span class="studio-status-sep">/</span>
            <span class="studio-status-sync"><i class="fas fa-clock" style="font-size: 10px;"></i> LAST AUDIT: <span id="dashboardLastUpdated" style="color: var(--text-secondary);">Synchronizing...</span></span>
        </div>
        <h1 class="page-title" id="dashboardGreeting">Studio Operations &amp; Workbench</h1>
        <p class="page-description">Welcome back, <?= htmlspecialchars($adminFirstName) ?>. Unified editorial operations, technical publishing, visitor moderation, and system telemetry.</p>
    </div>
    <!-- Direct Operational Action Palette -->
    <div class="header-actions" id="dashboardHeaderActions">
        <a href="articles-edit.php" class="btn btn-primary">
            <i class="fas fa-pen-nib" aria-hidden="true"></i>
            <span>New Draft</span>
        </a>
        <a href="projects.php" class="btn btn-secondary">
            <i class="fas fa-code-branch" aria-hidden="true"></i>
            <span>Log Project</span>
        </a>
        <a href="reviews.php" class="btn btn-secondary">
            <i class="far fa-comments" aria-hidden="true"></i>
            <span>Moderation</span>
            <span class="nav-badge" id="moderateReviewsBadge" style="<?= ($pendingReviewsCount > 0 ? '' : 'display:none;') ?> margin-left: 6px;"><?= (int)$pendingReviewsCount ?></span>
        </a>
    </div>
</header>


<!-- =====================================================
     2. OPERATIONAL ATTENTION LEDGER
     Answers: "What requires my attention?" immediately at the top.
====================================================== -->
<section id="dashboardAttentionPanel" class="admin-card operational-attention-bar" aria-label="Operational Attention">
    <div class="attention-bar-header">
        <div class="attention-header-left">
            <div class="attention-pulse-indicator">
                <span class="pulse-ring"></span>
                <span class="pulse-dot"></span>
            </div>
            <div>
                <h2 class="attention-heading">Operational Attention Ledger</h2>
                <p class="attention-subheading">Active backlog items requiring editorial approval, visibility audit, or moderation.</p>
            </div>
        </div>
        <span id="attentionStateBadge" class="status-badge status-neutral">Auditing backlog...</span>
    </div>

    <div class="attention-rows-track">
        <!-- Review Moderation Dispatch Row -->
        <a href="reviews.php" id="attentionRowReviews" class="attention-dispatch-row">
            <div class="dispatch-left">
                <span id="attentionReviewIndicator" class="attention-status-indicator status-warning">
                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                </span>
                <div class="dispatch-info">
                    <div class="dispatch-title">Visitor Reviews Moderation</div>
                    <div class="dispatch-meta" id="statTotalReviewsMeta">Checking unapproved community testimonials...</div>
                </div>
            </div>
            <span id="attentionBtnReviews" class="btn btn-secondary btn-sm">Moderate</span>
        </a>

        <!-- Draft & Hidden Articles Dispatch Row -->
        <a href="articles.php?status=draft" id="attentionRowArticles" class="attention-dispatch-row">
            <div class="dispatch-left">
                <span id="attentionArticlesIndicator" class="attention-status-indicator status-warning">
                    <i class="far fa-file-lines" aria-hidden="true"></i>
                </span>
                <div class="dispatch-info">
                    <div class="dispatch-title">Draft &amp; Hidden Writing (<span id="attentionHiddenArticles">0</span>)</div>
                    <div class="dispatch-meta" id="attentionArticlesMeta">Checking unpublished writing...</div>
                </div>
            </div>
            <span id="attentionBtnArticles" class="btn btn-secondary btn-sm">Review</span>
        </a>

        <!-- Draft & Hidden Projects Dispatch Row -->
        <a href="projects.php?status=hidden" id="attentionRowAchievements" class="attention-dispatch-row">
            <div class="dispatch-left">
                <span id="attentionAchievementsIndicator" class="attention-status-indicator status-warning">
                    <i class="fas fa-medal" aria-hidden="true"></i>
                </span>
                <div class="dispatch-info">
                    <div class="dispatch-title">Draft &amp; Hidden Projects (<span id="attentionHiddenAchievements">0</span>)</div>
                    <div class="dispatch-meta" id="attentionAchievementsMeta">Checking unpublished projects...</div>
                </div>
            </div>
            <span id="attentionBtnAchievements" class="btn btn-secondary btn-sm">Review</span>
        </a>

        <!-- Nominal All-Clear Reassurance State -->
        <div id="attentionAllClear" style="display:none;" class="attention-all-clear-message">
            <i class="fas fa-check-circle" style="margin-right: 6px;"></i>
            <span>All systems nominal — zero pending review items and no orphaned drafts.</span>
        </div>
    </div>
</section>


<!-- =====================================================
     3. PUBLISHING POSTURE & CONTENT LEDGER
     Answers: "What am I currently managing?" in an integrated,
     high-density Swiss Modernist architectural strip.
====================================================== -->
<section class="publishing-posture-ledger" aria-label="Studio Publishing Posture">
    <!-- Segment 1: Published Writing -->
    <div class="ledger-segment">
        <div class="ledger-label">
            <span>WRITING</span>
            <i class="far fa-file-alt" aria-hidden="true"></i>
        </div>
        <div class="ledger-primary-stat">
            <span id="statPublishedArticles" class="ledger-number">0</span>
            <span class="ledger-unit">live</span>
        </div>
        <div class="ledger-sub-stat">
            <span id="statTotalArticlesMeta">0 hidden</span>
            <span id="statPublishedArticlesMeta" style="display: none;">0 published</span>
            <span id="statTotalArticles" style="display:none;">0</span>
            <span id="statDraftArticlesMeta" style="display:none;">0</span>
        </div>
    </div>

    <!-- Segment 2: Reader Telemetry (7 Days) -->
    <div class="ledger-segment">
        <div class="ledger-label">
            <span>READERSHIP (7D)</span>
            <i class="far fa-eye" aria-hidden="true"></i>
        </div>
        <div class="ledger-primary-stat">
            <span id="quickStatsTotalReads" class="ledger-number">0</span>
            <span id="quickStatsReadsTrend" class="stat-trend trend-neutral"><i class="fas fa-minus"></i> 0%</span>
        </div>
        <div class="ledger-sub-stat">
            <span id="statReadsMeta">Live reader velocity</span>
        </div>
    </div>

    <!-- Segment 3: Engineering Projects -->
    <div class="ledger-segment">
        <div class="ledger-label">
            <span>PROJECTS</span>
            <i class="fas fa-code-branch" aria-hidden="true"></i>
        </div>
        <div class="ledger-primary-stat">
            <span class="ledger-number"><?= (int)($platformCounts['projects']['published'] ?? 0) ?></span>
            <span class="ledger-unit">active</span>
        </div>
        <div class="ledger-sub-stat">
            <span><?= (int)($platformCounts['projects']['total'] ?? 0) ?> total logged</span>
        </div>
    </div>

    <!-- Segment 4: Digital Assets & Lab -->
    <div class="ledger-segment">
        <div class="ledger-label">
            <span>STORE &amp; LAB</span>
            <i class="fas fa-store" aria-hidden="true"></i>
        </div>
        <div class="ledger-primary-stat">
            <span class="ledger-number"><?= (int)($platformCounts['products']['published'] ?? 0) ?></span>
            <span class="ledger-unit">store</span>
        </div>
        <div class="ledger-sub-stat">
            <span><?= (int)($platformCounts['products']['downloads'] ?? 0) ?> dl · <?= (int)($platformCounts['labs']['total'] ?? 0) ?> lab</span>
        </div>
    </div>

    <!-- Segment 5: Feedback Moderation -->
    <div class="ledger-segment">
        <div class="ledger-label">
            <span>MODERATION</span>
            <i class="far fa-comments" aria-hidden="true"></i>
        </div>
        <div class="ledger-primary-stat">
            <span id="statPendingReviews" class="ledger-number">0</span>
            <span class="ledger-unit">pending</span>
        </div>
        <div class="ledger-sub-stat">
            <span id="statPendingReviewsMeta" style="display:none;">0</span>
            <span id="statTotalReviews" style="display:none;">0</span>
            <span>Community reviews</span>
        </div>
    </div>
</section>


<!-- =====================================================
     4. ASYMMETRIC STUDIO WORKBENCH (60% / 40%)
     Answers: "What changed recently?" & "System Telemetry"
====================================================== -->
<div class="studio-workbench-split">

    <!-- ── LEFT COLUMN (60%): Editorial Stream & Content Matrix ── -->
    <div class="studio-primary-col">

        <!-- Module A: Recent Activity Pulse (Answers: "What changed recently?") -->
        <section class="admin-card recent-activity-panel" aria-label="Recent Studio Activity">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Editorial &amp; Platform Pulse</h2>
                    <p class="card-subtitle">Real-time audit log of content revisions, reviews, and system events.</p>
                </div>
                <a href="audit-log.php" class="btn btn-secondary btn-sm" style="font-size: 11px;">
                    <i class="fas fa-clipboard-list" style="margin-right: 4px;"></i> Full Audit Log
                </a>
            </div>

            <div class="recent-activity-list-container" style="flex: 1;">
                <ul id="recentActivityList" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column;">
                    <li style="color: var(--text-muted); font-size: 13px; padding: 16px;">Synchronizing activity events...</li>
                </ul>
                <div id="recentActivityEmpty" style="display:none; color: var(--text-muted); font-size: 13px; text-align: center; padding: 32px 0;">
                    <div style="font-size: 24px; color: var(--border-medium); margin-bottom: 12px;"><i class="fas fa-inbox"></i></div>
                    No recent activity recorded yet.
                </div>
            </div>
        </section>

        <!-- Module B: Editorial Engagement Matrix -->
        <section class="admin-card" aria-label="Editorial Engagement">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Editorial Engagement Matrix</h2>
                    <p class="card-subtitle">Top circulating essays vs. writings requiring amplification.</p>
                </div>
                <a href="articles.php" class="btn btn-secondary btn-sm" style="font-size: 11px;">
                    Writing (<span id="summaryArticlesCount">0</span>)
                </a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span style="font-size: 12px; font-weight: 600; font-family: var(--font-mono); text-transform: uppercase; color: var(--warning);"><i class="fas fa-fire"></i> Most Read Writing</span>
                    </div>
                    <ul id="mostReadArticlesList" class="ranked-content-list">
                        <li class="ranked-empty-state">Loading rankings...</li>
                    </ul>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <span style="font-size: 12px; font-weight: 600; font-family: var(--font-mono); text-transform: uppercase; color: var(--danger);"><i class="fas fa-chart-line-down"></i> Needs Amplification</span>
                    </div>
                    <ul id="leastReadArticlesList" class="ranked-content-list">
                        <li class="ranked-empty-state">Loading rankings...</li>
                    </ul>
                </div>
            </div>
        </section>

    </div>

    <!-- ── RIGHT COLUMN (40%): Telemetry & System Diagnostics ── -->
    <div class="studio-secondary-col">

        <!-- Module C: 7-Day Audience Velocity & Reads -->
        <section class="admin-card" id="dashboardTrafficSection" aria-label="Audience Telemetry">
            <div class="admin-card-header">
                <div>
                    <h2 class="card-title">Audience Telemetry (7-Day)</h2>
                    <p class="card-subtitle">Authentic visitor volume and readership trends.</p>
                </div>
                <a href="analytics.php" class="btn btn-secondary btn-sm" style="font-size: 11px;">
                    <i class="fas fa-chart-line" style="margin-right: 4px;"></i> Analytics
                </a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px;">
                <div style="background: var(--bg-canvas); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 10.5px; font-family: var(--font-mono); color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Unique Visitors</span>
                    <span id="quickStatsTotalVisitors" style="font-family: var(--font-mono); font-size: 18px; font-weight: 700; color: var(--accent);">0</span>
                    <div style="margin-top: 2px;"><span id="quickStatsVisitorsTrend" class="quick-stat-trend trend-neutral"><i class="fas fa-minus"></i> 0%</span></div>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle); text-align: center;">
                    <span style="display: block; font-size: 10.5px; font-family: var(--font-mono); color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Daily Average</span>
                    <span id="quickStatsDailyAvg" style="font-family: var(--font-mono); font-size: 18px; font-weight: 700; color: var(--warning);">0</span>
                    <div style="margin-top: 2px;"><span id="quickStatsDailyTrend" class="quick-stat-trend trend-neutral"><i class="fas fa-minus"></i> 0%</span></div>
                </div>
                <!-- Hidden element preserved for JS backward compatibility -->
                <span id="quickStatsTotalReadsOld" style="display: none;">0</span>
            </div>

            <div id="sevenDayChartContainer" style="min-height: 150px; display: flex; align-items: center; justify-content: center;">
                <svg class="weekly-bar-chart" viewBox="0 0 320 180" width="100%" height="160" aria-label="7-Day Traffic Chart">
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
                    <!-- Dynamic Bars updated by dashboard.js -->
                    <rect x="58" y="150" width="22" height="0" fill="var(--accent)"><title>Day 1</title></rect>
                    <rect x="94" y="150" width="22" height="0" fill="var(--accent)"><title>Day 2</title></rect>
                    <rect x="130" y="150" width="22" height="0" fill="var(--accent)"><title>Day 3</title></rect>
                    <rect x="166" y="150" width="22" height="0" fill="var(--accent)"><title>Day 4</title></rect>
                    <rect x="202" y="150" width="22" height="0" fill="var(--accent)"><title>Day 5</title></rect>
                    <rect x="238" y="150" width="22" height="0" fill="var(--accent)"><title>Day 6</title></rect>
                    <rect x="274" y="150" width="22" height="0" fill="var(--accent)"><title>Day 7</title></rect>
                    <!-- Day Labels updated by dashboard.js -->
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

        <!-- Module D: System Runtime Diagnostics & Health -->
        <details class="admin-card" style="padding: 0;">
            <summary style="padding: 16px 20px; font-weight: 600; cursor: pointer; color: var(--text-primary); list-style: none; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-server" style="color: var(--text-muted); font-size: 13px;"></i>
                    <span style="font-size: 13.5px;">Runtime Health</span>
                    <span class="system-status-pill status-pill-success" style="font-size: 10px;">Nominal</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 11px; color: var(--text-muted); font-family: var(--font-mono);">PHP <?= phpversion() ?></span>
                    <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--text-muted);"></i>
                </div>
            </summary>
            <div style="padding: 18px 20px; border-top: 1px solid var(--border-subtle); display: flex; flex-direction: column; gap: 12px; font-size: 12.5px;">
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">MySQL Database</span>
                        <span id="systemStatus_db" class="system-status-pill status-pill-neutral">Checking...</span>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">Auth Session Guard</span>
                        <span id="systemStatus_auth" class="system-status-pill status-pill-neutral">Checking...</span>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">Public Web Server</span>
                        <span id="systemStatus_web" class="system-status-pill status-pill-success">Operational</span>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--bg-canvas); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                        <span style="color: var(--text-secondary);">Upload Storage</span>
                        <span class="system-status-pill status-pill-success">Writable (/uploads)</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border-subtle); padding-top: 10px; font-size: 11.5px; color: var(--text-muted); flex-wrap: wrap; gap: 8px;">
                    <span style="font-family: var(--font-mono);"><span id="sysCountArticles">0</span> entries · <span id="sysCountUsers">0</span> users · <span id="sysCountPendingReviews">0</span> reviews</span>
                    <a href="settings.php" class="btn btn-secondary btn-sm" style="font-size: 11px;">Settings</a>
                </div>
            </div>
        </details>

    </div>

</div>


<!-- =====================================================
     5. STUDIO ROUTE DIRECTORY & CATALOG OVERVIEW
     High-density Swiss Modernist index of public routes & entity totals
====================================================== -->
<details class="admin-card" style="border: 1px solid var(--border-medium); border-radius: var(--radius-md); padding: 0;">
    <summary style="padding: 16px 20px; font-weight: 600; cursor: pointer; color: var(--text-primary); list-style: none; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-network-wired" style="color: var(--text-muted); font-size: 13px;"></i>
            <span style="font-size: 14px;">Studio Route Directory &amp; Catalog Ledger</span>
        </div>
        <i class="fas fa-chevron-down" style="font-size: 11px; color: var(--text-muted);"></i>
    </summary>
    <div style="padding: 20px; border-top: 1px solid var(--border-subtle); display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Public Website Control Table -->
        <div>
            <h3 style="font-size: 13px; font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-top: 0; margin-bottom: 10px;">Public Endpoint Routing Matrix</h3>
            <div style="overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-sm);">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-subtle); background: var(--bg-surface-elevated); color: var(--text-muted); font-family: var(--font-mono); font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 10px 14px; font-weight: 600;">Route URI</th>
                            <th style="padding: 10px 14px; font-weight: 600;">Resource Title</th>
                            <th style="padding: 10px 14px; font-weight: 600;">Status</th>
                            <th style="padding: 10px 14px; font-weight: 600; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Personal Engineering Studio (Home)</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="showcase.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Showcase</a>
                                <a href="/" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/projects</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Engineering Projects Directory</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="projects.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Manage</a>
                                <a href="/projects" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/lab</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Systems &amp; Research Lab</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Active</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="labs.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Manage</a>
                                <a href="/lab" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/articles</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Writing &amp; Notes</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="articles.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Manage</a>
                                <a href="/articles" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/gallery</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Visual Computing &amp; Topologies</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="media.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Media</a>
                                <a href="/gallery" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/store</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Engineering Tools &amp; Resources</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="store.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Catalog</a>
                                <a href="/store" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/journey</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Career Trajectory &amp; Invariants</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="journey.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Manage</a>
                                <a href="/journey" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 14px; font-family: var(--font-mono); color: var(--accent); font-weight: 500;">/about</td>
                            <td style="padding: 10px 14px; font-weight: 500;">Profile, Biography &amp; Convictions</td>
                            <td style="padding: 10px 14px;"><span class="status-badge status-published" style="padding: 2px 6px; font-size: 11px;">Live</span></td>
                            <td style="padding: 10px 14px; text-align: right;">
                                <a href="settings.php" style="color: var(--accent); margin-right: 12px; text-decoration: none; font-weight: 500;">Settings</a>
                                <a href="/about" target="_blank" rel="noopener" style="color: var(--text-muted);"><i class="fas fa-external-link-alt" style="font-size: 11px;"></i></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Content Overview Hub Grid -->
        <div>
            <h3 style="font-size: 13px; font-family: var(--font-mono); text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-top: 0; margin-bottom: 12px;">Entity Volume &amp; State Breakdown</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); font-weight: 600;">ARTICLES</span>
                        <i class="far fa-file-alt" style="color: var(--accent); font-size: 12px;"></i>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-secondary);" id="summaryArticlesBreakdown">Loading...</div>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); font-weight: 600;">PROJECTS (<span id="summaryAchievementsCount">0</span>)</span>
                        <i class="fas fa-code-branch" style="color: var(--success); font-size: 12px;"></i>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-secondary);" id="summaryAchievementsBreakdown">Loading...</div>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); font-weight: 600;">REVIEWS (<span id="summaryReviewsCount">0</span>)</span>
                        <i class="far fa-comments" style="color: var(--warning); font-size: 12px;"></i>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-secondary);" id="summaryReviewsBreakdown">Loading...</div>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); font-weight: 600;">USERS (<span id="summaryUsersCount">0</span>)</span>
                        <i class="far fa-user" style="color: var(--accent); font-size: 12px;"></i>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-secondary);" id="summaryUsersBreakdown">Loading...</div>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); font-weight: 600;">STORE (<span id="summaryProductsCount"><?= (int)($platformCounts['products']['total'] ?? 0) ?></span>)</span>
                        <i class="fas fa-store" style="color: var(--success); font-size: 12px;"></i>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-secondary);" id="summaryProductsBreakdown">Published: <?= (int)($platformCounts['products']['published'] ?? 0) ?> · Downloads: <?= (int)($platformCounts['products']['downloads'] ?? 0) ?></div>
                </div>
                <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="font-size: 11px; font-family: var(--font-mono); color: var(--text-muted); font-weight: 600;">STUDIO LAB (<span id="summaryLabsCount"><?= (int)($platformCounts['labs']['total'] ?? 0) ?></span>)</span>
                        <i class="fas fa-flask" style="color: var(--accent); font-size: 12px;"></i>
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-secondary);" id="summaryLabsBreakdown">Active: <?= (int)($platformCounts['labs']['active'] ?? 0) ?> · Planned: <?= (int)($platformCounts['labs']['planned'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
</details>

<!-- Hidden elements for JS fallbacks and backward compatibility -->
<div style="display: none;">
    <span id="statTotalAchievements">0</span>
    <span id="statTotalAchievementsMeta">0</span>
    <span id="statHiddenAchievementsMeta">0</span>
    <span id="statReadsTrend"></span>
    <span id="statTotalReads">0</span>
    <span id="statVisitorsTrend"></span>
    <span id="statTotalVisitors">0</span>
    <span id="statVisitorsMeta"></span>
    <span id="statTotalUsers">0</span>
    <span id="statTotalUsersMeta">0</span>
    <span id="statRegularUsersMeta">0</span>
</div>

<?php
$pageScripts = ['js/dashboard.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
