// ============================================================
// ADMIN — ARTICLES EDITOR (articles-edit.php)
// admin/js/articles-edit.js
//
// Dedicated controller for authoring and editing technical articles.
// Features:
//   - Full-form article drafting & publishing (/api/posts/create.php, update.php)
//   - Rich text WYSIWYG editor with quotes, highlighter, headers, lists
//   - UnsavedChangesGuard integration for dirty state tracking
//   - Dynamic category management modal & category suggestions
//   - Real-time SEO analysis integration
//   - Non-destructive article preview modal (/api/posts/preview.php)
//   - Home Showcase sync integration
// ============================================================

let currentAdminId = null;

function escapeHTML(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    // Default publish-date to today if creating new article
    const publishDateField = document.getElementById('publish_date');
    if (publishDateField && !publishDateField.value) {
        publishDateField.valueAsDate = new Date();
    }

    const postForm = document.getElementById('postForm');
    if (postForm) {
        postForm.addEventListener('submit', handleBlogSubmit);
    }

    // Populate categories
    await loadArticleCategories();

    // Initialize quote and selection tools
    initSelectionQuoteFeature();

    // Image preview listener
    const imageInput = document.getElementById('image');
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewImg = document.getElementById('imagePreviewImg');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    if (previewImg && previewContainer) {
                        previewImg.src = e.target.result;
                        previewContainer.style.display = 'block';
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    // Initialize SEO Analyzer
    if (typeof SeoAnalyzerUI !== 'undefined') {
        window._articleSeoAnalyzer = SeoAnalyzerUI.attach({
            buttonId: 'seoAnalyzeBtn',
            resultsContainerId: 'seoResultsContainer',
            keywordInputId: 'seoFocusKeyword',
            hintId: 'seoNotSavedHint',
            sectionId: 'seoAnalysisSection',
            titleId: 'seoAnalysisTitle',
            descriptionId: 'seoAnalysisDesc',
            keywordLabelId: 'seoKeywordLabel',
            langToggleId: 'seoLangToggle',
            getPostId: () => {
                const idField = document.getElementById('update_id');
                return idField ? idField.value : '';
            },
            notSavedHint: {
                ar: 'احفظ المقال أولاً لتتمكن من تحليل SEO.',
                en: 'Save the article first to analyze its SEO.'
            }
        });
    }

    // Initialize UnsavedChangesGuard
    if (typeof UnsavedChangesGuard !== 'undefined') {
        UnsavedChangesGuard.init({
            getSnapshot: getArticleSnapshot
        });
    }

    // Check if editing existing post
    const updateId = document.getElementById('update_id')?.value?.trim();
    if (updateId && parseInt(updateId, 10) > 0) {
        await loadArticleData(updateId);
    } else {
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getArticleSnapshot());
        }
    }

    // SEC-003: delegated listener for category rename/delete buttons.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-action^="cat-"]');
        if (!btn) return;
        const action = btn.dataset.action;
        const catId  = Number(btn.dataset.id);
        const name   = btn.dataset.name;
        const count  = Number(btn.dataset.count || 0);
        if (action === 'cat-rename') {
            openCategoryRename(catId, name);
        } else if (action === 'cat-delete') {
            initiateCategoryDelete(catId, name, count);
        }
    });
});

// ============================================================
// LOAD ARTICLE CATEGORIES
// ============================================================
async function loadArticleCategories() {
    try {
        const response = await fetch('/api/posts/categories.php?type=blog', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-cache'
        });
        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) return;

        const datalist = document.getElementById('categoriesList');
        if (datalist) {
            datalist.innerHTML = '';
            result.data.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                datalist.appendChild(opt);
            });
        }
    } catch (err) {
        console.warn('Could not load categories:', err);
    }
}

