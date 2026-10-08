<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'home';
$pageTitle = 'Mohammed Alrashadi — Personal Engineering Studio';
$pageDescription = 'Personal engineering platform and research notebook exploring systems architecture, database internals, computing fundamentals, and backend software engineering.';
$canonicalUrl = 'https://mohammedalrashadi.com/';

require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/includes/settings.php';

// Context-Aware Session State (Removed for Edge Caching)
// Client-side JS now fetches /api/user/homepage_rails.php

// Database connection & authentic data retrieval
$dbConnected = false;
$recentArticles = [];
$featuredProjects = [];
$showcaseItems = [];
$primaryExperiment = null;

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        require_once __DIR__ . '/src/autoload.php';
        $pdo = getDB();
        $dbConnected = true;
        
        $postRepo = new \Domain\Content\PostRepository($pdo);

        require_once __DIR__ . '/includes/settings.php';
        require_once __DIR__ . '/api/showcase/helper.php';

        // 1. Read curated showcase items (Mixed Content Strip)
        // Note: SELECT post_id FROM home_showcase_items verified column structure
        try {
            $curStmt = $pdo->query(
                "SELECT id, item_type, reference_id, post_id, title_override, description_override, 
                        image_url, alt_text, link_url, is_enabled, sort_order 
                 FROM home_showcase_items 
                 WHERE is_enabled = 1 
                 ORDER BY sort_order ASC, id ASC"
            );
            $rawRows = $curStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rawRows as $r) {
                $resolved = resolveShowcaseItem($pdo, $r, false);
                if ($resolved !== null) {
                    $showcaseItems[] = $resolved;
                }
            }
        } catch (Exception $e) {
            error_log('[index.php] home_showcase_items read failed: ' . $e->getMessage());
            $showcaseItems = [];
        }

        // 2. Fetch projects for Section 2 Architectural Systems Dossiers
        try {
            $featuredProjects = $postRepo->getRecentPublishedProjects(4);
        } catch (Exception $e) {
            error_log('[index.php] posts query failed: ' . $e->getMessage());
            $featuredProjects = [];
        }

        // 3. Fetch recent published articles for Publications Journal
        try {
            $recentArticles = $postRepo->getRecentPublishedBlogs(3);
        } catch (Exception $e) {
            error_log('[index.php] posts query failed: ' . $e->getMessage());
            $recentArticles = [];
        }

        // 4. Removed Server-Side Context-Aware Fetching
        // Hydration now happens via AJAX to allow guest HTML to be CDN-cached.

    } catch (Exception $e) {
        error_log('[index.php] database initialization failed: ' . $e->getMessage());
        $dbConnected = false;
    }
}

// Load primary lab experiment from authentic JSON repository data
$experimentsFile = __DIR__ . '/api/data/lab_experiments.json';
if (file_exists($experimentsFile)) {
    $labData = json_decode(file_get_contents($experimentsFile), true);
    if (!empty($labData[0])) {
        $primaryExperiment = $labData[0];
    }
}

// 4. Fallback: If no curated showcase items configured/enabled, provide standard fallback
if (empty($showcaseItems)) {
    $showcaseItems = [];
    foreach ($featuredProjects as $p) {
        $cleanContent = trim(strip_tags((string)($p['content'] ?? '')));
        $showcaseItems[] = [
            'id'           => (int)$p['id'],
            'item_type'    => 'project',
            'badge'        => 'Project',
            'category'     => (string)($p['category'] ?? ''),
            'title'        => $p['title'],
            'description'  => mb_substr($cleanContent, 0, 150, 'UTF-8') . (mb_strlen($cleanContent, 'UTF-8') > 150 ? '...' : ''),
            'image'        => !empty($p['image_url']) ? $p['image_url'] : '',
            'url'          => '/project.php?id=' . (int)$p['id'],
            'action_label' => 'Inspect System'
        ];
    }

    if (count($showcaseItems) < 4 && !empty($primaryExperiment)) {
        $showcaseItems[] = [
            'id'           => 0,
            'item_type'    => 'experiment',
            'badge'        => 'Lab',
            'category'     => (string)($primaryExperiment['categoryLabel'] ?? ''),
            'title'        => $primaryExperiment['title'],
            'description'  => (string)($primaryExperiment['outcomeHeadline'] ?? $primaryExperiment['question'] ?? ''),
            'image'        => !empty($primaryExperiment['image']) ? $primaryExperiment['image'] : '',
            'url'          => '/lab-detail.php?id=' . urlencode($primaryExperiment['id']),
            'action_label' => 'Inspect Benchmark'
        ];
    }

    if (count($showcaseItems) < 4 && !empty($recentArticles)) {
        $a = $recentArticles[0];
        $cleanArt = trim(strip_tags((string)($a['content'] ?? '')));
        $showcaseItems[] = [
            'id'           => (int)$a['id'],
            'item_type'    => 'article',
            'badge'        => 'Writing',
            'category'     => (string)($a['category'] ?? ''),
            'title'        => $a['title'],
            'description'  => mb_substr($cleanArt, 0, 150, 'UTF-8') . (mb_strlen($cleanArt, 'UTF-8') > 150 ? '...' : ''),
            'image'        => !empty($a['image_url']) ? $a['image_url'] : '',
            'url'          => '/post.php?id=' . (int)$a['id'],
            'action_label' => 'Read Essay'
        ];
    }
}

