<?php
// ============================================================
// PROJECT / PROJECT EDITOR — Server-Side Protected Admin Page
// Full-form editor for documenting engineering systems, benchmarks,
// architecture case studies, and gallery diagrams.
// Backed by api/posts/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'projects';
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit    = ($projectId > 0);

$pageTitle = ($isEdit ? 'Edit Project' : 'New Project');

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
            <a href="projects.php">Projects</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);" id="breadcrumbCurrent"><?= $isEdit ? 'Edit Project #' . $projectId : 'New Project' ?></span>
        </div>
        <h1 class="page-title" id="editorTitle"><?= $isEdit ? 'Edit Engineering Project' : 'New Engineering Project' ?></h1>
        <p class="page-description">Document architecture case studies, benchmark results, and tech stack details.</p>
    </div>

    <div class="header-actions">
        <a href="projects.php" class="btn btn-secondary" id="backToProjectsBtn">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back to Projects</span>
        </a>

        <?php if ($isEdit): ?>
        <a href="/projects.php?id=<?= urlencode((string)$projectId) ?>" target="_blank" class="btn btn-secondary" id="viewPublicLink" style="display: none;" title="View Live Project">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Page</span>
        </a>
        <?php endif; ?>

        <button type="button" class="btn btn-secondary" id="headerSaveDraftBtn" onclick="submitAchieveForm('draft')">
            <i class="fas fa-save" aria-hidden="true"></i>
            <span>Save Draft</span>
        </button>

        <button type="button" class="btn btn-primary" id="headerPublishBtn" onclick="handlePublishAchievement()">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
            <span>Publish Project</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. PROJECT COMPOSER (FORM WORKSPACE)
