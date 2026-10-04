<?php
// ============================================================
// USER DASHBOARD — OVERVIEW
// dashboard/index.php
// ============================================================

$activeNav = 'overview';
$pageTitle = 'Dashboard Overview';

require_once __DIR__ . '/partials/layout_top.php';
require_once dirname(__DIR__) . '/api/user/content_resolver.php';

$userId = (int)$currentUser['id'];

$bookmarksCount = 0;
$libraryCount   = 0;
$downloadsCount = 0;
$likesCount     = 0;
$recentlyViewed = [];
$recentActivity = [];

try {
    $pdo = getDB();

    // 1. Fetch counts
    $bStmt = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE user_id = ?");
    $bStmt->execute([$userId]);
    $bookmarksCount = (int)$bStmt->fetchColumn();

    $lStmt = $pdo->prepare("SELECT COUNT(*) FROM user_library WHERE user_id = ?");
    $lStmt->execute([$userId]);
    $libraryCount = (int)$lStmt->fetchColumn();

    $dStmt = $pdo->prepare("SELECT COUNT(*) FROM user_downloads WHERE user_id = ?");
    $dStmt->execute([$userId]);
    $downloadsCount = (int)$dStmt->fetchColumn();

    $kStmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ?");
    $kStmt->execute([$userId]);
    $likesCount = (int)$kStmt->fetchColumn();

    // 2. Fetch recently viewed items
    $hStmt = $pdo->prepare(
        "SELECT content_type, content_id, last_viewed_at 
         FROM reading_history 
         WHERE user_id = ? 
         ORDER BY last_viewed_at DESC 
         LIMIT 4"
    );
    $hStmt->execute([$userId]);
    $histRows = $hStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($histRows as $hr) {
        $meta = resolveUserContent($pdo, $hr['content_type'], $hr['content_id']);
        if (!$meta['is_missing']) {
            $recentlyViewed[] = array_merge($hr, ['item' => $meta]);
        }
    }

    // 3. Fetch recent real activity
    $aStmt = $pdo->prepare(
        "SELECT id, activity_type, description, created_at 
         FROM user_activities 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 6"
    );
    $aStmt->execute([$userId]);
    $recentActivity = $aStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('[dashboard/overview] DB query error: ' . $e->getMessage());
}
?>

