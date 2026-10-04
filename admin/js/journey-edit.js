// ============================================================
// ADMIN — JOURNEY MILESTONE EDITOR (journey-edit.php)
// admin/js/journey-edit.js
//
// Dedicated controller for authoring and editing Journey milestones:
//   - Creates & updates milestones via /api/journey/save.php
//   - Fetches single milestone via /api/journey/get.php
//   - Tracks dirty state via UnsavedChangesGuard
//   - Validates fields & handles smooth redirect
// ============================================================

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
    const authenticated = await checkAdminAuthentication();
    if (!authenticated) return;

    const form = document.getElementById('milestoneForm');
    if (form) {
        form.addEventListener('submit', handleMilestoneSubmit);
    }

    if (typeof UnsavedChangesGuard !== 'undefined') {
        UnsavedChangesGuard.init({
            getSnapshot: getMilestoneSnapshot
        });
    }

    const milestoneId = parseInt(document.getElementById('milestone_id')?.value || '0', 10);
    if (milestoneId > 0) {
        await loadMilestoneData(milestoneId);
    } else {
        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getMilestoneSnapshot());
        }
    }
});

// ============================================================
// LOAD MILESTONE DATA FOR EDITING
// ============================================================
async function loadMilestoneData(id) {
    showLoading(true);
    try {
        const res = await fetch(`/api/journey/get.php?id=${encodeURIComponent(id)}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (!data.success || !data.milestone) {
            alert('Failed to load milestone: ' + (data.message || 'Not found'));
            window.location.href = 'journey.php';
            return;
        }

        const m = data.milestone;

        document.getElementById('milestone_title').value = m.title || '';
        document.getElementById('milestone_period').value = m.period_label || '';
        document.getElementById('milestone_category').value = m.category || 'milestone';
        document.getElementById('milestone_status').value = m.status || 'draft';
        document.getElementById('milestone_icon').value = m.icon || '';
        document.getElementById('milestone_sort_order').value = m.sort_order ?? 0;
        document.getElementById('milestone_description').value = m.description || '';

        const breadcrumbCurrent = document.getElementById('breadcrumbCurrent');
        if (breadcrumbCurrent) breadcrumbCurrent.textContent = m.title ? (m.title.slice(0, 36) + (m.title.length > 36 ? '...' : '')) : `#${m.id}`;

        const editorTitle = document.getElementById('editorTitle');
        if (editorTitle) editorTitle.textContent = 'Edit Milestone: ' + (m.title || `#${m.id}`);

        const formTitle = document.getElementById('milestoneFormTitle');
        if (formTitle) formTitle.textContent = 'Edit Milestone: ' + (m.title || `#${m.id}`);

        if (typeof UnsavedChangesGuard !== 'undefined') {
            UnsavedChangesGuard.setBaseline(getMilestoneSnapshot());
        }

    } catch (err) {
        console.error('Error loading milestone:', err);
        alert('Network error while loading milestone details.');
    } finally {
        showLoading(false);
    }
}

// ============================================================
// FORM SUBMIT & SAVE
// ============================================================
function submitMilestoneForm() {
    const form = document.getElementById('milestoneForm');
    if (form) {
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}

async function handleMilestoneSubmit(e) {
    e.preventDefault();

    const titleEl = document.getElementById('milestone_title');
    const periodEl = document.getElementById('milestone_period');
    const descEl = document.getElementById('milestone_description');
    const idEl = document.getElementById('milestone_id');

    const title = titleEl ? titleEl.value.trim() : '';
    const period = periodEl ? periodEl.value.trim() : '';
    const desc = descEl ? descEl.value.trim() : '';
    const id = parseInt(idEl?.value || '0', 10);

    if (!title) {
        if (typeof showToast === 'function') showToast('Milestone title is required.', 'error');
        else alert('Milestone title is required.');
        titleEl?.focus();
        return;
    }

    if (!period) {
        if (typeof showToast === 'function') showToast('Period label is required.', 'error');
        else alert('Period label is required.');
        periodEl?.focus();
        return;
    }

    if (!desc) {
        if (typeof showToast === 'function') showToast('Description is required.', 'error');
        else alert('Description is required.');
        descEl?.focus();
        return;
    }

    const payload = {
        id: id,
        title: title,
        period_label: period,
        category: document.getElementById('milestone_category')?.value || 'milestone',
        status: document.getElementById('milestone_status')?.value || 'draft',
        icon: document.getElementById('milestone_icon')?.value.trim() || 'timeline',
        sort_order: parseInt(document.getElementById('milestone_sort_order')?.value || '0', 10),
        description: desc
    };

    const saveBtns = [
        document.getElementById('milestoneSaveBtn'),
        document.getElementById('headerSaveMilestoneBtn')
    ].filter(Boolean);

    saveBtns.forEach(b => { b.disabled = true; });

    showLoading(true);

    try {
        const res = await fetch('/api/journey/save.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (data.success) {
            if (typeof showToast === 'function') {
                showToast(id > 0 ? 'Milestone updated successfully!' : 'Milestone created successfully!', 'success');
            }

            if (typeof UnsavedChangesGuard !== 'undefined') {
                UnsavedChangesGuard.bypass();
            }

            setTimeout(() => {
                window.location.href = 'journey.php';
            }, 600);

        } else {
            alert(data.message || 'Failed to save milestone.');
        }

    } catch (err) {
        console.error('Error saving milestone:', err);
        alert('Network error while saving milestone.');
    } finally {
        showLoading(false);
        saveBtns.forEach(b => { b.disabled = false; });
    }
}

// ============================================================
// UNSAVED CHANGES SNAPSHOT & CANCEL
// ============================================================
function getMilestoneSnapshot() {
    return {
        title: (document.getElementById('milestone_title')?.value || '').trim(),
        period: (document.getElementById('milestone_period')?.value || '').trim(),
        category: (document.getElementById('milestone_category')?.value || '').trim(),
        status: (document.getElementById('milestone_status')?.value || '').trim(),
        icon: (document.getElementById('milestone_icon')?.value || '').trim(),
        sort_order: (document.getElementById('milestone_sort_order')?.value || '').trim(),
        description: (document.getElementById('milestone_description')?.value || '').trim(),
    };
}

function handleMilestoneCancel(e) {
    if (typeof UnsavedChangesGuard !== 'undefined' && UnsavedChangesGuard.isDirty()) {
        const confirmed = confirm('You have unsaved changes in this milestone. Are you sure you want to discard them?');
        if (!confirmed) {
            if (e) e.preventDefault();
            return false;
        }
        UnsavedChangesGuard.bypass();
    }
    return true;
}

