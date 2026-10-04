<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'journey';
  $pageTitle = 'Journey';
  $pageDescription = 'Journey — Architectural Evolution (2021 to Present). How I\'ve been learning and evolving as an engineer. A record of systems benchmarks and shifts.';
  $canonicalUrl = 'https://mohammedalrashadi.com/journey.php';

  // Database query with fallback — matches the pattern used by
  // articles.php / projects.php. If journey_milestones doesn't exist
  // yet (migration not applied) or the query fails, $milestones stays
  // empty and the page falls back to its original honest empty state.
  $milestones = [];
  if (file_exists(__DIR__ . '/api/config.local.php')) {
      try {
          require_once __DIR__ . '/api/db.php';
          $pdo = getDB();
          $stmt = $pdo->query(
              "SELECT id, title, period_label, description, category, icon, sort_order
                 FROM journey_milestones
                WHERE deleted_at IS NULL AND status = 'published'
                ORDER BY sort_order ASC, id ASC"
          );
          $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
          error_log('[journey.php] milestones query failed: ' . $e->getMessage());
          $milestones = [];
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

  <main class="page-transition-wrapper relative z-0 w-full pt-16 bg-surface min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">

      <div class="relative w-full max-w-[1600px] mx-auto px-gutter py-space-xl">
        <!-- Editorial Header Section -->
        <header class="flex flex-col gap-space-sm max-w-[820px]">
          <div class="flex items-center gap-space-xs">
            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
            <span class="font-label-micro text-label-micro uppercase tracking-widest text-primary font-semibold">
              ENGINEERING TIMELINE &amp; MILESTONES — 2021 TO PRESENT
            </span>
          </div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight font-bold">
            Journey &amp; Architectural Evolution
          </h1>
          <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed">
            How I have been learning, building, and evolving as a software engineer. A record of deliberate intellectual shifts, systems benchmarks, and distributed invariants.
          </p>
          <div class="pt-space-xs">
            <blockquote class="font-label-code text-label-code text-primary italic">
              “Simple at the surface. Deep underneath.”
            </blockquote>
          </div>
        </header>

        <?php if (empty($milestones)): ?>
        <!-- Honest Empty State -->
        <section class="mt-space-xl bg-surface-container-low rounded-xl p-space-2xl text-center flex flex-col items-center justify-center gap-space-md border border-outline-variant/10 py-20">
          <div class="p-space-lg rounded-full bg-surface-container border border-outline-variant/10 text-primary">
            <span class="material-symbols-outlined text-[3rem]">timeline</span>
          </div>
          <h2 class="font-headline-md text-headline-md text-on-surface font-semibold">No Journey Milestones Published Yet</h2>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-lg leading-relaxed">
            The engineering timeline, architectural shifts, and system milestone trajectory are currently being curated. Check back soon for chronological retrospective notes.
          </p>
          <div class="flex flex-wrap items-center justify-center gap-space-sm pt-space-xs">
            <a href="/projects.php" class="px-space-md py-space-xs rounded-lg bg-primary text-on-primary font-body-sm text-body-sm font-semibold hover:opacity-90 transition-all inline-flex items-center gap-2">
              <span>Explore Projects</span>
              <span class="material-symbols-outlined text-[1rem]">arrow_forward</span>
            </a>
            <a href="/articles.php" class="px-space-md py-space-xs rounded-lg bg-surface-container text-on-surface-variant hover:text-on-surface font-body-sm text-body-sm font-medium transition-all">
              <span>Read Writing Archive</span>
            </a>
          </div>
        </section>
        <?php else: ?>
        <!-- Milestone Timeline -->
        <section class="mt-space-xl relative flex flex-col gap-space-xl max-w-[820px]">
          <div class="absolute left-[20px] -translate-x-1/2 top-4 bottom-4 w-px bg-outline-variant/30" aria-hidden="true"></div>

          <?php foreach ($milestones as $m): ?>
          <article class="relative flex gap-space-md pl-0">
            <div class="relative z-10 flex-shrink-0 w-10 h-10 rounded-full bg-surface-container-low border border-outline-variant/20 flex items-center justify-center text-primary">
              <span class="material-symbols-outlined text-[1.1rem]"><?= htmlspecialchars($m['icon'] ?: 'timeline') ?></span>
            </div>
            <div class="flex-1 bg-surface-container-low rounded-xl border border-outline-variant/10 p-space-lg">
              <div class="flex flex-wrap items-center gap-space-xs mb-1">
                <span class="font-label-code text-label-code text-primary font-semibold uppercase tracking-wide"><?= htmlspecialchars($m['period_label']) ?></span>
                <span class="text-on-surface-variant text-xs">•</span>
                <span class="text-xs text-on-surface-variant capitalize"><?= htmlspecialchars(str_replace('_', ' ', $m['category'])) ?></span>
              </div>
              <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold mb-space-xs"><?= htmlspecialchars($m['title']) ?></h2>
              <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed"><?= nl2br(htmlspecialchars($m['description'])) ?></p>
            </div>
          </article>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>

</html>