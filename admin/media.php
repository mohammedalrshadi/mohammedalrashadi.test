<?php
// ============================================================
// MEDIA LIBRARY — Server-Side Protected Admin Page
// Unified Media Library browsing cover art, architecture diagrams,
// and uploaded assets.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/api/helpers/image_optimizer.php';

requireAdminPage('login.php');

$optCaps = imageOptimizerCapabilities();
$activeNav = 'media';
$pageTitle = 'Media Library';

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
            <span style="color: var(--accent);">Media Library</span>
        </div>
        <h1 class="page-title">Media Library &amp; Visual Assets</h1>
        <p class="page-description">Browse and organize architecture diagrams, cover illustrations, and uploaded engineering schematics.</p>
    </div>

    <div class="header-actions">
        <button type="button" class="btn btn-primary" onclick="openMediaUploadModal()">
            <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
            <span>Upload Media</span>
        </button>
    </div>
</header>


<!-- =====================================================
     1.5. IMAGE OPTIMIZATION PIPELINE DIAGNOSTIC CARD
====================================================== -->
<section class="admin-card" style="margin-bottom: 24px;" aria-label="Optimization Diagnostics">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: var(--radius-sm); background: var(--accent-subtle); display: flex; align-items: center; justify-content: center; color: var(--accent);">
                <i class="fas fa-bolt" style="font-size: 18px;"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: var(--text-primary);">Image Optimization &amp; WebP Engine</h3>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-muted);">Real-time diagnostics for host graphics capabilities and responsive variant pipelines.</p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <?php if ($optCaps['can_optimize']): ?>
                <span class="status-badge status-active" style="font-size: 11px;">
                    <i class="fas fa-check-circle"></i> Engine Active
                </span>
            <?php else: ?>
                <span class="status-badge status-danger" style="font-size: 11px;">
                    <i class="fas fa-exclamation-triangle"></i> Optimization Disabled
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 12px;">
        <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-family: var(--font-mono); margin-bottom: 4px;">GD Graphics Engine</div>
            <div style="font-weight: 600; color: var(--text-primary); font-family: var(--font-mono);"><?= htmlspecialchars($optCaps['gd_version']) ?></div>
            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;"><?= $optCaps['gd_loaded'] ? 'Loaded in PHP' : 'Not Loaded' ?></div>
        </div>

        <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-family: var(--font-mono); margin-bottom: 4px;">WebP &amp; Formats</div>
            <div style="font-weight: 600; color: <?= $optCaps['webp_support'] ? 'var(--success)' : 'var(--danger)' ?>; font-family: var(--font-mono);">
                <?= $optCaps['webp_support'] ? 'WebP Enabled' : 'No WebP' ?>
            </div>
            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">JPEG: <?= $optCaps['jpeg_support'] ? '✓' : '✗' ?> | PNG: <?= $optCaps['png_support'] ? '✓' : '✗' ?> | GIF: <?= $optCaps['gif_support'] ? '✓' : '✗' ?></div>
        </div>

        <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-family: var(--font-mono); margin-bottom: 4px;">Memory Guard</div>
            <div style="font-weight: 600; color: var(--text-primary); font-family: var(--font-mono);"><?= htmlspecialchars($optCaps['memory_limit']) ?> limit</div>
            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">Cap: 40 MP &middot; Budget: 70%</div>
        </div>

        <div style="background: var(--bg-canvas); padding: 12px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
            <div style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-family: var(--font-mono); margin-bottom: 4px;">Orientation &amp; Variants</div>
            <div style="font-weight: 600; color: var(--text-primary); font-family: var(--font-mono);">EXIF <?= $optCaps['exif_loaded'] ? 'Active' : 'N/A' ?></div>
            <div style="color: var(--text-muted); font-size: 11px; margin-top: 2px;">-480w, -960w &middot; Max 1920px</div>
        </div>
    </div>
</section>


<!-- =====================================================
     2. METRICS & FILTER TOOLBAR
====================================================== -->
<section class="table-container" style="padding: 0; margin-bottom: 24px;">
    <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <!-- Search -->
        <div class="search-input-wrapper" style="min-width: 260px;">
            <i class="fas fa-search toolbar-search-icon" aria-hidden="true"></i>
            <input
                type="search"
                id="mediaSearchInput"
                class="form-control"
                placeholder="Search assets by name or source..."
                aria-label="Search media assets"
                oninput="filterMediaGrid()"
            >
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs" role="group" aria-label="Filter media by type">
            <button
                type="button"
                class="filter-tab active"
                data-filter="all"
                onclick="setMediaFilter('all')"
            >
                All Assets (<span id="countMediaAll">0</span>)
            </button>

            <button
                type="button"
                class="filter-tab"
                data-filter="articles"
                onclick="setMediaFilter('articles')"
            >
                Articles (<span id="countMediaArticles">0</span>)
            </button>

            <button
                type="button"
                class="filter-tab"
                data-filter="projects"
                onclick="setMediaFilter('projects')"
            >
                Projects (<span id="countMediaProjects">0</span>)
            </button>
        </div>
    </div>
