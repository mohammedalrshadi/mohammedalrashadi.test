<?php
// ============================================================
// HOME SHOWCASE CURATION — Server-Side Protected Admin Page
// admin/showcase.php
//
// Visual curation workspace for the Home Showcase mixed-content strip
// directly below the header/hero on the public homepage.
// Backed by api/showcase/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'showcase';
$pageTitle = 'Home Showcase';

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
            <span>Commerce &amp; Assets</span>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">Home Showcase</span>
        </div>
        <h1 class="page-title">Home Showcase</h1>
        <p class="page-description">Curate the horizontal mixed-content strip directly below the Home header/hero. Control order and spotlight products, images, projects, and writing.</p>
    </div>

    <div class="header-actions">
        <a href="/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" title="View Public Site">
            <i class="fas fa-external-link-alt" aria-hidden="true" style="font-size: 11px;"></i>
            <span>Live Homepage</span>
        </a>
        <a href="showcase-edit.php" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>Add Showcase Item</span>
        </a>
    </div>
</header>

<!-- =====================================================
     2. ARCHITECTURAL DISTINCTION CALLOUT
====================================================== -->
<div style="background: var(--color-surface-container-low, var(--bg-surface)); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: flex-start; gap: 14px;">
    <i class="fas fa-circle-info" style="color: var(--color-primary); font-size: 18px; margin-top: 2px;"></i>
    <div style="font-size: 13px; line-height: 1.6; color: var(--text-secondary);">
        <strong style="color: var(--text-primary); display: block; margin-bottom: 2px;">Home Showcase Architecture &amp; Single Source of Truth</strong>
        The Home Showcase is a <strong>curated presentation rail</strong> on the homepage. It references existing entities (Store products, engineering projects, articles) or standalone visual assets. It does <em>not</em> duplicate content. Removing an item from Showcase only removes it from the homepage strip; your original product, project, or article remains intact.
        <span style="display: block; margin-top: 4px; color: var(--text-muted); font-size: 12px;">
            <i class="fas fa-shield-halved" style="color: var(--color-primary); margin-right: 4px;"></i>
            <strong>Distinct from Gallery:</strong> The dedicated Gallery (<code style="font-family: var(--font-mono); color: var(--color-primary);">project_images</code>) manages project media. Home Showcase is the Home-level mixed strip.
        </span>
    </div>
</div>

<!-- =====================================================
     3. ACTIVE SHOWCASE STRIP (ORDERED SEQUENCE)
====================================================== -->
<section class="admin-card" style="margin-bottom: 32px;" aria-label="Showcase Strip Sequence">
    <div class="admin-card-header" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <h2 class="card-title" style="margin: 0;">Homepage Showcase Rail</h2>
                <span class="status-badge status-published" id="showcaseCountBadge">0 Items Active</span>
            </div>
            <p class="card-subtitle" style="margin-top: 4px;">These items appear on the homepage in the exact horizontal sequence listed below. Use the arrows to reorder.</p>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <button type="button" id="refreshShowcaseBtn" class="btn btn-secondary btn-sm" title="Refresh sequence">
                <i class="fas fa-rotate"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div id="showcaseLoadingState" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
        <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 12px; color: var(--accent);"></i>
        <p>Loading showcase items...</p>
    </div>

    <!-- Empty State -->
    <div id="showcaseEmptyState" style="display: none; text-align: center; padding: 56px 20px; color: var(--text-muted);">
        <div style="width: 52px; height: 52px; margin: 0 auto 16px; border-radius: 50%; background: var(--bg-hover); display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-layer-group" style="font-size: 22px; color: var(--text-muted);"></i>
        </div>
        <h3 style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">No Showcase Items Configured</h3>
        <p style="font-size: 13px; max-width: 440px; margin: 0 auto 20px; line-height: 1.5;">The homepage is currently using automatic fallbacks. Add products, projects, writing, or images to create your custom curated strip.</p>
        <a href="showcase-edit.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add First Item
        </a>
    </div>

    <!-- Showcase Sequence Table / Cards -->
    <div class="table-responsive" id="showcaseTableContainer" style="display: none;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 70px; text-align: center;">Order</th>
                    <th style="width: 80px;">Preview</th>
                    <th style="min-width: 220px;">Item &amp; Destination</th>
                    <th style="width: 120px;">Type</th>
                    <th style="width: 100px; text-align: center;">Status</th>
                    <th style="width: 180px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="showcaseTableBody">
                <!-- Injected dynamically by admin/js/showcase.js -->
            </tbody>
        </table>
    </div>
</section>

<?php
$pageScripts = ['js/showcase.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
