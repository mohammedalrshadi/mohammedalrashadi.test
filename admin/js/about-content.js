// ============================================================
// ADMIN ABOUT CONTENT — about-content.js
// Client-side controller for the "About Content" tab in
// Control Center: Principles cards + Focus Area tag groups.
// Only runs its init if the About tab's containers exist on
// the page (settings.php), so it's safe to load elsewhere.
// ============================================================

let aboutPrinciples = [];
let aboutFocusGroups = {};
let pendingAboutDelete = null;

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('principlesListContainer')) {
        loadAboutContent();

        const form = document.getElementById('aboutBlockForm');
        if (form) {
            form.addEventListener('submit', handleAboutBlockSubmit);
        }
    }

    // SEC-003: delegated handler for about-content action buttons.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-action="delete"]');
        if (!btn) return;
        const id    = Number(btn.dataset.id);
        const title = btn.dataset.title;
        openDeleteAboutModal(id, title);
    });
});

function _aboutCsrf() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function _aboutEscape(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function loadAboutContent() {
    const principlesEl = document.getElementById('principlesListContainer');
    const focusEl = document.getElementById('focusTagsListContainer');

    try {
        const res = await fetch('/api/about_content/list.php', { headers: { 'Accept': 'application/json' } });
        const data = await res.json();

        if (!data.success) {
            principlesEl.innerHTML = `<p style="color: var(--accent-red); font-size: 13px;">Failed to load: ${_aboutEscape(data.message || 'Unknown error')}</p>`;
            return;
        }

        aboutPrinciples = data.principles || [];
        aboutFocusGroups = data.focus_groups || {};

        renderPrinciples();
        renderFocusGroups();

    } catch (err) {
        principlesEl.innerHTML = '<p style="color: var(--accent-red); font-size: 13px;">Network error loading About content.</p>';
        focusEl.innerHTML = '';
    }
}

function renderPrinciples() {
    const el = document.getElementById('principlesListContainer');

    if (aboutPrinciples.length === 0) {
        el.innerHTML = '<p style="color: var(--text-muted); font-size: 13px;">No principle cards yet.</p>';
        return;
    }

    el.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 8px;">
            ${aboutPrinciples.map(p => `
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid var(--border-subtle); border-radius: 8px;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: var(--text-primary); font-size: 13.5px;">
                            <i class="fas ${p.icon ? '' : 'fa-circle'}" style="color: var(--accent); width: 16px;"></i>
                            ${_aboutEscape(p.title)}
                            <span style="font-family: var(--font-mono); font-size: 11px; color: var(--text-muted); margin-left: 6px;">#${p.sort_order}</span>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">${_aboutEscape(p.description || '')}</div>
                    </div>
                    <div style="display: flex; gap: 4px; flex-shrink: 0;">
                        <button type="button" class="btn btn-icon btn-sm" onclick="editAboutBlock(${p.id}, 'principle')" title="Edit"><i class="fas fa-pen"></i></button>
                        <button type="button" class="btn btn-icon btn-sm" data-action="delete" data-id="${p.id}" data-title="${_aboutEscape(p.title)}" title="Delete" style="color: var(--accent-red);"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderFocusGroups() {
    const el = document.getElementById('focusTagsListContainer');
    const groupNames = Object.keys(aboutFocusGroups);

    if (groupNames.length === 0) {
        el.innerHTML = '<p style="color: var(--text-muted); font-size: 13px;">No focus tags yet.</p>';
        return;
    }

    el.innerHTML = groupNames.map(group => `
        <div style="margin-bottom: 16px;">
            <div style="font-family: var(--font-mono); font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">${_aboutEscape(group)}</div>
            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                ${aboutFocusGroups[group].map(t => `
                    <span class="tag badge-neutral" style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px 4px 10px;">
                        ${_aboutEscape(t.title)}
                        <button type="button" onclick="editAboutBlock(${t.id}, 'focus_tag')" title="Edit" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 0;"><i class="fas fa-pen" style="font-size: 10px;"></i></button>
                        <button type="button" data-action="delete" data-id="${t.id}" data-title="${_aboutEscape(t.title)}" title="Delete" style="background: none; border: none; cursor: pointer; color: var(--accent-red); padding: 0;"><i class="fas fa-times" style="font-size: 10px;"></i></button>
                    </span>
                `).join('')}
            </div>
        </div>
    `).join('');
}

// ---- Modal ---------------------------------------------------------------

function startNewAboutBlock(blockType) {
    resetAboutBlockForm(blockType);
    document.getElementById('aboutBlockModal').style.display = 'flex';
}

function editAboutBlock(id, blockType) {
    const list = blockType === 'principle' ? aboutPrinciples : Object.values(aboutFocusGroups).flat();
    const row = list.find(x => x.id === id);
    if (!row) return;

    document.getElementById('aboutBlockModalTitle').textContent = blockType === 'principle' ? 'Edit Principle' : 'Edit Focus Tag';
    document.getElementById('about_block_id').value = row.id;
    document.getElementById('about_block_type').value = blockType;
    document.getElementById('about_group_label').value = row.group_label || '';
    document.getElementById('about_icon').value = row.icon || '';
    document.getElementById('about_title').value = row.title || '';
    document.getElementById('about_description').value = row.description || '';
    document.getElementById('about_sort_order').value = row.sort_order ?? 0;

    toggleAboutFieldsFor(blockType);
    document.getElementById('aboutBlockModal').style.display = 'flex';
}

function toggleAboutFieldsFor(blockType) {
    const isPrinciple = blockType === 'principle';
    document.getElementById('about_group_label_wrap').style.display = isPrinciple ? 'none' : 'block';
    document.getElementById('about_description_wrap').style.display = isPrinciple ? 'block' : 'none';
    document.getElementById('about_icon_wrap').style.display = isPrinciple ? 'block' : 'none';
}

function resetAboutBlockForm(blockType) {
    document.getElementById('aboutBlockModalTitle').textContent = blockType === 'principle' ? 'Add Principle' : 'Add Focus Tag';
    document.getElementById('about_block_id').value = '0';
    document.getElementById('about_block_type').value = blockType;
    document.getElementById('aboutBlockForm').reset();
    document.getElementById('about_sort_order').value = 0;
    toggleAboutFieldsFor(blockType);
}

function closeAboutBlockModal() {
    document.getElementById('aboutBlockModal').style.display = 'none';
}

async function handleAboutBlockSubmit(e) {
    e.preventDefault();

    const blockType = document.getElementById('about_block_type').value;
    const title = document.getElementById('about_title').value.trim();
    const groupLabel = document.getElementById('about_group_label').value.trim();

    if (!title) {
        showToastSafe('Title is required.', 'error');
        return;
    }
    if (blockType === 'focus_tag' && !groupLabel) {
        showToastSafe('Group label is required for a focus tag.', 'error');
        return;
    }

    const payload = {
        id: parseInt(document.getElementById('about_block_id').value, 10) || 0,
        block_type: blockType,
        group_label: groupLabel,
        icon: document.getElementById('about_icon').value.trim(),
        title: title,
        description: document.getElementById('about_description').value.trim(),
        sort_order: parseInt(document.getElementById('about_sort_order').value, 10) || 0,
    };

    const btn = document.getElementById('aboutBlockSaveBtn');
    btn.disabled = true;

    try {
        const res = await fetch('/api/about_content/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _aboutCsrf(), 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            closeAboutBlockModal();
            showToastSafe('Saved.', 'success');
            await loadAboutContent();
        } else {
            showToastSafe(data.message || 'Failed to save.', 'error');
        }
    } catch (err) {
        showToastSafe('Network error while saving.', 'error');
    } finally {
        btn.disabled = false;
    }
}

// ---- Delete ----------------------------------------------------------------

function openDeleteAboutModal(id, title) {
    if (!confirm(`Delete "${title}"? This cannot be undone from the public page (it stays soft-deleted in the database).`)) {
        return;
    }
    executeDeleteAboutBlock(id);
}

async function executeDeleteAboutBlock(id) {
    try {
        const res = await fetch('/api/about_content/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _aboutCsrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const data = await res.json();
        if (data.success) {
            showToastSafe('Deleted.', 'success');
            loadAboutContent();
        } else {
            alert('Failed to delete: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while deleting.');
    }
}

function showToastSafe(msg, type) {
    // settings.js already defines a global showToast used elsewhere on
    // this page; reuse it if present, otherwise fall back to alert().
    if (typeof showToast === 'function') {
        showToast(msg, type);
    } else {
        alert(msg);
    }
}
