// ============================================================
// ADMIN — ENGINEERING PROJECTS & SYSTEMS DIRECTORY (projects.php)
// admin/js/projects.js
//
// Directory controller for managing engineering projects:
//   - Filter by status tabs (All, Published, Draft, Hidden)
//   - Search by keyword with 300ms debounce
//   - Filter by category & sort order
//   - Pagination controls
//   - Direct publish/hide quick status actions
//   - Soft-delete to Trash and Trash management (restore, purge)
//   - Project Category Management Modal (create, rename, delete/reassign)
//   - Row navigation links to projects-edit.php
// ============================================================

let currentAchievePage = 1;
let currentAchieveTrashOpen = false;

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

    await loadAchievements(1);
    await loadAchievementCategories();

    // Search input debounce listener
    const searchInput = document.getElementById('achieveSearchInput');
    if (searchInput) {
        let searchTimer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadAchievements(1);
            }, 300);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimer);
                loadAchievements(1);
            }
        });
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
// LOAD CATEGORIES FOR FILTER DROPDOWN
// ============================================================
async function loadAchievementCategories() {
    const categoryFilter = document.getElementById('achieveCategoryFilter');
    if (!categoryFilter) return;

    try {
        const response = await fetch('/api/posts/categories.php?type=project', {
            method: 'GET',
            credentials: 'same-origin',
        });

        if (!response.ok) return;
        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) return;

        const currentVal = categoryFilter.value;
        categoryFilter.innerHTML = '<option value="">All Categories</option>' +
            result.data.map(cat => `<option value="${escapeHTML(cat)}">${escapeHTML(cat)}</option>`).join('');

        if (currentVal) categoryFilter.value = currentVal;
    } catch (err) {
        console.warn('Could not load categories for filter:', err);
    }
}

// ============================================================
// STATUS TABS & COUNTERS
// ============================================================
function setAchieveStatusTab(status) {
    const statusFilterEl = document.getElementById('achieveStatusFilter');
    if (statusFilterEl) {
        statusFilterEl.value = status;
    }

    document.querySelectorAll('#achieveStatusTabs .filter-tab').forEach(tab => {
        const isSelected = tab.getAttribute('data-status') === status;
        tab.classList.toggle('active', isSelected);
        tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });

    loadAchievements(1);
}

async function updateAchieveTabCounts() {
    try {
        const res = await fetch('/api/posts/list.php?type=project&limit=100&status=all', { credentials: 'same-origin' });
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) {
            const allProjects = json.data;
            const countAll = allProjects.length;
            const countPublished = allProjects.filter(p => p.status === 'published').length;
            const countDraft = allProjects.filter(p => p.status === 'draft').length;
            const countHidden = allProjects.filter(p => p.status === 'hidden').length;

            const cAll = document.getElementById('tabCountAchieveAll');
            const cPub = document.getElementById('tabCountAchievePublished');
            const cDraft = document.getElementById('tabCountAchieveDraft');
            const cHidden = document.getElementById('tabCountAchieveHidden');

            if (cAll) cAll.textContent = countAll;
            if (cPub) cPub.textContent = countPublished;
            if (cDraft) cDraft.textContent = countDraft;
            if (cHidden) cHidden.textContent = countHidden;

            // Update overview rail
            const ovTotal = document.getElementById('overviewTotalProjects');
            const ovPub = document.getElementById('overviewPublishedProjects');
            const ovDraft = document.getElementById('overviewDraftProjects');
            if (ovTotal) ovTotal.textContent = countAll;
            if (ovPub) ovPub.textContent = countPublished;
            if (ovDraft) ovDraft.textContent = countDraft;
        }
    } catch (e) {
        // Tab counts background fetch - fails silently
    }
}

