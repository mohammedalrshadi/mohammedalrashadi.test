// ============================================================
// ADMIN — HOME SHOWCASE ITEM EDITOR (showcase-edit.php)
// admin/js/showcase-edit.js
//
// Dedicated controller for authoring and editing Home Showcase items:
//   - Creates & updates showcase items via /api/showcase/save.php
//   - Fetches single item via /api/showcase/get.php
//   - Preloads candidates via /api/showcase/candidates.php
//   - Tracks dirty state via UnsavedChangesGuard
//   - Validates fields & handles smooth redirect
// ============================================================

let candidatesCache = {
    products: [],
    projects: [],
    writing: []
};

function escapeHtml(str) {
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
    if (typeof checkAdminAuthentication === 'function') {
        const authenticated = await checkAdminAuthentication();
        if (!authenticated) return;
    }

    // 1. Setup form submit & radio listeners
    const form = document.getElementById('showcaseForm');
    if (form) {
        form.addEventListener('submit', handleShowcaseSubmit);
    }

    const typeRadios = document.querySelectorAll('input[name="item_type_radio"]');
    typeRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            updateModalTypeUI(radio.value);
        });
    });

    // 2. Preload candidate lists for dropdown pickers
    await loadShowcaseCandidates();

    // 3. Initialize UnsavedChangesGuard
    if (typeof UnsavedChangesGuard !== 'undefined') {
        UnsavedChangesGuard.init({
            getSnapshot: getShowcaseSnapshot
        });
    }

    // 4. Load item if editing, or set initial state if creating
    const showcaseId = parseInt(document.getElementById('showcase_id')?.value || '0', 10);
    if (showcaseId > 0) {
        await loadShowcaseData(showcaseId);
    } else {
        updateModalTypeUI('product');
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getShowcaseSnapshot());
        }
    }
});

// ============================================================
// CANDIDATES LOADING
// ============================================================

