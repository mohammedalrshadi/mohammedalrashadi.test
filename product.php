<?php
// ============================================================
// PRODUCT DETAIL PAGE
// /store/{slug} or /product.php?slug={slug}
//
// Dedicated technical product presentation for Mohammed Alrashadi Store.
// Free products offer direct secure downloads; external products redirect
// to the verified external checkout platform (Gumroad, ThemeForest, etc.).
// ============================================================

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/api/posts/sanitizer.php'; // DC-004: render-time sanitisation

$currentPage = 'store';
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$productId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$product = $product ?? null;
$isAdmin = isAdminLoggedIn();

if (file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        $pdo = getDB();

        if ($product === null) {
            if (!empty($slug)) {
                $whereStatus = $isAdmin ? '' : "AND status = 'published'";
                $stmt = $pdo->prepare("SELECT * FROM products WHERE slug = :slug $whereStatus LIMIT 1");
                $stmt->execute([':slug' => $slug]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            } elseif ($productId > 0) {
                $whereStatus = $isAdmin ? '' : "AND status = 'published'";
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id $whereStatus LIMIT 1");
                $stmt->execute([':id' => $productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Throwable $e) {
        error_log('[product.php] DB query error: ' . $e->getMessage());
        if ($product === null) {
            $product = null;
        }
    }

    // Fetch product gallery images (Phase 3)
    $galleryImages = $galleryImages ?? [];
    if ($product && empty($galleryImages) && isset($pdo)) {
        try {
            $stmtGal = $pdo->prepare("SELECT id, image_path, alt_text, sort_order FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC");
            $stmtGal->execute([':id' => $product['id']]);
            $galleryImages = $stmtGal->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Safely ignore missing table (42S02) on environments where migration hasn't run
            if ($e->getCode() !== '42S02') {
                error_log('[product.php] Fetch gallery error: ' . $e->getMessage());
            }
        } catch (Throwable $e) {
            error_log('[product.php] Fetch gallery general error: ' . $e->getMessage());
        }
    }

    // Fetch product resources
    $productResources = $productResources ?? [];
    if ($product && empty($productResources) && isset($pdo)) {
        try {
            $resWhere = $isAdmin ? '' : "AND is_public = 1";
            $stmtRes = $pdo->prepare("SELECT * FROM product_resources WHERE product_id = :id $resWhere ORDER BY sort_order ASC, id ASC");
            $stmtRes->execute([':id' => $product['id']]);
            $productResources = $stmtRes->fetchAll(PDO::FETCH_ASSOC);
            foreach ($productResources as &$r) {
                $r['file_name'] = basename(explode('?', $r['file_path'])[0]);
            }
            unset($r);
        } catch (Throwable $e) {
            error_log('[product.php] Fetch resources error: ' . $e->getMessage());
        }
    }

    // Check for SEO redirect if product not found
    if (!$product && !empty($slug) && isset($pdo)) {
        try {
            $stmtRedir = $pdo->prepare("SELECT destination_path FROM url_redirects WHERE source_path = :source LIMIT 1");
            $stmtRedir->execute([':source' => '/store/' . $slug]);
            $redir = $stmtRedir->fetchColumn();
            if ($redir) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $redir);
                exit;
            }
        } catch (Throwable $e) { error_log('[product.php:93] non-fatal, fallback used: ' . get_class($e)); }
    }
}

if ($product) {
    $pageTitle = $product['title'];
    $pageDescription = !empty($product['short_description'])
        ? $product['short_description']
        : mb_substr(strip_tags($product['description'] ?? ''), 0, 160) . '...';
    $canonicalUrl = 'https://mohammedalrashadi.com/store/' . rawurlencode($product['slug']);
    if (!empty($product['thumbnail'])) {
        $ogImage = $product['thumbnail'];
    }
} else {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $robots = 'noindex,follow';
    $pageTitle = 'Product Not Found';
    $pageDescription = 'The requested digital product was not found in the Store catalog.';
    $canonicalUrl = 'https://mohammedalrashadi.com/store';
}
?>
<!DOCTYPE html>
<html lang="en" class="is-animating">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>

  <?php if ($product):
    $hasDownload = ($product['product_type'] === 'free_download') && !empty($product['download_path']);
    $isFree = $hasDownload || strcasecmp(trim((string)$product['price_display']), 'free') === 0 || trim((string)$product['price_display']) === '0' || trim((string)$product['price_display']) === '0.00';
    $numericPrice = '0.00';
    if (!$isFree && !empty($product['price_display'])) {
        $extracted = preg_replace('/[^0-9.]/', '', $product['price_display']);
        if ($extracted !== '' && is_numeric($extracted)) {
            $numericPrice = number_format((float)$extracted, 2, '.', '');
        }
    }

    $productImage = !empty($product['thumbnail'])
        ? (strpos($product['thumbnail'], 'http') === 0 ? $product['thumbnail'] : 'https://mohammedalrashadi.com/' . ltrim($product['thumbnail'], '/'))
        : 'https://mohammedalrashadi.com/assets/logo/logo.png';

    $currency = strtoupper(trim((string)($product['currency'] ?? 'USD')));
    if ($isFree) {
        $displayPrice = 'Free';
    } else {
        $displayPrice = ($currency === 'USD') ? '$' . $numericPrice : $numericPrice . ' ' . $currency;
    }

    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['title'],
        'image' => $productImage,
        'description' => $pageDescription,
        'offers' => [
            '@type' => 'Offer',
            'price' => $numericPrice,
            'priceCurrency' => $currency,
            'availability' => ($product['status'] === 'published') ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => $canonicalUrl
        ]
    ];

    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => 'https://mohammedalrashadi.com/'
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'Store',
                'item' => 'https://mohammedalrashadi.com/store'
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $product['title'],
                'item' => $canonicalUrl
            ]
        ]
    ];
  ?>
  <!-- JSON-LD Structured Data: Product & BreadcrumbList -->
  <script type="application/ld+json">
  <?= json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <script type="application/ld+json">
  <?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <?php endif; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">

  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="page-transition-wrapper relative z-0 w-full pt-16 bg-background min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">

      <!-- Top Navigation & Breadcrumb -->
      <section class="w-full border-b border-outline-variant/10 bg-surface-container-lowest/50 py-space-sm">
        <div class="container-catalog flex flex-wrap items-center justify-between gap-space-sm font-label-code text-label-micro text-outline">
          <div class="flex items-center gap-space-xs flex-wrap">
            <a href="/index.php" class="hover:text-primary transition-colors">HOME</a>
            <span>/</span>
            <a href="/store" class="hover:text-primary transition-colors">STORE</a>
            <?php if (!empty($product['category'])): ?>
              <span>/</span>
              <a href="/store?category=<?= urlencode($product['category']) ?>" class="hover:text-primary transition-colors uppercase"><?= htmlspecialchars($product['category']) ?></a>
            <?php endif; ?>
            <span>/</span>
            <span class="text-on-surface font-semibold truncate max-w-[200px] sm:max-w-xs md:max-w-md"><?= htmlspecialchars($product['title'] ?? '') ?></span>
          </div>
          <div>
            <a href="/store" class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1 font-medium">
              <span class="material-symbols-outlined text-[1rem]">arrow_back</span>
              Back to Store
            </a>
          </div>
        </div>
      </section>

      <?php if (!$product): ?>
        <!-- 404 Product Not Found State -->
        <div class="max-w-[800px] w-full mx-auto px-gutter py-space-2xl text-center flex flex-col items-center justify-center gap-space-md my-space-xl">
          <div class="p-space-lg rounded-full bg-surface-container-low border border-outline-variant/10 text-outline">
            <span class="material-symbols-outlined text-[3rem]">storefront</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Product Not Found</h1>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-lg leading-relaxed">
            The requested product is not available or has been unpublished from the public store catalog.
          </p>
          <div class="pt-space-md">
            <a href="/store" class="btn btn-primary font-label-code text-label-code">
              <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
              <span>Back to Store Catalog</span>
            </a>
          </div>
        </div>
      <?php else:
        $isUploadedDemo = !empty($product['live_demo_source']) && $product['live_demo_source'] === 'uploaded' && !empty($product['live_demo_path']);
        $hasLiveDemo = !empty($product['live_demo_url']) || $isUploadedDemo;
        $resolvedLiveDemoUrl = $isUploadedDemo ? '/demo/' . rawurlencode($product['slug']) . '/' : $product['live_demo_url'];

        $hasExternal = !empty($product['external_url']);
        $platform = !empty($product['platform']) ? $product['platform'] : 'External Platform';
        $downloadUrl = '/api/products/download.php?id=' . (int)$product['id'];
        $thumb = !empty($product['thumbnail']) ? $product['thumbnail'] : '';

        // Precompute related items for bottom section and anchor nav
        $relatedItems = [];
        if (isset($pdo) && $product) {
            try {
                require_once __DIR__ . '/src/autoload.php';
                $resolver = new \Domain\Content\RelatedContentResolver();
                $relatedItems = $resolver->getRelatedProducts($pdo, $product['id'], $product['category'] ?? '', 3);
            } catch (Throwable $e) {
                error_log('[product.php] Related items error: ' . $e->getMessage());
                $relatedItems = [];
            }
        }
      ?>
        <!-- Product Detail Container (Catalog Container ≈ 1320px) -->
        <div class="container-catalog py-8 lg:py-12 flex flex-col">

          <?php if ($product['status'] !== 'published' && $isAdmin): ?>
            <!-- Admin Preview Notice Banner -->
            <div class="p-4 rounded-lg bg-yellow-500/10 border border-yellow-500/30 text-yellow-200 text-sm flex items-center justify-between gap-4 mb-8">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">visibility</span>
                <span><strong>Admin Preview:</strong> This product is currently in <strong><?= htmlspecialchars(strtoupper($product['status'])) ?></strong> state and not visible to public visitors.</span>
              </div>
              <a href="/admin/store-edit.php?id=<?= (int)$product['id'] ?>" class="font-label-code text-xs underline font-semibold text-yellow-300 hover:text-white">Edit in Studio &rarr;</a>
            </div>
          <?php endif; ?>

          <!-- HERO SECTION: Two Columns (Image-heavy Left / Info-heavy Right) -->
          <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start" aria-label="Product Showcase">

            <!-- Left Column: Main Image & Gallery Strip (7 cols) -->
            <div class="lg:col-span-7 flex flex-col gap-4">
              <!-- Main Showcase Image Container -->
              <div class="w-full bg-surface-container-lowest border border-outline-variant/15 overflow-hidden aspect-[16/10] flex items-center justify-center relative rounded-xl shadow-sm">
                <?php 
                $thumbValid = false;
                if (!empty($thumb)) {
                    $thumbLocal = __DIR__ . '/' . ltrim($thumb, '/');
                    if (is_file($thumbLocal)) {
                        $thumbValid = true;
                    }
                }

                // Determine primary hero image: cover thumbnail if valid, else first valid gallery image
                $initialImage = null;
                $initialAlt = $product['title'];
                if ($thumbValid) {
                    $initialImage = $thumb;
                } elseif (!empty($galleryImages)) {
                    foreach ($galleryImages as $gImg) {
                        if (!empty($gImg['image_path'])) {
                            $gLocal = __DIR__ . '/' . ltrim($gImg['image_path'], '/');
                            if (is_file($gLocal)) {
                                $initialImage = $gImg['image_path'];
                                $initialAlt = !empty($gImg['alt_text']) ? $gImg['alt_text'] : $product['title'];
                                break;
                            }
                        }
                    }
                }

                if (!empty($initialImage)): ?>
                  <?= responsiveImage($initialImage, $initialAlt, [
                      'class' => 'w-full h-full object-contain object-center',
                      'priority' => true,
                      'sizes' => '(max-width: 1024px) 100vw, 760px',
                      'attr' => [
                          'id' => 'mainProductImage',
                          'onerror' => 'this.onerror=null; this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';'
                      ]
                  ]) ?>
                  <div class="flex-col items-center justify-center gap-2 text-outline w-full h-full" style="display: none;">
                    <span class="material-symbols-outlined text-[4rem] text-primary/60">image_not_supported</span>
                    <span class="text-sm font-medium">No preview image</span>
                  </div>
                <?php else: ?>
                  <div class="flex flex-col items-center justify-center gap-2 text-outline w-full h-full">
                    <span class="material-symbols-outlined text-[4rem] text-primary/60">image_not_supported</span>
                    <span class="text-sm font-medium">No preview image</span>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Gallery Thumbnail Strip with Scroll Navigation -->
              <?php if (!empty($galleryImages)): ?>
                <div class="relative flex items-center gap-2 mt-1">
                  <!-- Prev Button -->
                  <button type="button" id="prevThumbBtn" class="hidden sm:inline-flex items-center justify-center w-8 h-8 rounded-full bg-surface-container-low border border-outline-variant/20 text-on-surface hover:text-primary hover:border-primary/40 transition-colors shrink-0 disabled:opacity-30 disabled:pointer-events-none" aria-label="Scroll thumbnails left">
                    <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                  </button>

                  <!-- Thumbnail List -->
                  <div id="galleryThumbStrip" class="flex gap-3 overflow-x-auto py-1 scroll-smooth snap-x hide-scrollbar flex-1" role="tablist" aria-label="Product Gallery">
                    <?php if ($thumbValid): 
                        $isCoverActive = ($initialImage === $thumb);
                    ?>
                      <button type="button" role="tab" aria-selected="<?= $isCoverActive ? 'true' : 'false' ?>" class="gallery-thumb-btn flex-shrink-0 w-20 sm:w-24 h-14 sm:h-16 rounded-lg border-2 <?= $isCoverActive ? 'border-primary opacity-100' : 'border-transparent opacity-60' ?> hover:border-outline-variant/40 overflow-hidden snap-start focus:outline-none focus:ring-2 focus:ring-primary/40 transition-all relative" aria-label="Main Cover" data-full-src="<?= htmlspecialchars($thumb) ?>">
                        <?= responsiveImage($thumb, 'Main Cover', [
                            'class' => 'w-full h-full object-cover',
                            'attr' => ['onerror' => 'this.onerror=null; this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';']
                        ]) ?>
                        <div class="flex-col items-center justify-center text-outline w-full h-full bg-surface-container-low rounded-lg absolute inset-0" style="display: none;">
                          <span class="material-symbols-outlined text-[1.25rem] text-primary/70">image_not_supported</span>
                        </div>
                      </button>
                    <?php endif; ?>

                    <?php foreach($galleryImages as $idx => $img):
                        $imgValid = false;
                        if (!empty($img['image_path'])) {
                            $imgLocal = __DIR__ . '/' . ltrim($img['image_path'], '/');
                            if (is_file($imgLocal)) {
                                $imgValid = true;
                            }
                        }
                        if ($imgValid):
                            $alt = !empty($img['alt_text']) ? $img['alt_text'] : ($product['title'] . ' - image ' . ($idx + 1));
                            $isActive = ($initialImage === $img['image_path']);
                    ?>
                      <button type="button" role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>" class="gallery-thumb-btn flex-shrink-0 w-20 sm:w-24 h-14 sm:h-16 rounded-lg border-2 <?= $isActive ? 'border-primary opacity-100' : 'border-transparent opacity-60' ?> hover:border-outline-variant/40 overflow-hidden snap-start focus:outline-none focus:ring-2 focus:ring-primary/40 transition-all relative" aria-label="<?= htmlspecialchars($alt) ?>" data-full-src="<?= htmlspecialchars($img['image_path']) ?>">
                        <?= responsiveImage($img['image_path'], $alt, [
                            'class' => 'w-full h-full object-cover',
                            'attr' => ['onerror' => 'this.onerror=null; this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';']
                        ]) ?>
                        <div class="flex-col items-center justify-center text-outline w-full h-full bg-surface-container-low rounded-lg absolute inset-0" style="display: none;">
                          <span class="material-symbols-outlined text-[1.25rem] text-primary/70">image_not_supported</span>
                        </div>
                      </button>
                    <?php endif; endforeach; ?>
                  </div>

                  <!-- Next Button -->
                  <button type="button" id="nextThumbBtn" class="hidden sm:inline-flex items-center justify-center w-8 h-8 rounded-full bg-surface-container-low border border-outline-variant/20 text-on-surface hover:text-primary hover:border-primary/40 transition-colors shrink-0 disabled:opacity-30 disabled:pointer-events-none" aria-label="Scroll thumbnails right">
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                  </button>
                </div>
              <?php endif; ?>
            </div>

            <!-- Right Column: Product Information & CTAs (5 cols) -->
            <div class="lg:col-span-5 flex flex-col pt-1">
              <!-- Category Pill -->
              <?php if (!empty($product['category'])): ?>
                <div class="mb-3">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono font-medium uppercase tracking-wider bg-surface-container-high/60 text-primary border border-primary/20">
                    <?= htmlspecialchars($product['category']) ?>
                  </span>
                </div>
              <?php endif; ?>

              <!-- Product Title -->
              <h1 class="text-3xl sm:text-4xl lg:text-5xl font-display font-bold text-on-surface tracking-tight leading-[1.15] mb-4 break-words">
                <?= htmlspecialchars($product['title']) ?>
              </h1>

              <!-- Short Description -->
              <?php if (!empty($product['short_description'])): ?>
                <p class="text-base sm:text-lg text-on-surface-variant leading-relaxed max-w-xl mb-6">
                  <?= htmlspecialchars($product['short_description']) ?>
                </p>
              <?php endif; ?>

              <!-- Prominent Price Display -->
              <div class="text-3xl sm:text-4xl font-display font-bold text-on-surface tracking-tight mb-6">
                <?= htmlspecialchars($displayPrice) ?>
              </div>

              <!-- Primary Action Area -->
              <div class="flex flex-col gap-3 w-full max-w-md">
                <div class="flex flex-wrap items-center gap-3">
                  <?php if ($hasDownload): ?>
                    <a href="<?= htmlspecialchars($downloadUrl) ?>" class="js-record-download btn btn-download flex-1 min-w-[200px] justify-center py-3.5 px-6 text-base font-semibold rounded-lg shadow-sm transition-all text-center flex items-center gap-2" data-product-id="<?= (int)$product['id'] ?>" download>
                      <span class="material-symbols-outlined text-[20px]">download</span>
                      <span>Direct Free Download</span>
                    </a>
                  <?php elseif ($hasExternal): ?>
                    <a href="<?= htmlspecialchars($product['external_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-external flex-1 min-w-[200px] justify-center py-3.5 px-6 text-base font-semibold rounded-lg shadow-sm transition-all text-center flex items-center gap-2">
                      <span><?= $isFree ? 'View on' : 'Buy on' ?> <?= htmlspecialchars($platform) ?></span>
                      <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                      <span class="sr-only">(opens in a new tab)</span>
                    </a>
                  <?php else: ?>
                    <div class="py-3 px-6 text-center rounded-lg bg-surface-container text-on-surface-variant font-medium text-sm w-full">
                      Currently unavailable
                    </div>
                  <?php endif; ?>

                  <?php if ($hasLiveDemo): ?>
                    <a href="<?= htmlspecialchars($resolvedLiveDemoUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary justify-center py-3.5 px-5 text-base font-medium rounded-lg border border-outline-variant/30 hover:bg-surface-container-high transition-all flex items-center gap-1.5 shrink-0">
                      <span>Live Demo</span>
                      <span class="material-symbols-outlined text-[18px]">arrow_outward</span>
                      <span class="sr-only">(opens in a new tab)</span>
                    </a>
                  <?php endif; ?>
                </div>

                <!-- One line supporting text under CTA -->
                <?php if ($hasDownload && !$hasExternal): ?>
                  <p class="text-xs text-on-surface-variant/80">
                    Direct download from this website. No account or checkout required.
                  </p>
                <?php elseif ($hasExternal): ?>
                  <p class="text-xs text-on-surface-variant/80">
                    Opens externally in a new tab.
                  </p>
                <?php endif; ?>

                <!-- User Interaction Bar (Like & Bookmark) -->
                <div class="user-interaction-bar flex items-center gap-6 mt-4 pt-4 border-t border-outline-variant/10" data-content-type="product" data-content-id="<?= (int)$product['id'] ?>" data-track-history="false">
                  <button type="button" class="user-like-btn flex items-center gap-2 text-on-surface-variant hover:text-red-500 transition-colors text-sm font-medium" title="Like this product">
                    <span class="material-symbols-outlined user-like-icon text-[20px]">favorite_border</span>
                    <span class="user-like-count">0</span>
                  </button>
                  <button type="button" class="user-bookmark-btn flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors text-sm font-medium" title="Save to bookmarks">
                    <span class="material-symbols-outlined user-bm-icon text-[20px]">bookmark_border</span>
                    <span>Bookmark</span>
                  </button>
                </div>
              </div>

            </div>
          </section>

          <!-- TAB / ANCHOR NAVIGATION ROW -->
          <nav class="w-full border-y border-outline-variant/15 bg-surface/95 backdrop-blur-md sticky top-0 z-20 my-10 lg:my-14 h-[46px]" aria-label="Product detail sections">
            <div class="flex items-center h-full gap-6 sm:gap-8 overflow-x-auto px-4 sm:px-0 scrollbar-none font-medium text-sm text-on-surface-variant">
              <a href="#overview" class="product-nav-link text-primary border-b-2 border-primary pb-1 -mb-[15px] whitespace-nowrap transition-colors font-semibold">Overview</a>
              <?php if (!empty($productResources) || $hasDownload): ?>
                <a href="#what-you-get" class="product-nav-link hover:text-primary border-b-2 border-transparent pb-1 -mb-[15px] whitespace-nowrap transition-colors">What You Get</a>
              <?php endif; ?>
              <a href="#specifications" class="product-nav-link hover:text-primary border-b-2 border-transparent pb-1 -mb-[15px] whitespace-nowrap transition-colors">Specifications</a>
              <a href="#usage-license" class="product-nav-link hover:text-primary border-b-2 border-transparent pb-1 -mb-[15px] whitespace-nowrap transition-colors">Usage &amp; License</a>
              <?php if (!empty($relatedItems)): ?>
                <a href="#related-products" class="product-nav-link hover:text-primary border-b-2 border-transparent pb-1 -mb-[15px] whitespace-nowrap transition-colors">Related Products</a>
              <?php endif; ?>
            </div>
          </nav>

          <!-- MAIN CONTENT SECTION: Two-column layout (Left: Overview / Right: Sidebar) -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-start">

            <!-- Left Main Column: Overview (8 cols) -->
            <div class="lg:col-span-8 flex flex-col gap-10">
              <?php if (!empty($product['description'])): ?>
                <section id="overview" class="scroll-mt-28 flex flex-col gap-4" aria-label="Overview">
                  <h2 class="text-2xl sm:text-3xl font-bold text-on-surface font-display tracking-tight border-b border-outline-variant/10 pb-4">Overview</h2>
                  <div class="prose prose-on-surface max-w-none text-on-surface-variant text-base sm:text-lg leading-relaxed">
                    <?= ArticleHtmlSanitizer::sanitize((string) $product['description']) ?>
                  </div>
                </section>
              <?php endif; ?>
            </div>

            <!-- Right Sidebar: What You Get, Specifications, Usage & License (4 cols) -->
            <div class="lg:col-span-4 flex flex-col gap-8 lg:sticky lg:top-32">

              <!-- WHAT YOU GET -->
              <?php if (!empty($productResources) || $hasDownload): ?>
                <section id="what-you-get" class="scroll-mt-28 flex flex-col gap-3" aria-label="What You Get">
                  <h3 class="text-lg font-bold text-on-surface font-display tracking-tight flex items-center gap-2 border-b border-outline-variant/10 pb-3">
                    <span class="material-symbols-outlined text-primary text-[20px]">checklist</span>
                    What You Get
                  </h3>
                  <ul class="flex flex-col divide-y divide-outline-variant/10 text-sm">
                    <?php if (!empty($productResources)): ?>
                      <?php foreach ($productResources as $resource): ?>
                        <?php
                            $pathWithoutQuery = explode('?', $resource['file_path'])[0];
                            $ext = pathinfo($pathWithoutQuery, PATHINFO_EXTENSION);
                            $extLabel = $ext ? strtoupper($ext) : 'FILE';
                        ?>
                        <li class="flex items-center justify-between gap-3 py-3 first:pt-1 last:pb-1 text-on-surface">
                          <div class="flex items-center gap-2.5 min-w-0">
                            <span class="material-symbols-outlined text-on-surface-variant text-[20px] shrink-0">description</span>
                            <div class="flex items-center gap-2 min-w-0">
                              <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-surface-container-high text-on-surface shrink-0"><?= htmlspecialchars($extLabel) ?></span>
                              <span class="font-medium truncate"><?= htmlspecialchars($resource['title']) ?></span>
                            </div>
                          </div>

                          <?php if ($isFree): ?>
                            <a href="/api/products/resource_download.php?id=<?= (int)$resource['id'] ?>" class="text-primary hover:underline text-xs font-semibold flex items-center gap-1 shrink-0 ml-2" aria-label="Download <?= htmlspecialchars($resource['title']) ?>">
                              <span>Download</span>
                              <span class="material-symbols-outlined text-[16px]">download</span>
                            </a>
                          <?php else: ?>
                            <span class="text-text-muted text-xs flex items-center gap-1 font-medium shrink-0 ml-2">
                              <span>Included</span>
                              <span class="material-symbols-outlined text-[16px]">lock</span>
                            </span>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    <?php elseif ($hasDownload): ?>
                      <li class="flex items-center gap-2.5 py-3 first:pt-1 last:pb-1 text-on-surface">
                        <span class="material-symbols-outlined text-on-surface-variant text-[20px]">folder</span>
                        <span class="font-medium">Digital Download File</span>
                      </li>
                    <?php endif; ?>
                  </ul>
                </section>
              <?php endif; ?>

              <!-- SPECIFICATIONS -->
              <section id="specifications" class="scroll-mt-28 flex flex-col gap-3" aria-label="Specifications">
                <h3 class="text-lg font-bold text-on-surface font-display tracking-tight flex items-center gap-2 border-b border-outline-variant/10 pb-3">
                  <span class="material-symbols-outlined text-primary text-[20px]">tune</span>
                  Specifications
                </h3>
                <dl class="flex flex-col divide-y divide-outline-variant/10 text-sm">
                  <div class="flex justify-between items-center py-2.5 first:pt-1">
                    <dt class="text-text-muted">Type</dt>
                    <dd class="text-on-surface font-medium"><?= $isFree ? 'Digital Download' : 'External Product' ?></dd>
                  </div>
                  <div class="flex justify-between items-center py-2.5">
                    <dt class="text-text-muted">Category</dt>
                    <dd class="text-on-surface font-medium"><?= htmlspecialchars($product['category']) ?></dd>
                  </div>
                  <?php if (!$isFree && !empty($product['platform'])): ?>
                    <div class="flex justify-between items-center py-2.5">
                      <dt class="text-text-muted">Platform</dt>
                      <dd class="text-on-surface font-medium"><?= htmlspecialchars($product['platform']) ?></dd>
                    </div>
                  <?php endif; ?>
                  <?php if (count($productResources) > 0): ?>
                    <div class="flex justify-between items-center py-2.5">
                      <dt class="text-text-muted">Files</dt>
                      <dd class="text-on-surface font-medium"><?= count($productResources) ?> included</dd>
                    </div>
                  <?php endif; ?>
                  <div class="flex justify-between items-center py-2.5 last:pb-1">
                    <dt class="text-text-muted">Published</dt>
                    <dd class="text-on-surface font-medium"><?= !empty($product['created_at']) ? date('M d, Y', strtotime($product['created_at'])) : 'Recent' ?></dd>
                  </div>
                </dl>
              </section>

              <!-- USAGE & LICENSE -->
              <section id="usage-license" class="scroll-mt-28 flex flex-col gap-2.5 p-5 rounded-xl bg-surface-container-low/70 border border-outline-variant/15" aria-label="Usage and License">
                <h4 class="text-sm font-semibold text-on-surface flex items-center gap-2">
                  <span class="material-symbols-outlined text-primary text-[18px]">gavel</span>
                  Usage &amp; License
                </h4>
                <p class="text-xs text-on-surface-variant leading-relaxed">
                  Please refer to the included documentation or the external provider for commercial and personal usage rights.
                </p>
              </section>

            </div>

          </div>

          <!-- RELATED PRODUCTS SECTION (Bottom, Full Width Card Grid) -->
          <?php if (!empty($relatedItems)): ?>
            <section id="related-products" class="scroll-mt-28 mt-16 lg:mt-24 pt-10 border-t border-outline-variant/15 w-full" aria-label="Related Products">
              <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div>
                  <h2 class="text-2xl sm:text-3xl font-bold text-on-surface font-display tracking-tight">Related Products</h2>
                  <p class="text-sm text-on-surface-variant mt-1">Explore similar tools and engineering resources</p>
                </div>
                <a href="/store" class="text-xs font-mono font-semibold text-primary hover:underline flex items-center gap-1">
                  <span>View All in Store</span>
                  <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                </a>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($relatedItems as $item): ?>
                  <a href="<?= htmlspecialchars($item['url']) ?>" class="group flex flex-col rounded-xl bg-surface-container-lowest border border-outline-variant/15 hover:border-primary/40 transition-all overflow-hidden shadow-sm hover:shadow-md">
                    <?php if (!empty($item['image'])): ?>
                      <div class="aspect-video w-full bg-surface-container-low overflow-hidden relative">
                        <img decoding="async" src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="hidden items-center justify-center w-full h-full bg-surface-container-low text-outline">
                          <span class="material-symbols-outlined text-[2rem] text-primary/70">inventory_2</span>
                        </div>
                      </div>
                    <?php else: ?>
                      <div class="aspect-video w-full bg-surface-container-low flex items-center justify-center text-outline">
                        <span class="material-symbols-outlined text-[2.5rem] text-primary/70">inventory_2</span>
                      </div>
                    <?php endif; ?>

                    <div class="p-5 flex flex-col flex-1 justify-between gap-3">
                      <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between gap-2">
                          <span class="font-mono text-[10px] font-semibold uppercase tracking-wider text-text-muted"><?= htmlspecialchars($item['category']) ?></span>
                          <span class="text-xs font-mono text-primary font-medium"><?= htmlspecialchars($item['type_label']) ?></span>
                        </div>
                        <h3 class="text-base font-semibold text-on-surface group-hover:text-primary transition-colors line-clamp-2">
                          <?= htmlspecialchars($item['title']) ?>
                        </h3>
                      </div>
                      <div class="flex items-center gap-1 text-primary text-xs font-semibold pt-2 border-t border-outline-variant/10">
                        <span>Learn more</span>
                        <span class="material-symbols-outlined text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

        </div>
      <?php endif; ?>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>
  <script src="/assets/js/user_interactions.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // 1. Gallery Image Switcher & Thumbnail Carousel
      const mainImg = document.getElementById('mainProductImage');
      const thumbs = document.querySelectorAll('.gallery-thumb-btn');
      const strip = document.getElementById('galleryThumbStrip');
      const prevBtn = document.getElementById('prevThumbBtn');
      const nextBtn = document.getElementById('nextThumbBtn');

      if (mainImg && thumbs.length > 0) {
        thumbs.forEach(btn => {
          btn.addEventListener('click', function() {
            const imgEl = this.querySelector('img');
            if (!imgEl) return;

            // Prefer the original full-resolution URL stored in data-full-src.
            const fullSrc = this.dataset.fullSrc || imgEl.src;

            mainImg.src = fullSrc;
            mainImg.removeAttribute('srcset');
            mainImg.alt = imgEl.alt;
            mainImg.style.display = 'block';
            if (mainImg.nextElementSibling) {
              mainImg.nextElementSibling.style.display = 'none';
            }

            // Update active states
            thumbs.forEach(t => {
              t.classList.remove('border-primary', 'opacity-100');
              t.classList.add('border-transparent', 'opacity-60');
              t.setAttribute('aria-selected', 'false');
            });

            this.classList.remove('border-transparent', 'opacity-60');
            this.classList.add('border-primary', 'opacity-100');
            this.setAttribute('aria-selected', 'true');
          });
        });
      }

      if (strip && prevBtn && nextBtn) {
        const updateScrollButtons = () => {
          const maxScroll = strip.scrollWidth - strip.clientWidth;
          prevBtn.disabled = strip.scrollLeft <= 5;
          nextBtn.disabled = strip.scrollLeft >= maxScroll - 5;
        };

        prevBtn.addEventListener('click', () => {
          strip.scrollBy({ left: -220, behavior: 'smooth' });
        });
        nextBtn.addEventListener('click', () => {
          strip.scrollBy({ left: 220, behavior: 'smooth' });
        });
        strip.addEventListener('scroll', updateScrollButtons, { passive: true });
        updateScrollButtons();
      }

      // 2. Tab / Anchor Navigation Active Spy
      const navLinks = document.querySelectorAll('.product-nav-link');
      if (navLinks.length > 0) {
        const observedSections = [];
        navLinks.forEach(link => {
          const hash = link.getAttribute('href');
          if (hash && hash.startsWith('#')) {
            const el = document.getElementById(hash.substring(1));
            if (el) observedSections.push({ link, el });
          }
        });

        if ('IntersectionObserver' in window && observedSections.length > 0) {
          const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
              if (entry.isIntersecting) {
                navLinks.forEach(l => {
                  l.classList.remove('text-primary', 'border-primary', 'font-semibold');
                  l.classList.add('border-transparent');
                });
                const matched = observedSections.find(s => s.el === entry.target);
                if (matched) {
                  matched.link.classList.remove('border-transparent');
                  matched.link.classList.add('text-primary', 'border-primary', 'font-semibold');
                }
              }
            });
          }, { rootMargin: '-20% 0px -70% 0px' });

          observedSections.forEach(s => observer.observe(s.el));
        }
      }
    });
  </script>
</body>
</html>
