<?php
// ============================================================
// CATEGORIES — Server-Side Protected Admin Page
// Full-page Taxonomy & Category Management Workspace.
// Backed by categories table and api/categories endpoints.
// Supports: blog, project (projects), lab, product.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$activeNav = 'categories';
$pageTitle = 'Taxonomy & Categories';

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
            <span style="color: var(--accent);">Categories</span>
        </div>
        <h1 class="page-title">Taxonomy &amp; Categories</h1>
        <p class="page-description">Create, rename, and manage taxonomies across articles, engineering projects, laboratory experiments, and store products.</p>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="focusNewCategoryInput()">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>Add Category</span>
        </button>
    </div>
</header>

<!-- =====================================================
     2. SUMMARY METRICS RAIL
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Total Categories</span>
            <i class="fas fa-tags stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statTotalCategories">0</div>
        <div class="stat-meta">Active taxonomies</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Article Categories</span>
            <i class="far fa-file-alt stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statArticleCategories" style="color: var(--accent);">0</div>
        <div class="stat-meta">Writing &amp; essays</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Project Categories</span>
            <i class="fas fa-code-branch stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statProjectCategories" style="color: var(--success);">0</div>
        <div class="stat-meta">Systems &amp; architecture</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Lab Categories</span>
            <i class="fas fa-flask stat-icon" style="color: var(--warning);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statLabCategories" style="color: var(--warning);">0</div>
        <div class="stat-meta">Benchmarks &amp; experiments</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Store Categories</span>
            <i class="fas fa-store stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="statProductCategories" style="color: var(--accent);">0</div>
        <div class="stat-meta">Templates &amp; products</div>
    </div>
</div>

<!-- =====================================================
     3. CATEGORY CREATOR BAR
====================================================== -->
<section class="admin-card" style="margin-bottom: 24px;">
    <div class="admin-card-header">
        <div>
            <h2 class="card-title">Add New Category</h2>
            <p class="card-subtitle">Create a new category for the currently selected taxonomy group.</p>
        </div>
    </div>

    <form id="categoryCreateForm" onsubmit="handleCategoryCreate(event)" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 240px;">
            <label for="categoryNameInput" style="display: block; font-size: 12.5px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">Category Name <span style="color: var(--accent);">*</span></label>
            <input type="text" id="categoryNameInput" class="form-control" placeholder="e.g. Distributed Systems, Linux Kernel, Database..." required maxlength="255">
        </div>
        <div style="width: 220px;">
            <label for="categoryTypeSelect" style="display: block; font-size: 12.5px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px;">Taxonomy Group</label>
            <select id="categoryTypeSelect" class="form-control">
                <option value="blog">Writing</option>
                <option value="project">Projects</option>
                <option value="lab">Studio Lab</option>
                <option value="product">Store</option>
            </select>
        </div>
        <div>
            <button type="submit" id="categoryCreateBtn" class="btn btn-primary" style="height: 42px; padding: 0 20px;">
                <i class="fas fa-plus"></i>
                <span>Create Category</span>
            </button>
        </div>
    </form>
</section>

<!-- =====================================================
     4. TAXONOMY TABLE WORKSPACE
====================================================== -->
<section class="table-container" aria-label="Categories Directory">

    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="categoryTypeTabs" role="tablist" aria-label="Filter categories by type" style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="filter-tab active" data-type="blog" role="tab" aria-selected="true" onclick="switchCategoryTab('blog')">
                <i class="far fa-file-alt" style="margin-right: 6px;"></i> Writing <span class="tab-counter" id="tabCountCatBlog">0</span>
            </button>
            <button type="button" class="filter-tab" data-type="project" role="tab" aria-selected="false" onclick="switchCategoryTab('project')">
                <i class="fas fa-code-branch" style="margin-right: 6px;"></i> Projects <span class="tab-counter" id="tabCountCatAchieve">0</span>
            </button>
            <button type="button" class="filter-tab" data-type="lab" role="tab" aria-selected="false" onclick="switchCategoryTab('lab')">
                <i class="fas fa-flask" style="margin-right: 6px;"></i> Studio Lab <span class="tab-counter" id="tabCountCatLab">0</span>
            </button>
            <button type="button" class="filter-tab" data-type="product" role="tab" aria-selected="false" onclick="switchCategoryTab('product')">
                <i class="fas fa-store" style="margin-right: 6px;"></i> Store <span class="tab-counter" id="tabCountCatProduct">0</span>
            </button>
        </div>
        <span id="categoryResultCount" class="page-meta" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
    </div>

    <div class="table-responsive">
        <table class="admin-table" id="categoriesTable">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Group</th>
                    <th style="text-align: center; width: 130px;">Published Items</th>
                    <th style="text-align: center; width: 110px;">Draft Items</th>
                    <th style="text-align: center; width: 110px;">Total Items</th>
                    <th style="text-align: right; width: 160px;">Actions</th>
                </tr>
            </thead>
            <tbody id="categoriesTableBody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <i class="fas fa-spinner fa-spin"></i> Loading categories...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div id="categoryEmptyState" class="empty-state" style="display: none; padding: 48px 20px; text-align: center;">
        <i class="fas fa-tags" style="font-size: 36px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3 style="font-size: 16px; color: var(--text-primary); margin-bottom: 8px;">No Categories Created Yet</h3>
        <p style="font-size: 13px; color: var(--text-muted); max-width: 420px; margin: 0 auto 20px;">
            Create your first taxonomy category to organize technical publications and projects.
        </p>
    </div>

