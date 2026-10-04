<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'lab';
$expId = isset($_GET['id']) ? trim($_GET['id']) : '';

// 301 Redirect legacy parameterized URL to clean URL
if (strpos($_SERVER['REQUEST_URI'], '/lab-detail.php') === 0 && $expId !== '') {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /labs/' . rawurlencode($expId));
    exit;
}

// Load from JSON
$experimentsFile = __DIR__ . '/api/data/lab_experiments.json';
$allExperiments = [];
$experiment = null;
if ($expId !== '' && file_exists($experimentsFile)) {
    $allExperiments = json_decode(file_get_contents($experimentsFile), true) ?: [];
    foreach ($allExperiments as $item) {
        if (isset($item['id']) && strcasecmp($item['id'], $expId) === 0) {
            $experiment = $item;
            break;
        }
    }
}

if (!$experiment) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $robots = 'noindex,follow';
    $pageTitle = 'Investigation Not Found Lab';
    $pageDescription = 'The requested laboratory investigation was not found or is no longer available.';
    $canonicalUrl = 'https://mohammedalrashadi.com/lab.php';
} else {
    $pageTitle = $experiment['id'] . ' ' . $experiment['title'];
    $pageDescription = $experiment['question'] ?? $experiment['title'];
    $canonicalUrl = 'https://mohammedalrashadi.com/labs/' . rawurlencode($experiment['id']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
  <?php if ($experiment): ?>
  <!-- JSON-LD Structured Data: Dataset/Article -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Dataset",
    "name": <?= json_encode($experiment['title'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    "description": <?= json_encode($experiment['question'], JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    "url": <?= json_encode($canonicalUrl, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    "creator": {
      "@type": "Person",
      "name": "Mohammed Alrashadi"
    }
  }
  </script>
  <?php endif; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="relative z-0 w-full pt-16 bg-background min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">
      <?php if (!$experiment): ?>
        <div class="max-w-[800px] w-full mx-auto px-gutter py-space-2xl text-center flex flex-col items-center justify-center gap-space-md my-space-xl">
          <div class="p-space-lg rounded-full bg-surface-container-low border border-outline-variant/10 text-outline">
            <span class="material-symbols-outlined text-[3rem]">science</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Investigation Not Found</h1>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-lg leading-relaxed">
            The requested benchmark investigation (<?= htmlspecialchars($expId ?: 'unknown') ?>) was not found or is no longer available in the active studio lab.
          </p>
          <div class="pt-space-md">
            <a href="/lab.php" class="px-space-lg py-space-sm rounded-lg bg-primary text-on-primary font-label-code text-label-code font-semibold hover:opacity-90 transition-all inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
              <span>Back to Studio Lab</span>
            </a>
          </div>
        </div>
      <?php else: ?>
      
      <!-- Top Breadcrumb & Metadata Strip -->
      <section class="w-full border-b border-outline-variant/10 bg-surface-container-lowest/50 py-space-sm">
        <div class="container-study flex flex-wrap items-center justify-between gap-space-sm font-label-code text-label-micro text-outline">
          <div class="flex items-center gap-space-xs">
            <a href="/index.php" class="hover:text-primary transition-colors">HOME</a>
            <span>/</span>
            <a href="/lab.php" class="hover:text-primary transition-colors">LAB</a>
            <span>/</span>
            <span class="text-primary font-semibold"><?= htmlspecialchars($experiment['id']) ?></span>
          </div>
          <div class="flex items-center gap-space-md">
            <span><?= htmlspecialchars($experiment['readTime'] ?? '8 min') ?> READ</span>
            <span>•</span>
            <a href="/lab.php" class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[1rem]">arrow_back</span>
              All Experiments
            </a>
          </div>
        </div>
      </section>

      <!-- Main Experiment Container (Study Container ≈ 1040px) -->
      <div class="container-study py-space-xl flex flex-col gap-space-2xl">
        
        <!-- Header -->
        <header class="flex flex-col gap-space-md border-b border-outline-variant/10 pb-space-lg">
          <div class="flex flex-wrap items-center gap-space-sm">
            <span class="px-space-sm py-0.5 rounded bg-primary/10 text-primary font-label-micro text-label-micro uppercase font-semibold">
              <?= htmlspecialchars($experiment['id']) ?> • <?= htmlspecialchars($experiment['categoryLabel'] ?? 'BENCHMARK') ?>
            </span>
            <span class="px-space-sm py-0.5 rounded bg-surface-container font-label-code text-label-micro text-on-surface-variant">
              <?= htmlspecialchars($experiment['status'] ?? 'CONCLUDED') ?>
            </span>
          </div>

          <h1 class="font-headline-lg lg:text-[2.25rem] text-on-surface tracking-tight font-bold leading-tight">
            <?= htmlspecialchars($experiment['title']) ?>
          </h1>

          <div class="flex flex-wrap items-center gap-space-sm pt-space-xs">
            <?php foreach (($experiment['tech'] ?? []) as $t): ?>
              <span class="px-space-xs py-[0.125rem] bg-surface-container-low rounded border border-outline-variant/10 font-label-code text-label-micro text-on-surface-variant">
                <?= htmlspecialchars($t) ?>
              </span>
            <?php endforeach; ?>
          </div>

          <!-- User Platform Interaction Bar (Likes, Bookmarks, History Tracking) -->
          <div class="user-interaction-bar flex items-center justify-between pt-3 border-t border-outline-variant/10" 
               data-content-type="lab" 
               data-content-id="<?= htmlspecialchars($experiment['id']) ?>"
               data-track-history="true">
            <div class="flex items-center gap-2">
              <button type="button" class="user-like-btn flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-text-secondary hover:text-red-400 hover:border-red-400/40 transition-colors text-xs font-mono" title="Like this experiment">
                <span class="material-symbols-outlined user-like-icon text-[18px]">favorite_border</span>
                <span class="user-like-count">0</span>
              </button>
              <button type="button" class="user-bookmark-btn flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-text-secondary hover:text-primary hover:border-primary/40 transition-colors text-xs font-mono" title="Save to bookmarks">
                <span class="material-symbols-outlined user-bm-icon text-[18px]">bookmark_border</span>
                <span>Bookmark</span>
              </button>
            </div>
          </div>
        </header>

        <!-- 1. Question & Problem Statement -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">1. Research Question</h2>
          <div class="bg-surface-container-low p-space-md rounded-xl border border-outline-variant/10">
            <p class="font-body-lg text-body-lg text-on-surface leading-relaxed">
              <?= htmlspecialchars($experiment['question']) ?>
            </p>
          </div>
        </section>

        <!-- 2. Hypothesis -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">2. Working Hypothesis</h2>
          <div class="card-recessed p-space-md border-l-4 border-l-primary">
            <p class="font-body-md text-body-md text-on-surface italic leading-relaxed">
              "<?= htmlspecialchars($experiment['hypothesis']) ?>"
            </p>
          </div>
        </section>

        <!-- 3. Environment & Hardware -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">3. Test Environment &amp; Hardware</h2>
          <div class="bg-surface-container-low p-space-md rounded-xl border border-outline-variant/10 font-label-code text-body-sm text-on-surface-variant leading-relaxed">
            <?= htmlspecialchars($experiment['environment']) ?>
          </div>
        </section>

        <!-- 4. Methodology -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">4. Benchmark Methodology</h2>
          <div class="bg-surface-container-low p-space-md rounded-xl border border-outline-variant/10">
            <p class="font-body-md text-body-md text-on-surface leading-relaxed">
              <?= htmlspecialchars($experiment['method']) ?>
            </p>
          </div>
        </section>

        <!-- 5. Empirical Results & Data Table -->
        <section class="flex flex-col gap-space-md">
          <div class="flex items-center justify-between">
            <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">5. Empirical Results &amp; Data</h2>
            <span class="font-label-code text-label-micro text-outline">Controlled Sample</span>
          </div>

          <div class="card-recessed p-space-md border-primary/20 flex flex-col gap-space-xs">
            <span class="font-label-code text-body-sm text-primary font-bold">
              <?= htmlspecialchars($experiment['outcomeHeadline']) ?>
            </span>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
              <?= htmlspecialchars($experiment['outcomeDesc']) ?>
            </p>
          </div>

          <?php if (!empty($experiment['metrics'])): ?>
          <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 overflow-x-auto shadow-md">
            <table class="w-full text-left font-label-code text-body-sm border-collapse">
              <thead>
                <tr class="border-b border-outline-variant/20 bg-surface-container text-outline text-[11px] uppercase">
                  <th class="py-2.5 px-4 font-semibold">Parameter / Metric</th>
                  <th class="py-2.5 px-4 font-semibold">Baseline</th>
                  <th class="py-2.5 px-4 font-semibold text-primary">Tuned / Comparison</th>
                  <th class="py-2.5 px-4 font-semibold">Trade-Off / Analysis</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/10 text-on-surface-variant">
                <?php foreach ($experiment['metrics'] as $row): ?>
                  <tr>
                    <td class="py-3 px-4 text-on-surface font-medium"><?= htmlspecialchars($row['param']) ?></td>
                    <td class="py-3 px-4 text-on-surface-variant"><?= htmlspecialchars($row['baseline']) ?></td>
                    <td class="py-3 px-4 text-primary font-semibold"><?= htmlspecialchars($row['tuned']) ?></td>
                    <td class="py-3 px-4 text-outline"><?= htmlspecialchars($row['tradeoff'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

          <?php if (!empty($experiment['image'])): ?>
          <!-- Benchmark Chart Visualizer -->
          <div class="bg-surface-container-low rounded-xl p-space-md border border-outline-variant/10 flex flex-col gap-space-sm mt-space-sm">
            <div class="flex items-center justify-between pb-space-xs border-b border-outline-variant/10">
              <span class="font-label-code text-label-code text-on-surface font-semibold">FIGURE: MEASURED RUNTIME PROFILE</span>
              <span class="font-label-micro text-label-micro text-outline">Telemetry Plot</span>
            </div>
            <div class="card-recessed w-full overflow-hidden flex items-center justify-center p-space-sm">
              <img loading="lazy" decoding="async" src="<?= htmlspecialchars($experiment['image']) ?>" alt="Benchmark telemetry diagram" class="w-full h-auto max-h-[460px] object-contain rounded"/>
            </div>
          </div>
          <?php endif; ?>
        </section>

        <!-- 6. Observations & Invariants -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">6. Observations &amp; Architectural Invariants</h2>
          <div class="bg-surface-container-low p-space-md rounded-xl border border-outline-variant/10">
            <p class="font-body-md text-body-md text-on-surface leading-relaxed">
              <?= htmlspecialchars($experiment['observations']) ?>
            </p>
          </div>
        </section>

        <!-- 7. Conclusion & Rule of Thumb -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">7. Rule of Thumb / Takeaway</h2>
          <div class="bg-surface-container-low p-space-md rounded-xl border border-outline-variant/10">
            <p class="font-body-md text-body-md text-on-surface leading-relaxed">
              <?= htmlspecialchars($experiment['conclusion']) ?>
            </p>
          </div>
        </section>

        <!-- 8. Reproducibility -->
        <section class="flex flex-col gap-space-xs">
          <h2 class="font-label-code text-label-code text-primary uppercase font-semibold tracking-wider">8. Reproducibility (Run It Yourself)</h2>
          <div class="card-recessed p-space-md flex flex-col gap-space-xs">
            <div class="flex items-center justify-between">
              <span class="font-label-code text-label-micro text-outline">HARNESS COMMAND:</span>
              <button type="button" class="copy-code-btn px-2 py-1 rounded bg-surface-container hover:bg-surface-container-high text-on-surface font-label-code text-label-micro transition-colors flex items-center gap-1" data-code="<?= htmlspecialchars($experiment['repro_command']) ?>">
                <span class="material-symbols-outlined text-[14px]">content_copy</span>
                <span>Copy</span>
              </button>
            </div>
            <pre class="font-label-code text-body-sm text-primary overflow-x-auto p-space-xs bg-surface-container-low rounded"><code><?= htmlspecialchars($experiment['repro_command']) ?></code></pre>
          </div>
        </section>

        <!-- Footer Navigation Links -->
        <div class="pt-space-md border-t border-outline-variant/10 flex flex-col sm:flex-row items-center justify-between gap-space-md">
          <a href="/lab.php" class="px-space-lg py-space-sm rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-body-sm font-medium transition-colors flex items-center gap-space-xs">
            <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
            <span>All Lab Experiments</span>
          </a>
          <a href="/gallery.php" class="px-space-lg py-space-sm rounded-lg bg-primary text-on-primary font-body-sm font-semibold hover:bg-secondary transition-colors flex items-center gap-space-xs">
            <span>Explore Architecture Gallery</span>
            <span class="material-symbols-outlined text-[1.125rem]">arrow_forward</span>
          </a>
        </div>
        
        <?php
        $relatedItems = [];
        if (isset($experiment)) {
            require_once __DIR__ . '/src/autoload.php';
            $resolver = new \Domain\Content\RelatedContentResolver();
            $relatedItems = $resolver->getRelatedLabExperiments($experiment['id'], $experiment['categoryLabel'] ?? $experiment['category'] ?? '', 3);
        }
        require __DIR__ . '/includes/related_content.php';
        ?>
      <?php endif; ?>

      </div>
    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script>
    (function() {
      var copyButtons = document.querySelectorAll('.copy-code-btn');
      copyButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          var code = btn.getAttribute('data-code') || '';
          navigator.clipboard.writeText(code).then(function() {
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px] text-primary">check</span> Copied!';
            setTimeout(function() {
              btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">content_copy</span> Copy';
            }, 2000);
          });
        });
      });
    })();
  </script>
  <script src="/assets/js/user_interactions.js"></script>
</body>
</html>
