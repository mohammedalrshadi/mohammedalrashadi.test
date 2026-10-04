<?php
// ============================================================
// ARTICLE EDITOR — Server-Side Protected Admin Page
// Full-form editor for drafting and publishing articles.
// Backed by api/posts/*.php endpoints.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'articles';
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($postId > 0);

$pageTitle = ($isEdit ? 'Edit Article' : 'New Article');

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
            <a href="articles.php">Articles</a>
            <span class="breadcrumb-sep">/</span>
            <span style="color: var(--accent);" id="breadcrumbCurrent"><?= $isEdit ? 'Edit Article #' . $postId : 'New Article' ?></span>
        </div>
        <h1 class="page-title" id="editorTitle"><?= $isEdit ? 'Edit Article' : 'Write New Article' ?></h1>
        <p class="page-description">Draft, format, and publish engineering essays, benchmarks, and research notes.</p>
    </div>

    <div class="header-actions">
        <a href="articles.php" class="btn btn-secondary" id="backToArticlesBtn">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back to Articles</span>
        </a>

        <?php if ($isEdit): ?>
        <a href="/post.php?id=<?= urlencode((string)$postId) ?>" target="_blank" class="btn btn-secondary" id="viewPublicLink" style="display: none;" title="View Live Article">
            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
            <span>Public Page</span>
        </a>
        <?php endif; ?>

        <button type="button" class="btn btn-secondary" id="headerPreviewBtn" onclick="handlePreviewArticle()">
            <i class="fas fa-eye" aria-hidden="true"></i>
            <span>Preview</span>
        </button>

        <button type="button" class="btn btn-secondary" id="headerSaveDraftBtn" onclick="submitArticleForm('draft')">
            <i class="fas fa-save" aria-hidden="true"></i>
            <span>Save Draft</span>
        </button>

        <button type="button" class="btn btn-primary" id="headerPublishBtn" onclick="handlePublishArticle()">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
            <span>Publish Article</span>
        </button>
    </div>
</header>


<!-- =====================================================
     2. EDITORIAL WRITING WORKSPACE (FORM)
