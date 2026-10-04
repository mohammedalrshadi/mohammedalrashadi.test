// ============================================================
// ADMIN — HOME SHOWCASE CURATION STUDIO (showcase.php)
// admin/js/showcase.js
//
// Manages the curated mixed-content strip on the homepage:
// - Products (referencing products.id via reference_id)
// - Images (standalone visual assets via image_url, alt_text)
// - Projects (referencing posts.id via reference_id)
// - Writing (referencing posts.id via reference_id)
// ============================================================

let showcaseItems = [];

document.addEventListener('DOMContentLoaded', async () => {
    if (typeof checkAdminAuthentication === 'function') {
        const authenticated = await checkAdminAuthentication();
        if (!authenticated) return;
    }

    // 1. Initial Data Fetch
    await loadShowcaseItems();

    // 2. Setup Event Handlers
    setupShowcaseEvents();
});

function getCsrfTokenSafe() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Escapes HTML to prevent XSS injection in admin table.
 */
function escapeHTML(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Loads current showcase strip items from server.
 */
async function loadShowcaseItems() {
    const loadingState = document.getElementById('showcaseLoadingState');
    const emptyState = document.getElementById('showcaseEmptyState');
    const container = document.getElementById('showcaseTableContainer');
    const countBadge = document.getElementById('showcaseCountBadge');

    if (loadingState) loadingState.style.display = 'block';
    if (emptyState) emptyState.style.display = 'none';
    if (container) container.style.display = 'none';

    try {
        const res = await fetch('/api/showcase/list.php?admin=1', {
            credentials: 'same-origin'
        });
        const json = await res.json();

        if (loadingState) loadingState.style.display = 'none';

        if (!json.success || !Array.isArray(json.data)) {
            throw new Error(json.message || 'Failed to fetch showcase items');
        }

        showcaseItems = json.data;

        // Update Active Count
        const activeCount = showcaseItems.filter(i => i.is_enabled).length;
        if (countBadge) {
            countBadge.textContent = `${activeCount} Active / ${showcaseItems.length} Total`;
            countBadge.className = activeCount > 0 ? 'status-badge status-published' : 'status-badge status-warning';
        }

        if (showcaseItems.length === 0) {
            if (emptyState) emptyState.style.display = 'block';
            if (container) container.style.display = 'none';
        } else {
            if (emptyState) emptyState.style.display = 'none';
            if (container) container.style.display = 'block';
            renderShowcaseTable();
        }

    } catch (err) {
        console.error('Error loading showcase items:', err);
        if (loadingState) loadingState.style.display = 'none';
        if (typeof showToast === 'function') {
            showToast('Error loading showcase: ' + (err.message || 'Network error'), 'error');
        }
    }
}

/**
 * Returns type badge HTML with distinctive theme colors.
 */
function renderTypeBadge(type) {
    const normType = (type || 'project').toLowerCase();
    switch (normType) {
        case 'product':
            return '<span class="status-badge" style="background: var(--color-surface-container-low, var(--bg-surface)); color: var(--color-tertiary); border: 1px solid var(--border-medium);"><i class="fas fa-store" style="margin-right: 4px; font-size: 11px;"></i>Product</span>';
        case 'project':
            return '<span class="status-badge" style="background: var(--color-surface-container-low, var(--bg-surface)); color: var(--color-primary); border: 1px solid var(--border-medium);"><i class="fas fa-code-branch" style="margin-right: 4px; font-size: 11px;"></i>Project</span>';
        case 'writing':
        case 'article':
            return '<span class="status-badge" style="background: var(--color-surface-container-low, var(--bg-surface)); color: var(--color-success); border: 1px solid var(--border-medium);"><i class="far fa-file-alt" style="margin-right: 4px; font-size: 11px;"></i>Writing</span>';
        case 'image':
            return '<span class="status-badge" style="background: var(--color-surface-container-low, var(--bg-surface)); color: var(--color-warning); border: 1px solid var(--border-medium);"><i class="fas fa-image" style="margin-right: 4px; font-size: 11px;"></i>Image</span>';
        default:
            return `<span class="status-badge status-neutral">${escapeHTML(type)}</span>`;
    }
}

/**
 * Renders the table of showcase items.
 */
function renderShowcaseTable() {
    const tbody = document.getElementById('showcaseTableBody');
    if (!tbody) return;

    const total = showcaseItems.length;

    tbody.innerHTML = showcaseItems.map((item, idx) => {
        const isFirst = idx === 0;
        const isLast = idx === total - 1;
        const isEnabled = !!item.is_enabled;
        const isMissing = !!item.is_missing;

        const safeTitle = escapeHTML(item.title || 'Untitled');
        const safeUrl = escapeHTML(item.url || '#');
        const safeImage = item.image ? escapeHTML(item.image) : '/assets/diagram_distributed_systems.png';

        const statusBadge = isMissing
            ? '<span class="status-badge status-archived" title="Referenced content unavailable">Missing</span>'
            : (isEnabled
                ? '<span class="status-badge status-published">Active</span>'
                : '<span class="status-badge status-neutral">Hidden</span>');

        return `
            <tr data-id="${item.id}" class="${!isEnabled ? 'opacity-60' : ''}">
                <!-- 1. Order Badge -->
                <td style="text-align: center;">
                    <span class="showcase-order-badge" style="display: inline-block; padding: 4px 8px; font-size: 11.5px; font-family: var(--font-mono); font-weight: 700; background: var(--bg-hover); border-radius: var(--radius-sm); border: 1px solid var(--border-medium); color: var(--accent);">
                        #${idx + 1}
                    </span>
                </td>

                <!-- 2. Preview Thumbnail -->
                <td>
                    <div style="width: 54px; height: 38px; border-radius: 4px; overflow: hidden; background: var(--bg-hover); border: 1px solid var(--border-medium); display: flex; align-items: center; justify-content: center;">
                        <img src="${safeImage}" alt="" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='/assets/diagram_distributed_systems.png';">
                    </div>
                </td>

                <!-- 3. Title & Destination -->
                <td>
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <strong style="font-size: 13.5px;"><a href="showcase-edit.php?id=${encodeURIComponent(item.id)}" style="color: var(--text-primary); text-decoration: none;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--text-primary)'">${safeTitle}</a></strong>
                        <div style="font-size: 11.5px; font-family: var(--font-mono); color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                            <span>${safeUrl}</span>
                            ${safeUrl !== '#' ? `<a href="${safeUrl}" target="_blank" rel="noopener" style="color: var(--accent);" title="Test Link"><i class="fas fa-arrow-up-right-from-square" style="font-size: 10px;"></i></a>` : ''}
                        </div>
                        ${item.title_override ? `<span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Override: "${escapeHTML(item.title_override)}"</span>` : ''}
                    </div>
                </td>

                <!-- 4. Content Type Badge -->
                <td>
                    ${renderTypeBadge(item.item_type)}
                </td>

                <!-- 5. Status -->
                <td style="text-align: center;">
                    ${statusBadge}
                </td>

                <!-- 6. Actions (Reorder, Edit, Toggle, Delete) -->
                <td style="text-align: right;">
                    <div style="display: inline-flex; align-items: center; gap: 6px;">
                        <!-- Move Up -->
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Move Up" ${isFirst ? 'disabled' : ''} onclick="moveShowcaseItem(${idx}, -1)">
                            <i class="fas fa-arrow-up" style="font-size: 11px;"></i>
                        </button>

                        <!-- Move Down -->
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Move Down" ${isLast ? 'disabled' : ''} onclick="moveShowcaseItem(${idx}, 1)">
                            <i class="fas fa-arrow-down" style="font-size: 11px;"></i>
                        </button>

                        <!-- Toggle Visibility -->
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="${isEnabled ? 'Hide on Homepage' : 'Show on Homepage'}" onclick="toggleShowcaseItem(${item.id})">
                            <i class="fas ${isEnabled ? 'fa-eye' : 'fa-eye-slash'}" style="font-size: 11px; color: ${isEnabled ? 'var(--accent)' : 'var(--text-muted)'};"></i>
                        </button>

                        <!-- Edit -->
                        <a href="showcase-edit.php?id=${encodeURIComponent(item.id)}" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Edit Item">
                            <i class="far fa-edit" style="font-size: 11px;"></i>
                        </a>

                        <!-- Delete -->
                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px; color: var(--danger);" title="Remove from Showcase" onclick="deleteShowcaseItem(${item.id})">
                            <i class="far fa-trash-alt" style="font-size: 11px;"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Reorders an item up (-1) or down (+1) and persists new order.
 */
async function moveShowcaseItem(fromIndex, delta) {
    const toIndex = fromIndex + delta;
    if (toIndex < 0 || toIndex >= showcaseItems.length) return;

    // Swap in local array
    const temp = showcaseItems[fromIndex];
    showcaseItems[fromIndex] = showcaseItems[toIndex];
    showcaseItems[toIndex] = temp;

    // Immediately update UI
    renderShowcaseTable();

    // Persist to server
    const ids = showcaseItems.map(i => i.id);
    try {
        const res = await fetch('/api/showcase/reorder.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ item_ids: ids })
        });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Failed to persist order');
        }
        if (typeof showToast === 'function') {
            showToast('Showcase order updated.', 'success');
        }
    } catch (err) {
        console.error('Reorder error:', err);
        if (typeof showToast === 'function') {
            showToast('Failed to save order: ' + err.message, 'error');
        }
        await loadShowcaseItems(); // rollback
    }
}

/**
 * Toggles visibility for a single item.
 */
async function toggleShowcaseItem(id) {
    try {
        const res = await fetch('/api/showcase/toggle.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Failed to toggle visibility');
        }

        const item = showcaseItems.find(i => i.id === id);
        if (item) {
            item.is_enabled = json.is_enabled;
        }

        renderShowcaseTable();
        const activeCount = showcaseItems.filter(i => i.is_enabled).length;
        const countBadge = document.getElementById('showcaseCountBadge');
        if (countBadge) {
            countBadge.textContent = `${activeCount} Active / ${showcaseItems.length} Total`;
        }

        if (typeof showToast === 'function') {
            showToast(json.is_enabled ? 'Item enabled on homepage.' : 'Item hidden from homepage.', 'info');
        }
    } catch (err) {
        console.error('Toggle error:', err);
        if (typeof showToast === 'function') {
            showToast('Failed to toggle item: ' + err.message, 'error');
        }
    }
}

/**
 * Removes an item from the Showcase strip.
 */
async function deleteShowcaseItem(id) {
    const item = showcaseItems.find(i => i.id === id);
    const itemTitle = item ? (item.title || 'this item') : 'this item';

    const confirmed = window.confirm(
        `Remove "${itemTitle}" from the Home Showcase strip?\n\n` +
        `Note: This will only remove it from the homepage presentation rail. ` +
        `The original product, project, or writing content will NOT be deleted.`
    );

    if (!confirmed) return;

    try {
        const res = await fetch('/api/showcase/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': getCsrfTokenSafe()
            },
            body: JSON.stringify({ id })
        });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Failed to delete item');
        }

        if (typeof showToast === 'function') {
            showToast('Item removed from Home Showcase.', 'success');
        }

        await loadShowcaseItems();

    } catch (err) {
        console.error('Delete error:', err);
        if (typeof showToast === 'function') {
            showToast('Failed to remove item: ' + err.message, 'error');
        }
    }
}

/**
 * Configures event listeners.
 */
function setupShowcaseEvents() {
    // Refresh button
    const refreshBtn = document.getElementById('refreshShowcaseBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => loadShowcaseItems());
    }
}

