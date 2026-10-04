<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'lab';
$pageTitle = 'Lab';
$pageDescription = 'Studio Lab — Empirical Benchmarks & Systems Notes. Controlled benchmarks, database indexing investigations, and reproducible engineering notes.';
$canonicalUrl = 'https://mohammedalrashadi.com/lab.php';

// Load experiments data from local JSON
$experimentsFile = __DIR__ . '/api/data/lab_experiments.json';
$experiments = [];
if (file_exists($experimentsFile)) {
    $jsonContent = file_get_contents($experimentsFile);
    $experiments = json_decode($jsonContent, true) ?: [];
}

$categories = [];
foreach ($experiments as $exp) {
    if (!empty($exp['category'])) {
        $cSlug = strtolower(trim($exp['category']));
        if (!isset($categories[$cSlug])) {
            $categories[$cSlug] = $exp['categoryLabel'] ?? ucfirst($cSlug);
        }
    }
}

$flagship = !empty($experiments) ? $experiments[0] : null;
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
      
      <!-- Top Section: Header & Research Context -->
      <section class="container-catalog reveal-section pt-space-xl pb-space-lg">
        <div class="flex flex-col gap-space-sm">
          <div class="flex items-center gap-space-xs text-primary font-label-micro uppercase tracking-widest">
            <span class="w-2 h-2 rounded-full bg-primary"></span>
            <span>Empirical Research &amp; Benchmark Notebook</span>
          </div>
          <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-space-md">
            <div class="flex flex-col gap-space-xs max-w-2xl">
              <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight font-bold">Studio Lab</h1>
              <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
                Controlled benchmarks, concurrency profiling, and reproducible investigations into database internals, memory hierarchies, and runtime invariants.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-space-xs bg-surface-container-low p-space-xs rounded-lg border border-outline-variant/10">
              <div class="flex items-center gap-space-xs px-space-sm py-space-xs bg-surface-container rounded">
                <span class="font-label-code text-label-code text-on-surface font-semibold"><?= count($experiments) ?> Investigations Logged</span>
              </div>
              <span class="font-label-micro text-label-micro text-on-surface-variant px-space-xs hidden sm:inline">Deterministic Harnesses</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Category Filter & Search Bar -->
      <section class="container-catalog reveal-section pb-space-lg">
        <div class="bg-surface-container-low p-space-md rounded-xl flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md border border-outline-variant/10">
          <div class="filter-scroll-wrapper">
            <div class="filter-scroll-container" id="categoryFilters">
              <button class="filter-pill active" data-category="all" type="button">
                All (<?= count($experiments) ?>)
              </button>
              <?php foreach ($categories as $catSlug => $catLabel): ?>
              <button class="filter-pill" data-category="<?= htmlspecialchars($catSlug) ?>" type="button">
                <?= htmlspecialchars($catLabel) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="flex items-center gap-space-sm w-full lg:w-auto">
            <div class="relative flex-1 lg:w-72">
              <label for="labSearch" class="sr-only">Search investigations</label>
              <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-[1.125rem] text-outline pointer-events-none">search</span>
              <input class="w-full bg-surface-container-lowest text-on-surface placeholder:text-outline font-body-sm text-body-sm pl-9 pr-space-md py-space-xs rounded focus:outline-none focus:ring-1 focus:ring-primary transition-all border border-outline-variant/10" id="labSearch" placeholder="Search investigations, metrics..." type="text"/>
            </div>
          </div>
        </div>
      </section>

      <?php if ($flagship): ?>
      <!-- Flagship Empirical Benchmark Section -->
      <section class="container-catalog reveal-section pb-space-2xl">
        <div class="bg-surface-container-low rounded-xl p-space-lg lg:p-space-xl flex flex-col gap-space-lg shadow-xl relative overflow-hidden border border-outline-variant/10">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-sm">
            <div class="flex items-center gap-space-xs">
              <span class="font-label-micro text-label-micro text-primary uppercase tracking-widest bg-primary/10 px-space-xs py-[0.125rem] rounded font-semibold">
                <?= htmlspecialchars($flagship['id']) ?>
              </span>
              <span class="text-outline-variant font-label-micro text-label-micro">•</span>
              <span class="font-label-micro text-label-micro text-on-surface-variant uppercase tracking-widest">
                Flagship Benchmark: <?= htmlspecialchars($flagship['categoryLabel'] ?? 'Systems') ?>
              </span>
            </div>
            <div class="flex items-center gap-space-sm font-label-code text-label-micro text-outline">
              <span><?= htmlspecialchars($flagship['readTime'] ?? '8 min') ?> Read</span>
              <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-start">
            <div class="lg:col-span-5 flex flex-col gap-space-md">
              <div class="flex flex-col gap-space-xs">
                <h2 class="font-headline-md text-headline-md text-on-surface font-semibold tracking-tight">
                  <a href="/labs/<?= urlencode($flagship['id']) ?>" class="hover:text-primary transition-colors">
                    <?= htmlspecialchars($flagship['title']) ?>
                  </a>
                </h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                  <?= htmlspecialchars($flagship['question']) ?>
                </p>
              </div>

              <div class="bg-surface-container-lowest p-space-md rounded-lg flex flex-col gap-space-xs border border-outline-variant/10">
                <span class="font-label-micro text-label-micro text-outline uppercase tracking-wider">Hypothesis</span>
                <p class="font-body-sm text-body-sm text-on-surface italic">
                  "<?= htmlspecialchars($flagship['hypothesis']) ?>"
                </p>
              </div>

              <div class="bg-surface-container-lowest p-space-md rounded-lg flex flex-col gap-space-xs border border-primary/20">
                <span class="font-label-micro text-label-micro text-primary uppercase tracking-wider font-semibold">Empirical Outcome</span>
                <p class="font-label-code text-body-sm text-primary font-bold">
                  <?= htmlspecialchars($flagship['outcomeHeadline']) ?>
                </p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                  <?= htmlspecialchars($flagship['outcomeDesc']) ?>
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-space-sm pt-space-xs">
                <a href="/labs/<?= urlencode($flagship['id']) ?>" class="px-space-md py-space-xs rounded-lg bg-primary text-on-primary font-body-sm text-body-sm font-semibold hover:bg-secondary transition-colors flex items-center gap-space-xs">
                  <span>Read Full Experiment Log &amp; Repro</span>
                  <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
                </a>
              </div>
            </div>

            <!-- Benchmark Visual -->
            <div class="lg:col-span-7 flex flex-col gap-space-sm">
              <div class="bg-surface-container-lowest rounded-xl overflow-hidden p-space-md flex flex-col gap-space-sm shadow-md border border-outline-variant/10">
                <div class="flex items-center justify-between pb-space-xs border-b border-outline-variant/10">
                  <div class="flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[1.125rem] text-primary">analytics</span>
                    <span class="font-label-code text-label-code text-on-surface font-semibold">BENCHMARK TELEMETRY &amp; PROFILES</span>
                  </div>
                  <?php if (!empty($flagship['environment'])): ?>
                  <span class="font-label-micro text-label-micro text-outline"><?= htmlspecialchars(mb_strimwidth((string) $flagship['environment'], 0, 60, '…', 'UTF-8')) ?></span>
                  <?php endif; ?>
                </div>
                <?php if (!empty($flagship['image'])): ?>
                <a href="/labs/<?= urlencode($flagship['id']) ?>" class="w-full rounded-lg overflow-hidden bg-surface-container-lowest flex items-center justify-center group block">
                  <img alt="<?= htmlspecialchars('Benchmark visual for experiment: ' . ($flagship['title'] ?? '')) ?>" class="w-full h-auto max-h-[360px] object-contain rounded group-hover:scale-[1.01] transition-transform duration-300" src="<?= htmlspecialchars((string) $flagship['image']) ?>"/>
                </a>
                <?php endif; ?>
                <div class="flex flex-wrap gap-1 font-label-code text-label-micro text-on-surface-variant pt-space-xs">
                  <?php foreach (($flagship['tech'] ?? []) as $t): ?>
                    <span class="px-space-xs py-[0.125rem] bg-surface-container rounded border border-outline-variant/10"><?= htmlspecialchars($t) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Experiments Grid -->
      <section class="container-catalog reveal-section pb-space-2xl">
        <div class="flex flex-col gap-space-lg">
          <div class="flex items-center justify-between">
            <h2 class="font-headline-md text-headline-md text-on-surface font-semibold tracking-tight">Active Matrix &amp; Controlled Experiments</h2>
            <span class="font-label-code text-label-code text-outline" id="experimentsCount"><?= count($experiments) ?> Experiments Logged</span>
          </div>

          <?php if (empty($experiments)): ?>
            <div class="bg-surface-container-low rounded-xl p-space-xl text-center flex flex-col items-center justify-center gap-space-sm border border-outline-variant/10 py-16">
              <span class="material-symbols-outlined text-4xl text-outline mb-2">science</span>
              <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Lab Investigations Published Yet</h3>
              <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md leading-relaxed">
                Controlled benchmarks, concurrency profiling, and reproducible engineering notes are currently in progress. New investigations will appear here once verified.
              </p>
            </div>
          <?php else: ?>
          <div class="flex flex-col border-t border-outline-variant/20" id="experimentsGrid">
            <?php foreach ($experiments as $exp): 
              $detailUrl = '/labs/' . urlencode($exp['id']);
            ?>
              <article class="experiment-card flex flex-col lg:flex-row gap-space-md lg:gap-space-xl py-space-xl border-b border-outline-variant/20 transition-colors hover:bg-surface-container-lowest group" 
                       data-category="<?= htmlspecialchars(strtolower($exp['category'])) ?>"
                       data-title="<?= htmlspecialchars(strtolower($exp['title'] . ' ' . $exp['question'])) ?>">
                
                <!-- Meta Column -->
                <div class="flex flex-col gap-1 lg:w-1/4 shrink-0 lg:pt-1">
                  <span class="font-label-code text-label-micro text-primary uppercase tracking-widest font-semibold">
                    <?= htmlspecialchars($exp['id']) ?> • <?= htmlspecialchars(strtoupper($exp['categoryLabel'] ?? $exp['category'])) ?>
                  </span>
                  <span class="font-label-code text-label-micro text-text-muted mt-1">
                    <?= htmlspecialchars($exp['readTime'] ?? '7 min') ?> READ
                  </span>
                </div>
                
                <!-- Content Column -->
                <div class="flex flex-col gap-space-sm flex-1">
                  <h3 class="font-headline-sm text-headline-sm text-on-surface group-hover:text-primary transition-colors font-semibold">
                    <a href="<?= htmlspecialchars($detailUrl) ?>" class="block">
                      <?= htmlspecialchars($exp['title']) ?>
                    </a>
                  </h3>
                  <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed max-w-3xl">
                    <?= htmlspecialchars($exp['question']) ?>
                  </p>

                  <div class="bg-surface-container-low p-space-md rounded-lg flex flex-col gap-space-xs border border-outline-variant/10 mt-2 max-w-3xl">
                    <span class="font-label-micro text-label-micro text-outline uppercase tracking-wider">Empirical Outcome</span>
                    <span class="font-label-code text-body-sm text-primary font-semibold"><?= htmlspecialchars($exp['outcomeHeadline']) ?></span>
                  </div>

                  <div class="flex flex-wrap gap-1 font-label-code text-label-micro text-on-surface-variant mt-2">
                    <?php foreach (($exp['tech'] ?? []) as $t): ?>
                      <span class="px-space-xs py-[0.125rem] bg-surface-container rounded border border-outline-variant/10"><?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                  </div>

                  <div class="mt-2">
                    <a class="font-label-code text-label-code text-primary hover:text-secondary flex items-center gap-space-xs transition-colors w-max font-medium" href="<?= htmlspecialchars($detailUrl) ?>">
                      <span>View Methodology &amp; Repro</span>
                      <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
                    </a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
            <div id="no-labs-found" class="empty-state py-space-2xl text-center flex-col items-center justify-center gap-space-sm bg-surface-container-low rounded-xl border border-outline-variant/10 my-space-lg" style="display: none;">
              <span class="material-symbols-outlined text-[2.5rem] text-outline">search_off</span>
              <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">No Matching Experiments</p>
              <p class="font-body-sm text-body-sm text-on-surface-variant max-w-md">No experiments match your current search or category filter criteria.</p>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Interactive Lab Search & Filtering Script -->
  <script>
    (function() {
      var buttons = document.querySelectorAll('.filter-pill');
      var searchInput = document.getElementById('labSearch');
      var cards = document.querySelectorAll('.experiment-card');
      var countDisplay = document.getElementById('experimentsCount');
      var currentCategory = 'all';

      function applyFilter() {
        var query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        var visible = 0;

        cards.forEach(function(card) {
          var cat = card.getAttribute('data-category') || '';
          var title = card.getAttribute('data-title') || '';
          var matchesCat = (currentCategory === 'all' || cat.indexOf(currentCategory) !== -1);
          var matchesQuery = (!query || title.indexOf(query) !== -1 || cat.indexOf(query) !== -1);

          if (matchesCat && matchesQuery) {
            card.style.display = 'flex';
            visible++;
          } else {
            card.style.display = 'none';
          }
        });

        if (countDisplay) {
          countDisplay.textContent = visible + ' Experiments Logged';
        }
        
        var noResults = document.getElementById('no-labs-found');
        if (noResults) {
          noResults.style.display = (visible === 0 && cards.length > 0) ? 'flex' : 'none';
        }
      }

      buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          buttons.forEach(function(b) {
            b.classList.remove('active');
          });
          btn.classList.add('active');
          currentCategory = btn.getAttribute('data-category');
          applyFilter();
        });
      });

      if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
      }
    })();
  </script>
</body>
</html>
