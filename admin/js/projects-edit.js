// ============================================================
// ADMIN — PROJECTS / PROJECTS EDITOR (projects-edit.php)
// admin/js/projects-edit.js
//
// Dedicated controller for authoring and editing engineering projects:
//   - Full-form project composer (/api/posts/create.php, update.php)
//   - Rich text WYSIWYG technical specification editor
//   - Project Gallery management (upload, view, delete diagrams/screenshots)
//   - UnsavedChangesGuard integration for dirty state tracking
//   - Dynamic project category management modal
//   - Home Showcase sync integration
// ============================================================

let pendingGalleryFiles = [];

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

    // Default project date to today if creating new
    const dateField = document.getElementById('achieve_date');
    if (dateField && !dateField.value) {
        dateField.valueAsDate = new Date();
    }

    const achieveForm = document.getElementById('achieveForm');
    if (achieveForm) {
        achieveForm.addEventListener('submit', handleAchievementSubmit);
    }

    // Populate categories
    await loadAchievementCategories();

    // Featured image preview
    const imageInput = document.getElementById('achieve_image');
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const previewContainer = document.getElementById('achieveImagePreviewContainer');
            const previewImg = document.getElementById('achieveImagePreviewImg');
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

    // Gallery file input
    const galleryInput = document.getElementById('achieveGalleryInput');
    if (galleryInput) {
        galleryInput.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                Array.from(this.files).forEach(f => {
                    handleGalleryFileStaging(f);
                });
                this.value = '';
            }
        });
    }

    // Initialize UnsavedChangesGuard
    if (typeof UnsavedChangesGuard !== 'undefined') {
        UnsavedChangesGuard.init({
            getSnapshot: getAchievementSnapshot
        });
    }

    // Check if editing existing project
    const updateId = document.getElementById('achieve_update_id')?.value?.trim();
    if (updateId && parseInt(updateId, 10) > 0) {
        await loadAchievementData(updateId);
    } else {
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getAchievementSnapshot());
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
// LOAD CATEGORIES
// ============================================================
async function loadAchievementCategories() {
    try {
        const response = await fetch('/api/posts/categories.php?type=project', {
            method: 'GET',
            credentials: 'same-origin',
        });
        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) return;

        const datalist = document.getElementById('achieveCategoriesList');
        if (datalist) {
            datalist.innerHTML = '';
            result.data.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                datalist.appendChild(opt);
            });
        }
    } catch (err) {
        console.warn('Could not load project categories:', err);
    }
}

// ============================================================
// LOAD PROJECT DATA FOR EDITING
// ============================================================
async function loadAchievementData(id) {
    showLoading(true);
    try {
        const res = await fetch(`/api/posts/list.php?id=${encodeURIComponent(id)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json();

        if (!json.success || !json.data) {
            alert('Failed to load project: ' + (json.message || 'Project not found.'));
            window.location.href = 'projects.php';
            return;
        }

        const proj = json.data;

        // Title and Breadcrumbs
        const titleEl = document.getElementById('achieve_title');
        if (titleEl) titleEl.value = proj.title || '';

        const breadcrumbCurrent = document.getElementById('breadcrumbCurrent');
        if (breadcrumbCurrent) breadcrumbCurrent.textContent = proj.title ? (proj.title.slice(0, 36) + (proj.title.length > 36 ? '...' : '')) : `#${proj.id}`;

        const editorTitle = document.getElementById('editorTitle');
        if (editorTitle) editorTitle.textContent = 'Edit Project: ' + (proj.title || `#${proj.id}`);

        const formTitle = document.getElementById('achieveFormTitle');
        if (formTitle) formTitle.textContent = 'Edit Engineering Project: ' + (proj.title || `#${proj.id}`);

        // Category & Date
        const categoryEl = document.getElementById('achieve_category');
        if (categoryEl) categoryEl.value = proj.category || '';

        const dateEl = document.getElementById('achieve_date');
        if (dateEl && proj.created_at) {
            const d = new Date(proj.created_at);
            dateEl.value = d.toISOString().split('T')[0];
        }

        // Excerpt & Content
        const excerptEl = document.getElementById('achieve_excerpt');
        if (excerptEl && proj.excerpt) excerptEl.value = proj.excerpt;

        const editor = document.getElementById('achieveContentEditor');
        if (editor) {
            editor.innerHTML = sanitizeArticleHtml(proj.content || '');
        }

        // Existing image preview
        if (proj.image_url) {
            const previewContainer = document.getElementById('achieveImagePreviewContainer');
            const previewImg = document.getElementById('achieveImagePreviewImg');
            if (previewContainer && previewImg) {
                previewImg.src = proj.image_url;
                previewContainer.style.display = 'block';
            }
        }

        // Status
        const currentStatus = proj.status || 'draft';
        const statusEl = document.getElementById('achieve_status');
        if (statusEl) {
            statusEl.value = currentStatus;
            statusEl.dataset.originalStatus = currentStatus;
        }

        updateAchieveFormStatusBadge(currentStatus);
        updateAchieveFormButtonsForStatus(currentStatus);

        // View Public Page link
        const viewPublicLink = document.getElementById('viewPublicLink');
        if (viewPublicLink) {
            viewPublicLink.href = `/projects.php?id=${encodeURIComponent(proj.id)}`;
            viewPublicLink.style.display = (currentStatus === 'published') ? 'inline-flex' : 'none';
        }

        // Sync Showcase Toggle
        syncInlineShowcaseToggle('project', proj.id);

        // Load Gallery Images
        await loadGalleryImages(proj.id);

        // Set baseline snapshot
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getAchievementSnapshot());
        }

    } catch (err) {
        console.error('Error loading project data:', err);
        alert('Network error while loading project details.');
    } finally {
        showLoading(false);
    }
}

