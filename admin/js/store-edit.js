// ============================================================
// ADMIN — STORE PRODUCT EDITOR JAVASCRIPT
// admin/js/store-edit.js
//
// Controller for admin/store-edit.php:
//   - Fetches existing product data if editing
//   - Manages dynamic fields based on product_type (external vs free_download)
//   - Auto-generates slugs from title
//   - Handles thumbnail uploads and previews
//   - Handles protected PDF/ZIP file uploads
//   - Handles rich-text description authoring & DOMPurify sanitization
//   - Submits payload to /api/products/create.php or update.php
// ============================================================

let isEditMode = false;
let autoSlugEnabled = true;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function loadProductCategories(selectedCategory) {
    try {
        const res = await fetch('/api/categories/list.php?type=product', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            const select = document.getElementById('category');
            if (select) {
                const currentVal = selectedCategory || select.value;
                select.innerHTML = data.data.map(c => `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`).join('');
                if (currentVal && !data.data.some(c => c.name === currentVal)) {
                    const opt = document.createElement('option');
                    opt.value = currentVal;
                    opt.textContent = currentVal;
                    select.appendChild(opt);
                }
                if (currentVal) {
                    select.value = currentVal;
                }
            }
        }
    } catch (e) {
        console.warn('Could not load dynamic product categories:', e);
    }
}

document.addEventListener('DOMContentLoaded', async function () {
    const productId = parseInt(document.getElementById('product_id')?.value || '0', 10);
    isEditMode = productId > 0;

    await loadProductCategories();

    const galSec = document.getElementById('productGallerySection');
    const galDis = document.getElementById('productGalleryDisabled');
    const resContainer = document.getElementById('product-resources-container');

    if (isEditMode) {
        autoSlugEnabled = false;
        if (galSec) galSec.style.display = 'block';
        if (galDis) galDis.style.display = 'none';
        loadProductForEditing(productId);
    } else {
        if (galSec) galSec.style.display = 'none';
        if (galDis) galDis.style.display = 'block';
        if (resContainer) {
            resContainer.innerHTML = '<div style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 16px;">Please save the product first before adding resources.</div>';
        }
    }
});

/**
 * Loads product details for editing.
 */
