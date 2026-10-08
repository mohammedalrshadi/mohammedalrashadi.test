<?php
// ============================================================
// WORKSPACE: READING LIST
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-reading';
$pageTitle = 'Study & Knowledge - Reading List';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Reading List</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus"></i> <span>Add Item</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Growth & Learning</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Reading List</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Track books, articles, papers, and courses.</p>
        </div>
    </section>

    <div class="pw-card" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
            <div class="pw-section-title">
                <span class="material-symbols-outlined text-primary" style="font-size: 16px;">menu_book</span>
                Reading Queue
            </div>
        </div>
        <div class="pw-card-list" id="tableBody">
            <div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading reading list...
            </div>
        </div>
    </div>
</div>

<div id="itemModal" class="modal" role="dialog" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Reading Item</h3>
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
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">URL (optional)</label>
                    <input type="url" id="itemUrl" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Type</label>
                        <select id="itemType" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                            <option value="book">Book</option>
                            <option value="article">Article</option>
                            <option value="paper">Paper</option>
                            <option value="doc">Documentation</option>
                            <option value="course">Course</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Status</label>
                        <select id="itemStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                            <option value="want_to_read">Want to Read</option>
                            <option value="reading">Reading</option>
                            <option value="completed">Completed</option>
                            <option value="paused">Paused</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
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

function fetchItems() { fetch('/api/workspace/reading.php').then(r=>r.json()).then(d=>{if(d.success){items=d.data; render();} }); }

function getBadge(status) {
    if(status==='reading') return `<span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Reading</span>`;
    if(status==='completed') return `<span style="background: rgba(16, 185, 129, 0.1); color: rgb(16, 185, 129); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Completed</span>`;
    return `<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">${status.replace(/_/g,' ')}</span>`;
}

function render() {
    const tbody = document.getElementById('tableBody');
    if(!items.length) { 
        tbody.innerHTML = `<div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined text-primary" style="font-size: 32px; margin-bottom: 12px; display: block;">menu_book</span>
            No reading items saved.
        </div>`; 
        return; 
    }
    tbody.innerHTML = items.map(i => `
    <div class="pw-list-item bg-lowest" style="${i.status==='completed'?'opacity:0.6;':''}">
        <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 48px; height: 48px; padding: 6px; background: var(--pw-surface-container); border-radius: 8px;">
                <span class="material-symbols-outlined text-primary" style="font-size: 24px;">book</span>
            </div>
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; min-height: 48px;">
                <div style="font-size: 16px; font-weight: 600; color: var(--pw-text-main); margin-bottom: 4px;">
                    ${i.url ? `<a href="${escapeHtml(i.url)}" target="_blank" style="color:inherit; text-decoration:none;">${escapeHtml(i.title)} <span class="material-symbols-outlined" style="font-size:12px; color:var(--pw-text-muted); margin-left:2px;">open_in_new</span></a>` : escapeHtml(i.title)}
                </div>
                <div style="font-size: 13px; color: var(--pw-text-muted); display: flex; gap: 16px; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">category</span> <span class="font-mono" style="text-transform:uppercase;">${i.type}</span></span>
                </div>
            </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 16px; min-width: 150px; justify-content: flex-end;">
            ${getBadge(i.status)}
            <div style="display: flex; gap: 4px;">
                <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);" onclick="editItem(${i.id})"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-danger);" onclick="deleteItem(${i.id})"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
            </div>
        </div>
    </div>`).join('');
}

function openModal(item=null) {
    if(item) {
        document.getElementById('modalTitle').textContent='Edit Reading Item';
        document.getElementById('itemId').value=item.id;
        document.getElementById('itemTitle').value=item.title;
        document.getElementById('itemUrl').value=item.url||'';
        document.getElementById('itemType').value=item.type;
        document.getElementById('itemStatus').value=item.status;
    } else {
        document.getElementById('modalTitle').textContent='Add Reading Item';
        document.querySelector('form').reset();
        document.getElementById('itemId').value='0';
    }
    document.getElementById('itemModal').style.display='block';
}

function closeModal() { document.getElementById('itemModal').style.display='none'; }
function editItem(id) { openModal(items.find(i=>i.id===id)); }
function deleteItem(id) {
    if(!confirm('Delete this item?')) return;
    fetch('/api/workspace/reading.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({action:'delete_reading',id:id}) }).then(r=>r.json()).then(d=>{if(d.success)fetchItems();});
}
function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value,10);
    const p = { action:id>0?'update_reading':'create_reading', id:id, title:document.getElementById('itemTitle').value, url:document.getElementById('itemUrl').value, type:document.getElementById('itemType').value, status:document.getElementById('itemStatus').value };
    fetch('/api/workspace/reading.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(p) }).then(r=>r.json()).then(d=>{if(d.success){closeModal();fetchItems();}});
}
function escapeHtml(s) { return (s+'').replace(/[&<"']/g, m=>({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>
<?php require __DIR__.'/partials/layout_bottom.php'; ?>
