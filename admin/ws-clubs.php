<?php
// ============================================================
// WORKSPACE: CLUBS & ORGS
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

$activeNav = 'ws-clubs';
$pageTitle = 'Campus Life - Clubs & Organizations';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Clubs & Organizations</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openModal()">
            <i class="fas fa-plus" aria-hidden="true"></i>
            <span>Add Organization</span>
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Community & Leadership</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Clubs & Organizations</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Manage your university clubs, extracurriculars, and community involvement.</p>
        </div>
    </section>

    <div class="pw-card" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
            <div class="pw-section-title">
                <span class="material-symbols-outlined text-primary" style="font-size: 16px;">groups</span>
                My Organizations
            </div>
        </div>
        <div class="pw-card-list" id="tableBody">
            <div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading clubs...
            </div>
        </div>
    </div>
</div>

<!-- MODAL -->
<div id="itemModal" class="modal" role="dialog" aria-hidden="true" style="display: none;">
    <div class="modal-backdrop" onclick="closeModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Add Organization</h3>
            <button type="button" class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="itemForm" onsubmit="handleSubmit(event)">
                <input type="hidden" id="itemId" value="0">
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Club / Organization Name <span class="text-danger">*</span></label>
                    <input type="text" id="itemName" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Umbrella Organization</label>
                        <input type="text" id="itemOrg" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="e.g., Student Union">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">My Role</label>
                        <input type="text" id="itemRole" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" placeholder="e.g., President">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Status</label>
                    <select id="itemStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                        <option value="committee">Committee Member</option>
                        <option value="volunteer">Volunteer</option>
                        <option value="member" selected>Member</option>
                        <option value="following">Following</option>
                        <option value="interested">Interested</option>
                        <option value="former">Former</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Start Date</label>
                        <input type="date" id="itemStart" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                    </div>
                    <div class="form-group">
                        <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">End Date</label>
                        <input type="date" id="itemEnd" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Description / Notes</label>
                    <textarea id="itemDesc" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;" rows="2"></textarea>
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
    fetch('/api/workspace/clubs.php')
        .then(r => r.json())
        .then(d => { if(d.success) { items = d.data; renderItems(); } })
        .catch(console.error);
}

function getBadge(status) {
    let color = 'var(--pw-text-secondary)';
    let bg = 'var(--pw-surface-container)';
    
    if (status === 'committee' || status === 'volunteer') { bg = 'var(--pw-success-bg)'; color = 'var(--pw-success)'; }
    if (status === 'member') { bg = 'var(--pw-accent-bg)'; color = 'var(--pw-accent)'; }
    
    return `<span style="padding: 2px 6px; font-size: 9px; font-weight: 700; letter-spacing: 0.05em; border-radius: 4px; background: ${bg}; color: ${color};">${status.toUpperCase()}</span>`;
}

function renderItems() {
    const tbody = document.getElementById('tableBody');
    if (items.length === 0) {
        tbody.innerHTML = `<div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined text-primary" style="font-size: 32px; margin-bottom: 12px; display: block;">groups</span>
            No clubs added yet.
        </div>`;
        return;
    }
    
    tbody.innerHTML = items.map(i => {
        let timeline = (i.start_date ? i.start_date.substring(0,4) : '') + (i.end_date ? ' - ' + i.end_date.substring(0,4) : (i.start_date ? ' - Present' : '—'));
        return `
        <div class="pw-list-item bg-lowest" style="${i.status==='former' ? 'opacity:0.6;' : ''}">
            <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 48px; height: 48px; padding: 6px; background: var(--pw-surface-container); border-radius: 8px;">
                    <span class="material-symbols-outlined text-primary" style="font-size: 24px;">group</span>
                </div>
                <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; min-height: 48px;">
                    <div style="font-size: 16px; font-weight: 600; color: var(--pw-text-main); margin-bottom: 4px;">${escapeHtml(i.name)}</div>
                    <div style="font-size: 13px; color: var(--pw-text-muted); display: flex; gap: 16px; align-items: center;">
                        <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">badge</span> ${escapeHtml(i.role || 'Member')}</span>
                        ${i.organization ? `<span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">account_balance</span> ${escapeHtml(i.organization)}</span>` : ''}
                    </div>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 16px; min-width: 150px; justify-content: flex-end;">
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                    ${getBadge(i.status)}
                    <span class="font-mono" style="font-size: 11px; color: var(--pw-text-secondary);">${timeline}</span>
                </div>
                <div style="display: flex; gap: 4px;">
                    <button type="button" class="pw-btn" onclick="editItem(${i.id})" title="Edit" style="padding: 4px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                    <button type="button" class="pw-btn text-danger" onclick="deleteItem(${i.id})" title="Delete" style="padding: 4px; font-size: 11px; background: transparent; border: none; color: var(--pw-danger);"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function openModal(item = null) {
    if (item) {
        document.getElementById('modalTitle').textContent = 'Edit Organization';
        document.getElementById('itemId').value = item.id;
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemOrg').value = item.organization || '';
        document.getElementById('itemRole').value = item.role || '';
        document.getElementById('itemStatus').value = item.status;
        document.getElementById('itemStart').value = item.start_date || '';
        document.getElementById('itemEnd').value = item.end_date || '';
        document.getElementById('itemDesc').value = item.description || '';
    } else {
        document.getElementById('modalTitle').textContent = 'Add Organization';
        document.getElementById('itemForm').reset();
        document.getElementById('itemId').value = '0';
    }
    document.getElementById('itemModal').style.display = 'block';
}

function closeModal() { document.getElementById('itemModal').style.display = 'none'; }
function editItem(id) { openModal(items.find(i => i.id === id)); }

function handleSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('itemId').value, 10);
    const payload = {
        action: id > 0 ? 'update_club' : 'create_club', id: id,
        name: document.getElementById('itemName').value,
        organization: document.getElementById('itemOrg').value,
        role: document.getElementById('itemRole').value,
        status: document.getElementById('itemStatus').value,
        start_date: document.getElementById('itemStart').value,
        end_date: document.getElementById('itemEnd').value,
        description: document.getElementById('itemDesc').value,
    };
    
    fetch('/api/workspace/clubs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if(d.success) { closeModal(); fetchItems(); showToast(d.message, 'success'); }
        else showToast(d.message, 'error');
    });
}

function deleteItem(id) {
    if(!confirm('Delete this club?')) return;
    fetch('/api/workspace/clubs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ action: 'delete_club', id: id })
    }).then(r => r.json()).then(d => {
        if(d.success) { fetchItems(); showToast(d.message, 'success'); }
    });
}

function escapeHtml(str) {
    return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