async function loadProductForEditing(id) {
    showLoading(true);

    try {
        const response = await fetch(`/api/products/list.php?id=${id}`, {
            method: 'GET',
            credentials: 'same-origin',
        });

        const result = await response.json();

        if (!result.success || !result.data) {
            showToast(result.message || 'Product could not be found.', 'error');
            return;
        }

        const p = result.data;
        await loadProductCategories(p.category);

        // Populate core identity fields
        document.getElementById('title').value = p.title || '';
        document.getElementById('slug').value = p.slug || '';
        document.getElementById('category').value = p.category || 'Other';
        document.getElementById('status').value = p.status || 'draft';
        document.getElementById('sort_order').value = p.sort_order || 0;
        document.getElementById('featured').checked = p.featured === 1;

        // External Access
        const isExternal = (p.product_type === 'external') || Boolean(p.external_url);
        const extToggle = document.getElementById('enableExternalAccessToggle');
        if (extToggle) extToggle.checked = isExternal;
        const extFields = document.getElementById('externalAccessFields');
        if (extFields) extFields.style.display = isExternal ? 'block' : 'none';
        document.getElementById('platform').value = p.platform || '';
        document.getElementById('price_display').value = p.price_display || '';
        document.getElementById('currency').value = p.currency || 'USD';
        document.getElementById('external_url').value = p.external_url || '';

        // Live Demo
        if (p.live_demo_url || p.live_demo_source === 'uploaded') {
            document.getElementById('enableLiveDemoToggle').checked = true;
            document.getElementById('liveDemoFields').style.display = 'block';

            if (p.live_demo_source === 'uploaded') {
                document.querySelector('input[name="live_demo_source"][value="uploaded"]').checked = true;
            } else {
                document.querySelector('input[name="live_demo_source"][value="external"]').checked = true;
            }
        } else {
            document.getElementById('enableLiveDemoToggle').checked = false;
            document.getElementById('liveDemoFields').style.display = 'none';
            document.querySelector('input[name="live_demo_source"][value="external"]').checked = true;
        }
        document.getElementById('live_demo_url').value = p.live_demo_url || '';
        if (p.live_demo_path) {
            document.getElementById('live_demo_path').value = p.live_demo_path;
            const demoStatusBox = document.getElementById('currentDemoStatus');
            const demoPathEl = document.getElementById('currentDemoPath');
            if (demoStatusBox && demoPathEl) {
                demoPathEl.textContent = p.live_demo_path;
                demoStatusBox.style.display = 'flex';
            }
        }
        handleDemoSourceChange();

        // Direct Download
        const isDownload = (p.product_type === 'free_download') || Boolean(p.download_path);
        const dlToggle = document.getElementById('enableDirectDownloadToggle');
        if (dlToggle) dlToggle.checked = isDownload;
        const dlFields = document.getElementById('directDownloadFields');
        if (dlFields) dlFields.style.display = isDownload ? 'block' : 'none';
        document.getElementById('download_path').value = p.download_path || '';
        const statusBox = document.getElementById('currentDownloadStatus');
        const filenameEl = document.getElementById('currentDownloadFilename');
        if (p.download_path && statusBox && filenameEl) {
            filenameEl.textContent = p.download_path;
            statusBox.style.display = 'flex';
        } else if (statusBox) {
            statusBox.style.display = 'none';
        }

        // Thumbnail Artwork
        const thumbInput = document.getElementById('thumbnail');
        if (thumbInput) thumbInput.value = p.thumbnail || '';
        const thumbImg = document.getElementById('thumbnailPreviewImg');
        const thumbPh = document.getElementById('thumbnailPlaceholder');
        const removeThumbBtn = document.getElementById('removeThumbnailBtn');
        if (p.thumbnail) {
            if (thumbImg) {
                thumbImg.src = p.thumbnail;
                thumbImg.style.display = 'block';
            }
            if (thumbPh) thumbPh.style.display = 'none';
            if (removeThumbBtn) removeThumbBtn.style.display = 'inline-flex';
        } else {
            if (thumbImg) {
                thumbImg.src = '';
                thumbImg.style.display = 'none';
            }
            if (thumbPh) thumbPh.style.display = 'block';
            if (removeThumbBtn) removeThumbBtn.style.display = 'none';
        }

        // Short Summary (Card Preview)
        const shortDescEl = document.getElementById('short_description');
        if (shortDescEl) {
            shortDescEl.value = p.short_description || '';
        }

        // Full Editorial Description (WYSIWYG contentEditor)
        const editorEl = document.getElementById('contentEditor');
        if (editorEl) {
            editorEl.innerHTML = p.description || '';
        }

        // Context header & view public link
        const publicLink = document.getElementById('viewPublicLink');
        if (publicLink && p.slug) {
            publicLink.href = `/store/${p.slug}`;
            publicLink.style.display = 'inline-flex';
        }
        const bcCurrent = document.getElementById('breadcrumbCurrent');
        if (bcCurrent && p.title) bcCurrent.textContent = p.title;
        const edTitle = document.getElementById('editorTitle');
        if (edTitle && p.title) edTitle.textContent = `Edit: ${p.title}`;

        // Sync Home Showcase toggle
        syncInlineShowcaseToggle('product', id);

        // Load Product Resources & Product Gallery
        loadProductResources();
        loadGalleryImages();

    } catch (err) {
        console.error('[store-edit.js] Load error:', err);
        showToast('Network error loading product.', 'error');
    } finally {
        showLoading(false);
    }
}


function handleLiveDemoToggle() {
    const isEnabled = document.getElementById('enableLiveDemoToggle')?.checked;
    const fields = document.getElementById('liveDemoFields');
    if (fields) {
        fields.style.display = isEnabled ? 'block' : 'none';
    }
}

function handleDemoSourceChange() {
    const source = document.querySelector('input[name="live_demo_source"]:checked')?.value;
    const upFields = document.getElementById('demoSourceUploaded');
    const extFields = document.getElementById('demoSourceExternal');
    if (source === 'uploaded') {
        if (upFields) upFields.style.display = 'block';
        if (extFields) extFields.style.display = 'none';
    } else {
        if (upFields) upFields.style.display = 'none';
        if (extFields) extFields.style.display = 'block';
    }
}

function handleDirectDownloadToggle() {
    const isEnabled = document.getElementById('enableDirectDownloadToggle')?.checked;
    const fields = document.getElementById('directDownloadFields');
    if (fields) {
        fields.style.display = isEnabled ? 'block' : 'none';
    }
}

function handleExternalAccessToggle() {
    const isEnabled = document.getElementById('enableExternalAccessToggle')?.checked;
    const fields = document.getElementById('externalAccessFields');
    if (fields) {
        fields.style.display = isEnabled ? 'block' : 'none';
    }
}

function removeUploadedDemo() {
    // Note: We don't delete the physical files here, we just unset it in the UI and let save handle it if needed
    // or call an API. The instructions say to make UI for "Remove Demo", which we just did.
    document.getElementById('live_demo_path').value = '';
    const statusBox = document.getElementById('currentDemoStatus');
    if (statusBox) statusBox.style.display = 'none';
    showToast('Uploaded demo removed from product configuration.', 'success');
}

