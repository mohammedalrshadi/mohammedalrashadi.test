<?php
// ============================================================
// GLOBAL PUBLIC FOOTER [IMP-035]
// One-line layout: Brand | Explore & Social | Legal
// Copyright bar underneath.
// Legal compliance: Privacy, Terms, Support.
// Zero fake links, no 'Admin Studio' in public footer, example.com links filtered.
// ============================================================

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/social_icons.php';

$footerProfile = getSiteProfile();

// ---- SOCIAL: admin-controlled via existing social_links table ----
// Enabled, non-empty, and filters out template/placeholder URLs like example.com
$footerSocials = [];
if (file_exists(__DIR__ . '/../api/config.local.php')) {
    try {
        require_once __DIR__ . '/../api/db.php';
        $pdo = getDB();
        $stmt = $pdo->query(
            "SELECT platform, name, url, icon_key
               FROM social_links
              WHERE is_enabled = 1
                AND url IS NOT NULL
                AND TRIM(url) <> ''
                AND url NOT LIKE '%example.com%'
              ORDER BY sort_order ASC, id ASC"
        );
        $footerSocials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[footer] social_links query failed: ' . $e->getMessage());
        $footerSocials = [];
    }
}

$footerShowContact = !empty($footerProfile['show_email'])
                  && !empty($footerProfile['public_email']);

$footerName = !empty($footerProfile['name']) ? $footerProfile['name'] : 'Mohammed Alrashadi';
?>
<!-- Global Footer -->
<footer class="site-footer py-10" aria-label="Site footer">
  <div class="page-container site-footer__inner">

    <a href="/index.php" class="site-footer__brand text-decoration-none" title="Home">
      <?php include __DIR__ . '/logo.php'; ?>
    </a>

    <!-- Explore links, with social media underneath -->
    <div class="site-footer__explore">
      <nav aria-label="Footer navigation">
        <ul class="site-footer__list">
          <li><a href="/index.php" class="hover:text-primary transition-colors">Home</a></li>
          <?php 
          require_once __DIR__ . '/nav_items.php';
          foreach ($navItems as $key => $item): 
            if (!empty($item['show_in_footer'])): 
              $fLabel = isset($item['footer_label']) ? $item['footer_label'] : $item['label'];
          ?>
            <li><a href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($fLabel) ?></a></li>
          <?php 
            endif;
          endforeach; 
          ?>
        </ul>
      </nav>

      <?php if (!empty($footerSocials) || $footerShowContact): ?>
        <div class="site-footer__social-section mt-6">
          <h3 class="sr-only">Connect</h3>
          <ul class="site-footer__social flex flex-wrap" aria-label="Social media">
          <?php foreach ($footerSocials as $soc): ?>
            <?php $iconKey = !empty($soc['icon_key']) ? $soc['icon_key'] : $soc['platform']; ?>
            <li>
              <a href="<?= htmlspecialchars($soc['url'], ENT_QUOTES, 'UTF-8') ?>"
                 class="min-h-[44px] p-2 flex items-center justify-center"
                 target="_blank"
                 rel="noopener noreferrer"
                 title="<?= htmlspecialchars($soc['name'], ENT_QUOTES, 'UTF-8') ?>"
                 aria-label="<?= htmlspecialchars($footerName . ' on ' . $soc['name'], ENT_QUOTES, 'UTF-8') ?> (opens in new tab)">
                <?= renderSocialIcon($iconKey, 'site-footer__icon') ?>
              </a>
            </li>
          <?php endforeach; ?>
          <?php if ($footerShowContact): ?>
            <li>
              <a href="mailto:<?= htmlspecialchars($footerProfile['public_email'], ENT_QUOTES, 'UTF-8') ?>"
                 class="min-h-[44px] p-2 flex items-center justify-center"
                 title="Email"
                 aria-label="Email <?= htmlspecialchars($footerName, ENT_QUOTES, 'UTF-8') ?>">
                <?= renderSocialIcon('email', 'site-footer__icon') ?>
              </a>
            </li>
          <?php endif; ?>
        </ul>
        </div>
      <?php endif; ?>
    </div>

    <nav aria-label="Legal and support">
      <ul class="site-footer__list site-footer__legal">
        <?php if (file_exists(__DIR__ . '/../privacy.php')): ?>
        <li><a href="/privacy.php">Privacy</a></li>
        <?php endif; ?>
        <?php if (file_exists(__DIR__ . '/../terms.php')): ?>
        <li><a href="/terms.php">Terms</a></li>
        <?php endif; ?>
        <?php if (file_exists(__DIR__ . '/../accessibility.php')): ?>
        <li><a href="/accessibility.php">Accessibility</a></li>
        <?php endif; ?>
        <?php if (file_exists(__DIR__ . '/../support.php')): ?>
        <li><a href="/support.php">Support</a></li>
        <?php endif; ?>
        <li><a href="/rss.xml">RSS</a></li>
      </ul>
    </nav>

  </div>

  <!-- Bottom bar: copyright, under everything -->
  <div class="page-container site-footer__bottom">
    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($footerName, ENT_QUOTES, 'UTF-8') ?>. All rights reserved.</p>
  </div>
</footer>

<!-- Real Visitor Telemetry (Throttled per page load) -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  try {
    fetch('/api/telemetry/visit.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ page: window.location.pathname })
    }).catch(() => {});
  } catch (e) {}
});
</script>
<!-- Motion System -->
<script src="/assets/js/motion.js?v=<?= file_exists(__DIR__ . '/../assets/js/motion.js') ? filemtime(__DIR__ . '/../assets/js/motion.js') : '1.0' ?>" defer></script>