// ============================================================
// STATUS BADGES & BUTTONS
// ============================================================
function updateAchieveFormStatusBadge(status) {
    const badge = document.getElementById('achieveFormStatus');
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

function updateAchieveFormButtonsForStatus(status) {
    const publishBtn = document.getElementById('achievePublishBtn');
    const headerPublishBtn = document.getElementById('headerPublishBtn');
    const publishBtnText = document.getElementById('achievePublishBtnText');

    if (status === 'published') {
        if (publishBtnText) publishBtnText.textContent = 'Update Published Project';
        if (publishBtn) {
            publishBtn.className = 'btn btn-primary';
            publishBtn.innerHTML = '<i class="fas fa-check"></i> <span id="achievePublishBtnText">Update Published Project</span>';
        }
        if (headerPublishBtn) {
            headerPublishBtn.innerHTML = '<i class="fas fa-check"></i> <span>Update Project</span>';
        }
    } else {
        if (publishBtnText) publishBtnText.textContent = 'Publish Project';
        if (publishBtn) {
            publishBtn.className = 'btn btn-primary';
            publishBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span id="achievePublishBtnText">Publish Project</span>';
        }
        if (headerPublishBtn) {
            headerPublishBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Publish Project</span>';
        }
    }
}

// ============================================================
// GALLERY LOGIC
// ============================================================
async function loadGalleryImages(postId) {
    const grid = document.getElementById('achieveGalleryGrid');
    if (!grid) return;

    try {
        const res = await fetch(`/api/project_images/list.php?post_id=${encodeURIComponent(postId)}`, {
            credentials: 'same-origin'
        });
        const json = await res.json();
        const savedImages = (json.success && Array.isArray(json.data)) ? json.data : [];

        renderGalleryGrid(savedImages);
    } catch (err) {
        console.warn('Could not load gallery images:', err);
    }
}

function renderGalleryGrid(savedImages = []) {
    const grid = document.getElementById('achieveGalleryGrid');
    if (!grid) return;

    grid.innerHTML = '';

    savedImages.forEach(img => {
        const item = document.createElement('div');
        item.className = 'gallery-thumb-item';
        item.style.cssText = 'position: relative; width: 100px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border-subtle); background: var(--bg-hover); display: inline-block; margin: 4px;';
        item.innerHTML = `
            <img src="${img.image_url}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
            <button type="button" class="btn-icon btn-danger" style="position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; padding: 0; font-size: 10px; background: rgba(0,0,0,0.6); color: white; border-radius: 4px; border: none; cursor: pointer;" onclick="deleteGalleryImage(${img.id})" title="Delete image">
                <i class="fas fa-trash"></i>
            </button>
        `;
        grid.appendChild(item);
    });

    pendingGalleryFiles.forEach((entry, idx) => {
        const item = document.createElement('div');
        item.className = 'gallery-thumb-item pending';
        item.style.cssText = 'position: relative; width: 100px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px dashed var(--accent); background: var(--bg-hover); display: inline-block; margin: 4px;';
        item.innerHTML = `
            <img src="${entry.previewUrl}" alt="" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;">
            <span style="position: absolute; bottom: 2px; left: 4px; font-size: 9px; background: var(--accent); color: white; padding: 1px 4px; border-radius: 2px;">Pending</span>
            <button type="button" class="btn-icon btn-danger" style="position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; padding: 0; font-size: 10px; background: rgba(0,0,0,0.6); color: white; border-radius: 4px; border: none; cursor: pointer;" onclick="removePendingGalleryFile(${idx})" title="Cancel upload">
                <i class="fas fa-times"></i>
            </button>
        `;
        grid.appendChild(item);
    });
}

function handleGalleryFileStaging(file) {
    const previewUrl = URL.createObjectURL(file);
    pendingGalleryFiles.push({ file, previewUrl });
    const postId = document.getElementById('achieve_update_id')?.value;
    if (postId) {
        loadGalleryImages(postId);
    } else {
        renderGalleryGrid([]);
    }
}

function removePendingGalleryFile(idx) {
    if (pendingGalleryFiles[idx]) {
        URL.revokeObjectURL(pendingGalleryFiles[idx].previewUrl);
        pendingGalleryFiles.splice(idx, 1);
        const postId = document.getElementById('achieve_update_id')?.value;
        if (postId) {
            loadGalleryImages(postId);
        } else {
            renderGalleryGrid([]);
        }
    }
}

async function deleteGalleryImage(imageId) {
    const confirmed = confirm('Remove this image from the project gallery?');
    if (!confirmed) return;

    try {
        const postId = document.getElementById('achieve_update_id')?.value;
        const res = await fetch('/api/project_images/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id: imageId, post_id: postId ? parseInt(postId, 10) : 0 })
        });
        const json = await res.json();
        if (json.success) {
            if (postId) loadGalleryImages(postId);
            if (typeof showToast === 'function') showToast('Gallery image removed.', 'info');
        } else {
            alert(json.message || 'Failed to delete gallery image.');
        }
    } catch (e) {
        alert('Network error while deleting image.');
    }
}

