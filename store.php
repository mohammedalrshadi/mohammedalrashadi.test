<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
// ============================================================
// STORE — PUBLIC CATALOG
// /store or /store.php
//
// Technical digital product discovery and catalog for Mohammed Alrashadi platform.
// Exposes ONLY published products (status = 'published').
// ============================================================

$currentPage = 'store';
$pageTitle = 'Store';
$pageDescription = 'Curated engineering templates, developer cheat sheets, Notion workspaces, and digital tools by Mohammed Alrashadi.';
$canonicalUrl = 'https://mohammedalrashadi.com/store';

require_once __DIR__ . '/includes/image_helper.php';

$products = [];
$categories = [];
$totalCount = 0;

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        $pdo = getDB();

        // Query published products only
        $stmt = $pdo->query(
            "SELECT id, title, slug, short_description, category, product_type,
                    platform, price_display, currency, thumbnail, external_url, download_path,
                    live_demo_url, live_demo_source, live_demo_path,
                    featured, sort_order, created_at
             FROM products
             WHERE status = 'published'
             ORDER BY featured DESC, sort_order ASC, created_at DESC"
        );
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch distinct categories of published products
        $catStmt = $pdo->query(
            "SELECT DISTINCT category
             FROM products
             WHERE status = 'published' AND category IS NOT NULL AND category <> ''
             ORDER BY category ASC"
        );
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (Throwable $e) {
        error_log('[store.php] DB query error: ' . $e->getMessage());
        $products = [];
        $categories = [];
    }
}