====================================================== -->
<section class="editor-card" id="projects" aria-label="Project Composer">

    <div class="admin-card-header">
        <div>
            <h2 class="card-title" id="achieveFormTitle"><?= $isEdit ? 'Edit Engineering Project' : 'New Engineering Project' ?></h2>
            <p class="card-subtitle">Document architecture, benchmark results, and tech stack details.</p>
        </div>
        <span id="achieveFormStatus" class="status-badge" style="display: none;"></span>
    </div>

    <form id="achieveForm" novalidate>

        <input type="hidden" id="achieve_update_id" value="<?= $isEdit ? $projectId : '' ?>">
        <input type="hidden" id="achieve_type" value="project">
        <input type="hidden" id="achieve_status" value="draft">

        <!-- SECTION 1: ESSENTIAL DETAILS -->
        <div class="editor-section">
            <div class="editor-section-header">
                <h3 class="editor-section-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Project Overview</span>
                </h3>
                <p class="editor-section-desc">Project name, engineering focus area, and cover architecture diagram.</p>
            </div>

            <!-- Title -->
            <div class="form-group">
                <label for="achieve_title">Project Title <span style="color: var(--accent);">*</span></label>
                <input
                    type="text"
                    id="achieve_title"
                    class="form-control"
                    required
                    placeholder="e.g., Distributed Key-Value Store"
                >
            </div>

            <!-- Category + Date Grid -->
            <div class="form-grid">
                <div class="form-group">
                    <div class="form-label-row">
                        <label for="achieve_category">Category <span style="color: var(--accent);">*</span></label>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openAchieveCategoryModal()">
                            Manage
                        </button>
                    </div>
                    <input
                        type="text"
                        id="achieve_category"
                        list="achieveCategoriesList"
                        class="form-control"
                        required
                        placeholder="e.g., Distributed Systems, Database Internals"
                    >
                    <datalist id="achieveCategoriesList"></datalist>
                    <span class="form-help">Select an existing focus area or type a new one.</span>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label for="achieve_date">Date Achieved / Shipped <span style="color: var(--accent);">*</span></label>
                    </div>
                    <input
                        type="date"
                        id="achieve_date"
                        class="form-control"
                        required
                    >
                    <span class="form-help">Release or benchmark milestone date.</span>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="form-group" style="margin-top: 14px;">
                <label for="achieve_image">Featured Architecture Image</label>
                <input
                    type="file"
                    id="achieve_image"
                    class="form-control"
                    accept="image/*"
                >
                <span class="form-help">Primary diagram or header screenshot (JPG, PNG, WebP).</span>
                <div id="achieveImagePreviewContainer" style="display: none; margin-top: 10px;">
                    <img id="achieveImagePreviewImg" src="" alt="Architecture Preview" style="max-height: 180px; border-radius: 6px; border: 1px solid var(--border-subtle);">
                </div>
            </div>

            <!-- Feature on Home Showcase Toggle -->
            <div class="form-group" style="margin-top: 14px; background: var(--bg-canvas); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); padding: 12px 14px;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-layer-group" style="color: var(--accent); font-size: 16px;"></i>
                        <div>
                            <label for="featureOnShowcaseToggle" style="margin: 0; font-size: 13px; font-weight: 600; color: var(--text-primary); cursor: pointer;">
                                Feature on Home Showcase
                            </label>
                            <span class="form-help" style="margin: 1px 0 0 0; display: block; font-size: 11px; color: var(--text-muted);">
                                Curate this project into the featured hero showcase on the public homepage.
                            </span>
                        </div>
                    </div>
                    <label class="toggle-switch-label" for="featureOnShowcaseToggle" style="margin: 0;">
                        <input type="checkbox" id="featureOnShowcaseToggle" class="showcase-toggle" onchange="handleInlineShowcaseToggle('project')">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>


        <!-- SECTION 2: SPECIFICATION & CONTENT -->
        <div class="editor-section">
            <div class="editor-section-header">
                <h3 class="editor-section-title">
                    <i class="fas fa-file-lines"></i>
                    <span>Technical Case Study</span>
                </h3>
                <p class="editor-section-desc">Architecture walkthrough, problem statement, and engineering decisions.</p>
            </div>

            <div class="form-group">
                <label for="achieve_excerpt">Brief Architecture Summary (Excerpt)</label>
                <textarea
                    id="achieve_excerpt"
                    class="form-control"
                    rows="2"
                    placeholder="Short 2-3 sentence overview shown in project cards and bento grids..."
                ></textarea>
            </div>

            <div class="form-group" style="margin-top: 14px;">
                <label>Technical Details &amp; Specifications</label>
                <div class="wysiwyg-wrapper">
                    <!-- Toolbar -->
                    <div class="editor-toolbar" role="toolbar" aria-label="Editor formatting">
                        <button type="button" class="toolbar-btn" title="Bold" onclick="document.execCommand('bold', false, null)">
                            <i class="fas fa-bold"></i>
                        </button>
                        <button type="button" class="toolbar-btn" title="Italic" onclick="document.execCommand('italic', false, null)">
                            <i class="fas fa-italic"></i>
                        </button>
                        <button type="button" class="toolbar-btn" title="Underline" onclick="document.execCommand('underline', false, null)">
                            <i class="fas fa-underline"></i>
                        </button>

                        <div class="toolbar-separator"></div>

                        <button type="button" class="toolbar-btn" title="Heading 2" onclick="document.execCommand('formatBlock', false, '<h2>')">
                            <strong>H2</strong>
                        </button>
                        <button type="button" class="toolbar-btn" title="Heading 3" onclick="document.execCommand('formatBlock', false, '<h3>')">
                            <strong>H3</strong>
                        </button>

                        <div class="toolbar-separator"></div>

                        <button type="button" class="toolbar-btn" title="Bullet List" onclick="document.execCommand('insertUnorderedList', false, null)">
                            <i class="fas fa-list-ul"></i>
                        </button>
                        <button type="button" class="toolbar-btn" title="Numbered List" onclick="document.execCommand('insertOrderedList', false, null)">
                            <i class="fas fa-list-ol"></i>
                        </button>
                        <button type="button" class="toolbar-btn" title="Quote Block" onclick="document.execCommand('formatBlock', false, '<blockquote>')">
                            <i class="fas fa-quote-left"></i>
                        </button>
                    </div>

                    <!-- Contenteditable Area -->
                    <div
                        id="achieveContentEditor"
                        class="editor-content"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                        data-placeholder="Describe the system architecture, design tradeoffs, implementation, and benchmark results..."
                    ></div>
                </div>

                <textarea id="achieve_content" class="hidden" style="display: none;"></textarea>
            </div>
        </div>


        <!-- SECTION 3: PROJECT GALLERY & DIAGRAMS (REQ-006) -->
        <div class="editor-section" id="achieveGallerySection">
            <div class="editor-section-header" style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 class="editor-section-title">
                        <i class="fas fa-images"></i>
                        <span>Project Gallery &amp; Benchmarks</span>
                    </h3>
                    <p class="editor-section-desc">Upload supporting diagrams, benchmark graphs, and UI screenshots.</p>
                </div>

                <div>
                    <input type="file" id="achieveGalleryInput" class="hidden" style="display: none;" accept="image/*" multiple>
                    <button type="button" id="achieveGalleryAddBtn" class="btn btn-secondary btn-sm" onclick="document.getElementById('achieveGalleryInput').click();">
                        <i class="fas fa-plus"></i> Add Images
                    </button>
                </div>
            </div>

            <!-- Thumbnails Grid -->
            <div id="achieveGalleryGrid" class="gallery-grid">
                <!-- Dynamically populated by projects-edit.js -->
            </div>
        </div>


        <!-- ACTIONS BAR -->
        <div class="form-actions-bar">
            <a href="projects.php" id="achieveCancelBtn" class="btn btn-secondary" onclick="handleAchievementCancel(event)">
                <i class="fas fa-times"></i>
                <span>Cancel</span>
            </a>

            <button type="submit" id="achieveSubmitBtn" class="btn btn-secondary">
                <i class="fas fa-save"></i>
                <span id="achieveSaveDraftBtnText">Save Draft</span>
            </button>

            <button type="button" id="achievePublishBtn" class="btn btn-primary" onclick="handlePublishAchievement()">
                <i class="fas fa-paper-plane"></i>
                <span id="achievePublishBtnText">Publish Project</span>
            </button>
        </div>

    </form>

