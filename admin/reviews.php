<?php
// ============================================================
// REVIEWS — Server-Side Protected Admin Page
// Redesigned as an Authentic Moderation Workspace.
// Strictly maintains all DOM IDs and classes required by admin/js/reviews.js.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$activeNav = 'reviews';
$pageTitle = 'Reviews Moderation';

$reviewStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $pdoStats = getDB();
    $counts = getPlatformEntityCounts($pdoStats);
    $reviewStats = $counts['reviews'];
} catch (Throwable $e) {}

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
            <span style="color: var(--accent);">Reviews</span>
        </div>
        <h1 class="page-title">Reviews &amp; Reader Moderation</h1>
        <p class="page-description">Moderate incoming reader testimonials, ratings, and article discussions.</p>
        <p id="reviewsTotalMeta" class="page-meta" hidden></p>
    </div>

    <div class="header-actions">
        <span class="status-badge status-warning" id="reviewsPendingBadge" hidden style="font-size: 12px; padding: 6px 14px;">
            <span id="totalPendingReviews">0</span>
            <span>Awaiting Approval</span>
        </span>
    </div>
</header>

<!-- =====================================================
     SECTION OVERVIEW RAIL & QUICK ACTIONS
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card" style="cursor: pointer;" onclick="filterReviews('all')">
        <div class="stat-header">
            <span class="stat-label">Total Reviews</span>
            <i class="fas fa-comments stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewTotalReviews"><?= (int)$reviewStats['total'] ?></div>
        <div class="stat-meta">Visitor &amp; reader feedback</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="filterReviews('pending')">
        <div class="stat-header">
            <span class="stat-label">Awaiting Approval</span>
            <i class="fas fa-clock stat-icon" style="color: var(--warning);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewPendingReviews" style="color: var(--warning);"><?= (int)$reviewStats['pending'] ?></div>
        <div class="stat-meta">Pending moderation</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="filterReviews('approved')">
        <div class="stat-header">
            <span class="stat-label">Approved</span>
            <i class="fas fa-circle-check stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewApprovedReviews" style="color: var(--success);"><?= (int)$reviewStats['approved'] ?></div>
        <div class="stat-meta">Visible on public pages</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Quick Actions</span>
            <i class="fas fa-bolt stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="filterReviews('pending')">
                <i class="fas fa-filter"></i> Pending
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="loadReviews()">
                <i class="fas fa-rotate"></i> Refresh
            </button>
            <a href="/" target="_blank" class="btn btn-secondary btn-sm" title="View Public Site">
                <i class="fas fa-arrow-up-right-from-square"></i>
            </a>
        </div>
    </div>
</div>


<!-- =====================================================
     2. REVIEWS MODERATION PANEL
====================================================== -->
<section class="table-container" id="reviews" aria-labelledby="reviewsPanelTitle">

    <!-- Header & Filter Tabs -->
    <div style="padding: 18px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div>
            <h2 class="card-title" id="reviewsPanelTitle">Moderation Queue</h2>
            <p class="card-subtitle">Review feedback submitted by visitors across public pages.</p>
        </div>

        <div class="filter-tabs" role="group" aria-label="Filter reviews by moderation status">
            <button
                type="button"
                class="filter-tab active"
                data-status="all"
                aria-pressed="true"
                onclick="filterReviews('all')"
            >
                All
            </button>

            <button
                type="button"
                class="filter-tab"
                data-status="pending"
                aria-pressed="false"
                onclick="filterReviews('pending')"
            >
                Pending
            </button>

            <button
                type="button"
                class="filter-tab"
                data-status="approved"
                aria-pressed="false"
                onclick="filterReviews('approved')"
            >
                Approved
            </button>

            <button
                type="button"
                class="filter-tab"
                data-status="rejected"
                aria-pressed="false"
                onclick="filterReviews('rejected')"
            >
                Rejected
            </button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="min-width: 140px;">Reviewer</th>
                    <th>Email</th>
                    <th style="min-width: 240px;">Feedback Message</th>
                    <th>Target Scope</th>
                    <th style="text-align: center;">Status</th>
                    <th>Date</th>
                    <th style="text-align: right; min-width: 160px;">Moderation</th>
                </tr>
            </thead>
            <tbody id="reviewsTableBody">
                <tr>
                    <td colspan="7" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        Loading reviews...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</section>


<!-- =====================================================
     3. FULL REVIEW DETAIL MODAL
====================================================== -->
<div id="reviewModalOverlay" class="modal" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle" hidden>
    <div class="modal-dialog" id="reviewModal" style="max-width: 580px;">

        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="reviewModalTitle">Review Details</h3>
                <span id="reviewModalDate" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
            </div>
            <button type="button" class="modal-close" id="reviewModalCloseBtn" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Reviewer Meta Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <div>
                    <h4 id="reviewModalAuthor" style="margin: 0 0 2px 0; font-size: 15px; font-weight: 600; color: var(--text-primary);"></h4>
                    <span id="reviewModalEmail" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
                </div>
                <div style="text-align: right;">
                    <div id="reviewModalRating" class="review-stars" style="margin-bottom: 4px;"></div>
                    <span id="reviewModalStatus" class="status-badge"></span>
                </div>
            </div>

            <!-- Target Scope -->
            <div style="font-size: 12.5px;">
                <span style="color: var(--text-muted); margin-right: 6px;">Target Context:</span>
                <span id="reviewModalScope"></span>
            </div>

            <!-- Complete Message -->
            <div>
                <label style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; font-family: var(--font-mono);">Full Feedback</label>
                <div id="reviewModalMessage" style="padding: 16px; background: var(--bg-input); border: 1px solid var(--border-medium); border-radius: var(--radius-sm); font-size: 14px; line-height: 1.7; color: var(--text-primary); white-space: pre-wrap;"></div>
            </div>
        </div>

        <div class="modal-footer" style="justify-content: space-between;">
            <button type="button" class="btn btn-danger btn-sm" id="reviewModalDeleteBtn">
                <i class="fas fa-trash-alt"></i> Delete
            </button>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="reviewModalRejectBtn">
                    <i class="fas fa-ban"></i> Reject
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="reviewModalApproveBtn">
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </div>

    </div>
</div>

<?php
$pageScripts = ['js/reviews.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
