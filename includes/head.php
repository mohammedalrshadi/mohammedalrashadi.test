<?php
require_once __DIR__ . '/settings.php';

if (!isset($pageTitle)) {
    $pageTitle = getSiteSetting('seo.default_title', 'Mohammed Alrashadi // Systems & Software Engineering');
}
if (!isset($pageDescription)) {
    $pageDescription = getSiteSetting('seo.default_description', 'Systems, databases, computing fundamentals, and backend software engineering projects and research by Mohammed Alrashadi.');
}
$socialTitle = buildPageTitle($pageTitle, true);
$pageTitle = buildPageTitle($pageTitle);
if (!isset($canonicalUrl)) {
    $canonicalUrl = getSiteSetting('website.canonical_url', 'https://mohammedalrashadi.com/');
}
$siteCanonical = rtrim(getSiteSetting('website.canonical_url', 'https://mohammedalrashadi.com'), '/');

if (!isset($ogImage) || empty($ogImage)) {
    $ogSetting = getSiteSetting('branding.og_image_url', '/assets/logo/logo.png');
    // If the DB value is the old monogram, override it
    if (strpos($ogSetting, 'monogram.png') !== false) {
        $ogSetting = '/assets/logo/logo.png';
    }
    $ogImage = (strpos($ogSetting, 'http://') === 0 || strpos($ogSetting, 'https://') === 0)
        ? $ogSetting
        : $siteCanonical . '/' . ltrim($ogSetting, '/');
} elseif (strpos($ogImage, 'http://') !== 0 && strpos($ogImage, 'https://') !== 0) {
    $ogImage = $siteCanonical . '/' . ltrim($ogImage, '/');
}

$faviconUrl = getSiteSetting('branding.favicon_url', '/assets/logo/logo.png');
$siteName = getSiteSetting('website.platform_name', 'Mohammed Alrashadi');
$robots = $robots ?? 'index,follow';
$ogType = $ogType ?? 'website';
$ogLocale = $ogLocale ?? 'en_US';
?>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>"/>

<meta name="robots" content="<?= htmlspecialchars($robots) ?>"/>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>"/>
<?php
// CSRF protection: expose session token in head for authenticated users
if (!function_exists('isUserLoggedIn')) {
    require_once dirname(__DIR__) . '/api/auth/guard.php';
}
if (isUserLoggedIn()) {
    $__headCsrf = getCsrfToken();
    if (!empty($__headCsrf)) {
        echo '<meta name="csrf-token" content="' . htmlspecialchars($__headCsrf, ENT_QUOTES, 'UTF-8') . '"/>' . "\n";
    }
}
?>

<!-- Open Graph / Social -->
<meta property="og:type" content="<?= htmlspecialchars($ogType) ?>"/>
<meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>"/>
<meta property="og:locale" content="<?= htmlspecialchars($ogLocale) ?>"/>
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>"/>
<meta property="og:title" content="<?= htmlspecialchars($socialTitle) ?>"/>
<meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>"/>
<?php if ($ogType === 'article'): ?>
<?php if (!empty($articlePublishedTime)): ?>
<meta property="article:published_time" content="<?= htmlspecialchars($articlePublishedTime) ?>"/>
<?php endif; ?>
<?php if (!empty($articleModifiedTime)): ?>
<meta property="article:modified_time" content="<?= htmlspecialchars($articleModifiedTime) ?>"/>
<?php endif; ?>
<?php if (!empty($articleAuthor)): ?>
<meta property="article:author" content="<?= htmlspecialchars($articleAuthor) ?>"/>
<?php endif; ?>
<?php endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:url" content="<?= htmlspecialchars($canonicalUrl) ?>"/>
<meta name="twitter:title" content="<?= htmlspecialchars($socialTitle) ?>"/>
<meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>"/>

<!-- Favicon -->
<?php require_once __DIR__ . '/favicon.php'; ?>

<!-- RSS Feed Autodiscovery -->
<link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars(getSiteSetting('website.platform_name', 'Mohammed Alrashadi')) ?> — RSS Feed" href="/rss.xml"/>

<!-- Google Fonts: Inter & JetBrains Mono -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=JetBrains+Mono:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=block" as="style" onload="this.onload=null;this.rel='stylesheet'"/>
<noscript><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&amp;display=block" rel="stylesheet"/></noscript>

<!-- Instant Theme Initialization Script (Zero Flash, 3-Theme System) -->
<script>
  (function() {
    try {
      var stored = localStorage.getItem('site-theme') || localStorage.getItem('theme');
      var validThemes = ['light', 'dark', 'green'];
      var theme = (stored && validThemes.indexOf(stored) !== -1) ? stored : null;
      if (!theme) {
        var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        theme = systemDark ? 'dark' : 'light';
      }
      document.documentElement.setAttribute('data-theme', theme);
    } catch(e) {}
  })();
</script>

<!-- Precompiled Tailwind CSS & Global Design System (Zero Runtime CDN) -->
<link rel="stylesheet" href="/css/tailwind.css?v=<?= file_exists(__DIR__ . '/../css/tailwind.css') ? filemtime(__DIR__ . '/../css/tailwind.css') : '1.0' ?>"/>
<link rel="stylesheet" href="/css/styles.css?v=<?= file_exists(__DIR__ . '/../css/styles.css') ? filemtime(__DIR__ . '/../css/styles.css') : '1.0' ?>"/>
<?php if (isset($currentPage) && $currentPage === 'home'): ?>
<link rel="stylesheet" href="/css/world-tree.css?v=1.0"/>
<script src="/js/world-clock.js?v=1.0" defer></script>
<script src="/js/world-tree.js?v=1.0" defer></script>
<?php endif; ?>
<script src="/js/theme-toggle.js?v=<?= file_exists(__DIR__ . '/../js/theme-toggle.js') ? filemtime(__DIR__ . '/../js/theme-toggle.js') : '1.0' ?>" defer></script>
