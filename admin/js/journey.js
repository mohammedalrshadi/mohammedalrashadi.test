// ============================================================
// ADMIN JOURNEY — journey.js
// Client-side controller for the Journey Timeline Directory page:
//   - Filter milestones by status (All, Published, Draft, Trash)
//   - Soft-delete to trash & trash management (restore, purge)
//   - Direct navigation to journey-edit.php
// ============================================================

let allMilestones = [];
let trashMilestones = [];
let currentStatusFilter = 'all';
let pendingDeleteId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadMilestones();

    // SEC-003: delegated handler — values come from data-* attributes, not inline JS strings.
    const journeyBody = document.getElementById('journeyTableBody');
    if (journeyBody) {
        journeyBody.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            const milestoneId    = Number(btn.dataset.id);
            const milestoneTitle = btn.dataset.title;  // plain string from dataset
            if (action === 'delete') {
                openDeleteMilestoneModal(milestoneId, milestoneTitle);
            } else if (action === 'purge') {
                purgeMilestone(milestoneId, milestoneTitle);
            }
        });
    }
});

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function loadMilestones() {
    const tbody = document.getElementById('journeyTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading milestones...</td></tr>';

    try {
        const [res, trashRes] = await Promise.all([
            fetch('/api/journey/list.php?scope=admin', { headers: { 'Accept': 'application/json' } }),
            fetch('/api/journey/trash.php', { headers: { 'Accept': 'application/json' } }).catch(() => null),
        ]);

        const data = await res.json();
        const trashData = trashRes ? await trashRes.json().catch(() => null) : null;

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--accent-red); padding: 24px;">Error: ${escapeHtml(data.message || 'Failed to load milestones')}</td></tr>`;
            return;
        }

        allMilestones = data.milestones || [];
        trashMilestones = (trashData && trashData.success && Array.isArray(trashData.data)) ? trashData.data : [];

        updateTabCounters();
        renderTable();

    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--accent-red); padding: 24px;">Failed to connect to Journey API.</td></tr>';
    }
}

function updateTabCounters() {
    const allCount = allMilestones.length;
    const publishedCount = allMilestones.filter(m => m.status === 'published').length;
    const draftCount = allMilestones.filter(m => m.status === 'draft').length;
    const trashCount = trashMilestones.length;

    const elAll = document.getElementById('tabCountJourneyAll');
    const elPub = document.getElementById('tabCountJourneyPublished');
    const elDraft = document.getElementById('tabCountJourneyDraft');
    const elTrash = document.getElementById('tabCountJourneyTrash');

    if (elAll) elAll.textContent = allCount;
    if (elPub) elPub.textContent = publishedCount;
    if (elDraft) elDraft.textContent = draftCount;
    if (elTrash) elTrash.textContent = trashCount;
}

