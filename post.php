<?php
$currentPage = 'writing';

$rawId = $_GET['id'] ?? null;
$rawSlug = $_GET['slug'] ?? null;

$validatedId = filter_var($rawId, FILTER_VALIDATE_INT, [
    'options' => [
        'min_range' => 1,
        'max_range' => 2147483647,
    ],
]);
$postId = ($validatedId !== false) ? $validatedId : null;
$postSlug = ($rawSlug !== null && preg_match('/^[a-zA-Z0-9_\-]+$/', $rawSlug)) ? $rawSlug : null;

require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/api/auth/guard.php'; // needed for getCsrfToken() in review form
$_reviewCsrfToken = getCsrfToken();

$post = null;
$reviews = [];

// Database Query with error handling
// Database Query with error handling
if (($postId !== null || $postSlug !== null) && file_exists(__DIR__ . '/api/config.local.php')) {
    try {
        require_once __DIR__ . '/api/db.php';
        require_once __DIR__ . '/src/autoload.php';
        
        $pdo = getDB();
        $postRepo = new \Domain\Content\PostRepository($pdo);
        
        // Fetch article
        if ($postSlug !== null) {
            $post = $postRepo->getPublishedBlogBySlug($postSlug);
        } else {
            $post = $postRepo->getPublishedBlogById($postId);
        }
        
        // Handle legacy ?id= redirect
        if ($postId !== null && $postSlug === null && $post && !empty($post['slug'])) {
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: /articles/' . rawurlencode($post['slug']));
            exit;
        }
        
        if ($post) {
            // Fetch approved reader reviews
            $postId = $post['id'];
            $reviews = $postRepo->getApprovedReviews($postId);

            // Increment view count
            $postRepo->incrementViews($postId);
        } else if ($postSlug !== null) {
            // Check redirect table if slug is not found
            $redir = $postRepo->getRedirect('/articles/' . $postSlug);
            if ($redir) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $redir);
                exit;
            }
        }
    } catch (Exception $e) {
        $post = null;
    }
}

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/api/posts/helper.php';
$profile = getSiteProfile();