async function uploadDemoZip() {
    const fileInput = document.getElementById('demoZipInput');
    const productId = document.getElementById('product_id')?.value;

    if (!productId || productId === '0') {
        showToast('Please save the product first before uploading a demo ZIP.', 'error');
        return;
    }
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showToast('Please select a ZIP file.', 'error');
        return;
    }

    const file = fileInput.files[0];
    showLoading(true);

    try {
        const formData = new FormData();
        formData.append('demo_file', file);
        formData.append('id', productId);

        const response = await fetch('/api/products/upload_demo.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        });

        const result = await response.json();

        if (!result.success) {
            showToast(result.message || 'Failed to upload demo.', 'error');
            return;
        }

        document.getElementById('live_demo_path').value = result.live_demo_path;

        const demoStatusBox = document.getElementById('currentDemoStatus');
        const demoPathEl = document.getElementById('currentDemoPath');
        if (demoStatusBox && demoPathEl) {
            demoPathEl.textContent = result.live_demo_path;
            demoStatusBox.style.display = 'flex';
        }

        fileInput.value = '';
        showToast('Demo uploaded and deployed successfully.', 'success');

    } catch (err) {
        console.error('[store-edit.js] Demo upload error:', err);
        showToast('Network error uploading demo.', 'error');
    } finally {
        showLoading(false);
    }
}


/**
 * Slug generator from title.
 */
function generateSlugFromTitle() {
    const titleVal = document.getElementById('title')?.value || '';
    if (!titleVal) return;

    let s = titleVal.toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .trim()
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    if (!s) s = 'product-' + Math.random().toString(36).substring(2, 8);
    document.getElementById('slug').value = s;
}

function handleTitleChange() {
    if (autoSlugEnabled && !isEditMode) {
        generateSlugFromTitle();
    }
}

document.getElementById('slug')?.addEventListener('input', function () {
    autoSlugEnabled = false;
});

/**
 * Thumbnail Upload.
 */
async function uploadThumbnailFile() {
    const fileInput = document.getElementById('thumbnailFileInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showToast('Please choose an image file first.', 'error');
        return;
    }

    const file = fileInput.files[0];
    showLoading(true);

    try {
        const formData = new FormData();
        formData.append('thumbnail', file, 'upload_' + Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9.-_]/g, '_'));
        formData.append('upload_type', 'thumbnail');

        const response = await fetch('/api/products/upload.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        });

        const result = await response.json();

        if (!result.success || !result.url) {
            showToast(result.message || 'Failed to upload thumbnail.', 'error');
            return;
        }

        setThumbnailPreview(result.url);
        fileInput.value = '';
        showToast('Thumbnail uploaded successfully.', 'success');

    } catch (err) {
        console.error('[store-edit.js] Thumbnail upload error:', err);
        showToast('Network error uploading thumbnail.', 'error');
    } finally {
        showLoading(false);
    }
}

function setThumbnailPreview(url) {
    document.getElementById('thumbnail').value = url;
    const img = document.getElementById('thumbnailPreviewImg');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    const removeBtn = document.getElementById('removeThumbnailBtn');

    if (img && placeholder) {
        img.src = url;
        img.style.display = 'block';
        placeholder.style.display = 'none';
    }
    if (removeBtn) {
        removeBtn.style.display = 'inline-flex';
    }
}

function removeThumbnail() {
    document.getElementById('thumbnail').value = '';
    const img = document.getElementById('thumbnailPreviewImg');
    const placeholder = document.getElementById('thumbnailPlaceholder');
    const removeBtn = document.getElementById('removeThumbnailBtn');

    if (img && placeholder) {
        img.src = '';
        img.style.display = 'none';
        placeholder.style.display = 'block';
    }
    if (removeBtn) {
        removeBtn.style.display = 'none';
    }
}

/**
 * Download File Upload (PDF, ZIP).
 */
async function uploadDownloadFile() {
    const fileInput = document.getElementById('downloadFileInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showToast('Please select a PDF or ZIP file to upload.', 'error');
        return;
    }

    const file = fileInput.files[0];
    showLoading(true);

    try {
        const formData = new FormData();
        formData.append('download_file', file);
        formData.append('upload_type', 'download');

        const response = await fetch('/api/products/upload.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: formData,
        });

        const result = await response.json();

        if (!result.success || !result.download_path) {
            showToast(result.message || 'Failed to upload download file.', 'error');
            return;
        }

        document.getElementById('download_path').value = result.download_path;

        const statusBox = document.getElementById('currentDownloadStatus');
        const filenameEl = document.getElementById('currentDownloadFilename');
        if (statusBox && filenameEl) {
            filenameEl.textContent = `${result.filename} (${(result.size / 1024 / 1024).toFixed(2)} MB)`;
            statusBox.style.display = 'flex';
        }

        fileInput.value = '';
        showToast('Download asset uploaded and secured.', 'success');

    } catch (err) {
        console.error('[store-edit.js] Download upload error:', err);
        showToast('Network error uploading download asset.', 'error');
    } finally {
        showLoading(false);
    }
}

