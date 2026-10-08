<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'projects';
$pageTitle = 'Projects';
$pageDescription = 'Production-ready systems, architectural prototypes, distributed pipelines, and computing tools by Mohammed Alrashadi.';
$canonicalUrl = 'https://mohammedalrashadi.com/projects.php';

require_once __DIR__ . '/includes/image_helper.php';

// Database query with fallback
$projects = [];
$totalCount = 0;

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        require_once __DIR__ . '/src/autoload.php';
        $pdo = getDB();
        $postRepo = new \Domain\Content\PostRepository($pdo);
        $projects = $postRepo->getPublishedProjects();

        $categoryRepo = new \Domain\Content\CategoryRepository($pdo);
        $categories = $categoryRepo->getNamesByType('project');
    } catch (Exception $e) {
        $projects = [];
        $categories = [];
    }}

$totalCount = count($projects);
?>
<!DOCTYPE html>
<html lang="en" class="is-animating">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="page-transition-wrapper relative z-0 w-full pt-16 bg-background min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">
      
      <!-- Top Section: Header & Metadata Bar -->
      <section class="container-catalog reveal-section pt-space-xl pb-space-lg">
        <div class="flex flex-col gap-space-sm">
          <div class="flex items-center gap-space-xs text-primary font-label-micro uppercase tracking-widest font-semibold">
            <span>Engineering Archive — Systems &amp; Projects</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface tracking-tight font-bold">Projects</h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl leading-relaxed">
            Practical systems, architectural prototypes, distributed pipelines, and computing tools built to explore resilience, concurrency, and real-world scale.
          </p>
        </div>

        <!-- Filter & Search Controls -->
        <div class="mt-space-lg flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md">
          <!-- Category Pills with Controlled Scroll & Edge-Fade -->
          <div class="filter-scroll-wrapper">
            <div class="filter-scroll-container" id="category-filters">
              <button class="filter-pill active" data-category="all" type="button">
                All (<?= (int)$totalCount ?>)
              </button>
              <?php foreach ($categories as $cat): ?>
                <button class="filter-pill" data-category="<?= htmlspecialchars(strtolower($cat)) ?>" type="button">
                  <?= htmlspecialchars($cat) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Search Input -->
          <div class="relative flex-1 sm:w-72">
            <label for="project-search" class="sr-only">Search systems</label>
            <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-outline text-[1.125rem] pointer-events-none">search</span>
            <input class="input-search" id="project-search" placeholder="Search systems..." type="text"/>
            <kbd class="absolute right-space-sm top-1/2 -translate-y-1/2 font-label-code text-label-micro bg-surface-container-high text-on-surface-variant px-space-xs py-[0.125rem] rounded">⌘K</kbd>
          </div>
        </div>
      </section>

      <!-- Projects Grid -->
      <section class="container-catalog reveal-section pb-space-2xl">
        <div class="flex items-center justify-between pb-space-md border-b border-outline-variant/10 mb-space-lg">
          <h2 class="font-headline-md text-headline-md text-on-surface font-semibold">Systems &amp; Case Studies</h2>
          <span class="font-label-code text-label-code text-outline" id="project-count">Showing <?= count($projects) ?> Entries</span>
        </div>

        <?php if (empty($projects)): ?>
          <div class="staging-state" style="margin-bottom: 2rem;">
            <span class="staging-state-label">SYSTEMS ARCHIVE — IN STAGING</span>
            <p class="staging-state-title">Case Studies Being Documented</p>
            <p class="staging-state-desc">Architecture blueprints, performance post-mortems, and systems case studies will appear here as they are finalized from the Studio Lab.</p>
            <a href="/lab.php" class="btn btn-secondary" style="margin-top: 0.75rem; font-size: 0.8125rem;">Explore Lab Benchmarks</a>
          </div>
        <?php else: ?>
        <div class="flex flex-col border-t border-outline-variant/20" id="project-grid">
          <?php foreach ($projects as $proj): 
            $catLower = strtolower($proj['category']);
            $detailUrl = '/projects/' . rawurlencode($proj['slug'] ?? $proj['id']);
            $descSnippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($proj['content']))), 0, 200, 'UTF-8');
          ?>
            <div class="project-item group flex flex-col md:flex-row md:items-start gap-space-md md:gap-space-xl py-space-xl border-b border-outline-variant/20 transition-colors hover:bg-surface-container-lowest" 
                 data-category="<?= htmlspecialchars($catLower) ?>"
                 data-title="<?= htmlspecialchars(strtolower($proj['title'])) ?>">
              
              <!-- Meta Column -->
              <div class="flex flex-col gap-1 md:w-1/4 shrink-0">
                <span class="font-label-code text-label-micro text-text-muted">
                  <?= !empty($proj['created_at']) ? date('M Y', strtotime($proj['created_at'])) : 'Ongoing' ?>
                </span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface group-hover:text-primary transition-colors font-semibold">
                  <a href="<?= htmlspecialchars($detailUrl) ?>" class="block">
                    <?= htmlspecialchars($proj['title']) ?>
                  </a>
                </h3>
              </div>
              
              <!-- Content Column -->
              <div class="flex flex-col gap-space-md flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="badge badge-neutral"><?= htmlspecialchars($proj['category'] ?: 'Engineering') ?></span>
                </div>
                
                <p class="font-body-md text-body-md text-on-surface-variant max-w-3xl leading-relaxed">
                  <?= htmlspecialchars($descSnippet) ?>...
                </p>
                
                <div>
                  <a href="<?= htmlspecialchars($detailUrl) ?>" class="font-label-code text-label-code text-primary hover:text-secondary flex items-center gap-1 transition-colors w-max">
                    <span>Inspect Architecture</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Dynamic Filter and Search Script -->
  <script>
    (function() {
      var searchInput = document.getElementById('project-search');
      var filterBtns = document.querySelectorAll('.filter-pill');
      var items = document.querySelectorAll('.project-item');
      var countEl = document.getElementById('project-count');
      var activeCategory = 'all';

      function applyFilters() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var visibleCount = 0;

        items.forEach(function(item) {
          var itemCat = item.getAttribute('data-category') || '';
          var itemTitle = item.getAttribute('data-title') || '';
          var itemText = item.textContent.toLowerCase();

          var matchCat = (activeCategory === 'all') || itemCat.indexOf(activeCategory) !== -1;
          var matchQuery = !query || itemTitle.indexOf(query) !== -1 || itemText.indexOf(query) !== -1;

          if (matchCat && matchQuery) {
            item.style.display = '';
            visibleCount++;
          } else {
            item.style.display = 'none';
          }
        });

        if (countEl) {
          countEl.textContent = 'Showing ' + visibleCount + ' Entries';
        }
      }

      filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          filterBtns.forEach(function(b) {
            b.classList.remove('active');
          });
          btn.classList.add('active');
          activeCategory = btn.getAttribute('data-category') || 'all';
          applyFilters();
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
      }

      // Keyboard shortcut ⌘K or Ctrl+K to focus search
      window.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
          if (searchInput) {
            e.preventDefault();
            searchInput.focus();
          }
        }
      });
    })();
  </script>
</body>
</html>
