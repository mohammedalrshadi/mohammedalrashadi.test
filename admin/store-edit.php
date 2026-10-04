<?php
// ============================================================
// STORE PRODUCT EDITOR — Server-Side Protected Admin Page
// Create and edit Store digital products.
// Backed by api/products/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'store';
$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit    = ($productId > 0);

$pageTitle = ($isEdit ? 'Edit Product' : 'New Product');

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
            <a href="store.php">Store</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);" id="breadcrumbCurrent"><?= $isEdit ? 'Edit Product' : 'New Product' ?></span>
        </div>
        <h1 class="page-title" id="editorTitle"><?= $isEdit ? 'Edit Product' : 'Create New Product' ?></h1>
        <p class="page-description">Configure digital product metadata, platform links, pricing, downloadable assets, and documentation.</p>
    </div>

    <div class="header-actions">
        <a href="store.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back to Store</span>
        </a>

        <?php if ($isEdit): ?>
        <a href="#" target="_blank" class="btn btn-secondary" id="viewPublicLink" style="display: none;" title="View Live Product">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Page</span>
        </a>
        <?php endif; ?>

        <button type="button" class="btn btn-primary" id="saveProductBtn" onclick="saveProductForm()">
            <i class="fas fa-save" aria-hidden="true"></i>
            <span>Save Product</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. PRODUCT COMPOSER FORM