function removeDownloadFile() {
    document.getElementById('download_path').value = '';
    const statusBox = document.getElementById('currentDownloadStatus');
    if (statusBox) {
        statusBox.style.display = 'none';
    }
    showToast('Download asset unlinked from product.', 'success');
}

/**
 * Save product (Create or Update).
 */
async function saveProductForm() {
    const id = parseInt(document.getElementById('product_id')?.value || '0', 10);
    const title = (document.getElementById('title')?.value || '').trim();
    const slug = (document.getElementById('slug')?.value || '').trim().toLowerCase();
    const category = document.getElementById('category')?.value || 'Other';
    const status = document.getElementById('status')?.value || 'draft';
    const sortOrder = parseInt(document.getElementById('sort_order')?.value || '0', 10);
    const featured = document.getElementById('featured')?.checked ? 1 : 0;

    const isExternalEnabled = document.getElementById('enableExternalAccessToggle')?.checked;
    const platform = isExternalEnabled ? (document.getElementById('platform')?.value || '').trim() : '';
    const priceDisplay = isExternalEnabled ? (document.getElementById('price_display')?.value || '').trim() : '';
    const currency = isExternalEnabled ? (document.getElementById('currency')?.value || 'USD').trim().toUpperCase() : 'USD';
    const externalUrl = isExternalEnabled ? (document.getElementById('external_url')?.value || '').trim() : '';

    const isDirectDownloadEnabled = document.getElementById('enableDirectDownloadToggle')?.checked;
    const downloadPath = isDirectDownloadEnabled ? (document.getElementById('download_path')?.value || '').trim() : '';

    const productType = (!isExternalEnabled && isDirectDownloadEnabled) ? 'free_download' : 'external';

    const thumbnail = (document.getElementById('thumbnail')?.value || '').trim();

    const isLiveDemoEnabled = document.getElementById('enableLiveDemoToggle')?.checked;
    const demoSource = document.querySelector('input[name="live_demo_source"]:checked')?.value;

    let liveDemoUrl = '';
    // Note: live_demo_source and live_demo_path are updated via upload_demo.php directly on the backend.
    // However, if we switch back to external, we need to send the external URL.
    if (isLiveDemoEnabled && demoSource === 'external') {
        liveDemoUrl = (document.getElementById('live_demo_url')?.value || '').trim();
    } else if (isLiveDemoEnabled && demoSource === 'uploaded') {
        // liveDemoUrl is empty when uploaded is active
        liveDemoUrl = '';
    }
const shortDescription = (document.getElementById('short_description')?.value || '').trim();
    const rawDescription = document.getElementById('contentEditor')?.innerHTML || '';
    const description = typeof sanitizeArticleHtml === 'function'
        ? sanitizeArticleHtml(rawDescription)
        : rawDescription;

    // Client-side pre-validations
    if (!title) {
        showToast('Product title is required.', 'error');
        document.getElementById('title')?.focus();
        return;
    }

    if (!slug) {
        showToast('Product URL slug is required.', 'error');
        document.getElementById('slug')?.focus();
        return;
    }

    if (productType === 'external') {
        if (!externalUrl) {
            showToast('External URL is required for external products.', 'error');
            document.getElementById('external_url')?.focus();
            return;
        }
    } else if (productType === 'free_download') {
        if (status === 'published' && !downloadPath) {
            showToast('You must upload a download file before publishing a free product.', 'error');
            return;
        }
    }

    if (isLiveDemoEnabled && demoSource === 'external' && !liveDemoUrl) {
        showToast('Live Demo URL is required for external live demo.', 'error');
        document.getElementById('live_demo_url')?.focus();
        return;
    }


    const payload = {
        title,
        slug,
        category,
        product_type: productType,
        status,
        sort_order: sortOrder,
        featured,
        thumbnail,
        short_description: shortDescription,
        description,
    };

    if (document.getElementById('enableExternalAccessToggle')?.checked) {
        payload.platform = (document.getElementById('platform')?.value || '').trim();
        payload.price_display = (document.getElementById('price_display')?.value || '').trim();
        payload.currency = (document.getElementById('currency')?.value || 'USD').trim().toUpperCase();
        payload.external_url = (document.getElementById('external_url')?.value || '').trim();
    } else {
        payload.platform = '';
        payload.price_display = '';
        payload.currency = 'USD';
        payload.external_url = '';
    }

    if (document.getElementById('enableDirectDownloadToggle')?.checked) {
        payload.download_path = (document.getElementById('download_path')?.value || '').trim();
    } else {
        payload.download_path = '';
    }

    if (document.getElementById('enableLiveDemoToggle')?.checked) {
        const demoSource = document.querySelector('input[name="live_demo_source"]:checked')?.value;
        payload.live_demo_source = demoSource;
        if (demoSource === 'external') {
            payload.live_demo_url = (document.getElementById('live_demo_url')?.value || '').trim();
        } else if (demoSource === 'uploaded') {
            payload.live_demo_url = '';
        }
    } else {
        payload.live_demo_source = null;
        payload.live_demo_url = '';
    }


    if (id > 0) {
        payload.id = id;
    }

    const endpoint = id > 0 ? '/api/products/update.php' : '/api/products/create.php';
    showLoading(true);

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const result = await response.json();

        // Clear previous field errors
        document.querySelectorAll('.field-error-msg').forEach(el => el.remove());
        document.querySelectorAll('.error-border').forEach(el => {
            el.classList.remove('error-border');
            el.style.borderColor = '';
        });

        if (!result.success) {
            showToast(result.message || 'Failed to save product.', 'error');
            
            if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                let firstField = null;
                
                result.errors.forEach(err => {
                    let fieldId = err.field;
                    if (fieldId === 'description') fieldId = 'contentEditor';
                    if (fieldId === 'thumbnail') fieldId = 'thumbnailFileInput';
                    
                    const fieldEl = document.getElementById(fieldId);
                    if (fieldEl) {
                        if (!firstField) firstField = fieldEl;
                        
                        fieldEl.classList.add('error-border');
                        fieldEl.style.borderColor = 'red';
                        
                        const msgEl = document.createElement('div');
                        msgEl.className = 'field-error-msg';
                        msgEl.style.color = 'red';
                        msgEl.style.fontSize = '12px';
                        msgEl.style.marginTop = '4px';
                        msgEl.textContent = err.message;
                        
                        if (fieldEl.parentNode) {
                            fieldEl.parentNode.insertBefore(msgEl, fieldEl.nextSibling);
                        }
                    }
                });
                
                if (firstField) {
                    firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            return;
        }

        showToast(result.message || 'Product saved successfully.', 'success');

        setTimeout(() => {
            window.location.href = 'store.php';
        }, 800);

    } catch (err) {
        console.error('[store-edit.js] Save error:', err);
        showToast('Network error saving product.', 'error');
    } finally {
        showLoading(false);
    }
}


