<?php
// ============================================================
// JOURNEY MILESTONE EDITOR — Server-Side Protected Admin Page
// Full-form editor for creating and editing Journey milestones.
// Backed by api/journey/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'journey';
$milestoneId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($milestoneId > 0);

$pageTitle = ($isEdit ? 'Edit Milestone' : 'New Milestone');

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
            <a href="journey.php">Journey</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);" id="breadcrumbCurrent"><?= $isEdit ? 'Edit Milestone #' . $milestoneId : 'New Milestone' ?></span>
        </div>
        <h1 class="page-title" id="editorTitle"><?= $isEdit ? 'Edit Journey Milestone' : 'Add New Milestone' ?></h1>
        <p class="page-description">Manage milestones, architectural shifts, and learning events shown on the public Journey timeline.</p>
    </div>

    <div class="header-actions">
        <a href="journey.php" class="btn btn-secondary" id="backToJourneyBtn">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back to Journey</span>
        </a>

        <?php if ($isEdit): ?>
        <a href="/journey.php" target="_blank" class="btn btn-secondary" id="viewPublicLink" title="View Public Journey Timeline">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Journey</span>
        </a>
        <?php endif; ?>

        <button type="button" class="btn btn-primary" id="headerSaveMilestoneBtn" onclick="submitMilestoneForm()">
            <i class="fas fa-save" aria-hidden="true"></i>
            <span>Save Milestone</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. MILESTONE COMPOSER (FORM WORKSPACE)
====================================================== -->
<section class="admin-card" id="milestoneComposer" aria-label="Milestone Editor">

    <div class="admin-card-header">
        <div>
            <h2 class="card-title" id="milestoneFormTitle"><?= $isEdit ? 'Edit Milestone' : 'Add New Milestone' ?></h2>
            <p class="card-subtitle">Fields marked * are required. Draft milestones remain hidden from the public Journey timeline.</p>
        </div>
        <span id="milestoneFormStatus" class="status-badge" style="display: none;"></span>
    </div>

    <form id="milestoneForm" novalidate style="padding: 24px;">

        <input type="hidden" id="milestone_id" value="<?= $isEdit ? $milestoneId : 0 ?>">

        <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
                <label for="milestone_title">Milestone Title <span style="color: var(--accent);">*</span></label>
                <input type="text" id="milestone_title" class="form-control" required maxlength="200"
                       placeholder="e.g., Shifted from framework-first to systems-first learning">
            </div>

            <div class="form-group">
                <label for="milestone_period">Period Label <span style="color: var(--accent);">*</span></label>
                <input type="text" id="milestone_period" class="form-control" required maxlength="80"
                       placeholder="e.g., Late 2023">
            </div>
        </div>

        <div class="form-grid" style="margin-top: 14px;">
            <div class="form-group">
                <label for="milestone_category">Category</label>
                <select id="milestone_category" class="form-control">
                    <option value="milestone">Milestone</option>
                    <option value="shift">Architectural Shift</option>
                    <option value="benchmark">Benchmark / Investigation</option>
                    <option value="learning">Learning Event</option>
                </select>
            </div>

            <div class="form-group">
                <label for="milestone_status">Status</label>
                <select id="milestone_status" class="form-control">
                    <option value="draft">Draft (hidden from public site)</option>
                    <option value="published">Published (visible on public site)</option>
                </select>
            </div>
        </div>

        <div class="form-grid" style="margin-top: 14px;">
            <div class="form-group">
                <label for="milestone_icon">Icon Name</label>
                <input type="text" id="milestone_icon" class="form-control" maxlength="60"
                       placeholder="timeline (Material Symbols name)">
                <span class="form-help">Any <a href="https://fonts.google.com/icons" target="_blank" rel="noopener">Material Symbols</a> icon name, e.g. timeline, insights, schema.</span>
            </div>

            <div class="form-group">
                <label for="milestone_sort_order">Sort Order</label>
                <input type="number" id="milestone_sort_order" class="form-control" value="0" step="1">
                <span class="form-help">Lower numbers appear first on the timeline.</span>
            </div>
        </div>

        <div class="form-grid" style="margin-top: 14px;">
            <div class="form-group" style="grid-column: span 2;">
                <label for="milestone_description">Description <span style="color: var(--accent);">*</span></label>
                <textarea id="milestone_description" rows="5" class="form-control" required
                          placeholder="What changed, what prompted it, and what it led to."></textarea>
            </div>
        </div>

        <div class="form-actions-bar" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
            <a href="journey.php" id="milestoneCancelBtn" class="btn btn-secondary" onclick="handleMilestoneCancel(event)">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" id="milestoneSaveBtn" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <span id="milestoneSaveBtnText"><?= $isEdit ? 'Save Changes' : 'Create Milestone' ?></span>
            </button>
            <span id="milestoneStatusMsg" style="font-size: 12.5px; color: var(--text-muted); font-family: var(--font-mono);"></span>
        </div>
    </form>
</section>

<?php
$pageScripts = ['js/journey-edit.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

