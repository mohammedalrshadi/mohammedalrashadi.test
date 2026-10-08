<?php
// ============================================================
// PERSONAL WORKSPACE DASHBOARD
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');
$activeNav = 'workspace';
$pageTitle = 'Dashboard';
require __DIR__ . '/partials/layout_top.php';
$adminFirstName = isset($_gtbFirstName) ? $_gtbFirstName : 'Mohammed';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Dashboard</span>
    </div>
    <div class="pw-header-actions">
        <a href="ws-tasks.php" class="pw-btn pw-btn-primary"><i class="fas fa-plus"></i> New Task</a>
    </div>
</header>

<!-- DASHBOARD SCROLL AREA -->
<div class="pw-content">
    
    <!-- Top Welcome & Daily Anchor -->
    <section style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="font-mono text-primary" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Academic Year 2 • Term 4</span>
            <span class="font-mono" style="color: var(--pw-border-hover); font-size: 12px;">•</span>
            <span class="font-mono text-secondary" style="font-size: 11px; display: flex; align-items: center; gap: 4px;">
                <span class="material-symbols-outlined text-tertiary" style="font-size: 14px;">partly_cloudy_day</span>
                Campus: 64°F, North Quad
            </span>
        </div>
        <h1 class="font-display" style="font-size: 36px; font-weight: 700; color: var(--pw-text-main); letter-spacing: -0.02em; line-height: 1.1; margin: 0;">Good morning, <?= htmlspecialchars($adminFirstName) ?>.</h1>
        <p style="font-size: 15px; color: var(--pw-text-muted); margin: 0;" id="dateDisplay">
            Loading date...
        </p>
    </section>
    
    <!-- Next Action Hero Card -->
    <section class="pw-hero-card" style="background: linear-gradient(135deg, var(--pw-primary) 0%, var(--pw-accent) 100%); color: #fff; border: none;">
        <div class="pw-hero-content" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 24px;">
            <div style="display: flex; flex-direction: column; gap: 12px; max-width: 700px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Today's Focus</span>
                    <span class="font-mono" style="font-size: 11px; font-weight: 600; opacity: 0.8;">Est. 15 Mins • High Leverage</span>
                </div>
                <h2 class="font-display" style="font-size: 32px; font-weight: 700; margin: 0; letter-spacing: -0.01em; line-height: 1.2;">
                    Complete BCNF & 3NF Normalization Practice
                </h2>
                <div style="background: rgba(255,255,255,0.1); padding: 16px; border-radius: 12px; display: flex; align-items: flex-start; gap: 12px; border: 1px solid rgba(255,255,255,0.15);">
                    <span class="material-symbols-outlined" style="font-size: 20px; margin-top: 2px;">lightbulb</span>
                    <p style="font-size: 14px; margin: 0; line-height: 1.5; opacity: 0.95;">
                        <strong>Why this matters:</strong> You scored 58% on normalization; closing this specific gap unblocks 
                        <span style="font-weight: 600;">REST API Data Modeling</span> in your scheduled <span style="font-weight: 600;">E-Commerce API</span> milestone.
                    </p>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 12px; min-width: 220px;">
                <button class="pw-btn" style="padding: 12px 16px; width: 100%; font-size: 14px; background: #fff; color: var(--pw-primary); border: none;">
                    <span class="material-symbols-outlined" style="font-size: 18px;">play_arrow</span> Start Practice
                </button>
                <button class="pw-btn" style="padding: 10px 16px; width: 100%; font-size: 13px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);">
                    <span class="material-symbols-outlined" style="font-size: 16px;">schedule</span> Postpone to 4:00 PM
                </button>
            </div>
        </div>
    </section>

    <!-- Two-Column Connected Core -->
    <div class="pw-grid-12">
        
        <!-- Left Column: Where am I -->
        <div class="pw-col-7" style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Academic Rhythm / Schedule Card -->
            <div class="pw-card" style="padding: 28px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
                    <div class="pw-section-title" style="font-size: 13px; font-weight: 700; color: var(--pw-text-main);">
                        Plan the day with clarity
                    </div>
                    <span class="font-mono" style="font-size: 11px; font-weight: 600; color: var(--pw-text-muted);" id="event-count-badge">Loading...</span>
                </div>
                <div class="pw-card-list" id="today-events">
                    <div style="color:var(--pw-text-muted); font-size:13px; text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Checking calendar...</div>
                </div>
            </div>

            <!-- Today's Tasks -->
            <div class="pw-card" style="padding: 28px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
                    <div class="pw-section-title" style="font-size: 13px; font-weight: 700; color: var(--pw-text-main);">
                        Your next actions
                    </div>
                    <span class="font-mono text-secondary" style="font-size: 11px; font-weight: 600;">Inbox</span>
                </div>
                <div class="pw-card-list" id="today-tasks">
                    <div style="color:var(--pw-text-muted); font-size:13px; text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading tasks...</div>
                </div>
            </div>

        </div>

        <!-- Right Column: Where am I going -->
        <div class="pw-col-5" style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- My Path Pipeline -->
            <div class="pw-card" style="padding: 28px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px; margin-bottom: 16px;">
                    <div class="pw-section-title" style="font-size: 13px; font-weight: 700; color: var(--pw-text-main);">
                        Continue where you left off
                    </div>
                    <a href="ws-overview.php" style="font-size: 12px; font-weight: 600; color: var(--pw-primary); text-decoration: none;">View Map →</a>
                </div>
                <div class="pw-card-list" id="continue-blocks">
                    <div style="color:var(--pw-text-muted); font-size:13px; text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                </div>
                <div style="margin-top: 24px; border-top: 1px solid var(--pw-border-subtle); padding-top: 20px;" id="progress-blocks">
                    <div style="color:var(--pw-text-muted); font-size:13px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Syncing...</div>
                </div>
            </div>

            <!-- Ecosystem Status (From layout bottom) -->
            <div class="pw-card" style="padding: 24px; flex-direction: row; align-items: center; gap: 16px; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 48px; height: 48px; background: var(--pw-surface-container); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--pw-text-secondary);">
                        <span class="material-symbols-outlined" style="font-size: 24px;">psychology</span>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 14px; font-weight: 600; color: var(--pw-text-main);">Learning Momentum</span>
                        </div>
                        <p style="font-size: 13px; color: var(--pw-text-muted); margin: 4px 0 0 0;">You're keeping a consistent pace this week.</p>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('dateDisplay').textContent = new Date().toLocaleDateString('en-US', options);

    fetch('/api/workspace/overview.php?action=dashboard_context')
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                renderToday(d.data.today_tasks);
                renderEvents(d.data.today_events);
                renderContinue(d.data.continue_course, d.data.continue_project, d.data.continue_note);
                renderProgress(d.data.active_goals, d.data.active_projects_count, d.data.learning_skills);
            }
        });
});

