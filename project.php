<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'projects';
$projectSlug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$legacyId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

require_once __DIR__ . '/includes/image_helper.php';

$project = null;
$galleryImages = [];

// Fetch from database if available
if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        require_once __DIR__ . '/src/autoload.php';
        
        $pdo = getDB();
        $postRepo = new \Domain\Content\PostRepository($pdo);
        
        // Handle legacy ?id= routing
        if ($legacyId && !$projectSlug) {
            $legacySlug = $postRepo->getLegacyProjectSlug($legacyId);
            if ($legacySlug) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: /projects/' . rawurlencode($legacySlug));
                exit;
            }
        }
        
        // Handle standard clean URL routing
        if ($projectSlug) {
            $project = $postRepo->getPublishedProjectBySlug($projectSlug);

            if ($project) {
                $galleryImages = $postRepo->getProjectGalleryImages($project['id']);
            } else {
                // Check redirect table
                $redir = $postRepo->getRedirect('/projects/' . $projectSlug);
                if ($redir) {
                    header('HTTP/1.1 301 Moved Permanently');
                    header('Location: ' . $redir);
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        $project = null;
    }
}

if ($project) {
    $ogType = 'article';
    $pageTitle = !empty($project['meta_title']) ? $project['meta_title'] : $project['title'];
    $pageDescription = !empty($project['meta_description']) ? $project['meta_description'] : mb_substr(strip_tags($project['content']), 0, 160) . '...';
    $canonicalUrl = 'https://mohammedalrashadi.com/projects/' . rawurlencode($project['slug']);
    $pubDate = !empty($project['published_at']) ? $project['published_at'] : $project['created_at'];
    $modDate = !empty($project['updated_at']) ? $project['updated_at'] : $pubDate;
    $articlePublishedTime = date('c', strtotime($pubDate ?: 'now'));
    $articleModifiedTime = date('c', strtotime($modDate ?: 'now'));
    $articleAuthor = 'Mohammed Alrashadi';
    if (!empty($project['image_url'])) {
        $ogImage = $project['image_url'];
    }
} else {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $robots = 'noindex,follow';
    $pageTitle = 'Project Not Found';
    $pageDescription = 'The requested engineering project case study was not found.';
    $canonicalUrl = 'https://mohammedalrashadi.com/projects.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
  
  <?php if ($project): 
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        'headline' => $pageTitle,
        'description' => $pageDescription,
        'author' => [
            '@type' => 'Person',
            'name' => 'Mohammed Alrashadi',
            'url' => 'https://mohammedalrashadi.com/about'
        ],
        'datePublished' => $articlePublishedTime,
        'dateModified' => $articleModifiedTime,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl
        ]
    ];
    if (!empty($ogImage)) {
        $structuredData['image'] = 'https://mohammedalrashadi.com' . $ogImage;
    }
    
    $breadcrumbData = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => 'https://mohammedalrashadi.com/'
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Projects',
                'item' => 'https://mohammedalrashadi.com/projects'
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $pageTitle,
                'item' => $canonicalUrl
            ]
        ]
    ];
  ?>
  <!-- JSON-LD Structured Data: CreativeWork -->
  <script type="application/ld+json">
  <?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <!-- JSON-LD Structured Data: BreadcrumbList -->
  <script type="application/ld+json">
  <?= json_encode($breadcrumbData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <?php endif; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="relative z-0 w-full pt-16 bg-background min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">
      
      <!-- Top Navigation & Breadcrumb -->
      <section class="w-full border-b border-outline-variant/10 bg-surface-container-lowest/50 py-space-sm">
        <div class="container-study flex flex-wrap items-center justify-between gap-space-sm font-label-code text-label-micro text-outline">
          <div class="flex items-center gap-space-xs">
            <a href="/index.php" class="hover:text-primary transition-colors">HOME</a>
            <span>/</span>
            <a href="/projects.php" class="hover:text-primary transition-colors">PROJECTS</a>
            <span>/</span>
            <span class="text-primary font-semibold"><?= htmlspecialchars($project['category'] ?? 'CASE STUDY') ?></span>
          </div>
          <div>
            <a href="/projects.php" class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1 font-medium">
              <span class="material-symbols-outlined text-[1rem]">arrow_back</span>
              Back to Projects
            </a>
          </div>
        </div>
      </section>

      <?php if (!$project): ?>
        <div class="max-w-[800px] w-full mx-auto px-gutter py-space-2xl text-center flex flex-col items-center justify-center gap-space-md my-space-xl">
          <div class="p-space-lg rounded-full bg-surface-container-low border border-outline-variant/10 text-outline">
            <span class="material-symbols-outlined text-[3rem]">inventory_2</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Case Study Not Found</h1>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-lg leading-relaxed">
            The requested project case study was not found or is no longer available in the published engineering archive.
          </p>
          <div class="pt-space-md">
            <a href="/projects.php" class="px-space-lg py-space-sm rounded-lg bg-primary text-on-primary font-label-code text-label-code font-semibold hover:opacity-90 transition-all inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
              <span>Back to Projects Archive</span>
            </a>
          </div>
        </div>
      <?php else: ?>
      <!-- Main Project Case Study Container (Study Container ≈ 1040px) -->
      <div class="container-study py-space-xl flex flex-col gap-space-2xl">
        
        <!-- 1. OVERVIEW & TITLE -->
        <header class="flex flex-col gap-space-md">
          <div class="flex flex-wrap items-center gap-space-sm">
            <span class="px-space-sm py-space-xs rounded bg-primary/10 text-primary font-label-micro text-label-micro uppercase font-semibold">
              <?= htmlspecialchars($project['category'] ?? 'Distributed Systems') ?>
            </span>
            <span class="font-label-code text-label-micro text-outline">
              Published: <?= !empty($project['created_at']) ? date('F Y', strtotime($project['created_at'])) : 'Recent' ?>
            </span>
          </div>

          <h1 class="font-headline-lg lg:text-display text-on-surface tracking-tight leading-tight font-bold">
            <?= htmlspecialchars($project['title']) ?>
          </h1>

          <p class="font-body-lg text-body-lg text-on-surface-variant max-w-4xl leading-relaxed">
            <?= nl2br(htmlspecialchars($project['content'])) ?>
          </p>

          <!-- User Platform Interaction Bar (Likes, Bookmarks, History Tracking) -->
          <div class="user-interaction-bar flex items-center justify-between pt-3 border-t border-outline-variant/10" 
               data-content-type="project" 
               data-content-id="<?= (int)$project['id'] ?>"
               data-track-history="true">
            <div class="flex items-center gap-2">
              <button type="button" class="user-like-btn flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-text-secondary hover:text-red-400 hover:border-red-400/40 transition-colors text-xs font-mono" title="Like this project">
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

        <!-- 2. ARCHITECTURE DIAGRAM / VISUAL ASSET -->
        <section class="flex flex-col gap-space-xs">
          <div class="relative w-full rounded-xl overflow-hidden bg-surface-container-lowest border border-outline-variant/10 shadow-xl group">
            <?= responsiveImage(
                $project['image_url'] ?? '/assets/diagram_distributed_systems.png',
                htmlspecialchars($project['title']) . ' Architecture',
                [
                    'class' => 'w-full h-auto object-cover object-center group-hover:scale-[1.01] transition-transform duration-500',
                    'priority' => true,
                    'sizes' => '(max-width: 1024px) 100vw, 1200px'
                ]
            ) ?>
            <div class="absolute bottom-3 left-3 right-3 bg-surface-container-lowest/90 backdrop-blur-md px-space-md py-space-xs rounded-lg flex items-center justify-between text-on-surface">
              <span class="font-label-code text-label-micro text-primary">System Architecture &amp; Data Flow</span>
              <span class="font-label-code text-label-micro text-outline">Structural Diagram</span>
            </div>
          </div>
        </section>

        <!-- 3. PROBLEM & SOLUTION -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg">
          <!-- Problem Statement -->
          <div class="bg-surface-container-low p-space-lg rounded-xl border border-outline-variant/10 flex flex-col gap-space-sm">
            <div class="flex items-center gap-space-xs text-error">
              <span class="material-symbols-outlined text-[1.25rem]">error_outline</span>
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">The Engineering Problem</h2>
            </div>
            <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
              <?= htmlspecialchars($project['problem'] ?? 'High concurrency, distributed network retries, and transient failures create dual-write anomalies and race conditions where state changes risk corrupting invariants without robust isolation.') ?>
            </p>
          </div>

          <!-- Solution Approach -->
          <div class="bg-surface-container-low p-space-lg rounded-xl border border-outline-variant/10 flex flex-col gap-space-sm">
            <div class="flex items-center gap-space-xs text-primary">
              <span class="material-symbols-outlined text-[1.25rem]">check_circle</span>
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">The Architectural Solution</h2>
            </div>
            <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
              <?= htmlspecialchars($project['solution'] ?? 'Engineered a resilient pipeline pairing transactional outbox persistence with deterministic payload fingerprinting and adaptive flow control to guarantee idempotent state mutations.') ?>
            </p>
          </div>
        </section>

        <!-- 4. ARCHITECTURAL DEEP DIVE -->
        <section class="flex flex-col gap-space-md bg-surface-container-low p-space-lg rounded-xl border border-outline-variant/10">
          <div class="flex items-center gap-space-xs text-primary">
            <span class="material-symbols-outlined text-[1.25rem]">schema</span>
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Architecture &amp; Data Flow</h2>
          </div>
          <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">
            <?= htmlspecialchars($project['architecture_desc'] ?? 'Operations flow through clear decoupled layers ensuring strict transactional isolation before asynchronous message propagation across distributed workers.') ?>
          </p>
        </section>

        <!-- 5. KEY FEATURES -->
        <section class="flex flex-col gap-space-md">
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Key Architectural Features</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
            <?php 
              $features = $project['features'] ?? [
                'Transactional Integrity: Guarantees consistent state mutations without phantom updates.',
                'Deterministic Fingerprinting: Verifies idempotency across duplicate network retries.',
                'Adaptive Flow Control: Protects consumers from buffer exhaustion during load surges.',
                'Clean Recovery Semantics: Automatic reconciliation following network partitions.'
              ];
              foreach ($features as $feat): 
            ?>
              <div class="p-space-md rounded-lg bg-surface-container-low border border-outline-variant/10 flex items-start gap-space-sm">
                <span class="material-symbols-outlined text-primary text-[1.125rem] mt-0.5">verified</span>
                <span class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed"><?= htmlspecialchars($feat) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 6. ENGINEERING DECISIONS & TRADE-OFFS -->
        <section class="flex flex-col gap-space-md">
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Engineering Decisions &amp; Trade-Offs</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
            <?php 
              $decisions = $project['decisions'] ?? [
                'Simplicity vs Complexity: Prioritized single-database ACID outbox over distributed multi-phase commit protocols.',
                'Memory vs Latency: Traded minor memory overhead for O(1) in-memory deduplication lookup efficiency.'
              ];
              foreach ($decisions as $dec): 
            ?>
              <div class="p-space-md rounded-lg bg-surface-container-low border border-outline-variant/10 flex flex-col gap-1">
                <span class="font-label-code text-label-micro text-secondary font-semibold">DECISION</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed"><?= htmlspecialchars($dec) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 7. TECHNOLOGY STACK -->
        <section class="flex flex-col gap-space-md bg-surface-container-low p-space-lg rounded-xl border border-outline-variant/10">
          <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Technologies &amp; Libraries</h2>
          <div class="flex flex-wrap gap-space-xs">
            <?php 
              $techs = $project['stack'] ?? ['Go', 'PostgreSQL', 'Redis Streams', 'Docker'];
              foreach ($techs as $tech): 
            ?>
              <span class="px-space-md py-1.5 rounded-lg bg-surface-container font-label-code text-label-code text-on-surface border border-outline-variant/10">
                <?= htmlspecialchars($tech) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 8. GALLERY ATTACHMENTS (IF PRESENT) -->
        <?php if (!empty($galleryImages)): ?>
          <section class="flex flex-col gap-space-md">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Project Gallery &amp; Artifacts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-space-md">
              <?php foreach ($galleryImages as $gImg): ?>
                <div class="aspect-video rounded-lg overflow-hidden bg-surface-container border border-outline-variant/10">
                  <?= responsiveImage($gImg, 'Project artifact', [
                      'class' => 'w-full h-full object-cover',
                      'sizes' => '(max-width: 640px) 100vw, 360px'
                  ]) ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- 9. FOOTER ACTIONS -->
        <div class="pt-space-md border-t border-outline-variant/10 flex flex-col sm:flex-row items-center justify-between gap-space-md">
          <a href="/projects.php" class="px-space-lg py-space-sm rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface font-body-sm font-medium transition-colors flex items-center gap-space-xs">
            <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
            <span>All Projects</span>
          </a>
          <a href="/gallery.php" class="px-space-lg py-space-sm rounded-lg bg-primary text-on-primary font-body-sm font-semibold hover:bg-secondary transition-colors flex items-center gap-space-xs">
            <span>Explore Visual Gallery</span>
            <span class="material-symbols-outlined text-[1.125rem]">arrow_forward</span>
          </a>
        </div>
        <?php
        $relatedItems = [];
        if (isset($pdo) && $project) {
            $resolver = new \Domain\Content\RelatedContentResolver();
            $relatedItems = $resolver->getRelatedProjects($pdo, $project['id'], $project['category'] ?? '', 3);
        }
        require __DIR__ . '/includes/related_content.php';
        ?>
      <?php endif; ?>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  <script src="/assets/js/user_interactions.js"></script>
</body>
</html>
