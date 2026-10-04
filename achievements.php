<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
// ============================================================
// ACHIEVEMENTS — PUBLIC
// /achievements.php
// ============================================================

$currentPage = 'achievements';
$pageTitle = 'Achievements';
$pageDescription = 'Certificates, awards, and formal recognitions earned by Mohammed Alrashadi.';
$canonicalUrl = 'https://mohammedalrashadi.com/achievements';

require_once __DIR__ . '/includes/image_helper.php';

$achievements = [];
$categories = [];
$galleries = [];
$totalCount = 0;

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        $pdo = getDB();

        // DB-001 support: check if deleted_at exists before querying it
        $deletedAtCheck = '';
        try {
            $pdo->query("SELECT deleted_at FROM achievements LIMIT 1");
            $deletedAtCheck = " AND deleted_at IS NULL";
        } catch (Throwable $e) { error_log('[achievements.php:31] non-fatal, fallback used: ' . get_class($e)); }

        // Query published achievements
        $stmt = $pdo->query(
            "SELECT id, title, slug, organization, date_awarded, category, description, image_url, url, created_at
             FROM achievements
             WHERE status = 'published'{$deletedAtCheck}
             ORDER BY date_awarded DESC, created_at DESC"
        );
        $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch distinct categories
        $catStmt = $pdo->query(
            "SELECT DISTINCT category
             FROM achievements
             WHERE status = 'published' AND category IS NOT NULL AND category <> ''{$deletedAtCheck}
             ORDER BY category ASC"
        );
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Fetch galleries (DB-006: proper JOIN to avoid fetching orphaned/hidden images)
        $galDeletedAtCheck = str_replace('deleted_at', 'a.deleted_at', $deletedAtCheck);
        $galStmt = $pdo->query(
            "SELECT ag.achievement_id, ag.image_url, ag.alt_text
             FROM achievement_gallery ag
             JOIN achievements a ON ag.achievement_id = a.id
             WHERE a.status = 'published'{$galDeletedAtCheck}
             ORDER BY ag.achievement_id, ag.sort_order ASC, ag.id ASC"
        );
        $rawGalleries = $galStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rawGalleries as $g) {
            $galleries[$g['achievement_id']][] = [
                'url' => $g['image_url'],
                'alt' => $g['alt_text'] ?: ''
            ];
        }
    } catch (Throwable $e) {
        error_log('[achievements.php] DB query error: ' . $e->getMessage());
    }
}