// ============================================================
// LOAD PROJECTS DIRECTORY TABLE
// ============================================================
async function loadAchievements(targetPage = null) {
    if (typeof targetPage === 'number' && targetPage >= 1) {
        currentAchievePage = targetPage;
    }

    const tbody = document.getElementById('achievementsTableBody');
    if (!tbody) return;

    if (typeof renderSkeletonRows === 'function') {
        renderSkeletonRows(tbody, 5);
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading projects...</td></tr>';
    }

    try {
        const searchInputEl = document.getElementById('achieveSearchInput');
        const categoryFilterEl = document.getElementById('achieveCategoryFilter');
        const statusFilterEl = document.getElementById('achieveStatusFilter');
        const sortFilterEl = document.getElementById('achieveSortFilter');
        const resultCountEl = document.getElementById('achieveResultCount');
        const paginationEl = document.getElementById('achievePagination');

        const searchVal = searchInputEl ? searchInputEl.value.trim().slice(0, 100) : '';
        const categoryVal = categoryFilterEl ? categoryFilterEl.value.trim() : '';
        const statusVal = (statusFilterEl && statusFilterEl.value) ? statusFilterEl.value : 'all';
        const sortVal = (sortFilterEl && sortFilterEl.value) ? sortFilterEl.value : 'newest';
        const pageVal = currentAchievePage || 1;

        // Background update tab count pills
        updateAchieveTabCounts();

        const queryParams = new URLSearchParams({
            type: 'project',
            limit: '15',
            page: pageVal.toString(),
            status: statusVal,
            sort: sortVal,
        });

        if (searchVal) queryParams.set('search', searchVal);
        if (categoryVal) queryParams.set('category', categoryVal);

        const response = await fetch(`/api/posts/list.php?${queryParams.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
        });

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to load projects.');
        }

        const data = result.data || [];
        const pagination = result.pagination || {
            total: data.length,
            page: pageVal,
            limit: 15,
            total_pages: 1,
            has_prev: false,
            has_next: false,
        };

        if (resultCountEl) {
            resultCountEl.textContent = `${pagination.total} project${pagination.total === 1 ? '' : 's'}`;
            resultCountEl.style.display = 'inline-block';
        }

        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            const hasFilters = Boolean(searchVal || categoryVal || statusVal !== 'all' || sortVal !== 'newest');
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 56px 20px; color: var(--text-muted);">
                        <i class="${hasFilters ? 'fas fa-search' : 'fas fa-code-branch'}" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--text-muted);"></i>
                        <h4 style="font-size: 15px; color: var(--text-primary); margin-bottom: 6px;">
                            ${hasFilters ? 'No projects match your search or filters' : 'No projects yet'}
                        </h4>
                        <p style="font-size: 13px; max-width: 420px; margin: 0 auto 16px;">
                            ${hasFilters ? 'Try adjusting your search keywords or resetting filters.' : 'Document and showcase your systems architecture and portfolio builds.'}
                        </p>
                        ${hasFilters
                    ? '<button type="button" class="btn btn-secondary btn-sm" onclick="resetAchievementFilters()"><i class="fas fa-undo"></i> Reset Filters</button>'
                    : '<a href="projects-edit.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create First Project</a>'}
                    </td>
                </tr>
            `;

            if (paginationEl) {
                paginationEl.style.display = 'none';
                paginationEl.innerHTML = '';
            }
            return;
        }

        data.forEach(proj => {
            const tr = document.createElement('tr');
            const title = escapeHTML(proj.title || 'Untitled Project');
            const category = escapeHTML(proj.category || '—');

            let statusLabel = 'Draft';
            let statusClass = 'status-badge status-warning';
            if (proj.status === 'published') {
                statusLabel = 'Published';
                statusClass = 'status-badge status-success';
            } else if (proj.status === 'hidden') {
                statusLabel = 'Hidden';
                statusClass = 'status-badge status-neutral';
            }

            const formattedDate = proj.created_at
                ? new Date(proj.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                : '—';

            let statusActionHtml = '';
            if (proj.status === 'draft') {
                statusActionHtml = `
                    <button class="btn-action btn-publish" onclick="publishAchievementDirectly(${proj.id})" style="color: var(--success, #28a745);">
                        <i class="fas fa-paper-plane"></i> Publish
                    </button>
                `;
            } else if (proj.status === 'published') {
                statusActionHtml = `
                    <button class="btn-action btn-hide" onclick="hideAchievementDirectly(${proj.id})">
                        <i class="fas fa-eye-slash"></i> Hide
                    </button>
                `;
            } else if (proj.status === 'hidden') {
                statusActionHtml = `
                    <button class="btn-action btn-show" onclick="publishAchievementDirectly(${proj.id})">
                        <i class="fas fa-eye"></i> Show
                    </button>
                `;
            }

            const editUrl = `projects-edit.php?id=${encodeURIComponent(proj.id)}`;

            tr.innerHTML = `
                <td class="table-cell-strong">
                    <a href="${editUrl}" style="color: inherit; text-decoration: none;" class="table-row-title">
                        ${title}
                    </a>
                </td>
                <td class="table-cell-muted">
                    <span class="badge badge-neutral" style="font-size: 11px;">${category}</span>
                </td>
                <td style="font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);">
                    ${formattedDate}
                </td>
                <td style="text-align: center;">
                    <span class="${statusClass}">
                        ${statusLabel}
                    </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <a href="${editUrl}" class="btn-action btn-edit" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    ${statusActionHtml}
                    <button class="btn-action btn-delete" onclick="deleteAchievement(${proj.id})" style="color: var(--danger);">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
        });

        renderAdminAchievementsPagination(pagination);

    } catch (err) {
        console.error('Projects load error:', err);
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger); padding: 32px;">Failed to load projects: ${escapeHTML(err.message)}</td></tr>`;
    }
}

function renderAdminAchievementsPagination(pagination) {
    const container = document.getElementById('achievePagination');
    if (!container) return;

    if (!pagination || pagination.total_pages <= 1) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    container.style.display = 'flex';
    container.innerHTML = `
        <button type="button" class="btn btn-secondary btn-sm" ${!pagination.has_prev ? 'disabled' : ''} onclick="loadAchievements(${pagination.page - 1})">
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <span style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono); margin: 0 10px;">
            Page ${pagination.page} of ${pagination.total_pages}
        </span>
        <button type="button" class="btn btn-secondary btn-sm" ${!pagination.has_next ? 'disabled' : ''} onclick="loadAchievements(${pagination.page + 1})">
            Next <i class="fas fa-chevron-right"></i>
        </button>
    `;
}

function resetAchievementFilters() {
    const search = document.getElementById('achieveSearchInput');
    const cat = document.getElementById('achieveCategoryFilter');
    const status = document.getElementById('achieveStatusFilter');
    const sort = document.getElementById('achieveSortFilter');

    if (search) search.value = '';
    if (cat) cat.value = '';
    if (status) status.value = 'all';
    if (sort) sort.value = 'newest';

    document.querySelectorAll('#achieveStatusTabs .filter-tab').forEach(t => {
        const isAll = t.getAttribute('data-status') === 'all';
        t.classList.toggle('active', isAll);
        t.setAttribute('aria-selected', isAll ? 'true' : 'false');
    });

    loadAchievements(1);
}

function resetAchieveFilters() {
    resetAchievementFilters();
}

// ============================================================
// DIRECT PUBLISH / HIDE / DELETE ACTIONS
// ============================================================
async function publishAchievementDirectly(id) {
    try {
        const res = await fetch('/api/posts/publish.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') showToast('Project published successfully!', 'success');
            loadAchievements();
        } else {
            alert(json.message || 'Failed to publish project.');
        }
    } catch (e) {
        alert('Network error while publishing project.');
    }
}

async function hideAchievementDirectly(id) {
    try {
        const res = await fetch('/api/posts/hide.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') showToast('Project hidden.', 'info');
            loadAchievements();
        } else {
            alert(json.message || 'Failed to hide project.');
        }
    } catch (e) {
        alert('Network error while hiding project.');
    }
}

async function deleteAchievement(id) {
    const confirmed = confirm('Move this project to Trash? It can be restored later from the Projects Trash panel.');
    if (!confirmed) return;

    try {
        const res = await fetch('/api/posts/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') showToast('Project moved to trash.', 'success');
            loadAchievements();
            if (currentAchieveTrashOpen) loadAchievementsTrash();
        } else {
            alert(json.message || 'Failed to delete project.');
        }
    } catch (e) {
        alert('Network error while deleting project.');
    }
}

