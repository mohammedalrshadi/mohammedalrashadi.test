<?php
// ============================================================
// PROJECTS & PROJECTS — Server-Side Protected Admin Page
// Redesigned with a Content-First Engineering Projects Workflow.
// Strictly maintains all DOM IDs and classes required by admin/js/projects.js.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$activeNav = 'projects';
$pageTitle = 'Projects';

$projectStats = ['total' => 0, 'published' => 0, 'draft' => 0];
try {
    $pdoStats = getDB();
    $counts = getPlatformEntityCounts($pdoStats);
    $projectStats = $counts['projects'];
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
            <span style="color: var(--accent);">Projects</span>
        </div>
        <h1 class="page-title">Projects</h1>
        <p class="page-description">Manage systems architecture, research experiments, benchmarks, and portfolio builds.</p>
    </div>

    <div class="header-actions">
        <a href="/projects.php" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" title="View Public Projects">
            <i class="fas fa-external-link-alt" aria-hidden="true" style="font-size: 11px;"></i>
            <span>Live Projects</span>
        </a>

        <button type="button" class="btn btn-secondary" onclick="openAchieveCategoryModal()" id="openAchieveCategoryModalBtn">
            <i class="fas fa-tags" aria-hidden="true"></i>
            <span>Categories</span>
        </button>

        <a href="projects-edit.php" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>New Project</span>
        </a>
    </div>
</header>

<!-- =====================================================
     SECTION OVERVIEW RAIL
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card" style="cursor: pointer;" onclick="setAchieveStatusTab('all')">
        <div class="stat-header">
            <span class="stat-label">Total Projects</span>
            <i class="fas fa-code-branch stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewTotalProjects"><?= (int)$projectStats['total'] ?></div>
        <div class="stat-meta">Engineering builds &amp; showcases</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="setAchieveStatusTab('published')">
        <div class="stat-header">
            <span class="stat-label">Published</span>
            <i class="fas fa-circle-check stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewPublishedProjects" style="color: var(--success);"><?= (int)$projectStats['published'] ?></div>
        <div class="stat-meta">Live on public website</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="setAchieveStatusTab('draft')">
        <div class="stat-header">
            <span class="stat-label">In-Progress Drafts</span>
            <i class="fas fa-file-pen stat-icon" style="color: var(--warning);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewDraftProjects" style="color: var(--warning);"><?= (int)$projectStats['draft'] ?></div>
        <div class="stat-meta">Unpublished projects</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Publication Rate</span>
            <i class="fas fa-chart-pie stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewProjectPubRate" style="color: var(--accent);"><?= $projectStats['total'] > 0 ? round(($projectStats['published'] / $projectStats['total']) * 100) : 0 ?>%</div>
        <div class="stat-meta"><?= (int)$projectStats['published'] ?> of <?= (int)$projectStats['total'] ?> live</div>
    </div>
</div>


<!-- =====================================================
     2. CONTENT-FIRST PROJECTS TABLE & DISCOVERY
====================================================== -->
<section class="table-container" id="achievementsTableContainer" aria-label="Projects Directory">

    <!-- Status Filter Tabs -->
    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="achieveStatusTabs" role="tablist" aria-label="Filter projects by status">
            <button type="button" class="filter-tab active" data-status="all" role="tab" aria-selected="true" onclick="setAchieveStatusTab('all')">
                All <span class="tab-counter" id="tabCountAchieveAll">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="published" role="tab" aria-selected="false" onclick="setAchieveStatusTab('published')">
                Published <span class="tab-counter" id="tabCountAchievePublished">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="draft" role="tab" aria-selected="false" onclick="setAchieveStatusTab('draft')">
                Draft <span class="tab-counter" id="tabCountAchieveDraft">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="hidden" role="tab" aria-selected="false" onclick="setAchieveStatusTab('hidden')">
                Hidden <span class="tab-counter" id="tabCountAchieveHidden">0</span>
            </button>
        </div>
        <span id="achieveResultCount" class="page-meta" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
    </div>

    <!-- Search & Discovery Toolbar -->
    <div style="padding: 14px 20px 18px; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div class="search-input-wrapper">
            <i class="fas fa-search toolbar-search-icon" aria-hidden="true"></i>
            <input
                type="search"
                id="achieveSearchInput"
                class="form-control"
                placeholder="Search projects by title or technology..."
                maxlength="100"
                aria-label="Search projects"
            >
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select id="achieveCategoryFilter" class="form-control" style="width: auto; min-width: 140px;" onchange="loadAchievements(1)" aria-label="Filter by category">
                <option value="">All Categories</option>
            </select>

            <select id="achieveStatusFilter" class="form-control" style="width: auto; min-width: 120px;" onchange="loadAchievements(1)" aria-label="Filter by status">
                <option value="all" selected>All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="hidden">Hidden</option>
            </select>

            <select id="achieveSortFilter" class="form-control" style="width: auto; min-width: 130px;" onchange="loadAchievements(1)" aria-label="Sort projects">
                <option value="newest" selected>Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="title_asc">Title (A - Z)</option>
                <option value="title_desc">Title (Z - A)</option>
            </select>

            <button
                type="button"
                id="achieveResetBtn"
                class="btn btn-secondary btn-sm"
                onclick="resetAchieveFilters()"
                title="Reset filters"
            >
                <i class="fas fa-undo"></i>
                <span>Reset</span>
            </button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="min-width: 240px;">Project / System</th>
                    <th>Category</th>
                    <th>Completion Date</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right; min-width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="achievementsTableBody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        Loading projects...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="achievePagination" class="pagination-wrapper" style="display: none;"></div>

</section>


<!-- =====================================================
     4. PROJECTS TRASH MANAGEMENT (REQ-005)
====================================================== -->
<section class="admin-card" id="achievementsTrashContainer" style="margin-top: 32px;">
    <div class="admin-card-header">
        <div>
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-trash-alt" style="color: var(--text-muted); font-size: 14px;"></i>
                <span>Projects Trash</span>
            </h3>
            <p class="card-subtitle">Deleted projects can be restored or permanently purged.</p>
        </div>

        <button type="button" id="toggleAchievementsTrashBtn" class="btn btn-secondary btn-sm" onclick="toggleAchievementsTrash()">
            <i class="fas fa-chevron-down" id="achieveTrashChevronIcon"></i>
            <span>View Trash</span>
        </button>
    </div>

    <div id="achievementsTrashContent" class="trash-content hidden" style="display: none;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Deleted Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="achievementsTrashTableBody"></tbody>
            </table>
        </div>
    </div>
</section>


<!-- =====================================================
     5. PROJECT CATEGORY MODAL (REQ-002)
====================================================== -->
<div id="achieveCategoryManageModal" class="modal" role="dialog" aria-labelledby="achieveCategoryModalTitle" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="achieveCategoryModalTitle">Project Categories</h3>
            <button type="button" class="modal-close" onclick="closeAchieveCategoryModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <!-- Add Category -->
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <input
                    type="text"
                    id="achieveNewCategoryInput"
                    class="form-control"
                    placeholder="New category name..."
                    maxlength="60"
                >
                <button type="button" id="achieveAddCategoryBtn" class="btn btn-primary" onclick="handleAddAchieveCategory()">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <!-- Categories List Table -->
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Projects</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="achieveCategoryListTbody"></tbody>
                </table>
            </div>

            <!-- Safe Delete / Reassign Subpanel -->
            <div id="achieveCategoryDeletePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px; color: var(--danger);">
                    Delete Category: <span id="achieveDeleteTargetCategoryName"></span>
                </h4>
                <p id="achieveDeleteUsageDescription" style="font-size: 12px; color: var(--text-muted); margin: 0 0 12px 0;"></p>

                <form id="achieveCategoryDeleteForm" onsubmit="handleExecuteAchieveCategoryDelete(event)">
                    <input type="hidden" id="achieveDeleteSourceCategoryId">
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="achieveDeleteActionType" value="reassign" id="achieveDeleteActionReassign" checked onchange="toggleAchieveDeleteActionInputs()">
                            <span>Reassign projects to another category:</span>
                        </label>
                        <div id="achieveReassignSelectWrapper">
                            <select id="achieveReassignTargetCategorySelect" class="form-control"></select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="achieveDeleteActionType" value="unlink" id="achieveDeleteActionUnlink" onchange="toggleAchieveDeleteActionInputs()">
                            <span>Unlink projects (leave uncategorized)</span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelAchieveCategoryActionPanel()">Cancel</button>
                        <button type="submit" id="achieveConfirmDeleteCategoryBtn" class="btn btn-danger btn-sm">Confirm Delete</button>
                    </div>
                </form>
            </div>

            <!-- Inline Rename Subpanel -->
            <div id="achieveCategoryRenamePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px;">
                    Rename Category: <span id="achieveRenameOldCategoryName"></span>
                </h4>
                <form id="achieveCategoryRenameForm" onsubmit="handleExecuteAchieveCategoryRename(event)">
                    <input type="hidden" id="achieveRenameCategoryId">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="achieveRenameCategoryNewNameInput" class="form-control" placeholder="New name..." required maxlength="60">
                        <button type="submit" id="achieveConfirmRenameCategoryBtn" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelAchieveCategoryActionPanel()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAchieveCategoryModal()">Close</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/projects.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
