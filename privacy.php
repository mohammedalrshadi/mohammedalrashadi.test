<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
// ============================================================
// PRIVACY POLICY — Mohammed Alrashadi Personal Engineering Platform
// ============================================================

require_once __DIR__ . '/includes/settings.php';

$currentPage = 'privacy';
$pageTitle = 'Privacy Policy';
$pageDescription = 'Understand how data is handled, stored, and protected across the Mohammed Alrashadi platform.';
$canonicalUrl = 'https://mohammedalrashadi.com/privacy.php';
?>
<!-- NOTE: This policy was generated as a reasonable baseline and should be reviewed by legal counsel before relying on it for regulatory compliance. -->
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-background text-on-surface font-sans antialiased min-h-screen selection:bg-primary-container selection:text-on-primary flex flex-col justify-between">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="relative z-0 w-full pt-16 flex-grow">
    <div class="page-container py-12 max-w-3xl flex flex-col gap-10">
      
      <!-- Page Header -->
      <div class="flex flex-col gap-2 border-b border-border/60 pb-6">
        <span class="font-mono text-xs text-primary uppercase tracking-wider font-semibold">// LEGAL &amp; TRANSPARENCY</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Privacy Policy</h1>
        <p class="font-mono text-xs text-text-muted">Last Updated: September 2026 • Version 1.1</p>
      </div>

      <!-- Prose Content -->
      <article class="prose flex flex-col gap-6 text-text-secondary leading-relaxed text-sm sm:text-base">
        
        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">1. Overview</h2>
          <p>
            This platform operates as an engineering portfolio, technical publication, and software distribution service created by Mohammed Alrashadi. We value minimalism, user autonomy, and privacy by design. We do not sell user data, run advertising networks, or embed third-party surveillance scripts.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">2. Information We Collect</h2>
          <p>We only collect information directly necessary to provide you with access to our platform features:</p>
          <ul class="list-disc pl-5 flex flex-col gap-1.5">
            <li><strong>Account Information:</strong> When you register an account, we collect your name, email address, and a securely salted bcrypt hash of your password. We never store plaintext passwords.</li>
            <li><strong>Platform Activity:</strong> If you are logged in, we store your saved bookmarks, article likes, claimed digital products, and reading progress to synchronize your experience.</li>
            <li><strong>Support Inquiries:</strong> When submitting an inquiry through our Help &amp; Support form, we collect your name, email address, topic, and message content to respond to your request.</li>
            <li><strong>First-Party Telemetry:</strong> We collect aggregated, pseudonymized page visit statistics. A cryptographically hashed HMAC identifier (<code class="font-mono text-xs bg-surface-container px-1 py-0.5 rounded">_va_id</code>) is used to estimate unique visits without collecting personally identifiable IP addresses or cross-site behavioral profiles.</li>
          </ul>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">3. Cookies &amp; Local Storage</h2>
          <p>We use a strictly limited set of first-party cookies and browser local storage:</p>
          <ul class="list-disc pl-5 flex flex-col gap-1.5">
            <li><code class="font-mono text-xs bg-surface-container px-1 py-0.5 rounded">PHPSESSID</code>: Essential session cookie for maintaining authenticated user logins.</li>
            <li><code class="font-mono text-xs bg-surface-container px-1 py-0.5 rounded">site-theme</code>: Stored in your browser's <code class="font-mono text-xs">localStorage</code> to remember your Light, Dark, or Space Green color preference without flash on reload.</li>
            <li><code class="font-mono text-xs bg-surface-container px-1 py-0.5 rounded">_va_id</code>: A persistent first-party cookie used exclusively for platform traffic analysis.</li>
          </ul>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">4. Data Retention &amp; Security</h2>
          <p>
            Your credentials and personal information are stored in an encrypted MariaDB database hosted in secure data centers. Access is restricted to authenticated administrative workflows protected by rate-limiting, CSRF tokens, and session integrity guards. Unused password recovery tokens automatically expire after 60 minutes.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">5. Your Rights</h2>
          <p>
            You have the right to access the personal information we hold about you, update your profile credentials, or request permanent deletion of your account and associated platform records. You may exercise these rights via your account settings dashboard or by reaching out through our <a href="/support.php" class="text-primary hover:underline font-medium">Customer Support page</a>.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">6. Contact &amp; Questions</h2>
          <p>
            If you have questions regarding this Privacy Policy or our security practices, please contact us via the <a href="/support.php" class="text-primary hover:underline font-medium">Support page</a>.
          </p>
        </section>

      </article>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>