====================================================== -->
<section class="editor-card" id="publish" aria-label="Article Composer">

    <div class="admin-card-header">
        <div>
            <h2 class="card-title" id="formTitle"><?= $isEdit ? 'Edit Article' : 'Write New Article' ?></h2>
            <p class="card-subtitle">Draft or refine your technical writing for publication.</p>
        </div>
        <span id="articleFormStatus" class="status-badge" style="display: none;"></span>
    </div>

    <form id="postForm" novalidate>

        <input type="hidden" id="update_id" value="<?= $isEdit ? $postId : '' ?>">
        <input type="hidden" id="post_type" value="blog">
        <input type="hidden" id="post_status" value="draft">

        <!-- SECTION 1: ESSENTIAL DETAILS -->
        <div class="editor-section">
            <div class="editor-section-header">
                <h3 class="editor-section-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Article Details</span>
                </h3>
                <p class="editor-section-desc">Article headline, taxonomy classification, and featured cover artwork.</p>
            </div>

            <!-- Title -->
            <div class="form-group">
                <label for="title">Article Title <span style="color: var(--accent);">*</span></label>
                <input
                    type="text"
                    id="title"
                    class="form-control"
                    required
                    placeholder="e.g., Understanding Concurrency Control in Distributed Databases"
                >
            </div>

            <!-- Category + Date Grid -->
            <div class="form-grid">
                <div class="form-group">
                    <div class="form-label-row">
                        <label for="category">Category <span style="color: var(--accent);">*</span></label>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openCategoryModal()">
                            Manage
                        </button>
                    </div>
                    <input
                        type="text"
                        id="category"
                        list="categoriesList"
                        class="form-control"
                        required
                        placeholder="Select or enter category..."
                    >
                    <datalist id="categoriesList"></datalist>
                    <span class="form-help">Choose from suggestions or type a new category.</span>
                </div>

                <div class="form-group">
                    <div class="form-label-row">
                        <label for="publish_date">Publication Date <span style="color: var(--accent);">*</span></label>
                    </div>
                    <input
                        type="date"
                        id="publish_date"
                        class="form-control"
                        required
                    >
                    <span class="form-help">Scheduled or historical publication timestamp.</span>
                </div>
            </div>

            <!-- Featured Cover Image -->
            <div class="form-group" style="margin-top: 14px;">
                <label for="image">Featured Cover Image</label>
                <input
                    type="file"
                    id="image"
                    class="form-control"
                    accept="image/*"
                >
                <span class="form-help">Supported formats: JPG, PNG, WebP (maximum 5MB).</span>
                <div id="imagePreviewContainer" style="display: none; margin-top: 10px;">
                    <img id="imagePreviewImg" src="" alt="Cover Preview" style="max-height: 180px; border-radius: 6px; border: 1px solid var(--border-subtle);">
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
                                Curate this article into the featured hero showcase on the public homepage.
                            </span>
                        </div>
                    </div>
                    <label class="toggle-switch-label" for="featureOnShowcaseToggle" style="margin: 0;">
                        <input type="checkbox" id="featureOnShowcaseToggle" class="showcase-toggle" onchange="handleInlineShowcaseToggle('writing')">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>


        <!-- SECTION 2: EDITORIAL QUOTES (OPTIONAL) -->
        <div class="editor-section">
            <div class="editor-section-header">
                <h3 class="editor-section-title">
                    <i class="fas fa-quote-right"></i>
                    <span>Editorial Quote &amp; Excerpt</span>
                </h3>
                <p class="editor-section-desc">Key takeaways highlighted on cards and social preview snippets.</p>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="quote_ar">Article Quote (Arabic / Primary)</label>
                    <textarea
                        id="quote_ar"
                        class="form-control"
                        rows="2"
                        placeholder="اكتب اقتباساً مميزاً للمقال..."
                    ></textarea>
                </div>

                <div class="form-group">
                    <label for="quote_en">Article Quote (English / Secondary)</label>
                    <textarea
                        id="quote_en"
                        class="form-control"
                        rows="2"
                        dir="ltr"
                        placeholder="Key takeaway or quotation in English..."
                    ></textarea>
                </div>
            </div>

        </div>


        <!-- SECTION 3: ARTICLE BODY (WYSIWYG) -->
        <div class="editor-section">
            <div class="editor-section-header">
                <h3 class="editor-section-title">
                    <i class="fas fa-feather-alt"></i>
                    <span>Article Content</span>
                </h3>
                <p class="editor-section-desc">Formatted prose, code blocks, and diagrams.</p>
            </div>

            <div class="form-group">
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

                        <div class="toolbar-separator"></div>

                        <button type="button" class="toolbar-btn" title="Highlight selection" onclick="toggleHighlight()">
                            <i class="fas fa-highlighter"></i>
                        </button>
                        <button type="button" id="toolbarMakeQuoteBtn" class="toolbar-btn" title="Convert to Quote" style="display: none;">
                            <i class="fas fa-quote-right"></i>
                        </button>
                    </div>

                    <!-- Contenteditable Area -->
                    <div
                        id="contentEditor"
                        class="editor-content"
                        contenteditable="true"
                        role="textbox"
                        aria-multiline="true"
                        data-placeholder="Begin writing your technical essay or benchmark analysis here..."
                    ></div>

                    <!-- Floating Quote Tooltip -->
                    <button type="button" id="floatingMakeQuoteBtn" class="btn btn-primary btn-sm" style="display: none; position: absolute; z-index: 50;">
                        <i class="fas fa-quote-right"></i> Make Quote
                    </button>
                </div>

                <!-- Hidden field bound for form submission -->
                <textarea id="content" class="hidden" style="display: none;"></textarea>
            </div>
        </div>


        <!-- SECTION 4: SEO & METADATA -->
        <div class="editor-section" id="seoAnalysisSection">
            <div class="editor-section-header" style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h3 class="editor-section-title" id="seoAnalysisTitle">
                        <i class="fas fa-chart-line"></i>
                        <span>SEO &amp; Discoverability Analysis</span>
                    </h3>
                    <p class="editor-section-desc" id="seoAnalysisDesc">Audit metadata and keywords before publishing.</p>
                </div>
                <button type="button" id="seoLangToggle" class="btn btn-secondary btn-sm">English</button>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="seoFocusKeyword" id="seoKeywordLabel">Target Focus Keyword</label>
                    <input
                        type="text"
                        id="seoFocusKeyword"
                        class="form-control"
                        maxlength="100"
                        placeholder="e.g. distributed systems"
                    >
                </div>

                <div style="display: flex; flex-direction: column; justify-content: center; gap: 6px;">
                    <button type="button" id="seoAnalyzeBtn" class="btn btn-secondary" style="align-self: flex-start;" disabled>
                        <i class="fas fa-magnifying-glass-chart"></i> Run SEO Audit
                    </button>
                    <span class="form-help" id="seoNotSavedHint">
                        Save article draft first to run SEO audit.
                    </span>
                </div>
            </div>

            <div class="seo-results-container" id="seoResultsContainer" style="margin-top: 14px;"></div>
        </div>


        <!-- ACTIONS BAR -->
        <div class="form-actions-bar">
            <a href="articles.php" id="cancelBtn" class="btn btn-secondary" onclick="handleArticleCancel(event)">
                <i class="fas fa-times"></i>
                <span>Cancel</span>
            </a>

            <button type="button" id="previewBtn" class="btn btn-secondary" onclick="handlePreviewArticle()">
                <i class="fas fa-eye"></i>
                <span>Preview</span>
            </button>

            <button type="submit" id="submitBtn" class="btn btn-secondary">
                <i class="fas fa-save"></i>
                <span id="saveDraftBtnText">Save Draft</span>
            </button>

            <button type="button" id="publishBtn" class="btn btn-primary" onclick="handlePublishArticle()">
                <i class="fas fa-paper-plane"></i>
                <span id="publishBtnText">Publish Article</span>
            </button>
        </div>

    </form>