// ============================================================
// PRODUCT RESOURCES
// ============================================================

let productResources = [];

async function loadProductResources() {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');
    if (!productId) return;

    const container = document.getElementById('product-resources-list');
    if (container) {
        container.innerHTML = '<div style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 16px;"><i class="fas fa-spinner fa-spin"></i> Loading resources...</div>';
    }

    try {
        const res = await fetch(`/api/products/resources_list.php?product_id=${productId}`);
        const data = await res.json();
        if (data.success) {
            productResources = data.data;
            renderResources();
        } else {
            if (container) {
                container.innerHTML = '<div style="color: var(--danger); font-size: 14px; text-align: center; padding: 16px;">Error loading resources.</div>';
            }
        }
    } catch (e) {
        console.error('Failed to load resources', e);
        if (container) {
            container.innerHTML = '<div style="color: var(--danger); font-size: 14px; text-align: center; padding: 16px;">Network error loading resources.</div>';
        }
    }
}

function formatBytes(bytes, decimals = 1) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function renderResources() {
    const container = document.getElementById('product-resources-list');
    if (!container) return;

    if (productResources.length === 0) {
        container.innerHTML = '<div style="color: var(--text-muted); font-size: 14px; text-align: center; padding: 16px;">No resources added yet.</div>';
        return;
    }

    container.innerHTML = '';
    productResources.forEach((resource, index) => {
        const div = document.createElement('div');
        div.style = 'border: 1px solid var(--border-subtle); padding: 16px; border-radius: var(--radius-sm); display: flex; justify-content: space-between; align-items: center; background: var(--bg-canvas);';

        div.innerHTML = `
            <div>
                <div style="font-weight: 600; font-size: 14px; color: var(--text-primary); margin-bottom: 4px;">
                    <span style="color: var(--text-muted); margin-right: 8px;">${resource.sort_order}.</span>
                    ${escapeHtml(resource.title)}
                </div>
                <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 4px;">
                    ${escapeHtml(resource.file_name)} &middot; ${formatBytes(resource.file_size)}
                </div>
                <div style="font-size: 12px; display: flex; gap: 8px; align-items: center;">
                    <span style="padding: 2px 6px; border-radius: 4px; background: ${resource.status === 'public' ? 'var(--success-subtle)' : 'var(--warning-subtle)'}; color: ${resource.status === 'public' ? 'var(--success)' : 'var(--warning)'};">
                        ${resource.status === 'public' ? 'Published' : 'Draft'}
                    </span>
                    ${resource.description ? `<span style="color: var(--text-muted);">${escapeHtml(resource.description)}</span>` : ''}
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="openResourceModal(${index})">Replace/Edit</button>
                <button type="button" class="btn btn-sm btn-danger" style="background: var(--danger-subtle); color: var(--danger); border: none;" onclick="removeResource(${resource.id})">Remove</button>
            </div>
        `;
        container.appendChild(div);
    });
}