// ============================================================
// LOAD ARTICLE DATA FOR EDITING
// ============================================================
async function loadArticleData(id) {
    showLoading(true);
    try {
        const res = await fetch(`/api/posts/list.php?id=${encodeURIComponent(id)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json();

        if (!json.success || !json.data) {
            alert('Failed to load article: ' + (json.message || 'Article not found.'));
            window.location.href = 'articles.php';
            return;
        }

        const post = json.data;

        // Title and Breadcrumb
        const titleEl = document.getElementById('title');
        if (titleEl) titleEl.value = post.title || '';

        const breadcrumbCurrent = document.getElementById('breadcrumbCurrent');
        if (breadcrumbCurrent) breadcrumbCurrent.textContent = post.title ? (post.title.slice(0, 36) + (post.title.length > 36 ? '...' : '')) : `#${post.id}`;

        const editorTitle = document.getElementById('editorTitle');
        if (editorTitle) editorTitle.textContent = 'Edit Article: ' + (post.title || `#${post.id}`);

        const formTitle = document.getElementById('formTitle');
        if (formTitle) formTitle.textContent = 'Edit Article: ' + (post.title || `#${post.id}`);

        // Category & Date
        const categoryEl = document.getElementById('category');
        if (categoryEl) categoryEl.value = post.category || '';

        const dateEl = document.getElementById('publish_date');
        if (dateEl && post.created_at) {
            const d = new Date(post.created_at);
            dateEl.value = d.toISOString().split('T')[0];
        }

        // Quotes & Excerpt
        const quoteArEl = document.getElementById('quote_ar');
        if (quoteArEl) quoteArEl.value = post.quote_ar || '';

        const quoteEnEl = document.getElementById('quote_en');
        if (quoteEnEl) quoteEnEl.value = post.quote_en || '';



        // Content
        const editor = document.getElementById('contentEditor');
        if (editor) {
            editor.innerHTML = sanitizeArticleHtml(post.content || '');
        }

        // Existing image preview
        if (post.image_url) {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewImg = document.getElementById('imagePreviewImg');
            if (previewContainer && previewImg) {
                previewImg.src = post.image_url;
                previewContainer.style.display = 'block';
            }
        }

        // Status
        const currentStatus = post.status || 'draft';
        const postStatusEl = document.getElementById('post_status');
        if (postStatusEl) {
            postStatusEl.value = currentStatus;
            postStatusEl.dataset.originalStatus = currentStatus;
        }

        updateFormStatusBadge(currentStatus);
        updateFormButtonsForStatus(currentStatus);

        // View Public Page link
        const viewPublicLink = document.getElementById('viewPublicLink');
        if (viewPublicLink) {
            viewPublicLink.href = `/post.php?id=${encodeURIComponent(post.id)}`;
            viewPublicLink.style.display = (currentStatus === 'published') ? 'inline-flex' : 'none';
        }

        // Sync Showcase Toggle
        syncInlineShowcaseToggle('writing', post.id);

        // Refresh SEO Analyzer
        if (window._articleSeoAnalyzer) {
            window._articleSeoAnalyzer.refreshState();
        }

        // Set baseline snapshot
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getArticleSnapshot());
        }

    } catch (err) {
        console.error('Error loading article data:', err);
        alert('Network error while loading article details.');
    } finally {
        showLoading(false);
    }
}

// ============================================================
// STATUS BADGES & BUTTONS
// ============================================================
function updateFormStatusBadge(status) {
    const badge = document.getElementById('articleFormStatus');
    if (!badge) return;

    badge.style.display = 'inline-flex';
    badge.className = 'status-badge';

    if (status === 'published') {
        badge.classList.add('status-success');
        badge.textContent = 'Published';
    } else if (status === 'hidden') {
        badge.classList.add('status-neutral');
        badge.textContent = 'Hidden';
    } else {
        badge.classList.add('status-warning');
        badge.textContent = 'Draft';
    }
}

function updateFormButtonsForStatus(status) {
    const publishBtn = document.getElementById('publishBtn');
    const headerPublishBtn = document.getElementById('headerPublishBtn');
    const publishBtnText = document.getElementById('publishBtnText');

    if (status === 'published') {
        if (publishBtnText) publishBtnText.textContent = 'Update Published Article';
        if (publishBtn) {
            publishBtn.className = 'btn btn-primary';
            publishBtn.innerHTML = '<i class="fas fa-check"></i> <span id="publishBtnText">Update Published Article</span>';
        }
        if (headerPublishBtn) {
            headerPublishBtn.innerHTML = '<i class="fas fa-check"></i> <span>Update Article</span>';
        }
    } else {
        if (publishBtnText) publishBtnText.textContent = 'Publish Article';
        if (publishBtn) {
            publishBtn.className = 'btn btn-primary';
            publishBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span id="publishBtnText">Publish Article</span>';
        }
        if (headerPublishBtn) {
            headerPublishBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Publish Article</span>';
        }
    }
}

