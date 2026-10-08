<?php
// ============================================================
// WORKSPACE: TASKS
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-tasks';
$pageTitle = 'Tasks';
require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Tasks</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openTaskModal()">
            <i class="fas fa-plus"></i> New Task
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Daily Focus</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Inbox &amp; Backlog</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Organize your next actions, capture ideas, and move forward.</p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <select id="taskFilter" class="pw-btn" style="background: var(--pw-surface); cursor: pointer;" onchange="fetchTasks()">
                <option value="active">Active Only</option>
                <option value="completed">Completed Only</option>
                <option value="all">All Tasks</option>
            </select>
            <button type="button" class="pw-btn pw-btn-primary" onclick="openTaskModal()">
                <span class="material-symbols-outlined" style="font-size: 18px;">add</span> New Task
            </button>
        </div>
    </section>

    <div class="pw-card">
        <div class="pw-card-header" style="border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
            <h2 class="pw-card-title">All Tasks</h2>
        </div>
        <div class="pw-card-list" id="tasksTableBody">
            <div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading tasks...
            </div>
        </div>
    </div>
</div>

<!-- TASK MODAL (Reusing global modal structure but styled for PW) -->
<div id="taskModal" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="closeTaskModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 class="font-display" style="font-size: 20px; font-weight: 700;" id="taskModalTitle">Add Task</h3>
            <button type="button" onclick="closeTaskModal()" style="background:none; border:none; color:var(--pw-text-muted); cursor:pointer; font-size:16px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="taskForm" onsubmit="handleTaskSubmit(event)">
            <input type="hidden" id="taskId" value="0">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Task Title</label>
                <input type="text" id="taskTitle" required style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-family: var(--pw-font-sans); font-size: 14px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Description</label>
                <textarea id="taskDescription" rows="3" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-family: var(--pw-font-sans); font-size: 14px;"></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Status</label>
                    <select id="taskStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="deferred">Deferred</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Priority</label>
                    <select id="taskPriority" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Due Date</label>
                <input type="date" id="taskDueDate" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-family: var(--pw-font-sans); font-size: 14px;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="pw-btn" onclick="closeTaskModal()">Cancel</button>
                <button type="submit" class="pw-btn pw-btn-primary" id="taskSubmitBtn">Save Task</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentTasks = [];

document.addEventListener('DOMContentLoaded', () => {
    fetchTasks();
});

function fetchTasks() {
    const filter = document.getElementById('taskFilter').value;
    
    fetch('/api/workspace/tasks.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                currentTasks = data.data;
                let filtered = currentTasks;
                if (filter === 'active') filtered = currentTasks.filter(t => t.status !== 'completed');
                else if (filter === 'completed') filtered = currentTasks.filter(t => t.status === 'completed');
                renderTasks(filtered);
            }
        });
}

function getPriorityBadge(p) {
    if(p==='urgent') return '<span style="background: var(--pw-danger-bg); color: var(--pw-danger); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Urgent</span>';
    if(p==='high') return '<span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">High</span>';
    if(p==='medium') return '<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Med</span>';
    return '<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Low</span>';
}

function renderTasks(tasks) {
    const tbody = document.getElementById('tasksTableBody');
    if (tasks.length === 0) {
        tbody.innerHTML = `
        <div style="padding: 48px 24px; text-align: center; color: var(--pw-text-muted);">
            <div style="margin-bottom: 16px;"><span class="material-symbols-outlined" style="font-size: 32px; color: var(--pw-border-hover);">task_alt</span></div>
            <div style="font-size: 14px; font-weight: 500;">Inbox Zero.</div>
            <div style="font-size: 13px; margin-top: 4px;">Time to relax or plan your next move.</div>
        </div>`;
        return;
    }
    tbody.innerHTML = tasks.map(t => {
        const isCompleted = t.status === 'completed';
        return `
            <div class="pw-list-item bg-lowest" style="${isCompleted ? 'opacity:0.6' : ''}">
                <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; margin-top: 4px;">
                        <input type="checkbox" ${isCompleted ? 'checked' : ''} onchange="toggleTaskStatus(${t.id}, this.checked)" style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--pw-primary);">
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:15px; font-weight:600; margin-bottom:4px; color: var(--pw-text-main); ${isCompleted ? 'text-decoration:line-through' : ''}">${escapeHtml(t.title)}</div>
                        <div style="font-size:12px; color:var(--pw-text-muted); display:flex; gap:16px; align-items: center;">
                            ${t.due_date ? `<span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">schedule</span> ${t.due_date.substring(0,10)}</span>` : ''}
                            <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">info</span> <span style="text-transform:capitalize;">${t.status.replace('_',' ')}</span></span>
                        </div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:16px;">
                    ${getPriorityBadge(t.priority)}
                    <div style="display:flex; gap:8px;">
                        <button class="pw-btn" style="padding:4px 8px; font-size:11px; background: transparent; border: none; color: var(--pw-text-muted);" onclick="editTask(${t.id})"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                        <button class="pw-btn" style="padding:4px 8px; font-size:11px; background: transparent; border: none; color: var(--pw-danger);" onclick="deleteTask(${t.id})"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function toggleTaskStatus(id, isChecked) {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('/api/workspace/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ action: 'toggle_status', id: id, status: isChecked ? 'completed' : 'pending' })
    }).then(r => r.json()).then(d => { if(d.success) fetchTasks(); });
}

function openTaskModal(task = null) {
    const modal = document.getElementById('taskModal');
    if (task) {
        document.getElementById('taskModalTitle').textContent = 'Edit Task';
        document.getElementById('taskId').value = task.id;
        document.getElementById('taskTitle').value = task.title;
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskStatus').value = task.status || 'pending';
        document.getElementById('taskPriority').value = task.priority || 'medium';
        document.getElementById('taskDueDate').value = task.due_date ? task.due_date.substring(0, 10) : '';
    } else {
        document.getElementById('taskModalTitle').textContent = 'Add Task';
        document.getElementById('taskForm').reset();
        document.getElementById('taskId').value = '0';
    }
    modal.style.display = 'flex';
}
function closeTaskModal() { document.getElementById('taskModal').style.display = 'none'; }
function editTask(id) { const task = currentTasks.find(t => t.id === id); if (task) openTaskModal(task); }

function handleTaskSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('taskId').value, 10);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const payload = {
        action: id > 0 ? 'update_task' : 'create_task',
        id: id,
        title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDescription').value,
        status: document.getElementById('taskStatus').value,
        priority: document.getElementById('taskPriority').value,
        due_date: document.getElementById('taskDueDate').value,
    };
    fetch('/api/workspace/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.success) { closeTaskModal(); fetchTasks(); }
        else { alert('Failed to save task'); }
    });
}

function deleteTask(id) {
    if (!confirm('Are you sure?')) return;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('/api/workspace/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ action: 'delete_task', id: id })
    }).then(r => r.json()).then(d => { if(d.success) fetchTasks(); });
}

function escapeHtml(str) { return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
