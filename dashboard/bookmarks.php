<?php
// ============================================================
// USER DASHBOARD — BOOKMARKS
// dashboard/bookmarks.php
// ============================================================

$activeNav = 'bookmarks';
$pageTitle = 'Bookmarks';

require_once __DIR__ . '/partials/layout_top.php';
require_once dirname(__DIR__) . '/api/user/content_resolver.php';

$userId = (int)$currentUser['id'];
$bookmarks = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, content_type, content_id, created_at 
         FROM bookmarks 
         WHERE user_id = ? 
         ORDER BY created_at DESC"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $meta = resolveUserContent($pdo, $r['content_type'], $r['content_id']);
        $bookmarks[] = array_merge($r, ['item' => $meta]);
    }
} catch (Exception $e) {
    error_log('[dashboard/bookmarks] DB error: ' . $e->getMessage());
}
?>

<div class="flex flex-col gap-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/70">
    <div class="flex flex-col gap-1">
      <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
        <span class="material-symbols-outlined text-[18px]">bookmark</span>
        <span>Curated Reading List</span>
      </div>
      <h1 class="font-headline-md text-2xl font-bold text-on-surface">Bookmarks</h1>
      <p class="font-sans text-xs sm:text-sm text-text-secondary">
        Technical essays, architectural case studies, lab benchmarks, and digital resources saved for reference.
      </p>
    </div>

    <!-- Filter Buttons -->
    <div class="flex flex-wrap items-center gap-1.5 self-start sm:self-auto" id="bookmark-filters">
      <button class="filter-pill active px-3 py-1 rounded-lg text-xs font-mono border border-primary/30 bg-primary/10 text-primary font-semibold" data-type="all">All</button>
      <button class="filter-pill px-3 py-1 rounded-lg text-xs font-mono border border-border text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" data-type="writing">Writing</button>
      <button class="filter-pill px-3 py-1 rounded-lg text-xs font-mono border border-border text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" data-type="project">Projects</button>
      <button class="filter-pill px-3 py-1 rounded-lg text-xs font-mono border border-border text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" data-type="lab">Lab</button>
      <button class="filter-pill px-3 py-1 rounded-lg text-xs font-mono border border-border text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors" data-type="product">Store</button>
    </div>
  </div>

  <!-- Content List -->
  <?php if (empty($bookmarks)): ?>
    <div class="card p-12 rounded-2xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-3">
      <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-text-muted">
        <span class="material-symbols-outlined text-3xl">bookmark_border</span>
      </div>
      <h3 class="font-headline-sm text-base font-semibold text-on-surface">No bookmarks saved yet</h3>
      <p class="font-sans text-xs text-text-secondary max-w-md leading-relaxed">
        Click the bookmark icon on any technical article, engineering project, lab benchmark, or product to save it here for later.
      </p>
      <div class="flex flex-wrap gap-2 mt-2">
        <a href="/articles.php" class="btn btn-secondary text-xs">Read Writing</a>
        <a href="/projects.php" class="btn btn-secondary text-xs">View Projects</a>
        <a href="/store" class="btn btn-secondary text-xs">Explore Store</a>
      </div>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="bookmarks-grid">
      <?php foreach ($bookmarks as $b): 
        $item = $b['item'];
      ?>
        <div class="card bookmark-card p-5 rounded-2xl border border-border/80 flex flex-col justify-between gap-4 hover:border-primary/40 transition-colors" 
             data-type="<?= htmlspecialchars($b['content_type']) ?>" 
             id="bm-card-<?= htmlspecialchars($b['content_type']) ?>-<?= htmlspecialchars($b['content_id']) ?>">
          
          <div class="flex flex-col gap-3">
            <div class="aspect-[16/10] w-full rounded-xl overflow-hidden bg-surface-container relative border border-border/40">
              <img loading="lazy" decoding="async" src="<?= htmlspecialchars($item['image']) ?>" 
                   alt="<?= htmlspecialchars(!empty($item['title']) ? $item['title'] : 'Bookmarked resource') ?>" 
                   class="w-full h-full object-cover">
              <div class="absolute top-2.5 left-2.5">
                <span class="badge badge-neutral text-[10px] bg-surface backdrop-blur-sm">
                  <?= htmlspecialchars($item['type_badge']) ?>
                </span>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <span class="font-mono text-[10px] text-text-muted uppercase tracking-wider">
                <?= htmlspecialchars($item['category']) ?>
              </span>
              <h3 class="font-headline-sm text-base font-semibold text-on-surface leading-snug">
                <?= htmlspecialchars($item['title']) ?>
              </h3>
            </div>
          </div>

          <div class="flex items-center justify-between pt-3 border-t border-border/60">
            <a href="<?= htmlspecialchars($item['url']) ?>" class="btn btn-primary text-xs py-1.5 px-3">
              <span>Open Content</span>
              <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>

            <button class="btn btn-icon text-text-muted hover:text-red-400 remove-bm-btn" 
                    title="Remove Bookmark"
                    aria-label="Remove Bookmark"
                    data-type="<?= htmlspecialchars($b['content_type']) ?>" 
                    data-id="<?= htmlspecialchars($b['content_id']) ?>">
              <span class="material-symbols-outlined text-[18px]">bookmark_remove</span>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // 1. Filter logic
    const filterButtons = document.querySelectorAll('#bookmark-filters button');
    const cards = document.querySelectorAll('.bookmark-card');

    filterButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        filterButtons.forEach(b => {
          b.className = 'filter-pill px-3 py-1 rounded-lg text-xs font-mono border border-border text-text-secondary hover:text-text-primary hover:bg-surface-container transition-colors';
        });
        btn.className = 'filter-pill active px-3 py-1 rounded-lg text-xs font-mono border border-primary/30 bg-primary/10 text-primary font-semibold';

        const filterType = btn.getAttribute('data-type');
        cards.forEach(card => {
          if (filterType === 'all' || card.getAttribute('data-type') === filterType) {
            card.style.display = 'flex';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });

    // 2. Remove bookmark handler
    document.querySelectorAll('.remove-bm-btn').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const type = btn.getAttribute('data-type');
        const id = btn.getAttribute('data-id');

        try {
          const res = await window.UserAPI.post('/api/bookmarks/toggle.php', {
            content_type: type,
            content_id: id
          });

          if (res.success && !res.bookmarked) {
            const card = document.getElementById(`bm-card-${type}-${id}`);
            if (card) {
              card.style.opacity = '0';
              card.style.transform = 'scale(0.95)';
              card.style.transition = 'all 0.2s ease-out';
              setTimeout(() => card.remove(), 200);
            }
          }
        } catch (err) {
          console.error('Failed to remove bookmark:', err);
        }
      });
    });
  });
</script>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