// ============================================================
// FORM SUBMISSION & SAVE
// ============================================================
function submitArticleForm(targetStatus) {
    const statusField = document.getElementById('post_status');
    if (statusField && targetStatus) {
        statusField.value = targetStatus;
    }
    const form = document.getElementById('postForm');
    if (form) {
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

async function handlePublishArticle() {
    const statusField = document.getElementById('post_status');
    if (statusField) {
        statusField.value = 'published';
    }
    const form = document.getElementById('postForm');
    if (form) {
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

function isSubstantivelyEmpty(html) {
    if (!html) return true;
    const stripped = html
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ')
        .trim();
    const hasMedia = /<(img|video|audio|iframe|table)\b/i.test(html);
    return stripped === '' && !hasMedia;
}

async function handleBlogSubmit(e) {
    e.preventDefault();

    const titleEl = document.getElementById('title');
    const categoryEl = document.getElementById('category');
    const contentEditor = document.getElementById('contentEditor');
    const updateId = document.getElementById('update_id')?.value?.trim();
    const isUpdating = Boolean(updateId && parseInt(updateId, 10) > 0);

    const title = titleEl ? titleEl.value.trim() : '';
    const category = categoryEl ? categoryEl.value.trim() : '';
    const rawHtml = contentEditor ? contentEditor.innerHTML : '';

    if (!title) {
        if (typeof showToast === 'function') showToast('Please enter an article title.', 'error');
        else alert('Please enter an article title.');
        titleEl?.focus();
        return;
    }

    if (!category) {
        if (typeof showToast === 'function') showToast('Please select or enter a category.', 'error');
        else alert('Please select or enter a category.');
        categoryEl?.focus();
        return;
    }

    if (isSubstantivelyEmpty(rawHtml)) {
        if (typeof showToast === 'function') showToast('Please provide substantive content for the article.', 'error');
        else alert('Please provide substantive content for the article.');
        contentEditor?.focus();
        return;
    }

    const sanitizedContent = sanitizeArticleHtml(rawHtml);
    const postStatus = document.getElementById('post_status')?.value || 'draft';
    const publishDate = document.getElementById('publish_date')?.value || '';
    const quoteAr = document.getElementById('quote_ar')?.value?.trim() || '';
    const quoteEn = document.getElementById('quote_en')?.value?.trim() || '';

    const submitBtns = [
        document.getElementById('submitBtn'),
        document.getElementById('publishBtn'),
        document.getElementById('headerSaveDraftBtn'),
        document.getElementById('headerPublishBtn')
    ].filter(Boolean);

    submitBtns.forEach(b => { b.disabled = true; });

    showLoading(true);

    try {
        const imageInput = document.getElementById('image');
        const existingImg = document.getElementById('imagePreviewImg');
        const imageUrl = await EditorUtils.uploadImageOrGetExisting(imageInput, existingImg);

        const payload = {
            title,
            category,
            content: sanitizedContent,
            type: 'blog',
            status: postStatus,
            created_at: publishDate,
            quote_ar: quoteAr,
            quote_en: quoteEn,
            image_url: imageUrl
        };

        let endpoint = '/api/posts/create.php';
        if (isUpdating) {
            endpoint = '/api/posts/update.php';
            payload.id = parseInt(updateId, 10);
            delete payload.status;
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save article.');
        }

        const savedId = isUpdating ? updateId : (result.data?.id || result.id);

        if (isUpdating) {
            const originalStatus = document.getElementById('post_status')?.dataset?.originalStatus || 'draft';
            await EditorUtils.handleStatusTransition(savedId, postStatus, originalStatus);
        }

        if (typeof showToast === 'function') {
            showToast(isUpdating ? 'Article updated successfully!' : 'Article created successfully!', 'success');
        }

        // Bypass unsaved changes guard on clean save
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.bypass();
        }

        setTimeout(() => {
            window.location.href = 'articles.php';
        }, 600);

    } catch (err) {
        console.error('Error saving article:', err);
        if (typeof showToast === 'function') {
            showToast(err.message || 'Network error while saving article.', 'error');
        } else {
            alert(err.message || 'Network error while saving article.');
        }
    } finally {
        showLoading(false);
        submitBtns.forEach(b => { b.disabled = false; });
    }
}

// ============================================================
// UNSAVED CHANGES SNAPSHOT & CANCEL
// ============================================================
function getArticleSnapshot() {
    const titleEl = document.getElementById('title');
    const categoryEl = document.getElementById('category');
    const contentEl = document.getElementById('contentEditor');
    const quoteArEl = document.getElementById('quote_ar');
    const quoteEnEl = document.getElementById('quote_en');

    const dateEl = document.getElementById('publish_date');
    const imageEl = document.getElementById('image');

    const html = contentEl ? contentEl.innerHTML : '';
    const normHtml = typeof UnsavedChangesGuard !== 'undefined'
        ? UnsavedChangesGuard.normalizeHtml(html)
        : (html || '').trim();

    return {
        title: (titleEl ? titleEl.value : '').trim(),
        category: (categoryEl ? categoryEl.value : '').trim(),
        content: normHtml,
        quote_ar: (quoteArEl ? quoteArEl.value : '').trim(),
        quote_en: (quoteEnEl ? quoteEnEl.value : '').trim(),
        publish_date: (dateEl ? dateEl.value : '').trim(),
        has_new_image: !!(imageEl && imageEl.files && imageEl.files.length > 0),
        image_name: imageEl && imageEl.files && imageEl.files.length > 0 ? imageEl.files[0].name : '',
    };
}

function handleArticleCancel(e) {
    if (typeof UnsavedChangesGuard !== 'undefined' && UnsavedChangesGuard.isDirty()) {
        const confirmed = confirm('You have unsaved changes in this article. Are you sure you want to discard them?');
        if (!confirmed) {
            if (e) e.preventDefault();
            return false;
        }
        UnsavedChangesGuard.bypass();
    }
    return true;
}

// ============================================================
// PREVIEW MODAL
// ============================================================
async function handlePreviewArticle() {
    const modal = document.getElementById('articlePreviewModal');
    const body = document.getElementById('articlePreviewModalBody');
    if (!modal || !body) return;

    modal.style.display = 'flex';
    body.innerHTML = `
        <div style="text-align: center; color: var(--text-muted); padding: 40px;">
            <i class="fas fa-circle-notch fa-spin" style="font-size: 24px; margin-bottom: 12px; color: var(--accent);"></i>
            <p>Rendering preview...</p>
        </div>
    `;

    const title = document.getElementById('title')?.value || 'Untitled Draft';
    const category = document.getElementById('category')?.value || 'Uncategorized';
    const rawContent = document.getElementById('contentEditor')?.innerHTML || '';
    const quoteAr = document.getElementById('quote_ar')?.value || '';
    const quoteEn = document.getElementById('quote_en')?.value || '';
    const publishDate = document.getElementById('publish_date')?.value || new Date().toISOString().split('T')[0];

    let imagePreviewUrl = '';
    const imageInput = document.getElementById('image');
    if (imageInput && imageInput.files && imageInput.files[0]) {
        imagePreviewUrl = URL.createObjectURL(imageInput.files[0]);
    } else {
        const existingImg = document.getElementById('imagePreviewImg');
        if (existingImg && existingImg.src) {
            imagePreviewUrl = existingImg.src;
        }
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);

    try {
        const res = await fetch('/api/posts/preview.php', {
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                title,
                category,
                content: rawContent,
                quote_ar: quoteAr,
                quote_en: quoteEn,
                publish_date: publishDate,
                image_url: imagePreviewUrl
            })
        });

        clearTimeout(timeoutId);
        const data = await res.json();
        if (data.success && data.html) {
            body.innerHTML = data.html;
        } else {
            body.innerHTML = `<div class="error-msg" style="padding: 24px; color: var(--danger);">Failed to generate preview: ${escapeHTML(data.message || 'Unknown error')}</div>`;
        }
    } catch (err) {
        if (err.name === 'AbortError') {
            body.innerHTML = `<div class="error-msg" style="padding: 24px; color: var(--danger);">Request timed out. Please try again.</div>`;
        } else {
            body.innerHTML = `<div class="error-msg" style="padding: 24px; color: var(--danger);">Network error generating preview.</div>`;
        }
    }
}

function closeArticlePreviewModal() {
    const modal = document.getElementById('articlePreviewModal');
    if (modal) modal.style.display = 'none';
}

// ============================================================
// EDITORIAL FORMATTING HELPERS
// ============================================================
function detectTextLanguage(text) {
    if (!text || typeof text !== 'string') return 'ltr';
    const arabicPattern = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
    return arabicPattern.test(text) ? 'rtl' : 'ltr';
}

function toggleHighlight() {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0 || sel.isCollapsed) return;
    const range = sel.getRangeAt(0);

    const parentMark = getParentMark(range.commonAncestorContainer);
    if (parentMark) {
        const text = parentMark.textContent;
        const textNode = document.createTextNode(text);
        parentMark.parentNode.replaceChild(textNode, parentMark);
    } else {
        const mark = document.createElement('mark');
        mark.className = 'editor-highlight';
        try {
            range.surroundContents(mark);
        } catch (e) {
            document.execCommand('backColor', false, '#FFF59D');
        }
    }
}