====================================================== -->
<form id="productForm" class="admin-form-page" onsubmit="event.preventDefault(); saveProductForm();" style="display: flex; flex-direction: column; gap: 24px; padding-bottom: 60px;">

    <input type="hidden" id="product_id" value="<?= $productId ?>">

    <!-- SECTION 1: CORE METADATA & CLASSIFICATION -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-tag" style="color: var(--accent);"></i>
                <span>Core Classification &amp; Identity</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Headline title, URL identifier, digital category, and publication status.
            </p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Title -->
            <div class="form-group">
                <label for="title" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                    Product Title <span style="color: var(--accent);">*</span>
                </label>
                <input
                    type="text"
                    id="title"
                    class="form-control"
                    required
                    placeholder="e.g. Systems Engineering Notion Workspace"
                    maxlength="500"
                    oninput="handleTitleChange()"
                >
            </div>

            <!-- Slug & Auto-slug generator -->
            <div class="form-group">
                <div class="form-label-row">
                    <label for="slug" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary);">
                        URL Slug <span style="color: var(--accent);">*</span>
                    </label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="generateSlugFromTitle()">
                        <i class="fas fa-wand-magic-sparkles"></i> Auto-Generate
                    </button>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);">/store/</span>
                    <input
                        type="text"
                        id="slug"
                        class="form-control"
                        required
                        placeholder="systems-engineering-notion-workspace"
                        maxlength="500"
                        style="font-family: var(--font-mono); font-size: 13px;"
                    >
                </div>
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                    Unique clean URL identifier for public discovery (lowercase letters, numbers, and hyphens).
                </span>
            </div>

            <!-- Category, Type, Status, Sort in grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label for="category" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                        Category <span style="color: var(--accent);">*</span>
                    </label>
                    <select id="category" class="form-control" required>
                        <option value="Notion Templates">Notion Templates</option>
                        <option value="Website Templates">Website Templates</option>
                        <option value="Developer Resources">Developer Resources</option>
                        <option value="Study Resources">Study Resources</option>
                        <option value="Guides &amp; PDFs">Guides &amp; PDFs</option>
                        <option value="UI / Design Resources">UI / Design Resources</option>
                        <option value="Tools">Tools</option>
                        <option value="Other">Other</option>
                    </select>
                </div>


                <div class="form-group">
                    <label for="status" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                        Status <span style="color: var(--accent);">*</span>
                    </label>
                    <select id="status" class="form-control" required>
                        <option value="draft">Draft (Hidden from Public)</option>
                        <option value="published">Published (Visible in Store)</option>
                        <option value="archived">Archived (Delisted)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="sort_order" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                        Sort Order
                    </label>
                    <input type="number" id="sort_order" class="form-control" min="0" value="0" placeholder="0">
                </div>
            </div>

            <!-- Featured toggle -->
            <div style="margin-top: 6px;">
                <label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--text-primary);">
                    <input type="checkbox" id="featured" value="1">
                    <span>Mark as <strong>Featured Product</strong> (highlighted in public Store showcase)</span>
                </label>
            </div>

            <!-- Feature on Home Showcase Toggle -->
            <div class="form-group" style="margin-top: 12px; background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-layer-group" style="color: var(--accent); font-size: 16px;"></i>
                        <div>
                            <label for="featureOnShowcaseToggle" style="margin: 0; font-size: 13px; font-weight: 600; color: var(--text-primary); cursor: pointer;">
                                Feature on Home Showcase
                            </label>
                            <span class="form-help" style="margin: 1px 0 0 0; display: block; font-size: 11px; color: var(--text-muted);">
                                Curate this digital product into the featured hero showcase on the public homepage.
                            </span>
                        </div>
                    </div>
                    <label class="toggle-switch-label" for="featureOnShowcaseToggle" style="margin: 0;">
                        <input type="checkbox" id="featureOnShowcaseToggle" class="showcase-toggle" onchange="handleInlineShowcaseToggle('product')">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

        <!-- SECTION 2: PRODUCT ACCESS -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); margin-bottom: 24px;">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-key" style="color: var(--accent);"></i>
                <span>Product Access &amp; Delivery</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Configure how customers access this product (enable one or more).
            </p>
        </div>

        <!-- 2.1 LIVE DEMO -->
        <div style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <label style="font-weight: 600; font-size: 14px; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-desktop"></i> Live Demo Preview
                </label>
                <label class="toggle-switch-label" for="enableLiveDemoToggle" style="margin: 0;">
                    <input type="checkbox" id="enableLiveDemoToggle" class="showcase-toggle" onchange="handleLiveDemoToggle()">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div id="liveDemoFields" style="display: none; padding-top: 12px; border-top: 1px dashed var(--border-subtle);">
                <div class="form-group">
                    <label style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px; display: block;">
                        Demo Source
                    </label>
                    <div style="display: flex; gap: 16px;">
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="live_demo_source" value="uploaded" onchange="handleDemoSourceChange()"> Uploaded Static Website
                        </label>
                        <label style="font-size: 13px; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="live_demo_source" value="external" onchange="handleDemoSourceChange()"> External Website
                        </label>
                    </div>
                </div>

                <!-- Uploaded Demo Fields -->
                <div id="demoSourceUploaded" style="display: none; background: var(--bg-canvas); padding: 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                    <div id="currentDemoStatus" style="display: none; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i>
                            <span style="font-size: 13px; font-weight: 600;">Uploaded Demo Active</span>
                            <span id="currentDemoPath" style="font-size: 11px; color: var(--text-muted); font-family: monospace;"></span>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeUploadedDemo()">Remove Demo</button>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="font-size: 12.5px; font-weight: 600; margin-bottom: 6px; display: block;">Upload Static Website (ZIP, max 50MB, required: index.html)</label>
                        <div style="display: flex; gap: 12px;">
                            <input type="file" id="demoZipInput" accept=".zip,application/zip" class="form-control" style="flex: 1;">
                            <button type="button" class="btn btn-secondary" onclick="uploadDemoZip()">
                                <i class="fas fa-upload"></i> Upload &amp; Deploy
                            </button>
                        </div>
                    </div>
                    <input type="hidden" id="live_demo_path">
                </div>

                <!-- External Demo Fields -->
                <div id="demoSourceExternal" style="display: none;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="live_demo_url" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                            External Demo URL
                        </label>
                        <input type="url" id="live_demo_url" class="form-control" placeholder="https://example.com/demo" maxlength="2000" style="font-family: var(--font-mono); font-size: 13px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- 2.2 DIRECT DOWNLOAD -->
        <div style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <label style="font-weight: 600; font-size: 14px; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-download"></i> Direct Download
                </label>
                <label class="toggle-switch-label" for="enableDirectDownloadToggle" style="margin: 0;">
                    <input type="checkbox" id="enableDirectDownloadToggle" class="showcase-toggle" onchange="handleDirectDownloadToggle()">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div id="directDownloadFields" style="display: none; padding-top: 12px; border-top: 1px dashed var(--border-subtle);">
                <input type="hidden" id="download_path">
                <!-- Current File Status Box -->
                <div id="currentDownloadStatus" style="display: none; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--bg-surface-elevated, rgba(255,255,255,0.03)); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-circle-check" style="color: var(--success); font-size: 18px;"></i>
                        <div>
                            <div id="currentDownloadFilename" style="font-size: 13px; font-weight: 600; color: var(--text-primary); font-family: var(--font-mono);">file.pdf</div>
                            <div style="font-size: 11px; color: var(--text-muted);">Protected download file verified on server</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeDownloadFile()" style="font-size: 11.5px;">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>

                <!-- Upload Drop Zone / Input -->
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                        Upload New Download File (PDF or ZIP, max 20MB)
                    </label>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="file" id="downloadFileInput" accept=".pdf,.zip,application/pdf,application/zip" class="form-control" style="flex: 1;">
                        <button type="button" class="btn btn-secondary" id="uploadDownloadBtn" onclick="uploadDownloadFile()">
                            <i class="fas fa-cloud-arrow-up"></i> Upload Asset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2.3 EXTERNAL ACCESS -->
        <div style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <label style="font-weight: 600; font-size: 14px; margin: 0; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-external-link-alt"></i> External Access
                </label>
                <label class="toggle-switch-label" for="enableExternalAccessToggle" style="margin: 0;">
                    <input type="checkbox" id="enableExternalAccessToggle" class="showcase-toggle" onchange="handleExternalAccessToggle()">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div id="externalAccessFields" style="display: none; padding-top: 12px; border-top: 1px dashed var(--border-subtle);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label for="platform" style="font-size: 12.5px; font-weight: 600; margin-bottom: 6px; display: block;">Platform Name</label>
                        <input type="text" id="platform" class="form-control" placeholder="e.g. Gumroad" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="price_display" style="font-size: 12.5px; font-weight: 600; margin-bottom: 6px; display: block;">Price Display</label>
                        <input type="text" id="price_display" class="form-control" placeholder="e.g. $19" maxlength="50">
                    </div>
                    <div class="form-group">
                        <label for="currency" style="font-size: 12.5px; font-weight: 600; margin-bottom: 6px; display: block;">Currency</label>
                        <input type="text" id="currency" class="form-control" value="USD" placeholder="USD" maxlength="10" style="text-transform: uppercase;">
                    </div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label for="external_url" style="font-size: 12.5px; font-weight: 600; margin-bottom: 6px; display: block;">Destination URL</label>
                    <input type="url" id="external_url" class="form-control" placeholder="https://gumroad.com/l/product" maxlength="2000" style="font-family: var(--font-mono); font-size: 13px;">
                </div>
            </div>
        </div>
    </div>



    <!-- SECTION 3: PRODUCT RESOURCES -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); margin-bottom: 24px;">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-layer-group" style="color: var(--accent);"></i>
                <span>Product Resources</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Manage additional downloadable assets, documentation links, and sub-components.
            </p>
        </div>
            <div id="product-resources-container">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openResourceModal(-1)">
                <i class="fas fa-plus"></i> Add Resource
            </button>
        </div>
        <div id="product-resources-list" style="display: flex; flex-direction: column; gap: 12px;">
            <!-- Rendered by JS -->
        </div>
    </div>
    </div>

    <!-- SECTION 4: THUMBNAIL & MEDIA -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-image" style="color: var(--accent);"></i>
                <span>Product Artwork &amp; Thumbnail</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Card cover photo and preview artwork for the Store directory (PNG, JPG, or WebP).
            </p>
        </div>

        <input type="hidden" id="thumbnail">

        <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
            <!-- Preview Box -->
            <div id="thumbnailPreviewContainer" style="width: 180px; height: 120px; border-radius: var(--radius-sm); border: 1px dashed var(--border-subtle); display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.2); overflow: hidden; position: relative;">
                <img id="thumbnailPreviewImg" src="" alt="Thumbnail preview" style="display: none; width: 100%; height: 100%; object-fit: cover;">
                <span id="thumbnailPlaceholder" style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 8px;">
                    <i class="fas fa-image" style="font-size: 24px; margin-bottom: 6px; display: block;"></i>
                    No Image
                </span>
            </div>

            <!-- Upload Controls -->
            <div style="flex: 1; min-width: 240px; display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="file" id="thumbnailFileInput" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control" style="flex: 1;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="uploadThumbnailFile()">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" id="removeThumbnailBtn" onclick="removeThumbnail()" style="display: none;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <span style="font-size: 11px; color: var(--text-muted);">
                    Recommended resolution: 1200×800 or 16:9 ratio. Maximum file size: 20MB.
                </span>
            </div>
        </div>
    </div>


    <!-- SECTION 4.5: PRODUCT GALLERY -->
    <div id="productGallerySection" class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); display: none;">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-images" style="color: var(--accent);"></i>
                        <span>Product Gallery</span>
                    </h2>
                    <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                        Upload additional images to visually showcase this product.
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span id="galleryCountLabel" style="font-size: 12px; color: var(--text-muted); font-weight: 600;">0 / 12 images</span>
                    <input type="file" id="galleryFileInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple class="form-control" style="width: 200px;">
                    <button type="button" class="btn btn-secondary btn-sm" id="uploadGalleryBtn" onclick="uploadGalleryImages()">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </div>
            </div>
        </div>

        <div id="galleryEmptyState" style="color: var(--text-muted); text-align: center; padding: 30px; border: 1px dashed var(--border-subtle); border-radius: var(--radius-sm);">
            <i class="fas fa-images" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
            No gallery images yet.<br>
            <span style="font-size: 12px;">Upload images to showcase this product.</span>
        </div>

        <div id="galleryGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; display: none;">
            <!-- Gallery items injected here -->
        </div>
    </div>

    <div id="productGalleryDisabled" class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); text-align: center; display: none;">
        <i class="fas fa-images" style="font-size: 24px; margin-bottom: 10px; display: block; color: var(--text-muted); opacity: 0.5;"></i>
        <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary);">Product Gallery</h2>
        <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
            Save the product first to enable Gallery uploads.
        </p>
    </div>

    <!-- SECTION 5: EDITORIAL PROSE & DESCRIPTION -->
    <div class="card" style="padding: 24px; background: var(--bg-surface); border: 1px solid var(--border-subtle); border-radius: var(--radius-md);">
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="font-size: 16px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-align-left" style="color: var(--accent);"></i>
                <span>Editorial Presentation &amp; Content</span>
            </h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                Overview pitch, feature breakdown, specifications, and what's included.
            </p>
        </div>

        <!-- Short Description -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="short_description" style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Short Summary (Card Preview)
            </label>
            <textarea
                id="short_description"
                class="form-control"
                rows="3"
                placeholder="Concise overview displayed on store catalog cards and search previews..."
                maxlength="1000"
            ></textarea>
        </div>

        <!-- Full Rich-Text Description using existing WYSIWYG toolbar styling -->
        <div class="form-group">
            <label style="font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; display: block;">
                Full Description &amp; What's Included
            </label>

            <div class="wysiwyg-wrapper" style="border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); overflow: hidden;">
                <!-- Toolbar -->
                <div class="editor-toolbar" role="toolbar" aria-label="Editor formatting" style="display: flex; align-items: center; gap: 4px; padding: 8px 12px; background: var(--bg-surface-elevated, rgba(255,255,255,0.02)); border-bottom: 1px solid var(--border-subtle); flex-wrap: wrap;">
                    <button type="button" class="toolbar-btn" title="Bold" onclick="document.execCommand('bold', false, null)">
                        <i class="fas fa-bold"></i>
                    </button>
                    <button type="button" class="toolbar-btn" title="Italic" onclick="document.execCommand('italic', false, null)">
                        <i class="fas fa-italic"></i>
                    </button>
                    <button type="button" class="toolbar-btn" title="Underline" onclick="document.execCommand('underline', false, null)">
                        <i class="fas fa-underline"></i>
                    </button>

                    <div class="toolbar-separator" style="width: 1px; height: 18px; background: var(--border-subtle); margin: 0 4px;"></div>

                    <button type="button" class="toolbar-btn" title="Heading 2" onclick="document.execCommand('formatBlock', false, '<h2>')">
                        <strong>H2</strong>
                    </button>
                    <button type="button" class="toolbar-btn" title="Heading 3" onclick="document.execCommand('formatBlock', false, '<h3>')">
                        <strong>H3</strong>
                    </button>

                    <div class="toolbar-separator" style="width: 1px; height: 18px; background: var(--border-subtle); margin: 0 4px;"></div>

                    <button type="button" class="toolbar-btn" title="Bullet List" onclick="document.execCommand('insertUnorderedList', false, null)">
                        <i class="fas fa-list-ul"></i>
                    </button>
                    <button type="button" class="toolbar-btn" title="Numbered List" onclick="document.execCommand('insertOrderedList', false, null)">
                        <i class="fas fa-list-ol"></i>
                    </button>
                    <button type="button" class="toolbar-btn" title="Quote" onclick="document.execCommand('formatBlock', false, '<blockquote>')">
                        <i class="fas fa-quote-left"></i>
                    </button>
                </div>

                <!-- Contenteditable Div -->
                <div
                    id="contentEditor"
                    class="editor-content"
                    contenteditable="true"
                    role="textbox"
                    aria-multiline="true"
                    data-placeholder="Describe the product, features, specifications, and what is included..."
                    style="min-height: 240px; padding: 16px; outline: none; line-height: 1.6; font-size: 14px; color: var(--text-primary);"
                ></div>

                <textarea id="description" style="display: none;"></textarea>
            </div>
        </div>
    </div>

