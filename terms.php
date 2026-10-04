<?php
require_once __DIR__ . '/api/auth/guard.php';
_startSecureSession();
// ============================================================
// TERMS OF SERVICE — Mohammed Alrashadi Personal Engineering Platform
// ============================================================

require_once __DIR__ . '/includes/settings.php';

$currentPage = 'terms';
$pageTitle = 'Terms of Service';
$pageDescription = 'Review the terms, acceptable use guidelines, and intellectual property notices governing this platform.';
$canonicalUrl = 'https://mohammedalrashadi.com/terms.php';
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
        <span class="font-mono text-xs text-primary uppercase tracking-wider font-semibold">// TERMS &amp; CONDITIONS</span>
        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">Terms of Service</h1>
        <p class="font-mono text-xs text-text-muted">Last Updated: September 2026 • Version 1.1</p>
      </div>

      <!-- Prose Content -->
      <article class="prose flex flex-col gap-6 text-text-secondary leading-relaxed text-sm sm:text-base">
        
        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">1. Acceptance of Terms</h2>
          <p>
            By accessing or using this website, its downloadable engineering resources, interactive labs, or publication content, you agree to be bound by these Terms of Service. If you do not agree with any part of these terms, please refrain from using the platform.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">2. Educational &amp; Portfolio Nature</h2>
          <p>
            This website represents the independent software engineering work, academic research, and technical writing of Mohammed Alrashadi. The digital products, code samples, and laboratory experiments are provided primarily for educational, demonstration, and personal evaluation purposes.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">3. Intellectual Property Rights</h2>
          <p>
            Unless explicitly licensed under an open-source license (such as MIT or Apache 2.0 within a specific repository):
          </p>
          <ul class="list-disc pl-5 flex flex-col gap-1.5">
            <li>All technical essays, architectural documentation, branding assets, monograms, and website design code are the intellectual property of Mohammed Alrashadi.</li>
            <li>You may view, read, and bookmark content for personal, non-commercial use.</li>
            <li>Redistribution, modification for commercial sale, or wholesale scraping of articles or downloadable packages without prior written permission is prohibited.</li>
          </ul>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">4. User Accounts &amp; Conduct</h2>
          <p>
            If you create an account, you are responsible for maintaining the confidentiality of your credentials and for all activities conducted through your account. You agree not to:
          </p>
          <ul class="list-disc pl-5 flex flex-col gap-1.5">
            <li>Attempt to bypass authentication mechanisms, rate limits, or access controls.</li>
            <li>Submit malicious, abusive, or spam submissions through the support or contact forms.</li>
            <li>Impersonate any other individual or entity.</li>
          </ul>
          <p>We reserve the right to suspend or terminate accounts that violate these security standards.</p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">5. Disclaimer of Warranties</h2>
          <p>
            The software, code snippets, architectural patterns, and laboratory tools on this platform are provided on an <strong>"AS IS"</strong> and <strong>"AS AVAILABLE"</strong> basis without warranties of any kind, whether express or implied. Use of any code or architectural advice in mission-critical or production environments is at your own risk.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">6. Limitation of Liability</h2>
          <p>
            In no event shall Mohammed Alrashadi be liable for any direct, indirect, incidental, or consequential damages resulting from the use or inability to use this platform or any downloadable artifacts.
          </p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="text-on-surface font-semibold text-lg sm:text-xl">7. Contact Information</h2>
          <p>
            For inquiries regarding permissions, licensing, or terms of use, please visit our <a href="/support.php" class="text-primary hover:underline font-medium">Customer Support page</a>.
          </p>
        </section>

      </article>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>