function getParentMark(node) {
    while (node && node.nodeName !== 'BODY' && node.id !== 'contentEditor') {
        if (node.nodeName === 'MARK') return node;
        node = node.parentNode;
    }
    return null;
}

function initSelectionQuoteFeature() {
    const editor = document.getElementById('contentEditor');
    const floatingBtn = document.getElementById('floatingMakeQuoteBtn');
    if (!editor || !floatingBtn) return;

    editor.addEventListener('mouseup', () => {
        const sel = window.getSelection();
        if (!sel || sel.isCollapsed || !sel.toString().trim()) {
            floatingBtn.style.display = 'none';
            return;
        }
        const range = sel.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        if (rect.width > 0) {
            floatingBtn.style.top = `${window.scrollY + rect.top - 40}px`;
            floatingBtn.style.left = `${window.scrollX + rect.left + (rect.width / 2) - 45}px`;
            floatingBtn.style.display = 'block';
        }
    });

    floatingBtn.addEventListener('click', () => {
        convertSelectionToQuote();
        floatingBtn.style.display = 'none';
    });

    document.addEventListener('mousedown', (e) => {
        if (floatingBtn && !floatingBtn.contains(e.target) && !editor.contains(e.target)) {
            floatingBtn.style.display = 'none';
        }
    });
}