async function loadShowcaseCandidates() {
    try {
        const res = await fetch('/api/showcase/candidates.php?type=all&limit=100', {
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success && json.data) {
            candidatesCache = {
                products: json.data.products || [],
                projects: json.data.projects || [],
                writing: json.data.writing || json.data.articles || []
            };
            populateCandidateSelects();
        }
    } catch (err) {
        console.warn('Could not preload showcase candidates:', err);
    }
}

function populateCandidateSelects() {
    // 1. Products
    const prodSelect = document.getElementById('select_product_id');
    if (prodSelect) {
        const curr = prodSelect.value;
        prodSelect.innerHTML = '<option value="">-- Choose a Product --</option>' +
            candidatesCache.products.map(p => {
                const price = p.price_display ? ` (${escapeHtml(p.price_display)})` : '';
                return `<option value="${p.id}">${escapeHtml(p.title)}${price}</option>`;
            }).join('');
        if (curr) prodSelect.value = curr;
    }

    // 2. Projects
    const projSelect = document.getElementById('select_project_id');
    if (projSelect) {
        const curr = projSelect.value;
        projSelect.innerHTML = '<option value="">-- Choose a Project --</option>' +
            candidatesCache.projects.map(p => {
                const cat = p.category ? ` [${escapeHtml(p.category)}]` : '';
                return `<option value="${p.id}">${escapeHtml(p.title)}${cat}</option>`;
            }).join('');
        if (curr) projSelect.value = curr;
    }

    // 3. Writing
    const writingSelect = document.getElementById('select_writing_id');
    if (writingSelect) {
        const curr = writingSelect.value;
        writingSelect.innerHTML = '<option value="">-- Choose a Writing Essay --</option>' +
            candidatesCache.writing.map(w => {
                const cat = w.category ? ` [${escapeHtml(w.category)}]` : '';
                return `<option value="${w.id}">${escapeHtml(w.title)}${cat}</option>`;
            }).join('');
        if (curr) writingSelect.value = curr;
    }
}

// ============================================================
// DYNAMIC PICKER VISIBILITY
// ============================================================

function updateModalTypeUI(type) {
    const normType = (type === 'article') ? 'writing' : type;
    const groupProd = document.getElementById('groupProductPicker');
    const groupProj = document.getElementById('groupProjectPicker');
    const groupWrit = document.getElementById('groupWritingPicker');
    const groupImg = document.getElementById('groupImageInputs');

    if (groupProd) groupProd.style.display = (normType === 'product') ? 'block' : 'none';
    if (groupProj) groupProj.style.display = (normType === 'project') ? 'block' : 'none';
    if (groupWrit) groupWrit.style.display = (normType === 'writing') ? 'block' : 'none';
    if (groupImg) groupImg.style.display = (normType === 'image') ? 'block' : 'none';
}

// ============================================================
// LOAD ITEM DATA FOR EDITING
// ============================================================

async function loadShowcaseData(id) {
    showLoading(true);
    try {
        const res = await fetch(`/api/showcase/get.php?id=${encodeURIComponent(id)}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (!data.success || !data.item) {
            alert('Failed to load showcase item: ' + (data.message || 'Not found'));
            window.location.href = 'showcase.php';
            return;
        }

        const item = data.item;

        // Set type radio
        let type = (item.item_type || 'project').toLowerCase();
        if (type === 'article') type = 'writing';

        const radio = document.querySelector(`input[name="item_type_radio"][value="${type}"]`);
        if (radio) {
            radio.checked = true;
        }
        updateModalTypeUI(type);

        // Reference ID or standalone inputs
        const refId = item.reference_id || item.post_id || item.product_id || '';
        if (type === 'product') {
            const sel = document.getElementById('select_product_id');
            if (sel) sel.value = String(refId);
        } else if (type === 'project') {
            const sel = document.getElementById('select_project_id');
            if (sel) sel.value = String(refId);
        } else if (type === 'writing') {
            const sel = document.getElementById('select_writing_id');
            if (sel) sel.value = String(refId);
        } else if (type === 'image') {
            const imgInput = document.getElementById('input_image_url');
            const altInput = document.getElementById('input_alt_text');
            const linkInput = document.getElementById('input_link_url');
            if (imgInput) imgInput.value = item.image_url || '';
            if (altInput) altInput.value = item.alt_text || '';
            if (linkInput) linkInput.value = item.link_url || '';
        }

        // Overrides
        const titleOverrideEl = document.getElementById('override_title');
        const descOverrideEl = document.getElementById('override_desc');
        const imageOverrideEl = document.getElementById('override_image');
        const altOverrideEl = document.getElementById('override_alt');

        if (titleOverrideEl) titleOverrideEl.value = item.title_override || '';
        if (descOverrideEl) descOverrideEl.value = item.description_override || '';
        if (imageOverrideEl) imageOverrideEl.value = item.image_url || '';
        if (altOverrideEl) altOverrideEl.value = item.alt_text || '';

        // Enabled
        const enabledEl = document.getElementById('showcase_is_enabled');
        if (enabledEl) enabledEl.checked = !!item.is_enabled;

        // Titles & Breadcrumb
        const displayTitle = item.title || item.title_override || `#${item.id}`;
        const breadcrumbCurrent = document.getElementById('breadcrumbCurrent');
        if (breadcrumbCurrent) {
            breadcrumbCurrent.textContent = displayTitle.slice(0, 36) + (displayTitle.length > 36 ? '...' : '');
        }

        const editorTitle = document.getElementById('editorTitle');
        if (editorTitle) editorTitle.textContent = 'Edit Showcase Item: ' + displayTitle;

        const formTitle = document.getElementById('showcaseFormTitle');
        if (formTitle) formTitle.textContent = 'Edit Showcase Item: ' + displayTitle;

        // Baseline for dirty tracking
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getShowcaseSnapshot());
        }

    } catch (err) {
        console.error('Error loading showcase item:', err);
        alert('Network error while loading showcase item details.');
    } finally {
        showLoading(false);
    }
}

// ============================================================
// FORM SUBMIT & SAVE
// ============================================================