// ============================================================
// TRASH MANAGEMENT (REQ-005)
// ============================================================
function toggleAchievementsTrash() {
    const content = document.getElementById('achievementsTrashContent');
    const chevron = document.getElementById('achieveTrashChevronIcon');
    if (!content) return;

    currentAchieveTrashOpen = !currentAchieveTrashOpen;
    content.style.display = currentAchieveTrashOpen ? 'block' : 'none';
    if (chevron) {
        chevron.className = currentAchieveTrashOpen ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }

    if (currentAchieveTrashOpen) {
        loadAchievementsTrash();
    }
}

async function loadAchievementsTrash() {
    const tbody = document.getElementById('achievementsTrashTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 20px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading trash...</td></tr>';

    try {
        const res = await fetch('/api/posts/trash.php?type=project', { credentials: 'same-origin' });
        const json = await res.json();

        if (!json.success || !Array.isArray(json.data)) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger); padding: 16px;">Failed to load trash.</td></tr>';
            return;
        }

        if (json.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--text-muted); padding: 24px;">Trash is empty.</td></tr>';
            return;
        }

        tbody.innerHTML = json.data.map(p => `
            <tr>
                <td style="font-weight: 600; text-decoration: line-through; opacity: 0.85;">${escapeHTML(p.title || 'Untitled')}</td>
                <td style="font-size: 12px; color: var(--danger); font-family: var(--font-mono);">
                    ${p.deleted_at ? new Date(p.deleted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Deleted'}
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="restoreAchievement(${p.id})" style="padding: 2px 8px; font-size: 11px; margin-right: 4px;">
                        <i class="fas fa-rotate-left"></i> Restore
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="purgeAchievement(${p.id})" style="padding: 2px 8px; font-size: 11px;">
                        <i class="fas fa-trash-can"></i> Purge
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger); padding: 16px;">Error loading trash.</td></tr>';
    }
}

async function restoreAchievement(id) {
    try {
        const res = await fetch('/api/posts/restore.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') showToast('Project restored successfully!', 'success');
            loadAchievements();
            loadAchievementsTrash();
        } else {
            alert(json.message || 'Failed to restore project.');
        }
    } catch (e) {
        alert('Network error while restoring project.');
    }
}

async function purgeAchievement(id) {
    const confirmed = confirm('PERMANENTLY purge this project? This action CANNOT be undone.');
    if (!confirmed) return;

    try {
        const res = await fetch('/api/posts/purge.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (json.success) {
            if (typeof showToast === 'function') showToast('Project permanently purged.', 'info');
            loadAchievementsTrash();
        } else {
            alert(json.message || 'Failed to purge project.');
        }
    } catch (e) {
        alert('Network error while purging project.');
    }
}

// ============================================================
// PROJECT CATEGORY MODAL LOGIC (REQ-002)
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
