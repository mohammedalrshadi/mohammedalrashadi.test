<?php
// ============================================================
// SHARED PARTIAL — <head> + sidebar + mobile topbar + global top bar + <main> open
// ============================================================
// Included by every admin page AFTER authentication check.
// Expects:
//   $pageTitle   (string) — <title> text
//   $activeNav   (string) — one of:
//     dashboard | articles | projects | labs | journey | categories
//     store | media | showcase
//     analytics | seo | reviews | support
//     users | backups | audit | settings
// ============================================================

if (!isset($pageTitle)) {
    $pageTitle = 'Admin Studio';
}
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';
$pageTitle = buildPageTitle($pageTitle);

if (!isset($activeNav)) {
    $activeNav = '';
}

if (!function_exists('navLinkClass')) {
    function navLinkClass(string $key, string $activeNav): string {
        return $key === $activeNav ? 'nav-link active' : 'nav-link';
    }
}

$adminCssPath = __DIR__ . '/../css/admin.css';
$adminCssVersion = file_exists($adminCssPath)
    ? substr(md5_file($adminCssPath), 0, 10)
    : '1';

require_once dirname(__DIR__) . '/../api/helpers/alerts.php';

$newSupportCount = 0;
$pendingReviewsCount = 0;
$unreadAlertsCount = 0;
if (function_exists('getDB')) {
    try {
        $pdoNav = getDB();
        $countsNav = getAdminAlertCounts($pdoNav);
        $unreadAlertsCount   = $countsNav['unread_alerts'];
        $newSupportCount     = $countsNav['new_support'];
        $pendingReviewsCount = $countsNav['pending_reviews'];
    } catch (Throwable $e) {
        $newSupportCount = 0;
        $pendingReviewsCount = 0;
        $unreadAlertsCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSRF token for state-changing requests -->
    <meta name="csrf-token" content="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php require_once dirname(dirname(__DIR__)) . '/includes/favicon.php'; ?>

    <!-- Site Configuration -->
    <script>
        window.siteTimezone = <?php echo json_encode(getSiteSetting('site.timezone', 'Asia/Riyadh')); ?>;
    </script>

    <!-- Instant Theme Initialization Script (Zero Flash, 3-Theme System) -->
    <script>
      (function() {
        try {
          var stored = localStorage.getItem('site-theme') || localStorage.getItem('theme');
          var validThemes = ['light', 'dark', 'green'];
          var theme = (stored && validThemes.indexOf(stored) !== -1) ? stored : null;
          if (!theme) {
            var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            theme = systemDark ? 'dark' : 'light';
          }
          document.documentElement.setAttribute('data-theme', theme);
        } catch(e) {}
      })();
    </script>

    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DOMPurify for defense-in-depth sanitization -->
    <script src="/assets/vendor/dompurify/purify.min.js"></script>

    <!-- Design System & Admin Stylesheet with content hash cache busting -->
    <link rel="stylesheet" href="/css/styles.css?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/css/styles.css'); ?>">
    <link rel="stylesheet" href="/admin/css/admin.css?v=<?php echo htmlspecialchars($adminCssVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <script src="/js/theme-toggle.js?v=<?php echo filemtime(dirname(dirname(__DIR__)) . '/js/theme-toggle.js'); ?>" defer></script>
</head>

<body>

    <!-- Processing Indicator Overlay -->
    <div id="loading" class="loading-indicator">
        <i class="fas fa-circle-notch fa-spin"></i>
        <span>Processing...</span>
    </div>


    <!-- Mobile Topbar -->
    <header class="mobile-topbar">
        <div class="mobile-topbar-brand">
            <span class="admin-monogram-sm">MA</span>
            <span>Admin Studio</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="alerts-bell-btn" id="adminAlertsBtnMobile" onclick="toggleAdminAlertsDropdown(event)" aria-label="Notifications" title="Notifications" style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); color: var(--text-secondary); min-width: 44px; min-height: 44px; width: 44px; height: 44px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; position: relative;">
                <i class="far fa-bell" aria-hidden="true"></i>
                <span class="alerts-badge" id="adminAlertsBadgeMobile" style="<?php echo $unreadAlertsCount > 0 ? '' : 'display: none;'; ?> position: absolute; top: -4px; right: -4px; background: var(--danger); color: var(--bg-body); font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 10px; font-family: var(--font-mono); line-height: 1.2;"><?php echo $unreadAlertsCount; ?></span>
            </button>
            <button type="button" class="theme-toggle-btn" id="admin-theme-toggle-mobile" aria-label="Toggle theme" title="Toggle Theme (Light / Dark / Green)" style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); color: var(--text-secondary); min-width: 44px; min-height: 44px; width: 44px; height: 44px; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">
                <i class="fas fa-moon"></i>
            </button>
            <button type="button" id="sidebarToggle" class="mobile-topbar-toggle" aria-label="Toggle navigation drawer" aria-expanded="false" aria-controls="adminSidebar" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Sidebar Backdrop Overlay for Mobile -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- =====================================================
         SIDEBAR NAVIGATION
    ====================================================== -->
    <aside class="sidebar" id="adminSidebar" aria-label="Admin Navigation">

        <!-- Branding -->
        <a href="index.php" class="sidebar-brand">
                <?php require dirname(dirname(__DIR__)) . '/includes/logo.php'; ?>
                <div class="brand-text">
                    <span class="brand-title">Mohammed Alrashadi</span>
                    <span class="brand-subtitle">Admin Studio</span>
                </div>
            </a>

            <!-- Grouped Navigation -->
            <nav class="nav-groups-wrapper">

                <!-- ================================================
                     TIER 1 — EDITORIAL STUDIO
                     Dashboard, Articles, Projects, Labs, Journey, Categories
                     (Categories confirmed as cross-cutting Taxonomy Engine
                     for blog, projects, labs, and store)
                ================================================ -->
                <div class="nav-group" data-group="editorial">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-title">Editorial Studio</span>
                        <i class="fas fa-chevron-down group-toggle-icon"></i>
                    </button>
                    <div class="nav-group-content">
                        <ul class="nav-menu">
                            <li class="nav-item">
                                <a href="index.php" class="<?php echo navLinkClass('dashboard', $activeNav); ?>">
                                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="articles.php" class="<?php echo navLinkClass('articles', $activeNav); ?>">
                                    <i class="far fa-file-alt" aria-hidden="true"></i>
                                    <span>Writing</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="projects.php" class="<?php echo navLinkClass('projects', $activeNav); ?>">
                                    <i class="fas fa-code-branch" aria-hidden="true"></i>
                                    <span>Projects</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="achievements.php" class="<?php echo navLinkClass('achievements', $activeNav); ?>">
                                    <i class="fas fa-award" aria-hidden="true"></i>
                                    <span>Achievements</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="labs.php" class="<?php echo navLinkClass('labs', $activeNav); ?>">
                                    <i class="fas fa-flask" aria-hidden="true"></i>
                                    <span>Studio Lab</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="journey.php" class="<?php echo navLinkClass('journey', $activeNav); ?>">
                                    <i class="fas fa-route" aria-hidden="true"></i>
                                    <span>Journey</span>
                                </a>
                            </li>
                            <!-- Cross-cutting Taxonomy & Category Engine (blog, projects, labs, store) -->
                            <li class="nav-item">
                                <a href="categories.php" class="<?php echo navLinkClass('categories', $activeNav); ?>">
                                    <i class="fas fa-tags" aria-hidden="true"></i>
                                    <span>Categories</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ================================================
                     TIER 2 — COMMERCE & ASSETS
                     Store, Media Library, Home Showcase
                ================================================ -->
                <div class="nav-group" data-group="commerce">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-title">Commerce &amp; Assets</span>
                        <i class="fas fa-chevron-down group-toggle-icon"></i>
                    </button>
                    <div class="nav-group-content">
                        <ul class="nav-menu">
                            <li class="nav-item">
                                <a href="store.php" class="<?php echo navLinkClass('store', $activeNav); ?>">
                                    <i class="fas fa-store" aria-hidden="true"></i>
                                    <span>Store</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="media.php" class="<?php echo navLinkClass('media', $activeNav); ?>">
                                    <i class="fas fa-images" aria-hidden="true"></i>
                                    <span>Media Library</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="showcase.php" class="<?php echo navLinkClass('showcase', $activeNav); ?>">
                                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                                    <span>Home Showcase</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ================================================
                     TIER 3 — AUDIENCE & INSIGHTS
                     Analytics, SEO Workspace, Visitor Reviews, Support
                ================================================ -->
                <div class="nav-group" data-group="audience">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-title">Audience &amp; Insights</span>
                        <i class="fas fa-chevron-down group-toggle-icon"></i>
                    </button>
                    <div class="nav-group-content">
                        <ul class="nav-menu">
                            <li class="nav-item">
                                <a href="analytics.php" class="<?php echo navLinkClass('analytics', $activeNav); ?>">
                                    <i class="fas fa-chart-pie" aria-hidden="true"></i>
                                    <span>Analytics</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="seo.php" class="<?php echo navLinkClass('seo', $activeNav); ?>">
                                    <i class="fas fa-chart-simple" aria-hidden="true"></i>
                                    <span>SEO Workspace</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="reviews.php" class="<?php echo navLinkClass('reviews', $activeNav); ?>">
                                    <i class="far fa-comments" aria-hidden="true"></i>
                                    <span>Visitor Reviews</span>
                                    <?php if ($pendingReviewsCount > 0): ?>
                                        <span class="nav-badge" id="reviewsNavBadge"><?= $pendingReviewsCount ?></span>
                                    <?php else: ?>
                                        <span class="nav-badge" id="reviewsNavBadge" style="display: none;">0</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="support.php" class="<?php echo navLinkClass('support', $activeNav); ?>">
                                    <i class="far fa-life-ring" aria-hidden="true"></i>
                                    <span>Support</span>
                                    <?php if ($newSupportCount > 0): ?>
                                        <span class="nav-badge" id="supportNavBadge"><?= $newSupportCount ?></span>
                                    <?php else: ?>
                                        <span class="nav-badge" id="supportNavBadge" style="display: none;">0</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- ================================================
                     TIER 4 — PLATFORM & SECURITY
                     Users & Roles, Backups, Audit Log, Settings
                ================================================ -->
                <div class="nav-group" data-group="platform">
                    <button type="button" class="nav-group-toggle" aria-expanded="true">
                        <span class="nav-group-title">Platform &amp; Security</span>
                        <i class="fas fa-chevron-down group-toggle-icon"></i>
                    </button>
                    <div class="nav-group-content">
                        <ul class="nav-menu">
                            <li class="nav-item">
                                <a href="users.php" class="<?php echo navLinkClass('users', $activeNav); ?>">
                                    <i class="far fa-user" aria-hidden="true"></i>
                                    <span>Users &amp; Roles</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="backups.php" class="<?php echo navLinkClass('backups', $activeNav); ?>">
                                    <i class="fas fa-database" aria-hidden="true"></i>
                                    <span>Backups</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="audit-log.php" class="<?php echo navLinkClass('audit', $activeNav); ?>">
                                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                                    <span>Audit Log</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="settings.php" class="<?php echo navLinkClass('settings', $activeNav); ?>">
                                    <i class="fas fa-sliders" aria-hidden="true"></i>
                                    <span>Settings</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

            </nav>

        <!-- Mobile-Only Utilities Dock -->
        <div class="sidebar-utilities-mobile">
            <a href="../index.php" class="sidebar-button" target="_blank" rel="noopener">
                <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                <span>Public Site</span>
            </a>
            <button type="button" class="sidebar-button logout-button text-danger" onclick="logoutAdmin()" style="color: var(--danger);">
                <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                <span>Log Out</span>
            </button>
        </div>

    </aside>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const toggles = document.querySelectorAll('.nav-group-toggle');
        toggles.forEach(toggle => {
            const group = toggle.closest('.nav-group');
            const groupId = group.dataset.group;
            const content = group.querySelector('.nav-group-content');
            const hasActiveLink = group.querySelector('a.active') !== null;
            // Phase 1: new group keys — editorial/commerce/audience/platform
            // Old keys (overview/content/media/system) are preserved in localStorage
            // but will just default to expanded since they won't match.
            const isCollapsed = localStorage.getItem('sidebar_group_' + groupId) === 'collapsed' && !hasActiveLink;
            
            if (isCollapsed) {
                toggle.setAttribute('aria-expanded', 'false');
                content.style.display = 'none';
                toggle.querySelector('.group-toggle-icon').style.transform = 'rotate(-90deg)';
            }
            
            toggle.addEventListener('click', () => {
                const expanded = toggle.getAttribute('aria-expanded') === 'true';
                
                if (expanded) {
                    toggle.setAttribute('aria-expanded', 'false');
                    content.style.display = 'none';
                    toggle.querySelector('.group-toggle-icon').style.transform = 'rotate(-90deg)';
                    localStorage.setItem('sidebar_group_' + groupId, 'collapsed');
                } else {
                    toggle.setAttribute('aria-expanded', 'true');
                    content.style.display = 'block';
                    toggle.querySelector('.group-toggle-icon').style.transform = 'rotate(0deg)';
                    localStorage.setItem('sidebar_group_' + groupId, 'expanded');
                }
            });
        });
    });
    </script>

    <!-- =====================================================
         MAIN WORKSPACE CONTENT CONTAINER
    ====================================================== -->
    <main class="main-content" id="mainContent">

        <?php
        // ── Phase 1: Global Top Bar ─────────────────────────────────────
        // Resolve admin user info for the identity badge.
        // currentUser() is available because guard.php was required before
        // this partial (every admin page calls requireAdminPage() first).
        $_gtbUser      = function_exists('currentUser') ? currentUser() : null;
        $_gtbFullName  = $_gtbUser ? ($_gtbUser['name'] ?? 'Administrator') : 'Administrator';
        $_gtbFirstName = ucfirst(strtolower(explode(' ', trim($_gtbFullName))[0]));
        $_gtbInitial   = mb_strtoupper(mb_substr($_gtbFirstName, 0, 1));
        ?>
        <!-- =====================================================
             PHASE 1: PERSISTENT GLOBAL TOP BAR
             Search (opens ⌘K command palette) + Notification bell
             + User identity badge. Renders on EVERY admin page.
             Bell uses .header-actions wrapper so common.js
             toggleAdminAlertsDropdown() detects it as a header
             trigger and anchors the dropdown from the top.
        ====================================================== -->
        <div class="global-top-bar" id="globalTopBar" role="banner" aria-label="Studio top bar" style="justify-content: flex-start;">
            <?php
            $groupMap = [
                'dashboard' => ['title' => 'Editorial Studio', 'url' => 'index.php'],
                'articles' => ['title' => 'Editorial Studio', 'url' => 'index.php'],
                'projects' => ['title' => 'Editorial Studio', 'url' => 'index.php'],
                'labs' => ['title' => 'Editorial Studio', 'url' => 'index.php'],
                'journey' => ['title' => 'Editorial Studio', 'url' => 'index.php'],
                'categories' => ['title' => 'Editorial Studio', 'url' => 'index.php'],
                'store' => ['title' => 'Commerce & Assets', 'url' => 'store.php'],
                'media' => ['title' => 'Commerce & Assets', 'url' => 'media.php'],
                'showcase' => ['title' => 'Commerce & Assets', 'url' => 'showcase.php'],
                'analytics' => ['title' => 'Audience & Insights', 'url' => 'analytics.php'],
                'seo' => ['title' => 'Audience & Insights', 'url' => 'seo.php'],
                'reviews' => ['title' => 'Audience & Insights', 'url' => 'reviews.php'],
                'support' => ['title' => 'Audience & Insights', 'url' => 'support.php'],
                'users' => ['title' => 'Platform & Security', 'url' => 'users.php'],
                'backups' => ['title' => 'Platform & Security', 'url' => 'backups.php'],
                'audit' => ['title' => 'Platform & Security', 'url' => 'audit-log.php'],
                'settings' => ['title' => 'Platform & Security', 'url' => 'settings.php']
            ];
            $currentGroup = $groupMap[$activeNav] ?? null;
            $currentPageTitle = $pageTitle ?? ucfirst(str_replace('-', ' ', $activeNav));
            ?>
            <div class="global-breadcrumb" style="display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; font-family: var(--font-sans); flex: 1; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= $currentPageTitle ?>
            </div>

            <!-- Notification Bell + User Identity — .header-actions wrapper
                 satisfies the isHeader check in toggleAdminAlertsDropdown() -->
            <div class="header-actions" style="gap: var(--space-3); display: flex; align-items: center; flex-shrink: 0;">

                <!-- Search — clicking opens the ⌘K command palette -->
                <button
                    type="button"
                    class="global-top-bar-btn"
                    onclick="openCommandPalette()"
                    aria-label="Search studio"
                    title="Search studio (⌘K)"
                >
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                </button>

                <a href="../index.php" target="_blank" rel="noopener" class="global-top-bar-btn" title="View Public Site">
                    <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>

                <button type="button" class="theme-toggle-btn global-top-bar-btn" id="admin-theme-toggle" aria-label="Change theme" title="Change theme">
                    <i class="fas fa-sun" aria-hidden="true"></i>
                </button>

                <!-- Notification Bell -->
                <button
                    type="button"
                    class="global-top-bar-bell"
                    id="notificationBadgeBtn"
                    onclick="toggleAdminAlertsDropdown(event)"
                    aria-label="Notifications and system alerts"
                    title="Notifications & System Alerts"
                >
                    <i class="far fa-bell" aria-hidden="true"></i>
                    <span
                        class="notification-badge"
                        id="notificationBadge"
                        style="<?= ($unreadAlertsCount > 0 ? 'display:inline-flex;' : 'display:none;') ?>"
                    ><?= (int)$unreadAlertsCount ?></span>
                </button>

                <div style="width: 1px; height: 24px; background: var(--border-subtle); margin: 0 4px;"></div>

                <!-- User Identity Badge (Simplified) -->
                <a href="settings.php" class="user-identity-badge" title="Platform Profile & Settings" style="padding: 4px; border-radius: 50%;">
                    <div class="admin-monogram-sm" id="userAvatarInitial" style="margin: 0;"><?= htmlspecialchars($_gtbInitial) ?></div>
                </a>

                <button type="button" class="global-top-bar-btn" onclick="logoutAdmin()" title="Log Out">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                </button>

            </div>
        </div><!-- /.global-top-bar -->

        <!-- Toast Notification Container (inside main workspace) -->
        <div id="toastContainer" class="toast-container" aria-live="polite"></div>

        <!-- =====================================================
             ADMIN NOTIFICATION CENTER DROPDOWN PANEL
        ====================================================== -->
        <div id="adminAlertsDropdown" class="alerts-dropdown-panel" role="dialog" aria-label="Notification Center" aria-modal="false" style="display: none;">
            <div class="alerts-dropdown-header">
                <div class="alerts-header-left">
                    <span class="alerts-dropdown-title">Notifications</span>
                    <span class="alerts-header-badge" id="alertsHeaderCount"><?php echo $unreadAlertsCount; ?> unread</span>
                </div>
                <div class="alerts-header-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="alertsMarkAllReadBtn" onclick="markAllAlertsRead(event)" style="font-size: 11px; padding: 3px 8px;">
                        <i class="fas fa-check-double" aria-hidden="true"></i>
                        <span>Mark all read</span>
                    </button>
                    <button type="button" class="alerts-close-btn" onclick="closeAdminAlertsDropdown()" aria-label="Close notifications">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="alerts-dropdown-body" id="alertsDropdownList">
                <div class="alerts-loading-placeholder">
                    <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <span>Loading alerts...</span>
                </div>
            </div>
            <div class="alerts-dropdown-footer">
                <?php if (file_exists(__DIR__ . '/../audit-log.php')): ?>
                    <a href="audit-log.php" class="alerts-footer-link">View System Audit Log →</a>
                <?php else: ?>
                    <span class="alerts-footer-text">Admin Studio Alerts</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- TOP HEALTH / ATTENTION BAR -->
        <div id="globalAttentionBar" class="global-attention-bar" style="display: none;" role="status">
            <div class="attention-bar-content">
                <i class="fas fa-circle-info attention-bar-icon" id="attentionBarIcon" aria-hidden="true"></i>
                <span id="attentionBarText" class="attention-bar-text">Checking system status...</span>
            </div>
            <div style="display: flex; align-items: center;">
                <a id="attentionBarLink" href="index.php" class="attention-bar-action">View details →</a>
                <button type="button" class="attention-bar-close" id="attentionBarClose" aria-label="Dismiss" title="Dismiss">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- COMMAND PALETTE MODAL (⌘K / Ctrl+K) -->
        <div id="commandPaletteModal" class="modal command-palette-modal" role="dialog" aria-label="Command Palette" aria-modal="true" style="display: none;">
            <div class="modal-backdrop" onclick="closeCommandPalette()"></div>
            <div class="command-palette-dialog">
                <div class="command-palette-header">
                    <i class="fas fa-magnifying-glass command-palette-search-icon" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="commandPaletteInput"
                        class="command-palette-input"
                        placeholder="Search articles, projects, reviews, pages... (ESC to close)"
                        autocomplete="off"
                        spellcheck="false"
                        aria-label="Search studio"
                    >
                    <kbd class="command-palette-kbd">ESC</kbd>
                </div>
                <div class="command-palette-body" id="commandPaletteResults">
                    <div class="command-palette-group-title">STUDIO NAVIGATION</div>
                    <a href="index.php" class="command-palette-item">
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                        <span>Dashboard</span>
                        <span class="command-palette-tag">Overview</span>
                    </a>
                    <a href="articles.php" class="command-palette-item">
                        <i class="far fa-file-alt" aria-hidden="true"></i>
                        <span>Articles &amp; Writing</span>
                        <span class="command-palette-tag">Content</span>
                    </a>
                    <a href="projects.php" class="command-palette-item">
                        <i class="fas fa-code-branch" aria-hidden="true"></i>
                        <span>Engineering Projects</span>
                        <span class="command-palette-tag">Content</span>
                    </a>
                    <a href="store.php" class="command-palette-item">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <span>Store Catalog &amp; Products</span>
                        <span class="command-palette-tag">Content</span>
                    </a>
                    <a href="journey.php" class="command-palette-item">
                        <i class="fas fa-route" aria-hidden="true"></i>
                        <span>Journey Timeline</span>
                        <span class="command-palette-tag">Content</span>
                    </a>
                    <a href="categories.php" class="command-palette-item">
                        <i class="fas fa-tags" aria-hidden="true"></i>
                        <span>Taxonomy &amp; Categories</span>
                        <span class="command-palette-tag">Content</span>
                    </a>
                    <a href="settings.php?tab=about" class="command-palette-item">
                        <i class="fas fa-user-pen" aria-hidden="true"></i>
                        <span>About Content (Principles &amp; Focus Tags)</span>
                        <span class="command-palette-tag">Settings</span>
                    </a>
                    <a href="media.php" class="command-palette-item">
                        <i class="fas fa-images" aria-hidden="true"></i>
                        <span>Media Library</span>
                        <span class="command-palette-tag">Media</span>
                    </a>
                    <a href="showcase.php" class="command-palette-item">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        <span>Home Showcase</span>
                        <span class="command-palette-tag">Presentation</span>
                    </a>
                    <a href="reviews.php" class="command-palette-item">
                        <i class="far fa-comments" aria-hidden="true"></i>
                        <span>Visitor Reviews</span>
                        <span class="command-palette-tag">Moderation</span>
                    </a>
                    <a href="seo.php" class="command-palette-item">
                        <i class="fas fa-chart-simple" aria-hidden="true"></i>
                        <span>SEO Workspace</span>
                        <span class="command-palette-tag">Optimization</span>
                    </a>
                    <a href="analytics.php" class="command-palette-item">
                        <i class="fas fa-chart-pie" aria-hidden="true"></i>
                        <span>Analytics Telemetry</span>
                        <span class="command-palette-tag">Telemetry</span>
                    </a>
                    <a href="backups.php" class="command-palette-item">
                        <i class="fas fa-database" aria-hidden="true"></i>
                        <span>Database Backups</span>
                        <span class="command-palette-tag">System</span>
                    </a>
                    <a href="audit-log.php" class="command-palette-item">
                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        <span>Audit Log</span>
                        <span class="command-palette-tag">System</span>
                    </a>
                    <a href="settings.php?tab=social" class="command-palette-item">
                        <i class="fas fa-share-nodes" aria-hidden="true"></i>
                        <span>Social Links</span>
                        <span class="command-palette-tag">Settings</span>
                    </a>
                    <a href="users.php" class="command-palette-item">
                        <i class="far fa-user" aria-hidden="true"></i>
                        <span>Users &amp; Roles</span>
                        <span class="command-palette-tag">System</span>
                    </a>
                    <a href="settings.php" class="command-palette-item">
                        <i class="fas fa-sliders" aria-hidden="true"></i>
                        <span>Settings &amp; Diagnostics</span>
                        <span class="command-palette-tag">System</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- UNIVERSAL CATEGORY MANAGEMENT MODAL -->
        <div id="globalCategoryModal" class="modal" role="dialog" aria-labelledby="globalCategoryModalTitle" aria-modal="true" style="display: none;">
            <div class="modal-backdrop" onclick="closeGlobalCategoryModal()"></div>
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 class="modal-title" id="globalCategoryModalTitle">Category Management</h3>
                    <button type="button" class="modal-close" onclick="closeGlobalCategoryModal()" aria-label="Close modal">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                        <button type="button" id="globalCatTabBlog" class="filter-tab active" onclick="switchGlobalCatType('blog')">Article Categories</button>
                        <button type="button" id="globalCatTabAchieve" class="filter-tab" onclick="switchGlobalCatType('project')">Project Categories</button>
                    </div>
                    <form id="globalCategoryCreateForm" onsubmit="handleGlobalCategoryCreate(event)" style="display: flex; gap: 8px; margin-bottom: 16px;">
                        <input type="text" id="globalCategoryNewName" class="form-control" placeholder="New category name..." required maxlength="255">
                        <button type="submit" class="btn btn-primary btn-sm" style="white-space: nowrap;">Add Category</button>
                    </form>
                    <div id="globalCategoryList" class="category-manager-list" style="max-height: 280px; overflow-y: auto;">
                        <div style="text-align: center; color: var(--text-muted); padding: 20px;">Loading categories...</div>
                    </div>
                </div>
            </div>
        </div>
