<?php
// ============================================================
// WORKSPACE: NOTES
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-notes';
$pageTitle = 'Notes';
require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Notes</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openNoteModal()">
            <i class="fas fa-plus"></i> New Note
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Growth & Learning</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Knowledge Base</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Capture ideas, meeting notes, and unformatted thoughts.</p>
        </div>
    </section>

    <div id="notesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
        <div style="grid-column: 1 / -1; text-align: center; padding: 48px; color: var(--pw-text-muted);">
            <i class="fas fa-spinner fa-spin"></i> Loading notes...
        </div>
    </div>
</div>

<!-- NOTE MODAL -->
<div id="noteModal" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="closeNoteModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 600px; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="font-size: 16px; font-weight: 600;" id="noteModalTitle">Write Note</h3>
            <button type="button" onclick="closeNoteModal()" style="background:none; border:none; color:var(--pw-text-muted); cursor:pointer; font-size:16px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="noteForm" onsubmit="handleNoteSubmit(event)">
            <input type="hidden" id="noteId" value="0">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Title</label>
                <input type="text" id="noteTitle" required style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Content</label>
                <textarea id="noteContent" rows="12" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-family: var(--pw-font-mono); font-size: 13px; line-height: 1.5;"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="pw-btn" onclick="closeNoteModal()">Cancel</button>
                <button type="submit" class="pw-btn pw-btn-primary" id="noteSubmitBtn">Save Note</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentNotes = [];

document.addEventListener('DOMContentLoaded', () => { fetchNotes(); });

function fetchNotes() {
    fetch('/api/workspace/notes.php').then(r => r.json()).then(data => {
        if (data.success) { currentNotes = data.data; renderNotes(currentNotes); }
    });
}

function renderNotes(notes) {
    const grid = document.getElementById('notesGrid');
    if (notes.length === 0) {
        grid.innerHTML = `
        <div style="grid-column: 1 / -1; padding: 48px; text-align: center; color: var(--pw-text-muted);">
            <div style="margin-bottom: 16px;"><span class="material-symbols-outlined" style="font-size: 32px; color: var(--pw-border-hover);">edit_note</span></div>
            <div style="font-size: 14px; font-weight: 500;">No notes found.</div>
            <div style="font-size: 13px; margin-top: 4px;">Start building your knowledge base.</div>
        </div>`;
        return;
    }
    grid.innerHTML = notes.map(n => {
        let preview = n.content || '';
        if (preview.length > 150) preview = preview.substring(0, 150) + '...';
        const date = n.updated_at ? n.updated_at.substring(0, 10) : 'Just now';

        return `
        <div class="pw-card" style="padding:0; display:flex; flex-direction:column; justify-content:space-between; height: 100%;">
            <div>
                <div style="padding: 16px; border-bottom: 1px solid var(--pw-border-subtle); display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; background: var(--pw-surface-container); border-radius: var(--pw-radius-lg) var(--pw-radius-lg) 0 0;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 600; line-height: 1.4; color: var(--pw-text-main);">${escapeHtml(n.title)}</h3>
                    <div style="display: flex; gap: 4px; flex-shrink: 0;">
                        <button type="button" class="pw-btn" style="padding: 4px; font-size: 14px; background: transparent; border: none; color: var(--pw-text-muted);" onclick="editNote(${n.id})"><span class="material-symbols-outlined" style="font-size: 16px;">edit</span></button>
                        <button type="button" class="pw-btn" style="padding: 4px; font-size: 14px; background: transparent; border: none; color: var(--pw-danger);" onclick="deleteNote(${n.id})"><span class="material-symbols-outlined" style="font-size: 16px;">delete</span></button>
                    </div>
                </div>
                <div style="padding: 16px; color: var(--pw-text-secondary); font-size: 13px; line-height: 1.6; white-space: pre-wrap; font-family: var(--pw-font-mono); overflow: hidden;">${escapeHtml(preview)}</div>
            </div>
            <div style="padding: 12px 16px; border-top: 1px solid var(--pw-border-subtle); font-size: 11px; color: var(--pw-text-muted); display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 var(--pw-radius-lg) var(--pw-radius-lg);">
                <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">schedule</span> ${date}</span>
                ${n.entity_type ? `<span style="font-family: var(--pw-font-mono); background: var(--pw-surface-container); padding: 2px 6px; border-radius: 4px; border: 1px solid var(--pw-border-subtle); text-transform: uppercase;">${escapeHtml(n.entity_type)}</span>` : ''}
            </div>
        </div>
    `}).join('');
}

function openNoteModal(note = null) {
    const modal = document.getElementById('noteModal');
    if (note) {
        document.getElementById('noteModalTitle').textContent = 'Edit Note';
        document.getElementById('noteId').value = note.id;
        document.getElementById('noteTitle').value = note.title;
        document.getElementById('noteContent').value = note.content || '';
    } else {
        document.getElementById('noteModalTitle').textContent = 'Write Note';
        document.getElementById('noteForm').reset();
        document.getElementById('noteId').value = '0';
    }
    modal.style.display = 'flex';
}
function closeNoteModal() { document.getElementById('noteModal').style.display = 'none'; }
function editNote(id) { const n = currentNotes.find(x => x.id === id); if (n) openNoteModal(n); }

function handleNoteSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('noteId').value, 10);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const payload = {
        action: id > 0 ? 'update_note' : 'create_note',
        id: id,
        title: document.getElementById('noteTitle').value,
        content: document.getElementById('noteContent').value,
    };
    fetch('/api/workspace/notes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.success) { closeNoteModal(); fetchNotes(); }
        else { alert('Failed to save note'); }
    });
}

function deleteNote(id) {
    if (!confirm('Are you sure?')) return;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('/api/workspace/notes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ action: 'delete_note', id: id })
    }).then(r => r.json()).then(d => { if(d.success) fetchNotes(); });
}

function escapeHtml(str) { return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
