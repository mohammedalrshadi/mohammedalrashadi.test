<?php
// ============================================================
// WORKSPACE: COURSES
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-courses';
$pageTitle = 'Courses';
require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Courses</span>
    </div>
    <div class="pw-header-actions">
        <button type="button" class="pw-btn pw-btn-primary" onclick="openCourseModal()">
            <i class="fas fa-plus"></i> Add Course
        </button>
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Growth & Learning</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Course Curriculum</h1>
            <p class="pw-page-subtitle" style="margin: 0;">Manage your academic semester and learning progress.</p>
        </div>
    </section>

    <div class="pw-card" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
            <div class="pw-section-title">
                <span class="material-symbols-outlined text-primary" style="font-size: 16px;">school</span>
                Course Curriculum
            </div>
        </div>
        <div class="pw-card-list" id="coursesTableBody">
            <div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
                <i class="fas fa-spinner fa-spin"></i> Loading courses...
            </div>
        </div>
    </div>
</div>

<!-- COURSE MODAL -->
<div id="courseModal" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="closeCourseModal()"></div>
    <div class="modal-dialog" style="border-radius: var(--pw-radius-md); max-width: 500px; padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="font-size: 16px; font-weight: 600;" id="courseModalTitle">Add Course</h3>
            <button type="button" onclick="closeCourseModal()" style="background:none; border:none; color:var(--pw-text-muted); cursor:pointer; font-size:16px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="courseForm" onsubmit="handleCourseSubmit(event)">
            <input type="hidden" id="courseId" value="0">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Course Code</label>
                <input type="text" id="courseCode" required style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Course Name</label>
                <input type="text" id="courseName" required style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Semester</label>
                    <input type="text" id="courseSemester" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Status</label>
                    <select id="courseStatus" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
                        <option value="planned">Planned</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="dropped">Dropped</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Instructor</label>
                <input type="text" id="courseInstructor" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 500; margin-bottom: 8px; display: block;">Progress (%)</label>
                <input type="number" id="courseProgress" min="0" max="100" value="0" style="width: 100%; padding: 10px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius-sm); font-size: 14px;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" class="pw-btn" onclick="closeCourseModal()">Cancel</button>
                <button type="submit" class="pw-btn pw-btn-primary" id="courseSubmitBtn">Save Course</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentCourses = [];

document.addEventListener('DOMContentLoaded', () => { fetchCourses(); });

function fetchCourses() {
    fetch('/api/workspace/courses.php').then(r => r.json()).then(data => {
        if (data.success) { currentCourses = data.data; renderCourses(currentCourses); }
    });
}

function getStatusBadge(s) {
    if(s==='in_progress') return '<span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">In Progress</span>';
    if(s==='completed') return '<span style="background: rgba(16, 185, 129, 0.1); color: rgb(16, 185, 129); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Completed</span>';
    if(s==='dropped') return '<span style="background: var(--pw-danger-bg); color: var(--pw-danger); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Dropped</span>';
    return '<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Planned</span>';
}

function renderCourses(courses) {
    const tbody = document.getElementById('coursesTableBody');
    if (courses.length === 0) {
        tbody.innerHTML = `<div style="padding: 32px; text-align: center; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined text-primary" style="font-size: 32px; margin-bottom: 12px; display: block;">school</span>
            No courses found.
        </div>`;
        return;
    }
    tbody.innerHTML = courses.map(c => `
        <div class="pw-list-item bg-lowest">
            <div style="display: flex; gap: 16px; align-items: flex-start; flex: 1;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 70px; padding: 6px; background: var(--pw-surface-container); border-radius: 6px;">
                    <span class="font-mono text-primary" style="font-size: 12px; font-weight: 700;">${escapeHtml(c.code)}</span>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 15px; font-weight: 600; color: var(--pw-text-main); margin-bottom: 4px;">${escapeHtml(c.name)}</div>
                    <div style="font-size: 12px; color: var(--pw-text-muted); display: flex; gap: 16px; align-items: center;">
                        ${c.instructor ? `<span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">person</span> ${escapeHtml(c.instructor)}</span>` : ''}
                        <span style="display: flex; align-items: center; gap: 4px;"><span class="material-symbols-outlined" style="font-size: 14px;">calendar_today</span> ${escapeHtml(c.semester || 'TBA')}</span>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; min-width: 150px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    ${getStatusBadge(c.status)}
                    <div style="display: flex; gap: 4px;">
                        <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-text-muted);" onclick="editCourse(${c.id})"><span class="material-symbols-outlined" style="font-size: 18px;">edit</span></button>
                        <button class="pw-btn" style="padding: 4px 8px; font-size: 11px; background: transparent; border: none; color: var(--pw-danger);" onclick="deleteCourse(${c.id})"><span class="material-symbols-outlined" style="font-size: 18px;">delete</span></button>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; width: 100%; justify-content: flex-end;">
                    <span class="font-mono" style="font-size: 10px; font-weight: 600; color: var(--pw-text-secondary);">${c.progress}%</span>
                    <div style="flex: 0 0 60px; height: 4px; background: var(--pw-border-subtle); border-radius: 2px; overflow: hidden;">
                        <div style="height: 100%; width: ${c.progress}%; background: ${c.progress === 100 ? 'rgb(16, 185, 129)' : 'var(--pw-primary)'};"></div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function openCourseModal(course = null) {
    const modal = document.getElementById('courseModal');
    if (course) {
        document.getElementById('courseModalTitle').textContent = 'Edit Course';
        document.getElementById('courseId').value = course.id;
        document.getElementById('courseCode').value = course.code;
        document.getElementById('courseName').value = course.name;
        document.getElementById('courseSemester').value = course.semester || '';
        document.getElementById('courseStatus').value = course.status || 'planned';
        document.getElementById('courseInstructor').value = course.instructor || '';
        document.getElementById('courseProgress').value = course.progress || 0;
    } else {
        document.getElementById('courseModalTitle').textContent = 'Add Course';
        document.getElementById('courseForm').reset();
        document.getElementById('courseId').value = '0';
    }
    modal.style.display = 'flex';
}
function closeCourseModal() { document.getElementById('courseModal').style.display = 'none'; }
function editCourse(id) { const c = currentCourses.find(c => c.id === id); if (c) openCourseModal(c); }

function handleCourseSubmit(e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('courseId').value, 10);
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const payload = {
        action: id > 0 ? 'update_course' : 'create_course',
        id: id,
        code: document.getElementById('courseCode').value,
        name: document.getElementById('courseName').value,
        semester: document.getElementById('courseSemester').value,
        status: document.getElementById('courseStatus').value,
        instructor: document.getElementById('courseInstructor').value,
        progress: document.getElementById('courseProgress').value,
    };
    fetch('/api/workspace/courses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(d => {
        if (d.success) { closeCourseModal(); fetchCourses(); }
        else { alert('Failed to save course'); }
    });
}

function deleteCourse(id) {
    if (!confirm('Are you sure?')) return;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    fetch('/api/workspace/courses.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ action: 'delete_course', id: id })
    }).then(r => r.json()).then(d => { if(d.success) fetchCourses(); });
}

function escapeHtml(str) { return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