if ($post) {
    $ogType = 'article';
    $pageTitle = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'];
    $pageDescription = !empty($post['meta_description']) ? $post['meta_description'] : mb_substr(strip_tags($post['content']), 0, 160) . '...';
    $canonicalUrl = !empty($post['slug']) ? 'https://mohammedalrashadi.com/articles/' . rawurlencode($post['slug']) : 'https://mohammedalrashadi.com/post.php?id=' . (int)$post['id'];
    $words = str_word_count(strip_tags($post['content']));
    $readTime = max(1, (int) ceil($words / 200));
    $dateFormatted = !empty($post['created_at']) ? date('F d, Y', strtotime($post['created_at'])) : 'Recent';
    $pubDate = !empty($post['published_at']) ? $post['published_at'] : $post['created_at'];
    $modDate = !empty($post['updated_at']) ? $post['updated_at'] : $pubDate;
    $articlePublishedTime = date('c', strtotime($pubDate ?: 'now'));
    $articleModifiedTime = date('c', strtotime($modDate ?: 'now'));
    $articleAuthor = 'Mohammed Alrashadi';
    $dateFormatted = !empty($pubDate) ? date('F d, Y', strtotime($pubDate)) : 'Recent';
    if (!empty($post['image_url'])) {
        $ogImage = $post['image_url'];
    }
} else {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    $robots = 'noindex,follow';
    $pageTitle = 'Article Not Found';
    $pageDescription = 'The requested technical essay was not found.';
    $canonicalUrl = 'https://mohammedalrashadi.com/articles.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
  

  <?php if ($post): 
    $articleImage = !empty($post['image_url'])
        ? (strpos($post['image_url'], 'http') === 0 ? $post['image_url'] : 'https://mohammedalrashadi.com/' . ltrim($post['image_url'], '/'))
        : 'https://mohammedalrashadi.com/assets/logo/logo.png';

    $techArticleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'TechArticle',
        'headline' => $post['title'],
        'description' => $pageDescription,
        'image' => $articleImage,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl
        ],
        'author' => [
            '@type' => 'Person',
            'name' => 'Mohammed Alrashadi',
            'url' => 'https://mohammedalrashadi.com/about.php'
        ],
        'publisher' => [
            '@type' => 'Person',
            'name' => 'Mohammed Alrashadi',
            'url' => 'https://mohammedalrashadi.com/about.php'
        ],
        'datePublished' => $articlePublishedTime,
        'dateModified' => $articleModifiedTime
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
                'name' => 'Writing',
                'item' => 'https://mohammedalrashadi.com/articles.php'
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $post['title'],
                'item' => $canonicalUrl
            ]
        ]
    ];
  ?>
  <!-- JSON-LD Structured Data: TechArticle & BreadcrumbList -->
  <script type="application/ld+json">
  <?= json_encode($techArticleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <script type="application/ld+json">
  <?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
  </script>
  <?php endif; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary">
  
  <!-- Scroll Reading Progress Bar -->
  <div class="reading-progress-track" aria-hidden="true">
    <div class="reading-progress-fill" id="reading-progress-bar"></div>
  </div>

  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="relative z-0 w-full pt-16 bg-background min-h-[calc(100vh-16rem)]">
    <div class="flex flex-col w-full">
      <?php if (!$post): ?>
        <div class="max-w-[800px] w-full mx-auto px-gutter py-space-2xl text-center flex flex-col items-center justify-center gap-space-md my-space-xl">
          <div class="p-space-lg rounded-full bg-surface-container-low border border-outline-variant/10 text-outline">
            <span class="material-symbols-outlined text-[3rem]">article</span>
          </div>
          <h1 class="font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface font-bold">Article Not Found</h1>
          <p class="font-body-md text-body-md text-on-surface-variant max-w-lg leading-relaxed">
            The requested technical essay was not found or is no longer available in the published engineering archive.
          </p>
          <div class="pt-space-md">
            <a href="/articles.php" class="px-space-lg py-space-sm rounded-lg bg-primary text-on-primary font-label-code text-label-code font-semibold hover:opacity-90 transition-all inline-flex items-center gap-2">
              <span class="material-symbols-outlined text-[1.125rem]">arrow_back</span>
              <span>Back to Writing Archive</span>
            </a>
          </div>
        </div>
      <?php else: ?>
      <!-- Top Breadcrumb & Metadata Strip -->
      <section class="w-full border-b border-outline-variant/10 bg-surface-container-lowest/50 py-space-sm">
        <div class="container-study flex flex-wrap items-center justify-between gap-space-sm font-label-code text-label-micro text-outline">
          <div class="flex items-center gap-space-xs">
            <a href="/index.php" class="hover:text-primary transition-colors">HOME</a>
            <span>/</span>
            <a href="/articles.php" class="hover:text-primary transition-colors">WRITING</a>
            <span>/</span>
            <span class="text-primary font-semibold">ARTICLE #<?= (int)$post['id'] ?></span>
          </div>
          <div class="flex items-center gap-space-md">
            <span><?= $readTime ?> MIN READ</span>
            <span>•</span>
            <a href="/articles.php" class="text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[1rem]">arrow_back</span>
              All Articles
            </a>
          </div>
        </div>
      </section>

      <!-- Editorial Reading Canvas (Reading Container ≈ 680px) -->
      <article class="container-reading py-space-2xl flex flex-col gap-space-xl">
        
        <!-- Header -->
        <header class="flex flex-col gap-space-md border-b border-outline-variant/10 pb-space-lg">
          <div class="flex flex-wrap items-center gap-space-xs text-on-surface-variant font-label-micro text-label-micro">
            <span class="px-space-sm py-0.5 rounded bg-primary/10 text-primary font-semibold uppercase">
              <?= htmlspecialchars($post['category'] ?: 'ENGINEERING') ?>
            </span>
            <span>•</span>
            <span><?= htmlspecialchars($dateFormatted) ?></span>
          </div>

          <h1 class="text-headline-lg-mobile font-headline-lg md:text-[2.5rem] md:leading-[1.15] text-on-surface tracking-tight font-bold">
            <?= htmlspecialchars($post['title']) ?>
          </h1>

          <div class="flex items-center justify-between text-on-surface-variant font-label-code text-label-micro pt-space-xs">
            <div class="flex items-center gap-space-xs">
              <img src="<?= htmlspecialchars($profile['avatar_url']) ?>" alt="<?= htmlspecialchars($profile['name']) ?>" width="24" height="24" loading="lazy" decoding="async" class="w-6 h-6 rounded-full object-cover"/>
              <span class="text-on-surface font-medium"><?= htmlspecialchars($profile['name']) ?></span>
            </div>
            <span class="text-outline"><?= number_format((int)($post['views'] ?? 0)) ?> Lifetime Reads</span>
          </div>

          <!-- User Platform Interaction Bar (Likes, Bookmarks, History Tracking) -->
          <div class="user-interaction-bar flex items-center justify-between pt-3 border-t border-outline-variant/10" 
               data-content-type="writing" 
               data-content-id="<?= (int)$post['id'] ?>"
               data-track-history="true">
            <div class="flex items-center gap-2">
              <button type="button" class="user-like-btn flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-text-secondary hover:text-red-400 hover:border-red-400/40 transition-colors text-xs font-mono" title="Like this article">
                <span class="material-symbols-outlined user-like-icon text-[18px]">favorite_border</span>
                <span class="user-like-count">0</span>
              </button>
              <button type="button" class="user-bookmark-btn flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-text-secondary hover:text-primary hover:border-primary/40 transition-colors text-xs font-mono" title="Save to bookmarks">
                <span class="material-symbols-outlined user-bm-icon text-[18px]">bookmark_border</span>
                <span>Bookmark</span>
              </button>
            </div>
          </div>
        </header>

        <!-- Cover Image (if set) -->
        <?php if (!empty($post['image_url'])): ?>
          <div class="w-full rounded-xl overflow-hidden bg-surface-container-lowest border border-outline-variant/10 shadow-lg">
            <?= responsiveImage($post['image_url'], $post['title'], [
                'class' => 'w-full h-auto object-cover',
                'priority' => true,
                'sizes' => '(max-width: 680px) 100vw, 680px'
            ]) ?>
          </div>
        <?php endif; ?>

        <!-- Body Content -->
        <div class="prose prose-editorial max-w-none font-body-lg text-body-lg text-on-surface leading-[1.8] flex flex-col gap-space-md">
          <?= renderArticleContent($post['content'] ?? '') ?>
        </div>

        <!-- Author Dossier Footer Block -->
        <div class="mt-space-xl p-space-lg rounded-xl bg-surface-container-low border border-outline-variant/10 flex flex-col sm:flex-row items-center gap-space-md">
          <img src="<?= htmlspecialchars($profile['avatar_url']) ?>" alt="<?= htmlspecialchars($profile['name']) ?>" width="64" height="64" loading="lazy" decoding="async" class="w-16 h-16 rounded-full object-cover ring-2 ring-primary/20 shrink-0"/>
          <div class="flex flex-col gap-1 text-center sm:text-left">
            <span class="font-headline-sm text-headline-sm text-on-surface font-semibold"><?= htmlspecialchars($profile['name']) ?></span>
            <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
              <?= htmlspecialchars($profile['role']) ?> focusing on systems, database internals, and distributed architecture invariants under the motto "<?= htmlspecialchars($profile['motto']) ?>".
            </p>
            <div class="pt-1 flex items-center justify-center sm:justify-start gap-space-sm font-label-code text-label-micro">
              <a href="/about.php" class="text-primary hover:underline">About <?= htmlspecialchars($profile['short_name'] ?: $profile['name']) ?></a>
              <span class="text-outline">•</span>
              <a href="/projects.php" class="text-primary hover:underline">Explore Projects</a>
            </div>
          </div>
        </div>

        <!-- Reader Feedback & Peer Reviews -->
        <section class="mt-space-xl flex flex-col gap-space-lg border-t border-outline-variant/10 pt-space-xl" id="reader-reviews">
          <div class="flex items-center justify-between">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Reader Reviews &amp; Discourse</h2>
            <span class="font-label-code text-label-micro text-outline"><?= count($reviews) ?> Verified Reviews</span>
          </div>

          <!-- Reviews List -->
          <?php if (!empty($reviews)): ?>
            <div class="flex flex-col gap-space-md">
              <?php foreach ($reviews as $rev): ?>
                <div class="p-space-md rounded-lg bg-surface-container-low border border-outline-variant/10 flex flex-col gap-1">
                  <div class="flex items-center justify-between font-label-micro text-label-micro text-outline">
                    <span class="text-on-surface font-medium"><?= htmlspecialchars($rev['name']) ?></span>
                    <span><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                  </div>
                  <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                    <?= htmlspecialchars($rev['message']) ?>
                  </p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="font-body-sm text-body-sm text-outline italic">
              No public reviews approved for this article yet. Share your technical feedback below.
            </p>
          <?php endif; ?>

          <!-- Review Submission Form -->
          <form id="review-submission-form" class="flex flex-col gap-space-sm p-space-md rounded-xl bg-surface-container-low border border-outline-variant/10 mt-space-xs">
            <span class="font-label-code text-label-micro text-primary uppercase font-semibold">Leave Technical Feedback</span>
            
            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"/>
            <input type="hidden" id="review_csrf_token" value="<?= htmlspecialchars($_reviewCsrfToken, ENT_QUOTES, 'UTF-8') ?>"/>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-sm">
              <input type="text" name="name" required placeholder="Your Name or Handle" class="input-text"/>
              <input type="email" name="email" required placeholder="Your Email (kept private)" class="input-text"/>
            </div>

            <textarea name="message" required rows="3" placeholder="Critique, questions, or architectural observations..." class="input-text resize-y"></textarea>

            <div class="flex items-center justify-between pt-1">
              <span id="review-form-status" class="font-label-code text-label-micro text-outline">Reviews undergo review prior to publishing.</span>
              <button type="submit" class="px-space-md py-1.5 rounded-lg bg-primary text-on-primary font-label-code text-label-code font-semibold hover:bg-secondary transition-colors flex items-center gap-1">
                <span>Submit Feedback</span>
                <span class="material-symbols-outlined text-[14px]">send</span>
              </button>
            </div>
          </form>
        </section>

      </article>
      
      <?php
      $relatedItems = [];
      if (isset($pdo) && $post) {
          $resolver = new \Domain\Content\RelatedContentResolver();
          $relatedItems = $resolver->getRelatedBlogs($pdo, $post['id'], $post['category'] ?? '', 3);
      }
      require __DIR__ . '/includes/related_content.php';
      ?>

      <?php endif; ?>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Reading Progress and Interaction Script -->
  <script>
    (function() {
      // 1. Reading Progress Bar
      var progressBar = document.getElementById('reading-progress-bar');
      window.addEventListener('scroll', function() {
        if (!progressBar) return;
        var docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        if (docHeight > 0) {
          var scrolled = (window.scrollY / docHeight) * 100;
          progressBar.style.width = Math.min(100, Math.max(0, scrolled)) + '%';
        }
      });

      // 2. Code Copy Action
      var copyButtons = document.querySelectorAll('.copy-code-btn');
      copyButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
          var code = btn.getAttribute('data-code') || '';
          navigator.clipboard.writeText(code).then(function() {
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px] text-primary">check</span> Copied!';
            setTimeout(function() {
              btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">content_copy</span> Copy';
            }, 2000);
          });
        });
      });

      // 3. Review Submission Handler
      var reviewForm = document.getElementById('review-submission-form');
      var statusDisplay = document.getElementById('review-form-status');
      if (reviewForm && statusDisplay) {
        reviewForm.addEventListener('submit', function(e) {
          e.preventDefault();
          var formData = new FormData(reviewForm);
          var csrfToken = document.getElementById('review_csrf_token')?.value || '';
          var data = {
            post_id: formData.get('post_id'),
            name: formData.get('name'),
            email: formData.get('email'),
            message: formData.get('message')
          };

          statusDisplay.textContent = 'Submitting feedback...';
          fetch('/api/reviews/submit.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(data)
          })
          .then(function(res) { return res.json(); })
          .then(function(res) {
            if (res.success) {
              statusDisplay.textContent = 'Thank you! Your review has been submitted for approval.';
              statusDisplay.className = 'font-label-code text-label-micro text-primary';
              reviewForm.reset();
            } else {
              statusDisplay.textContent = res.message || 'Submission failed. Please try again.';
              statusDisplay.className = 'font-label-code text-label-micro text-error';
            }
          })
          .catch(function() {
            statusDisplay.textContent = 'Network error. Please check your connection and try again.';
            statusDisplay.className = 'font-label-code text-label-micro text-error';
          });
        });
      }
    })();
  </script>
  <script src="/assets/js/user_interactions.js"></script>
</body>
</html>
