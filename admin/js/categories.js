// ============================================================
// ADMIN CATEGORIES — categories.js
// Client-side controller for Taxonomy & Category Management.
// Supports 4 taxonomy groups: blog, project (projects), lab, product.
// ============================================================

let currentTabType = 'blog';
let categoriesBlog = [];
let categoriesAchievement = [];
let categoriesLab = [];
let categoriesProduct = [];
let categoryPendingDelete = null;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', async () => {
    if (typeof checkAdminAuthentication === 'function') {
        const authenticated = await checkAdminAuthentication();
        if (!authenticated) return;
    }

    // Check URL params for initial tab
    const urlParams = new URLSearchParams(window.location.search);
    const initialType = urlParams.get('type');
    const validTabs = ['blog', 'project', 'lab', 'product'];
    if (initialType && validTabs.includes(initialType)) {
        currentTabType = initialType;
    }

    // Sync select dropdown with active tab
    const typeSelect = document.getElementById('categoryTypeSelect');
    if (typeSelect) {
        typeSelect.value = currentTabType;
        typeSelect.addEventListener('change', (e) => {
            switchCategoryTab(e.target.value);
        });
    }

    // Modal background & Esc listeners
    ['renameCategoryModal', 'deleteCategoryModal'].forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('modal-backdrop')) {
                    if (modalId === 'renameCategoryModal') closeRenameCategoryModal();
                    if (modalId === 'deleteCategoryModal') closeDeleteCategoryModal();
                }
            });
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeRenameCategoryModal();
            closeDeleteCategoryModal();
        }
    });

    updateActiveTabUI();
    await loadCategories();
});

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : (window.csrfToken || '');
}

function updateActiveTabUI() {
    const tabs = document.querySelectorAll('#categoryTypeTabs .filter-tab');
    tabs.forEach(t => {
        const isMatch = t.getAttribute('data-type') === currentTabType;
        t.classList.toggle('active', isMatch);
        t.setAttribute('aria-selected', isMatch ? 'true' : 'false');
    });

    const typeSelect = document.getElementById('categoryTypeSelect');
    if (typeSelect && typeSelect.value !== currentTabType) {
        typeSelect.value = currentTabType;
    }
}

function switchCategoryTab(type) {
    const validTabs = ['blog', 'project', 'lab', 'product'];
    if (!validTabs.includes(type)) return;
    currentTabType = type;
    updateActiveTabUI();

    const url = new URL(window.location.href);
    url.searchParams.set('type', type);
    window.history.replaceState({}, '', url.toString());

    renderCategoriesTable();
}

