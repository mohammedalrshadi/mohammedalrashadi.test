<?php
// ============================================================
// USER DASHBOARD — ACTIVITY TIMELINE
// dashboard/activity.php
// ============================================================

$activeNav = 'activity';
$pageTitle = 'Activity Timeline';

require_once __DIR__ . '/partials/layout_top.php';
require_once dirname(__DIR__) . '/api/user/content_resolver.php';

$userId = (int)$currentUser['id'];
$activities = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, activity_type, content_type, content_id, description, created_at 
         FROM user_activities 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 100"
    );
    $stmt->execute([$userId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[dashboard/activity] DB error: ' . $e->getMessage());
}

function getActivityIcon(string $type): string {
    return match ($type) {
        'bookmark_added'     => 'bookmark',
        'bookmark_removed'   => 'bookmark_remove',
        'like_added'         => 'favorite',
        'like_removed'       => 'heart_minus',
        'content_viewed'     => 'visibility',
        'download_completed' => 'download',
        'library_added'      => 'folder_special',
        'profile_updated'    => 'person',
        'settings_updated'   => 'settings',
        'account_created'    => 'verified_user',
        default              => 'radio_button_checked',
    };
}
?>

<div class="flex flex-col gap-6">

  <!-- Header -->
  <div class="flex flex-col gap-1 pb-4 border-b border-border/70">
    <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
      <span class="material-symbols-outlined text-[18px]">timeline</span>
      <span>Audit Trail</span>
    </div>
    <h1 class="font-headline-md text-2xl font-bold text-on-surface">Activity Timeline</h1>
    <p class="font-sans text-xs sm:text-sm text-text-secondary">
      Chronological audit stream of your authentic interactions, saved items, and account updates.
    </p>
  </div>

  <?php if (empty($activities)): ?>
    <div class="card p-12 rounded-2xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-3">
      <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-text-muted">
        <span class="material-symbols-outlined text-3xl">event_busy</span>
      </div>
      <h3 class="font-headline-sm text-base font-semibold text-on-surface">No activity logged yet</h3>
      <p class="font-sans text-xs text-text-secondary max-w-md leading-relaxed">
        Your real actions—such as bookmarking research, downloading resources, or liking articles—will be logged here chronologically.
      </p>
    </div>
  <?php else: ?>
    <div class="card p-6 rounded-2xl border border-border/80">
      <div class="relative border-l border-border/70 ml-3.5 space-y-6">
        <?php foreach ($activities as $act): 
          $icon = getActivityIcon($act['activity_type']);
          $url = null;
          if (!empty($act['content_type']) && !empty($act['content_id'])) {
              $meta = resolveUserContent($pdo, $act['content_type'], $act['content_id']);
              if (!$meta['is_missing']) {
                  $url = $meta['url'];
              }
          }
        ?>
          <div class="relative pl-6">
            <!-- Dot / Icon Indicator -->
            <span class="absolute -left-3.5 top-0.5 w-7 h-7 rounded-full bg-surface-container border border-border flex items-center justify-center text-primary">
              <span class="material-symbols-outlined text-[14px]"><?= $icon ?></span>
            </span>

            <div class="flex flex-col gap-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-xs sm:text-sm text-on-surface leading-snug">
                  <?= htmlspecialchars($act['description']) ?>
                </span>
                <?php if ($url): ?>
                  <a href="<?= htmlspecialchars($url) ?>" class="text-[11px] font-mono text-primary hover:underline flex items-center gap-0.5">
                    <span>View</span>
                    <span class="material-symbols-outlined text-[12px]">arrow_forward</span>
                  </a>
                <?php endif; ?>
              </div>
              <span class="font-mono text-[10px] text-text-muted">
                <?= date('F j, Y · g:i a', strtotime($act['created_at'])) ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

