<?php
if (!isset($currentPage)) {
    $currentPage = 'home';
}

require_once __DIR__ . '/nav_items.php';

require_once dirname(__DIR__) . '/api/auth/guard.php';
$isLoggedIn = isUserLoggedIn();
$loggedUser = currentUser();
?>
<style>
/* Modern Glass Floating Header Architecture */
.modern-header-wrapper {
  position: absolute; top: 0; left: 0; right: 0; z-index: 100;
  pointer-events: none; transition: all 0.3s ease;
}
.modern-header-inner {
  pointer-events: auto; width: 100%; height: 64px;
  background-color: var(--color-surface);
  background-color: color-mix(in srgb, var(--color-surface) 90%, transparent);
  backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--color-border);
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  padding: 0 1rem; transition: background-color 0.3s ease, border-color 0.3s ease;
}

@media (min-width: 768px) {
  .modern-header-wrapper { padding-top: 1rem; padding-left: clamp(1.5rem, 5vw, 4rem); padding-right: clamp(1.5rem, 5vw, 4rem); }
  .modern-header-inner {
    width: 100%; max-width: 2560px; height: 60px; gap: 2rem;
    margin: 0 auto;
    background-color: var(--color-surface);
    background-color: color-mix(in srgb, var(--color-surface) 85%, transparent);
    backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px);
    border: 1px solid var(--color-border);
    border-radius: 9999px;
    padding: 0 1rem 0 1.5rem;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.2), inset 0 1px 0 var(--color-border-subtle);
  }
}

/* Nav Links */
.modern-nav { display: flex; align-items: center; gap: 0.375rem; }
.modern-nav-link {
  position: relative; font-size: 15px; font-weight: 500;
  color: var(--color-text-secondary); padding: 0.5rem 0.75rem;
  min-height: 44px; display: inline-flex; align-items: center;
  transition: color 0.2s; white-space: nowrap;
}
.modern-nav-link:hover { color: var(--color-text-primary); }
.modern-nav-link.active { color: var(--color-primary); }
@media (min-width: 768px) {
  .modern-nav-link.active::after {
    content: ''; position: absolute; bottom: 0px; left: 15%; width: 70%; height: 2px;
    background-color: var(--color-primary);
    box-shadow: 0 -2px 8px 0px var(--color-primary);
    border-radius: 2px 2px 0 0; opacity: 0.8;
  }
}

/* Controls */
.modern-controls { display: flex; align-items: center; gap: 0.5rem; }
.modern-search-btn {
  display: flex; align-items: center; gap: 0.5rem; height: 44px; min-height: 44px; padding: 0 1rem;
  background-color: var(--color-surface-container); border: 1px solid var(--color-border);
  border-radius: 9999px; color: var(--color-text-muted); font-size: 14px;
  transition: all 0.2s; cursor: pointer;
}
.modern-search-btn:hover { background-color: var(--color-surface-container-high); color: var(--color-text-primary); }

.modern-theme-btn {
  display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; min-width: 44px; min-height: 44px;
  color: var(--color-text-secondary); background: transparent; border: none;
  border-radius: 50%; transition: color 0.2s; cursor: pointer;
}
.modern-theme-btn:hover { color: var(--color-text-primary); }
.modern-theme-btn .material-symbols-outlined { font-size: 20px !important; }

.modern-signin-btn {
  display: flex; align-items: center; gap: 0.375rem; height: 44px; min-height: 44px; padding: 0 1.25rem;
  background-color: var(--color-surface-container);
  border: 1px solid var(--color-border); border-radius: 9999px;
  color: var(--color-text-primary); font-size: 14px; font-weight: 500;
  transition: all 0.2s; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.modern-signin-btn:hover { border-color: var(--color-outline); background-color: var(--color-surface-container-high); }
.modern-signin-btn .material-symbols-outlined { font-size: 18px; color: var(--color-primary); }

.modern-user-btn {
  display: flex; align-items: center; gap: 0.375rem; height: 44px; min-height: 44px; padding: 0 0.375rem 0 0.75rem;
  background-color: var(--color-surface-container); border: 1px solid var(--color-border);
  border-radius: 9999px; color: var(--color-text-primary); transition: all 0.2s; cursor: pointer;
}
.modern-user-btn:hover { background-color: var(--color-surface-container-high); }
.modern-user-avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background-color: var(--color-primary-container);
  color: var(--color-primary); display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; font-family: monospace;
}

