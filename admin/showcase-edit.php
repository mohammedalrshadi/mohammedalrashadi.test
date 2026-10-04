<?php
// ============================================================
// HOME SHOWCASE ITEM EDITOR — Server-Side Protected Admin Page
// admin/showcase-edit.php
//
// Dedicated full-form editor for adding and editing items on the
// Home Showcase curation rail directly below the hero.
// Backed by api/showcase/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'showcase';
$showcaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($showcaseId > 0);

$pageTitle = ($isEdit ? 'Edit Showcase Item' : 'New Showcase Item');

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
            <a href="showcase.php">Home Showcase</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);" id="breadcrumbCurrent"><?= $isEdit ? 'Edit Item #' . $showcaseId : 'New Item' ?></span>
        </div>
        <h1 class="page-title" id="editorTitle"><?= $isEdit ? 'Edit Showcase Item' : 'Add Showcase Item' ?></h1>
        <p class="page-description">Attach a product, image, project, or writing to the homepage presentation rail.</p>
    </div>

    <div class="header-actions">
        <a href="showcase.php" class="btn btn-secondary" id="backToShowcaseBtn">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back to Showcase</span>
        </a>

        <a href="/" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" title="View Public Site">
            <i class="fas fa-external-link-alt" aria-hidden="true" style="font-size: 11px;"></i>
            <span>Live Homepage</span>
        </a>

        <button type="button" class="btn btn-primary" id="headerSaveShowcaseBtn" onclick="submitShowcaseForm()">
            <i class="fas fa-save" aria-hidden="true"></i>
            <span>Save Item</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. SHOWCASE ITEM COMPOSER (FORM WORKSPACE)