$totalCount = count($products);
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

      <!-- Top Section: Header & Discovery -->
      <section class="container-catalog reveal-section pt-space-xl pb-space-lg">
        <div class="flex flex-col gap-space-sm">
          <div class="flex items-center gap-space-xs text-primary font-label-micro uppercase tracking-widest font-semibold">
            <span class="material-symbols-outlined text-[1rem]">shopping_bag</span>
            <span>Digital Catalog &amp; Resources</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface tracking-tight font-bold">Store</h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl leading-relaxed">
            Curated digital resources, downloadable technical guides, Notion templates, and recommended engineering tools.
          </p>
          <p class="font-body-md text-on-surface-variant max-w-3xl leading-relaxed">
            A curated collection of digital products and engineering boilerplates. Some resources are available as direct free downloads, while selected products link to verified external marketplaces (no internal checkout or payment processing is used on this site).
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
            <label for="store-search" class="sr-only">Search catalog</label>
            <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-outline text-[1.125rem] pointer-events-none">search</span>
            <input class="input-search" id="store-search" placeholder="Search catalog..." type="text"/>
            <span class="material-symbols-outlined absolute right-space-sm top-1/2 -translate-y-1/2 text-outline text-[1rem] cursor-pointer hidden" id="clear-search">close</span>
          </div>
        </div>
      </section>

      <!-- Products Grid Section -->
      <section class="container-catalog reveal-section pb-space-2xl">
        <div class="flex items-center justify-between pb-space-md border-b border-outline-variant/10 mb-space-lg">
          <h2 class="font-headline-md text-headline-md text-on-surface font-semibold">Available Products</h2>
          <span class="font-label-code text-label-code text-outline" id="store-count">Showing <?= count($products) ?> Entries</span>
        </div>

        <?php if (empty($products)): ?>
          <!-- Empty State -->
          <div class="py-space-2xl text-center flex flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 p-space-xl my-space-lg">
            <span class="material-symbols-outlined text-[2.5rem] text-outline">storefront</span>
            <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Products Published Yet</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">Digital templates, developer tools, and downloadable guides will appear here soon.</p>
          </div>
        <?php else: ?>
          <!-- Products Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-space-lg" id="product-grid">
            <?php foreach ($products as $prod):
              $catLower = strtolower($prod['category'] ?? '');

              $isUploadedDemo = !empty($prod['live_demo_source']) && $prod['live_demo_source'] === 'uploaded' && !empty($prod['live_demo_path']);
              $hasLiveDemo = !empty($prod['live_demo_url']) || $isUploadedDemo;
              $hasDownload = !empty($prod['download_path']);
              $hasExternal = !empty($prod['external_url']);


              $isFree = $hasDownload;
              $thumb = !empty($prod['thumbnail']) ? $prod['thumbnail'] : '';
              $detailUrl = '/store/' . rawurlencode($prod['slug']);
              $price = !empty($prod['price_display']) ? $prod['price_display'] : ($isFree ? 'Free' : '');
            ?>
              <div class="product-card flex flex-col bg-surface-container-low rounded-xl p-space-lg shadow-md hover:shadow-xl transition-all duration-200 group border border-outline-variant/10"
                   data-category="<?= htmlspecialchars($catLower) ?>"
                   data-title="<?= htmlspecialchars(strtolower($prod['title'])) ?>"
                   data-description="<?= htmlspecialchars(strtolower($prod['short_description'] ?? '')) ?>">

                <!-- Thumbnail / Card Image -->
                <a href="<?= htmlspecialchars($detailUrl) ?>" class="h-48 rounded-lg bg-surface-container-lowest overflow-hidden relative mb-space-md block border border-outline-variant/5">
                  <?php if (!empty($thumb)): ?>
                    <?= responsiveImage($thumb, $prod['title'], [
                        'class' => 'w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300',
                        'sizes' => '(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 360px'
                    ]) ?>
                  <?php else: ?>
                    <div class="w-full h-full flex flex-col items-center justify-center text-outline gap-2 bg-surface-container">
                      <span class="material-symbols-outlined text-[2.5rem] text-primary/60"><?= $isFree ? 'download' : 'storefront' ?></span>
                      <span class="font-label-code text-label-micro uppercase tracking-wider"><?= htmlspecialchars($prod['category'] ?: 'Product') ?></span>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($prod['featured'])): ?>
                    <div class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded bg-primary text-on-primary font-label-micro font-bold text-[10px] tracking-wider uppercase shadow-md flex items-center gap-1">
                      <span class="material-symbols-outlined text-[12px]">star</span>
                      <span>Featured</span>
                    </div>
                  <?php endif; ?>
                </a>

                <!-- Content -->
                <div class="flex flex-col flex-1 justify-between gap-space-md">
                  <div>
                    <!-- Category & Type Meta -->
                    <div class="flex items-center justify-between gap-2 pb-1.5 font-label-micro text-label-micro">
                      <span class="text-primary font-semibold uppercase tracking-wider">
                        <?= htmlspecialchars($prod['category'] ?: 'Resource') ?>
                      </span>
                      <?php if ($hasDownload): ?>
                        <span class="text-emerald-400 flex items-center gap-1 font-mono text-[11px]">
                          <span class="material-symbols-outlined text-[13px]">file_download</span> Free Download
                        </span>
                      <?php elseif ($hasLiveDemo): ?>
                        <span class="text-amber-400 flex items-center gap-1 font-mono text-[11px]">
                          <span class="material-symbols-outlined text-[13px]">desktop_windows</span> Live Demo
                        </span>
                      <?php elseif ($hasExternal): ?>
                        <span class="text-on-surface-variant flex items-center gap-1 font-mono text-[11px]">
                          <span class="material-symbols-outlined text-[13px]">open_in_new</span> <?= htmlspecialchars($prod['platform'] ?: 'External') ?>
                        </span>
                      <?php endif; ?>
                    </div>

                    <!-- Title -->
                    <h3 class="font-headline-sm text-headline-sm text-on-surface group-hover:text-primary transition-colors font-semibold leading-snug">
                      <a href="<?= htmlspecialchars($detailUrl) ?>">
                        <?= htmlspecialchars($prod['title']) ?>
                      </a>
                    </h3>

                    <!-- Short Description -->
                    <?php if (!empty($prod['short_description'])): ?>
                      <p class="font-body-sm text-body-sm text-on-surface-variant mt-space-xs line-clamp-2 leading-relaxed">
                        <?= htmlspecialchars($prod['short_description']) ?>
                      </p>
                    <?php endif; ?>
                  </div>

                  <!-- Footer Bar: Price & CTA -->
                  <div class="pt-space-sm border-t border-outline-variant/10 flex items-center justify-between gap-2">
                    <div class="font-label-code font-bold text-sm <?= $isFree ? 'text-emerald-400' : 'text-on-surface' ?>">
                      <?= htmlspecialchars($price) ?>
                    </div>

                    <a href="<?= htmlspecialchars($detailUrl) ?>"
                       class="btn <?= $isFree ? 'btn-download' : 'btn-external' ?> !min-h-0 py-1.5 px-space-md font-label-code text-xs">
                      <span><?= $isFree ? 'Free Download' : 'External Store' ?></span>
                      <span class="material-symbols-outlined text-[14px]"><?= $isFree ? 'download' : 'open_in_new' ?></span>
                    </a>
                  </div>
                </div>

              </div>
            <?php endforeach; ?>
          </div>

          <!-- No Search Results Found (Hidden by default) -->
          <div id="no-search-results" class="py-space-2xl text-center hidden flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 p-space-xl my-space-lg">
            <span class="material-symbols-outlined text-[2.5rem] text-outline">search_off</span>
            <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Matching Products</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">No products match your current search or category filter criteria.</p>
          </div>
        <?php endif; ?>

      </section>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Interactive Client-side Filter & Search Script -->
  <script>
    (function() {
      const filterBtns = document.querySelectorAll('.filter-pill');
      const cards = document.querySelectorAll('.product-card');
      const searchInput = document.getElementById('store-search');
      const clearSearchBtn = document.getElementById('clear-search');
      const countEl = document.getElementById('store-count');
      const noResultsEl = document.getElementById('no-search-results');

      let currentCategory = 'all';
      let searchQuery = '';

      function applyFilters() {
        let visibleCount = 0;

        cards.forEach(card => {
          const cardCat = card.getAttribute('data-category') || '';
          const cardTitle = card.getAttribute('data-title') || '';
          const cardDesc = card.getAttribute('data-description') || '';

          const matchesCat = (currentCategory === 'all') || (cardCat === currentCategory);
          const matchesSearch = !searchQuery || cardTitle.includes(searchQuery) || cardDesc.includes(searchQuery);

          if (matchesCat && matchesSearch) {
            card.style.display = 'flex';
            visibleCount++;
          } else {
            card.style.display = 'none';
          }
        });

        if (countEl) {
          countEl.textContent = `Showing ${visibleCount} of ${cards.length} Entries`;
        }

        if (noResultsEl) {
          noResultsEl.style.display = (visibleCount === 0 && cards.length > 0) ? 'flex' : 'none';
        }
      }

      filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          filterBtns.forEach(b => b.classList.remove('active'));
          btn.classList.add('active');

          currentCategory = btn.getAttribute('data-category') || 'all';
          applyFilters();
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', (e) => {
          searchQuery = e.target.value.trim().toLowerCase();
          if (clearSearchBtn) {
            clearSearchBtn.style.display = searchQuery ? 'block' : 'none';
          }
          applyFilters();
        });
      }

      if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
          if (searchInput) {
            searchInput.value = '';
            searchQuery = '';
            clearSearchBtn.style.display = 'none';
            applyFilters();
            searchInput.focus();
          }
        });
      }
    })();
  </script>

</body>
</html>