function convertSelectionToQuote() {
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed) return;
    const selectedText = sel.toString().trim();
    if (!selectedText) return;

    const lang = detectTextLanguage(selectedText);
    const targetFieldId = (lang === 'rtl') ? 'quote_ar' : 'quote_en';
    const targetField = document.getElementById(targetFieldId);

    if (targetField) {
        targetField.value = selectedText;
        if (typeof showToast === 'function') {
            showToast(`Saved to ${lang === 'rtl' ? 'Arabic' : 'English'} quote field!`, 'success');
        }
    }
}

// ============================================================
// CATEGORY MODAL LOGIC (REQ-002)
// ============================================================
function openCategoryModal() {
    const modal = document.getElementById('categoryManageModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        loadCategoriesForModal();
    }
}

function closeCategoryModal() {
    const modal = document.getElementById('categoryManageModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        cancelCategoryActionPanel();
    }
}

async function loadCategoriesForModal() {
    const tbody = document.getElementById('categoryListTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading categories...</td></tr>';

    try {
        const res = await fetch('/api/categories/list.php?type=blog', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success || !Array.isArray(json.data)) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger); padding: 14px;">Failed to load categories.</td></tr>';
            return;
        }

        if (json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">No categories yet.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(c => `
            <tr>
                <td style="font-weight: 600;">${escapeHTML(c.name)}</td>
                <td style="text-align: right; color: var(--text-muted);">${c.post_count || 0}</td>
                <td style="text-align: right; white-space: nowrap;">
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px; margin-right: 4px;" data-action="cat-rename" data-id="${c.id}" data-name="${escapeHTML(c.name)}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" style="padding: 2px 8px; font-size: 11px;" data-action="cat-delete" data-id="${c.id}" data-name="${escapeHTML(c.name)}" data-count="${c.post_count || 0}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger); padding: 14px;">Error loading categories.</td></tr>';
    }
}