function focusNewCategoryInput() {
    const input = document.getElementById('categoryNameInput');
    if (input) {
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

async function loadCategories() {
    const tbody = document.getElementById('categoriesTableBody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 32px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading categories...</td></tr>';
    }

    try {
        const [resBlog, resAchieve, resLab, resProd] = await Promise.all([
            fetch('/api/categories/list.php?type=blog', { credentials: 'same-origin' }),
            fetch('/api/categories/list.php?type=project', { credentials: 'same-origin' }),
            fetch('/api/categories/list.php?type=lab', { credentials: 'same-origin' }),
            fetch('/api/categories/list.php?type=product', { credentials: 'same-origin' }),
        ]);

        const dataBlog = await resBlog.json();
        const dataAchieve = await resAchieve.json();
        const dataLab = await resLab.json();
        const dataProd = await resProd.json();

        categoriesBlog = dataBlog.success ? (dataBlog.data || []) : [];
        categoriesAchievement = dataAchieve.success ? (dataAchieve.data || []) : [];
        categoriesLab = dataLab.success ? (dataLab.data || []) : [];
        categoriesProduct = dataProd.success ? (dataProd.data || []) : [];

        updateCounters();
        renderCategoriesTable();

    } catch (err) {
        console.error('Failed to load categories:', err);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--color-error, #FF7A7A); padding: 24px;">Failed to load categories: ${escapeHtml(err.message)}</td></tr>`;
        }
        if (typeof showToast === 'function') {
            showToast('Error loading categories: ' + err.message, 'error');
        }
    }
}

function updateCounters() {
    const countBlog = categoriesBlog.length;
    const countAchieve = categoriesAchievement.length;
    const countLab = categoriesLab.length;
    const countProd = categoriesProduct.length;
    const countTotal = countBlog + countAchieve + countLab + countProd;

    const elTotal = document.getElementById('statTotalCategories');
    if (elTotal) elTotal.textContent = countTotal;

    const elBlog = document.getElementById('statArticleCategories');
    if (elBlog) elBlog.textContent = countBlog;

    const elAchieve = document.getElementById('statProjectCategories');
    if (elAchieve) elAchieve.textContent = countAchieve;

    const elLab = document.getElementById('statLabCategories');
    if (elLab) elLab.textContent = countLab;

    const elProd = document.getElementById('statProductCategories');
    if (elProd) elProd.textContent = countProd;

    const tBlog = document.getElementById('tabCountCatBlog');
    if (tBlog) tBlog.textContent = countBlog;

    const tAchieve = document.getElementById('tabCountCatAchieve');
    if (tAchieve) tAchieve.textContent = countAchieve;

    const tLab = document.getElementById('tabCountCatLab');
    if (tLab) tLab.textContent = countLab;

    const tProd = document.getElementById('tabCountCatProduct');
    if (tProd) tProd.textContent = countProd;
}

function getCurrentList() {
    if (currentTabType === 'project') return categoriesAchievement;
    if (currentTabType === 'lab') return categoriesLab;
    if (currentTabType === 'product') return categoriesProduct;
    return categoriesBlog;
}

function renderCategoriesTable() {
    const list = getCurrentList();
    const tbody = document.getElementById('categoriesTableBody');
    const emptyState = document.getElementById('categoryEmptyState');
    const resultCount = document.getElementById('categoryResultCount');

    if (resultCount) {
        resultCount.textContent = `${list.length} category${list.length === 1 ? '' : 'ies'}`;
    }

    if (!tbody) return;

    if (list.length === 0) {
        tbody.innerHTML = '';
        if (emptyState) emptyState.style.display = 'block';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    let groupBadge = 'Article';
    if (currentTabType === 'project') groupBadge = 'Project';
    if (currentTabType === 'lab') groupBadge = 'Lab';
    if (currentTabType === 'product') groupBadge = 'Store';

    const rows = list.map(c => {
        const id = Number(c.id);
        const name = escapeHtml(c.name);
        const pubCount = Number(c.published_count || 0);
        const draftCount = Number(c.draft_count || 0);
        const totalCount = Number(c.active_posts_count || 0);

        return `
            <tr>
                <td>
                    <strong style="color: var(--text-primary); font-size: 13.5px;">${name}</strong>
                </td>
                <td>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: var(--radius-xs); background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); font-size: 11.5px; color: var(--text-secondary);">
                        ${groupBadge}
                    </span>
                </td>
                <td style="text-align: center;">
                    <span style="font-weight: 600; color: var(--color-success, #4ADE80);">${pubCount}</span>
                </td>
                <td style="text-align: center;">
                    <span style="color: var(--text-muted);">${draftCount}</span>
                </td>
                <td style="text-align: center;">
                    <strong style="color: var(--text-primary);">${totalCount}</strong>
                </td>
                <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 6px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openRenameCategoryModal(${id})" title="Rename Category">
                            <i class="fas fa-edit" aria-hidden="true"></i>
                            <span>Rename</span>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteCategoryModal(${id})" title="Delete Category">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rows.join('');
}

// ============================================================
// CREATE CATEGORY
// ============================================================
async function handleCategoryCreate(event) {
    event.preventDefault();

    const nameInput = document.getElementById('categoryNameInput');
    const typeSelect = document.getElementById('categoryTypeSelect');
    const createBtn = document.getElementById('categoryCreateBtn');

    const name = (nameInput ? nameInput.value : '').trim();
    const type = typeSelect ? typeSelect.value : currentTabType;

    if (!name) return;

    if (createBtn) createBtn.disabled = true;

    try {
        const res = await fetch('/api/categories/create.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
            },
            body: JSON.stringify({ name, type }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to create category.');
        }

        if (nameInput) nameInput.value = '';
        if (typeof showToast === 'function') {
            showToast('Category created successfully.', 'success');
        }

        switchCategoryTab(type);
        await loadCategories();

    } catch (err) {
        console.error('Failed to create category:', err);
        if (typeof showToast === 'function') {
            showToast('Error: ' + err.message, 'error');
        } else {
            alert(err.message);
        }
    } finally {
        if (createBtn) createBtn.disabled = false;
    }
}

// ============================================================
// RENAME CATEGORY MODAL
// ============================================================
function openRenameCategoryModal(id) {
    const list = getCurrentList();
    const cat = list.find(c => Number(c.id) === Number(id));
    if (!cat) return;

    const idInput = document.getElementById('renameCategoryId');
    const typeInput = document.getElementById('renameCategoryType');
    const oldInput = document.getElementById('renameCategoryOldName');
    const newInput = document.getElementById('renameCategoryNewName');
    const modal = document.getElementById('renameCategoryModal');

    if (idInput) idInput.value = cat.id;
    if (typeInput) typeInput.value = cat.type;
    if (oldInput) oldInput.value = cat.name;
    if (newInput) {
        newInput.value = cat.name;
        setTimeout(() => newInput.focus(), 100);
    }

    if (modal) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
}

function closeRenameCategoryModal() {
    const modal = document.getElementById('renameCategoryModal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
}

async function handleCategoryRename(event) {
    event.preventDefault();

    const idInput = document.getElementById('renameCategoryId');
    const newInput = document.getElementById('renameCategoryNewName');
    const submitBtn = document.getElementById('renameCategorySubmitBtn');

    const id = Number(idInput ? idInput.value : 0);
    const newName = (newInput ? newInput.value : '').trim();

    if (!id || !newName) return;

    if (submitBtn) submitBtn.disabled = true;

    try {
        const res = await fetch('/api/categories/rename.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
            },
            body: JSON.stringify({ id, new_name: newName }),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to rename category.');
        }

        closeRenameCategoryModal();
        if (typeof showToast === 'function') {
            showToast('Category renamed successfully.', 'success');
        }

        await loadCategories();

    } catch (err) {
        console.error('Failed to rename category:', err);
        if (typeof showToast === 'function') {
            showToast('Error: ' + err.message, 'error');
        } else {
            alert(err.message);
        }
    } finally {
        if (submitBtn) submitBtn.disabled = false;
    }
}

// ============================================================
// DELETE CATEGORY MODAL (SAFE, PREVENTS ORPHAN ROWS)
// ============================================================
function openDeleteCategoryModal(id) {
    const list = getCurrentList();
    const cat = list.find(c => Number(c.id) === Number(id));
    if (!cat) return;

    categoryPendingDelete = cat;

    const idInput = document.getElementById('deleteCategoryId');
    const typeInput = document.getElementById('deleteCategoryType');
    const nameLabel = document.getElementById('deleteCategoryName');
    const inUseNotice = document.getElementById('deleteCategoryInUseNotice');
    const itemCount = document.getElementById('deleteCategoryItemCount');
    const reassignSelect = document.getElementById('deleteReassignTargetSelect');
    const modal = document.getElementById('deleteCategoryModal');

    if (idInput) idInput.value = cat.id;
    if (typeInput) typeInput.value = cat.type;
    if (nameLabel) nameLabel.textContent = `"${cat.name}"`;

    const count = Number(cat.active_posts_count || 0);

    if (count > 0) {
        if (itemCount) itemCount.textContent = count;
        if (reassignSelect) {
            // Populate replacement options excluding the current category
            const otherCats = list.filter(c => Number(c.id) !== Number(cat.id));
            if (otherCats.length > 0) {
                reassignSelect.innerHTML = '<option value="">Select replacement category…</option>' +
                    otherCats.map(c => `<option value="${escapeHtml(c.name)}">${escapeHtml(c.name)}</option>`).join('');
                reassignSelect.required = true;
            } else {
                reassignSelect.innerHTML = '<option value="">(No alternative category available)</option>';
                reassignSelect.required = false;
            }
        }
        if (inUseNotice) inUseNotice.style.display = 'block';
    } else {
        if (inUseNotice) inUseNotice.style.display = 'none';
        if (reassignSelect) reassignSelect.required = false;
    }

    if (modal) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteCategoryModal() {
    const modal = document.getElementById('deleteCategoryModal');
    if (modal) {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    categoryPendingDelete = null;
}

async function handleCategoryDelete(event) {
    event.preventDefault();

    if (!categoryPendingDelete) return;

    const cat = categoryPendingDelete;
    const count = Number(cat.active_posts_count || 0);
    const reassignSelect = document.getElementById('deleteReassignTargetSelect');
    const targetName = reassignSelect ? reassignSelect.value.trim() : '';

    if (count > 0 && !targetName) {
        alert('Please select a replacement category to reassign items before deleting.');
        if (reassignSelect) reassignSelect.focus();
        return;
    }

    const payload = {
        id: Number(cat.id),
        action: count > 0 ? 'reassign' : 'delete',
        target_category_name: count > 0 ? targetName : '',
    };

    const confirmBtn = document.getElementById('deleteCategoryConfirmBtn');
    if (confirmBtn) confirmBtn.disabled = true;

    try {
        const res = await fetch('/api/categories/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe(),
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete category.');
        }

        closeDeleteCategoryModal();
        if (typeof showToast === 'function') {
            showToast('Category deleted successfully.', 'success');
        }

        await loadCategories();

    } catch (err) {
        console.error('Failed to delete category:', err);
        if (typeof showToast === 'function') {
            showToast('Error: ' + err.message, 'error');
        } else {
            alert(err.message);
        }
    } finally {
        if (confirmBtn) confirmBtn.disabled = false;
    }
}
