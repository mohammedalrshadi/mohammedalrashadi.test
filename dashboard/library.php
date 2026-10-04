<?php
// ============================================================
// USER DASHBOARD — LIBRARY
// dashboard/library.php
// ============================================================

$activeNav = 'library';
$pageTitle = 'My Library';

require_once __DIR__ . '/partials/layout_top.php';

$userId = (int)$currentUser['id'];
$libraryItems = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT l.id AS library_id, l.product_id, l.access_type, l.created_at AS claimed_at,
                p.title, p.slug, p.short_description, p.category, p.thumbnail, p.product_type, p.status, p.price_display
         FROM user_library l
         INNER JOIN products p ON l.product_id = p.id
         WHERE l.user_id = ?
         ORDER BY l.created_at DESC"
    );
    $stmt->execute([$userId]);
    $libraryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[dashboard/library] DB error: ' . $e->getMessage());
}
?>

<div class="flex flex-col gap-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/70">
    <div class="flex flex-col gap-1">
      <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
        <span class="material-symbols-outlined text-[18px]">folder_special</span>
        <span>Entitlements &amp; Resources</span>
      </div>
      <h1 class="font-headline-md text-2xl font-bold text-on-surface">My Library</h1>
      <p class="font-sans text-xs sm:text-sm text-text-secondary">
        Digital tools, Notion templates, and technical resources claimed to your account.
      </p>
    </div>

    <a href="/store" class="btn btn-secondary text-xs self-start sm:self-auto">
      <span class="material-symbols-outlined text-[16px]">storefront</span>
      <span>Browse Store</span>
    </a>
  </div>

  <!-- Content Grid -->
  <?php if (empty($libraryItems)): ?>
    <div class="card p-12 rounded-2xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-3">
      <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-text-muted">
        <span class="material-symbols-outlined text-3xl">folder_off</span>
      </div>
      <h3 class="font-headline-sm text-base font-semibold text-on-surface">Your library is empty</h3>
      <p class="font-sans text-xs text-text-secondary max-w-md leading-relaxed">
        Free developer cheat sheets, Notion templates, and architecture guides you download from the Store will be saved here for instant access anytime.
      </p>
      <a href="/store" class="btn btn-primary text-xs mt-2">
        <span class="material-symbols-outlined text-[16px]">explore</span>
        <span>Discover Digital Products</span>
      </a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($libraryItems as $item): ?>
        <div class="card p-5 rounded-2xl border border-border/80 flex flex-col justify-between gap-4 hover:border-primary/40 transition-colors">
          <div class="flex flex-col gap-3">
            <div class="aspect-[16/10] w-full rounded-xl overflow-hidden bg-surface-container relative border border-border/40">
              <img loading="lazy" decoding="async" src="<?= htmlspecialchars(!empty($item['thumbnail']) ? $item['thumbnail'] : '/assets/store-placeholder.png') ?>" 
                   alt="<?= htmlspecialchars($item['title']) ?>" 
                   class="w-full h-full object-cover">
              <div class="absolute top-2.5 left-2.5">
                <span class="badge badge-neutral text-[10px] bg-surface backdrop-blur-sm">
                  <?= htmlspecialchars($item['category'] ?: 'Digital Resource') ?>
                </span>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <h3 class="font-headline-sm text-base font-semibold text-on-surface leading-snug">
                <?= htmlspecialchars($item['title']) ?>
              </h3>
              <p class="font-sans text-xs text-text-secondary line-clamp-2 leading-relaxed">
                <?= htmlspecialchars($item['short_description'] ?: 'Digital product resource.') ?>
              </p>
            </div>
          </div>

          <div class="flex flex-col gap-3 pt-3 border-t border-border/60">
            <div class="flex items-center justify-between text-[11px] font-mono text-text-muted">
              <span>Claimed</span>
              <span><?= date('M j, Y', strtotime($item['claimed_at'])) ?></span>
            </div>

            <div class="flex items-center gap-2">
              <a href="/api/products/download.php?id=<?= (int)$item['product_id'] ?>" 
                 class="js-record-download btn btn-primary text-xs py-2 flex-1 justify-center"
                 data-product-id="<?= (int)$item['product_id'] ?>">
                <span class="material-symbols-outlined text-[16px]">download</span>
                <span>Download</span>
              </a>
              <a href="/store/<?= rawurlencode($item['slug']) ?>" 
                 class="btn btn-secondary text-xs py-2 px-3"
                 title="View in Store">
                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

