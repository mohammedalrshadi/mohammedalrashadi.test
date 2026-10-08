<?php
$file = 'admin/partials/layout_top.php';
$content = file_get_contents($file);

// Add CSS link
$cssInclude = '    <link rel="stylesheet" href="/admin/css/admin.css?v=<?php echo htmlspecialchars($adminCssVersion, ENT_QUOTES, \'UTF-8\'); ?>">';
$workspaceCssInclude = "    <link rel=\"stylesheet\" href=\"/admin/css/admin.css?v=<?php echo htmlspecialchars(\$adminCssVersion, ENT_QUOTES, 'UTF-8'); ?>\">\n    <?php if (str_starts_with(\$activeNav, 'ws-') || \$activeNav === 'workspace'): ?>\n    <link rel=\"stylesheet\" href=\"/admin/css/workspace.css\">\n    <?php endif; ?>";
$content = str_replace($cssInclude, $workspaceCssInclude, $content);

// Replace Sidebar
$sidebarStart = '    <!-- =====================================================
         SIDEBAR NAVIGATION
    ====================================================== -->';
$sidebarEnd = '    <!-- =====================================================
         MAIN WORKSPACE CONTENT CONTAINER
    ====================================================== -->';

$pattern = '/' . preg_quote($sidebarStart, '/') . '.*?' . preg_quote($sidebarEnd, '/') . '/s';

