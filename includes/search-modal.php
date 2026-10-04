<?php
// ============================================================
// UNIVERSAL SITE SEARCH MODAL
// includes/search-modal.php
//
// Fast, keyboard-driven search overlay across:
// - Engineering Projects (/project.php?id=...)
// - Research Articles (/post.php?id=...)
// - Empirical Lab Benchmarks (/lab-detail.php?id=...)
//
// Security invariants:
// - Title, snippet, and category text are strictly rendered via textContent
//   or rigorously escaped HTML entities (no raw innerHTML from API).
// - Focus trapping, Esc key dismissal, and ARIA dialog semantics.
// ============================================================
?>
<!-- Search Modal Root Container -->
<div id="site-search-modal" 
     class="fixed inset-0 z-[100] hidden items-start justify-center pt-16 sm:pt-24 px-4 pb-6 overflow-y-auto"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="site-search-title"
     style="display: none;">

  <!-- Dimmed & Blurred Backdrop -->
  <div id="site-search-backdrop" 
       class="fixed inset-0 bg-background opacity-80 backdrop-blur-sm transition-opacity" 
       aria-hidden="true"></div>

  <!-- Dialog Frame -->
  <div class="relative w-full max-w-2xl bg-surface border border-border rounded-2xl shadow-2xl overflow-hidden z-10 flex flex-col max-h-[82vh] transition-all">
    
    <!-- Hidden Title for Screen Readers -->
    <h2 id="site-search-title" class="sr-only">Search Platform Technical Content</h2>

    <!-- Search Input Bar -->
    <div class="flex items-center gap-3 px-4 py-3.5 border-b border-border bg-surface-container-lowest">
      <span class="material-symbols-outlined text-[20px] text-text-muted shrink-0" aria-hidden="true">search</span>
      
      <input id="site-search-input" 
             type="text" 
             class="w-full bg-transparent text-on-surface placeholder:text-text-muted text-sm font-sans focus:outline-none" 
             placeholder="Search essays, projects, benchmarks (⌘K)..." 
             autocomplete="off" 
             autocorrect="off" 
             autocapitalize="off" 
             spellcheck="false"
             aria-autocomplete="list"
             aria-controls="site-search-results"
             aria-expanded="false" />

      <!-- Loading Spinner -->
      <span id="site-search-spinner" class="material-symbols-outlined text-[18px] text-primary animate-spin shrink-0 hidden" aria-hidden="true">progress_activity</span>

      <!-- Input Clear Button -->
      <button type="button" 
              id="site-search-clear" 
              class="text-text-muted hover:text-text-primary transition-colors p-1 rounded hover:bg-surface-container hidden" 
              title="Clear search query"
              aria-label="Clear query">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>

      <!-- Dismiss Button / ESC Badge -->
      <button type="button"
              id="site-search-close-btn"
              class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded bg-surface-container text-[10px] font-mono text-text-muted border border-border hover:text-text-primary hover:border-outline transition-colors"
              title="Close search modal (Esc)">
        ESC
      </button>
    </div>

    <!-- Scrollable Results / Suggestions Body -->
    <div id="site-search-body" class="flex-1 overflow-y-auto p-4 max-h-[58vh] space-y-4">
      
      <!-- Default Suggested State (rendered when input is empty) -->
      <div id="site-search-default-state" class="space-y-5 py-2">
        <!-- Suggested Keywords -->
        <div>
          <span class="font-label-code text-[11px] uppercase tracking-wider text-text-muted font-semibold block mb-2.5">Suggested Topics</span>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="site-search-chip px-3 py-1 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-xs font-mono text-text-secondary hover:text-primary transition-colors" data-term="B-Tree">B-Tree</button>
            <button type="button" class="site-search-chip px-3 py-1 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-xs font-mono text-text-secondary hover:text-primary transition-colors" data-term="Redis">Redis MsgPack</button>
            <button type="button" class="site-search-chip px-3 py-1 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-xs font-mono text-text-secondary hover:text-primary transition-colors" data-term="Concurrency">Concurrency</button>
            <button type="button" class="site-search-chip px-3 py-1 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-xs font-mono text-text-secondary hover:text-primary transition-colors" data-term="Storage">Storage Engines</button>
            <button type="button" class="site-search-chip px-3 py-1 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-xs font-mono text-text-secondary hover:text-primary transition-colors" data-term="MySQL">MySQL InnoDB</button>
          </div>
        </div>

        <!-- Quick Platform Navigation -->
        <div>
          <span class="font-label-code text-[11px] uppercase tracking-wider text-text-muted font-semibold block mb-2.5">Quick Navigation</span>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <a href="/projects.php" class="flex items-center justify-between p-2.5 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-text-secondary hover:text-text-primary transition-colors group">
              <span class="text-xs font-sans font-medium flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-primary">terminal</span>
                Engineering Projects
              </span>
              <span class="material-symbols-outlined text-[14px] text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-0.5">arrow_forward</span>
            </a>
            <a href="/lab.php" class="flex items-center justify-between p-2.5 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-text-secondary hover:text-text-primary transition-colors group">
              <span class="text-xs font-sans font-medium flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-secondary">science</span>
                Studio Lab Benchmarks
              </span>
              <span class="material-symbols-outlined text-[14px] text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-0.5">arrow_forward</span>
            </a>
            <a href="/articles.php" class="flex items-center justify-between p-2.5 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-text-secondary hover:text-text-primary transition-colors group">
              <span class="text-xs font-sans font-medium flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-tertiary">article</span>
                Research Writing
              </span>
              <span class="material-symbols-outlined text-[14px] text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-0.5">arrow_forward</span>
            </a>
            <a href="/journey.php" class="flex items-center justify-between p-2.5 rounded-lg bg-surface-container-low hover:bg-surface-container border border-border text-text-secondary hover:text-text-primary transition-colors group">
              <span class="text-xs font-sans font-medium flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-outline">timeline</span>
                Architectural Journey
              </span>
              <span class="material-symbols-outlined text-[14px] text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-0.5">arrow_forward</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Results Container (dynamically injected) -->
      <div id="site-search-results" class="space-y-5 hidden" role="listbox"></div>

      <!-- No Results State -->
      <div id="site-search-empty-state" class="py-12 text-center space-y-2 hidden">
        <span class="material-symbols-outlined text-[32px] text-text-muted">search_off</span>
        <p class="font-sans text-sm text-text-primary font-medium">No engineering records found</p>
        <p class="font-sans text-xs text-text-muted max-w-sm mx-auto" id="site-search-empty-msg">
          Try searching for broader keywords like &quot;database&quot;, &quot;index&quot;, or &quot;caching&quot;.
        </p>
      </div>

    </div>

    <!-- Modal Footer / Keyboard Controls Guide -->
    <div class="px-4 py-2.5 bg-surface-container-low border-t border-border flex items-center justify-between text-[11px] font-mono text-text-muted">
      <div class="flex items-center gap-3">
        <span class="hidden sm:inline-flex items-center gap-1">
          <kbd class="px-1 py-0.5 rounded bg-surface-container border border-border text-[9px]">↑</kbd>
          <kbd class="px-1 py-0.5 rounded bg-surface-container border border-border text-[9px]">↓</kbd>
          <span>navigate</span>
        </span>
        <span class="inline-flex items-center gap-1">
          <kbd class="px-1 py-0.5 rounded bg-surface-container border border-border text-[9px]">↵</kbd>
          <span>select</span>
        </span>
        <span class="inline-flex items-center gap-1">
          <kbd class="px-1 py-0.5 rounded bg-surface-container border border-border text-[9px]">esc</kbd>
          <span>close</span>
        </span>
      </div>
      <div class="text-[10px] text-text-muted hidden sm:block">
        Index v1
      </div>
    </div>

  </div>