function submitShowcaseForm() {
    const form = document.getElementById('showcaseForm');
    if (form) {
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

async function handleShowcaseSubmit(e) {
    e.preventDefault();

    hideShowcaseErrors();

    const saveBtns = [
        document.getElementById('saveShowcaseBtn'),
        document.getElementById('headerSaveShowcaseBtn')
    ].filter(Boolean);

    const id = parseInt(document.getElementById('showcase_id')?.value || '0', 10);
    const typeRadio = document.querySelector('input[name="item_type_radio"]:checked');
    let itemType = typeRadio ? typeRadio.value : 'project';
    if (itemType === 'article') itemType = 'writing';

    let referenceId = null;
    let imageUrl = (document.getElementById('override_image')?.value || '').trim();
    let altText = (document.getElementById('override_alt')?.value || '').trim();
    let linkUrl = null;

    if (itemType === 'product') {
        const pVal = document.getElementById('select_product_id')?.value;
        if (!pVal) {
            showShowcaseError('Please select a Store Product.');
            document.getElementById('select_product_id')?.focus();
            return;
        }
        referenceId = parseInt(pVal, 10);
    } else if (itemType === 'project') {
        const prVal = document.getElementById('select_project_id')?.value;
        if (!prVal) {
            showShowcaseError('Please select an Engineering Project.');
            document.getElementById('select_project_id')?.focus();
            return;
        }
        referenceId = parseInt(prVal, 10);
    } else if (itemType === 'writing') {
        const wVal = document.getElementById('select_writing_id')?.value;
        if (!wVal) {
            showShowcaseError('Please select a Writing Essay.');
            document.getElementById('select_writing_id')?.focus();
            return;
        }
        referenceId = parseInt(wVal, 10);
    } else if (itemType === 'image') {
        const rawImg = (document.getElementById('input_image_url')?.value || '').trim();
        if (!rawImg) {
            showShowcaseError('Please specify an Image URL or asset path.');
            document.getElementById('input_image_url')?.focus();
            return;
        }
        imageUrl = rawImg;
        const rawAlt = (document.getElementById('input_alt_text')?.value || '').trim();
        if (rawAlt) altText = rawAlt;
        linkUrl = (document.getElementById('input_link_url')?.value || '').trim();
    }

    const titleOverride = (document.getElementById('override_title')?.value || '').trim();
    const descOverride = (document.getElementById('override_desc')?.value || '').trim();
    const isEnabled = document.getElementById('showcase_is_enabled')?.checked ? 1 : 0;

    const payload = {
        id: id,
        item_type: itemType,
        reference_id: referenceId,
        title_override: titleOverride,
        description_override: descOverride,
        image_url: imageUrl,
        alt_text: altText,
        link_url: linkUrl,
        is_enabled: isEnabled
    };

    saveBtns.forEach(b => { b.disabled = true; });
    showLoading(true);

    try {
        const res = await fetch('/api/showcase/save.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        if (!json.success) {
            throw new Error(json.message || 'Failed to save showcase item.');
        }

        if (typeof showToast === 'function') {
            showToast(json.message || (id > 0 ? 'Showcase item updated successfully.' : 'Showcase item added successfully.'), 'success');
        }

        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.bypass();
        }

        setTimeout(() => {
            window.location.href = 'showcase.php';
        }, 600);

    } catch (err) {
        console.error('Save showcase error:', err);
        showShowcaseError(err.message || 'Network error occurred while saving.');
    } finally {
        showLoading(false);
        saveBtns.forEach(b => { b.disabled = false; });
    }
}

// ============================================================
// ERROR DISPLAY
// ============================================================

function showShowcaseError(msg) {
    const errBox = document.getElementById('showcaseErrorBox') || document.getElementById('modalErrorBox');
    if (errBox) {
        errBox.textContent = msg;
        errBox.style.display = 'block';
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (typeof showToast === 'function') {
        showToast(msg, 'error');
    }
}

function hideShowcaseErrors() {
    const errBoxes = [
        document.getElementById('showcaseErrorBox'),
        document.getElementById('modalErrorBox')
    ];
    errBoxes.forEach(box => {
        if (box) box.style.display = 'none';
    });
}

// ============================================================
// UNSAVED CHANGES SNAPSHOT & CANCEL
// ============================================================

function getShowcaseSnapshot() {
    const typeRadio = document.querySelector('input[name="item_type_radio"]:checked');
    const itemType = typeRadio ? typeRadio.value : '';

    return {
        item_type: itemType,
        product_id: (document.getElementById('select_product_id')?.value || '').trim(),
        project_id: (document.getElementById('select_project_id')?.value || '').trim(),
        writing_id: (document.getElementById('select_writing_id')?.value || '').trim(),
        image_url: (document.getElementById('input_image_url')?.value || '').trim(),
        alt_text: (document.getElementById('input_alt_text')?.value || '').trim(),
        link_url: (document.getElementById('input_link_url')?.value || '').trim(),
        title_override: (document.getElementById('override_title')?.value || '').trim(),
        desc_override: (document.getElementById('override_desc')?.value || '').trim(),
        image_override: (document.getElementById('override_image')?.value || '').trim(),
        alt_override: (document.getElementById('override_alt')?.value || '').trim(),
        is_enabled: !!document.getElementById('showcase_is_enabled')?.checked
    };
}

function handleShowcaseCancel(e) {
    if (typeof UnsavedChangesGuard !== 'undefined' && UnsavedChangesGuard.isDirty()) {
        const confirmed = confirm('You have unsaved changes in this showcase item. Are you sure you want to discard them?');
        if (!confirmed) {
            if (e) e.preventDefault();
            return false;
        }
        UnsavedChangesGuard.bypass();
    }
    return true;
}

