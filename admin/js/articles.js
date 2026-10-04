// ============================================================
// ADMIN — ARTICLES DIRECTORY & MANAGEMENT (articles.php)
// admin/js/articles.js
//
// Directory controller for managing technical articles:
//   - Filter by status tabs (All, Published, Draft, Hidden)
//   - Search by keyword with 300ms debounce
//   - Filter by category & sort order
//   - Pagination controls
//   - Direct publish/hide quick status actions
//   - Soft-delete to Trash and Trash management (restore, purge)
//   - Category Management Modal (create, rename, delete/reassign)
//   - Row navigation links to articles-edit.php
// ============================================================

let currentArticlePage = 1;
let currentArticlesTrashOpen = false;

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

    await loadArticles(1);
    await loadArticleCategories();

    // Search input debounce listener
    const articleSearchInput = document.getElementById('articleSearchInput');
    if (articleSearchInput) {
        let articleSearchTimer = null;
        articleSearchInput.addEventListener('input', function () {
            clearTimeout(articleSearchTimer);
            articleSearchTimer = setTimeout(() => {
                loadArticles(1);
            }, 300);
        });
        articleSearchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(articleSearchTimer);
                loadArticles(1);
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
async function loadArticleCategories() {
    const categoryFilter = document.getElementById('articleCategoryFilter');
    if (!categoryFilter) return;

    try {
        const response = await fetch('/api/posts/categories.php?type=blog', {
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
function setArticleStatusTab(status) {
    const statusFilterEl = document.getElementById('articleStatusFilter');
    if (statusFilterEl) {
        statusFilterEl.value = status;
    }

    document.querySelectorAll('#articleStatusTabs .filter-tab').forEach(tab => {
        const isSelected = tab.getAttribute('data-status') === status;
        tab.classList.toggle('active', isSelected);
        tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });

    loadArticles(1);
}

async function updateArticleTabCounts() {
    try {
        const res = await fetch('/api/posts/list.php?type=blog&limit=100&status=all', { credentials: 'same-origin' });
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) {
            const allPosts = json.data;
            const countAll = allPosts.length;
            const countPublished = allPosts.filter(p => p.status === 'published').length;
            const countDraft = allPosts.filter(p => p.status === 'draft').length;
            const countHidden = allPosts.filter(p => p.status === 'hidden').length;

            const cAll = document.getElementById('tabCountArticlesAll');
            const cPub = document.getElementById('tabCountArticlesPublished');
            const cDraft = document.getElementById('tabCountArticlesDraft');
            const cHidden = document.getElementById('tabCountArticlesHidden');

            if (cAll) cAll.textContent = countAll;
            if (cPub) cPub.textContent = countPublished;
            if (cDraft) cDraft.textContent = countDraft;
            if (cHidden) cHidden.textContent = countHidden;

            // Also update overview rail if present
            const ovTotal = document.getElementById('overviewTotalArticles');
            const ovPub = document.getElementById('overviewPublishedArticles');
            const ovDraft = document.getElementById('overviewDraftArticles');
            if (ovTotal) ovTotal.textContent = countAll;
            if (ovPub) ovPub.textContent = countPublished;
            if (ovDraft) ovDraft.textContent = countDraft;
        }
    } catch (e) {
        // Tab counts background fetch - fails silently
    }
}

// ============================================================
// LOAD ARTICLES DIRECTORY TABLE
// ============================================================
async function loadArticles(targetPage = null) {
    if (typeof targetPage === 'number' && targetPage >= 1) {
        currentArticlePage = targetPage;
    }

    const tbody = document.getElementById('postsTableBody');
    if (!tbody) return;

    if (typeof renderSkeletonRows === 'function') {
        renderSkeletonRows(tbody, 5);
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading articles...</td></tr>';
    }

    try {
        const searchInputEl = document.getElementById('articleSearchInput');
        const categoryFilterEl = document.getElementById('articleCategoryFilter');
        const statusFilterEl = document.getElementById('articleStatusFilter');
        const sortFilterEl = document.getElementById('articleSortFilter');
        const resultCountEl = document.getElementById('articleResultCount');
        const paginationEl = document.getElementById('articlePagination');

        const searchVal = searchInputEl ? searchInputEl.value.trim().slice(0, 100) : '';
        const categoryVal = categoryFilterEl ? categoryFilterEl.value.trim() : '';
        const statusVal = (statusFilterEl && statusFilterEl.value) ? statusFilterEl.value : 'all';
        const sortVal = (sortFilterEl && sortFilterEl.value) ? sortFilterEl.value : 'newest';
        const pageVal = currentArticlePage || 1;

        // Background update tab count pills
        updateArticleTabCounts();

        const queryParams = new URLSearchParams({
            type: 'blog',
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
            throw new Error(result.message || 'Failed to load articles.');
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
            resultCountEl.textContent = `${pagination.total} article${pagination.total === 1 ? '' : 's'}`;
            resultCountEl.style.display = 'inline-block';
        }

        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            const hasFilters = Boolean(searchVal || categoryVal || statusVal !== 'all' || sortVal !== 'newest');
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 56px 20px; color: var(--text-muted);">
                        <i class="${hasFilters ? 'fas fa-search' : 'far fa-newspaper'}" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--text-muted);"></i>
                        <h4 style="font-size: 15px; color: var(--text-primary); margin-bottom: 6px;">
                            ${hasFilters ? 'No articles match your search or filters' : 'No articles yet'}
                        </h4>
                        <p style="font-size: 13px; max-width: 420px; margin: 0 auto 16px;">
                            ${hasFilters ? 'Try adjusting your search keywords or resetting filters.' : 'Draft and publish your first technical article or engineering write-up.'}
                        </p>
                        ${hasFilters
                    ? '<button type="button" class="btn btn-secondary btn-sm" onclick="resetArticleFilters()"><i class="fas fa-undo"></i> Reset Filters</button>'
                    : '<a href="articles-edit.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create First Article</a>'}
                    </td>
                </tr>
            `;

            if (paginationEl) {
                paginationEl.style.display = 'none';
                paginationEl.innerHTML = '';
            }
            return;
        }

        data.forEach(post => {
            const tr = document.createElement('tr');
            const title = escapeHTML(post.title || 'Untitled');
            const category = escapeHTML(post.category || '—');

            let statusLabel = 'Draft';
            let statusClass = 'status-badge status-warning';
            if (post.status === 'published') {
                statusLabel = 'Published';
                statusClass = 'status-badge status-success';
            } else if (post.status === 'hidden') {
                statusLabel = 'Hidden';
                statusClass = 'status-badge status-neutral';
            }

            const formattedDate = post.created_at
                ? new Date(post.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                : '—';

            let statusActionHtml = '';
            if (post.status === 'draft') {
                statusActionHtml = `
                    <button class="btn-action btn-publish" onclick="publishPostDirectly(${post.id})" style="color: var(--success, #28a745);">
                        <i class="fas fa-paper-plane"></i> Publish
                    </button>
                `;
            } else if (post.status === 'published') {
                statusActionHtml = `
                    <button class="btn-action btn-hide" onclick="hidePostDirectly(${post.id})">
                        <i class="fas fa-eye-slash"></i> Hide
                    </button>
                `;
            } else if (post.status === 'hidden') {
                statusActionHtml = `
                    <button class="btn-action btn-show" onclick="publishPostDirectly(${post.id})">
                        <i class="fas fa-eye"></i> Show
                    </button>
                `;
            }

            const editUrl = `articles-edit.php?id=${encodeURIComponent(post.id)}`;

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
                    <button class="btn-action btn-delete" onclick="deletePost(${post.id})" style="color: var(--danger);">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
        });

        renderAdminArticlesPagination(pagination);

    } catch (err) {
        console.error('Articles load error:', err);
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger); padding: 32px;">Failed to load articles: ${escapeHTML(err.message)}</td></tr>`;
    }
}

function renderAdminArticlesPagination(pagination) {
    const container = document.getElementById('articlePagination');
    if (!container) return;

    if (!pagination || pagination.total_pages <= 1) {
        container.style.display = 'none';
        container.innerHTML = '';
        return;
    }

    container.style.display = 'flex';
    container.innerHTML = `
        <button type="button" class="btn btn-secondary btn-sm" ${!pagination.has_prev ? 'disabled' : ''} onclick="loadArticles(${pagination.page - 1})">
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <span style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono); margin: 0 10px;">
            Page ${pagination.page} of ${pagination.total_pages}
        </span>
        <button type="button" class="btn btn-secondary btn-sm" ${!pagination.has_next ? 'disabled' : ''} onclick="loadArticles(${pagination.page + 1})">
            Next <i class="fas fa-chevron-right"></i>
        </button>
    `;
}

function resetArticleFilters() {
    const search = document.getElementById('articleSearchInput');
    const cat = document.getElementById('articleCategoryFilter');
    const status = document.getElementById('articleStatusFilter');
    const sort = document.getElementById('articleSortFilter');

    if (search) search.value = '';
    if (cat) cat.value = '';
    if (status) status.value = 'all';
    if (sort) sort.value = 'newest';

    document.querySelectorAll('#articleStatusTabs .filter-tab').forEach(t => {
        const isAll = t.getAttribute('data-status') === 'all';
        t.classList.toggle('active', isAll);
        t.setAttribute('aria-selected', isAll ? 'true' : 'false');
    });

    loadArticles(1);
}

// ============================================================
// DIRECT PUBLISH / HIDE / DELETE ACTIONS
// ============================================================
async function publishPostDirectly(id) {
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
            if (typeof showToast === 'function') showToast('Article published successfully!', 'success');
            loadArticles();
        } else {
            alert(json.message || 'Failed to publish article.');
        }
    } catch (e) {
        alert('Network error while publishing article.');
    }
}

async function hidePostDirectly(id) {
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
            if (typeof showToast === 'function') showToast('Article hidden.', 'info');
            loadArticles();
        } else {
            alert(json.message || 'Failed to hide article.');
        }
    } catch (e) {
        alert('Network error while hiding article.');
    }
}

