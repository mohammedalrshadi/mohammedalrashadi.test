// ============================================================
// ADMIN — STORE CATALOG MANAGEMENT JAVASCRIPT
// admin/js/store.js
//
// Controller for admin/store.php:
//   - Fetches products from /api/products/list.php
//   - Filters by status tabs, search terms, categories, and product type
//   - Quick status publish/unpublish toggle
//   - Product deletion with server-side confirmation modal
// ============================================================

let allProducts = [];
let activeStatusFilter = 'all';
let searchDebounceTimer = null;
let targetDeleteId = null;

document.addEventListener('DOMContentLoaded', function () {
    loadProducts();

    // SEC-003: delegated handler for product table action buttons.
    const storeBody = document.getElementById('productsTableBody');
    if (storeBody) {
        storeBody.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const action    = btn.dataset.action;
            const productId = Number(btn.dataset.id);
            const status    = btn.dataset.status;
            const title     = btn.dataset.title;
            if (action === 'toggle-publish') {
                togglePublishStatus(productId, status);
            } else if (action === 'delete') {
                promptDeleteProduct(productId, title);
            }
        });
    }
});

/**
 * Loads all products from the backend API.
 */
async function loadProducts() {
    showLoading(true);

    try {
        const response = await fetch('/api/products/list.php?status=all', {
            method: 'GET',
            credentials: 'same-origin',
        });

        const result = await response.json();

        if (!result.success) {
            showToast(result.message || 'Failed to load products.', 'error');
            return;
        }

        allProducts = Array.isArray(result.data) ? result.data : [];
        updateTabCounters();
        renderProductsTable();

    } catch (err) {
        console.error('[store.js] Load error:', err);
        showToast('Network error loading store products.', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Calculates and renders counter badges on status tabs.
 */
function updateTabCounters() {
    const counts = {
        all: allProducts.length,
        published: 0,
        draft: 0,
        archived: 0,
    };

    allProducts.forEach(p => {
        if (counts.hasOwnProperty(p.status)) {
            counts[p.status]++;
        }
    });

    document.getElementById('tabCountAll').textContent = counts.all;
    document.getElementById('tabCountPublished').textContent = counts.published;
    document.getElementById('tabCountDraft').textContent = counts.draft;
    document.getElementById('tabCountArchived').textContent = counts.archived;
}

/**
 * Filter tab click handler.
 */
function filterProductsByStatus(status) {
    activeStatusFilter = status;

    const tabs = document.querySelectorAll('#productStatusTabs .filter-tab');
    tabs.forEach(tab => {
        const isMatch = tab.getAttribute('data-status') === status;
        tab.classList.toggle('active', isMatch);
        tab.setAttribute('aria-selected', isMatch ? 'true' : 'false');
    });

    renderProductsTable();
}

/**
 * Debounced search input handler.
 */
function debounceProductSearch() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        renderProductsTable();
    }, 200);
}

/**
 * Renders the filtered products into the data table.
 */
