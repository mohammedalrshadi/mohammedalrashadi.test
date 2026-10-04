<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'gallery';
$pageTitle = 'Gallery';
$pageDescription = 'Visual Gallery — Architectural Artifacts & Topologies. A curated archive documenting system topologies, 3D geometric structures, and empirical benchmarks.';
$canonicalUrl = 'https://mohammedalrashadi.com/gallery.php';

require_once __DIR__ . '/includes/image_helper.php';

// Database query for project images joined with published projects
$artifacts = [];
$categories = [];

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        $pdo = getDB();

        $imgTable = 'achievement_images';
        $checkImgTbl = $pdo->query("SHOW TABLES LIKE 'project_images'");
        if ($checkImgTbl && $checkImgTbl->fetchColumn()) {
            $imgTable = 'project_images';
        }

        $stmt = $pdo->query(
            "SELECT 
                ai.id, 
                ai.image_url, 
                ai.created_at, 
                p.id AS post_id, 
                p.title AS post_title, 
                p.category AS post_category, 
                p.type AS post_type,
                p.content AS post_content
             FROM {$imgTable} ai
             JOIN posts p ON ai.post_id = p.id
             WHERE (p.type = 'project' OR p.type = 'achievement') AND p.status = 'published' AND p.deleted_at IS NULL
             ORDER BY ai.id DESC"
        );
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawRows as $row) {
            $cat = !empty($row['post_category']) ? $row['post_category'] : 'Systems';
            $catKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $cat));
            $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($row['post_content'] ?? ''))), 0, 150, 'UTF-8');
            $artifacts[] = [
                'id' => 'IMG-' . str_pad((string)$row['id'], 2, '0', STR_PAD_LEFT),
                'title' => $row['post_title'] ?: 'Engineering Schematic',
                'category' => $catKey ?: 'systems',
                'category_name' => $cat,
                'image' => $row['image_url'],
                'resolution' => 'Visual Asset',
                'description' => $snippet ? $snippet . '...' : 'Architectural visual asset for ' . ($row['post_title'] ?: 'Case Study') . '.',
                'tags' => array_values(array_filter([$cat, $row['post_type'] ?? null])),
                'related_url' => '/project.php?id=' . (int)$row['post_id'],
                'related_label' => 'Project: ' . ($row['post_title'] ?: 'Case Study'),
                'created_at' => $row['created_at'] ?? null
            ];

            if ($catKey && !isset($categories[$catKey])) {
                $categories[$catKey] = $cat;
            }
        }
    } catch (Exception $e) {
        $artifacts = [];
        $categories = [];
    }
}
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
          <div class="flex flex-wrap items-center gap-space-xs text-primary font-label-micro uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
            <span>Visual Archive &amp; Engineering Artifacts</span>
            <span class="text-outline-variant font-label-micro">•</span>
            <span class="text-on-surface-variant font-label-micro">Curated Repertory</span>
          </div>

          <div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md">
            <div class="max-w-3xl">
              <h1 class="font-display text-display text-on-surface tracking-tight">Visual Gallery</h1>
              <p class="font-body-lg text-body-lg text-on-surface-variant mt-space-xs leading-relaxed">
                A curated visual archive documenting system topologies, 3D geometric computing structures, empirical benchmarks, and low-level architectural artifacts across my engineering journey.
              </p>
            </div>

            <!-- Stats Badge Row -->
            <div class="flex flex-wrap items-center gap-space-xs bg-surface-container-low p-1.5 rounded-lg border border-outline-variant/10">
              <div class="flex items-center gap-1.5 px-space-xs py-1">
                <span class="w-2 h-2 rounded-full bg-primary"></span>
                <span class="font-label-code text-label-code text-on-surface font-semibold"><?= count($artifacts) ?></span>
                <span class="font-label-code text-label-micro text-on-surface-variant">Archived</span>
              </div>
              <span class="text-outline-variant text-xs">/</span>
              <div class="flex items-center gap-1.5 px-space-xs py-1">
                <span class="material-symbols-outlined text-sm text-secondary">verified</span>
                <span class="font-label-code text-label-micro text-on-surface-variant">UHD Vector &amp; 4K</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <?php if (empty($artifacts)): ?>
        <section class="container-catalog reveal-section pb-space-2xl">
          <div class="py-space-2xl text-center flex flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 p-space-xl my-space-lg">
            <span class="material-symbols-outlined text-[2.5rem] text-outline">photo_library</span>
            <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Visual Artifacts Archived Yet</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">System topologies, architecture diagrams, and benchmark schematics will appear here once visual assets are attached to published projects.</p>
          </div>
        </section>
      <?php else: ?>
      <!-- Category Filter Tabs & Search Controls -->
      <section class="container-catalog reveal-section pb-space-lg">
        <div class="bg-surface-container-low p-space-md rounded-xl flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md border border-outline-variant/10">
          <div class="filter-scroll-wrapper">
            <div class="filter-scroll-container" id="galleryCategoryFilters">
              <button class="filter-pill active" data-category="all" type="button">
                All (<?= count($artifacts) ?>)
              </button>
              <?php foreach ($categories as $catKey => $catLabel): ?>
                <button class="filter-pill" data-category="<?= htmlspecialchars($catKey) ?>" type="button">
                  <?= htmlspecialchars($catLabel) ?>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="flex items-center gap-space-xs">
            <div class="relative min-w-[210px] sm:min-w-[260px]">
              <label for="gallerySearch" class="sr-only">Search visual artifacts</label>
              <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[1.125rem] text-outline pointer-events-none">search</span>
              <input class="w-full pl-9 pr-3 py-1.5 rounded-lg bg-surface-container-lowest text-on-surface font-body-sm text-body-sm placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary transition-all border border-outline-variant/10" id="gallerySearch" placeholder="Search visual artifacts..." type="text"/>
            </div>
          </div>
        </div>
      </section>

      <!-- Featured Spotlight Hero Artifact -->
      <section class="container-catalog reveal-section mb-space-2xl">
        <div class="bg-surface-container-low rounded-xl p-space-md sm:p-space-lg shadow-xl relative overflow-hidden group border border-outline-variant/10">
          <div class="absolute top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-primary/40 to-transparent"></div>
          
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg items-center">
            <!-- Left Preview -->
            <div class="lg:col-span-8 relative rounded-lg overflow-hidden bg-surface-container-lowest cursor-pointer transition-transform duration-300 group" onclick="openLightbox(0)">
              <?= responsiveImage(
                  $artifacts[0]['image'],
                  $artifacts[0]['title'],
                  [
                      'class' => 'w-full aspect-[1.79] object-cover transition-transform duration-500 group-hover:scale-[1.01]',
                      'priority' => true,
                      'sizes' => '(max-width: 1024px) 100vw, 800px'
                  ]
              ) ?>
              <div class="absolute top-3 left-3 bg-surface-container-lowest/85 backdrop-blur-md px-2.5 py-1 rounded font-label-micro text-label-micro text-primary flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                <?= htmlspecialchars($artifacts[0]['id']) ?> — <?= htmlspecialchars($artifacts[0]['category_name']) ?>
              </div>
              <div class="absolute bottom-3 right-3 bg-surface-container-lowest/85 backdrop-blur-md px-2 py-1 rounded font-label-code text-label-micro text-on-surface-variant flex items-center gap-1">
                <span class="material-symbols-outlined text-xs text-primary">fullscreen</span>
                <?= htmlspecialchars($artifacts[0]['resolution']) ?>
              </div>
              <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                <div class="bg-surface-container-lowest/90 px-space-md py-space-xs rounded-full flex items-center gap-space-xs shadow-lg">
                  <span class="material-symbols-outlined text-primary text-sm">zoom_in</span>
                  <span class="font-label-code text-label-code text-on-surface">Inspect High-Res Geometric Render</span>
                </div>
              </div>
            </div>

            <!-- Right Dossier -->
            <div class="lg:col-span-4 flex flex-col justify-between h-full gap-space-md">
              <div>
                <div class="flex items-center justify-between gap-space-xs mb-space-xs">
                  <span class="font-label-micro text-label-micro text-primary uppercase tracking-wider font-semibold">Spotlight Selection</span>
                  <span class="font-label-code text-label-micro text-outline"><?= !empty($artifacts[0]['created_at']) ? date('M Y', strtotime($artifacts[0]['created_at'])) : 'Curated' ?></span>
                </div>
                <h2 class="font-headline-md text-headline-md text-on-surface tracking-tight mb-space-xs font-semibold">
                  <?= htmlspecialchars($artifacts[0]['title']) ?>
                </h2>
                <p class="font-body-md text-body-md text-on-surface-variant mb-space-md leading-relaxed">
                  <?= htmlspecialchars($artifacts[0]['description']) ?>
                </p>
                <div class="flex flex-wrap gap-1.5 mb-space-md font-label-code text-label-micro">
                  <?php foreach ($artifacts[0]['tags'] as $t): ?>
                    <span class="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant border border-outline-variant/10"><?= htmlspecialchars($t) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="pt-space-sm flex flex-col gap-space-xs">
                <button class="w-full flex items-center justify-center gap-space-xs bg-primary hover:bg-secondary text-on-primary font-label-code text-label-code font-semibold py-2.5 px-space-md rounded-lg transition-colors shadow-md" onclick="openLightbox(0)" type="button">
                  <span class="material-symbols-outlined text-[1.125rem]">pan_tool</span>
                  <span>Open in High-Resolution Lightbox</span>
                </button>
                <a href="<?= htmlspecialchars($artifacts[0]['related_url']) ?>" class="w-full flex items-center justify-center gap-space-xs bg-surface-container hover:bg-surface-container-high text-primary font-label-code text-label-code font-medium py-2 px-space-md rounded-lg transition-colors border border-outline-variant/10">
                  <span>Explore <?= htmlspecialchars($artifacts[0]['related_label']) ?></span>
                  <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Curated Visual Archive Grid -->
      <section class="max-w-[1600px] mx-auto px-gutter w-full mb-space-2xl">
        <div class="flex items-center justify-between mb-space-md">
          <div class="flex items-center gap-space-xs">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Curated Technical Portfolio</h2>
            <span class="font-label-code text-label-micro text-outline bg-surface-container-low px-2 py-0.5 rounded-full border border-outline-variant/10" id="visible-count"><?= count($artifacts) ?> Items Displayed</span>
          </div>
          <span class="font-label-micro text-label-micro text-outline uppercase hidden sm:block">
            Click artifact for high-resolution inspection
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-space-lg" id="galleryGrid">
          <?php foreach ($artifacts as $index => $item): ?>
            <div class="gallery-item flex flex-col gap-space-sm group"
                 data-category="<?= htmlspecialchars($item['category']) ?>"
                 data-title="<?= htmlspecialchars(strtolower($item['title'])) ?>">
              
              <!-- Image Card with Lightbox Trigger -->
              <div class="relative w-full aspect-video rounded-md overflow-hidden bg-surface-container-lowest cursor-pointer group-hover:shadow-md transition-shadow" onclick="openLightbox(<?= $index ?>)">
                <?= responsiveImage(
                    $item['image'],
                    $item['title'],
                    [
                        'class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500',
                        'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 360px'
                    ]
                ) ?>
                <div class="absolute top-2 left-2 bg-surface-container-lowest/80 backdrop-blur-md px-2 py-0.5 rounded font-label-micro text-label-micro text-primary">
                  <?= htmlspecialchars($item['id']) ?>
                </div>
                <div class="absolute bottom-2 right-2 bg-surface-container-lowest/80 backdrop-blur-md px-2 py-0.5 rounded font-label-code text-label-micro text-outline">
                  <?= htmlspecialchars($item['resolution']) ?>
                </div>
                <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <span class="bg-surface-container-lowest/90 text-primary px-space-sm py-1 rounded-full font-label-code text-label-micro flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">zoom_in</span> Inspect
                  </span>
                </div>
              </div>

              <!-- Details & Shared System Links (Override H) -->
              <div class="flex flex-col gap-space-xs flex-1 justify-between">
                <div>
                  <div class="flex items-center justify-between text-outline font-label-micro text-label-micro">
                    <span class="text-secondary uppercase font-medium"><?= htmlspecialchars($item['category_name']) ?></span>
                    <span>ARCHIVE SPEC</span>
                  </div>
                  <h3 class="font-headline-sm text-headline-sm text-on-surface group-hover:text-primary transition-colors mt-0.5 font-semibold">
                    <?= htmlspecialchars($item['title']) ?>
                  </h3>
                  <p class="font-body-sm text-body-sm text-on-surface-variant mt-1 line-clamp-2">
                    <?= htmlspecialchars($item['description']) ?>
                  </p>
                </div>

                <div class="pt-space-xs flex items-center justify-between border-t border-outline-variant/10">
                  <a href="<?= htmlspecialchars($item['related_url']) ?>" class="font-label-code text-label-code text-primary hover:text-secondary flex items-center gap-1 transition-colors font-medium">
                    <span><?= htmlspecialchars($item['related_label']) ?></span>
                    <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
                  </a>
                  <button type="button" class="text-on-surface-variant hover:text-primary font-label-code text-label-micro flex items-center gap-1 transition-colors" onclick="openLightbox(<?= $index ?>)">
                    <span class="material-symbols-outlined text-[14px]">fullscreen</span> Expand
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <div id="no-gallery-found" class="empty-state col-span-1 sm:col-span-2 lg:col-span-2 py-space-2xl text-center flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 my-space-lg" style="display: none;">
            <span class="material-symbols-outlined text-[2.5rem] text-outline">search_off</span>
            <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Matching Artifacts</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">No visual artifacts match your current search or category filter criteria.</p>
          </div>
        </div>
      </section>
      <?php endif; ?>

    </div>
  </main>

  <!-- High-Resolution Lightbox Modal (Accessible & Keyboard-Controlled) -->
  <div id="gallery-lightbox" class="fixed inset-0 z-50 bg-background/95 backdrop-blur-md flex items-center justify-center p-space-md opacity-0 pointer-events-none transition-opacity duration-300" role="dialog" aria-modal="true" aria-label="Visual Artifact Lightbox">
    <button type="button" class="absolute top-4 right-4 w-11 h-11 min-w-[44px] min-h-[44px] flex items-center justify-center rounded-lg bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-colors cursor-pointer" onclick="closeLightbox()" aria-label="Close Lightbox">
      <span class="material-symbols-outlined text-[24px]">close</span>
    </button>
    <div class="max-w-5xl w-full flex flex-col items-center gap-space-md">
      <div class="relative w-full max-h-[75vh] flex items-center justify-center overflow-hidden rounded-xl bg-surface-container-lowest border border-outline-variant/20 p-2">
        <img id="lightbox-img" src="" alt="Enlarged visual artifact preview" class="max-w-full max-h-[70vh] object-contain rounded"/>
      </div>
      <div class="flex items-center justify-between w-full text-on-surface font-label-code text-body-sm">
        <div class="flex flex-col">
          <span id="lightbox-title" class="font-semibold text-primary"></span>
          <span id="lightbox-desc" class="text-on-surface-variant text-[12px]"></span>
        </div>
        <a id="lightbox-link" href="#" class="px-space-md py-1.5 rounded bg-primary text-on-primary font-medium hover:bg-secondary transition-colors flex items-center gap-1">
          <span>Discover System</span>
          <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
        </a>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Gallery & Lightbox Script -->
  <script>
    var galleryData = <?= json_encode($artifacts, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var currentLightboxIndex = 0;

    function openLightbox(index) {
      var item = galleryData[index];
      if (!item) return;
      currentLightboxIndex = index;
      document.getElementById('lightbox-img').src = item.image;
      document.getElementById('lightbox-img').alt = item.title;
      document.getElementById('lightbox-title').textContent = item.id + ' — ' + item.title;
      document.getElementById('lightbox-desc').textContent = item.description || '';
      
      var linkEl = document.getElementById('lightbox-link');
      if (item.related_url) {
        linkEl.href = item.related_url;
        linkEl.querySelector('span').textContent = item.related_label || 'Discover System';
        linkEl.style.display = 'flex';
      } else {
        linkEl.style.display = 'none';
      }
      
      document.getElementById('gallery-lightbox').classList.remove('opacity-0', 'pointer-events-none');
      document.getElementById('gallery-lightbox').classList.add('opacity-100', 'pointer-events-auto');
      document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
      document.getElementById('gallery-lightbox').classList.remove('opacity-100', 'pointer-events-auto');
      document.getElementById('gallery-lightbox').classList.add('opacity-0', 'pointer-events-none');
      document.body.style.overflow = '';
    }

    function navigateLightbox(dir) {
      var newIndex = currentLightboxIndex + dir;
      if (newIndex >= 0 && newIndex < galleryData.length) {
        openLightbox(newIndex);
      }
    }

    // Keyboard support for Lightbox
    window.addEventListener('keydown', function(e) {
      var lightbox = document.getElementById('gallery-lightbox');
      if (!lightbox.classList.contains('opacity-100')) return;

      if (e.key === 'Escape') {
        closeLightbox();
      } else if (e.key === 'ArrowLeft') {
        navigateLightbox(-1);
      } else if (e.key === 'ArrowRight') {
        navigateLightbox(1);
      }
    });

    // Filtering & Search
    (function() {
      var catButtons = document.querySelectorAll('.filter-pill');
      var searchInput = document.getElementById('gallerySearch');
      var items = document.querySelectorAll('.gallery-item');
      var counter = document.getElementById('visible-count');
      var activeCat = 'all';

      function filterGallery() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var visible = 0;

        items.forEach(function(item) {
          var cat = item.getAttribute('data-category') || '';
          var title = item.getAttribute('data-title') || '';
          var matchCat = (activeCat === 'all' || cat.includes(activeCat));
          var matchQuery = (!query || title.includes(query) || cat.includes(query));

          if (matchCat && matchQuery) {
            item.style.display = 'flex';
            visible++;
          } else {
            item.style.display = 'none';
          }
        });

        if (counter) {
          counter.textContent = visible + ' Items Displayed';
        }
        
        var noResults = document.getElementById('no-gallery-found');
        if (noResults) {
          noResults.style.display = (visible === 0 && items.length > 0) ? 'flex' : 'none';
        }
      }

      catButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          catButtons.forEach(function(b) {
            b.classList.remove('active');
          });
          btn.classList.add('active');
          activeCat = btn.getAttribute('data-category');
          filterGallery();
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', filterGallery);
      }
    })();
  </script>
</body>
</html>