/* ---- Progressive ("priority") navigation ----
   Same floating pill + same links. As the window gets narrower (or the page
   is zoomed in) links drop off the right end of the nav one by one into a
   "More" menu, instead of squeezing or jumping to the hamburger. The
   hamburger is only used on phones (< 768px). */
.modern-header-inner > * { min-width: 0; }
.modern-header-inner > .flex-shrink-0,
.modern-controls { flex-shrink: 0; }
.modern-signin-btn, .modern-search-btn { white-space: nowrap; flex-shrink: 0; }

.hdr-desktop-nav { display: none; flex: 1 1 0; min-width: 0; align-items: center; justify-content: center; }
.hdr-burger { display: flex; flex-shrink: 0; }
.modern-nav { flex: 0 0 auto; flex-wrap: nowrap; }

.modern-more { position: relative; display: none; align-items: center; }
.modern-more-btn { display: inline-flex; align-items: center; gap: 0.125rem; background: none; border: 0; cursor: pointer; font-family: inherit; }
.modern-more-btn .material-symbols-outlined { font-size: 18px !important; transition: transform 0.2s; }
.modern-more.open .modern-more-btn { color: var(--color-text-primary); }
.modern-more.open .modern-more-btn .material-symbols-outlined { transform: rotate(180deg); }
.modern-more-menu {
  display: none; flex-direction: column; gap: 2px; position: absolute; right: 0;
  top: calc(100% + 14px); min-width: 200px; padding: 0.5rem; z-index: 120;
  background-color: var(--color-surface); border: 1px solid var(--color-border);
  border-radius: 1rem; box-shadow: 0 20px 40px -12px rgba(0,0,0,0.4);
}
.modern-more.open .modern-more-menu { display: flex; }
.modern-more-item {
  display: none; padding: 0.625rem 0.875rem; border-radius: 0.625rem; white-space: nowrap;
  font-size: 14.5px; font-weight: 500; color: var(--color-text-secondary); transition: background-color 0.2s, color 0.2s;
}
.modern-more-item:hover { background-color: var(--color-surface-container); color: var(--color-text-primary); }
.modern-more-item.active { color: var(--color-primary); }

@media (min-width: 768px) {
  .hdr-desktop-nav { display: flex; }
  .hdr-burger { display: none; }
  .hdr-mobile-only { display: none !important; }
}
/* Tablet / laptop / zoomed-in (<1440px): a little tighter so more links stay visible */
@media (min-width: 768px) and (max-width: 1439px) {
  .modern-header-inner { gap: 1rem; padding: 0 0.75rem 0 1.25rem; }
  .modern-nav { gap: 0.125rem; }
  .modern-nav-link { padding: 0.5rem 0.6rem; }
  .modern-controls { gap: 0.25rem; }
}

/* ---- Phones / very high zoom (<768px): same pill, hamburger menu ---- */
@media (max-width: 767px) {
  .modern-header-wrapper {
    padding-top: 0.5rem;
    padding-left: clamp(1rem, 5vw, 4rem);
    padding-right: clamp(1rem, 5vw, 4rem);
  }
  .modern-header-inner {
    height: 56px; gap: 0.5rem; margin: 0 auto; max-width: 2560px;
    padding: 0 0.5rem 0 1rem;
    background-color: var(--color-surface);
    background-color: color-mix(in srgb, var(--color-surface) 85%, transparent);
    backdrop-filter: blur(32px); -webkit-backdrop-filter: blur(32px);
    border: 1px solid var(--color-border);
    border-radius: 9999px;
    box-shadow: 0 10px 40px -10px rgba(0,0,0,0.2), inset 0 1px 0 var(--color-border-subtle);
  }
  #mobile-menu-button {
    width: 44px; height: 44px; min-width: 44px; min-height: 44px;
    border-radius: 9999px; padding: 0;
    background-color: var(--color-surface-container);
    border: 1px solid var(--color-border);
  }
  #mobile-navigation-drawer {
    top: calc(0.5rem + 56px + 0.5rem);
    left: clamp(1rem, 5vw, 4rem); right: clamp(1rem, 5vw, 4rem);
    border: 1px solid var(--color-border); border-radius: 1.25rem;
    max-height: calc(100vh - 5.5rem);
    max-height: calc(100dvh - 5.5rem);
  }
}
@media (max-width: 480px) {
  .modern-header-inner { padding: 0 0.5rem 0 0.75rem; gap: 0.375rem; }
  .modern-controls { gap: 0.25rem; }
  .modern-signin-btn, .modern-search-btn { padding: 0 0.75rem; min-height: 44px; }
  .modern-search-btn { min-width: 44px; justify-content: center; }
}
@media (max-width: 340px) {
  .modern-search-btn { display: none; }
}
</style>

