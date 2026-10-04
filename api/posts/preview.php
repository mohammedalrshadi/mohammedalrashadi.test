<?php
// ============================================================
// ARTICLE PREVIEW API
// POST /api/posts/preview.php
// Requires: authenticated admin session + CSRF token
// Renders unsaved draft content through the EXACT same rendering
// pipeline as post.php without mutating or touching the database.
// ============================================================

error_reporting(0);
ini_set('log_errors', '1');

require_once dirname(dirname(__DIR__)) . '/api/auth/guard.php';
require_once dirname(__DIR__) . '/posts/helper.php';
require_once dirname(dirname(__DIR__)) . '/includes/settings.php';

header('Content-Type: application/json');

requireAuth();
requireCSRF();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = file_get_contents('php://input');
$input = json_decode($body, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data payload.']);
    exit;
}

$title       = isset($input['title']) ? trim((string)$input['title']) : 'Untitled Draft';
$category    = isset($input['category']) ? trim((string)$input['category']) : 'General';
$rawContent  = isset($input['content']) ? (string)$input['content'] : '';
$imageUrl    = isset($input['image_url']) ? trim((string)$input['image_url']) : '';
$publishDate = isset($input['publish_date']) ? trim((string)$input['publish_date']) : date('Y-m-d');
$quoteAr     = isset($input['quote_ar']) ? trim((string)$input['quote_ar']) : '';
$quoteEn     = isset($input['quote_en']) ? trim((string)$input['quote_en']) : '';

// 1. Authoritative server-side HTML sanitization (matching create/update pipeline)
$cleanContent = ArticleHtmlSanitizer::sanitize($rawContent);

// 2. Metrics (matching post.php)
$textOnly = strip_tags($cleanContent);
$wordCount = str_word_count($textOnly);
$readTime = max(1, (int) ceil($wordCount / 200));
$dateFormatted = !empty($publishDate) ? date('F d, Y', strtotime($publishDate)) : date('F d, Y');

$profile = getSiteProfile();

// 3. Exact post.php rendering pipeline
ob_start();
?>
<article class="article-preview-document" style="max-width: 820px; margin: 0 auto; padding: 24px 16px; font-family: var(--font-sans); color: var(--text-primary);">
  <!-- Header -->
  <header style="margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid var(--border-subtle);">
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">
      <span class="status-badge" style="background: var(--color-primary-container); color: var(--color-on-primary-container); font-size: 11px; font-weight: 700; text-transform: uppercase;">
        <?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>
      </span>
      <span style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);">
        <?= htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8') ?>
      </span>
      <span style="color: var(--border-medium);">•</span>
      <span style="font-size: 12px; color: var(--text-muted); font-family: var(--font-mono);">
        <?= $readTime ?> min read (<?= $wordCount ?> words)
      </span>
      <span style="margin-left: auto; font-size: 11px; padding: 2px 8px; border-radius: 4px; background: var(--bg-surface-elevated); color: var(--warning); border: 1px solid var(--border-medium); font-family: var(--font-mono);">
        <i class="fas fa-eye" style="margin-right: 4px;"></i> Unsaved Draft Preview
      </span>
    </div>

    <h1 style="font-size: 28px; font-weight: 800; line-height: 1.3; margin: 0 0 16px 0; color: var(--text-primary); letter-spacing: -0.02em;">
      <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
    </h1>

    <?php if ($quoteAr !== '' || $quoteEn !== ''): ?>
      <div style="margin: 16px 0; padding: 14px 18px; border-left: 3px solid var(--accent); background: var(--bg-surface-elevated); border-radius: var(--radius-xs);">
        <?php if ($quoteAr !== ''): ?>
          <p style="margin: 0 0 6px 0; font-size: 14px; font-style: italic; color: var(--text-primary);" dir="rtl"><?= htmlspecialchars($quoteAr, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($quoteEn !== ''): ?>
          <p style="margin: 0; font-size: 13.5px; font-style: italic; color: var(--text-secondary);"><?= htmlspecialchars($quoteEn, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($imageUrl !== ''): ?>
      <div style="margin-top: 20px; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-subtle); max-height: 380px;">
        <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;">
      </div>
    <?php endif; ?>
  </header>

  <!-- Body Content: EXACT pipeline of post.php -->
  <div class="prose prose-editorial" style="line-height: 1.8; font-size: 15px; color: var(--text-secondary); display: flex; flex-direction: column; gap: 16px;">
    <?= renderArticleContent($cleanContent) ?>
  </div>

  <!-- Author Dossier Block (matching post.php line 204) -->
  <footer style="margin-top: 40px; padding: 20px; border-radius: var(--radius-md); background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); display: flex; align-items: center; gap: 16px;">
    <?php if (!empty($profile['avatar_url'])): ?>
      <img src="<?= htmlspecialchars($profile['avatar_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($profile['name'], ENT_QUOTES, 'UTF-8') ?>" style="width: 52px; height: 52px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid var(--border-medium);">
    <?php endif; ?>
    <div>
      <div style="font-size: 14.5px; font-weight: 700; color: var(--text-primary);"><?= htmlspecialchars($profile['name'], ENT_QUOTES, 'UTF-8') ?></div>
      <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">
        <?= htmlspecialchars($profile['role'], ENT_QUOTES, 'UTF-8') ?> — "<?= htmlspecialchars($profile['motto'], ENT_QUOTES, 'UTF-8') ?>"
      </div>
    </div>
  </footer>
</article>
<?php
$renderedHtml = ob_get_clean();

echo json_encode([
    'success' => true,
    'html'    => $renderedHtml,
    'meta'    => [
        'title'        => $title,
        'category'     => $category,
        'words'        => $wordCount,
        'read_time'    => $readTime,
        'publish_date' => $dateFormatted,
    ],
]);