function getPriorityBadge(p) {
    if(p==='urgent') return '<span style="background: var(--pw-danger-bg); color: var(--pw-danger); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Urgent</span>';
    if(p==='high') return '<span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">High</span>';
    if(p==='medium') return '<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Med</span>';
    return '<span style="background: var(--pw-surface-container); color: var(--pw-text-secondary); padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Low</span>';
}

function renderToday(tasks) {
    const el = document.getElementById('today-tasks');
    if(!tasks || tasks.length === 0) {
        el.innerHTML = `
        <div style="text-align: center; padding: 32px 24px; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined" style="font-size: 32px; margin-bottom: 12px; color: var(--pw-border-hover);">task_alt</span>
            <div style="font-size: 14px; font-weight: 500;">No tasks yet.</div>
            <div style="font-size: 13px; margin-top: 4px;">Start with one clear next action.</div>
        </div>`;
        return;
    }
    el.innerHTML = tasks.map(t => `
        <div class="pw-list-item bg-lowest">
            <div style="display: flex; gap: 12px; align-items: flex-start; flex: 1;">
                <div class="pw-checkbox"></div>
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: var(--pw-text-main); line-height: 1.2; margin-bottom: 4px;">${escapeHtml(t.title)}</div>
                    <div style="font-size: 12px; color: var(--pw-text-muted); display: flex; gap: 8px; align-items: center;">
                        ${getPriorityBadge(t.priority)}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function renderEvents(events) {
    const el = document.getElementById('today-events');
    const badge = document.getElementById('event-count-badge');
    if (badge) badge.textContent = events ? `${events.length} Events` : '0 Events';
    
    if(!events || events.length === 0) {
        el.innerHTML = `
        <div style="text-align: center; padding: 32px 24px; color: var(--pw-text-muted);">
            <span class="material-symbols-outlined" style="font-size: 32px; margin-bottom: 12px; color: var(--pw-border-hover);">event_available</span>
            <div style="font-size: 14px; font-weight: 500;">A clear schedule today.</div>
            <div style="font-size: 13px; margin-top: 4px;">Enjoy the deep work time.</div>
        </div>`;
        return;
    }
    el.innerHTML = events.map(e => `
        <div class="pw-list-item bg-lowest">
            <div style="display: flex; gap: 16px; align-items: flex-start;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 60px; padding: 6px; background: var(--pw-surface-container); border-radius: 8px;">
                    <span class="font-mono" style="font-size: 12px; font-weight: 700; color: var(--pw-text-main);">${e.event_time ? e.event_time.substring(0,5) : 'All'}</span>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="font-size: 14px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(e.title)}</span>
                    </div>
                    <p style="font-size: 12px; color: var(--pw-text-muted); margin: 0;">Scheduled Event</p>
                </div>
            </div>
        </div>
    `).join('');
}

function renderContinue(course, project, note) {
    const el = document.getElementById('continue-blocks');
    let html = '';
    if(project) {
        html += `
        <div class="pw-list-item">
            <div style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--pw-surface-container); color: var(--pw-primary); border-radius: 8px;">
                <span class="material-symbols-outlined" style="font-size: 18px;">terminal</span>
            </div>
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 14px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(project.name)}</span>
                </div>
                <p style="font-size: 13px; color: var(--pw-text-muted); margin: 4px 0 0 0;">Build evidence for your future.</p>
            </div>
        </div>`;
    }
    if(course) {
        html += `
        <div class="pw-list-item">
            <div style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--pw-surface-container); color: var(--pw-accent); border-radius: 8px;">
                <span class="material-symbols-outlined" style="font-size: 18px;">school</span>
            </div>
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 14px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(course.name)}</span>
                </div>
                <p style="font-size: 13px; color: var(--pw-text-muted); margin: 4px 0 0 0;">Continue your learning momentum.</p>
            </div>
        </div>`;
    }
    if(note) {
        html += `
        <div class="pw-list-item">
            <div style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: var(--pw-surface-container); color: var(--pw-text-secondary); border-radius: 8px;">
                <span class="material-symbols-outlined" style="font-size: 18px;">edit_document</span>
            </div>
            <div style="flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 14px; font-weight: 600; color: var(--pw-text-main);">${escapeHtml(note.title)}</span>
                </div>
                <p style="font-size: 13px; color: var(--pw-text-muted); margin: 4px 0 0 0;">Your recent thoughts and notes.</p>
            </div>
        </div>`;
    }
    if(html === '') { 
        el.innerHTML = `
        <div style="text-align: center; padding: 24px; color: var(--pw-text-muted);">
            <div style="font-size: 13px;">No recent context found. Time to start something new.</div>
        </div>`; 
    }
    else { el.innerHTML = html; }
}

function renderProgress(goals, projects, skills) {
    const el = document.getElementById('progress-blocks');
    el.innerHTML = `
        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span style="font-size: 13px; color: var(--pw-text-muted); display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 16px;">work</span> Active Goals</span>
            <span class="font-mono" style="font-size: 13px; font-weight: 600;">${goals}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span style="font-size: 13px; color: var(--pw-text-muted); display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 16px;">folder_special</span> Active Projects</span>
            <span class="font-mono" style="font-size: 13px; font-weight: 600;">${projects}</span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span style="font-size: 13px; color: var(--pw-text-muted); display: flex; align-items: center; gap: 8px;"><span class="material-symbols-outlined" style="font-size: 16px;">verified</span> Skills in Progress</span>
            <span class="font-mono" style="font-size: 13px; font-weight: 600;">${skills}</span>
        </div>
    `;
}

function escapeHtml(str) { return (str+'').replace(/[&<"']/g, m => ({'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m])); }
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
