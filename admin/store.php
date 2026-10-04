<?php
// ============================================================
// STORE — Server-Side Protected Admin Page
// Management interface for Store digital products and catalog.
// Backed by api/products/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/stats_helper.php';

requireAdminPage('login.php');

$activeNav = 'store';
$pageTitle = 'Store';

$storeStats = ['total' => 0, 'published' => 0, 'draft' => 0, 'downloads' => 0];
try {
    $pdoStats = getDB();
    $counts = getPlatformEntityCounts($pdoStats);
    $storeStats = $counts['products'];
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
            <span style="color: var(--accent);">Store</span>
        </div>
        <h1 class="page-title">Store</h1>
        <p class="page-description">Curate digital templates, engineering resources, study guides, and external catalog listings.</p>
    </div>

    <div class="header-actions">
        <a href="/store" target="_blank" class="btn btn-secondary" title="View Public Store">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Store</span>
        </a>

        <a href="store-edit.php" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>New Product</span>
        </a>
    </div>
</header>

<!-- =====================================================
     SECTION OVERVIEW RAIL
====================================================== -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <div class="stat-card" style="cursor: pointer;" onclick="filterProductsByStatus('all')">
        <div class="stat-header">
            <span class="stat-label">Catalog Products</span>
            <i class="fas fa-boxes-stacked stat-icon" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewTotalProducts"><?= (int)$storeStats['total'] ?></div>
        <div class="stat-meta">Templates &amp; tools</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="filterProductsByStatus('published')">
        <div class="stat-header">
            <span class="stat-label">Published</span>
            <i class="fas fa-circle-check stat-icon" style="color: var(--success);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewPublishedProducts" style="color: var(--success);"><?= (int)$storeStats['published'] ?></div>
        <div class="stat-meta">Active in public store</div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-label">Claimed Downloads</span>
            <i class="fas fa-cloud-arrow-down stat-icon" style="color: var(--accent);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewProductDownloads" style="color: var(--accent);"><?= (int)$storeStats['downloads'] ?></div>
        <div class="stat-meta">Total library claims</div>
    </div>

    <div class="stat-card" style="cursor: pointer;" onclick="filterProductsByStatus('draft')">
        <div class="stat-header">
            <span class="stat-label">In-Progress Drafts</span>
            <i class="fas fa-file-pen stat-icon" style="color: var(--warning);" aria-hidden="true"></i>
        </div>
        <div class="stat-value" id="overviewDraftProducts" style="color: var(--warning);"><?= (int)$storeStats['draft'] ?></div>
        <div class="stat-meta">Unpublished catalog items</div>
    </div>
</div>


<!-- =====================================================
     2. CONTENT TABLE & DISCOVERY
====================================================== -->
<section class="table-container" id="productsTableContainer" aria-label="Products Catalog Directory">

    <!-- Status Filter Tabs -->
    <div style="padding: 16px 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="productStatusTabs" role="tablist" aria-label="Filter products by status">
            <button type="button" class="filter-tab active" data-status="all" role="tab" aria-selected="true" onclick="filterProductsByStatus('all')">
                All <span class="tab-counter" id="tabCountAll">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="published" role="tab" aria-selected="false" onclick="filterProductsByStatus('published')">
                Published <span class="tab-counter" id="tabCountPublished">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="draft" role="tab" aria-selected="false" onclick="filterProductsByStatus('draft')">
                Draft <span class="tab-counter" id="tabCountDraft">0</span>
            </button>
            <button type="button" class="filter-tab" data-status="archived" role="tab" aria-selected="false" onclick="filterProductsByStatus('archived')">
                Archived <span class="tab-counter" id="tabCountArchived">0</span>
            </button>
        </div>
        <span id="productResultCount" class="page-meta" style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);"></span>
    </div>

    <!-- Search & Filters Toolbar -->
    <div style="padding: 14px 20px 18px; border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div class="search-input-wrapper">
            <i class="fas fa-search toolbar-search-icon" aria-hidden="true"></i>
            <input
                type="search"
                id="productSearchInput"
                class="form-control"
                placeholder="Search products by title, slug, or keywords..."
                maxlength="100"
                aria-label="Search products"
                oninput="debounceProductSearch()"
            >
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select id="productCategoryFilter" class="form-control" style="width: auto; min-width: 160px;" onchange="loadProducts()" aria-label="Filter by category">
                <option value="">All Categories</option>
                <option value="Notion Templates">Notion Templates</option>
                <option value="Website Templates">Website Templates</option>
                <option value="Developer Resources">Developer Resources</option>
                <option value="Study Resources">Study Resources</option>
                <option value="Guides &amp; PDFs">Guides &amp; PDFs</option>
                <option value="UI / Design Resources">UI / Design Resources</option>
                <option value="Tools">Tools</option>
                <option value="Other">Other</option>
            </select>

            <select id="productTypeFilter" class="form-control" style="width: auto; min-width: 140px;" onchange="loadProducts()" aria-label="Filter by type">
                <option value="">All Types</option>
                <option value="external">External Product</option>
                <option value="free_download">Free Download</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="admin-table" id="productsTable">
            <thead>
                <tr>
                    <th style="width: 70px;">Media</th>
                    <th>Product Title &amp; Slug</th>
                    <th style="width: 160px;">Category</th>
                    <th style="width: 130px;">Type</th>
                    <th style="width: 100px;">Price</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 80px; text-align: center;">Order</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                <!-- Populated via js/store.js -->
            </tbody>
        </table>
    </div>

    <!-- Empty State -->
    <div id="productsEmptyState" class="empty-state" style="display: none; padding: 48px 20px; text-align: center;">
        <i class="fas fa-store" style="font-size: 36px; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3 style="font-size: 16px; color: var(--text-primary); margin-bottom: 8px;">No Products Found</h3>
        <p style="font-size: 13px; color: var(--text-muted); max-width: 440px; margin: 0 auto 20px;">
            Add digital templates, cheat sheets, developer tools, or external affiliate recommendations to your store catalog.
        </p>
        <a href="store-edit.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add First Product
        </a>
    </div>

</section>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteProductModal" role="dialog" aria-modal="true" aria-labelledby="deleteProductModalTitle" style="display: none;">
    <div class="modal-backdrop" onclick="closeDeleteProductModal()"></div>
    <div class="modal-dialog" style="max-width: 460px;">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteProductModalTitle">Confirm Delete Product</h3>
            <button type="button" class="btn-close" onclick="closeDeleteProductModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="color: var(--text-primary); font-size: 14px; margin-bottom: 12px;">
                Are you sure you want to delete <strong id="deleteProductTitleDisplay" style="color: var(--accent);"></strong>?
            </p>
            <p style="color: var(--text-muted); font-size: 12.5px; line-height: 1.6;">
                This action will remove the product and any associated protected download files from the server. This cannot be undone.
            </p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeDeleteProductModal()">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteProductBtn" onclick="executeDeleteProduct()">
                <i class="fas fa-trash-alt"></i> Delete Product
            </button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/store.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