====================================================== -->
<section class="admin-card" id="showcaseComposer" aria-label="Showcase Editor">

    <div class="admin-card-header">
        <div>
            <h2 class="card-title" id="showcaseFormTitle"><?= $isEdit ? 'Edit Showcase Item' : 'Add Showcase Item' ?></h2>
            <p class="card-subtitle">Choose content type, link to existing entity or asset, and optionally configure custom overrides.</p>
        </div>
        <span id="showcaseFormStatus" class="status-badge" style="display: none;"></span>
    </div>

    <form id="showcaseForm" novalidate style="padding: 24px;">

        <input type="hidden" id="showcase_id" value="<?= $isEdit ? $showcaseId : 0 ?>">

        <!-- Content Type Selector -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="showcase_item_type" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px;">
                Content Type <span style="color: var(--accent);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                <label class="type-pill" style="cursor: pointer; text-align: center; border: 1px solid var(--border-medium); border-radius: var(--radius-sm); padding: 12px 6px; font-size: 12.5px; font-weight: 500; transition: all .15s ease;">
                    <input type="radio" name="item_type_radio" value="product" checked style="margin-right: 4px;">
                    <div><i class="fas fa-store" style="margin-bottom: 6px; display: block; font-size: 16px; color: var(--color-tertiary);"></i>Product</div>
                </label>
                <label class="type-pill" style="cursor: pointer; text-align: center; border: 1px solid var(--border-medium); border-radius: var(--radius-sm); padding: 12px 6px; font-size: 12.5px; font-weight: 500; transition: all .15s ease;">
                    <input type="radio" name="item_type_radio" value="project" style="margin-right: 4px;">
                    <div><i class="fas fa-code-branch" style="margin-bottom: 6px; display: block; font-size: 16px; color: var(--color-primary);"></i>Project</div>
                </label>
                <label class="type-pill" style="cursor: pointer; text-align: center; border: 1px solid var(--border-medium); border-radius: var(--radius-sm); padding: 12px 6px; font-size: 12.5px; font-weight: 500; transition: all .15s ease;">
                    <input type="radio" name="item_type_radio" value="writing" style="margin-right: 4px;">
                    <div><i class="far fa-file-alt" style="margin-bottom: 6px; display: block; font-size: 16px; color: var(--color-success);"></i>Writing</div>
                </label>
                <label class="type-pill" style="cursor: pointer; text-align: center; border: 1px solid var(--border-medium); border-radius: var(--radius-sm); padding: 12px 6px; font-size: 12.5px; font-weight: 500; transition: all .15s ease;">
                    <input type="radio" name="item_type_radio" value="image" style="margin-right: 4px;">
                    <div><i class="fas fa-image" style="margin-bottom: 6px; display: block; font-size: 16px; color: var(--color-warning);"></i>Image</div>
                </label>
            </div>
        </div>

        <!-- Dynamic Entity Picker for Product -->
        <div id="groupProductPicker" class="form-group" style="margin-bottom: 20px;">
            <label for="select_product_id" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                Select Store Product <span style="color: var(--accent);">*</span>
            </label>
            <select id="select_product_id" class="form-control" style="width: 100%;">
                <option value="">-- Choose a Product --</option>
            </select>
            <span class="form-help" style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 5px;">
                References products from the Store catalog. Links automatically to <code style="font-family: var(--font-mono); color: var(--accent);">/store/{slug}</code>.
            </span>
        </div>

        <!-- Dynamic Entity Picker for Project -->
        <div id="groupProjectPicker" class="form-group" style="margin-bottom: 20px; display: none;">
            <label for="select_project_id" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                Select Engineering Project <span style="color: var(--accent);">*</span>
            </label>
            <select id="select_project_id" class="form-control" style="width: 100%;">
                <option value="">-- Choose a Project --</option>
            </select>
            <span class="form-help" style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 5px;">
                References case studies from Projects. Links to <code style="font-family: var(--font-mono); color: var(--accent);">/project.php?id={id}</code>.
            </span>
        </div>

        <!-- Dynamic Entity Picker for Writing -->
        <div id="groupWritingPicker" class="form-group" style="margin-bottom: 20px; display: none;">
            <label for="select_writing_id" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                Select Writing / Essay <span style="color: var(--accent);">*</span>
            </label>
            <select id="select_writing_id" class="form-control" style="width: 100%;">
                <option value="">-- Choose a Writing Essay --</option>
            </select>
            <span class="form-help" style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 5px;">
                References technical essays from Writing. Links to <code style="font-family: var(--font-mono); color: var(--accent);">/post.php?id={id}</code>.
            </span>
        </div>

        <!-- Standalone Image Inputs -->
        <div id="groupImageInputs" style="display: none; margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 14px;">
                <label for="input_image_url" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                    Image Asset URL / Path <span style="color: var(--accent);">*</span>
                </label>
                <input type="text" id="input_image_url" class="form-control" placeholder="/uploads/diagram.png or https://...">
                <span class="form-help" style="font-size: 11.5px; color: var(--text-muted); display: block; margin-top: 5px;">
                    Provide the image path or select from <a href="media.php" target="_blank" style="color: var(--accent);">Media Library</a>.
                </span>
            </div>
            <div class="form-group" style="margin-bottom: 14px;">
                <label for="input_alt_text" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                    Alt Text (Accessibility &amp; SEO)
                </label>
                <input type="text" id="input_alt_text" class="form-control" placeholder="Descriptive alternative text for image">
            </div>
            <div class="form-group">
                <label for="input_link_url" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px;">
                    Destination Link URL (Optional)
                </label>
                <input type="text" id="input_link_url" class="form-control" placeholder="/gallery.php or custom URL">
            </div>
        </div>

        <!-- Custom Overrides (Collapsible / Optional) -->
        <details style="background: var(--bg-hover); border: 1px solid var(--border-medium); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px;">
            <summary style="font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-secondary); user-select: none;">
                <span>Optional Overrides (Title, Excerpt, Custom Image)</span>
            </summary>
            <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label for="override_title" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">Custom Title Override</label>
                    <input type="text" id="override_title" class="form-control" placeholder="Leave empty to use entity's default title">
                </div>
                <div class="form-group">
                    <label for="override_desc" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">Custom Excerpt / Description Override</label>
                    <textarea id="override_desc" class="form-control" rows="2" placeholder="Leave empty to use entity's default summary" style="resize: vertical;"></textarea>
                </div>
                <div class="form-group" id="override_image_container">
                    <label for="override_image" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">Custom Thumbnail URL Override</label>
                    <input type="text" id="override_image" class="form-control" placeholder="Leave empty to use entity's default thumbnail">
                </div>
                <div class="form-group" id="override_alt_container">
                    <label for="override_alt" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">Alt Text (Accessibility &amp; SEO)</label>
                    <input type="text" id="override_alt" class="form-control" placeholder="Leave empty to use entity's title">
                </div>
            </div>
        </details>

        <!-- Status Toggle -->
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--bg-hover); border-radius: var(--radius-sm); border: 1px solid var(--border-medium); margin-bottom: 20px;">
            <div>
                <strong style="font-size: 13.5px; color: var(--text-primary); display: block;">Active in Showcase Strip</strong>
                <span style="font-size: 12px; color: var(--text-muted);">When disabled, this item remains in your curated sequence but is hidden from public homepage visitors.</span>
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px; margin: 0; flex-shrink: 0;">
                <input type="checkbox" id="showcase_is_enabled" checked style="opacity: 0; width: 0; height: 0;">
                <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border-medium); transition: .3s; border-radius: 24px;"></span>
            </label>
        </div>

        <!-- Validation Error Box -->
        <div id="modalErrorBox" style="display: none; padding: 12px 16px; border-radius: var(--radius-sm); background: var(--danger-subtle); border: 1px solid var(--border-medium); color: var(--danger); font-size: 12.5px; margin-bottom: 20px;"></div>
        <div id="showcaseErrorBox" style="display: none; padding: 12px 16px; border-radius: var(--radius-sm); background: var(--danger-subtle); border: 1px solid var(--border-medium); color: var(--danger); font-size: 12.5px; margin-bottom: 20px;"></div>

        <!-- Form Actions Bar -->
        <div class="form-actions-bar" style="padding-top: 16px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
            <a href="showcase.php" id="cancelShowcaseBtn" class="btn btn-secondary" onclick="handleShowcaseCancel(event)">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" id="saveShowcaseBtn" class="btn btn-primary">
                <i class="fas fa-save"></i>
                <span id="saveShowcaseBtnText"><?= $isEdit ? 'Save Changes' : 'Save Showcase Item' ?></span>
            </button>
            <span id="showcaseStatusMsg" style="font-size: 12.5px; color: var(--text-muted); font-family: var(--font-mono);"></span>
        </div>

    </form>
</section>

<style>
/* Type Pill Selection Styles */
.type-pill:has(input:checked) {
    border-color: var(--color-primary) !important;
    background: var(--color-surface-elevated) !important;
    color: var(--color-primary) !important;
}
.switch input:checked + .slider {
    background-color: var(--color-primary);
}
.switch .slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
.switch input:checked + .slider:before {
    transform: translateX(20px);
}
</style>

<?php
$pageScripts = ['js/showcase-edit.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

