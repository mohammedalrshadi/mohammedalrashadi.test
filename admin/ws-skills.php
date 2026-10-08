<?php
// ============================================================
// WORKSPACE: SKILLS
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'ws-skills';
$pageTitle = 'Development - Skills Matrix';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Skills Matrix</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>Add Skill</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Growth & Learning</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Skills Matrix</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Track your technical and soft skills progression.</p>
        </div>
    </section>
    
        <div id="skillsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
            <div style="grid-column: 1/-1; text-align: center; padding: 48px; color: var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Loading...
            </div>
        </div>
    </div>
</div>

<!-- MODAL -->
<div id="itemModal" class="modal" role="dialog" aria-hidden="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Skill</h3>
            <button type="button" class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="itemForm" onsubmit="handleSubmit(event)">
                <input type="hidden" id="itemId" value="0">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Skill Name <span class="text-danger">*</span></label>
                    <input type="text" id="itemName" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required placeholder="e.g., Python">
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Category</label>
                    <input type="text" id="itemCat" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="e.g., Languages, Frameworks, Soft Skills">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Proficiency Status</label>
                    <select id="itemStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                        <option value="learning">Learning (Beginner)</option>
                        <option value="practicing">Practicing (Intermediate)</option>
                        <option value="applied">Applied (Advanced)</option>
                        <option value="strong">Strong (Expert)</option>
                    </select>
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
    fetch('/api/workspace/skills.php')
        .then(r => r.json())
        .then(d => { if(d.success) { items = d.data; renderItems(); } })
        .catch(console.error);
}

function getBadge(status) {
    let color = 'var(--pw-text-secondary)';
    let bg = 'var(--pw-surface-container)';
    let width = '25%';
    
    if (status === 'strong') { bg = 'var(--pw-success)'; width = '100%'; color = 'var(--pw-success)'; }
    if (status === 'applied') { bg = 'var(--pw-primary)'; width = '75%'; color = 'var(--pw-primary)'; }
    if (status === 'practicing') { bg = 'var(--pw-warning, #D97706)'; width = '50%'; color = 'var(--pw-warning, #D97706)'; }
    if (status === 'learning') { bg = 'var(--pw-text-muted)'; width = '25%'; color = 'var(--pw-text-secondary)'; }
    
    return { bg, width, color };
}

function renderItems() {
    const grid = document.getElementById('skillsGrid');
    if (items.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; padding: 48px; text-align: center; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined text-primary" style="font-size: 32px; margin-bottom: 12px; display: block;">psychology</span>
            No skills tracked yet.
        </div>`;
        return;
    }
    
    grid.innerHTML = items.map(i => {
        const badge = getBadge(i.status);
        return `
        <div class="pw-card bg-lowest" style="padding: 24px; display: flex; flex-direction: column; justify-content: space-between; border: 1px solid var(--pw-border-subtle); box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--pw-surface-container); border-radius: 8px;">
                        <span class="material-symbols-outlined text-primary" style="font-size: 20px;">psychology</span>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 2px 0; font-size: 16px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(i.name)}</h3>
                        <div style="font-size: 11px; color: var(--pw-text-muted); font-family: var(--pw-font-mono); text-transform: uppercase; letter-spacing: 0.05em;">
                            ${escapeHtml(i.category || 'Uncategorized')}
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 4px;">
                    <button type="button" class="pw-btn" onclick="editItem(${i.id})" title="Edit" style="padding: 4px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                    <button type="button" class="pw-btn" onclick="deleteItem(${i.id})" title="Delete" style="padding: 4px; font-size: 11px; background: transparent; border: none; color: var(--pw-danger);"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
                </div>
            </div>
            
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 6px; color: ${badge.color}; font-weight: 600;">
                    <span style="text-transform: capitalize;">${i.status}</span>
                    <span class="font-mono">${badge.width}</span>
                </div>
                <div style="width: 100%; height: 6px; background: var(--pw-border-subtle); border-radius: 3px; overflow: hidden;">
                    <div style="height: 100%; width: ${badge.width}; background: ${badge.bg}; border-radius: 3px;"></div>
                </div>
            </div>
        </div>`;
    }).join('');
}

function openModal(item = null) {
    if (item) {
        document.getElementById('modalTitle').textContent = 'Edit Skill';
        document.getElementById('itemId').value = item.id;
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemCat').value = item.category || '';
        document.getElementById('itemStatus').value = item.status;
    } else {
        document.getElementById('modalTitle').textContent = 'Add Skill';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '0';
    }
    document.getElementById('itemModal').style.display = 'block';
    document.getElementById('itemName').focus();
}

function closeModal() { document.getElementById('itemModal').style.display = 'none'; }
function editItem(id) { openModal(items.find(i => i.id === id)); }

function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value, 10);
    const payload = {
        action: id > 0 ? 'update_skill' : 'create_skill', id: id,
        name: document.getElementById('itemName').value,
        category: document.getElementById('itemCat').value,
        status: document.getElementById('itemStatus').value,
    };
    
    fetch('/api/workspace/skills.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if(d.success) { closeModal(); fetchItems(); showToast(d.message, 'success'); }
        else showToast(d.message, 'error');
    });
}

function deleteItem(id) {
    if(!confirm('Delete this skill?')) return;
    fetch('/api/workspace/skills.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ action: 'delete_skill', id: id })
    }).then(r => r.json()).then(d => {
        if(d.success) { fetchItems(); showToast(d.message, 'success'); }
    });
}

function escapeHtml(str) {
    return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