$totalCount = count($achievements);
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

      <!-- Top Section -->
      <section class="container-catalog reveal-section pt-space-xl pb-space-lg">
        <div class="flex flex-col gap-space-sm">
          <div class="flex items-center gap-space-xs text-primary font-label-micro uppercase tracking-widest font-semibold">
            <span class="material-symbols-outlined text-[1rem]">workspace_premium</span>
            <span>Certificates & Awards</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight font-bold">Achievements</h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-3xl leading-relaxed">
            Formal recognitions, academic certificates, and industry awards.
          </p>
        </div>

        <!-- Filter & Search Controls -->
        <div class="mt-space-lg flex flex-col md:flex-row items-stretch md:items-center justify-between gap-space-md">
          <!-- Category Pills -->
          <div class="filter-scroll-wrapper flex-1">
            <div class="filter-scroll-container flex flex-wrap gap-2" id="category-filters">
              <button class="filter-pill active" data-category="all" type="button">
                All (<span id="allCount"><?= (int)$totalCount ?></span>)
              </button>
              <?php foreach ($categories as $cat): ?>
                <button class="filter-pill" data-category="<?= htmlspecialchars(strtolower($cat)) ?>" type="button">
                  <?= htmlspecialchars($cat) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Search Input -->
          <div class="relative w-full md:w-72 flex-shrink-0 flex gap-2">
            <div class="relative flex-1">
              <label for="achieve-search" class="sr-only">Search achievements</label>
              <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-outline text-[1.125rem] pointer-events-none">search</span>
              <input class="input-search w-full pl-10" id="achieve-search" placeholder="Search achievements..." type="text"/>
            </div>
            <button id="clear-filters-btn" class="hidden btn btn-secondary px-3" title="Clear Filters" type="button">
                <span class="material-symbols-outlined">filter_alt_off</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Achievements Grid Section -->
      <section class="container-catalog reveal-section pb-space-2xl">
        <div class="flex items-center justify-between pb-space-md border-b border-outline-variant/10 mb-space-lg">
          <h2 class="font-headline-md text-headline-md text-on-surface font-semibold">Accomplishments</h2>
          <span class="font-label-code text-label-code text-outline" id="achieve-count"><?= $totalCount === 1 ? 'Showing 1 Entry' : 'Showing ' . $totalCount . ' Entries' ?></span>
        </div>

        <div id="achieve-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-space-lg <?php echo empty($achievements) ? 'hidden' : ''; ?>">
          <?php foreach ($achievements as $achieve):
            $catLower = strtolower($achieve['category'] ?? '');
            $thumb = !empty($achieve['image_url']) ? $achieve['image_url'] : '';
            $detailUrl = !empty($achieve['url']) ? $achieve['url'] : '';
            $dateFmt = !empty($achieve['date_awarded']) ? date('M Y', strtotime($achieve['date_awarded'])) : '';
            
            // Build gallery array for this achievement
            $itemGallery = [];
            $galleryUrls = [];
            
            if (!empty($galleries[$achieve['id']])) {
                foreach ($galleries[$achieve['id']] as $gImg) {
                    $itemGallery[] = $gImg;
                    $galleryUrls[] = $gImg['url'];
                }
            }
            
            if ($thumb && !in_array($thumb, $galleryUrls)) {
                // Prepend thumb to the gallery array if it's not already there
                array_unshift($itemGallery, ['url' => $thumb, 'alt' => $achieve['title']]);
            }
            $achieveData = $achieve;
            $achieveData['gallery'] = $itemGallery;
            $achievementJson = htmlspecialchars(json_encode($achieveData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
          ?>
            <a href="/achievement/<?= (int)$achieve['id'] ?>" class="achievement-card product-card flex flex-col bg-surface-container-low rounded-xl p-space-lg shadow-md hover:shadow-xl transition-all duration-200 group border border-outline-variant/10 h-full"
                 data-category="<?= htmlspecialchars($catLower) ?>"
                 data-title="<?= htmlspecialchars(strtolower($achieve['title'])) ?>"
                 data-description="<?= htmlspecialchars(strtolower($achieve['description'] ?? '')) ?>"
                 data-achievement='<?= $achievementJson ?>'>

              <!-- Image -->
              <div class="achv-thumb-box h-48 rounded-lg bg-surface-container-lowest overflow-hidden relative mb-space-md flex border border-outline-variant/5">
                <?php if (!empty($thumb)): ?>
                  <img decoding="async" src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($achieve['title']) ?>" 
                       class="w-full h-full object-contain cursor-pointer" loading="lazy"
                       onerror="this.onerror=null; this.src='/assets/images/placeholder.png'; this.classList.add('opacity-50');"
                       tabindex="0" role="button" aria-label="View full image">
                  <?php if(count($itemGallery) > 1): ?>
                    <span class="absolute top-2 right-2 bg-black/60 text-white text-xs px-2 py-1 rounded-md">
                        <span class="material-symbols-outlined text-[0.875rem]" style="vertical-align:middle" aria-hidden="true">photo_library</span> <?= count($itemGallery) ?>
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="w-full h-full flex flex-col items-center justify-center text-outline gap-2 bg-surface-container">
                    <span class="material-symbols-outlined text-[2.5rem] text-primary/60">workspace_premium</span>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Content -->
              <div class="flex flex-col flex-1 justify-between gap-space-sm">
                <div>
                  <div class="flex items-center justify-between gap-2 pb-1.5 font-label-micro text-label-micro">
                    <span class="text-primary font-semibold uppercase tracking-wider">
                      <?= htmlspecialchars($achieve['category'] ?: 'Achievement') ?>
                    </span>
                  </div>

                  <div class="block group/link">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold leading-snug line-clamp-2 group-hover/link:text-primary transition-colors" title="<?= htmlspecialchars($achieve['title']) ?>">
                      <?= htmlspecialchars($achieve['title']) ?>
                    </h3>
                  </div>
                  
                  <?php if (!empty($achieve['organization'])): ?>
                    <p class="font-body-sm text-body-sm text-on-surface-variant font-medium mt-1 truncate" title="<?= htmlspecialchars($achieve['organization']) ?>">
                      <?= htmlspecialchars($achieve['organization']) ?>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($achieve['description'])): ?>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-space-xs line-clamp-3 leading-relaxed">
                      <?= htmlspecialchars($achieve['description']) ?>
                    </p>
                  <?php endif; ?>
                </div>

                <!-- Footer details pinned to bottom -->
                <div class="flex items-center justify-between mt-space-md pt-space-sm border-t border-outline-variant/10">
                  <span class="text-on-surface-variant flex items-center gap-1 font-mono text-xs">
                    <?php if ($dateFmt): ?>
                      <span class="material-symbols-outlined text-[14px]">calendar_today</span> <?= $dateFmt ?>
                    <?php else: ?>
                      <span>&nbsp;</span>
                    <?php endif; ?>
                  </span>
                  <span class="text-primary hover:underline text-sm font-medium flex items-center gap-1 group-hover:text-secondary transition-colors">
                    Read More <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                  </span>
                </div>
              </div>

            </a>
          <?php endforeach; ?>
        </div>

        <div id="no-results-state" class="py-space-2xl text-center hidden flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 p-space-xl my-space-lg <?php echo empty($achievements) ? '!flex' : ''; ?>">
          <span class="material-symbols-outlined text-[2.5rem] text-outline">search_off</span>
          <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Achievements Found</p>
          <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">No achievements match your filters, or none have been published yet.</p>
        </div>

      </section>

    </div>
  </main>
  
  <!-- ACHIEVEMENTS LIGHTBOX -->
  <div id="achv-lightbox" class="achv-lightbox hidden" role="dialog" aria-modal="true" aria-label="Image Gallery">
      <div class="achv-lightbox-backdrop"></div>
      
      <button id="achv-lightbox-close" class="achv-lightbox-btn achv-lightbox-close" aria-label="Close">
          <span class="material-symbols-outlined">close</span>
      </button>
      
      <div class="achv-lightbox-content">
          <button id="achv-lightbox-prev" class="achv-lightbox-btn achv-lightbox-nav prev" aria-label="Previous image">
              <span class="material-symbols-outlined">chevron_left</span>
          </button>
          
          <div class="achv-lightbox-image-container">
              <img id="achv-lightbox-img" src="" alt="Full achievement image" />
              <div id="achv-lightbox-counter" class="achv-lightbox-counter">1 / 1</div>
          </div>
          
          <button id="achv-lightbox-next" class="achv-lightbox-btn achv-lightbox-nav next" aria-label="Next image">
              <span class="material-symbols-outlined">chevron_right</span>
          </button>
      </div>
      
      <div class="achv-lightbox-caption">
          <h3 id="achv-lightbox-title"></h3>
          <p id="achv-lightbox-meta"></p>
      </div>
  </div>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script>
    (function() {
      // Filtering and Search
      const filterBtns = document.querySelectorAll('.filter-pill');
      const cards = document.querySelectorAll('.product-card');
      const searchInput = document.getElementById('achieve-search');
      const clearSearchBtn = document.getElementById('clear-filters-btn');
      const countEl = document.getElementById('achieve-count');
      const noResultsEl = document.getElementById('no-results-state');
      const gridEl = document.getElementById('achieve-grid');

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
          countEl.textContent = visibleCount === 1 ? 'Showing 1 Entry' : `Showing ${visibleCount} Entries`;
        }
        
        if (searchQuery || currentCategory !== 'all') {
            clearSearchBtn.classList.remove('hidden');
        } else {
            clearSearchBtn.classList.add('hidden');
        }

        if (visibleCount === 0) {
            noResultsEl.classList.remove('hidden');
            noResultsEl.classList.add('!flex');
            gridEl.classList.add('hidden');
        } else {
            noResultsEl.classList.add('hidden');
            noResultsEl.classList.remove('!flex');
            gridEl.classList.remove('hidden');
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
          applyFilters();
        });
      }

      if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
          if (searchInput) searchInput.value = '';
          searchQuery = '';
          currentCategory = 'all';
          filterBtns.forEach(b => b.classList.remove('active'));
          document.querySelector('.filter-pill[data-category="all"]').classList.add('active');
          applyFilters();
        });
      }
    })();

    // Lightbox Functionality
    let achvGalleryImages = [];
    let achvCurrentIndex = 0;
    let achvLastFocused = null;
    
    const lightbox = document.getElementById('achv-lightbox');
    const lightboxImg = document.getElementById('achv-lightbox-img');
    const lightboxTitle = document.getElementById('achv-lightbox-title');
    const lightboxMeta = document.getElementById('achv-lightbox-meta');
    const counter = document.getElementById('achv-lightbox-counter');
    const prevBtn = document.getElementById('achv-lightbox-prev');
    const nextBtn = document.getElementById('achv-lightbox-next');
    
    window.openLightbox = function(data) {
        if (!data) return;
        let images = [];
        if (data.gallery && Array.isArray(data.gallery)) {
            images = data.gallery;
        } else if (data.image_url) {
            images = [{url: data.image_url, alt: data.title}];
        }
        
        if (images.length === 0) return;
        
        achvGalleryImages = images;
        achvCurrentIndex = 0;
        achvLastFocused = document.activeElement;
        
        lightboxTitle.textContent = data.title || '';
        let metaParts = [];
        if (data.organization) metaParts.push(data.organization);
        if (data.date_awarded) {
            const d = new Date(data.date_awarded);
            if (!isNaN(d)) {
                metaParts.push(d.toLocaleDateString('en-US', { year: 'numeric', month: 'short' }));
            }
        }
        lightboxMeta.textContent = metaParts.join(' • ');
        
        achvUpdateLightboxView();
        
        lightbox.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // lock scroll
        
        // Trap focus
        lightbox.focus();
    };

    document.addEventListener('click', (e) => {
        const card = e.target.closest('.achievement-card');
        if (!card) return;
        
        const thumbBox = e.target.closest('.achv-thumb-box');
        if (thumbBox) {
            e.preventDefault();
            e.stopPropagation();
            try {
                const data = JSON.parse(card.dataset.achievement);
                openLightbox(data);
            } catch (err) {
                console.error('Failed to parse achievement JSON', err);
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            if (document.activeElement && document.activeElement.tagName === 'IMG' && document.activeElement.closest('.achv-thumb-box')) {
                const card = document.activeElement.closest('.achievement-card');
                if (card) {
                    e.preventDefault();
                    e.stopPropagation();
                    try {
                        const data = JSON.parse(card.dataset.achievement);
                        openLightbox(data);
                    } catch (err) {
                        console.error('Failed to parse achievement JSON', err);
                    }
                }
            }
        }
    });
    
    function achvUpdateLightboxView() {
        const img = achvGalleryImages[achvCurrentIndex];
        lightboxImg.src = img.url;
        lightboxImg.alt = img.alt || 'Achievement image';
        
        if (achvGalleryImages.length > 1) {
            counter.textContent = `${achvCurrentIndex + 1} / ${achvGalleryImages.length}`;
            counter.style.display = 'block';
            prevBtn.style.display = 'flex';
            nextBtn.style.display = 'flex';
        } else {
            counter.style.display = 'none';
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        }
    }
    
    function achvCloseLightbox() {
        lightbox.classList.add('hidden');
        document.body.style.overflow = '';
        if (achvLastFocused) achvLastFocused.focus();
    }
    
    function achvPrevImage(e) {
        if (e) e.stopPropagation();
        if (achvGalleryImages.length <= 1) return;
        achvCurrentIndex = (achvCurrentIndex - 1 + achvGalleryImages.length) % achvGalleryImages.length;
        achvUpdateLightboxView();
    }
    
    function achvNextImage(e) {
        if (e) e.stopPropagation();
        if (achvGalleryImages.length <= 1) return;
        achvCurrentIndex = (achvCurrentIndex + 1) % achvGalleryImages.length;
        achvUpdateLightboxView();
    }
    
    // Event listeners
    document.getElementById('achv-lightbox-close').addEventListener('click', achvCloseLightbox);
    document.querySelector('.achv-lightbox-backdrop').addEventListener('click', achvCloseLightbox);
    prevBtn.addEventListener('click', achvPrevImage);
    nextBtn.addEventListener('click', achvNextImage);
    
    document.addEventListener('keydown', (e) => {
        if (lightbox.classList.contains('hidden')) return;
        if (e.key === 'Escape') achvCloseLightbox();
        if (e.key === 'ArrowLeft') achvPrevImage();
        if (e.key === 'ArrowRight') achvNextImage();
    });
    
    // Swipe
    let touchStartX = 0;
    lightbox.addEventListener('touchstart', e => touchStartX = e.changedTouches[0].screenX);
    lightbox.addEventListener('touchend', e => {
        if (lightbox.classList.contains('hidden')) return;
        let touchEndX = e.changedTouches[0].screenX;
        if (touchEndX < touchStartX - 50) achvNextImage();
        if (touchEndX > touchStartX + 50) achvPrevImage();
    });
  </script>

</body>
</html>
