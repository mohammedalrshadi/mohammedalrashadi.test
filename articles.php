<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'writing';
$pageTitle = 'Writing';
$pageDescription = 'Essays, post-mortems, and technical investigations exploring distributed systems, database internals, and low-level concurrency.';
$canonicalUrl = 'https://mohammedalrashadi.com/articles.php';

// Database query with fallback
$articles = [];
$totalCount = 0;

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        require_once __DIR__ . '/src/autoload.php';
        
        $pdo = getDB();
        $postRepo = new \Domain\Content\PostRepository($pdo);
        $articles = $postRepo->getPublishedBlogs();

        $categoryRepo = new \Domain\Content\CategoryRepository($pdo);
        $categories = $categoryRepo->getNamesByType('blog');
    } catch (Exception $e) {
        $articles = [];
        $categories = [];
    }}

$totalCount = count($articles);
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
      
      <!-- Content Canvas Container (Catalog Container ≈ 1320px) -->
      <div class="container-catalog pb-space-2xl">
        
        <!-- PAGE HERO & EDITORIAL INTRO -->
        <section class="pt-space-xl pb-space-lg flex flex-col gap-space-md">
          <div class="flex items-center gap-space-xs text-primary font-label-micro uppercase tracking-widest font-semibold">
            <span>Technical Discourse &amp; Engineering Essays</span>
          </div>

          <div class="flex flex-col gap-space-xs max-w-4xl">
            <h1 class="font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface tracking-tight font-bold">
              Writing
            </h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl leading-relaxed">
              Essays, post-mortems, and technical investigations exploring distributed systems resilience, storage engine internals, and low-level concurrency.
            </p>
          </div>

          <!-- Meta Summary Bar -->
          <div class="mt-space-sm flex flex-wrap items-center justify-between gap-space-md py-space-sm px-space-md bg-surface-container-low rounded-lg border border-outline-variant/10">
            <div class="flex flex-wrap items-center gap-x-space-md gap-y-space-xs font-label-code text-label-code text-on-surface-variant">
              <span class="flex items-center gap-space-xs text-on-surface font-medium">
                <span class="material-symbols-outlined text-[1.125rem] text-primary">menu_book</span>
                <?= (int)$totalCount ?> Published Essays
              </span>
              <span class="text-outline">•</span>
              <span>Systems &amp; Architecture</span>
            </div>
          </div>
        </section>

        <!-- SEARCH & FILTER BAR -->
        <section class="mb-space-xl flex flex-col gap-space-md">
          <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md">
            
            <!-- Search Input -->
            <div class="relative flex-1">
              <label for="essay-search-input" class="sr-only">Search essays</label>
              <span class="material-symbols-outlined absolute left-space-md top-1/2 -translate-y-1/2 text-outline pointer-events-none text-[1.25rem]">search</span>
              <input class="input-search" id="essay-search-input" placeholder="Search essays by title, topic, or concept..." type="text"/>
              <div class="absolute right-space-sm top-1/2 -translate-y-1/2 flex items-center gap-space-xs">
                <kbd class="font-label-code text-label-micro bg-surface-container-high text-on-surface-variant px-space-xs py-[0.125rem] rounded">⌘K</kbd>
              </div>
            </div>

            <!-- Category Filter Pills with Edge-Fade -->
            <div class="filter-scroll-wrapper">
              <div class="filter-scroll-container text-nowrap" id="filter-pills">
                <button class="filter-pill active" data-filter="all" type="button">
                  All (<?= (int)$totalCount ?>)
                </button>
                <?php foreach ($categories as $cat): ?>
                  <button class="filter-pill" data-filter="<?= htmlspecialchars(strtolower($cat)) ?>" type="button">
                    <?= htmlspecialchars($cat) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </section>

        <!-- ARTICLES GRID -->
        <?php if (empty($articles)): ?>
          <div class="py-space-2xl text-center flex flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 p-space-xl my-space-lg mb-space-2xl">
            <span class="material-symbols-outlined text-[2.5rem] text-outline">description</span>
            <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Writing Yet</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">Technical essays, systems research notes, and architecture breakdowns will appear here once published.</p>
          </div>
        <?php else: ?>
        <section class="flex flex-col border-t border-outline-variant/20 mb-space-2xl" id="articles-grid">
          <?php foreach ($articles as $art): 
            $cleanContent = strip_tags($art['content']);
            $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', $cleanContent)), 0, 200, 'UTF-8');
            $words = str_word_count($cleanContent);
            $readTime = max(1, (int) ceil($words / 200));
            $dateStr = !empty($art['created_at']) ? date('M d, Y', strtotime($art['created_at'])) : 'RECENT';
            $catLower = strtolower($art['category']);
            $detailUrl = !empty($art['slug']) ? '/articles/' . urlencode($art['slug']) : '/post.php?id=' . (int)$art['id'];
          ?>
            <article class="article-card flex flex-col md:flex-row gap-space-md py-space-xl border-b border-outline-variant/20 transition-colors hover:bg-surface-container-lowest group"
                     data-category="<?= htmlspecialchars($catLower) ?>"
                     data-title="<?= htmlspecialchars(strtolower($art['title'])) ?>">
              
              <!-- Meta Column -->
              <div class="flex flex-col gap-1 md:w-1/4 shrink-0 md:pt-1">
                <span class="font-label-code text-label-micro text-text-muted uppercase tracking-widest">
                  <?= $dateStr ?>
                </span>
                <span class="font-label-code text-label-micro text-primary">
                  <?= htmlspecialchars($art['category'] ?: 'ESSAY') ?>
                </span>
              </div>
              
              <!-- Content Column -->
              <div class="flex flex-col gap-space-sm flex-1">
                <h2 class="font-headline-md text-headline-md text-on-surface group-hover:text-primary transition-colors leading-snug font-semibold">
                  <a href="<?= htmlspecialchars($detailUrl) ?>" class="block">
                    <?= htmlspecialchars($art['title']) ?>
                  </a>
                </h2>

                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed max-w-3xl">
                  <?= htmlspecialchars($snippet) ?>...
                </p>

                <div class="mt-2 flex items-center justify-between max-w-3xl">
                  <a class="inline-flex items-center gap-space-xs font-label-code text-label-code text-primary hover:text-secondary transition-colors" href="<?= htmlspecialchars($detailUrl) ?>">
                    <span>Read Essay</span>
                    <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
                  </a>
                  <span class="font-label-code text-label-micro text-outline"><?= $readTime ?> MIN READ</span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
          <div id="no-articles-found" class="empty-state py-space-2xl text-center flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 my-space-lg" style="display: none;">
            <span class="material-symbols-outlined text-[2.5rem] text-outline">search_off</span>
            <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Matching Writing</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">No articles match your current search or category filter criteria.</p>
          </div>
        </section>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Search & Category Filter Script -->
  <script>
    (function() {
      var filterButtons = document.querySelectorAll('.filter-pill');
      var searchInput = document.getElementById('essay-search-input');
      var cards = document.querySelectorAll('.article-card');
      var currentFilter = 'all';

      function updateArticles() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var visibleCount = 0;

        cards.forEach(function(card) {
          var cat = card.getAttribute('data-category') || '';
          var title = card.getAttribute('data-title') || '';
          var body = card.textContent.toLowerCase();

          var matchesCat = (currentFilter === 'all') || (cat.indexOf(currentFilter) !== -1);
          var matchesQuery = !query || (title.indexOf(query) !== -1) || (body.indexOf(query) !== -1);

          if (matchesCat && matchesQuery) {
            card.style.display = '';
            visibleCount++;
          } else {
            card.style.display = 'none';
          }
        });
        
        var noResults = document.getElementById('no-articles-found');
        if (noResults) {
          noResults.style.display = (visibleCount === 0 && cards.length > 0) ? 'flex' : 'none';
        }
      }

      filterButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          filterButtons.forEach(function(b) {
            b.classList.remove('active');
          });
          btn.classList.add('active');
          currentFilter = btn.getAttribute('data-filter') || 'all';
          updateArticles();
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', updateArticles);
      }

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