</section>

<!-- =====================================================
     5. RENAME CATEGORY MODAL
====================================================== -->
<div id="renameCategoryModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="renameCategoryModalTitle" style="display: none;">
    <div class="modal-dialog" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title" id="renameCategoryModalTitle">Rename Category</h3>
            <button type="button" class="modal-close" onclick="closeRenameCategoryModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="renameCategoryForm" onsubmit="handleCategoryRename(event)">
            <input type="hidden" id="renameCategoryId" value="0">
            <input type="hidden" id="renameCategoryType" value="blog">
            <div class="modal-body" style="padding: 16px 0;">
                <div class="form-group">
                    <label for="renameCategoryOldName" style="font-size: 12.5px; color: var(--text-muted);">Current Name</label>
                    <input type="text" id="renameCategoryOldName" class="form-control" disabled style="background: var(--bg-canvas);">
                </div>
                <div class="form-group" style="margin-top: 12px;">
                    <label for="renameCategoryNewName" style="font-size: 12.5px; color: var(--text-primary);">New Name <span style="color: var(--accent);">*</span></label>
                    <input type="text" id="renameCategoryNewName" class="form-control" required maxlength="255" placeholder="Enter updated category name...">
                    <span class="form-help" style="margin-top: 4px; display: block; font-size: 11px; color: var(--text-muted);">
                        Renaming updates the category tag across all existing items automatically.
                    </span>
                </div>
            </div>
            <div class="modal-footer" style="padding-top: 14px; margin-top: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeRenameCategoryModal()">Cancel</button>
                <button type="submit" id="renameCategorySubmitBtn" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- =====================================================
     6. DELETE / REASSIGN CATEGORY MODAL
====================================================== -->
<div id="deleteCategoryModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="deleteCategoryModalTitle" style="display: none;">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteCategoryModalTitle" style="color: var(--danger);">Delete Category</h3>
            <button type="button" class="modal-close" onclick="closeDeleteCategoryModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="deleteCategoryForm" onsubmit="handleCategoryDelete(event)">
            <input type="hidden" id="deleteCategoryId" value="0">
            <input type="hidden" id="deleteCategoryType" value="blog">
            <div class="modal-body" style="padding: 16px 0; display: flex; flex-direction: column; gap: 14px;">
                <p style="font-size: 13.5px; color: var(--text-primary); margin: 0;">
                    Are you sure you want to delete category <strong id="deleteCategoryName" style="color: var(--accent);">—</strong>?
                </p>

                <!-- In-use notice & reassignment selector -->
                <div id="deleteCategoryInUseNotice" style="display: none; background: rgba(255, 122, 122, 0.08); border: 1px solid rgba(255, 122, 122, 0.25); border-radius: var(--radius-sm); padding: 12px 14px;">
                    <p style="font-size: 12.5px; color: var(--text-primary); margin: 0 0 10px 0;">
                        <i class="fas fa-triangle-exclamation" style="color: var(--danger); margin-right: 6px;"></i>
                        This category has <strong id="deleteCategoryItemCount">0</strong> active items assigned. To prevent orphaned records, select a replacement category:
                    </p>
                    <label for="deleteReassignTargetSelect" style="font-size: 12px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 4px;">Reassign Items To:</label>
                    <select id="deleteReassignTargetSelect" class="form-control form-control-sm" style="font-size: 13px;">
                        <option value="">Select replacement category…</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer" style="padding-top: 14px; margin-top: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeDeleteCategoryModal()">Cancel</button>
                <button type="submit" id="deleteCategoryConfirmBtn" class="btn btn-danger btn-sm">Delete Category</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = ['js/categories.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