<div class="flex flex-col gap-6">

  <!-- Welcome Banner -->
  <div class="card p-6 sm:p-8 rounded-2xl border border-border/80 bg-surface flex flex-col md:flex-row md:items-center justify-between gap-6 relative overflow-hidden">
    <div class="flex flex-col gap-2 relative z-10">
      <span class="font-mono text-xs text-primary uppercase tracking-widest font-semibold">User Workspace</span>
      <h1 class="font-headline-md text-2xl sm:text-3xl font-bold text-on-surface">
        Welcome back, <?= htmlspecialchars($currentUser['name'] ?: 'Member') ?>
      </h1>
      <p class="font-sans text-sm text-text-secondary max-w-xl leading-relaxed">
        Access your saved research, continue reading technical essays, and manage your claimed digital tools.
      </p>
    </div>
    
    <div class="flex items-center gap-3 relative z-10 self-start md:self-auto">
      <a href="/dashboard/library.php" class="btn btn-primary text-xs py-2 px-4">
        <span class="material-symbols-outlined text-[16px]">folder_special</span>
        <span>My Library</span>
      </a>
      <a href="/dashboard/bookmarks.php" class="btn btn-secondary text-xs py-2 px-4">
        <span class="material-symbols-outlined text-[16px]">bookmark</span>
        <span>Bookmarks</span>
      </a>
    </div>
  </div>

  <!-- Quick Access Metric Cards -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    
    <a href="/dashboard/library.php" class="card p-4 rounded-xl border border-border/60 hover:border-primary/40 transition-colors flex flex-col gap-1">
      <div class="flex items-center justify-between text-text-muted">
        <span class="font-mono text-xs uppercase tracking-wider">Library</span>
        <span class="material-symbols-outlined text-[18px]">folder_special</span>
      </div>
      <div class="font-mono text-2xl font-bold text-on-surface"><?= $libraryCount ?></div>
      <span class="font-sans text-[11px] text-text-secondary">Claimed resources</span>
    </a>

    <a href="/dashboard/bookmarks.php" class="card p-4 rounded-xl border border-border/60 hover:border-primary/40 transition-colors flex flex-col gap-1">
      <div class="flex items-center justify-between text-text-muted">
        <span class="font-mono text-xs uppercase tracking-wider">Bookmarks</span>
        <span class="material-symbols-outlined text-[18px]">bookmark</span>
      </div>
      <div class="font-mono text-2xl font-bold text-on-surface"><?= $bookmarksCount ?></div>
      <span class="font-sans text-[11px] text-text-secondary">Saved items</span>
    </a>

    <a href="/dashboard/downloads.php" class="card p-4 rounded-xl border border-border/60 hover:border-primary/40 transition-colors flex flex-col gap-1">
      <div class="flex items-center justify-between text-text-muted">
        <span class="font-mono text-xs uppercase tracking-wider">Downloads</span>
        <span class="material-symbols-outlined text-[18px]">download</span>
      </div>
      <div class="font-mono text-2xl font-bold text-on-surface"><?= $downloadsCount ?></div>
      <span class="font-sans text-[11px] text-text-secondary">Downloaded files</span>
    </a>

    <a href="/dashboard/likes.php" class="card p-4 rounded-xl border border-border/60 hover:border-primary/40 transition-colors flex flex-col gap-1">
      <div class="flex items-center justify-between text-text-muted">
        <span class="font-mono text-xs uppercase tracking-wider">Likes</span>
        <span class="material-symbols-outlined text-[18px]">favorite</span>
      </div>
      <div class="font-mono text-2xl font-bold text-on-surface"><?= $likesCount ?></div>
      <span class="font-sans text-[11px] text-text-secondary">Liked content</span>
    </a>

  </div>

  <!-- Two Column Content Grid: Recently Viewed & Activity -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
    
    <!-- Recently Viewed Section (7 cols) -->
    <div class="lg:col-span-7 flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-[20px]">history</span>
          <h2 class="font-headline-sm text-base font-semibold text-on-surface">Recently Viewed</h2>
        </div>
        <?php if (!empty($recentlyViewed)): ?>
          <a href="/dashboard/history.php" class="font-mono text-xs text-primary hover:underline">View All</a>
        <?php endif; ?>
      </div>

      <?php if (empty($recentlyViewed)): ?>
        <div class="card p-8 rounded-xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-2">
          <span class="material-symbols-outlined text-3xl text-text-muted">history_toggle_off</span>
          <h3 class="font-semibold text-sm text-on-surface">No reading history yet</h3>
          <p class="font-sans text-xs text-text-secondary max-w-sm">
            Technical essays, projects, benchmarks, and products you explore will appear here so you can easily continue reading.
          </p>
        </div>
      <?php else: ?>
        <div class="flex flex-col gap-3">
          <?php foreach ($recentlyViewed as $rv): 
            $item = $rv['item'];
          ?>
            <div class="card p-3 sm:p-4 rounded-xl border border-border/60 flex items-center justify-between gap-4 hover:border-border transition-colors">
              <div class="flex items-center gap-3 overflow-hidden">
                <img loading="lazy" decoding="async" src="<?= htmlspecialchars($item['image']) ?>" 
                     alt="<?= htmlspecialchars(!empty($item['title']) ? $item['title'] : 'Recently viewed item') ?>" 
                     class="w-12 h-12 rounded-lg object-cover bg-surface-container flex-shrink-0 border border-border/40">
                <div class="flex flex-col overflow-hidden">
                  <div class="flex items-center gap-2">
                    <span class="badge badge-neutral text-[10px] py-0 px-1.5"><?= htmlspecialchars($item['type_badge']) ?></span>
                    <span class="font-mono text-[10px] text-text-muted"><?= htmlspecialchars($item['category']) ?></span>
                  </div>
                  <h4 class="font-medium text-xs sm:text-sm text-on-surface truncate mt-0.5">
                    <?= htmlspecialchars($item['title']) ?>
                  </h4>
                  <span class="font-mono text-[10px] text-text-muted">
                    <?= date('M j, Y — g:i a', strtotime($rv['last_viewed_at'])) ?>
                  </span>
                </div>
              </div>

              <a href="<?= htmlspecialchars($item['url']) ?>" class="btn btn-secondary text-xs px-3 py-1.5 flex-shrink-0">
                <span>Continue</span>
                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Activity Timeline (5 cols) -->
    <div class="lg:col-span-5 flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-[20px]">timeline</span>
          <h2 class="font-headline-sm text-base font-semibold text-on-surface">Recent Activity</h2>
        </div>
        <?php if (!empty($recentActivity)): ?>
          <a href="/dashboard/activity.php" class="font-mono text-xs text-primary hover:underline">Full Log</a>
        <?php endif; ?>
      </div>

      <?php if (empty($recentActivity)): ?>
        <div class="card p-8 rounded-xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-2">
          <span class="material-symbols-outlined text-3xl text-text-muted">event_busy</span>
          <h3 class="font-semibold text-sm text-on-surface">No recent activity</h3>
          <p class="font-sans text-xs text-text-secondary">
            Your real bookmarks, likes, views, and downloads will be logged here chronologically.
          </p>
        </div>
      <?php else: ?>
        <div class="card p-4 rounded-xl border border-border/60 flex flex-col gap-3">
          <?php foreach ($recentActivity as $act): ?>
            <div class="flex items-start gap-3 text-xs pb-2.5 border-b border-border/40 last:border-0 last:pb-0">
              <span class="material-symbols-outlined text-primary text-[16px] mt-0.5">check_circle</span>
              <div class="flex flex-col flex-1">
                <span class="text-on-surface leading-snug"><?= htmlspecialchars($act['description']) ?></span>
                <span class="font-mono text-[10px] text-text-muted mt-0.5">
                  <?= date('M j, Y · g:i a', strtotime($act['created_at'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

