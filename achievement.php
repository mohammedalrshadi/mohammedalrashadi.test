<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
$currentPage = 'achievements';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

require_once __DIR__ . '/includes/image_helper.php';

$achievement = null;
$galleryImages = [];

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        $pdo = getDB();
        require_once __DIR__ . '/api/helpers/achievements_schema.php';
        $notTrashed = achievementsHasDeletedAt($pdo) ? ' AND deleted_at IS NULL' : '';
        
        if ($slug) {
            $stmt = $pdo->prepare("SELECT * FROM achievements WHERE slug = ? AND status = 'published'" . $notTrashed);
            $stmt->execute([$slug]);
        } elseif ($id) {
            $stmt = $pdo->prepare("SELECT * FROM achievements WHERE id = ? AND status = 'published'" . $notTrashed);
            $stmt->execute([$id]);
        }
        
        if (isset($stmt)) {
            $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($achievement) {
            $gStmt = $pdo->prepare("SELECT image_url, alt_text FROM achievement_gallery WHERE achievement_id = ? ORDER BY sort_order ASC, id ASC");
            $gStmt->execute([$achievement['id']]);
            $galleryImages = $gStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // error
    }
}

if ($achievement) {
    $pageTitle = $achievement['title'];
    $pageDescription = !empty($achievement['description']) ? mb_substr(strip_tags($achievement['description']), 0, 160) . '...' : 'Achievement by Mohammed Alrashadi';
    // An all-digit slug (legacy data) would be routed as an ID under /achievement/, so use the
    // unambiguous /achievements/<slug> route for it.
    $canonicalBase = ctype_digit((string) $achievement['slug']) ? '/achievements/' : '/achievement/';
    $canonicalUrl = 'https://mohammedalrashadi.com' . $canonicalBase . rawurlencode($achievement['slug']);
    if (!empty($achievement['image_url'])) {
        $ogImage = $achievement['image_url'];
    }
} else {
    http_response_code(404);
    $pageTitle = 'Achievement Not Found';
    $pageDescription = 'The requested achievement was not found.';
    $canonicalUrl = 'https://mohammedalrashadi.com/achievements.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen">
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="relative z-0 w-full pt-16 bg-background min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">
      <section class="w-full border-b border-outline-variant/10 bg-surface-container-lowest/50 py-space-sm">
        <div class="container-study flex flex-wrap items-center justify-between gap-space-sm font-label-code text-label-micro text-outline">
          <div class="flex items-center gap-space-xs">
            <a href="/index.php" class="hover:text-primary transition-colors">HOME</a>
            <span>/</span>
            <a href="/achievements.php" class="hover:text-primary transition-colors">ACHIEVEMENTS</a>
            <?php if ($achievement): ?>
            <span>/</span>
            <span class="text-primary font-semibold uppercase"><?= htmlspecialchars($achievement['category'] ?? 'AWARD') ?></span>
            <?php endif; ?>
          </div>
          <div>
            <a href="/achievements.php" class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1 font-medium">
              <span class="material-symbols-outlined text-[1rem]">arrow_back</span>
              Back to Achievements
            </a>
          </div>
        </div>
      </section>

      <?php if (!$achievement): ?>
        <div class="max-w-[800px] w-full mx-auto px-gutter py-space-2xl text-center flex flex-col items-center justify-center gap-space-md my-space-xl">
          <div class="p-space-lg rounded-full bg-surface-container-low border border-outline-variant/10 text-outline">
            <span class="material-symbols-outlined text-[3rem]">workspace_premium</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Achievement Not Found</h1>
          <div class="pt-space-md">
            <a href="/achievements.php" class="px-space-lg py-space-sm rounded-lg bg-primary text-on-primary font-label-code text-label-code font-semibold hover:opacity-90 inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
              <span>Back to Achievements</span>
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="container-study py-space-xl flex flex-col gap-space-2xl">
          <header class="flex flex-col gap-space-md">
            <div class="flex flex-wrap items-center gap-space-sm">
              <span class="px-space-sm py-space-xs rounded bg-primary/10 text-primary font-label-micro text-label-micro uppercase font-semibold">
                <?= htmlspecialchars($achievement['category'] ?? 'Achievement') ?>
              </span>
              <?php if (!empty($achievement['date_awarded'])): ?>
              <span class="font-label-code text-label-micro text-outline">
                Awarded: <?= date('F Y', strtotime($achievement['date_awarded'])) ?>
              </span>
              <?php endif; ?>
            </div>
            <h1 class="font-headline-lg lg:text-display text-on-surface tracking-tight leading-tight font-bold">
              <?= htmlspecialchars($achievement['title']) ?>
            </h1>
            <?php if (!empty($achievement['organization'])): ?>
            <p class="font-body-lg text-body-lg text-on-surface-variant font-medium">
              Issued by: <?= htmlspecialchars($achievement['organization']) ?>
            </p>
            <?php endif; ?>
            
            <?php if (!empty($achievement['image_url'])): ?>
            <div class="mt-space-md relative w-full max-w-3xl mx-auto rounded-xl overflow-hidden bg-surface-container-lowest border border-outline-variant/10 shadow-lg">
              <?= responsiveImage($achievement['image_url'], $achievement['title'], [
                  'class' => 'w-full h-auto object-contain max-h-[600px]'
              ]) ?>
            </div>
            <?php endif; ?>
          </header>
          
          <section class="flex flex-col gap-space-md max-w-4xl">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Description</h2>
            <p class="font-body-lg text-body-lg text-on-surface-variant leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($achievement['description']) ?></p>
            
            <?php if (!empty($achievement['url'])): ?>
            <div class="mt-space-md">
              <a href="<?= htmlspecialchars($achievement['url']) ?>" target="_blank" rel="noopener noreferrer" class="px-space-md py-space-sm rounded-lg bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-code text-label-code inline-flex items-center gap-2 transition-colors">
                <span>View Credential / Original Link</span>
                <span class="material-symbols-outlined text-[1.125rem]">open_in_new</span>
              </a>
            </div>
            <?php endif; ?>
          </section>

          <?php if (!empty($galleryImages)): ?>
          <section class="flex flex-col gap-space-md">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Gallery</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-space-md">
              <?php foreach ($galleryImages as $gImg): ?>
                <div class="aspect-square rounded-lg overflow-hidden bg-surface-container border border-outline-variant/10 flex items-center justify-center p-2">
                  <img decoding="async" src="<?= htmlspecialchars($gImg['image_url']) ?>" alt="<?= htmlspecialchars($gImg['alt_text'] ?? 'Gallery image') ?>" class="w-full h-full object-contain" loading="lazy">
                </div>
              <?php endforeach; ?>
            </div>
          </section>
          <?php endif; ?>

        </div>
      <?php endif; ?>
    </div>
  </main>
  <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
