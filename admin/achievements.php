<?php
// ============================================================
// ACHIEVEMENTS — Server-Side Protected Admin Page
// Management interface for formal recognitions, certificates, and awards.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'achievements';
$pageTitle = 'Achievements';

require __DIR__ . '/partials/layout_top.php';
?>

<header class="admin-page-header">
    <div class="header-titles">
        <div class="breadcrumb" aria-label="breadcrumb">
            <a href="index.php">Admin Studio</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);">Achievements</span>
        </div>
        <h1 class="page-title">Achievements</h1>
        <p class="page-description">Manage certificates, awards, and formal recognitions.</p>
    </div>
    <div class="header-actions">
        <a href="/achievements.php" target="_blank" class="btn btn-secondary">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Page</span>
        </a>
        <a href="achievements-edit.php" class="btn btn-primary">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>New Achievement</span>
        </a>
    </div>
</header>

<section class="table-container" id="achievementsTableContainer">
    <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div class="filter-tabs" id="achieveStatusTabs" role="tablist">
            <button type="button" class="filter-tab active" data-status="all">All</button>
            <button type="button" class="filter-tab" data-status="published">Published</button>
            <button type="button" class="filter-tab" data-status="hidden">Hidden</button>
            <button type="button" class="filter-tab" data-status="trash"><i class="fas fa-trash" aria-hidden="true"></i> Trash</button>
        </div>
        <div class="search-box">
            <input type="text" id="achieveSearch" class="form-control" placeholder="Search title or org..." style="min-width: 250px;">
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table" id="achievementsTable">
            <thead>
                <tr>
                    <th style="width: 70px;">Image</th>
                    <th>Title &amp; Slug</th>
                    <th>Organization</th>
                    <th>Date</th>
                    <th style="width: 110px;">Status</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="achievementsTableBody">
                <tr><td colspan="6" style="text-align: center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    
    <div style="padding: 16px; text-align: center;">
        <button type="button" id="loadMoreBtn" class="btn btn-secondary" style="display: none;">Load More</button>
    </div>
</section>

<!-- Delete Modal -->
<div class="modal" id="deleteAchieveModal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteAchieveModalTitle">Move to Trash</h3>
            <button type="button" class="modal-close" id="closeDeleteModalBtn"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p><span id="deleteAchieveLead">Move</span> <strong id="deleteAchieveTitle"></strong> <span id="deleteAchieveTail">to the trash? You can restore it later from the Trash tab.</span></p>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" id="cancelDeleteModalBtn">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteAchieveBtn">Move to Trash</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/common.js', 'js/achievements.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