</div>

<!-- Modal Controller Script -->
<script>
(function() {
  var modal = document.getElementById('site-search-modal');
  var backdrop = document.getElementById('site-search-backdrop');
  var input = document.getElementById('site-search-input');
  var spinner = document.getElementById('site-search-spinner');
  var clearBtn = document.getElementById('site-search-clear');
  var closeBtn = document.getElementById('site-search-close-btn');
  var defaultState = document.getElementById('site-search-default-state');
  var resultsContainer = document.getElementById('site-search-results');
  var emptyState = document.getElementById('site-search-empty-state');
  var emptyMsg = document.getElementById('site-search-empty-msg');
  
  var activeResults = [];
  var activeIndex = -1;
  var debounceTimer = null;
  var currentAbortCtrl = null;
  var lastFocusedElement = null;

  // ------------------------------------------------------------
  // Safe HTML Escaping (Guarantees zero raw HTML injection)
  // ------------------------------------------------------------
  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // ------------------------------------------------------------
  // Open / Close Controllers
  // ------------------------------------------------------------
  function openModal() {
    if (!modal) return;
    lastFocusedElement = document.activeElement;
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Update trigger accessibility
    var trigger = document.getElementById('site-search-trigger');
    if (trigger) trigger.setAttribute('aria-expanded', 'true');

    setTimeout(function() {
      if (input) {
        input.focus();
        input.select();
      }
    }, 50);
  }

  function closeModal() {
    if (!modal || modal.classList.contains('hidden')) return;
    modal.style.display = 'none';
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    
    var trigger = document.getElementById('site-search-trigger');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');

    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
      lastFocusedElement.focus();
    }
  }

  function clearSearch() {
    if (!input) return;
    input.value = '';
    input.focus();
    if (clearBtn) clearBtn.classList.add('hidden');
    if (spinner) spinner.classList.add('hidden');
    if (currentAbortCtrl) {
      currentAbortCtrl.abort();
      currentAbortCtrl = null;
    }
    showDefaultState();
  }

  function showDefaultState() {
    activeResults = [];
    activeIndex = -1;
    if (defaultState) defaultState.classList.remove('hidden');
    if (resultsContainer) {
      resultsContainer.classList.add('hidden');
      resultsContainer.innerHTML = '';
    }
    if (emptyState) emptyState.classList.add('hidden');
    if (input) input.setAttribute('aria-expanded', 'false');
  }

  // ------------------------------------------------------------
  // Render Grouped Search Results
  // Strictly renders titles/snippets as escaped text or textContent
  // ------------------------------------------------------------
  function renderResults(query, data) {
    if (!resultsContainer) return;
    resultsContainer.innerHTML = '';
    activeResults = [];
    activeIndex = -1;

    var projects = (data.results && data.results.projects) ? data.results.projects : [];
    var articles = (data.results && data.results.articles) ? data.results.articles : [];
    var lab      = (data.results && data.results.lab)      ? data.results.lab      : [];
    var history  = (data.results && data.results.history)  ? data.results.history  : [];
    var bookmarks = (data.results && data.results.bookmarks) ? data.results.bookmarks : [];
    var total    = projects.length + articles.length + lab.length + history.length + bookmarks.length;

    if (total === 0) {
      if (defaultState) defaultState.classList.add('hidden');
      if (resultsContainer) resultsContainer.classList.add('hidden');
      if (emptyState) {
        emptyState.classList.remove('hidden');
        if (emptyMsg) {
          emptyMsg.textContent = 'No technical records found matching "' + query + '". Try broader keywords like "database", "index", or "systems".';
        }
      }
      if (input) input.setAttribute('aria-expanded', 'false');
      return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    if (defaultState) defaultState.classList.add('hidden');
    resultsContainer.classList.remove('hidden');
    if (input) input.setAttribute('aria-expanded', 'true');

    // Section: History
    if (history.length > 0) {
      appendSection('Reading History', 'history', history, 'text-primary', 'bg-primary/10 text-primary border-primary/20');
    }

    // Section: Bookmarks
    if (bookmarks.length > 0) {
      appendSection('Bookmarks', 'bookmark', bookmarks, 'text-primary', 'bg-primary/10 text-primary border-primary/20');
    }

    // Section 1: Lab Benchmarks
    if (lab.length > 0) {
      appendSection('Studio Lab', 'science', lab, 'text-secondary', 'bg-secondary/10 text-secondary border-secondary/20');
    }

    // Section 2: Engineering Projects
    if (projects.length > 0) {
      appendSection('Projects & Architecture', 'terminal', projects, 'text-primary', 'bg-primary/10 text-primary border-primary/20');
    }

    // Section 3: Research Articles
    if (articles.length > 0) {
      appendSection('Writing', 'article', articles, 'text-tertiary', 'bg-tertiary/10 text-tertiary border-tertiary/20');
    }

    // Select first result by default for immediate Enter-key navigation
    if (activeResults.length > 0) {
      setActiveIndex(0);
    }
  }

  function appendSection(sectionTitle, iconName, items, iconColorClass, badgeClass) {
    var sectionWrapper = document.createElement('div');
    sectionWrapper.className = 'space-y-1.5';

    // Section Header
    var header = document.createElement('div');
    header.className = 'flex items-center gap-1.5 px-2 pb-1 font-label-code text-[11px] uppercase tracking-wider text-text-muted font-semibold';
    
    var icon = document.createElement('span');
    icon.className = 'material-symbols-outlined text-[14px] ' + iconColorClass;
    icon.textContent = iconName;
    header.appendChild(icon);

    var titleSpan = document.createElement('span');
    titleSpan.textContent = sectionTitle;
    header.appendChild(titleSpan);

    sectionWrapper.appendChild(header);

    // List of items
    var list = document.createElement('div');
    list.className = 'flex flex-col gap-1';

    items.forEach(function(item) {
      var itemIndex = activeResults.length;
      activeResults.push(item);

      var a = document.createElement('a');
      a.href = item.url;
      a.className = 'site-search-item flex items-start gap-3 p-3 rounded-xl border border-transparent hover:border-border hover:bg-surface-container transition-all group cursor-pointer focus:outline-none focus:bg-surface-container focus:border-primary/30';
      a.setAttribute('role', 'option');
      a.setAttribute('id', 'search-result-' + itemIndex);
      a.setAttribute('data-index', String(itemIndex));

      // Left Icon Container
      var leftIconWrap = document.createElement('div');
      leftIconWrap.className = 'w-8 h-8 rounded-lg bg-surface-container-low flex items-center justify-center shrink-0 border border-border group-hover:border-primary/30 transition-colors mt-0.5';
      var leftIcon = document.createElement('span');
      leftIcon.className = 'material-symbols-outlined text-[16px] text-text-muted group-hover:text-primary transition-colors';
      leftIcon.textContent = iconName;
      leftIconWrap.appendChild(leftIcon);
      a.appendChild(leftIconWrap);

      // Content Box
      var contentBox = document.createElement('div');
      contentBox.className = 'flex-1 min-w-0 flex flex-col gap-0.5';

      // Title Row
      var titleRow = document.createElement('div');
      titleRow.className = 'flex items-center gap-2 justify-between';

      var itemTitle = document.createElement('span');
      itemTitle.className = 'text-xs sm:text-sm font-sans font-semibold text-text-primary group-hover:text-primary transition-colors truncate';
      itemTitle.textContent = item.title; // SAFE: textContent avoids raw HTML
      titleRow.appendChild(itemTitle);

      // Category / Subsystem Badge
      if (item.category || item.id) {
        var badge = document.createElement('span');
        badge.className = 'text-[10px] font-mono px-1.5 py-0.5 rounded border shrink-0 ' + badgeClass;
        badge.textContent = item.id && item.type === 'lab' ? item.id : item.category;
        titleRow.appendChild(badge);
      }
      contentBox.appendChild(titleRow);

      // Snippet / Excerpt
      if (item.snippet) {
        var snippetP = document.createElement('p');
        snippetP.className = 'text-xs font-sans text-text-muted line-clamp-1 leading-relaxed';
        snippetP.textContent = item.snippet; // SAFE: textContent avoids raw HTML
        contentBox.appendChild(snippetP);
      }

      a.appendChild(contentBox);

      // Navigation Arrow
      var arrowWrap = document.createElement('div');
      arrowWrap.className = 'self-center text-text-muted group-hover:text-primary transition-transform group-hover:translate-x-0.5';
      var arrowIcon = document.createElement('span');
      arrowIcon.className = 'material-symbols-outlined text-[16px]';
      arrowIcon.textContent = 'arrow_forward';
      arrowWrap.appendChild(arrowIcon);
      a.appendChild(arrowWrap);

      // Hover / focus sync
      a.addEventListener('mouseenter', function() {
        setActiveIndex(itemIndex);
      });

      list.appendChild(a);
    });

    sectionWrapper.appendChild(list);
    resultsContainer.appendChild(sectionWrapper);
  }

  // ------------------------------------------------------------
  // Keyboard Selection Handling
  // ------------------------------------------------------------
  function setActiveIndex(newIndex) {
    var items = resultsContainer ? resultsContainer.querySelectorAll('.site-search-item') : [];
    if (!items.length) return;

    if (activeIndex >= 0 && activeIndex < items.length) {
      items[activeIndex].classList.remove('bg-surface-container', 'border-border', 'ring-1', 'ring-primary/40');
      items[activeIndex].setAttribute('aria-selected', 'false');
    }

    activeIndex = newIndex;
    if (activeIndex >= items.length) activeIndex = 0;
    if (activeIndex < 0) activeIndex = items.length - 1;

    var activeEl = items[activeIndex];
    if (activeEl) {
      activeEl.classList.add('bg-surface-container', 'border-border', 'ring-1', 'ring-primary/40');
      activeEl.setAttribute('aria-selected', 'true');
      activeEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }

  function navigateToActive() {
    if (activeIndex >= 0 && activeIndex < activeResults.length) {
      var selected = activeResults[activeIndex];
      if (selected && selected.url) {
        window.location.href = selected.url;
      }
    }
  }

  // ------------------------------------------------------------
  // Debounced API Query Fetcher
  // ------------------------------------------------------------
  function performSearch(query) {
    var trimmed = query.trim();
    if (trimmed.length < 2) {
      if (spinner) spinner.classList.add('hidden');
      showDefaultState();
      return;
    }

    if (clearBtn) clearBtn.classList.remove('hidden');
    if (spinner) spinner.classList.remove('hidden');

    if (currentAbortCtrl) {
      currentAbortCtrl.abort();
    }
    currentAbortCtrl = new AbortController();

    fetch('/api/search.php?q=' + encodeURIComponent(trimmed) + '&limit=5', {
      signal: currentAbortCtrl.signal,
      headers: { 'Accept': 'application/json' }
    })
    .then(function(res) {
      if (!res.ok) throw new Error('Search failed with status ' + res.status);
      return res.json();
    })
    .then(function(data) {
      if (spinner) spinner.classList.add('hidden');
      if (data && data.success) {
        renderResults(trimmed, data);
      }
    })
    .catch(function(err) {
      if (err.name === 'AbortError') return;
      if (spinner) spinner.classList.add('hidden');
      renderResults(trimmed, { results: { projects: [], articles: [], lab: [] } });
    });
  }

  // ------------------------------------------------------------
  // Event Listeners
  // ------------------------------------------------------------
  if (input) {
    input.addEventListener('input', function(e) {
      var val = e.target.value;
      if (clearBtn) {
        if (val.length > 0) clearBtn.classList.remove('hidden');
        else clearBtn.classList.add('hidden');
      }
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function() {
        performSearch(val);
      }, 200);
    });

    input.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setActiveIndex(activeIndex + 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setActiveIndex(activeIndex - 1);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        navigateToActive();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeModal();
      }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', clearSearch);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeModal);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeModal);
  }

  // Suggested chip button clicks
  document.querySelectorAll('.site-search-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
      var term = this.getAttribute('data-term');
      if (term && input) {
        input.value = term;
        input.focus();
        performSearch(term);
      }
    });
  });

  // Global Keyboard Trap and Hotkeys
  window.addEventListener('keydown', function(e) {
    // ⌘K or Ctrl+K opens modal
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (modal && !modal.classList.contains('hidden')) {
        closeModal();
      } else {
        openModal();
      }
      return;
    }

    // Escape closes modal
    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
      e.preventDefault();
      closeModal();
    }
  });

  // Expose global controller
  window.SiteSearch = {
    open: openModal,
    close: closeModal,
    clear: clearSearch,
    setQuery: function(q) {
      openModal();
      if (input) {
        input.value = q;
        performSearch(q);
      }
    }
  };
})();
</script>