</section>


<!-- =====================================================
     3. CATEGORY MANAGEMENT MODAL (REQ-002)
====================================================== -->
<div id="categoryManageModal" class="modal" role="dialog" aria-labelledby="categoryModalTitle" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="categoryModalTitle">Article Categories</h3>
            <button type="button" class="modal-close" onclick="closeCategoryModal()" aria-label="Close modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <!-- Add Category -->
            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                <input
                    type="text"
                    id="newCategoryInput"
                    class="form-control"
                    placeholder="New category name..."
                    maxlength="60"
                >
                <button type="button" id="addCategoryBtn" class="btn btn-primary" onclick="handleAddCategory()">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>

            <!-- Categories List Table -->
            <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Articles</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoryListTbody"></tbody>
                </table>
            </div>

            <!-- Safe Delete / Reassign Subpanel -->
            <div id="categoryDeletePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px; color: var(--danger);">
                    Delete Category: <span id="deleteTargetCategoryName"></span>
                </h4>
                <p id="deleteUsageDescription" style="font-size: 12px; color: var(--text-muted); margin: 0 0 12px 0;"></p>

                <form id="categoryDeleteForm" onsubmit="handleExecuteCategoryDelete(event)">
                    <input type="hidden" id="deleteSourceCategoryId">
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="deleteActionType" value="reassign" id="deleteActionReassign" checked onchange="toggleDeleteActionInputs()">
                            <span>Reassign articles to another category:</span>
                        </label>
                        <div id="reassignSelectWrapper">
                            <select id="reassignTargetCategorySelect" class="form-control"></select>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="radio" name="deleteActionType" value="unlink" id="deleteActionUnlink" onchange="toggleDeleteActionInputs()">
                            <span>Unlink articles (leave uncategorized)</span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelCategoryActionPanel()">Cancel</button>
                        <button type="submit" id="confirmDeleteCategoryBtn" class="btn btn-danger btn-sm">Confirm Delete</button>
                    </div>
                </form>
            </div>

            <!-- Inline Rename Subpanel -->
            <div id="categoryRenamePanel" class="hidden" style="display: none; margin-top: 16px; padding: 14px; background: var(--bg-surface-elevated); border-radius: var(--radius-sm); border: 1px solid var(--border-subtle);">
                <h4 style="margin: 0 0 8px 0; font-size: 14px;">
                    Rename Category: <span id="renameOldCategoryName"></span>
                </h4>
                <form id="categoryRenameForm" onsubmit="handleExecuteCategoryRename(event)">
                    <input type="hidden" id="renameCategoryId">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="renameCategoryNewNameInput" class="form-control" placeholder="New name..." required maxlength="60">
                        <button type="submit" id="confirmRenameCategoryBtn" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelCategoryActionPanel()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Close</button>
        </div>
    </div>
</div>


<!-- =====================================================
     4. ARTICLE PREVIEW MODAL
====================================================== -->
<div id="articlePreviewModal" class="modal" role="dialog" aria-labelledby="articlePreviewModalTitle" aria-modal="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeArticlePreviewModal()"></div>
    <div class="modal-dialog" style="max-width: 920px; width: 95%; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-subtle); padding: 14px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-eye" style="color: var(--accent);"></i>
                <h3 class="modal-title" id="articlePreviewModalTitle" style="margin: 0; font-size: 15px; font-weight: 700;">Article Live Preview</h3>
                <span style="font-size: 11px; padding: 2px 8px; border-radius: 4px; background: var(--bg-surface-elevated); color: var(--text-muted); font-family: var(--font-mono);">
                    Unsaved Content (No DB Mutation)
                </span>
            </div>
            <button type="button" class="modal-close" onclick="closeArticlePreviewModal()" aria-label="Close modal">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body" id="articlePreviewModalBody" style="overflow-y: auto; padding: 20px; background: var(--bg-canvas);">
            <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; margin-bottom: 12px; color: var(--accent);"></i>
                <p>Generating preview...</p>
            </div>
        </div>
        <div class="modal-footer" style="border-top: 1px solid var(--border-subtle); padding: 12px 20px; display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeArticlePreviewModal()">Close Preview</button>
        </div>
    </div>
</div>

<?php
$pageScripts = ['js/shared-editor.js', 'js/articles-edit.js'];
require __DIR__ . '/partials/layout_bottom.php';
?>

