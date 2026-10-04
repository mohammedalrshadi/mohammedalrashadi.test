<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'about';
require_once __DIR__ . '/includes/settings.php';
$profile = getSiteProfile();

$pageTitle = 'About';
$pageDescription = $profile['bio_short'];
$canonicalUrl = 'https://mohammedalrashadi.com/about.php';

// Database query with fallback — Principles cards + Focus Area tags,
// admin-editable via Control Center → About Content. If the table
// doesn't exist yet (migration not applied) or the query fails, these
// fall back to the site's original hardcoded defaults below, so the
// page never breaks or renders empty sections.
$aboutPrinciples = [];
$aboutFocusGroups = [];
if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        $pdo = getDB();
        $stmt = $pdo->query(
            "SELECT id, block_type, group_label, icon, title, description, sort_order
               FROM about_content_blocks
              WHERE deleted_at IS NULL
              ORDER BY block_type ASC, group_label ASC, sort_order ASC, id ASC"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['block_type'] === 'principle') {
                $aboutPrinciples[] = $row;
            } else {
                $group = $row['group_label'] ?: 'Other';
                $aboutFocusGroups[$group][] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('[about.php] content blocks query failed: ' . $e->getMessage());
        $aboutPrinciples = [];
        $aboutFocusGroups = [];
    }
}