$newSidebar = <<<HTML
    <!-- =====================================================
         SIDEBAR NAVIGATION
    ====================================================== -->
    <?php \$isWorkspace = (str_starts_with(\$activeNav, 'ws-') || \$activeNav === 'workspace'); ?>
    <?php if (!\$isWorkspace): ?>
        <aside class="sidebar" id="adminSidebar" aria-label="Admin Navigation">

            <!-- Branding -->
            <a href="index.php" class="sidebar-brand">
                    <?php require dirname(dirname(__DIR__)) . '/includes/logo.php'; ?>
                    <div class="brand-text">
                        <span class="brand-title">Mohammed Alrashadi</span>
                        <span class="brand-subtitle">Admin Studio</span>
                    </div>
                </a>
                
            <div class="mode-switcher-container" style="padding: 16px 20px 0;">
                <select id="adminModeSwitcher" onchange="window.location.href='workspace.php'" style="width: 100%; background: var(--bg-surface-elevated); color: var(--text-primary); border: 1px solid var(--border-subtle); padding: 8px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; appearance: none; -webkit-appearance: none; outline: none;">
                    <option value="website" selected>🌍 Website Admin</option>
                    <option value="workspace">🚀 Personal Workspace</option>
                </select>
            </div>

            <!-- Grouped Navigation -->
            <nav class="nav-groups-wrapper">
                <div id="navModeWebsite">
                    <!-- TIER 1 -->
                    <div class="nav-group" data-group="editorial">
                        <button type="button" class="nav-group-toggle" aria-expanded="true">
                            <span class="nav-group-title">Editorial Studio</span>
                            <i class="fas fa-chevron-down group-toggle-icon"></i>
                        </button>
                        <div class="nav-group-content">
                            <ul class="nav-menu">
                                <li class="nav-item"><a href="index.php" class="<?php echo navLinkClass('dashboard', \$activeNav); ?>"><i class="fas fa-chart-line"></i><span>Dashboard</span></a></li>
                                <li class="nav-item"><a href="articles.php" class="<?php echo navLinkClass('articles', \$activeNav); ?>"><i class="far fa-file-alt"></i><span>Writing</span></a></li>
                                <li class="nav-item"><a href="projects.php" class="<?php echo navLinkClass('projects', \$activeNav); ?>"><i class="fas fa-code-branch"></i><span>Projects</span></a></li>
                                <li class="nav-item"><a href="achievements.php" class="<?php echo navLinkClass('achievements', \$activeNav); ?>"><i class="fas fa-award"></i><span>Achievements</span></a></li>
                                <li class="nav-item"><a href="labs.php" class="<?php echo navLinkClass('labs', \$activeNav); ?>"><i class="fas fa-flask"></i><span>Studio Lab</span></a></li>
                                <li class="nav-item"><a href="journey.php" class="<?php echo navLinkClass('journey', \$activeNav); ?>"><i class="fas fa-route"></i><span>Journey</span></a></li>
                                <li class="nav-item"><a href="categories.php" class="<?php echo navLinkClass('categories', \$activeNav); ?>"><i class="fas fa-tags"></i><span>Categories</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <!-- TIER 2 -->
                    <div class="nav-group" data-group="commerce">
                        <button type="button" class="nav-group-toggle" aria-expanded="true">
                            <span class="nav-group-title">Commerce &amp; Assets</span>
                            <i class="fas fa-chevron-down group-toggle-icon"></i>
                        </button>
                        <div class="nav-group-content">
                            <ul class="nav-menu">
                                <li class="nav-item"><a href="store.php" class="<?php echo navLinkClass('store', \$activeNav); ?>"><i class="fas fa-store"></i><span>Store</span></a></li>
                                <li class="nav-item"><a href="media.php" class="<?php echo navLinkClass('media', \$activeNav); ?>"><i class="fas fa-images"></i><span>Media Library</span></a></li>
                                <li class="nav-item"><a href="showcase.php" class="<?php echo navLinkClass('showcase', \$activeNav); ?>"><i class="fas fa-layer-group"></i><span>Home Showcase</span></a></li>
                            </ul>
                        </div>
                    </div>
                    <!-- TIER 3 -->
                    <div class="nav-group" data-group="audience">
                        <button type="button" class="nav-group-toggle" aria-expanded="true">
                            <span class="nav-group-title">Audience &amp; Insights</span>
                            <i class="fas fa-chevron-down group-toggle-icon"></i>
                        </button>
                        <div class="nav-group-content">
                            <ul class="nav-menu">
                                <li class="nav-item"><a href="analytics.php" class="<?php echo navLinkClass('analytics', \$activeNav); ?>"><i class="fas fa-chart-pie"></i><span>Analytics</span></a></li>
                                <li class="nav-item"><a href="seo.php" class="<?php echo navLinkClass('seo', \$activeNav); ?>"><i class="fas fa-chart-simple"></i><span>SEO Workspace</span></a></li>
                                <li class="nav-item">
                                    <a href="reviews.php" class="<?php echo navLinkClass('reviews', \$activeNav); ?>"><i class="far fa-comments"></i><span>Visitor Reviews</span>
                                    <?php if (\$pendingReviewsCount > 0): ?><span class="nav-badge"><?= \$pendingReviewsCount ?></span><?php endif; ?></a>
                                </li>
                                <li class="nav-item">
                                    <a href="support.php" class="<?php echo navLinkClass('support', \$activeNav); ?>"><i class="far fa-life-ring"></i><span>Support</span>
                                    <?php if (\$newSupportCount > 0): ?><span class="nav-badge"><?= \$newSupportCount ?></span><?php endif; ?></a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!-- TIER 4 -->
                    <div class="nav-group" data-group="platform">
                        <button type="button" class="nav-group-toggle" aria-expanded="true">
                            <span class="nav-group-title">Platform &amp; Security</span>
                            <i class="fas fa-chevron-down group-toggle-icon"></i>
                        </button>
                        <div class="nav-group-content">
                            <ul class="nav-menu">
                                <li class="nav-item"><a href="users.php" class="<?php echo navLinkClass('users', \$activeNav); ?>"><i class="far fa-user"></i><span>Users &amp; Roles</span></a></li>
                                <li class="nav-item"><a href="backups.php" class="<?php echo navLinkClass('backups', \$activeNav); ?>"><i class="fas fa-database"></i><span>Backups</span></a></li>
                                <li class="nav-item"><a href="audit-log.php" class="<?php echo navLinkClass('audit', \$activeNav); ?>"><i class="fas fa-clipboard-list"></i><span>Audit Log</span></a></li>
                                <li class="nav-item"><a href="settings.php" class="<?php echo navLinkClass('settings', \$activeNav); ?>"><i class="fas fa-sliders"></i><span>Settings</span></a></li>
                            </ul>
                        </div>
                    </div>
                </div> 
            </nav>
            <div class="sidebar-utilities-mobile">
                <button type="button" class="sidebar-button logout-button text-danger" onclick="logoutAdmin()" style="color: var(--danger);"><i class="fas fa-right-from-bracket"></i><span>Log Out</span></button>
            </div>
        </aside>
    <?php else: ?>
        <aside class="pw-sidebar">
            <div class="pw-mode-switcher">
                <button class="pw-mode-btn" onclick="window.location.href='index.php'">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:20px; height:20px; background:#fff; border-radius:4px; display:flex; align-items:center; justify-content:center;">
                            <span style="color:#000; font-weight:800; font-size:12px;">M</span>
                        </div>
                        <span>Personal Workspace</span>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--pw-sidebar-text);"></i>
                </button>
            </div>
            <nav class="pw-nav">
                <div class="pw-nav-group">
                    <div class="pw-nav-title">Overview</div>
                    <a href="workspace.php" class="pw-nav-item <?php echo \$activeNav==='workspace'?'active':''; ?>"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="ws-overview.php" class="pw-nav-item <?php echo \$activeNav==='ws-overview'?'active':''; ?>"><i class="fas fa-compass"></i> Life Overview</a>
                    <a href="ws-calendar.php" class="pw-nav-item <?php echo \$activeNav==='ws-calendar'?'active':''; ?>"><i class="far fa-calendar-alt"></i> Calendar</a>
                </div>
                <div class="pw-nav-group">
                    <div class="pw-nav-title">Study</div>
                    <a href="ws-courses.php" class="pw-nav-item <?php echo \$activeNav==='ws-courses'?'active':''; ?>"><i class="fas fa-graduation-cap"></i> Courses</a>
                    <a href="ws-tasks.php" class="pw-nav-item <?php echo \$activeNav==='ws-tasks'?'active':''; ?>"><i class="fas fa-check-square"></i> Tasks</a>
                    <a href="ws-notes.php" class="pw-nav-item <?php echo \$activeNav==='ws-notes'?'active':''; ?>"><i class="far fa-file-alt"></i> Notes</a>
                </div>
                <div class="pw-nav-group">
                    <div class="pw-nav-title">Build</div>
                    <a href="ws-projects.php" class="pw-nav-item <?php echo \$activeNav==='ws-projects'?'active':''; ?>"><i class="fas fa-code-branch"></i> Projects</a>
                    <a href="ws-skills.php" class="pw-nav-item <?php echo \$activeNav==='ws-skills'?'active':''; ?>"><i class="fas fa-layer-group"></i> Skills</a>
                    <a href="ws-achievements.php" class="pw-nav-item <?php echo \$activeNav==='ws-achievements'?'active':''; ?>"><i class="fas fa-trophy"></i> Achievements</a>
                </div>
                <div class="pw-nav-group">
                    <div class="pw-nav-title">Knowledge</div>
                    <a href="ws-reading.php" class="pw-nav-item <?php echo \$activeNav==='ws-reading'?'active':''; ?>"><i class="fas fa-book"></i> Reading</a>
                    <a href="ws-resources.php" class="pw-nav-item <?php echo \$activeNav==='ws-resources'?'active':''; ?>"><i class="fas fa-link"></i> Resources</a>
                </div>
                <div class="pw-nav-group">
                    <div class="pw-nav-title">Life</div>
                    <a href="ws-goals.php" class="pw-nav-item <?php echo \$activeNav==='ws-goals'?'active':''; ?>"><i class="fas fa-bullseye"></i> Goals</a>
                    <a href="ws-habits.php" class="pw-nav-item <?php echo \$activeNav==='ws-habits'?'active':''; ?>"><i class="fas fa-sync-alt"></i> Habits</a>
                    <a href="ws-clubs.php" class="pw-nav-item <?php echo \$activeNav==='ws-clubs'?'active':''; ?>"><i class="fas fa-users"></i> Clubs</a>
                </div>
            </nav>
        </aside>
    <?php endif; ?>

    <!-- =====================================================
         MAIN WORKSPACE CONTENT CONTAINER
    ====================================================== -->
HTML;

$content = preg_replace($pattern, $newSidebar, $content);

// Also replace `<main class="main-content" id="mainContent">` with dynamic class
$mainStart = '<main class="main-content" id="mainContent">';
$mainReplacement = '    <main class="<?php echo $isWorkspace ? \'pw-main\' : \'main-content\'; ?>" id="mainContent">';
$content = str_replace($mainStart, $mainReplacement, $content);

file_put_contents($file, $content);
echo "Updated layout_top.php successfully.\n";