</section>


<!-- =====================================================
     3. MEDIA ASSETS GRID
====================================================== -->
<div id="mediaLoadingState" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
    <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 12px; color: var(--accent);"></i>
    <p>Loading media assets from database...</p>
</div>

<div id="mediaEmptyState" style="display: none; text-align: center; padding: 60px 20px; background: var(--bg-surface); border: 1px dashed var(--border-subtle); border-radius: var(--radius-md);">
    <i class="far fa-images" style="font-size: 40px; color: var(--text-muted); margin-bottom: 16px;"></i>
    <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px 0;">No Media Assets Found</h3>
    <p style="font-size: 13px; color: var(--text-muted); max-width: 440px; margin: 0 auto 20px auto;">
        Upload project architecture diagrams, system schematics, or article cover images to populate the media library.
    </p>
    <button type="button" class="btn btn-primary" onclick="openMediaUploadModal()">
        <i class="fas fa-cloud-arrow-up"></i>
        <span>Upload First Image</span>
    </button>
</div>

<div id="mediaGrid" class="media-grid" style="display: none;">
    <!-- Media cards populated dynamically by admin/js/media.js -->
</div>


<!-- =====================================================
     4. MEDIA PREVIEW & INSPECTOR MODAL
====================================================== -->
<div id="mediaPreviewModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="mediaPreviewTitle" style="display: none;">
    <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
            <h3 class="modal-title" id="mediaPreviewTitle">Asset Inspector</h3>
            <button type="button" class="modal-close" onclick="closeMediaPreviewModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <div style="background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden; display: flex; align-items: center; justify-content: center; min-height: 260px; max-height: 420px;">
                <img id="previewModalImage" src="" alt="Asset Preview" style="max-width: 100%; max-height: 420px; object-fit: contain;">
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div>
                    <label style="font-size: 11px; text-transform: uppercase; font-family: var(--font-mono); color: var(--text-muted);">Asset URL</label>
                    <div style="display: flex; gap: 8px; margin-top: 4px;">
                        <input type="text" id="previewModalUrl" class="form-control" readonly style="font-family: var(--font-mono); font-size: 12px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyPreviewUrl()">
                            <i class="far fa-copy"></i>
                            <span>Copy</span>
                        </button>
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: var(--text-muted); border-top: 1px solid var(--border-subtle); padding-top: 10px;">
                    <span>Linked Context: <strong id="previewModalContext" style="color: var(--text-primary);">—</strong></span>
                    <a id="previewModalOpenLink" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="font-size: 11px;">
                        <span>Open in New Tab</span>
                        <i class="fas fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeMediaPreviewModal()">Close</button>
        </div>
    </div>
</div>


<!-- =====================================================
     5. UPLOAD MEDIA MODAL
====================================================== -->
<div id="mediaUploadModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="mediaUploadTitle" style="display: none;">
    <div class="modal-dialog" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title" id="mediaUploadTitle">Upload Media Asset</h3>
            <button type="button" class="modal-close" onclick="closeMediaUploadModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="mediaUploadForm" onsubmit="handleMediaUpload(event)">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="form-group">
                    <label for="mediaFileInput">Select Image File <span style="color: var(--accent);">*</span></label>
                    <input
                        type="file"
                        id="mediaFileInput"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                        required
                        onchange="handleMediaFileSelect(event)"
                    >
                    <span class="form-help">Supported: JPG, PNG, WebP, GIF, SVG (up to 5MB).</span>
                </div>

                <div id="uploadPreviewWrapper" style="display: none; background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 8px; text-align: center;">
                    <img id="uploadPreviewImage" src="" alt="Upload Preview" style="max-width: 100%; max-height: 180px; object-fit: contain;">
                </div>

                <div id="uploadProgressMessage" style="display: none; font-size: 12px; color: var(--accent); font-family: var(--font-mono); text-align: center;">
                    <i class="fas fa-spinner fa-spin"></i> Uploading asset...
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeMediaUploadModal()">Cancel</button>
                <button type="submit" id="mediaUploadSubmitBtn" class="btn btn-primary">
                    <i class="fas fa-cloud-arrow-up"></i>
                    <span>Upload</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$pageScripts = ['js/media.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>
