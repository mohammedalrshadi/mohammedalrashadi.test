// ============================================================
// ADMIN LABS — labs.js
// Client-side controller for the Engineering Labs Admin Studio.
// ============================================================

let allExperiments = [];
let currentStatusFilter = 'all';
let pendingDeleteId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadLabs();

    // SEC-003: delegated handler for table action buttons.
    // Buttons use data-action + data-id attributes instead of inline onclick.
    const labsBody = document.getElementById('labsTableBody');
    if (labsBody) {
        labsBody.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            const labId  = btn.dataset.id;
            if (action === 'delete') {
                openDeleteModal(labId);
            }
        });
    }
});

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function loadLabs() {
    const tbody = document.getElementById('labsTableBody');
    const emptyState = document.getElementById('labsEmptyState');
    const table = document.getElementById('labsTable');

    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading experiments...</td></tr>';

    try {
        const res = await fetch('/api/labs/list.php', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--accent-red); padding: 24px;">Error: ${escapeHtml(data.message || 'Failed to load labs')}</td></tr>`;
            return;
        }

        allExperiments = data.experiments || [];
        updateCategoryOptions();
        updateTabCounters();
        renderTable();

    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--accent-red); padding: 24px;">Failed to connect to Labs API.</td></tr>';
    }
}

function updateCategoryOptions() {
    const select = document.getElementById('labCategoryFilter');
    if (!select) return;

    const categories = new Set();
    allExperiments.forEach(exp => {
        if (exp.category) categories.add(exp.category);
    });

    const currentVal = select.value;
    select.innerHTML = '<option value="">All Categories</option>';
    categories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat;
        opt.textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
        select.appendChild(opt);
    });
    if (categories.has(currentVal)) {
        select.value = currentVal;
    }
}

function updateTabCounters() {
    const allCount = allExperiments.length;
    let concludedCount = 0;
    let activeCount = 0;
    let progressCount = 0;

    allExperiments.forEach(exp => {
        const s = (exp.status || '').toLowerCase();
        if (s.includes('concluded') || s.includes('verified')) concludedCount++;
        else if (s === 'active') activeCount++;
        else if (s.includes('progress') || s.includes('draft')) progressCount++;
    });

    document.getElementById('tabCountLabsAll').textContent = allCount;
    document.getElementById('tabCountLabsConcluded').textContent = concludedCount;
    document.getElementById('tabCountLabsActive').textContent = activeCount;
    document.getElementById('tabCountLabsProgress').textContent = progressCount;
}

function filterLabs(status) {
    currentStatusFilter = status;
    const tabs = document.querySelectorAll('#labStatusTabs .filter-tab');
    tabs.forEach(t => {
        t.classList.toggle('active', t.getAttribute('data-status') === status);
        t.setAttribute('aria-selected', t.getAttribute('data-status') === status ? 'true' : 'false');
    });
    renderTable();
}