</form>

    <!-- RESOURCE MODAL -->
    <div id="resourceModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: var(--bg-surface); width: 100%; max-width: 500px; border-radius: var(--radius-md); box-shadow: 0 10px 25px rgba(0,0,0,0.2); padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 12px;">
                <h3 id="resourceModalTitle" style="font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0;">Add Resource</h3>
                <button type="button" onclick="closeResourceModal()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 18px;">&times;</button>
            </div>
            <form id="resourceForm" onsubmit="saveResource(event)">
                <input type="hidden" id="resource_id" value="0">
                <input type="hidden" id="resource_product_id" value="">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="resource_title" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">Title</label>
                    <input type="text" id="resource_title" class="form-control" required style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label for="resource_description" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">Description</label>
                    <input type="text" id="resource_description" class="form-control" style="width: 100%;">
                </div>

                <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label for="resource_sort_order" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">Sort Order</label>
                        <input type="number" id="resource_sort_order" class="form-control" value="0" style="width: 100%;">
                    </div>
                    <div class="form-group" style="flex: 1; margin: 0;">
                        <label for="resource_status" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">Status</label>
                        <select id="resource_status" class="form-control" style="width: 100%;">
                            <option value="public">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label for="resource_file" style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">File <span id="resource_file_required_star" style="color: var(--danger);">*</span></label>
                    <input type="file" id="resource_file" class="form-control" style="width: 100%;">

                    <div id="resource_current_file" style="display: none; margin-top: 8px; font-size: 13px; color: var(--text-secondary); background: var(--bg-canvas); padding: 8px; border-radius: 4px; border: 1px dashed var(--border-subtle);">
                        Current file: <strong id="resource_current_file_name"></strong> (<span id="resource_current_file_size"></span>)
                        <br><span style="font-size: 12px; color: var(--text-muted);">Leave empty to keep current file.</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-subtle); padding-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="closeResourceModal()">Cancel</button>
                    <button type="submit" id="resourceSaveBtn" class="btn btn-primary">Save Resource</button>
                </div>
            </form>
        </div>
    </div>

<?php
$pageScripts = ['js/store-edit.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