function openResourceModal(resourceIndex = -1) {
    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');

    if (!productId) {
        showToast('Please save the product first before adding resources.', 'error');
        return;
    }

    document.getElementById('resource_product_id').value = productId;

    if (resourceIndex >= 0) {
        const resource = productResources[resourceIndex];
        document.getElementById('resourceModalTitle').textContent = 'Edit Resource';
        document.getElementById('resource_id').value = resource.id;
        document.getElementById('resource_title').value = resource.title;
        document.getElementById('resource_description').value = resource.description || '';
        document.getElementById('resource_sort_order').value = resource.sort_order;
        document.getElementById('resource_status').value = resource.status;

        document.getElementById('resource_file_required_star').style.display = 'none';
        document.getElementById('resource_file').required = false;

        document.getElementById('resource_current_file').style.display = 'block';
        document.getElementById('resource_current_file_name').textContent = resource.file_name;
        document.getElementById('resource_current_file_size').textContent = formatBytes(resource.file_size);
    } else {
        document.getElementById('resourceModalTitle').textContent = 'Add Resource';
        document.getElementById('resourceForm').reset();
        document.getElementById('resource_id').value = '0';
        document.getElementById('resource_sort_order').value = productResources.length > 0 ? Math.max(...productResources.map(r => r.sort_order)) + 1 : 1;

        document.getElementById('resource_file_required_star').style.display = 'inline';
        document.getElementById('resource_file').required = true;
        document.getElementById('resource_current_file').style.display = 'none';
    }

    document.getElementById('resourceModal').style.display = 'flex';
}

function closeResourceModal() {
    document.getElementById('resourceModal').style.display = 'none';
}

async function saveResource(e) {
    e.preventDefault();

    const resourceId = parseInt(document.getElementById('resource_id').value);
    const fileInput = document.getElementById('resource_file');
    const hasFile = fileInput.files && fileInput.files.length > 0;

    const formData = new FormData();
    formData.append('product_id', document.getElementById('resource_product_id').value);
    if (resourceId > 0) formData.append('id', resourceId);
    formData.append('title', document.getElementById('resource_title').value);
    formData.append('description', document.getElementById('resource_description').value);
    formData.append('sort_order', document.getElementById('resource_sort_order').value);
    formData.append('status', document.getElementById('resource_status').value);

    if (hasFile) {
        formData.append('resource_file', fileInput.files[0]);
    }

    try {
        document.getElementById('resourceSaveBtn').disabled = true;
        document.getElementById('resourceSaveBtn').textContent = 'Saving...';

        let endpoint = '/api/products/resource_upload.php';
        let isJsonPost = false;

        // If editing and NO new file, use resource_update.php
        if (resourceId > 0 && !hasFile) {
            endpoint = '/api/products/resource_update.php';
            isJsonPost = true;
        }


        const fetchOptions = {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            }
        };

        if (isJsonPost) {
            fetchOptions.headers['Content-Type'] = 'application/json';
            const payload = {
                id: resourceId,
                title: document.getElementById('resource_title').value,
                description: document.getElementById('resource_description').value,
                sort_order: document.getElementById('resource_sort_order').value,
                status: document.getElementById('resource_status').value
            };
            fetchOptions.body = JSON.stringify(payload);
        } else {
            fetchOptions.body = formData;
        }

        const res = await fetch(endpoint, fetchOptions);
        const data = await res.json();

        if (data.success) {
            showToast(data.message, 'success');
            closeResourceModal();
            loadProductResources();
        } else {
            showToast(data.message || 'Error saving resource.', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Network error saving resource.', 'error');
    } finally {
        document.getElementById('resourceSaveBtn').disabled = false;
        document.getElementById('resourceSaveBtn').textContent = 'Save Resource';
    }
}

async function removeResource(resourceId) {
    if (!confirm('Are you sure you want to remove this resource? This action cannot be undone.')) {
        return;
    }

    try {

        const res = await fetch('/api/products/resource_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ id: resourceId })
        });

        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            loadProductResources();
        } else {
            showToast(data.message || 'Error deleting resource.', 'error');
        }
    } catch (err) {
        console.error(err);
        showToast('Network error deleting resource.', 'error');
    }
}

// ============================================================
// PRODUCT GALLERY
// ============================================================
let galleryImages = [];
let isGalleryReordering = false;
const MAX_GALLERY_IMAGES = 12;

