<?php
// ============================================================
// USER DASHBOARD — READING HISTORY
// dashboard/history.php
// ============================================================

$activeNav = 'history';
$pageTitle = 'Reading History';

require_once __DIR__ . '/partials/layout_top.php';
require_once dirname(__DIR__) . '/api/user/content_resolver.php';

$userId = (int)$currentUser['id'];
$historyItems = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, content_type, content_id, progress_percent, last_viewed_at 
         FROM reading_history 
         WHERE user_id = ? 
         ORDER BY last_viewed_at DESC 
         LIMIT 100"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $meta = resolveUserContent($pdo, $r['content_type'], $r['content_id']);
        $historyItems[] = array_merge($r, ['item' => $meta]);
    }
} catch (Exception $e) {
    error_log('[dashboard/history] DB error: ' . $e->getMessage());
}
?>

<div class="flex flex-col gap-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/70">
    <div class="flex flex-col gap-1">
      <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
        <span class="material-symbols-outlined text-[18px]">history</span>
        <span>Content Exploration Trail</span>
      </div>
      <h1 class="font-headline-md text-2xl font-bold text-on-surface">Reading History</h1>
      <p class="font-sans text-xs sm:text-sm text-text-secondary">
        Chronological list of essays, architectural deep-dives, lab experiments, and products you viewed.
      </p>
    </div>

    <?php if (!empty($historyItems)): ?>
      <button id="clear-history-btn" class="btn btn-secondary text-xs text-red-400 hover:border-red-500/40 self-start sm:self-auto">
        <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
        <span>Clear History</span>
      </button>
    <?php endif; ?>
  </div>

  <?php if (empty($historyItems)): ?>
    <div class="card p-12 rounded-2xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-3" id="empty-history-box">
      <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-text-muted">
        <span class="material-symbols-outlined text-3xl">history_toggle_off</span>
      </div>
      <h3 class="font-headline-sm text-base font-semibold text-on-surface">No reading history recorded</h3>
      <p class="font-sans text-xs text-text-secondary max-w-md leading-relaxed">
        Whenever you view technical essays, projects, or lab benchmarks while logged in, they will be logged here so you can re-open them quickly.
      </p>
      <a href="/articles.php" class="btn btn-primary text-xs mt-2">
        <span class="material-symbols-outlined text-[16px]">auto_stories</span>
        <span>Read Latest Writing</span>
      </a>
    </div>
  <?php else: ?>
    <div class="flex flex-col gap-3" id="history-container">
      <?php foreach ($historyItems as $h): 
        $item = $h['item'];
      ?>
        <div class="card p-4 rounded-xl border border-border/70 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-primary/40 transition-colors">
          <div class="flex items-center gap-3.5 overflow-hidden">
            <img loading="lazy" decoding="async" src="<?= htmlspecialchars($item['image']) ?>" 
                 alt="<?= htmlspecialchars(!empty($item['title']) ? $item['title'] : 'Reading history item') ?>" 
                 class="w-14 h-14 rounded-xl object-cover bg-surface-container flex-shrink-0 border border-border/50">
            <div class="flex flex-col overflow-hidden">
              <div class="flex items-center gap-2">
                <span class="badge badge-neutral text-[10px] py-0 px-1.5"><?= htmlspecialchars($item['type_badge']) ?></span>
                <span class="font-mono text-[10px] text-text-muted uppercase"><?= htmlspecialchars($item['category']) ?></span>
              </div>
              <h3 class="font-semibold text-sm text-on-surface truncate mt-1">
                <?= htmlspecialchars($item['title']) ?>
              </h3>
              <span class="font-mono text-[11px] text-text-muted mt-0.5">
                Last viewed on <?= date('M j, Y \a\t g:i a', strtotime($h['last_viewed_at'])) ?>
              </span>
            </div>
          </div>

          <a href="<?= htmlspecialchars($item['url']) ?>" class="btn btn-primary text-xs py-2 px-3.5 self-start sm:self-auto flex-shrink-0">
            <span>Continue Reading</span>
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const clearBtn = document.getElementById('clear-history-btn');
    if (clearBtn) {
      clearBtn.addEventListener('click', async () => {
        if (!confirm('Are you sure you want to clear your reading history? This cannot be undone.')) {
          return;
        }

        clearBtn.disabled = true;
        clearBtn.innerHTML = '<span>Clearing...</span>';

        try {
          const res = await window.UserAPI.post('/api/history/clear.php', {});
          if (res.success) {
            const container = document.getElementById('history-container');
            if (container) container.remove();
            clearBtn.remove();

            // Render empty state
            const wrapper = document.querySelector('.flex.flex-col.gap-6');
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'card p-12 rounded-2xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-3';
            emptyDiv.innerHTML = `
              <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-text-muted">
                <span class="material-symbols-outlined text-3xl">history_toggle_off</span>
              </div>
              <h3 class="font-headline-sm text-base font-semibold text-on-surface">Your reading history has been cleared</h3>
              <p class="font-sans text-xs text-text-secondary max-w-md leading-relaxed">
                New articles, engineering projects, and benchmarks you view will be logged here.
              </p>
            `;
            wrapper.appendChild(emptyDiv);
          } else {
            alert(res.message || 'Failed to clear history.');
            clearBtn.disabled = false;
            clearBtn.innerHTML = '<span class="material-symbols-outlined text-[16px]">delete_sweep</span><span>Clear History</span>';
          }
        } catch (err) {
          console.error(err);
          alert('Network error.');
          clearBtn.disabled = false;
        }
      });
    }
  });
</script>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

