<?php
// ============================================================
// WORKSPACE: LIFE OVERVIEW
// ============================================================
require_once dirname(__DIR__) . '/api/auth/guard.php';
requireAdminPage('login.php');

$activeNav = 'ws-overview';
$pageTitle = 'Dashboard - Life Overview';

require __DIR__ . '/partials/layout_top.php';
?>

<!-- HEADER -->
<header class="pw-sub-header">
    <div class="pw-header-breadcrumbs">
        <i class="fas fa-home"></i>
        <span style="margin: 0 4px;">/</span>
        <span class="current">Life Overview</span>
    </div>
    <div class="pw-header-actions">
        
    </div>
</header>

<div class="pw-content">
    <section style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="background: var(--pw-primary-bg); color: var(--pw-primary); padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">My Path</span>
            </div>
            <h1 class="pw-page-title" style="margin: 0;">Journey Overview</h1>
            <p class="pw-page-subtitle" style="margin: 0;">High-level metrics and statistics across your personal ecosystem.</p>
        </div>
    </section>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 8px;">
        
        <div class="pw-card" style="margin-bottom:0; padding:32px 24px; text-align:center; align-items: center;">
            <div style="width: 48px; height: 48px; background: var(--pw-accent-bg); color: var(--pw-accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <span class="material-symbols-outlined">checklist</span>
            </div>
            <div style="font-size:40px; font-weight:700; color:var(--pw-text-main); font-family: var(--pw-font-display); line-height: 1;" id="stat-tasks">0</div>
            <div style="font-size:12px; font-weight:600; color:var(--pw-text-muted); margin-top:12px;">Active Tasks</div>
        </div>
        
        <div class="pw-card" style="margin-bottom:0; padding:32px 24px; text-align:center; align-items: center;">
            <div style="width: 48px; height: 48px; background: var(--pw-primary-bg); color: var(--pw-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <span class="material-symbols-outlined">folder_special</span>
            </div>
            <div style="font-size:40px; font-weight:700; color:var(--pw-text-main); font-family: var(--pw-font-display); line-height: 1;" id="stat-projects">0</div>
            <div style="font-size:12px; font-weight:600; color:var(--pw-text-muted); margin-top:12px;">Active Projects</div>
        </div>
        
        <div class="pw-card" style="margin-bottom:0; padding:32px 24px; text-align:center; align-items: center;">
            <div style="width: 48px; height: 48px; background: var(--pw-tertiary-bg); color: var(--pw-tertiary); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <span class="material-symbols-outlined">flag</span>
            </div>
            <div style="font-size:40px; font-weight:700; color:var(--pw-text-main); font-family: var(--pw-font-display); line-height: 1;" id="stat-goals">0</div>
            <div style="font-size:12px; font-weight:600; color:var(--pw-text-muted); margin-top:12px;">Active Goals</div>
        </div>
        
        <div class="pw-card" style="margin-bottom:0; padding:32px 24px; text-align:center; align-items: center;">
            <div style="width: 48px; height: 48px; background: var(--pw-success-bg); color: var(--pw-success); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <span class="material-symbols-outlined">menu_book</span>
            </div>
            <div style="font-size:40px; font-weight:700; color:var(--pw-text-main); font-family: var(--pw-font-display); line-height: 1;" id="stat-reading">0</div>
            <div style="font-size:12px; font-weight:600; color:var(--pw-text-muted); margin-top:12px;">Reading Progress</div>
        </div>

    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
        <div class="pw-card" style="padding: 24px;">
            <div class="pw-card-header" style="border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px;">
                <h2 class="pw-card-title">Recent Tasks</h2>
                <a href="ws-tasks.php" class="pw-btn pw-btn-primary">View All</a>
            </div>
            <div class="pw-card-list" id="overview-tasks">Loading...</div>
        </div>
        
        <div class="pw-card" style="padding: 24px;">
            <div class="pw-card-header" style="border-bottom: 1px solid var(--pw-border-subtle); padding-bottom: 16px;">
                <h2 class="pw-card-title">Upcoming Events</h2>
                <a href="ws-calendar.php" class="pw-btn">View Calendar</a>
            </div>
            <div class="pw-card-list" id="overview-events">Loading...</div>
        </div>
    </div>

</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    fetch('/api/workspace/overview.php').then(r=>r.json()).then(d=>{
        if(d.success) {
            document.getElementById('stat-tasks').textContent = d.data.tasks_pending || '0';
            document.getElementById('stat-projects').textContent = d.data.projects_active || '0';
            document.getElementById('stat-goals').textContent = d.data.goals_active || '0';
            document.getElementById('stat-reading').textContent = d.data.reading_active || '0';
        }
    });

    // Fetch brief recent tasks
    fetch('/api/workspace/tasks.php?status=pending').then(r=>r.json()).then(d=>{
        if(d.success) {
            const container = document.getElementById('overview-tasks');
            if(d.data.length === 0) {
                container.innerHTML = `<div style="color:var(--pw-text-muted); font-size:13px; text-align:center; padding:16px;">No pending tasks!</div>`;
            } else {
                container.innerHTML = d.data.slice(0, 5).map(t => `
                    <div class="pw-list-item bg-lowest">
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size:14px; font-weight:600; color: var(--pw-text-main);">${t.title}</span>
                        </div>
                        <span class="pw-badge" style="background: var(--pw-surface-container); color: var(--pw-text-secondary);">${t.priority}</span>
                    </div>
                `).join('');
            }
        }
    });

    // Fetch brief upcoming events
    const today = new Date().toISOString().split('T')[0];
    fetch('/api/workspace/events.php?start='+today).then(r=>r.json()).then(d=>{
        if(d.success) {
            const container = document.getElementById('overview-events');
            if(d.data.length === 0) {
                container.innerHTML = `<div style="color:var(--pw-text-muted); font-size:13px; text-align:center; padding:16px;">No upcoming events.</div>`;
            } else {
                container.innerHTML = d.data.slice(0, 5).map(ev => `
                    <div class="pw-list-item bg-lowest">
                        <span style="font-size:14px; font-weight:600; color: var(--pw-text-main);">${ev.title}</span>
                        <span class="font-mono" style="font-size:11px; font-weight:600; color:var(--pw-text-muted);">${ev.event_date.substring(5)}</span>
                    </div>
                `).join('');
            }
        }
    });
});
</script>

<?php require __DIR__ . '/partials/layout_bottom.php'; ?>