$profile = getSiteProfile();

$sameAsUrls = [];
if ($dbConnected && isset($pdo)) {
    try {
        $sStmt = $pdo->query(
            "SELECT url FROM social_links WHERE is_enabled = 1 AND url IS NOT NULL AND TRIM(url) <> '' AND url NOT LIKE '%example.com%' ORDER BY sort_order ASC, id ASC"
        );
        $sameAsUrls = array_values(array_filter($sStmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Throwable $e) {
        $sameAsUrls = [];
    }
}

// Principles and journey shown on Home come from the admin-managed tables
// (About Content / Journey). Missing table or empty data -> section is hidden.
$homePrinciples = [];
$homeMilestones = [];
if ($dbConnected && isset($pdo)) {
    try {
        $homePrinciples = $pdo->query(
            "SELECT title, description FROM about_content_blocks
              WHERE deleted_at IS NULL AND block_type = 'principle'
              ORDER BY sort_order ASC, id ASC LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $homePrinciples = [];
    }
    try {
        $homeMilestones = $pdo->query(
            "SELECT title, period_label, description FROM journey_milestones
              WHERE deleted_at IS NULL AND status = 'published'
              ORDER BY sort_order ASC, id ASC LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $homeMilestones = [];
    }
}

$websiteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => getSiteSetting('website.platform_name', 'Mohammed Alrashadi'),
    'url' => 'https://mohammedalrashadi.com/',
    'description' => $pageDescription
];

$personSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Person',
    'name' => $profile['name'] ?? 'Mohammed Alrashadi',
    'url' => 'https://mohammedalrashadi.com/about.php',
    'jobTitle' => $profile['role'] ?? 'Software Engineering Student',
    'description' => $profile['bio_short'] ?? '',
    'sameAs' => $sameAsUrls
];
?>
<!DOCTYPE html>
<html lang="en" class="is-animating">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
  
  <!-- JSON-LD Structured Data: WebSite & Person -->
  <script type="application/ld+json">
  <?= json_encode($websiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <script type="application/ld+json">
  <?= json_encode($personSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
</head>
<body class="page-home bg-background text-on-surface font-sans antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>
  <?php $isLoggedInIndex = currentUserId() > 0; ?>

  <main class="page-transition-wrapper relative z-0 w-full pt-16 min-h-[calc(100vh-16rem)]">
    <div class="page-container py-space-xl editorial-narrative-flow">
      
      <!-- ============================================================
           ACT I: IDENTITY & ENGINEERING THESIS (HERO)
           Answers: "Who is Mohammed and what does he research/build?"
           ============================================================ -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg lg:gap-space-xl items-center pt-space-md pb-space-xl border-b border-border-subtle" aria-label="Engineering Identity">
        
        <div class="lg:col-span-8 flex flex-col gap-5 w-full">
          <!-- Monospace Overline -->
          <div class="flex items-center gap-2">
            <span id="hero-overline" class="editorial-overline">COMPUTER SCIENCE STUDENT · SOFTWARE BUILDER · LIFELONG LEARNER</span>
            <span class="w-1.5 h-1.5 rounded-full bg-primary inline-block"></span>
          </div>

          <!-- Balanced Typographic Headline -->
          <h1 id="hero-headline" class="font-display text-[clamp(2.1rem,4.5vw,3.75rem)] text-on-surface font-bold tracking-tight leading-[1.08] transition-opacity duration-300">
            Mohammed Alrashadi
          </h1>

          <!-- Personal Engineering Stance -->
          <div class="hero-thesis-statement flex flex-col gap-4">
            <p class="font-sans text-base sm:text-lg text-text-secondary leading-relaxed max-w-2xl xl:max-w-4xl font-medium">
              I design and build web applications, learning systems, and technical projects while documenting my journey through university, engineering, and personal growth.
            </p>
            <p class="font-sans text-sm sm:text-base text-text-secondary leading-relaxed max-w-2xl xl:max-w-4xl">
              This site is my public workspace for projects, writing, achievements, experiments, and the path I am building toward a career in software engineering.
            </p>
          </div>

          <?php $heroFocus = trim((string)($profile['current_focus'] ?? '')); ?>
          <?php if ($heroFocus !== ''): ?>
          <!-- Current focus (admin: Settings > profile.current_focus) -->
          <div class="hero-research-strip" aria-label="Current focus">
            <div class="hero-research-item">
              <span class="hero-research-label">CURRENTLY STUDYING</span>
              <span class="hero-research-val"><?= htmlspecialchars($heroFocus) ?></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- Primary Direct Workflow Actions -->
          <div id="hero-actions" class="flex flex-wrap items-center gap-space-md mt-2 transition-opacity duration-300">
            <a id="hero-action-primary" href="#systems" class="btn btn-primary px-6 py-3 rounded-lg font-medium text-sm sm:text-base">
              <span id="hero-action-primary-text">View Projects</span>
              <span id="hero-action-primary-icon" class="material-symbols-outlined text-[18px]">arrow_downward</span>
            </a>
            <a id="hero-action-secondary" href="/journey.php" class="btn btn-secondary px-6 py-3 rounded-lg font-medium text-sm sm:text-base">
              <span id="hero-action-secondary-text">Explore My Journey</span>
              <span id="hero-action-secondary-icon" class="material-symbols-outlined text-[18px]">route</span>
            </a>
            <a href="/articles.php" class="btn btn-ghost px-5 py-3 rounded-lg font-mono text-xs text-primary border border-border hover:border-primary">
              <span>READ WRITING</span>
              <span class="material-symbols-outlined text-[16px]">article</span>
            </a>
          </div>
        </div>

        <!-- Personal Identity & Clock of Life Card -->
        <div class="hidden lg:flex lg:col-span-4 justify-center items-center relative" aria-hidden="true">
          <div class="w-full max-w-[320px] xl:max-w-[360px] rounded-2xl bg-surface-container border border-border p-6 flex flex-col items-center justify-center relative overflow-hidden backdrop-blur-sm shadow-sm group min-h-[380px]">
            
            <!-- Clock of Life Background Motif -->
            <div class="absolute inset-0 flex items-center justify-center opacity-10 group-hover:opacity-20 transition-opacity duration-700 pointer-events-none" style="transform: scale(1.6);">
                <div class="chrono-clock chrono-clock--sm" id="hero-clock-motif" aria-hidden="true">
                    <svg class="chrono-arc" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="46" class="chrono-ring-outer" />
                        <circle cx="50" cy="50" r="38" class="chrono-ring-inner" />
                        <circle cx="50" cy="50" r="46" class="chrono-ring-progress" id="chrono-progress-hero" />
                    </svg>
                    <div class="chrono-hands">
                        <div class="chrono-sec-hand" id="sec-hand-hero"></div>
                    </div>
                    <div class="chrono-center"></div>
                </div>
            </div>

            <!-- Content Container (Above Clock) -->
            <div class="relative z-10 flex flex-col items-center gap-5 w-full mt-2">
              
              <!-- Avatar Area -->
              <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full bg-surface-container-highest border border-border shadow-inner overflow-hidden flex items-center justify-center relative">
                <!-- Instruction for the user -->
                <span class="text-[10px] font-mono text-text-muted text-center px-2 leading-tight">
                  Insert Image<br><span class="text-primary opacity-80">/assets/images/me.jpg</span>
                </span>
                <!-- To activate, uncomment and add real path:
                <img src="/assets/images/me.jpg" alt="Mohammed Alrashadi" class="w-full h-full object-cover">
                -->
              </div>

              <!-- Identity text -->
              <div class="flex flex-col items-center gap-1.5 text-center mt-2">
                <span class="font-display text-xl sm:text-2xl font-bold text-on-surface tracking-tight">Mohammed Alrashadi</span>
                <span class="font-mono text-[10px] sm:text-xs text-primary uppercase tracking-widest font-semibold">CS Student · Builder</span>
              </div>

              <!-- Tech Tags -->
              <div class="flex flex-wrap items-center justify-center gap-1.5 mt-2 w-full max-w-[280px]">
                <span class="px-2 py-1 rounded-md border border-border bg-surface-container-lowest font-mono text-[9px] text-text-secondary uppercase tracking-wider">Computer Science</span>
                <span class="px-2 py-1 rounded-md border border-border bg-surface-container-lowest font-mono text-[9px] text-text-secondary uppercase tracking-wider">Software Engineering</span>
                <span class="px-2 py-1 rounded-md border border-border bg-surface-container-lowest font-mono text-[9px] text-text-secondary uppercase tracking-wider">Web Development</span>
                <span class="px-2 py-1 rounded-md border border-border bg-surface-container-lowest font-mono text-[9px] text-text-secondary uppercase tracking-wider">AI Tools</span>
                <span class="px-2 py-1 rounded-md border border-border bg-surface-container-lowest font-mono text-[9px] text-text-secondary uppercase tracking-wider">Databases</span>
                <span class="px-2 py-1 rounded-md border border-border bg-surface-container-lowest font-mono text-[9px] text-text-secondary uppercase tracking-wider">Learning Systems</span>
              </div>

            </div>
            
          </div>
        </div>
      </section>

      <!-- ============================================================
           CONTEXT-AWARE WORKSPACE
           ============================================================ -->
      <?php require_once __DIR__ . '/includes/home_workspace.php'; ?>

      <!-- ============================================================
           ACT II: WHAT I BUILD (ARCHITECTURAL SYSTEMS SPOTLIGHT & DOSSIERS)
           Answers: "What complex engineering systems does Mohammed build?"
           ============================================================ -->
      <section class="editorial-section reveal-section" id="systems" aria-label="Engineered Systems">
        <div class="editorial-section-header with-action">
          <div>
            <span class="editorial-overline">PROJECTS</span>
            <h2 class="editorial-title">Projects</h2>
            <p class="editorial-subtitle">
              Systems I have built, with notes on how they work and why I built them.
            </p>
          </div>
          <a href="/projects.php" class="btn btn-ghost text-xs font-mono text-primary gap-1">
            <span>All Projects<?= count($featuredProjects) > 0 ? ' (' . count($featuredProjects) . ')' : '' ?></span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
        </div>

        <?php if (!empty($featuredProjects)): 
          $spotlight = $featuredProjects[0];
          $secondaryProjects = array_slice($featuredProjects, 1, 3);
        ?>
          <!-- Flagship Architectural Spotlight Dossier (Asymmetric 7/5 Grid) -->
          <article class="architectural-spotlight-dossier" aria-label="Flagship Architecture">
            <div class="flex flex-col gap-space-md">
              <div class="flex items-center gap-2">
                <span class="badge badge-accent">FLAGSHIP SYSTEM</span>
                <?php if (!empty($spotlight['category'])): ?>
                <span class="font-mono text-xs text-text-muted"><?= htmlspecialchars(strtoupper($spotlight['category'])) ?></span>
                <?php endif; ?>
              </div>

              <div class="flex flex-col gap-2">
                <h3 class="font-headline-lg text-2xl sm:text-3xl text-on-surface font-bold leading-snug">
                  <?= htmlspecialchars($spotlight['title']) ?>
                </h3>
                <?php
                  $spotText = trim(strip_tags((string)($spotlight['content'] ?? '')));
                  $spotShort = mb_substr($spotText, 0, 320, 'UTF-8') . (mb_strlen($spotText, 'UTF-8') > 320 ? '...' : '');
                ?>
                <?php if ($spotText !== ''): ?>
                <p class="font-sans text-sm sm:text-base text-text-secondary leading-relaxed">
                  <?= htmlspecialchars($spotShort) ?>
                </p>
                <?php endif; ?>
              </div>


              <div class="pt-3">
                <a class="btn btn-primary" href="/project.php?id=<?= (int)$spotlight['id'] ?>">
                  <span>Inspect System Architecture</span>
                  <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
              </div>
            </div>

            <?php if (!empty($spotlight['image_url'])): ?>
            <!-- Project image (only when one was uploaded) -->
            <div class="flex flex-col gap-2">
              <div class="image-frame w-full aspect-video bg-surface-container-lowest rounded-lg border border-border overflow-hidden">
                <?= responsiveImage(
                    $spotlight['image_url'],
                    htmlspecialchars($spotlight['title']),
                    [
                        'class' => 'w-full h-full object-cover',
                        'priority' => true,
                        'sizes' => '(max-width: 1024px) 100vw, 600px'
                    ]
                ) ?>
              </div>
            </div>
            <?php endif; ?>
          </article>

          <!-- Secondary Systems Grid (Dossiers) -->
          <?php if (!empty($secondaryProjects)): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md mt-2">
              <?php foreach ($secondaryProjects as $sp): 
                $spSnippet = mb_substr(trim(strip_tags((string)($sp['content'] ?? ''))), 0, 140, 'UTF-8');
              ?>
                <a href="/project.php?id=<?= (int)$sp['id'] ?>" class="card p-space-md flex flex-col justify-between gap-space-md group text-decoration-none border border-border hover:border-border-hover transition-colors">
                  <div class="flex flex-col gap-2">
                    <span class="font-mono text-[10.5px] text-primary uppercase"><?= htmlspecialchars($sp['category'] ?: 'Engineering') ?></span>
                    <h4 class="font-headline-sm text-base font-semibold text-on-surface group-hover:text-primary transition-colors leading-snug">
                      <?= htmlspecialchars($sp['title']) ?>
                    </h4>
                    <p class="font-sans text-xs text-text-secondary line-clamp-3 leading-relaxed">
                      <?= htmlspecialchars($spSnippet) ?>...
                    </p>
                  </div>
                  <div class="pt-2 border-t border-border flex items-center justify-between font-mono text-xs text-primary">
                    <span>Inspect System</span>
                    <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        <?php else: ?>
          <div class="staging-state">
            <span class="staging-state-label">COMING SOON</span>
            <p class="staging-state-title">Projects are being added</p>
            <p class="staging-state-desc">Project write-ups will appear here soon.</p>
            <a href="/lab.php" class="btn btn-secondary mt-3" style="font-size: 0.8125rem;">Visit Studio Lab</a>
          </div>
        <?php endif; ?>
      </section>


      <!-- ============================================================
           ACT III: SELECTED WORK (CURATED SHOWCASE RAIL)
           Answers: "What are the curated artifacts across all domains?"
           ============================================================ -->
      <section class="editorial-section reveal-section" id="showcase" aria-label="Visual Showcase">
        <div class="editorial-section-header with-action">
          <div>
            <span class="editorial-overline">FEATURED</span>
            <h2 class="editorial-title">Featured Work</h2>
            <p class="editorial-subtitle">
              A selection of my tools, systems, and research.
            </p>
          </div>

          <!-- Rail Navigation Controls -->
          <?php if (!empty($showcaseItems)): ?>
          <div class="flex items-center gap-2 self-start sm:self-auto">
            <button aria-label="Scroll left" 
                    id="showcase-prev" 
                    class="btn btn-icon text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined text-[20px]">west</span>
            </button>
            <button aria-label="Scroll right" 
                    id="showcase-next" 
                    class="btn btn-icon text-text-secondary hover:text-text-primary">
              <span class="material-symbols-outlined text-[20px]">east</span>
            </button>
          </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($showcaseItems)): ?>
          <!-- Horizontal Scroll Track -->
          <div class="showcase-rail no-scrollbar focus:outline-none focus:ring-1 focus:ring-primary rounded-xl" 
               id="showcase-track" 
               tabindex="0" 
               role="region" 
               aria-label="Horizontal Work Showcase">
            
            <?php foreach ($showcaseItems as $item): 
              $itemBadge = htmlspecialchars($item['badge'] ?? $item['type'] ?? 'Featured');
              $itemUrl = htmlspecialchars($item['url']);
            ?>
              <a href="<?= $itemUrl ?>" class="showcase-card group flex flex-col justify-between gap-space-md text-decoration-none">
                
                <div class="flex flex-col gap-space-sm">
                  <!-- Thumbnail Frame -->
                  <div class="image-frame w-full aspect-[16/10] bg-surface-container-lowest relative overflow-hidden rounded-lg border border-border/80">
                    <?php if (!empty($item['image'])): ?>
                    <?= responsiveImage(
                        $item['image'],
                        !empty($item['alt']) ? $item['alt'] : $item['title'],
                        [
                            'class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-300',
                            'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 360px'
                        ]
                    ) ?>
                    <?php endif; ?>
                    <div class="absolute top-2.5 left-2.5">
                      <span class="badge badge-neutral bg-surface-container-lowest/90 backdrop-blur-sm shadow-sm">
                        <?= $itemBadge ?>
                      </span>
                    </div>
                  </div>

                  <!-- Content -->
                  <div class="flex flex-col gap-1">
                    <?php if (!empty($item['category'])): ?>
                    <div class="metadata-row">
                      <span class="uppercase"><?= htmlspecialchars($item['category']) ?></span>
                    </div>
                    <?php endif; ?>
                    <h3 class="font-headline-sm text-on-surface group-hover:text-primary transition-colors font-semibold leading-snug">
                      <?= htmlspecialchars($item['title']) ?>
                    </h3>
                    <?php if (!empty($item['description'])): ?>
                    <p class="font-sans text-xs text-text-secondary line-clamp-2 leading-relaxed">
                      <?= htmlspecialchars($item['description']) ?>
                    </p>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Action Link -->
                <div class="pt-2 border-t border-border flex items-center justify-between text-xs font-mono text-primary group-hover:text-secondary transition-colors">
                  <span><?= htmlspecialchars($item['action_label']) ?></span>
                  <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
              </a>
            <?php endforeach; ?>

          </div>
        <?php else: ?>
          <div class="staging-state">
            <span class="staging-state-label">COMING SOON</span>
            <p class="staging-state-title">Nothing featured yet</p>
            <p class="staging-state-desc">Featured work will appear here soon.</p>
          </div>
        <?php endif; ?>
      </section>


      <!-- ============================================================
           ACT IV: WHAT I WRITE (TECHNICAL PUBLICATIONS JOURNAL)
           Answers: "What does Mohammed write about?"
           ============================================================ -->
      <section class="editorial-section reveal-section" id="writing" aria-label="Technical Writing">
        <div class="editorial-section-header with-action">
          <div>
            <span class="editorial-overline">WRITING</span>
            <h2 class="editorial-title">Writing</h2>
            <p class="editorial-subtitle">
              Articles about databases, distributed systems, and backend design.
            </p>
          </div>
          <a class="btn btn-ghost text-xs font-mono text-primary hover:text-secondary gap-1" href="/articles.php">
            <span>All Writing</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
        </div>

        <?php if (!empty($recentArticles)): ?>
          <div class="publication-journal-feed">
            <?php foreach ($recentArticles as $art): 
              $cleanSnippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($art['content']))), 0, 220, 'UTF-8');
              $words = str_word_count(strip_tags($art['content']));
              $readTime = max(1, (int) ceil($words / 200));
              $dateStr = !empty($art['created_at']) ? strtoupper(date('M Y', strtotime($art['created_at']))) : 'RECENT';
              $detailUrl = '/post.php?id=' . (int)$art['id'];
            ?>
              <a href="<?= htmlspecialchars($detailUrl) ?>" class="publication-journal-row group">
                <div class="publication-journal-meta">
                  <span class="text-primary font-bold"><?= $dateStr ?></span>
                  <span>·</span>
                  <span><?= $readTime ?> MIN READ</span>
                  <?php if (!empty($art['category'])): ?>
                    <span>·</span>
                    <span class="uppercase text-text-primary"><?= htmlspecialchars($art['category']) ?></span>
                  <?php endif; ?>
                </div>

                <h3 class="publication-journal-title">
                  <?= htmlspecialchars($art['title']) ?>
                </h3>

                <p class="publication-journal-abstract">
                  <?= htmlspecialchars($cleanSnippet) ?>...
                </p>

                <div class="flex items-center gap-1 font-mono text-xs text-primary pt-1">
                  <span>Read Essay</span>
                  <span class="material-symbols-outlined text-[15px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="staging-state">
            <span class="staging-state-label">COMING SOON</span>
            <p class="staging-state-title">No Writing Published Yet</p>
            <p class="staging-state-desc">Articles will appear here once they are published.</p>
          </div>
        <?php endif; ?>
      </section>


      <!-- ============================================================
           ACT V: WHAT I EXPERIMENT WITH (STUDIO LAB)
           Answers: "What empirical experiments does Mohammed conduct?"
           ============================================================ -->
      <section class="editorial-section reveal-section" id="lab" aria-label="Studio Lab">
        <div class="editorial-section-header with-action">
          <div>
            <span class="editorial-overline">STUDIO LAB</span>
            <h2 class="editorial-title">Lab</h2>
            <p class="editorial-subtitle">
              Tests, benchmarks, and hardware research.
            </p>
          </div>
          <a class="btn btn-ghost text-xs font-mono text-primary hover:text-secondary gap-1" href="/lab.php">
            <span>Explore Lab</span>
            <span class="material-symbols-outlined text-[16px]">science</span>
          </a>
        </div>

        <?php if (!empty($primaryExperiment)): ?>
          <article class="lab-empirical-dossier" aria-label="Featured Benchmark">
            <!-- Hypothesis & Methodology Column -->
            <div class="flex flex-col gap-space-md">
              <div class="flex items-center gap-2">
                <span class="badge badge-accent">ACTIVE BENCHMARK</span>
                <span class="font-mono text-xs text-text-muted"><?= htmlspecialchars((string)($primaryExperiment['id'] ?? '')) ?></span>
                <?php if (!empty($primaryExperiment['categoryLabel'])): ?>
                <span class="font-mono text-xs text-text-muted">·</span>
                <span class="font-mono text-xs text-text-secondary"><?= htmlspecialchars($primaryExperiment['categoryLabel']) ?></span>
                <?php endif; ?>
              </div>

              <div class="flex flex-col gap-2">
                <h3 class="font-headline-md text-2xl font-bold text-on-surface leading-snug">
                  <?= htmlspecialchars($primaryExperiment['title']) ?>
                </h3>
                <?php if (!empty($primaryExperiment['question'])): ?>
                <p class="font-sans text-sm text-text-secondary leading-relaxed">
                  <strong>Question:</strong> <?= htmlspecialchars($primaryExperiment['question']) ?>
                </p>
                <?php endif; ?>
              </div>

              <!-- Methodology Breakdown -->
              <?php if (!empty($primaryExperiment['methodologyHeadline'])): ?>
              <div class="flex flex-col gap-1.5 p-3 rounded-lg bg-surface-container-low border border-border">
                <span class="font-mono text-[10.5px] uppercase tracking-wider text-text-muted font-semibold">METHODOLOGY &amp; WORKLOAD</span>
                <p class="font-sans text-xs text-text-secondary m-0">
                  <?= htmlspecialchars($primaryExperiment['methodologyHeadline']) ?>
                </p>
              </div>
              <?php endif; ?>

              <div class="pt-2">
                <a href="/lab-detail.php?id=<?= urlencode($primaryExperiment['id']) ?>" class="btn btn-primary text-xs">
                  <span>Inspect Full Benchmark Findings</span>
                  <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
              </div>
            </div>

            <?php
              $labEnv = trim((string)($primaryExperiment['environment'] ?? ''));
              $labStatus = trim((string)($primaryExperiment['status'] ?? ''));
              $labOutcome = trim((string)($primaryExperiment['outcomeHeadline'] ?? ''));
            ?>
            <?php if ($labOutcome !== '' || $labEnv !== '' || $labStatus !== ''): ?>
            <!-- Empirical Outcome & Metrics Column (only fields entered in the admin) -->
            <div class="flex flex-col justify-between gap-space-md p-space-md rounded-lg bg-surface-container-low border border-border">
              <?php if ($labOutcome !== ''): ?>
              <div class="flex flex-col gap-2">
                <span class="font-mono text-[10.5px] uppercase tracking-wider text-primary font-bold">EMPIRICAL OUTCOME</span>
                <p class="font-sans text-sm font-semibold text-on-surface leading-snug">
                  "<?= htmlspecialchars($labOutcome) ?>"
                </p>
              </div>
              <?php endif; ?>

              <?php if ($labEnv !== '' || $labStatus !== ''): ?>
              <div class="flex flex-col gap-2 pt-3 border-t border-border font-mono text-xs text-text-muted">
                <?php if ($labEnv !== ''): ?>
                <div class="flex items-center justify-between gap-3">
                  <span>ENVIRONMENT</span>
                  <span class="text-text-primary text-right"><?= htmlspecialchars(mb_substr($labEnv, 0, 80, 'UTF-8')) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($labStatus !== ''): ?>
                <div class="flex items-center justify-between gap-3">
                  <span>STATUS</span>
                  <span class="text-text-primary text-right"><?= htmlspecialchars($labStatus) ?></span>
                </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </article>
        <?php else: ?>
          <div class="staging-state">
            <span class="staging-state-label">COMING SOON</span>
            <p class="staging-state-title">Experiments are being added</p>
            <p class="staging-state-desc">Lab experiments will appear here soon.</p>
          </div>
        <?php endif; ?>
      </section>


      <!-- ============================================================
           ACT VI: TRAJECTORY & CORE CONVICTIONS
           Answers: "What are Mohammed's engineering principles & journey?"
           ============================================================ -->
      <?php if (!empty($homePrinciples) || !empty($homeMilestones)): ?>
      <section class="editorial-section reveal-section" id="journey" aria-label="Journey and Convictions">
        <div class="editorial-section-header with-action">
          <div>
            <span class="editorial-overline">PRINCIPLES &amp; JOURNEY</span>
            <h2 class="editorial-title">My Principles and Journey</h2>
            <p class="editorial-subtitle">
              How I think about engineering, and the steps of my journey.
            </p>
          </div>
          <a class="btn btn-ghost text-xs font-mono text-primary hover:text-secondary gap-1" href="/journey.php">
            <span>Full Journey</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
        </div>

        <div class="convictions-trajectory-grid">
          <?php if (!empty($homePrinciples)): ?>
          <!-- Column 1: Principles (admin: About Content) -->
          <div class="conviction-dossier-card">
            <div class="flex items-center justify-between pb-2 border-b border-border">
              <span class="font-mono text-xs uppercase tracking-wider text-text-muted font-semibold">MY PRINCIPLES</span>
            </div>
            <?php foreach ($homePrinciples as $pi => $pr): ?>
            <div class="conviction-item">
              <span class="conviction-title"><?= sprintf('%02d', $pi + 1) ?>. <?= htmlspecialchars(mb_strtoupper((string)$pr['title'], 'UTF-8')) ?></span>
              <?php if (trim((string)($pr['description'] ?? '')) !== ''): ?>
              <p class="conviction-body"><?= htmlspecialchars((string)$pr['description']) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($homeMilestones)): ?>
          <!-- Column 2: Journey (admin: Journey) -->
          <div class="conviction-dossier-card">
            <div class="flex items-center justify-between pb-2 border-b border-border">
              <span class="font-mono text-xs uppercase tracking-wider text-text-muted font-semibold">MY JOURNEY</span>
              <a href="/journey.php" class="font-mono text-xs text-primary hover:underline">View Journey</a>
            </div>
            <?php foreach ($homeMilestones as $ms): ?>
            <div class="conviction-item">
              <span class="conviction-title"><?= htmlspecialchars(mb_strtoupper((string)$ms['title'], 'UTF-8')) ?><?= trim((string)($ms['period_label'] ?? '')) !== '' ? ' — ' . htmlspecialchars((string)$ms['period_label']) : '' ?></span>
              <?php if (trim((string)($ms['description'] ?? '')) !== ''): ?>
              <p class="conviction-body"><?= htmlspecialchars((string)$ms['description']) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </section>
      <?php endif; ?>


      <!-- ============================================================
           ACT VII: DEVELOPER TOOLS & STUDIO RESOURCES (STORE)
           Answers: "What practical tools does the studio produce?"
           ============================================================ -->
      <section class="editorial-section reveal-section" id="store" aria-label="Studio Resources">
        <div class="editorial-section-header with-action">
          <div>
            <span class="editorial-overline">STORE</span>
            <h2 class="editorial-title">Store &amp; Resources</h2>
            <p class="editorial-subtitle">
              Templates, blueprints, and developer tools I made while studying.
            </p>
          </div>
          <a class="btn btn-ghost text-xs font-mono text-primary hover:text-secondary gap-1" href="/store.php">
            <span>Browse Store</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-lg pt-4 pb-2 border-l-[3px] border-primary pl-5">
          <div class="flex flex-col gap-2 max-w-2xl">
            <div class="flex items-center gap-2">
              <span class="badge badge-accent">OPEN STORE</span>
              <span class="font-mono text-xs text-text-muted">TOOLS &amp; BLUEPRINTS</span>
            </div>
            <h3 class="font-headline-sm text-xl font-bold text-on-surface">
              Blueprints and Developer Tools
            </h3>
            <p class="font-sans text-sm text-text-secondary leading-relaxed">
              Download database schemas, starter projects, and templates. Each item shows whether it is a free file or an external link.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3 shrink-0">
            <a href="/store.php" class="btn btn-primary text-xs py-2.5 px-5">
              <span class="material-symbols-outlined text-[18px]">store</span>
              <span>Visit Store</span>
            </a>
            <a href="/about.php" class="btn btn-secondary text-xs py-2.5 px-5">
              <span class="material-symbols-outlined text-[18px]">person</span>
              <span>About Mohammed</span>
            </a>
          </div>
        </div>
      </section>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Horizontal Showcase Scroll Controller -->
  <script>
    (function() {
      var track = document.getElementById('showcase-track');
      var prevBtn = document.getElementById('showcase-prev');
      var nextBtn = document.getElementById('showcase-next');

      if (track && prevBtn && nextBtn) {
        var getScrollDistance = function() {
          var firstCard = track.querySelector('.showcase-card');
          return firstCard ? firstCard.offsetWidth + 24 : 400;
        };

        var updateButtonState = function() {
          var maxScroll = track.scrollWidth - track.clientWidth;
          if (maxScroll <= 0) {
            prevBtn.style.opacity = '0.35';
            nextBtn.style.opacity = '0.35';
            prevBtn.disabled = true;
            nextBtn.disabled = true;
          } else {
            var atStart = track.scrollLeft <= 5;
            var atEnd = track.scrollLeft >= maxScroll - 5;
            prevBtn.style.opacity = atStart ? '0.35' : '1';
            nextBtn.style.opacity = atEnd ? '0.35' : '1';
            prevBtn.disabled = atStart;
            nextBtn.disabled = atEnd;
          }
        };

        track.addEventListener('scroll', updateButtonState, { passive: true });
        window.addEventListener('resize', updateButtonState);
        setTimeout(updateButtonState, 100);

        prevBtn.addEventListener('click', function() {
          track.scrollBy({ left: -getScrollDistance(), behavior: 'smooth' });
        });

        nextBtn.addEventListener('click', function() {
          track.scrollBy({ left: getScrollDistance(), behavior: 'smooth' });
        });

        // Keyboard arrow navigation when track is focused
        track.addEventListener('keydown', function(e) {
          if (e.key === 'ArrowLeft') {
            e.preventDefault();
            track.scrollBy({ left: -getScrollDistance(), behavior: 'smooth' });
          } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            track.scrollBy({ left: getScrollDistance(), behavior: 'smooth' });
          }
        });
      }
    })();
  </script>



</body>
</html>