async function deletePost(id) {
    const confirmed = confirm('Move this article to Trash? It can be restored later from the Articles Trash panel.');
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
            if (typeof showToast === 'function') showToast('Article moved to trash.', 'success');
            loadArticles();
            if (currentArticlesTrashOpen) loadArticlesTrash();
        } else {
            alert(json.message || 'Failed to delete article.');
        }
    } catch (e) {
        alert('Network error while deleting article.');
    }
}

// ============================================================
// TRASH MANAGEMENT (REQ-005)
// ============================================================
function toggleArticlesTrash() {
    const content = document.getElementById('articlesTrashContent');
    const chevron = document.getElementById('trashChevronIcon');
    if (!content) return;

    currentArticlesTrashOpen = !currentArticlesTrashOpen;
    content.style.display = currentArticlesTrashOpen ? 'block' : 'none';
    if (chevron) {
        chevron.className = currentArticlesTrashOpen ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }

    if (currentArticlesTrashOpen) {
        loadArticlesTrash();
    }
}

async function loadArticlesTrash() {
    const tbody = document.getElementById('articlesTrashTableBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 20px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading trash...</td></tr>';

    try {
        const res = await fetch('/api/posts/trash.php?type=blog', { credentials: 'same-origin' });
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
                    <button type="button" class="btn btn-secondary btn-sm" onclick="restorePost(${p.id})" style="padding: 2px 8px; font-size: 11px; margin-right: 4px;">
                        <i class="fas fa-rotate-left"></i> Restore
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="purgePost(${p.id})" style="padding: 2px 8px; font-size: 11px;">
                        <i class="fas fa-trash-can"></i> Purge
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--danger); padding: 16px;">Error loading trash.</td></tr>';
    }
}

async function restorePost(id) {
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
            if (typeof showToast === 'function') showToast('Article restored successfully!', 'success');
            loadArticles();
            loadArticlesTrash();
        } else {
            alert(json.message || 'Failed to restore article.');
        }
    } catch (e) {
        alert('Network error while restoring article.');
    }
}

async function purgePost(id) {
    const confirmed = confirm('PERMANENTLY purge this article? This action CANNOT be undone.');
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
            if (typeof showToast === 'function') showToast('Article permanently purged.', 'info');
            loadArticlesTrash();
        } else {
            alert(json.message || 'Failed to purge article.');
        }
    } catch (e) {
        alert('Network error while purging article.');
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
