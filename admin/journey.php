<?php
// ============================================================
// JOURNEY — Server-Side Protected Admin Page
// Management interface for the public Journey timeline
// (journey.php), backed by the journey_milestones table.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'journey';
$pageTitle = 'Journey Timeline';

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
            <span style="color: var(--accent);">Journey</span>
        </div>
        <h1 class="page-title">Journey Timeline</h1>
        <p class="page-description">Manage the milestones, architectural shifts, and learning events shown on the public Journey page.</p>
    </div>

    <div class="header-actions">
        <a href="/journey.php" target="_blank" class="btn btn-secondary" title="View Public Journey Page">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Journey</span>
        </a>

        <a href="journey-edit.php" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>New Milestone</span>
        </a>
    </div>
</header>

<!-- =====================================================
     2. CONTENT TABLE
====================================================== -->
<section class="table-container" id="journeyTableContainer" aria-label="Journey Milestones Directory">

    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="journeyStatusTabs" role="tablist" aria-label="Filter milestones by status">
            <button type="button" class="filter-tab active" data-status="all" role="tab" aria-selected="true" onclick="filterMilestones('all')">
                All <span class="tab-counter" id="tabCountJourneyAll">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="published" role="tab" aria-selected="false" onclick="filterMilestones('published')">
                Published <span class="tab-counter" id="tabCountJourneyPublished">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="draft" role="tab" aria-selected="false" onclick="filterMilestones('draft')">
                Draft <span class="tab-counter" id="tabCountJourneyDraft">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="trash" role="tab" aria-selected="false" onclick="filterMilestones('trash')">
                <i class="fas fa-trash-can" style="margin-right: 4px; font-size: 11px;"></i>
                Trash <span class="tab-counter" id="tabCountJourneyTrash" style="background: var(--danger); color: white;">0</span>
            </button>
        </div>
        <span id="journeyResultCount" class="page-meta" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
    </div>

    <div class="table-responsive">
        <table class="admin-table" id="journeyTable">
            <thead>
                <tr>
                    <th style="width: 60px;">Order</th>
                    <th>Milestone Title</th>
                    <th style="width: 150px;">Period</th>
                    <th style="width: 130px;">Category</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="journeyTableBody">
                <!-- Populated via JS -->
            </tbody>
        </table>
    </div>

    <div id="journeyEmptyState" class="empty-state" style="display: none; padding: 48px 20px; text-align: center;">
        <i class="fas fa-timeline" style="font-size: 36px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3 style="font-size: 16px; color: var(--text-primary); margin-bottom: 8px;">No Journey Milestones Yet</h3>
        <p style="font-size: 13px; color: var(--text-muted); max-width: 420px; margin: 0 auto 20px;">
            Log architectural shifts, intellectual turning points, and system milestones as they happen.
        </p>
        <a href="journey-edit.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Create First Milestone
        </a>
    </div>

</section>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteMilestoneModal" role="dialog" aria-modal="true" aria-labelledby="deleteMilestoneModalTitle" style="display: none;">
    <div class="modal-backdrop" onclick="closeDeleteMilestoneModal()"></div>
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteMilestoneModalTitle">Confirm Delete</h3>
            <button type="button" class="btn-close" onclick="closeDeleteMilestoneModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="color: var(--text-primary); font-size: 14px; margin-bottom: 12px;">
                Delete <strong id="deleteMilestoneTitleDisplay" style="color: var(--accent);"></strong>?
            </p>
            <p style="color: var(--text-muted); font-size: 12.5px; line-height: 1.6;">
                This removes it from the public Journey page immediately. It stays recoverable in the database (soft delete).
            </p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeDeleteMilestoneModal()">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteMilestoneBtn" onclick="executeDeleteMilestone()">
                <i class="fas fa-trash-alt"></i> Delete Milestone
            </button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/journey.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