async function handleAddCategory() {
    const input = document.getElementById('newCategoryInput');
    const name = input ? input.value.trim() : '';
    if (!name) return;

    try {
        const res = await fetch('/api/categories/create.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ name, type: 'blog' })
        });
        const json = await res.json();
        if (json.success) {
            input.value = '';
            loadCategoriesForModal();
            loadArticleCategories();
            if (typeof showToast === 'function') showToast('Category created.', 'success');
        } else {
            alert(json.message || 'Failed to create category.');
        }
    } catch (err) {
        alert('Network error while adding category.');
    }
}

function openCategoryRename(id, name) {
    document.getElementById('renameCategoryId').value = id;
    document.getElementById('renameCategoryNewNameInput').value = name;
    document.getElementById('renameOldCategoryName').textContent = name;
    document.getElementById('categoryRenamePanel').style.display = 'block';
    document.getElementById('categoryDeletePanel').style.display = 'none';
}

async function handleExecuteCategoryRename(e) {
    e.preventDefault();
    const id = document.getElementById('renameCategoryId').value;
    const newName = document.getElementById('renameCategoryNewNameInput').value.trim();
    if (!id || !newName) return;

    try {
        const res = await fetch('/api/categories/rename.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id, name: newName, type: 'blog' })
        });
        const json = await res.json();
        if (json.success) {
            cancelCategoryActionPanel();
            loadCategoriesForModal();
            loadArticleCategories();
            if (typeof showToast === 'function') showToast('Category renamed.', 'success');
        } else {
            alert(json.message || 'Failed to rename category.');
        }
    } catch (err) {
        alert('Network error while renaming category.');
    }
}

function initiateCategoryDelete(id, name, activeCount) {
    document.getElementById('deleteSourceCategoryId').value = id;
    document.getElementById('deleteTargetCategoryName').textContent = name;
    document.getElementById('deleteUsageDescription').textContent = `${activeCount} article(s) currently use this category.`;
    document.getElementById('categoryDeletePanel').style.display = 'block';
    document.getElementById('categoryRenamePanel').style.display = 'none';

    // Populate reassign select with other categories
    const select = document.getElementById('reassignTargetCategorySelect');
    if (select) {
        fetch('/api/categories/list.php?type=blog', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    select.innerHTML = data.data
                        .filter(c => c.id != id)
                        .map(c => `<option value="${escapeHTML(c.name)}">${escapeHTML(c.name)}</option>`)
                        .join('');
                }
            });
    }
}

function toggleDeleteActionInputs() {
    const isReassign = document.getElementById('deleteActionReassign').checked;
    const wrapper = document.getElementById('reassignSelectWrapper');
    if (wrapper) wrapper.style.display = isReassign ? 'block' : 'none';
}

async function handleExecuteCategoryDelete(e) {
    e.preventDefault();
    const id = document.getElementById('deleteSourceCategoryId').value;
    const action = document.getElementById('deleteActionReassign').checked ? 'reassign' : 'unlink';
    const targetCategory = document.getElementById('reassignTargetCategorySelect')?.value || '';

    try {
        const res = await fetch('/api/categories/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id, action, target_category: targetCategory, type: 'blog' })
        });
        const json = await res.json();
        if (json.success) {
            cancelCategoryActionPanel();
            loadCategoriesForModal();
            loadArticleCategories();
            if (typeof showToast === 'function') showToast('Category deleted.', 'success');
        } else {
            alert(json.message || 'Failed to delete category.');
        }
    } catch (err) {
        alert('Network error while deleting category.');
    }
}

function cancelCategoryActionPanel() {
    const delPanel = document.getElementById('categoryDeletePanel');
    const renPanel = document.getElementById('categoryRenamePanel');
    if (delPanel) delPanel.style.display = 'none';
    if (renPanel) renPanel.style.display = 'none';
}

