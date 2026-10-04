<?php
// ============================================================
// ACHIEVEMENTS EDITOR
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'achievements';
$achieveId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit    = ($achieveId > 0);
$pageTitle = ($isEdit ? 'Edit Achievement' : 'New Achievement');

require __DIR__ . '/partials/layout_top.php';
?>

<header class="admin-page-header">
    <div class="header-titles">
        <div class="breadcrumb" aria-label="breadcrumb">
            <a href="index.php">Admin Studio</a>
            <span class="breadcrumb-sep">/</span>
            <a href="achievements.php">Achievements</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);"><?= $isEdit ? 'Edit' : 'New' ?></span>
        </div>
        <h1 class="page-title"><?= $pageTitle ?></h1>
    </div>
</header>

<section class="editor-card">
    <form id="achieveForm" novalidate>
        <input type="hidden" id="achieve_id" value="<?= $isEdit ? $achieveId : '' ?>">

        <!-- SECTION: BASIC INFORMATION -->
        <fieldset class="form-fieldset">
            <legend class="fieldset-legend">Basic Information</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="achieve_title">Title <span style="color: var(--accent);">*</span></label>
                    <input type="text" id="achieve_title" class="form-control" required>
                    <div id="title_error" style="color: red; display: none; font-size: 0.85em; margin-top: 4px;"></div>
                </div>
                <div class="form-group">
                    <label for="achieve_slug">Slug <span style="color: var(--accent);">*</span></label>
                    <input type="text" id="achieve_slug" class="form-control" required>
                    <div id="slug_error" style="color: red; display: none; font-size: 0.85em; margin-top: 4px;"></div>
                </div>
            </div>
            <div class="form-group">
                <label for="achieve_category">Type</label>
                <input type="text" id="achieve_category" class="form-control" placeholder="e.g. Award, Certification, Milestone">
            </div>
        </fieldset>

        <!-- SECTION: DETAILS -->
        <fieldset class="form-fieldset">
            <legend class="fieldset-legend">Details</legend>
            <div class="form-grid">
                <div class="form-group">
                    <label for="achieve_organization">Issuer / Organization</label>
                    <input type="text" id="achieve_organization" class="form-control">
                </div>
                <div class="form-group">
                    <label for="achieve_date">Date Awarded</label>
                    <input type="date" id="achieve_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label for="achieve_description">Description</label>
                <textarea id="achieve_description" class="form-control" rows="4"></textarea>
            </div>
        </fieldset>

        <!-- SECTION: EVIDENCE -->
        <fieldset class="form-fieldset">
            <legend class="fieldset-legend">Evidence</legend>
            <div class="form-group">
                <label for="achieve_url">Verification URL</label>
                <input type="url" id="achieve_url" class="form-control" placeholder="https://...">
            </div>
            <div class="form-group">
                <label>Main Image</label>
                <input type="file" id="mainImageInput" accept="image/jpeg, image/png, image/webp, image/gif" style="display: none;">
                <div id="mainImageDropzone" class="media-uploader-zone" role="button" tabindex="0" style="border: 2px dashed var(--border-color); padding: 2rem; text-align: center; border-radius: 8px; margin-bottom: 1rem; cursor: pointer; position: relative; user-select: none;">
                    <div id="mainImagePreview" style="display: none; margin-bottom: 1rem;">
                        <img src="" style="max-width: 100%; max-height: 200px; object-fit: contain; border-radius: 4px;">
                        <button type="button" id="mainImageRemoveBtn" class="btn btn-danger btn-sm" style="position: absolute; top: 8px; right: 8px;"><i class="fas fa-trash"></i></button>
                    </div>
                    <div id="mainImagePlaceholder">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                        <p style="margin: 0;">Click or drag an image here to upload</p>
                    </div>
                    <div id="mainImageLoading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Uploading...
                    </div>
                </div>
                <label for="achieve_image_url" style="font-size: 0.85em;">Or paste image URL fallback:</label>
                <input type="text" id="achieve_image_url" class="form-control" placeholder="/uploads/...">
            </div>
        </fieldset>

        <!-- SECTION: GALLERY (ONLY FOR EDIT) -->
        <fieldset class="form-fieldset" id="galleryFieldset" style="<?= $isEdit ? '' : 'display: none;' ?>">
            <legend class="fieldset-legend">Extra Evidence / Gallery</legend>
            <?php if (!$isEdit): ?>
                <div class="info-alert" style="margin-bottom: 1rem; padding: 1rem; background: var(--bg-alt); border-radius: 6px;">
                    <i class="fas fa-info-circle"></i> Save the achievement first to enable the gallery.
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label>Upload Gallery Image</label>
                    <input type="file" id="galleryInput" accept="image/jpeg, image/png, image/webp, image/gif" multiple style="display: none;">
                    <div id="galleryDropzone" class="media-uploader-zone" role="button" tabindex="0" style="border: 2px dashed var(--border-color); padding: 1rem; text-align: center; border-radius: 8px; cursor: pointer; user-select: none;">
                        <div id="galleryPlaceholder">
                            <i class="fas fa-plus"></i> Click or drag to add an image
                        </div>
                        <div id="galleryLoading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Uploading...
                        </div>
                    </div>
                </div>
                <div class="gallery-grid" id="galleryGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <!-- Gallery items loaded here -->
                </div>
            <?php endif; ?>
        </fieldset>

        <!-- SECTION: PUBLISHING -->
        <fieldset class="form-fieldset" style="border-bottom: none; margin-bottom: 0;">
            <legend class="fieldset-legend">Publishing</legend>
            <div class="form-group">
                <label for="achieve_status">Status</label>
                <select id="achieve_status" class="form-control">
                    <option value="published">Published</option>
                    <option value="hidden" selected>Hidden</option>
                </select>
            </div>
        </fieldset>

        <div class="form-actions-bar">
            <a href="achievements.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" id="saveAchieveBtn" class="btn btn-primary">Save Achievement</button>
        </div>
    </form>
</section>

<?php
// We include Sortable.js for gallery reordering if available, or just common.js
$pageScripts = ['js/achievements-edit.js'];
// Try to include sortable if it exists in assets (from projects probably)
if (file_exists(dirname(__DIR__) . '/assets/vendor/sortable/Sortable.min.js')) {
    array_unshift($pageScripts, '../assets/vendor/sortable/Sortable.min.js');
}
require __DIR__ . '/partials/layout_bottom.php';
?>