// Default icon per principle position, used only if an admin adds a
// principle without picking an icon (keeps the original visual rhythm).
$principleIconFallbacks = ['shield', 'analytics', 'layers'];
$sameAsUrls = [];
if (isset($pdo)) {
    try {
        $sStmt = $pdo->query(
            "SELECT url FROM social_links WHERE is_enabled = 1 AND url IS NOT NULL AND TRIM(url) <> '' AND url NOT LIKE '%example.com%' ORDER BY sort_order ASC, id ASC"
        );
        $sameAsUrls = array_values(array_filter($sStmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Throwable $e) {
        $sameAsUrls = [];
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
<body class="bg-background text-on-surface font-sans antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="page-transition-wrapper relative z-0 w-full pt-16 min-h-[calc(100vh-16rem)]">
    <div class="page-container py-space-xl flex flex-col section-spacing-lg">
      
      <!-- ============================================================
           1. PROFILE & BIOGRAPHY SECTION
           ============================================================ -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl items-start" aria-label="Personal Profile and Biography">
        
        <!-- Left: Profile Image Card & Facts (5 cols) -->
        <div class="lg:col-span-5 flex flex-col gap-space-lg">
            
            <!-- Portrait Image -->
            <div class="image-frame w-full aspect-[4/4.5] bg-surface-container-lowest" style="border-radius: var(--radius-lg);">
              <?php
                $avatarSrc = htmlspecialchars($profile['avatar_url']);
                $avatarFile = __DIR__ . '/' . ltrim($profile['avatar_url'], '/');
                $avatarExists = !empty($profile['avatar_url']) && file_exists($avatarFile) && $profile['avatar_url'] !== '/assets/profile_headshot.png';
              ?>
              <?php if ($avatarExists): ?>
                <img alt="<?= htmlspecialchars($profile['name']) ?> profile headshot" 
                     class="w-full h-full object-cover object-center" 
                     src="<?= $avatarSrc ?>" decoding="async" fetchpriority="high" />
              <?php else: ?>
                <!-- Elegant SVG Monogram — editorial identity placeholder -->
                <div style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--color-surface-container-low);gap:0.75rem;">
                  <svg width="72" height="72" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="72" height="72" rx="4" fill="var(--color-primary-container)"/>
                    <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle"
                          font-family="'JetBrains Mono', monospace" font-size="28" font-weight="700"
                          fill="var(--color-on-primary-container)" letter-spacing="-1">MA</text>
                  </svg>
                  <span style="font-family:'JetBrains Mono',monospace;font-size:10px;text-transform:uppercase;letter-spacing:0.1em;color:var(--color-text-muted);">MOHAMMED ALRASHADI</span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Profile Summary Facts -->
            <div class="bg-surface-container p-space-md rounded-lg flex flex-col gap-space-sm border border-border">
              <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="font-mono text-xs text-text-muted uppercase">Role</span>
                <span class="font-sans text-sm font-medium text-on-surface"><?= htmlspecialchars($profile['role']) ?></span>
              </div>
              <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="font-mono text-xs text-text-muted uppercase">Current Focus</span>
                <span class="font-mono text-xs text-primary font-medium"><?= htmlspecialchars($profile['current_focus']) ?></span>
              </div>
              <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="font-mono text-xs text-text-muted uppercase">Location</span>
                <span class="font-sans text-sm text-text-secondary"><?= htmlspecialchars($profile['location']) ?></span>
              </div>
              <?php if (!empty($profile['show_email']) && !empty($profile['public_email'])): ?>
              <div class="flex items-center justify-between pb-2 border-b border-border">
                <span class="font-mono text-xs text-text-muted uppercase">Public Email</span>
                <a href="mailto:<?= htmlspecialchars($profile['public_email']) ?>" class="font-mono text-xs text-primary hover:underline"><?= htmlspecialchars($profile['public_email']) ?></a>
              </div>
              <?php endif; ?>
              <div class="flex items-center justify-between">
                <span class="font-mono text-xs text-text-muted uppercase">Motto</span>
                <span class="font-mono text-xs text-primary font-medium">"<?= htmlspecialchars($profile['motto']) ?>"</span>
              </div>
            </div>

        </div>

        <!-- Right: Narrative Biography & Philosophy (7 cols) -->
        <div class="lg:col-span-7 flex flex-col gap-space-lg">
          
          <div class="flex flex-col gap-space-sm">
            <div class="flex items-center gap-2">
              <span class="badge badge-accent">Profile</span>
              <span class="text-text-muted font-mono text-xs hidden sm:inline">•</span>
              <span class="font-mono text-xs text-text-muted hidden sm:inline">Software Engineering</span>
            </div>

            <div class="flex flex-col gap-1">
              <h1 class="font-headline-lg text-4xl lg:text-5xl text-on-surface font-bold tracking-tight">
                <?= htmlspecialchars($profile['name']) ?>
              </h1>
              <p class="font-mono text-sm text-primary font-medium tracking-wide mt-1">
                "<?= htmlspecialchars($profile['motto']) ?>"
              </p>
            </div>

            <div class="flex flex-col gap-space-md text-text-secondary text-base md:text-lg leading-relaxed max-w-2xl pt-2">
              <?php 
                $bioParagraphs = explode("\n\n", $profile['bio_full']);
                foreach ($bioParagraphs as $bp):
                  $bp = trim($bp);
                  if ($bp !== ''):
              ?>
                <p><?= nl2br(htmlspecialchars($bp)) ?></p>
              <?php 
                  endif;
                endforeach; 
              ?>
            </div>
          </div>

          <!-- Platform Purpose Box -->
          <div class="pt-4 border-t border-border flex flex-col gap-2">
            <span class="font-mono text-xs text-primary uppercase tracking-wider font-semibold">Platform Purpose</span>
            <p class="font-sans text-sm text-text-secondary leading-relaxed">
              <?= htmlspecialchars(getSiteSetting('website.platform_purpose')) ?>
            </p>
          </div>

          <!-- Action Links -->
          <div class="flex flex-wrap items-center gap-space-sm pt-2">
            <a href="/projects.php" class="btn btn-primary">
              <span>Explore Projects</span>
              <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </a>
            <a href="/lab.php" class="btn btn-secondary">
              <span>Studio Lab</span>
              <span class="material-symbols-outlined text-[18px]">science</span>
            </a>
            <a href="/journey.php" class="btn btn-ghost">
              <span>Learning Journey</span>
              <span class="material-symbols-outlined text-[18px]">timeline</span>
            </a>
          </div>

        </div>

      </section>


      <!-- ============================================================
           2. CORE ENGINEERING PRINCIPLES
           ============================================================ -->
      <section class="flex flex-col gap-space-md" aria-label="Core Engineering Principles">
        <div class="flex flex-col gap-1">
          <span class="font-mono text-xs text-primary uppercase tracking-wider font-semibold">Philosophy</span>
          <h2 class="font-headline-lg text-headline-lg text-on-surface font-semibold">Engineering Principles</h2>
          <p class="font-sans text-sm text-text-secondary max-w-xl leading-relaxed">
            Core tenets that guide my approach to software design, experimentation, and system architecture.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
          
          <?php if (!empty($aboutPrinciples)): ?>
            <?php foreach ($aboutPrinciples as $i => $p): ?>
            <div class="flex flex-col gap-space-xs border-t border-border pt-4">
              <div class="flex items-center gap-2 <?= $i % 2 === 0 ? 'text-primary' : 'text-secondary' ?> mb-1">
                <span class="material-symbols-outlined text-[20px]"><?= htmlspecialchars($p['icon'] ?: ($principleIconFallbacks[$i % 3] ?? 'star')) ?></span>
                <h3 class="font-headline-sm text-base text-on-surface font-semibold"><?= htmlspecialchars($p['title']) ?></h3>
              </div>
              <p class="font-sans text-sm text-text-secondary leading-relaxed">
                <?= htmlspecialchars($p['description'] ?? '') ?>
              </p>
            </div>
            <?php endforeach; ?>
          <?php else: ?>

          <div class="flex flex-col gap-space-xs border-t border-border pt-4">
            <div class="flex items-center gap-2 text-primary mb-1">
              <span class="material-symbols-outlined text-[20px]">shield</span>
              <h3 class="font-headline-sm text-base text-on-surface font-semibold">Resilience First</h3>
            </div>
            <p class="font-sans text-sm text-text-secondary leading-relaxed">
              Design software assuming that network splits, process crashes, and retries are inevitable. Systems should degrade gracefully under stress.
            </p>
          </div>

          <div class="flex flex-col gap-space-xs border-t border-border pt-4">
            <div class="flex items-center gap-2 text-secondary mb-1">
              <span class="material-symbols-outlined text-[20px]">analytics</span>
              <h3 class="font-headline-sm text-base text-on-surface font-semibold">Empirical Rigor</h3>
            </div>
            <p class="font-sans text-sm text-text-secondary leading-relaxed">
              Measure rather than assume. Validate architectural decisions through reproducible benchmarks, profiling, and controlled test environments.
            </p>
          </div>

          <div class="flex flex-col gap-space-xs border-t border-border pt-4">
            <div class="flex items-center gap-2 text-primary mb-1">
              <span class="material-symbols-outlined text-[20px]">layers</span>
              <h3 class="font-headline-sm text-base text-on-surface font-semibold">Deep Clarity</h3>
            </div>
            <p class="font-sans text-sm text-text-secondary leading-relaxed">
              Keep surfaces clean and intuitive, while giving technical collaborators full visibility into underlying mechanisms and trade-offs.
            </p>
          </div>
          <?php endif; ?>

        </div>
      </section>


      <!-- ============================================================
           3. ENGINEERING FOCUS & EXPLORATION
           ============================================================ -->
      <section class="flex flex-col gap-space-lg border-t border-border pt-space-xl" aria-label="Engineering Interests and Tooling">
        <div class="flex flex-col gap-1">
          <span class="font-mono text-xs text-primary uppercase tracking-wider font-semibold">Technical Exploration</span>
          <h2 class="font-headline-lg text-headline-lg text-on-surface font-semibold">Focus Areas &amp; Tooling</h2>
          <p class="font-sans text-sm text-text-secondary max-w-xl leading-relaxed">
            Domains, architectures, and technologies explored through coursework, independent study, and hands-on projects.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-space-lg pt-space-xs">
          
          <?php if (!empty($aboutFocusGroups)): ?>
            <?php foreach ($aboutFocusGroups as $groupLabel => $tags): ?>
            <div class="flex flex-col gap-space-xs">
              <span class="font-mono text-xs text-text-muted uppercase tracking-wider font-semibold"><?= htmlspecialchars($groupLabel) ?></span>
              <div class="flex flex-wrap gap-1.5 pt-1">
                <?php foreach ($tags as $tag): ?>
                <span class="tag badge-neutral"><?= htmlspecialchars($tag['title']) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>

          <!-- Category 1: Languages & Foundations -->
          <div class="flex flex-col gap-space-xs">
            <span class="font-mono text-xs text-text-muted uppercase tracking-wider font-semibold">Languages &amp; Core</span>
            <div class="flex flex-wrap gap-1.5 pt-1">
              <span class="tag badge-neutral">Go</span>
              <span class="tag badge-neutral">C++</span>
              <span class="tag badge-neutral">SQL</span>
              <span class="tag badge-neutral">Python</span>
              <span class="tag badge-neutral">PHP</span>
              <span class="tag badge-neutral">JavaScript</span>
            </div>
          </div>

          <!-- Category 2: Databases & Storage -->
          <div class="flex flex-col gap-space-xs">
            <span class="font-mono text-xs text-text-muted uppercase tracking-wider font-semibold">Databases &amp; Storage</span>
            <div class="flex flex-wrap gap-1.5 pt-1">
              <span class="tag badge-neutral">MySQL / InnoDB</span>
              <span class="tag badge-neutral">B-Tree Indexing</span>
              <span class="tag badge-neutral">Hash Indexes</span>
              <span class="tag badge-neutral">Storage Engines</span>
              <span class="tag badge-neutral">Key-Value Lookups</span>
            </div>
          </div>

          <!-- Category 3: Systems & Architecture -->
          <div class="flex flex-col gap-space-xs">
            <span class="font-mono text-xs text-text-muted uppercase tracking-wider font-semibold">Systems &amp; Architecture</span>
            <div class="flex flex-wrap gap-1.5 pt-1">
              <span class="tag badge-neutral">Distributed Systems</span>
              <span class="tag badge-neutral">Database Internals</span>
              <span class="tag badge-neutral">Concurrency &amp; Runtimes</span>
              <span class="tag badge-neutral">Systems Architecture</span>
              <span class="tag badge-neutral">Empirical Benchmarks</span>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </section>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
