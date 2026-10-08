<?php
// ============================================================
// WORKSPACE: ACHIEVEMENTS
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-achievements';
$pageTitle = 'Planning - Achievements';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Personal Achievements</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> <span>Add Achievement</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Portfolio & Records</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Personal Achievements</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Track personal milestones securely (separate from your public portfolio).</p>
        </div>
    </section>
    
        <div id="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;"></div>
    </div>
</div>

<div id="itemModal" class="modal" role="dialog" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Achievement</h3>
            <button type="button" class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form onsubmit="handleSubmit(event)">
                <input type="hidden" id="itemId" value="0">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Title <span class="text-danger">*</span></label>
                    <input type="text" id="itemTitle" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Date Earned</label>
                    <input type="date" id="itemDate" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Description</label>
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

function fetchItems() { fetch('/api/workspace/achievements.php').then(r=>r.json()).then(d=>{if(d.success){items=d.data; render();} }); }

function render() {
    const grid = document.getElementById('grid');
    if(!items.length) { grid.innerHTML = `
        <div style="grid-column:1/-1; padding:48px 24px; text-align:center; color:var(--pw-text-muted);">
            <div style="margin-bottom: 16px;"><span class="material-symbols-outlined" style="font-size: 32px; color: var(--pw-border-hover);">workspace_premium</span></div>
            <div style="font-size: 14px; font-weight: 500;">No achievements recorded yet.</div>
            <div style="font-size: 13px; margin-top: 4px;">Record your first win today.</div>
        </div>`; 
        return; 
    }
    
    grid.innerHTML = items.map(i => `
    <div class="pw-card" style="margin-bottom:0; display:flex; flex-direction:column; padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="width: 40px; height: 40px; background: var(--pw-accent-bg); color: var(--pw-accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <span class="material-symbols-outlined" style="font-size: 20px;">workspace_premium</span>
                </div>
                <div>
                    <h3 style="margin:0 0 4px 0; font-size:16px; font-weight: 600; color:var(--pw-text-main);">${escapeHtml(i.title)}</h3>
                    <div style="font-size:12px; font-family:var(--pw-font-mono); color:var(--pw-text-secondary);">${i.date_earned||'No Date'}</div>
                </div>
            </div>
            <div style="display:flex; gap:4px; flex-shrink:0;">
                <button type="button" class="pw-btn" onclick="editItem(${i.id})" style="padding:4px; background: transparent; border: none; color: var(--pw-text-muted);" title="Edit"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                <button type="button" class="pw-btn text-danger" onclick="deleteItem(${i.id})" style="padding:4px; background: transparent; border: none; color: var(--pw-danger);" title="Delete"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
            </div>
        </div>
        ${i.description ? `<div style="font-size:13px; color:var(--pw-text-secondary); line-height:1.6; border-top: 1px solid var(--pw-border-subtle); padding-top: 16px; margin-top: 8px;">${escapeHtml(i.description)}</div>` : ''}
    </div>`).join('');
}

function openModal(item=null) {
    if(item) {
        document.getElementById('modalTitle').textContent='Edit Achievement';
        document.getElementById('itemId').value=item.id;
        document.getElementById('itemTitle').value=item.title;
        document.getElementById('itemDate').value=item.date_earned||'';
        document.getElementById('itemDesc').value=item.description||'';
    } else {
        document.getElementById('modalTitle').textContent='Add Achievement';
        document.querySelector('form').reset();
        document.getElementById('itemId').value='0';
    }
    document.getElementById('itemModal').style.display='block';
}

function closeModal() { document.getElementById('itemModal').style.display='none'; }
function editItem(id) { openModal(items.find(i=>i.id===id)); }
function deleteItem(id) {
    if(!confirm('Delete this achievement?')) return;
    fetch('/api/workspace/achievements.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({action:'delete_achievement',id:id}) }).then(r=>r.json()).then(d=>{if(d.success)fetchItems();});
}
function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value,10);
    const p = { action:id>0?'update_achievement':'create_achievement', id:id, title:document.getElementById('itemTitle').value, date_earned:document.getElementById('itemDate').value, description:document.getElementById('itemDesc').value };
    fetch('/api/workspace/achievements.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(p) }).then(r=>r.json()).then(d=>{if(d.success){closeModal();fetchItems();}});
}
function escapeHtml(s) { return (s+'').replace(/[&<"']/g, m=>({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>
<?php require __DIR__.'/partials/layout_bottom.php'; ?>
