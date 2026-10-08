<?php
// ============================================================
// WORKSPACE: RESOURCES
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'ws-resources';
$pageTitle = 'Study & Knowledge - Resources';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Resource Library</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>Add Resource</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Growth & Learning</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Resource Library</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Saved links, reference materials, and useful tools.</p>
        </div>
    </section>
    
        <div id="resourcesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
            <div style="grid-column: 1/-1; text-align: center; padding: 48px; color: var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading...
            </div>
        </div>
    </div>
</div>

<!-- MODAL -->
<div id="itemModal" class="modal" role="dialog" aria-hidden="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Resource</h3>
            <button type="button" class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="itemForm" onsubmit="handleSubmit(event)">
                <input type="hidden" id="itemId" value="0">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Title <span class="text-danger">*</span></label>
                    <input type="text" id="itemTitle" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required placeholder="Resource title">
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">URL <span class="text-danger">*</span></label>
                    <input type="url" id="itemUrl" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required placeholder="https://...">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Type</label>
                        <select id="itemType" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                            <option value="link">Link / Website</option>
                            <option value="video">Video</option>
                            <option value="doc">Document</option>
                            <option value="repo">Repository</option>
                            <option value="tool">Tool</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Status</label>
                        <select id="itemStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                            <option value="saved">Saved</option>
                            <option value="to_read">To Read / Watch</option>
                            <option value="reading">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Description / Notes</label>
                    <textarea id="itemDesc" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" rows="3"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="pw-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="pw-btn pw-btn-primary" id="submitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let items = [];

document.addEventListener('DOMContentLoaded', fetchItems);

function fetchItems() {
    fetch('/api/workspace/resources.php')
        .then(r => r.json())
        .then(d => { if(d.success) { items = d.data; renderItems(); } })
        .catch(console.error);
}

function getIcon(type) {
    const icons = {
        'video': 'fa-video', 'doc': 'fa-file-alt', 'repo': 'fa-code-branch', 
        'tool': 'fa-wrench', 'link': 'fa-link'
    };
    return icons[type] || 'fa-link';
}

function renderItems() {
    const grid = document.getElementById('resourcesGrid');
    if (items.length === 0) {
        grid.innerHTML = `
        <div style="grid-column: 1/-1; padding: 48px 24px; text-align: center; color: var(--pw-text-muted);">
            <div style="margin-bottom: 16px;"><span class="material-symbols-outlined" style="font-size: 32px; color: var(--pw-border-hover);">bookmark_add</span></div>
            <div style="font-size: 14px; font-weight: 500;">No resources saved yet.</div>
            <div style="font-size: 13px; margin-top: 4px;">Start collecting your tools and references.</div>
        </div>`;
        return;
    }
    
    grid.innerHTML = items.map(i => {
        let badgeHtml = '';
        if(i.status === 'completed') badgeHtml = '<span class="pw-badge" style="background: var(--pw-success-bg); color: var(--pw-success);">Completed</span>';
        else if(i.status === 'archived') badgeHtml = '<span class="pw-badge" style="background: var(--pw-surface-container); color: var(--pw-text-secondary);">Archived</span>';
        else badgeHtml = `<span class="pw-badge" style="background: var(--pw-primary-bg); color: var(--pw-primary);">${escapeHtml(i.status.replace('_', ' '))}</span>`;

        return `
        <div class="pw-card" style="margin-bottom: 0; display: flex; flex-direction: column; padding: 0;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--pw-border-subtle); display: flex; gap: 12px; align-items: flex-start; background: var(--pw-surface-container); border-radius: var(--pw-radius-lg) var(--pw-radius-lg) 0 0;">
                <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--pw-bg); border: 1px solid var(--pw-border-subtle); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--pw-accent);">
                    <i class="fas ${getIcon(i.type)}"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h3 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--pw-text-main);" title="${escapeHtml(i.title)}">
                        <a href="${escapeHtml(i.url)}" target="_blank" style="color: inherit; text-decoration: none;">${escapeHtml(i.title)}</a>
                    </h3>
                    <div style="font-size: 11px; color: var(--pw-text-secondary); font-family: var(--pw-font-mono); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${escapeHtml(i.url)}
                    </div>
                </div>
            </div>
            ${i.description ? `<div style="padding: 20px 24px; flex: 1; font-size: 13px; color: var(--pw-text-secondary); line-height: 1.6;">${escapeHtml(i.description)}</div>` : '<div style="flex:1;"></div>'}
            <div style="padding: 16px 24px; border-top: 1px solid var(--pw-border-subtle); display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 var(--pw-radius-lg) var(--pw-radius-lg);">
                ${badgeHtml}
                <div style="display: flex; gap: 4px;">
                    <button type="button" class="pw-btn" onclick="editItem(${i.id})" title="Edit" style="padding: 4px; background: transparent; border: none; color: var(--pw-text-muted);"><span class="material-symbols-outlined" style="font-size: 16px;">edit</span></button>
                    <button type="button" class="pw-btn text-danger" onclick="deleteItem(${i.id})" title="Delete" style="padding: 4px; background: transparent; border: none; color: var(--pw-danger);"><span class="material-symbols-outlined" style="font-size: 16px;">delete</span></button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function openModal(item = null) {
    if (item) {
        document.getElementById('modalTitle').textContent = 'Edit Resource';
        document.getElementById('itemId').value = item.id;
        document.getElementById('itemTitle').value = item.title;
        document.getElementById('itemUrl').value = item.url;
        document.getElementById('itemType').value = item.type;
        document.getElementById('itemStatus').value = item.status;
        document.getElementById('itemDesc').value = item.description || '';
    } else {
        document.getElementById('modalTitle').textContent = 'Add Resource';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '0';
    }
    document.getElementById('itemModal').style.display = 'block';
    document.getElementById('itemTitle').focus();
}

function closeModal() { document.getElementById('itemModal').style.display = 'none'; }
function editItem(id) { openModal(items.find(i => i.id === id)); }

function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value, 10);
    const payload = {
        action: id > 0 ? 'update_resource' : 'create_resource', id: id,
        title: document.getElementById('itemTitle').value,
        url: document.getElementById('itemUrl').value,
        type: document.getElementById('itemType').value,
        status: document.getElementById('itemStatus').value,
        description: document.getElementById('itemDesc').value,
    };
    
    fetch('/api/workspace/resources.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if(d.success) { closeModal(); fetchItems(); showToast(d.message, 'success'); }
        else showToast(d.message, 'error');
    });
}

function deleteItem(id) {
    if(!confirm('Delete this resource?')) return;
    fetch('/api/workspace/resources.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ action: 'delete_resource', id: id })
    }).then(r => r.json()).then(d => {
        if(d.success) { fetchItems(); showToast(d.message, 'success'); }
    });
}

function escapeHtml(str) {
    return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
