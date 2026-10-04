<?php
// ============================================================
// USER DASHBOARD — DOWNLOADS HISTORY
// dashboard/downloads.php
// ============================================================

$activeNav = 'downloads';
$pageTitle = 'Download History';

require_once __DIR__ . '/partials/layout_top.php';

$userId = (int)$currentUser['id'];
$downloads = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT d.id AS download_id, d.product_id, d.product_title, d.downloaded_at,
                p.title AS live_title, p.slug, p.thumbnail, p.status, p.product_type
         FROM user_downloads d
         LEFT JOIN products p ON d.product_id = p.id
         WHERE d.user_id = ?
         ORDER BY d.downloaded_at DESC"
    );
    $stmt->execute([$userId]);
    $downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[dashboard/downloads] DB error: ' . $e->getMessage());
}
?>

<div class="flex flex-col gap-6">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/70">
    <div class="flex flex-col gap-1">
      <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
        <span class="material-symbols-outlined text-[18px]">download</span>
        <span>Secure Deliveries</span>
      </div>
      <h1 class="font-headline-md text-2xl font-bold text-on-surface">Download History</h1>
      <p class="font-sans text-xs sm:text-sm text-text-secondary">
        Historical audit trail of all free technical resources and templates downloaded to your devices.
      </p>
    </div>

    <a href="/store" class="btn btn-secondary text-xs self-start sm:self-auto">
      <span class="material-symbols-outlined text-[16px]">storefront</span>
      <span>Browse Products</span>
    </a>
  </div>

  <?php if (empty($downloads)): ?>
    <div class="card p-12 rounded-2xl border border-dashed border-border text-center flex flex-col items-center justify-center gap-3">
      <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-text-muted">
        <span class="material-symbols-outlined text-3xl">download_done</span>
      </div>
      <h3 class="font-headline-sm text-base font-semibold text-on-surface">No downloads recorded yet</h3>
      <p class="font-sans text-xs text-text-secondary max-w-md leading-relaxed">
        Free digital templates, architecture cheat sheets, and technical study guides you download from the Store will appear here for immediate re-download.
      </p>
      <a href="/store" class="btn btn-primary text-xs mt-2">
        <span class="material-symbols-outlined text-[16px]">explore</span>
        <span>Explore Store Resources</span>
      </a>
    </div>
  <?php else: ?>
    <div class="card rounded-2xl border border-border/80 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-surface-container-high/40 border-b border-border text-text-muted font-mono uppercase text-[11px]">
              <th class="py-3 px-4 font-medium">Resource Title</th>
              <th class="py-3 px-4 font-medium">Delivered At</th>
              <th class="py-3 px-4 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/60 font-sans">
            <?php foreach ($downloads as $d): 
              $title = !empty($d['live_title']) ? $d['live_title'] : $d['product_title'];
            ?>
              <tr class="hover:bg-surface-container-low/50 transition-colors">
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">description</span>
                    <span class="font-medium text-on-surface text-xs sm:text-sm"><?= htmlspecialchars($title) ?></span>
                  </div>
                </td>
                <td class="py-3 px-4 font-mono text-text-secondary">
                  <?= date('M j, Y · g:i a', strtotime($d['downloaded_at'])) ?>
                </td>
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <?php if (!empty($d['product_id'])): ?>
                      <a href="/api/products/download.php?id=<?= (int)$d['product_id'] ?>" 
                         class="js-record-download btn btn-primary text-xs py-1 px-2.5"
                         data-product-id="<?= (int)$d['product_id'] ?>">
                        <span class="material-symbols-outlined text-[14px]">download</span>
                        <span>Re-download</span>
                      </a>
                    <?php endif; ?>
                    <?php if (!empty($d['slug'])): ?>
                      <a href="/store/<?= rawurlencode($d['slug']) ?>" 
                         class="btn btn-secondary text-xs py-1 px-2" 
                         title="View Product Page">
                        <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