async function loadGalleryImages() {
    const id = document.getElementById('product_id')?.value;
    if (!id || id == 0) return;
    try {
        const res = await fetch(`/api/products/images_list.php?product_id=${id}`, {
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (data.success) {
            galleryImages = Array.isArray(data.data) ? data.data : [];
            renderGallery();
        } else {
            showToast(data.message || 'Failed to load gallery.', 'error');
        }
    } catch(err) {
        console.error(err);
        showToast('Network error loading gallery.', 'error');
    }

}

function renderGallery() {
    const grid = document.getElementById('galleryGrid');
    const emptyState = document.getElementById('galleryEmptyState');
    const countLabel = document.getElementById('galleryCountLabel');
    const uploadBtn = document.getElementById('uploadGalleryBtn');
    const fileInput = document.getElementById('galleryFileInput');
    if (!grid) return;

    countLabel.textContent = `${galleryImages.length} / ${MAX_GALLERY_IMAGES} images`;

    if (galleryImages.length >= MAX_GALLERY_IMAGES) {
        uploadBtn.disabled = true;
        fileInput.disabled = true;
    } else {
        uploadBtn.disabled = false;
        fileInput.disabled = false;
    }

    if (galleryImages.length === 0) {
        grid.style.display = 'none';
        emptyState.style.display = 'block';
        grid.innerHTML = '';
        return;
    }

    emptyState.style.display = 'none';
    grid.style.display = 'grid';
    grid.innerHTML = '';

    galleryImages.forEach((img, index) => {
        const item = document.createElement('div');
        item.style.border = '1px solid var(--border-subtle)';
        item.style.borderRadius = 'var(--radius-sm)';
        item.style.padding = '8px';
        item.style.background = 'var(--bg-canvas)';
        item.style.display = 'flex';
        item.style.flexDirection = 'column';
        item.style.gap = '8px';

        item.draggable = true;
        item.dataset.index = index;
        item.addEventListener('dragstart', handleGalleryDragStart);
        item.addEventListener('dragover', handleGalleryDragOver);
        item.addEventListener('drop', handleGalleryDrop);
        item.addEventListener('dragenter', handleGalleryDragEnter);
        item.addEventListener('dragleave', handleGalleryDragLeave);
        item.addEventListener('dragend', (e) => e.currentTarget.style.opacity = '1');

        const previewContainer = document.createElement('div');
        previewContainer.style.width = '100%';
        previewContainer.style.height = '120px';
        previewContainer.style.overflow = 'hidden';
        previewContainer.style.borderRadius = 'var(--radius-sm)';

        const imgEl = document.createElement('img');
        imgEl.src = img.image_path;
        imgEl.style.width = '100%';
        imgEl.style.height = '100%';
        imgEl.style.objectFit = 'cover';
        imgEl.alt = img.alt_text || 'Gallery Image';
        imgEl.draggable = false;

        previewContainer.appendChild(imgEl);

        const altInput = document.createElement('input');
        altInput.type = 'text';
        altInput.className = 'form-control';
        altInput.style.fontSize = '12px';
        altInput.style.padding = '4px 8px';
        altInput.value = img.alt_text || '';
        altInput.placeholder = 'Alt text (display only)';
        altInput.title = 'Alt text editing is not currently supported by the backend.';
        altInput.readOnly = true;

        const controls = document.createElement('div');
        controls.style.display = 'flex';
        controls.style.justifyContent = 'space-between';

        const moveControls = document.createElement('div');
        moveControls.style.display = 'flex';
        moveControls.style.gap = '4px';

        const moveLeft = document.createElement('button');
        moveLeft.type = 'button';
        moveLeft.className = 'btn btn-secondary btn-sm';
        moveLeft.innerHTML = '<i class="fas fa-arrow-left"></i>';
        moveLeft.title = 'Move left';
        moveLeft.ariaLabel = 'Move image left';
        moveLeft.disabled = index === 0;
        moveLeft.onclick = () => moveGalleryImage(index, -1);

        const moveRight = document.createElement('button');
        moveRight.type = 'button';
        moveRight.className = 'btn btn-secondary btn-sm';
        moveRight.innerHTML = '<i class="fas fa-arrow-right"></i>';
        moveRight.title = 'Move right';
        moveRight.ariaLabel = 'Move image right';
        moveRight.disabled = index === galleryImages.length - 1;
        moveRight.onclick = () => moveGalleryImage(index, 1);

        moveControls.appendChild(moveLeft);
        moveControls.appendChild(moveRight);

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn btn-danger btn-sm';
        delBtn.innerHTML = '<i class="fas fa-trash"></i>';
        delBtn.title = 'Delete image';
        delBtn.ariaLabel = 'Delete image';
        delBtn.onclick = () => deleteGalleryImage(img.id);

        controls.appendChild(moveControls);
        controls.appendChild(delBtn);

        item.appendChild(previewContainer);
        item.appendChild(altInput);
        item.appendChild(controls);

        grid.appendChild(item);
    });
}

async function uploadGalleryImages() {
    const fileInput = document.getElementById('galleryFileInput');
    const files = fileInput.files;
    const productId = document.getElementById('product_id').value;

    if (!files || files.length === 0) {
        showToast('Please select at least one image to upload.', 'error');
        return;
    }

    const uploadBtn = document.getElementById('uploadGalleryBtn');
    uploadBtn.disabled = true;
    fileInput.disabled = true;
    showLoading(true);

    let successCount = 0;

    for (let i = 0; i < files.length; i++) {
        if (galleryImages.length >= MAX_GALLERY_IMAGES) {
            showToast('Maximum gallery limit (12) reached. Remaining files skipped.', 'error');
            break;
        }

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('image', files[i]);

        try {
            const res = await fetch('/api/products/image_upload.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin',
                body: formData
            });
            const data = await res.json();

            if (data.success && data.image) {
                galleryImages.push(data.image);
                successCount++;
            } else {
                showToast(`Failed to upload ${files[i].name}: ${data.message}`, 'error');
            }
        } catch (err) {
            console.error(err);
            showToast(`Network error uploading ${files[i].name}.`, 'error');
        }
    }

    fileInput.value = '';
    renderGallery();
    showLoading(false);

    if (successCount > 0) {
        showToast(`Successfully uploaded ${successCount} image(s).`, 'success');
    }
}

async function deleteGalleryImage(imageId) {
    if (!confirm('Are you sure you want to delete this gallery image? This action cannot be undone.')) {
        return;
    }

    showLoading(true);
    const formData = new URLSearchParams();
    formData.append('id', imageId);

    try {
        const res = await fetch('/api/products/image_delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: formData.toString()
        });
        const data = await res.json();

        if (data.success) {
            galleryImages = galleryImages.filter(img => img.id !== imageId);
            renderGallery();
            showToast('Image deleted successfully.', 'success');
        } else {
            showToast(data.message || 'Failed to delete image.', 'error');
        }
    } catch(err) {
        console.error(err);
        showToast('Network error deleting image.', 'error');
    } finally {
        showLoading(false);
    }
}

