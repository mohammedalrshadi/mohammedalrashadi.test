<?php
// ============================================================
// LABS — Server-Side Protected Admin Page
// Management interface for reproducible engineering experiments.
// Directly backed by api/data/lab_experiments.json.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$activeNav = 'labs';
$pageTitle = 'Studio Lab';

$labStats = ['total' => 0, 'completed' => 0, 'active' => 0, 'planned' => 0];
try {
    $pdoStats = getDB();
    $counts = getPlatformEntityCounts($pdoStats);
    $labStats = $counts['labs'];
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
            <span style="color: var(--accent);">Labs</span>
        </div>
        <h1 class="page-title">Studio Lab</h1>
        <p class="page-description">Manage reproducible benchmark notebooks, systems investigations, and empirical outcomes.</p>
    </div>

    <div class="header-actions">
        <a href="/lab.php" target="_blank" class="btn btn-secondary" title="View Public Lab">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Lab</span>
        </a>

        <a href="lab-edit.php" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>New Lab Experiment</span>
        </a>
    </div>
</header>

<!-- =====================================================
     SECTION OVERVIEW RAIL & QUICK ACTIONS
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card" style="cursor: pointer;" onclick="filterLabs('all')">
        <div class="stat-header">
            <span class="stat-label">Total Experiments</span>
            <i class="fas fa-flask stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewTotalLabs"><?= (int)$labStats['total'] ?></div>
        <div class="stat-meta">Reproducible engineering tests</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="filterLabs('verified & concluded')">
        <div class="stat-header">
            <span class="stat-label">Concluded</span>
            <i class="fas fa-circle-check stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewConcludedLabs" style="color: var(--success);"><?= (int)$labStats['completed'] ?></div>
        <div class="stat-meta">Verified outcomes</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="filterLabs('active')">
        <div class="stat-header">
            <span class="stat-label">Active / In-Flight</span>
            <i class="fas fa-spinner stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewActiveLabs" style="color: var(--accent);"><?= (int)$labStats['active'] ?></div>
        <div class="stat-meta">Current empirical investigations</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Quick Actions</span>
            <i class="fas fa-bolt stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
            <a href="lab-edit.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> New Experiment
            </a>
            <a href="/lab.php" target="_blank" class="btn btn-secondary btn-sm" title="View Public Lab">
                <i class="fas fa-arrow-up-right-from-square"></i> Public Lab
            </a>
        </div>
    </div>
</div>


<!-- =====================================================
     2. CONTENT TABLE & DISCOVERY
====================================================== -->
<section class="table-container" id="labsTableContainer" aria-label="Lab Experiments Directory">

    <!-- Status Filter Tabs -->
    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="labStatusTabs" role="tablist" aria-label="Filter experiments by status">
            <button type="button" class="filter-tab active" data-status="all" role="tab" aria-selected="true" onclick="filterLabs('all')">
                All <span class="tab-counter" id="tabCountLabsAll">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="verified & concluded" role="tab" aria-selected="false" onclick="filterLabs('verified & concluded')">
                Concluded <span class="tab-counter" id="tabCountLabsConcluded">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="active" role="tab" aria-selected="false" onclick="filterLabs('active')">
                Active <span class="tab-counter" id="tabCountLabsActive">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="in_progress" role="tab" aria-selected="false" onclick="filterLabs('in_progress')">
                In Progress <span class="tab-counter" id="tabCountLabsProgress">0</span>
            </button>
        </div>
        <span id="labResultCount" class="page-meta" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
    </div>

    <!-- Search Toolbar -->
    <div style="padding: 14px 20px 18px; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div class="search-input-wrapper">
            <i class="fas fa-search toolbar-search-icon" aria-hidden="true"></i>
            <input
                type="search"
                id="labSearchInput"
                class="form-control"
                placeholder="Search experiments by ID, title, or hypothesis..."
                maxlength="100"
                aria-label="Search experiments"
                oninput="searchLabs()"
            >
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select id="labCategoryFilter" class="form-control" style="width: auto; min-width: 140px;" onchange="searchLabs()" aria-label="Filter by category">
                <option value="">All Categories</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="admin-table" id="labsTable">
            <thead>
                <tr>
                    <th style="width: 110px;">ID</th>
                    <th>Investigation Title</th>
                    <th style="width: 160px;">Category</th>
                    <th style="width: 150px;">Status</th>
                    <th style="width: 100px;">Read Time</th>
                    <th style="width: 120px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="labsTableBody">
                <!-- Populated via JS -->
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <div id="labsEmptyState" class="empty-state" style="display: none; padding: 48px 20px; text-align: center;">
        <i class="fas fa-flask" style="font-size: 36px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3 style="font-size: 16px; color: var(--text-primary); margin-bottom: 8px;">No Lab Experiments Found</h3>
        <p style="font-size: 13px; color: var(--text-muted); max-w: 420px; margin: 0 auto 20px;">
            Log reproducible systems benchmarks, concurrency stress tests, or indexing investigations.
        </p>
        <a href="lab-edit.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Create First Experiment
        </a>
    </div>

</section>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteLabModal" role="dialog" aria-modal="true" aria-labelledby="deleteLabModalTitle" style="display: none;">
    <div class="modal-backdrop" onclick="closeDeleteModal()"></div>
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteLabModalTitle">Confirm Delete</h3>
            <button type="button" class="btn-close" onclick="closeDeleteModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="color: var(--text-primary); font-size: 14px; margin-bottom: 12px;">
                Are you sure you want to delete <strong id="deleteLabIdDisplay" style="color: var(--accent);"></strong>?
            </p>
            <p style="color: var(--text-muted); font-size: 12.5px; line-height: 1.6;">
                This action will permanently remove the experiment from the public Studio Lab. This cannot be undone.
            </p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeDeleteModal()">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteLabBtn" onclick="executeDeleteLab()">
                <i class="fas fa-trash-alt"></i> Delete Experiment
            </button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/labs.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