async function uploadPendingGalleryFiles(postId) {
    if (!pendingGalleryFiles.length) return;

    for (const entry of pendingGalleryFiles) {
        const formData = new FormData();
        formData.append('image', entry.file);
        formData.append('post_id', postId);

        try {
            await fetch('/api/project_images/add.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': getCsrfTokenSafe()
                },
                body: formData
            });
        } catch (e) {
            console.warn('Failed to upload gallery file:', entry.file.name, e);
        }
    }

    pendingGalleryFiles = [];
}

// ============================================================
// FORM SUBMISSION & SAVE
// ============================================================
function submitAchieveForm(targetStatus) {
    const statusField = document.getElementById('achieve_status');
    if (statusField && targetStatus) {
        statusField.value = targetStatus;
    }
    const form = document.getElementById('achieveForm');
    if (form) {
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

async function handlePublishAchievement() {
    const statusField = document.getElementById('achieve_status');
    if (statusField) {
        statusField.value = 'published';
    }
    const form = document.getElementById('achieveForm');
    if (form) {
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

function isAchieveSubstantivelyEmpty(html) {
    if (!html) return true;
    const stripped = html
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/g, ' ')
        .trim();
    const hasMedia = /<(img|video|audio|iframe|table)\b/i.test(html);
    return stripped === '' && !hasMedia;
}

async function handleAchievementSubmit(e) {
    e.preventDefault();

    const titleEl = document.getElementById('achieve_title');
    const categoryEl = document.getElementById('achieve_category');
    const contentEditor = document.getElementById('achieveContentEditor');
    const updateId = document.getElementById('achieve_update_id')?.value?.trim();
    const isUpdating = Boolean(updateId && parseInt(updateId, 10) > 0);

    const title = titleEl ? titleEl.value.trim() : '';
    const category = categoryEl ? categoryEl.value.trim() : '';
    const rawHtml = contentEditor ? contentEditor.innerHTML : '';

    if (!title) {
        if (typeof showToast === 'function') showToast('Please enter a project title.', 'error');
        else alert('Please enter a project title.');
        titleEl?.focus();
        return;
    }

    if (!category) {
        if (typeof showToast === 'function') showToast('Please select or enter a category.', 'error');
        else alert('Please select or enter a category.');
        categoryEl?.focus();
        return;
    }

    if (isAchieveSubstantivelyEmpty(rawHtml)) {
        if (typeof showToast === 'function') showToast('Please provide case study details or specification.', 'error');
        else alert('Please provide case study details or specification.');
        contentEditor?.focus();
        return;
    }

    const sanitizedContent = sanitizeArticleHtml(rawHtml);
    const postStatus = document.getElementById('achieve_status')?.value || 'draft';
    const achieveDate = document.getElementById('achieve_date')?.value || '';

    const submitBtns = [
        document.getElementById('achieveSubmitBtn'),
        document.getElementById('achievePublishBtn'),
        document.getElementById('headerSaveDraftBtn'),
        document.getElementById('headerPublishBtn')
    ].filter(Boolean);

    submitBtns.forEach(b => { b.disabled = true; });

    showLoading(true);

    try {
        const imageInput = document.getElementById('achieve_image');
        const existingImg = document.getElementById('achieveImagePreviewImg');
        const imageUrl = await EditorUtils.uploadImageOrGetExisting(imageInput, existingImg);

        const payload = {
            title,
            category,
            content: sanitizedContent,
            type: 'project',
            status: postStatus,
            created_at: achieveDate,
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
            throw new Error(result.message || 'Failed to save project.');
        }

        const savedId = isUpdating ? updateId : (result.data?.id || result.id);

        // Upload any pending gallery images
        if (savedId && pendingGalleryFiles.length > 0) {
            await uploadPendingGalleryFiles(savedId);
        }

        if (isUpdating) {
            const originalStatus = document.getElementById('achieve_status')?.dataset?.originalStatus || 'draft';
            await EditorUtils.handleStatusTransition(savedId, postStatus, originalStatus);
        }

        if (typeof showToast === 'function') {
            showToast(isUpdating ? 'Project updated successfully!' : 'Project created successfully!', 'success');
        }

        // Bypass unsaved changes guard on clean save
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.bypass();
        }

        setTimeout(() => {
            window.location.href = 'projects.php';
        }, 600);

    } catch (err) {
        console.error('Error saving project:', err);
        if (typeof showToast === 'function') {
            showToast(err.message || 'Network error while saving project.', 'error');
        } else {
            alert(err.message || 'Network error while saving project.');
        }
    } finally {
        showLoading(false);
        submitBtns.forEach(b => { b.disabled = false; });
    }
}

// ============================================================
// UNSAVED CHANGES SNAPSHOT & CANCEL
// ============================================================
function getAchievementSnapshot() {
    const titleEl = document.getElementById('achieve_title');
    const categoryEl = document.getElementById('achieve_category');
    const contentEl = document.getElementById('achieveContentEditor');
    const excerptEl = document.getElementById('achieve_excerpt');
    const dateEl = document.getElementById('achieve_date');
    const imageEl = document.getElementById('achieve_image');

    const html = contentEl ? contentEl.innerHTML : '';
    const normHtml = typeof UnsavedChangesGuard !== 'undefined'
        ? UnsavedChangesGuard.normalizeHtml(html)
        : (html || '').trim();

    return {
        title: (titleEl ? titleEl.value : '').trim(),
        category: (categoryEl ? categoryEl.value : '').trim(),
        content: normHtml,
        excerpt: (excerptEl ? excerptEl.value : '').trim(),
        date: (dateEl ? dateEl.value : '').trim(),
        has_new_image: !!(imageEl && imageEl.files && imageEl.files.length > 0),
        pending_gallery_count: pendingGalleryFiles.length,
    };
}

function handleAchievementCancel(e) {
    if (typeof UnsavedChangesGuard !== 'undefined' && UnsavedChangesGuard.isDirty()) {
        const confirmed = confirm('You have unsaved changes in this project. Are you sure you want to discard them?');
        if (!confirmed) {
            if (e) e.preventDefault();
            return false;
        }
        UnsavedChangesGuard.bypass();
    }
    return true;
}

// ============================================================
// CATEGORY MODAL LOGIC (REQ-002)
// ============================================================
function openAchieveCategoryModal() {
    const modal = document.getElementById('achieveCategoryManageModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        loadAchieveCategoriesForModal();
    }
}

function closeAchieveCategoryModal() {
    const modal = document.getElementById('achieveCategoryManageModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
        cancelAchieveCategoryActionPanel();
    }
}

async function loadAchieveCategoriesForModal() {
    const tbody = document.getElementById('achieveCategoryListTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading categories...</td></tr>';

    try {
        const res = await fetch('/api/categories/list.php?type=project', { credentials: 'same-origin' });
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

async function handleAddAchieveCategory() {
    const input = document.getElementById('newAchieveCategoryInput');
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
            body: JSON.stringify({ name, type: 'project' })
        });
        const json = await res.json();
        if (json.success) {
            input.value = '';
            loadAchieveCategoriesForModal();
            loadAchievementCategories();
            if (typeof showToast === 'function') showToast('Category created.', 'success');
        } else {
            alert(json.message || 'Failed to create category.');
        }
    } catch (err) {
        alert('Network error while adding category.');
    }
}

function openCategoryRename(id, name) {
    document.getElementById('achieveRenameCategoryId').value = id;
    document.getElementById('achieveRenameCategoryNewNameInput').value = name;
    document.getElementById('achieveRenameOldCategoryName').textContent = name;
    document.getElementById('achieveCategoryRenamePanel').style.display = 'block';
    document.getElementById('achieveCategoryDeletePanel').style.display = 'none';
}

async function handleExecuteAchieveCategoryRename(e) {
    e.preventDefault();
    const id = document.getElementById('achieveRenameCategoryId').value;
    const newName = document.getElementById('achieveRenameCategoryNewNameInput').value.trim();
    if (!id || !newName) return;

    try {
        const res = await fetch('/api/categories/rename.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id, name: newName, type: 'project' })
        });
        const json = await res.json();
        if (json.success) {
            cancelAchieveCategoryActionPanel();
            loadAchieveCategoriesForModal();
            loadAchievementCategories();
            if (typeof showToast === 'function') showToast('Category renamed.', 'success');
        } else {
            alert(json.message || 'Failed to rename category.');
        }
    } catch (err) {
        alert('Network error while renaming category.');
    }
}

function initiateCategoryDelete(id, name, activeCount) {
    document.getElementById('achieveDeleteSourceCategoryId').value = id;
    document.getElementById('achieveDeleteTargetCategoryName').textContent = name;
    document.getElementById('achieveDeleteUsageDescription').textContent = `${activeCount} project(s) currently use this category.`;
    document.getElementById('achieveCategoryDeletePanel').style.display = 'block';
    document.getElementById('achieveCategoryRenamePanel').style.display = 'none';

    const select = document.getElementById('achieveReassignTargetCategorySelect');
    if (select) {
        fetch('/api/categories/list.php?type=project', { credentials: 'same-origin' })
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

function toggleAchieveDeleteActionInputs() {
    const isReassign = document.getElementById('achieveDeleteActionReassign').checked;
    const wrapper = document.getElementById('achieveReassignSelectWrapper');
    if (wrapper) wrapper.style.display = isReassign ? 'block' : 'none';
}

async function handleExecuteAchieveCategoryDelete(e) {
    e.preventDefault();
    const id = document.getElementById('achieveDeleteSourceCategoryId').value;
    const action = document.getElementById('achieveDeleteActionReassign').checked ? 'reassign' : 'unlink';
    const targetCategory = document.getElementById('achieveReassignTargetCategorySelect')?.value || '';

    try {
        const res = await fetch('/api/categories/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id, action, target_category: targetCategory, type: 'project' })
        });
        const json = await res.json();
        if (json.success) {
            cancelAchieveCategoryActionPanel();
            loadAchieveCategoriesForModal();
            loadAchievementCategories();
            if (typeof showToast === 'function') showToast('Category deleted.', 'success');
        } else {
            alert(json.message || 'Failed to delete category.');
        }
    } catch (err) {
        alert('Network error while deleting category.');
    }
}

function cancelAchieveCategoryActionPanel() {
    const delPanel = document.getElementById('achieveCategoryDeletePanel');
    const renPanel = document.getElementById('achieveCategoryRenamePanel');
    if (delPanel) delPanel.style.display = 'none';
    if (renPanel) renPanel.style.display = 'none';
}