function renderProductsTable() {
    const tbody = document.getElementById('productsTableBody');
    const emptyState = document.getElementById('productsEmptyState');
    const metaCount = document.getElementById('productResultCount');
    const searchVal = (document.getElementById('productSearchInput')?.value || '').trim().toLowerCase();
    const catVal = document.getElementById('productCategoryFilter')?.value || '';
    const typeVal = document.getElementById('productTypeFilter')?.value || '';

    // Apply active filters
    const filtered = allProducts.filter(p => {
        // Status filter
        if (activeStatusFilter !== 'all' && p.status !== activeStatusFilter) {
            return false;
        }
        // Category filter
        if (catVal !== '' && p.category !== catVal) {
            return false;
        }
        // Product type filter
        if (typeVal !== '' && p.product_type !== typeVal) {
            return false;
        }
        // Search query
        if (searchVal !== '') {
            const titleMatch = (p.title || '').toLowerCase().includes(searchVal);
            const slugMatch = (p.slug || '').toLowerCase().includes(searchVal);
            const descMatch = (p.short_description || '').toLowerCase().includes(searchVal);
            if (!titleMatch && !slugMatch && !descMatch) {
                return false;
            }
        }
        return true;
    });

    metaCount.textContent = `Showing ${filtered.length} of ${allProducts.length} entries`;

    if (filtered.length === 0) {
        tbody.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }

    emptyState.style.display = 'none';

    tbody.innerHTML = filtered.map(p => {
        const isPublished = p.status === 'published';
        const isFree = p.product_type === 'free_download';

        const statusBadge = isPublished
            ? '<span class="status-badge status-published">Published</span>'
            : (p.status === 'draft'
                ? '<span class="status-badge status-draft">Draft</span>'
                : '<span class="status-badge status-neutral">Archived</span>');

        const typeBadge = isFree
            ? '<span class="status-badge" style="background: var(--color-surface-container-low, var(--bg-surface)); color: var(--color-primary); border: 1px solid var(--border-medium); font-size: 11px;"><i class="fas fa-file-arrow-down"></i> Free Download</span>'
            : `<span class="status-badge" style="background: var(--color-surface-container-low, var(--bg-surface)); color: var(--color-tertiary); border: 1px solid var(--border-medium); font-size: 11px;"><i class="fas fa-arrow-up-right-from-square"></i> ${escapeHtml(p.platform || 'External')}</span>`;

        const thumbHtml = p.thumbnail
            ? `<img src="${escapeHtml(p.thumbnail)}" alt="" style="width: 44px; height: 32px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-subtle);">`
            : `<div style="width: 44px; height: 32px; border-radius: 4px; background: var(--bg-hover); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 14px;"><i class="fas fa-store"></i></div>`;

        const priceText = escapeHtml(p.price_display || (isFree ? 'Free' : '—'));

        const featuredStar = p.featured
            ? '<i class="fas fa-star" title="Featured Product" style="color: var(--color-warning); margin-left: 6px; font-size: 11px;"></i>'
            : '';

        return `
            <tr>
                <td>${thumbHtml}</td>
                <td>
                    <div style="font-weight: 600; color: var(--text-primary); font-size: 13.5px; display: flex; align-items: center;">
                        <a href="store-edit.php?id=${p.id}" style="color: inherit; text-decoration: none;" class="hover-cyan">
                            ${escapeHtml(p.title)}
                        </a>
                        ${featuredStar}
                    </div>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 2px;">
                        /store/${escapeHtml(p.slug)}
                    </div>
                </td>
                <td>
                    <span style="font-size: 12.5px; color: var(--text-secondary);">${escapeHtml(p.category || 'Other')}</span>
                </td>
                <td>${typeBadge}</td>
                <td>
                    <span style="font-family: var(--font-mono); font-weight: 600; font-size: 13px; color: ${isFree ? 'var(--color-success)' : 'var(--text-primary)'};">
                        ${priceText}
                    </span>
                </td>
                <td>${statusBadge}</td>
                <td style="text-align: center; font-family: var(--font-mono); font-size: 12px; color: var(--text-muted);">
                    ${p.sort_order}
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <button type="button" class="btn btn-secondary btn-sm"
                        data-action="toggle-publish"
                        data-id="${p.id}"
                        data-status="${p.status}"
                        title="${isPublished ? 'Unpublish to Draft' : 'Publish Product'}">
                        <i class="fas ${isPublished ? 'fa-eye-slash' : 'fa-globe'}"></i>
                    </button>
                    <a href="store-edit.php?id=${p.id}" class="btn btn-secondary btn-sm" title="Edit Product">
                        <i class="fas fa-pen"></i>
                    </a>
                    <button type="button" class="btn btn-danger btn-sm"
                        data-action="delete"
                        data-id="${p.id}"
                        data-title="${escapeHtml(p.title)}"
                        title="Delete Product">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Quick toggle publish / draft state.
 */
async function togglePublishStatus(id, currentStatus) {
    const newStatus = currentStatus === 'published' ? 'draft' : 'published';
    showLoading(true);

    try {
        const response = await fetch('/api/products/update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                id: id,
                status: newStatus,
            }),
        });

        const result = await response.json();

        if (!result.success) {
            let msg = result.message || 'Failed to update status.';
            if (Array.isArray(result.errors) && result.errors.length > 0) {
                msg += ' ' + result.errors.map(e => e.message || e).join(' ');
            }
            showToast(msg, 'error');
            return;
        }

        showToast(`Product status set to ${newStatus}.`, 'success');
        // Update local object and re-render
        const prod = allProducts.find(p => p.id === id);
        if (prod) {
            prod.status = newStatus;
        }
        updateTabCounters();
        renderProductsTable();

    } catch (err) {
        console.error('[store.js] Toggle error:', err);
        showToast('Network error updating product status.', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Open delete modal.
 */
function promptDeleteProduct(id, title) {
    targetDeleteId = id;
    document.getElementById('deleteProductTitleDisplay').textContent = `"${title}"`;
    document.getElementById('deleteProductModal').style.display = 'flex';
}

function closeDeleteProductModal() {
    targetDeleteId = null;
    document.getElementById('deleteProductModal').style.display = 'none';
}

/**
 * Execute delete request.
 */
async function executeDeleteProduct() {
    if (!targetDeleteId) return;

    const idToDelete = targetDeleteId;

    showLoading(true);
    closeDeleteProductModal();

    try {
        const response = await fetch('/api/products/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                id: idToDelete,
                action: 'delete',
            }),
        });

        const result = await response.json();

        if (!result.success) {
            showToast(result.message || 'Failed to delete product.', 'error');
            return;
        }

        showToast('Product deleted successfully.', 'success');
        allProducts = allProducts.filter(p => p.id !== idToDelete);
        updateTabCounters();
        renderProductsTable();

    } catch (err) {
        console.error('[store.js] Delete error:', err);
        showToast('Network error deleting product.', 'error');
    } finally {
        showLoading(false);
    }
}

/**
 * Helper: escape HTML strings.
 */
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Helper: escape JS string parameters.
 */
function escapeJs(str) {
    if (!str) return '';
    return String(str)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"');
}