function filterMilestones(status) {
    currentStatusFilter = status;
    const tabs = document.querySelectorAll('#journeyStatusTabs .filter-tab');
    tabs.forEach(t => {
        t.classList.toggle('active', t.getAttribute('data-status') === status);
        t.setAttribute('aria-selected', t.getAttribute('data-status') === status ? 'true' : 'false');
    });
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('journeyTableBody');
    const emptyState = document.getElementById('journeyEmptyState');
    const table = document.getElementById('journeyTable');
    const countMeta = document.getElementById('journeyResultCount');

    let filtered = [];
    if (currentStatusFilter === 'trash') {
        filtered = trashMilestones;
    } else if (currentStatusFilter === 'all') {
        filtered = allMilestones;
    } else {
        filtered = allMilestones.filter(m => m.status === currentStatusFilter);
    }

    if (countMeta) {
        if (currentStatusFilter === 'trash') {
            countMeta.textContent = `${filtered.length} soft-deleted items in trash`;
        } else {
            countMeta.textContent = `${filtered.length} milestone${filtered.length === 1 ? '' : 's'}`;
        }
    }

    if (filtered.length === 0) {
        if (table) table.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }

    if (table) table.style.display = 'table';
    if (emptyState) emptyState.style.display = 'none';

    tbody.innerHTML = filtered.map(m => {
        if (currentStatusFilter === 'trash') {
            const delDate = m.deleted_at ? new Date(m.deleted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
            return `
                <tr style="opacity: 0.85;">
                    <td>
                        <span style="font-family: var(--font-mono); color: var(--text-muted); font-size: 12.5px;">
                            ${escapeHtml(String(m.sort_order ?? '—'))}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 3px; text-decoration: line-through;">
                            ${escapeHtml(m.title)}
                        </div>
                        <div style="font-size: 11px; color: var(--danger);">
                            <i class="fas fa-trash-can" style="margin-right: 4px;"></i> Deleted: ${delDate}
                        </div>
                    </td>
                    <td>
                        <span style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);">
                            ${escapeHtml(m.period_label || '—')}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-neutral" style="font-size: 11px;">
                            ${escapeHtml(m.category || 'milestone')}
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-danger" style="font-size: 11px; background: rgba(239, 68, 68, 0.15); color: var(--danger);">
                            DELETED
                        </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        <button type="button" class="btn btn-secondary btn-sm" style="margin-right: 4px; padding: 4px 10px; font-size: 12px;" onclick="restoreMilestone(${m.id})">
                            <i class="fas fa-rotate-left"></i> Restore
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" style="padding: 4px 10px; font-size: 12px;"
                            data-action="purge"
                            data-id="${m.id}"
                            data-title="${escapeHtml(m.title)}"
                            title="Permanently Purge">
                            <i class="fas fa-trash-can"></i> Purge
                        </button>
                    </td>
                </tr>
            `;
        }

        const statusClass = m.status === 'published' ? 'status-published' : 'status-draft';
        const editUrl = `journey-edit.php?id=${encodeURIComponent(m.id)}`;

        return `
            <tr>
                <td>
                    <span style="font-family: var(--font-mono); color: var(--text-muted); font-size: 12.5px;">
                        ${escapeHtml(String(m.sort_order))}
                    </span>
                </td>
                <td>
                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 3px;">
                        <a href="${editUrl}" style="color: inherit; text-decoration: none;" class="table-row-title">
                            ${escapeHtml(m.title)}
                        </a>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); max-width: 480px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${escapeHtml(m.description || '')}
                    </div>
                </td>
                <td>
                    <span style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);">
                        ${escapeHtml(m.period_label)}
                    </span>
                </td>
                <td>
                    <span class="badge badge-neutral" style="font-size: 11px;">
                        ${escapeHtml(m.category)}
                    </span>
                </td>
                <td>
                    <span class="status-badge ${statusClass}" style="font-size: 11px;">
                        ${escapeHtml((m.status || 'draft').toUpperCase())}
                    </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <a href="${editUrl}" class="btn btn-secondary btn-sm" style="margin-right: 4px; padding: 4px 10px; font-size: 12px;">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    <button type="button" class="btn btn-icon btn-sm"
                        data-action="delete"
                        data-id="${m.id}"
                        data-title="${escapeHtml(m.title)}"
                        title="Delete Milestone" style="color: var(--accent-red);">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// ---- Delete Modal ------------------------------------------------------
function openDeleteMilestoneModal(id, title) {
    pendingDeleteId = id;
    document.getElementById('deleteMilestoneTitleDisplay').textContent = title;
    document.getElementById('deleteMilestoneModal').style.display = 'flex';
}

function closeDeleteMilestoneModal() {
    pendingDeleteId = null;
    document.getElementById('deleteMilestoneModal').style.display = 'none';
}

async function executeDeleteMilestone() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('confirmDeleteMilestoneBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    try {
        const res = await fetch('/api/journey/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: pendingDeleteId })
        });

        const data = await res.json();
        if (data.success) {
            closeDeleteMilestoneModal();
            if (typeof showToast === 'function') showToast('Milestone deleted.', 'success');
            loadMilestones();
        } else {
            alert('Failed to delete milestone: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while deleting milestone.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Milestone';
    }
}

// ---- Restore from Trash ------------------------------------------------
async function restoreMilestone(id) {
    try {
        const res = await fetch('/api/journey/restore.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        const data = await res.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast('Milestone restored.', 'success');
            loadMilestones();
        } else {
            alert('Failed to restore milestone: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while restoring milestone.');
    }
}

// ---- Permanently Purge -------------------------------------------------
async function purgeMilestone(id, title) {
    const confirmed = window.confirm(`Permanently purge "${title}"?\n\nThis cannot be undone.`);
    if (!confirmed) return;

    try {
        const res = await fetch('/api/journey/purge.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        const data = await res.json();
        if (data.success) {
            if (typeof showToast === 'function') showToast('Milestone permanently purged.', 'info');
            loadMilestones();
        } else {
            alert('Failed to purge milestone: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while purging milestone.');
    }
}
