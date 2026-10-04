<?php
// ============================================================
// USER DASHBOARD — LAYOUT TOP
// dashboard/partials/layout_top.php
// ============================================================

header('X-Robots-Tag: noindex');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';

requireUserPage('/login.php');

$currentUser = currentUser();
$csrfToken   = getCsrfToken();

if (!isset($activeNav)) {
    $activeNav = 'overview';
}

if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';
$pageTitle = buildPageTitle($pageTitle);

$navSections = [
    'overview'  => ['label' => 'Overview',        'icon' => 'dashboard',      'url' => '/dashboard/index.php'],
    'library'   => ['label' => 'Library',         'icon' => 'folder_special', 'url' => '/dashboard/library.php'],
    'bookmarks' => ['label' => 'Bookmarks',       'icon' => 'bookmark',       'url' => '/dashboard/bookmarks.php'],
    'downloads' => ['label' => 'Downloads',       'icon' => 'download',       'url' => '/dashboard/downloads.php'],
    'history'   => ['label' => 'Reading History', 'icon' => 'history',        'url' => '/dashboard/history.php'],
    'likes'     => ['label' => 'Likes',           'icon' => 'favorite',       'url' => '/dashboard/likes.php'],
    'activity'  => ['label' => 'Activity',        'icon' => 'timeline',       'url' => '/dashboard/activity.php'],
    'profile'   => ['label' => 'Profile',         'icon' => 'person',         'url' => '/dashboard/profile.php'],
    'settings'  => ['label' => 'Settings',        'icon' => 'settings',       'url' => '/dashboard/settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="robots" content="noindex,follow">
  
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <?php require_once dirname(dirname(__DIR__)) . '/includes/favicon.php'; ?>

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
  
  <!-- Inter & JetBrains Mono Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  
  <!-- Material Symbols Outlined -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=block" as="style" onload="this.onload=null;this.rel='stylesheet'"/>
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=block" /></noscript>
  
  <!-- Precompiled Tailwind CSS & Global Design System (Zero Runtime CDN) -->
  <link rel="stylesheet" href="/css/tailwind.css?v=<?= file_exists(dirname(dirname(__DIR__)) . '/css/tailwind.css') ? filemtime(dirname(dirname(__DIR__)) . '/css/tailwind.css') : '1.0' ?>"/>
  
  <link rel="stylesheet" href="/css/styles.css?v=<?= filemtime(dirname(dirname(__DIR__)) . '/css/styles.css') ?>">
  <script src="/js/theme-toggle.js" defer></script>
</head>
<body class="bg-background text-on-surface font-sans antialiased min-h-screen flex flex-col selection:bg-primary-container selection:text-on-primary">

  <!-- Mobile Topbar -->
  <header class="lg:hidden h-16 bg-surface backdrop-blur-xl border-b border-border sticky top-0 z-40 px-4 flex items-center justify-between">
    <a href="/index.php" class="flex items-center gap-2 text-decoration-none">
      <?php include dirname(dirname(__DIR__)) . '/includes/logo.php'; ?>
    </a>

    <div class="flex items-center gap-2">
      <button id="dashboard-theme-toggle-mobile" 
              type="button" 
              class="theme-toggle-btn w-11 h-11 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-lg bg-surface-container border border-border text-text-secondary hover:text-text-primary transition-colors cursor-pointer"
              aria-label="Toggle theme"
              title="Toggle Theme (Light / Dark / Green)">
        <span class="material-symbols-outlined text-[20px]">dark_mode</span>
      </button>
      <button id="dashboard-mobile-toggle" 
              class="w-11 h-11 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-lg bg-surface-container border border-border text-text-secondary"
              aria-label="Toggle Dashboard Menu">
        <span class="material-symbols-outlined text-[20px]">menu</span>
      </button>
    </div>
  </header>

  <div class="flex flex-1 w-full max-w-[1400px] mx-auto min-h-[calc(100vh-4rem)]">
    
    <!-- Sidebar Navigation -->
    <aside id="dashboard-sidebar" 
           class="fixed inset-y-0 left-0 z-50 w-64 bg-surface border-r border-border flex flex-col justify-between p-4 transform -translate-x-full transition-transform duration-200 lg:translate-x-0 lg:static lg:h-auto lg:z-auto">
      
      <div class="flex flex-col gap-6">
        <!-- Brand & Back Link -->
        <div class="flex items-center justify-between pb-3 border-b border-border/70">
          <a href="/index.php" class="flex items-center group text-decoration-none" title="Home">
            <?php include dirname(dirname(__DIR__)) . '/includes/logo.php'; ?>
          </a>
          <button id="dashboard-close-sidebar" class="lg:hidden text-text-muted hover:text-text-primary p-1">
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <!-- User Identity Mini-Badge -->
        <div class="p-3 rounded-xl bg-surface-container flex items-center gap-3 border border-border/50">
          <div class="w-10 h-10 rounded-full bg-primary/20 text-primary font-mono font-semibold flex items-center justify-center text-sm border border-primary/30">
            <?= htmlspecialchars(strtoupper(substr($currentUser['name'] ?: 'U', 0, 1))) ?>
          </div>
          <div class="flex flex-col overflow-hidden">
            <span class="font-medium text-xs text-on-surface truncate"><?= htmlspecialchars($currentUser['name'] ?: 'Member') ?></span>
            <span class="font-mono text-[10px] text-text-muted truncate"><?= htmlspecialchars($currentUser['email']) ?></span>
          </div>
        </div>

        <!-- Navigation Links -->
        <nav class="flex flex-col gap-1" aria-label="Dashboard Navigation">
          <?php foreach ($navSections as $key => $item): 
            $isActive = ($activeNav === $key);
            $cls = $isActive 
              ? 'flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium text-primary bg-primary/10 border border-primary/20 font-semibold'
              : 'flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors';
          ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $cls ?>">
              <span class="material-symbols-outlined text-[18px] <?= $isActive ? 'text-primary' : 'text-text-muted' ?>">
                <?= $item['icon'] ?>
              </span>
              <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <!-- Bottom Actions -->
      <div class="flex flex-col gap-2 pt-4 border-t border-border/70">
        <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
        <a href="/admin/" 
           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs font-mono text-primary hover:bg-surface-container transition-colors"

           title="Admin Studio">
          <span class="material-symbols-outlined text-[16px]">admin_panel_settings</span>
          <span>Admin Studio</span>
        </a>
        <?php endif; ?>

        <!-- Theme Switcher Button -->
        <button type="button" 
                class="theme-toggle-btn flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container border border-border/50 transition-colors w-full text-left cursor-pointer"
                aria-label="Toggle theme"
                title="Toggle Theme (Light / Dark / Green)">
          <span class="material-symbols-outlined text-[16px]">dark_mode</span>
          <span>Theme: <span class="theme-text-label">Dark</span></span>
        </button>

        <a href="/index.php" 
           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors">
          <span class="material-symbols-outlined text-[16px]">home</span>
          <span>Platform Home</span>
        </a>

        <a href="/logout.php" 
           class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-xs text-red-400 hover:bg-red-500/10 transition-colors">
          <span class="material-symbols-outlined text-[16px]">logout</span>
          <span>Sign Out</span>
        </a>
      </div>

    </aside>

    <!-- Mobile Backdrop -->
    <div id="dashboard-backdrop" class="fixed inset-0 bg-background opacity-80 backdrop-blur-sm z-40 hidden lg:hidden"></div>

    <!-- Main Workspace Content Area -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-y-auto">

      <?php if (!empty($currentUser) && empty($currentUser['email_verified_at'])): ?>
      <!-- Dismissible Email Verification Banner -->
      <div id="email-verification-banner" class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <span class="material-symbols-outlined text-amber-400 text-xl mt-0.5">mark_email_unread</span>
          <div class="flex flex-col gap-0.5">
            <span class="font-semibold text-sm text-amber-300">Please verify your email address</span>
            <span class="text-xs text-amber-200/80">
              A verification link was sent to <strong><?= htmlspecialchars($currentUser['email']) ?></strong>.
              Unverified accounts cannot submit reviews.
            </span>
          </div>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto flex-shrink-0">
          <button type="button" 
                  onclick="resendDashboardVerification()" 
                  id="resendVerificationBtn"
                  class="px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 border border-amber-500/40 transition-colors flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[15px]">send</span>
            <span>Resend Email</span>
          </button>
          <button type="button" 
                  onclick="dismissVerificationBanner()" 
                  class="p-1 rounded-lg text-amber-300/70 hover:text-amber-200 hover:bg-amber-500/20 transition-colors cursor-pointer"
                  title="Dismiss for this session">
            <span class="material-symbols-outlined text-[18px]">close</span>
          </button>
        </div>
      </div>
      <script>
        function dismissVerificationBanner() {
          const b = document.getElementById('email-verification-banner');
          if (b) {
            b.style.display = 'none';
            sessionStorage.setItem('dismiss_verification_banner', '1');
          }
        }
        if (sessionStorage.getItem('dismiss_verification_banner') === '1') {
          const b = document.getElementById('email-verification-banner');
          if (b) b.style.display = 'none';
        }
        async function resendDashboardVerification() {
          const btn = document.getElementById('resendVerificationBtn');
          if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-[15px] animate-spin">sync</span><span>Sending...</span>';
          }
          try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const res = await fetch('/api/auth/resend_verification.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
              body: JSON.stringify({ email: <?= json_encode($currentUser['email'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?> })
            });
            const data = await res.json();
            alert(data.message || 'Verification link dispatched.');
          } catch (err) {
            alert('Failed to send request. Please try again.');
          } finally {
            if (btn) {
              btn.disabled = false;
              btn.innerHTML = '<span class="material-symbols-outlined text-[15px]">send</span><span>Resend Email</span>';
            }
          }
        }
      </script>
      <?php endif; ?>

