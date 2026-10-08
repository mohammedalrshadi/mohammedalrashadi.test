<?php
// ============================================================
// WORKSPACE: PROJECTS
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-projects';
$pageTitle = 'Projects';
require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Projects</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openProjectModal()">
            <i class="fas fa-plus"></i> Add Project
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Engineering & Builders</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Active Projects</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Track private ideas and software development.</p>
        </div>
    </section>

    <div class="pw-card" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
            <div class="pw-section-title">
                <span class="material-symbols-outlined text-primary" style="font-size: 16px;">terminal</span>
                Project Portfolio
            </div>
        </div>
        <div class="pw-card-list" id="projectsTableBody">
            <div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading projects...
            </div>
        </div>
    </div>
</div>

<!-- PROJECT MODAL -->
<div id="projectModal" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="closeProjectModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="font-size: 16px; font-weight: 600;" id="projectModalTitle">Add Project</h3>
            <button type="button" onclick="closeProjectModal()" style="background:none; border:none; color:var(--pw-text-muted); cursor:pointer; font-size:16px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="projectForm" onsubmit="handleProjectSubmit(event)">
            <input type="hidden" id="projectId" value="0">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Project Name</label>
                <input type="text" id="projectName" required style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Description</label>
                <textarea id="projectDescription" rows="3" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;"></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Status</label>
                    <select id="projectStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                        <option value="planned">Planned</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Technologies</label>
                    <input type="text" id="projectTechnologies" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Start Date</label>
                    <input type="date" id="projectStartDate" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Target Date</label>
                    <input type="date" id="projectTargetDate" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Repository URL</label>
                    <input type="url" id="projectRepo" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Demo URL</label>
                    <input type="url" id="projectDemo" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="pw-btn" onclick="closeProjectModal()">Cancel</button>
                <button type="submit" class="pw-btn pw-btn-primary" id="projectSubmitBtn">Save Project</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentProjects = [];

document.addEventListener('DOMContentLoaded', () => { fetchProjects(); });

function fetchProjects() {
    fetch('/api/workspace/projects.php').then(r => r.json()).then(data => {
        if (data.success) { currentProjects = data.data; renderProjects(currentProjects); }
    });
}

function getStatusBadge(s) {
    if(s==='active') return '<span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Active</span>';
    if(s==='completed') return '<span style="background: rgba(16, 185, 129, 0.1); color: rgb(16, 185, 129); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Completed</span>';
    if(s==='paused') return '<span style="background: var(--pw-warning-bg, #FEF3C7); color: var(--pw-warning, #D97706); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Paused</span>';
    return '<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Planned</span>';
}

function renderProjects(projects) {
    const tbody = document.getElementById('projectsTableBody');
    if (projects.length === 0) {
        tbody.innerHTML = `<div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined text-primary" style="font-size: 32px; margin-bottom: 12px; display: block;">terminal</span>
            No projects found.
        </div>`;
        return;
    }
    tbody.innerHTML = projects.map(p => {
        let timeline = '—';
        if (p.start_date && p.target_date) timeline = `${p.start_date.substring(0, 4)} - ${p.target_date.substring(0, 4)}`;
        else if (p.start_date) timeline = `Started ${p.start_date.substring(0, 4)}`;
        
        return `
        <div class="pw-list-item bg-lowest" style="${p.status==='completed'?'opacity:0.7':''}">
            <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 48px; height: 48px; padding: 6px; background: var(--pw-surface-container); border-radius: 8px;">
                    <span class="material-symbols-outlined text-primary" style="font-size: 24px;">code_blocks</span>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 16px; font-weight: 600; color: var(--pw-text-main); margin-bottom: 4px;">${escapeHtml(p.name)}</div>
                    ${p.description ? `<div style="font-size: 13px; color: var(--pw-text-muted); margin-bottom: 8px; max-width: 500px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(p.description)}</div>` : ''}
                    <div style="font-size: 12px; color: var(--pw-text-muted); display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                        <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">terminal</span> <span class="font-mono">${escapeHtml(p.technologies || 'None')}</span></span>
                        <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">calendar_today</span> ${timeline}</span>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 12px; min-width: 150px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    ${getStatusBadge(p.status)}
                    <div style="display: flex; gap: 4px;">
                        ${p.repo_url ? `<a href="${escapeHtml(p.repo_url)}" target="_blank" class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);"><span class="material-symbols-outlined" style="font-size: 18px;">code</span></a>` : ''}
                        ${p.demo_url ? `<a href="${escapeHtml(p.demo_url)}" target="_blank" class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);"><span class="material-symbols-outlined" style="font-size: 18px;">open_in_new</span></a>` : ''}
                        <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);" onclick="editProject(${p.id})"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                        <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-danger);" onclick="deleteProject(${p.id})"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');
}

function openProjectModal(project = null) {
    const modal = document.getElementById('projectModal');
    if (project) {
        document.getElementById('projectModalTitle').textContent = 'Edit Project';
        document.getElementById('projectId').value = project.id;
        document.getElementById('projectName').value = project.name;
        document.getElementById('projectDescription').value = project.description || '';
        document.getElementById('projectStatus').value = project.status || 'planned';
        document.getElementById('projectTechnologies').value = project.technologies || '';
        document.getElementById('projectStartDate').value = project.start_date ? project.start_date.substring(0, 10) : '';
        document.getElementById('projectTargetDate').value = project.target_date ? project.target_date.substring(0, 10) : '';
        document.getElementById('projectRepo').value = project.repo_url || '';
        document.getElementById('projectDemo').value = project.demo_url || '';
    } else {
        document.getElementById('projectModalTitle').textContent = 'Add Project';
        document.getElementById('projectForm').reset();
        document.getElementById('projectId').value = '0';
    }
    modal.style.display = 'flex';
}
function closeProjectModal() { document.getElementById('projectModal').style.display = 'none'; }
function editProject(id) { const p = currentProjects.find(x => x.id === id); if (p) openProjectModal(p); }

function handleProjectSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('projectId').value, 10);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const payload = {
        action: id > 0 ? 'update_project' : 'create_project',
        id: id,
        name: document.getElementById('projectName').value,
        description: document.getElementById('projectDescription').value,
        status: document.getElementById('projectStatus').value,
        technologies: document.getElementById('projectTechnologies').value,
        start_date: document.getElementById('projectStartDate').value,
        target_date: document.getElementById('projectTargetDate').value,
        repo_url: document.getElementById('projectRepo').value,
        demo_url: document.getElementById('projectDemo').value,
    };
    fetch('/api/workspace/projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.success) { closeProjectModal(); fetchProjects(); }
        else { alert('Failed to save project'); }
    });
}

function deleteProject(id) {
    if (!confirm('Are you sure?')) return;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('/api/workspace/projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ action: 'delete_project', id: id })
    }).then(r => r.json()).then(d => { if(d.success) fetchProjects(); });
}

function escapeHtml(str) { return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
