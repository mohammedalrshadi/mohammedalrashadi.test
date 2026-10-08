<?php
// ============================================================
// WORKSPACE: HABITS
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-habits';
$pageTitle = 'Planning - Habits';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Habits & Routines</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> <span>Add Habit</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Life Operations</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Habits & Routines</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Maintain consistency with your recurring actions.</p>
        </div>
    </section>

    <div class="pw-card" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
            <div class="pw-section-title">
                <span class="material-symbols-outlined text-primary" style="font-size: 16px;">repeat</span>
                Active Habits
            </div>
        </div>
        <div class="pw-card-list" id="tableBody">
            <div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading habits...
            </div>
        </div>
    </div>
    </div>
</div>

<div id="itemModal" class="modal" role="dialog" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="max-width:400px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Habit</h3>
            <button type="button" class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form onsubmit="handleSubmit(event)">
                <input type="hidden" id="itemId" value="0">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Habit Name <span class="text-danger">*</span></label>
                    <input type="text" id="itemName" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required placeholder="e.g., Read 30 mins">
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Frequency</label>
                    <input type="text" id="itemFreq" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="e.g., Daily, 3x/week">
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

function fetchItems() { fetch('/api/workspace/habits.php').then(r=>r.json()).then(d=>{if(d.success){items=d.data; render();} }); }

function render() {
    const tbody = document.getElementById('tableBody');
    if(!items.length) { 
        tbody.innerHTML = `<div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined text-primary" style="font-size: 32px; margin-bottom: 12px; display: block;">repeat</span>
            No habits defined.
        </div>`; 
        return; 
    }
    tbody.innerHTML = items.map(i => `
    <div class="pw-list-item bg-lowest">
        <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 48px; height: 48px; padding: 6px; background: var(--pw-surface-container); border-radius: 8px;">
                <span class="material-symbols-outlined text-primary" style="font-size: 24px;">check_circle</span>
            </div>
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; min-height: 48px;">
                <div style="font-size: 16px; font-weight: 600; color: var(--pw-text-main); margin-bottom: 4px;">${escapeHtml(i.name)}</div>
                <div style="font-size: 13px; color: var(--pw-text-muted); display: flex; gap: 16px; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">update</span> ${escapeHtml(i.frequency||'—')}</span>
                </div>
            </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 8px; min-width: 100px; justify-content: flex-end;">
            <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);" onclick="editItem(${i.id})"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
            <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-danger);" onclick="deleteItem(${i.id})"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
        </div>
    </div>`).join('');
}

function openModal(item=null) {
    if(item) {
        document.getElementById('modalTitle').textContent='Edit Habit';
        document.getElementById('itemId').value=item.id;
        document.getElementById('itemName').value=item.name;
        document.getElementById('itemFreq').value=item.frequency||'';
    } else {
        document.getElementById('modalTitle').textContent='Add Habit';
        document.querySelector('form').reset();
        document.getElementById('itemId').value='0';
    }
    document.getElementById('itemModal').style.display='block';
}

function closeModal() { document.getElementById('itemModal').style.display='none'; }
function editItem(id) { openModal(items.find(i=>i.id===id)); }
function deleteItem(id) {
    if(!confirm('Delete this habit?')) return;
    fetch('/api/workspace/habits.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({action:'delete_habit',id:id}) }).then(r=>r.json()).then(d=>{if(d.success)fetchItems();});
}
function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value,10);
    const p = { action:id>0?'update_habit':'create_habit', id:id, name:document.getElementById('itemName').value, frequency:document.getElementById('itemFreq').value };
    fetch('/api/workspace/habits.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(p) }).then(r=>r.json()).then(d=>{if(d.success){closeModal();fetchItems();}});
}
function escapeHtml(s) { return (s+'').replace(/[&<"']/g, m=>({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>
<?php require __DIR__.'/partials/layout_bottom.php'; ?>
