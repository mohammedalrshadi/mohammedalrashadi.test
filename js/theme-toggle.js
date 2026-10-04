/**
 * ============================================================
 * MOHAMMED ALRASHADI — 3-THEME CONTROLLER (Light / Dark / Green)
 * Architecture: Editorial Engineering Platform
 * 
 * Theme Cycle:
 *   Light ("light") -> Dark ("dark") -> Green ("green") -> Light ("light")
 *
 * Storage:
 *   localStorage key: 'site-theme' (supports fallback to legacy 'theme')
 * 
 * DOM Application:
 *   document.documentElement.setAttribute('data-theme', theme)
 * ============================================================
 */

(function () {
  'use strict';

  var THEMES = ['light', 'dark', 'green'];
  var STORAGE_KEY = 'site-theme';
  var LEGACY_STORAGE_KEY = 'theme';

  /**
   * Resolve current active theme from localStorage or system preference.
   * Default fallback to system dark/light, never green.
   */
  function getPreferredTheme() {
    try {
      var stored = localStorage.getItem(STORAGE_KEY) || localStorage.getItem(LEGACY_STORAGE_KEY);
      if (stored && THEMES.indexOf(stored) !== -1) {
        return stored;
      }
      // If an invalid or obsolete theme was stored, clean it up
      if (stored && THEMES.indexOf(stored) === -1) {
        try {
          localStorage.removeItem(STORAGE_KEY);
          localStorage.removeItem(LEGACY_STORAGE_KEY);
        } catch (err) { }
      }
      var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      return systemDark ? 'dark' : 'light';
    } catch (e) {
      return 'dark';
    }
  }

  /**
   * Helper to enable CSS transitions temporarily on theme change.
   */
  function triggerTransition() {
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!prefersReduced) {
      document.documentElement.classList.add('theme-transitioning');
      setTimeout(function() {
        document.documentElement.classList.remove('theme-transitioning');
      }, 300);
    }
  }

  /**
   * Apply theme attribute to documentElement and update all toggle buttons.
   */
  function applyTheme(theme, skipStorage) {
    if (THEMES.indexOf(theme) === -1) {
      theme = 'dark';
    }

    document.documentElement.setAttribute('data-theme', theme);
    if (theme === 'dark' || theme === 'green') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }

    if (!skipStorage) {
      try {
        localStorage.setItem(STORAGE_KEY, theme);
        // Keep legacy key synced for any external readers
        localStorage.setItem(LEGACY_STORAGE_KEY, theme);
      } catch (e) { }
    }

    updateToggleButtons(theme);
  }

  /**
   * Cycle to the next theme: light -> dark -> green -> light
   */
  function cycleTheme() {
    triggerTransition();
    var current = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
    var currentIndex = THEMES.indexOf(current);
    var nextIndex = (currentIndex === -1 ? 0 : (currentIndex + 1) % THEMES.length);
    var nextTheme = THEMES[nextIndex];
    applyTheme(nextTheme);
  }

  /**
   * Update visual icons and aria labels on all theme buttons on the page.
   */
  function updateToggleButtons(theme) {
    var buttons = document.querySelectorAll('#theme-toggle-btn, .theme-toggle-btn, #admin-theme-toggle');
    if (!buttons || buttons.length === 0) return;

    var themeNames = {
      'light': 'Light',
      'dark': 'Dark',
      'green': 'Green'
    };
    var currentThemeName = themeNames[theme] || 'Light';
    var currentIndex = THEMES.indexOf(theme);
    var nextIndex = (currentIndex === -1 ? 1 : (currentIndex + 1) % THEMES.length);
    var nextThemeName = themeNames[THEMES[nextIndex]] || 'Light';
    var labelText = 'Theme: ' + currentThemeName + ' (Click to switch to ' + nextThemeName + ')';

    buttons.forEach(function (btn) {
      btn.setAttribute('aria-label', labelText);
      btn.setAttribute('title', labelText);

      // 1. Material Symbols icon update (Public pages & User Dashboard)
      var matIcon = btn.querySelector('.material-symbols-outlined, #theme-toggle-icon');
      if (matIcon) {
        if (theme === 'light') {
          matIcon.textContent = 'light_mode';
        } else if (theme === 'dark') {
          matIcon.textContent = 'dark_mode';
        } else if (theme === 'green') {
          matIcon.textContent = 'eco';
        }
      }

      // 2. Font Awesome icon update
      var faIcon = btn.querySelector('i.fa-solid, i.fas, i.far, i.fa-sun, i.fa-moon, i.fa-leaf');
      if (faIcon) {
        faIcon.className = '';
        if (theme === 'light') {
          faIcon.className = 'fas fa-sun';
        } else if (theme === 'dark') {
          faIcon.className = 'fas fa-moon';
        } else if (theme === 'green') {
          faIcon.className = 'fas fa-leaf';
        }
      }

      // 3. Status text badge if present (e.g. in admin sidebar)
      var textSpan = btn.querySelector('.theme-text-label');
      if (textSpan) {
        textSpan.textContent = currentThemeName;
      }
    });

    var segmentedOptions = document.querySelectorAll('.theme-opt');
    segmentedOptions.forEach(function (opt) {
      if (opt.getAttribute('data-theme-val') === theme) {
        opt.classList.add('active');
      } else {
        opt.classList.remove('active');
      }
    });
  }

  /**
   * Bind event listeners to all theme toggle buttons across the DOM.
   */
  function initThemeButtons() {
    var buttons = document.querySelectorAll('#theme-toggle-btn, .theme-toggle-btn, #admin-theme-toggle');
    buttons.forEach(function (btn) {
      if (!btn.getAttribute('data-theme-listener-bound')) {
        btn.setAttribute('data-theme-listener-bound', 'true');
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          cycleTheme();
        });
      }
    });

    var segmentedOptions = document.querySelectorAll('.theme-opt');
    segmentedOptions.forEach(function (opt) {
      if (!opt.getAttribute('data-theme-listener-bound')) {
        opt.setAttribute('data-theme-listener-bound', 'true');
        opt.addEventListener('click', function (e) {
          e.preventDefault();
          triggerTransition();
          var val = this.getAttribute('data-theme-val');
          applyTheme(val);
        });
      }
    });

    var activeTheme = document.documentElement.getAttribute('data-theme');
    if (!activeTheme || THEMES.indexOf(activeTheme) === -1) {
      activeTheme = getPreferredTheme();
      applyTheme(activeTheme);
    } else {
      updateToggleButtons(activeTheme);
    }
  }

  // Cross-tab synchronization
  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY || e.key === LEGACY_STORAGE_KEY) {
      if (e.newValue && THEMES.indexOf(e.newValue) !== -1) {
        var currentTheme = document.documentElement.getAttribute('data-theme');
        if (currentTheme !== e.newValue) {
          triggerTransition();
          applyTheme(e.newValue, true);
        }
      }
    }
  });

  // System color scheme change listener (only active if no manual choice stored)
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
      try {
        var manualChoice = localStorage.getItem(STORAGE_KEY);
        if (!manualChoice) {
          applyTheme(e.matches ? 'dark' : 'light');
        }
      } catch (err) { }
    });
  }

  // Expose global controller
  window.SiteTheme = {
    getTheme: function () {
      var current = document.documentElement.getAttribute('data-theme');
      return (current && THEMES.indexOf(current) !== -1) ? current : getPreferredTheme();
    },
    setTheme: applyTheme,
    cycle: cycleTheme,
    init: initThemeButtons
  };

  // Immediate init or DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeButtons);
  } else {
    initThemeButtons();
  }
})();

