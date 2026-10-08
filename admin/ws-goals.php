<?php
// ============================================================
// WORKSPACE: GOALS
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-goals';
$pageTitle = 'Planning - Goals';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Long-term Goals</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> <span>Add Goal</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Strategic Planning</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Long-term Goals</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Define and track your macro objectives and milestones.</p>
        </div>
    </section>
    
        <div id="goalsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;"></div>
    </div>
</div>

<div id="itemModal" class="modal" role="dialog" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Goal</h3>
            <button type="button" class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form onsubmit="handleSubmit(event)">
                <input type="hidden" id="itemId" value="0">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Goal Title <span class="text-danger">*</span></label>
                    <input type="text" id="itemTitle" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Category</label>
                        <input type="text" id="itemCat" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="e.g., Career, Health">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Status</label>
                        <select id="itemStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Target Date</label>
                    <input type="date" id="itemDate" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Description / Definition of Done</label>
                    <textarea id="itemDesc" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" rows="3"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="pw-btn" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="pw-btn pw-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let items = [];
document.addEventListener('DOMContentLoaded', fetchItems);

function fetchItems() {
    fetch('/api/workspace/goals.php').then(r=>r.json()).then(d=>{if(d.success){items=d.data; render();} });
}

function render() {
    const grid = document.getElementById('goalsGrid');
    if(!items.length) { grid.innerHTML = `
        <div style="grid-column:1/-1; padding:48px 24px; text-align:center; color:var(--pw-text-muted);">
            <div style="margin-bottom: 16px;"><span class="material-symbols-outlined" style="font-size: 32px; color: var(--pw-border-hover);">flag</span></div>
            <div style="font-size: 14px; font-weight: 500;">No goals defined.</div>
            <div style="font-size: 13px; margin-top: 4px;">Set a target and start moving towards it.</div>
        </div>`; 
        return; 
    }
    
    grid.innerHTML = items.map(i => {
        let badgeHtml = '';
        if(i.status === 'completed') badgeHtml = '<span class="pw-badge" style="background: var(--pw-success-bg); color: var(--pw-success);">Completed</span>';
        else if(i.status === 'paused') badgeHtml = '<span class="pw-badge" style="background: var(--pw-warning-bg); color: var(--pw-warning);">Paused</span>';
        else badgeHtml = '<span class="pw-badge" style="background: var(--pw-primary-bg); color: var(--pw-primary);">Active</span>';

        return `
    <div class="pw-card" style="margin-bottom:0; display:flex; flex-direction:column; padding: 0; ${i.status==='completed'?'opacity:0.6;':''}">
        <div style="padding:20px 24px; border-bottom:1px solid var(--pw-border-subtle);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                <h3 style="margin:0; font-size:16px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(i.title)}</h3>
                <div style="display:flex; gap:4px; flex-shrink:0;">
                    <button type="button" class="pw-btn" onclick="editItem(${i.id})" style="padding:4px 8px; background: transparent; border: none; color: var(--pw-text-muted);" title="Edit"><span class="material-symbols-outlined" style="font-size:18px;">edit</span></button>
                    <button type="button" class="pw-btn text-danger" onclick="deleteItem(${i.id})" style="padding:4px 8px; background: transparent; border: none; color: var(--pw-danger);" title="Delete"><span class="material-symbols-outlined" style="font-size:18px;">delete</span></button>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span class="font-mono" style="font-size:11px; color:var(--pw-text-secondary); text-transform:uppercase; font-weight: 600; letter-spacing: 0.05em;">${escapeHtml(i.category||'General')}</span>
                ${badgeHtml}
            </div>
        </div>
        ${i.description ? `<div style="padding:20px 24px; flex:1; font-size:13px; color:var(--pw-text-secondary); line-height:1.6; white-space: pre-wrap;">${escapeHtml(i.description)}</div>` : '<div style="flex:1;"></div>'}
        <div style="padding:16px 24px; border-top:1px solid var(--pw-border-subtle); font-size:12px; color:var(--pw-text-muted); background:var(--pw-surface-container); border-radius: 0 0 var(--pw-radius-lg) var(--pw-radius-lg); display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined" style="font-size:16px;">flag</span> Target: <span style="font-weight: 600;">${i.target_date?i.target_date:'No date set'}</span>
        </div>
    </div>`;
    }).join('');
}

function openModal(item=null) {
    if(item) {
        document.getElementById('modalTitle').textContent='Edit Goal';
        document.getElementById('itemId').value=item.id;
        document.getElementById('itemTitle').value=item.title;
        document.getElementById('itemCat').value=item.category||'';
        document.getElementById('itemStatus').value=item.status;
        document.getElementById('itemDate').value=item.target_date||'';
        document.getElementById('itemDesc').value=item.description||'';
    } else {
        document.getElementById('modalTitle').textContent='Add Goal';
        document.querySelector('form').reset();
        document.getElementById('itemId').value='0';
    }
    document.getElementById('itemModal').style.display='block';
}

function closeModal() { document.getElementById('itemModal').style.display='none'; }
function editItem(id) { openModal(items.find(i=>i.id===id)); }
function deleteItem(id) {
    if(!confirm('Delete this goal?')) return;
    fetch('/api/workspace/goals.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({action:'delete_goal',id:id}) }).then(r=>r.json()).then(d=>{if(d.success)fetchItems();});
}
function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value,10);
    const p = { action:id>0?'update_goal':'create_goal', id:id, title:document.getElementById('itemTitle').value, category:document.getElementById('itemCat').value, status:document.getElementById('itemStatus').value, target_date:document.getElementById('itemDate').value, description:document.getElementById('itemDesc').value };
    fetch('/api/workspace/goals.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(p) }).then(r=>r.json()).then(d=>{if(d.success){closeModal();fetchItems();}});
}
function escapeHtml(s) { return (s+'').replace(/[&<"']/g, m=>({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>
<?php require __DIR__.'/partials/layout_bottom.php'; ?>