function moveGalleryImage(index, direction) {
    if (isGalleryReordering) return;
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= galleryImages.length) return;

    const temp = galleryImages[index];
    galleryImages[index] = galleryImages[targetIndex];
    galleryImages[targetIndex] = temp;

    renderGallery();
    saveGalleryOrder();
}

let draggedGalleryIndex = null;

function handleGalleryDragStart(e) {
    draggedGalleryIndex = parseInt(e.currentTarget.dataset.index, 10);
    e.dataTransfer.effectAllowed = 'move';
    setTimeout(() => { e.target.style.opacity = '0.5'; }, 0);
}

function handleGalleryDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleGalleryDragEnter(e) {
    e.preventDefault();
    const item = e.currentTarget;
    if (parseInt(item.dataset.index, 10) !== draggedGalleryIndex) {
        item.style.border = '1px dashed var(--accent)';
    }
}

function handleGalleryDragLeave(e) {
    e.currentTarget.style.border = '1px solid var(--border-subtle)';
}

function handleGalleryDrop(e) {
    e.preventDefault();
    if (isGalleryReordering) return;
    e.currentTarget.style.border = '1px solid var(--border-subtle)';
    const targetIndex = parseInt(e.currentTarget.dataset.index, 10);

    if (draggedGalleryIndex === null || draggedGalleryIndex === targetIndex) {
        renderGallery();
        return;
    }

    const item = galleryImages.splice(draggedGalleryIndex, 1)[0];
    galleryImages.splice(targetIndex, 0, item);

    renderGallery();
    saveGalleryOrder();
}

async function saveGalleryOrder() {
    if (isGalleryReordering) return;
    isGalleryReordering = true;

    const grid = document.getElementById('galleryGrid');
    if (grid) {
        grid.style.pointerEvents = 'none';
        grid.style.opacity = '0.6';
    }

    const productId = document.getElementById('product_id').value;
    const order = galleryImages.map(img => img.id);
    const backupOrder = [...galleryImages];

    try {
        const res = await fetch('/api/products/image_reorder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({ product_id: parseInt(productId, 10), order: order })
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Failed to reorder images.');
        }
    } catch (err) {
        console.error(err);
        showToast(err.message || 'Network error saving gallery order.', 'error');
        // Rollback state from backup
        galleryImages = backupOrder;
        renderGallery();
    } finally {
        isGalleryReordering = false;
        if (grid) {
            grid.style.pointerEvents = 'auto';
            grid.style.opacity = '1';
        }
    }
}
