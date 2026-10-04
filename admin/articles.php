<?php
// ============================================================
// ARTICLES — Server-Side Protected Admin Page
// Redesigned with a Content-First Editorial Workflow.
// Strictly maintains all DOM IDs and classes required by admin/js/articles.js.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$activeNav = 'articles';
$pageTitle = 'Writing';

$articleStats = ['total' => 0, 'published' => 0, 'draft' => 0];
try {
    $pdoStats = getDB();
    $counts = getPlatformEntityCounts($pdoStats);
    $articleStats = $counts['articles'];
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
            <span class="breadcrumb-current">Articles</span>
        </div>
        <h1 class="page-title">Writing</h1>
        <p class="page-description">Draft, publish, and manage engineering essays, benchmarks, and research notes.</p>
    </div>

    <div class="header-actions">
        <a href="/articles.php" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" title="View Public Articles">
            <i class="fas fa-external-link-alt" aria-hidden="true" style="font-size: 11px;"></i>
            <span>Live Articles</span>
        </a>

        <button type="button" class="btn btn-secondary" onclick="openCategoryModal()" id="openCategoryModalBtn">
            <i class="fas fa-tags" aria-hidden="true"></i>
            <span>Categories</span>
        </button>

        <a href="articles-edit.php" class="btn btn-primary">
            <i class="fas fa-pen-nib" aria-hidden="true"></i>
            <span>New Article</span>
        </a>
    </div>
</header>

<!-- =====================================================
     SECTION OVERVIEW RAIL & QUICK ACTIONS
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card" style="cursor: pointer;" onclick="setArticleStatusTab('all')">
        <div class="stat-header">
            <span class="stat-label">Total Articles</span>
            <i class="fas fa-newspaper stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewTotalArticles"><?= (int)$articleStats['total'] ?></div>
        <div class="stat-meta">Active editorial essays</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="setArticleStatusTab('published')">
        <div class="stat-header">
            <span class="stat-label">Published</span>
            <i class="fas fa-circle-check stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewPublishedArticles" style="color: var(--success);"><?= (int)$articleStats['published'] ?></div>
        <div class="stat-meta">Live on public website</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="setArticleStatusTab('draft')">
        <div class="stat-header">
            <span class="stat-label">In-Progress Drafts</span>
            <i class="fas fa-file-pen stat-icon" style="color: var(--warning);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewDraftArticles" style="color: var(--warning);"><?= (int)$articleStats['draft'] ?></div>
        <div class="stat-meta">Unpublished writing</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Publication Rate</span>
            <i class="fas fa-chart-pie stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewPubRate" style="color: var(--accent);"><?= $articleStats['total'] > 0 ? round(($articleStats['published'] / $articleStats['total']) * 100) : 0 ?>%</div>
        <div class="stat-meta"><?= (int)$articleStats['published'] ?> of <?= (int)$articleStats['total'] ?> live</div>
    </div>
</div>


<!-- =====================================================
     2. CONTENT-FIRST ARTICLES TABLE & DISCOVERY
====================================================== -->
<section class="table-container" id="articlesTableContainer" aria-label="Articles Directory">

    <!-- Status Filter Tabs -->
    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="articleStatusTabs" role="tablist" aria-label="Filter articles by status">
            <button type="button" class="filter-tab active" data-status="all" role="tab" aria-selected="true" onclick="setArticleStatusTab('all')">
                All <span class="tab-counter" id="tabCountArticlesAll">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="published" role="tab" aria-selected="false" onclick="setArticleStatusTab('published')">
                Published <span class="tab-counter" id="tabCountArticlesPublished">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="draft" role="tab" aria-selected="false" onclick="setArticleStatusTab('draft')">
                Draft <span class="tab-counter" id="tabCountArticlesDraft">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="hidden" role="tab" aria-selected="false" onclick="setArticleStatusTab('hidden')">
                Hidden <span class="tab-counter" id="tabCountArticlesHidden">0</span>
            </button>
        </div>
        <span id="articleResultCount" class="page-meta" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
    </div>

    <!-- Search & Discovery Toolbar -->
    <div style="padding: 14px 20px 18px; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div class="search-input-wrapper">
            <i class="fas fa-search toolbar-search-icon" aria-hidden="true"></i>
            <input
                type="search"
                id="articleSearchInput"
                class="form-control"
                placeholder="Search articles by title or keyword..."
                maxlength="100"
                aria-label="Search articles"
            >
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select id="articleCategoryFilter" class="form-control" style="width: auto; min-width: 140px;" onchange="loadArticles(1)" aria-label="Filter by category">
                <option value="">All Categories</option>
            </select>

            <select id="articleStatusFilter" class="form-control" style="width: auto; min-width: 120px;" onchange="loadArticles(1)" aria-label="Filter by status">
                <option value="all" selected>All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="hidden">Hidden</option>
            </select>

            <select id="articleSortFilter" class="form-control" style="width: auto; min-width: 130px;" onchange="loadArticles(1)" aria-label="Sort articles">
                <option value="newest" selected>Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="title_asc">Title (A - Z)</option>
                <option value="title_desc">Title (Z - A)</option>
            </select>

            <button
                type="button"
                id="articleResetBtn"
                class="btn btn-secondary btn-sm"
                onclick="resetArticleFilters()"
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
                    <th style="min-width: 240px;">Title</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right; min-width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody id="postsTableBody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                        Loading articles...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div id="articlePagination" class="pagination-wrapper" style="display: none;"></div>

</section>


<!-- =====================================================
     4. ARTICLES TRASH MANAGEMENT (REQ-005)
====================================================== -->
<section class="admin-card" id="articlesTrashContainer" style="margin-top: 32px;">
    <div class="admin-card-header">
        <div>
            <h3 class="card-title" style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-trash-alt" style="color: var(--text-muted); font-size: 14px;"></i>
                <span>Articles Trash</span>
            </h3>
            <p class="card-subtitle">Deleted articles can be restored or permanently purged.</p>
        </div>

        <button type="button" id="toggleArticlesTrashBtn" class="btn btn-secondary btn-sm" onclick="toggleArticlesTrash()">
            <i class="fas fa-chevron-down" id="trashChevronIcon"></i>
            <span>View Trash</span>
        </button>
    </div>

    <div id="articlesTrashContent" class="trash-content hidden" style="display: none;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Deleted Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="articlesTrashTableBody"></tbody>
            </table>
        </div>
    </div>
</section>


<!-- =====================================================
     5. CATEGORY MANAGEMENT MODAL (REQ-002)
====================================================== -->
<div id="categoryManageModal" class="modal" role="dialog" aria-labelledby="categoryModalTitle" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="categoryModalTitle">Article Categories</h3>
            <button type="button" class="modal-close" onclick="closeCategoryModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <!-- Add Category -->
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <input
                    type="text"
                    id="newCategoryInput"
                    class="form-control"
                    placeholder="New category name..."
                    maxlength="60"
                >
                <button type="button" id="addCategoryBtn" class="btn btn-primary" onclick="handleAddCategory()">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <!-- Categories List Table -->
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Articles</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoryListTbody"></tbody>
                </table>
            </div>

            <!-- Safe Delete / Reassign Subpanel -->
            <div id="categoryDeletePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px; color: var(--danger);">
                    Delete Category: <span id="deleteTargetCategoryName"></span>
                </h4>
                <p id="deleteUsageDescription" style="font-size: 12px; color: var(--text-muted); margin: 0 0 12px 0;"></p>

                <form id="categoryDeleteForm" onsubmit="handleExecuteCategoryDelete(event)">
                    <input type="hidden" id="deleteSourceCategoryId">
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="deleteActionType" value="reassign" id="deleteActionReassign" checked onchange="toggleDeleteActionInputs()">
                            <span>Reassign articles to another category:</span>
                        </label>
                        <div id="reassignSelectWrapper">
                            <select id="reassignTargetCategorySelect" class="form-control"></select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="deleteActionType" value="unlink" id="deleteActionUnlink" onchange="toggleDeleteActionInputs()">
                            <span>Unlink articles (leave uncategorized)</span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelCategoryActionPanel()">Cancel</button>
                        <button type="submit" id="confirmDeleteCategoryBtn" class="btn btn-danger btn-sm">Confirm Delete</button>
                    </div>
                </form>
            </div>

            <!-- Inline Rename Subpanel -->
            <div id="categoryRenamePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px;">
                    Rename Category: <span id="renameOldCategoryName"></span>
                </h4>
                <form id="categoryRenameForm" onsubmit="handleExecuteCategoryRename(event)">
                    <input type="hidden" id="renameCategoryId">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="renameCategoryNewNameInput" class="form-control" placeholder="New name..." required maxlength="60">
                        <button type="submit" id="confirmRenameCategoryBtn" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelCategoryActionPanel()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Close</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/articles.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