</section>


<!-- =====================================================
     3. PROJECT CATEGORY MODAL (REQ-002)
====================================================== -->
<div id="achieveCategoryManageModal" class="modal" role="dialog" aria-labelledby="achieveCategoryModalTitle" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="achieveCategoryModalTitle">Project Categories</h3>
            <button type="button" class="modal-close" onclick="closeAchieveCategoryModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <!-- Add Category -->
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <input
                    type="text"
                    id="newAchieveCategoryInput"
                    class="form-control"
                    placeholder="New category name..."
                    maxlength="60"
                >
                <button type="button" id="addAchieveCategoryBtn" class="btn btn-primary" onclick="handleAddAchieveCategory()">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <!-- Categories List Table -->
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Projects</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="achieveCategoryListTbody"></tbody>
                </table>
            </div>

            <!-- Safe Delete / Reassign Subpanel -->
            <div id="achieveCategoryDeletePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px; color: var(--danger);">
                    Delete Category: <span id="achieveDeleteTargetCategoryName"></span>
                </h4>
                <p id="achieveDeleteUsageDescription" style="font-size: 12px; color: var(--text-muted); margin: 0 0 12px 0;"></p>

                <form id="achieveCategoryDeleteForm" onsubmit="handleExecuteAchieveCategoryDelete(event)">
                    <input type="hidden" id="achieveDeleteSourceCategoryId">
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="achieveDeleteActionType" value="reassign" id="achieveDeleteActionReassign" checked onchange="toggleAchieveDeleteActionInputs()">
                            <span>Reassign projects to another category:</span>
                        </label>
                        <div id="achieveReassignSelectWrapper">
                            <select id="achieveReassignTargetCategorySelect" class="form-control"></select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="achieveDeleteActionType" value="unlink" id="achieveDeleteActionUnlink" onchange="toggleAchieveDeleteActionInputs()">
                            <span>Unlink projects (leave uncategorized)</span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelAchieveCategoryActionPanel()">Cancel</button>
                        <button type="submit" id="achieveConfirmDeleteCategoryBtn" class="btn btn-danger btn-sm">Confirm Delete</button>
                    </div>
                </form>
            </div>

            <!-- Inline Rename Subpanel -->
            <div id="achieveCategoryRenamePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px;">
                    Rename Category: <span id="achieveRenameOldCategoryName"></span>
                </h4>
                <form id="achieveCategoryRenameForm" onsubmit="handleExecuteAchieveCategoryRename(event)">
                    <input type="hidden" id="achieveRenameCategoryId">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="achieveRenameCategoryNewNameInput" class="form-control" placeholder="New name..." required maxlength="60">
                        <button type="submit" id="achieveConfirmRenameCategoryBtn" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelAchieveCategoryActionPanel()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAchieveCategoryModal()">Close</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/shared-editor.js', 'js/projects-edit.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