<!-- Global Technical Editorial Header -->
<header class="modern-header-wrapper">
  <div class="modern-header-inner">
    
    <!-- 1. Brand / Identity (Left) -->
    <div class="flex items-center flex-shrink-0">
      <a class="flex items-center group text-decoration-none" href="/index.php" title="Home">
        <?php include __DIR__ . '/logo.php'; ?>
      </a>
    </div>

    <!-- 2. Desktop Primary Navigation (Center) -->
    <div class="hdr-desktop-nav" id="hdr-nav-wrap">
      <nav class="modern-nav" id="hdr-nav" aria-label="Main Navigation">
        <?php foreach ($navItems as $key => $item): 
          $isActive = ($currentPage === $key);
          $linkClass = 'modern-nav-link' . ($isActive ? ' active' : '');
        ?>
          <a href="<?= htmlspecialchars($item['url']) ?>" 
             class="<?= $linkClass ?>" 
             <?= $isActive ? 'aria-current="page"' : '' ?>
             data-path="<?= htmlspecialchars($key) ?>">
            <?= htmlspecialchars($item['label']) ?>
          </a>
        <?php endforeach; ?>

        <!-- Overflow ("More") menu: links that do not fit are moved here -->
        <div class="modern-more" id="hdr-more">
          <button type="button" class="modern-nav-link modern-more-btn" id="hdr-more-btn"
                  aria-haspopup="menu" aria-expanded="false" aria-controls="hdr-more-menu">
            More <span class="material-symbols-outlined">expand_more</span>
          </button>
          <div class="modern-more-menu" id="hdr-more-menu" role="menu">
            <?php foreach ($navItems as $key => $item): ?>
              <a href="<?= htmlspecialchars($item['url']) ?>"
                 class="modern-more-item<?= ($currentPage === $key) ? ' active' : '' ?>"
                 role="menuitem"
                 <?= ($currentPage === $key) ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($item['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </nav>
    </div>

    <!-- 3. Right Header Controls -->
    <div class="modern-controls">
      
      <!-- Search Shortcut Trigger -->
      <button type="button" 
              id="site-search-trigger" 
              class="modern-search-btn" 
              title="Search Platform Content (⌘K)"
              aria-label="Search Platform Content"
              aria-haspopup="dialog"
              aria-expanded="false">
        <span class="material-symbols-outlined text-[16px]">search</span>
        <span class="hidden xl:inline">Search</span>
      </button>

      <!-- Theme Switcher -->
      <button id="theme-toggle-btn" 
              type="button" 
              class="modern-theme-btn"
              aria-label="Toggle theme"
              title="Toggle Theme (Light / Dark / Green)">
        <span class="material-symbols-outlined text-[18px]" id="theme-toggle-icon">dark_mode</span>
      </button>

      <!-- User / Profile Controls -->
      <?php if ($isLoggedIn): 
        $userInitial = strtoupper(substr($loggedUser['name'] ?? 'U', 0, 1));
        $isUserAdmin = ($loggedUser['role'] ?? '') === 'admin';
      ?>
        <div class="relative" id="user-menu-container">
          <button id="user-menu-button" 
                  type="button"
                  class="modern-user-btn group" 
                  aria-haspopup="menu"
                  aria-expanded="false"
                  aria-controls="user-menu-dropdown"
                  title="Account options">
            <div class="modern-user-avatar">
              <?= htmlspecialchars($userInitial) ?>
            </div>
            <span class="material-symbols-outlined text-[16px] text-text-muted group-hover:text-text-primary transition-transform duration-200" id="user-menu-arrow">expand_more</span>
          </button>

          <!-- Account Popover Dropdown -->
          <div id="user-menu-dropdown" 
               class="absolute right-0 mt-2 w-60 rounded-xl bg-surface backdrop-blur-xl border border-border shadow-2xl py-2 hidden z-50 transition-all origin-top-right" 
               role="menu" 
               aria-orientation="vertical" 
               aria-labelledby="user-menu-button">
            <!-- User Profile Header -->
            <div class="px-4 py-2 border-b border-border/60">
              <p class="text-xs font-semibold text-text-primary truncate"><?= htmlspecialchars($loggedUser['name'] ?? '') ?></p>
              <p class="text-[11px] font-mono text-text-muted truncate mt-0.5"><?= htmlspecialchars($loggedUser['email'] ?? '') ?></p>
            </div>
            
            <div class="py-1" role="none">
              <a href="/dashboard/" class="flex items-center gap-2.5 px-4 py-2 text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
                <span>User Dashboard</span>
              </a>
              <a href="/dashboard/bookmarks.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-primary">bookmark</span>
                <span>Saved Bookmarks</span>
              </a>
              <a href="/dashboard/library.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-primary">folder_zip</span>
                <span>Digital Library</span>
              </a>
              <a href="/dashboard/settings.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" role="menuitem">
                <span class="material-symbols-outlined text-[18px] text-primary">settings</span>
                <span>Account Settings</span>
              </a>
            </div>

            <?php if ($isUserAdmin): ?>
              <div class="border-t border-border/60 py-1" role="none">
                <a href="/admin/" class="flex items-center justify-between px-4 py-2 text-xs text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors font-medium" role="menuitem">
                  <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[18px] text-primary">admin_panel_settings</span>
                    <span>Admin Studio</span>
                  </div>
                  <span class="text-[10px] px-1.5 py-0.5 rounded bg-primary/20 text-primary font-mono font-semibold">ADMIN</span>
                </a>
              </div>
            <?php endif; ?>

            <div class="border-t border-border/60 pt-1" role="none">
              <a href="/logout.php" class="flex items-center gap-2.5 px-4 py-2 text-xs text-red-400 hover:text-red-300 hover:bg-surface-container transition-colors" role="menuitem">
                <span class="material-symbols-outlined text-[18px]">logout</span>
                <span>Sign Out</span>
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="/login.php" 
           class="modern-signin-btn"
           title="Sign in to your account">
          <span class="material-symbols-outlined">login</span>
          <span class="hidden sm:inline">Sign In</span>
        </a>
      <?php endif; ?>

      <!-- Mobile Hamburger Button (< 768px) -->
      <button id="mobile-menu-button" 
              type="button" 
              class="hdr-burger items-center justify-center w-10 h-10 min-w-[44px] min-h-[44px] rounded-lg bg-surface-container-low text-text-secondary hover:text-text-primary hover:bg-surface-container border border-border transition-colors"
              aria-label="Toggle navigation menu"
              aria-expanded="false"
              aria-controls="mobile-navigation-drawer">
        <span class="material-symbols-outlined text-[22px]" id="mobile-menu-icon">menu</span>
      </button>
    </div>
  </div>

  <!-- Mobile Backdrop Overlay -->
  <div id="mobile-menu-backdrop" 
       class="fixed inset-0 bg-background opacity-90 backdrop-blur-md z-[90] hidden hdr-mobile-only transition-opacity" 
       aria-hidden="true"></div>

  <!-- Mobile Navigation Drawer -->
  <div id="mobile-navigation-drawer" 
       class="fixed top-16 left-0 right-0 z-[100] isolate bg-background border-b border-border px-4 py-4 hidden hdr-mobile-only shadow-2xl transition-all max-h-[calc(100vh-4rem)] overflow-y-auto"
       role="dialog" 
       aria-label="Mobile Navigation">
    <nav class="flex flex-col gap-1" aria-label="Mobile Links">
      <!-- Quick Search Button in Mobile Drawer -->
      <button type="button" 
              id="mobile-drawer-search-trigger" 
              class="min-h-[44px] px-3 py-2.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors flex items-center justify-between border border-border/50 mb-2 cursor-pointer"
              aria-label="Search Platform Content">
        <span class="text-sm flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px] text-primary">search</span>
          Search platform...
        </span>
        <kbd class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-surface-container text-text-muted border border-border">⌘K</kbd>
      </button>

      <?php foreach ($navItems as $key => $item): 
        $isActive = ($currentPage === $key);
        $mClass = $isActive
          ? 'min-h-[44px] px-3 py-2.5 rounded-lg text-primary font-semibold bg-surface-container flex items-center justify-between border border-primary/20'
          : 'min-h-[44px] px-3 py-2.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors flex items-center justify-between';
      ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" 
           class="<?= $mClass ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
          <span class="text-sm"><?= htmlspecialchars($item['label']) ?></span>
          <?php if ($isActive): ?>
            <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <!-- Drawer User Section -->
      <div class="pt-2 mt-2 border-t border-border/60 flex flex-col gap-1">
        <?php if ($isLoggedIn): ?>
          <a href="/dashboard/" class="min-h-[44px] px-3 py-2.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-primary">dashboard</span>
            <span class="text-sm">User Dashboard</span>
          </a>
          <?php if (($loggedUser['role'] ?? '') === 'admin'): ?>
            <a href="/admin/" class="min-h-[44px] px-3 py-2.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors flex items-center gap-2">
              <span class="material-symbols-outlined text-[18px] text-primary">admin_panel_settings</span>
              <span class="text-sm">Admin Studio</span>
            </a>
          <?php endif; ?>
          <a href="/logout.php" class="min-h-[44px] px-3 py-2.5 rounded-lg text-red-400 hover:bg-surface-container transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">logout</span>
            <span class="text-sm">Sign Out</span>
          </a>
        <?php else: ?>
          <a href="/login.php" class="min-h-[44px] px-3 py-2.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-primary">login</span>
            <span class="text-sm">Sign In</span>
          </a>
          <a href="/register.php" class="min-h-[44px] px-3 py-2.5 rounded-lg text-primary hover:bg-surface-container transition-colors flex items-center gap-2 font-medium">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span class="text-sm">Create Account</span>
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>

<!-- Global Shell Interaction Script (Theme, Mobile Menu, Keyboard Shortcuts) -->
<script>
  (function() {
    // Mobile Navigation Controller with Keyboard and Scroll Lock
    var menuBtn = document.getElementById('mobile-menu-button');
    var drawer = document.getElementById('mobile-navigation-drawer');
    var backdrop = document.getElementById('mobile-menu-backdrop');
    var menuIcon = document.getElementById('mobile-menu-icon');

    function closeMobileMenu() {
      if (!drawer || drawer.classList.contains('hidden')) return;
      drawer.classList.add('hidden');
      if (backdrop) backdrop.classList.add('hidden');
      if (menuBtn) {
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.focus();
      }
      if (menuIcon) menuIcon.textContent = 'menu';
      document.body.style.overflow = '';
    }

    function openMobileMenu() {
      if (!drawer) return;
      drawer.classList.remove('hidden');
      if (backdrop) backdrop.classList.remove('hidden');
      if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
      if (menuIcon) menuIcon.textContent = 'close';
      document.body.style.overflow = 'hidden';
    }

    if (menuBtn && drawer) {
      menuBtn.addEventListener('click', function() {
        var isOpen = menuBtn.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
          closeMobileMenu();
        } else {
          openMobileMenu();
        }
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', closeMobileMenu);
    }

    // Site Search Button Triggers
    var searchTrigger = document.getElementById('site-search-trigger');
    if (searchTrigger) {
      searchTrigger.addEventListener('click', function() {
        if (window.SiteSearch && typeof window.SiteSearch.open === 'function') {
          window.SiteSearch.open();
        }
      });
    }

    var drawerSearchTrigger = document.getElementById('mobile-drawer-search-trigger');
    if (drawerSearchTrigger) {
      drawerSearchTrigger.addEventListener('click', function() {
        closeMobileMenu();
        if (window.SiteSearch && typeof window.SiteSearch.open === 'function') {
          window.SiteSearch.open();
        }
      });
    }

    // User Account Popover Controller
    var userMenuBtn = document.getElementById('user-menu-button');
    var userMenuDropdown = document.getElementById('user-menu-dropdown');
    var userMenuArrow = document.getElementById('user-menu-arrow');

    function closeUserMenu() {
      if (!userMenuDropdown || userMenuDropdown.classList.contains('hidden')) return;
      userMenuDropdown.classList.add('hidden');
      if (userMenuBtn) {
        userMenuBtn.setAttribute('aria-expanded', 'false');
      }
      if (userMenuArrow) {
        userMenuArrow.style.transform = '';
      }
    }

    function openUserMenu() {
      if (!userMenuDropdown) return;
      userMenuDropdown.classList.remove('hidden');
      if (userMenuBtn) {
        userMenuBtn.setAttribute('aria-expanded', 'true');
      }
      if (userMenuArrow) {
        userMenuArrow.style.transform = 'rotate(180deg)';
      }
    }

    if (userMenuBtn && userMenuDropdown) {
      userMenuBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = userMenuBtn.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
          closeUserMenu();
        } else {
          closeMobileMenu();
          openUserMenu();
        }
      });

      // Keyboard navigation within popover menu
      userMenuDropdown.addEventListener('keydown', function(e) {
        var items = Array.from(userMenuDropdown.querySelectorAll('[role="menuitem"]'));
        var currentIndex = items.indexOf(document.activeElement);

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          var nextIndex = (currentIndex + 1) % items.length;
          items[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          var prevIndex = (currentIndex - 1 + items.length) % items.length;
          items[prevIndex].focus();
        } else if (e.key === 'Tab') {
          closeUserMenu();
        }
      });

      // Outside click closes user menu
      document.addEventListener('click', function(e) {
        if (!userMenuDropdown.contains(e.target) && e.target !== userMenuBtn && !userMenuBtn.contains(e.target)) {
          closeUserMenu();
        }
      });
    }

    // Close overlays on Escape Key
    window.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeMobileMenu();
        closeUserMenu();
      }
    });


    // Progressive navigation: move links that do not fit into the "More" menu
    (function() {
      var wrap = document.getElementById('hdr-nav-wrap');
      var nav = document.getElementById('hdr-nav');
      var more = document.getElementById('hdr-more');
      var moreBtn = document.getElementById('hdr-more-btn');
      if (!wrap || !nav || !more || !moreBtn) return;
      var links = Array.prototype.slice.call(nav.querySelectorAll(':scope > a.modern-nav-link'));
      var items = Array.prototype.slice.call(more.querySelectorAll('.modern-more-item'));

      function closeMore() {
        more.classList.remove('open');
        moreBtn.setAttribute('aria-expanded', 'false');
      }

      function layout() {
        if (getComputedStyle(wrap).display === 'none') return;
        links.forEach(function(a) { a.style.display = ''; });
        items.forEach(function(a) { a.style.display = 'none'; });
        more.style.display = 'none';
        moreBtn.classList.remove('active');
        var i = links.length;
        while (i > 1 && nav.offsetWidth > wrap.clientWidth) {
          i--;
          links[i].style.display = 'none';
          items[i].style.display = 'block';
          more.style.display = 'flex';
        }
        if (more.style.display === 'flex') {
          var activeHidden = false;
          for (var k = i; k < links.length; k++) {
            if (links[k].classList.contains('active')) activeHidden = true;
          }
          moreBtn.classList.toggle('active', activeHidden);
        } else {
          closeMore();
        }
      }

      moreBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        var open = more.classList.toggle('open');
        moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.addEventListener('click', function(e) {
        if (!more.contains(e.target)) closeMore();
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMore();
      });

      var raf = null;
      function schedule() {
        if (raf) cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function() { raf = null; layout(); });
      }
      window.addEventListener('resize', schedule);
      window.addEventListener('load', schedule);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
      if (window.ResizeObserver) new ResizeObserver(schedule).observe(wrap);
      layout();
    })();

    // Close mobile menu on resize past the 768px breakpoint
    window.addEventListener('resize', function() {
      if (window.innerWidth >= 768) {
        closeMobileMenu();
      }
      closeUserMenu();
    });
  })();
</script>

<?php require_once __DIR__ . '/search-modal.php'; ?>