function searchLabs() {
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('labsTableBody');
    const emptyState = document.getElementById('labsEmptyState');
    const table = document.getElementById('labsTable');
    const countMeta = document.getElementById('labResultCount');

    const searchInput = document.getElementById('labSearchInput');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const catSelect = document.getElementById('labCategoryFilter');
    const catFilter = catSelect ? catSelect.value.toLowerCase() : '';

    const filtered = allExperiments.filter(exp => {
        // Status filter
        if (currentStatusFilter !== 'all') {
            const s = (exp.status || '').toLowerCase();
            if (currentStatusFilter === 'verified & concluded' && !(s.includes('concluded') || s.includes('verified'))) {
                return false;
            }
            if (currentStatusFilter === 'active' && s !== 'active') {
                return false;
            }
            if (currentStatusFilter === 'in_progress' && !(s.includes('progress') || s.includes('draft'))) {
                return false;
            }
        }

        // Category filter
        if (catFilter && (exp.category || '').toLowerCase() !== catFilter) {
            return false;
        }

        // Query filter
        if (query) {
            const searchStr = `${exp.id} ${exp.title} ${exp.question} ${exp.hypothesis} ${exp.category} ${exp.categoryLabel}`.toLowerCase();
            if (!searchStr.includes(query)) return false;
        }

        return true;
    });

    if (countMeta) {
        countMeta.textContent = `${filtered.length} of ${allExperiments.length} experiments`;
    }

    if (filtered.length === 0) {
        table.style.display = 'none';
        emptyState.style.display = 'block';
        return;
    }

    table.style.display = 'table';
    emptyState.style.display = 'none';

    tbody.innerHTML = filtered.map(exp => {
        const statusClass = getStatusBadgeClass(exp.status);
        const detailUrl = `/lab-detail.php?id=${encodeURIComponent(exp.id)}`;
        const editUrl = `lab-edit.php?id=${encodeURIComponent(exp.id)}`;

        return `
            <tr>
                <td>
                    <span style="font-family: var(--font-mono); font-weight: 700; color: var(--accent); font-size: 12.5px;">
                        ${escapeHtml(exp.id)}
                    </span>
                </td>
                <td>
                    <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 3px;">
                        <a href="${editUrl}" style="color: inherit; text-decoration: none;" class="table-row-title">
                            ${escapeHtml(exp.title)}
                        </a>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${escapeHtml(exp.question || exp.outcomeHeadline || '')}
                    </div>
                </td>
                <td>
                    <span class="badge badge-neutral" style="font-size: 11px;">
                        ${escapeHtml(exp.categoryLabel || exp.category || 'Systems')}
                    </span>
                </td>
                <td>
                    <span class="status-badge ${statusClass}" style="font-size: 11px;">
                        ${escapeHtml(exp.status || 'ACTIVE')}
                    </span>
                </td>
                <td>
                    <span style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);">
                        ${escapeHtml(exp.readTime || '5 min')}
                    </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <a href="${detailUrl}" target="_blank" class="btn btn-icon btn-sm" title="View Public Spec" style="margin-right: 4px; color: var(--text-muted);">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <a href="${editUrl}" class="btn btn-secondary btn-sm" style="margin-right: 4px; padding: 4px 10px; font-size: 12px;">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    <button type="button" class="btn btn-icon btn-sm"
                        data-action="delete"
                        data-id="${escapeHtml(exp.id)}"
                        title="Delete Experiment" style="color: var(--accent-red);">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s.includes('concluded') || s.includes('verified')) return 'status-published';
    if (s === 'active') return 'status-active';
    if (s.includes('progress') || s.includes('draft')) return 'status-draft';
    return 'status-neutral';
}

function openDeleteModal(id) {
    pendingDeleteId = id;
    document.getElementById('deleteLabIdDisplay').textContent = id;
    document.getElementById('deleteLabModal').style.display = 'flex';
}

function closeDeleteModal() {
    pendingDeleteId = null;
    document.getElementById('deleteLabModal').style.display = 'none';
}

async function executeDeleteLab() {
    if (!pendingDeleteId) return;

    const btn = document.getElementById('confirmDeleteLabBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    try {
        const res = await fetch('/api/labs/delete.php', {
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
            closeDeleteModal();
            showToast('Experiment deleted successfully', 'success');
            loadLabs();
        } else {
            alert('Failed to delete experiment: ' + (data.message || 'Unknown error'));
        }
    } catch (err) {
        alert('Network error while deleting experiment.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Experiment';
    }
}

function showToast(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notice toast-${type}`;
    toast.style.cssText = 'position: fixed; bottom: 24px; right: 24px; z-index: 9999; padding: 12px 20px; background: var(--bg-surface-elevated, #1C2532); border: 1px solid var(--border-medium, rgba(148,163,184,0.16)); border-radius: 8px; color: var(--text-primary, #F1F5F9); font-size: 13px; box-shadow: 0 4px 16px rgba(0,0,0,0.4);';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

