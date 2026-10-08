<?php
// ============================================================
// HELP & SUPPORT — Mohammed Alrashadi Personal Engineering Platform
// ============================================================

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/api/auth/guard.php';

_startSecureSession();

$currentPage = 'support';
$pageTitle = 'Help & Support';
$pageDescription = 'Get assistance with platform products, technical essays, account credentials, or general inquiries.';
$canonicalUrl = 'https://mohammedalrashadi.com/support.php';
$csrfToken = getCsrfToken();

$userName  = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';

if (!empty($_GET['sent']) || !empty($_GET['ticket']) || !empty($_GET['success']) || !empty($_GET['status'])) {
    header('X-Robots-Tag: noindex');
    $robots = 'noindex,follow';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-background text-on-surface font-sans antialiased min-h-screen selection:bg-primary-container selection:text-on-primary flex flex-col justify-between">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="relative z-0 w-full pt-16 flex-grow">
    <div class="page-container py-12 max-w-4xl flex flex-col gap-12">
      
      <!-- Page Header -->
      <div class="flex flex-col gap-2 border-b border-border/60 pb-6 text-center sm:text-left">
        <span class="font-mono text-xs text-primary uppercase tracking-wider font-semibold">// ASSISTANCE &amp; INQUIRIES</span>
        <h1 class="font-headline-lg text-headline-lg-mobile md:text-headline-lg text-on-surface font-bold">Help &amp; Support</h1>
        <p class="font-sans text-sm text-text-secondary max-w-xl">
          Have a question about a technical essay, digital product download, or account access? Review common questions below or send an inquiry directly.
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">

        <!-- Column 1: FAQ Accordions (5 cols on lg) -->
        <div class="lg:col-span-5 flex flex-col gap-4">
          <h2 class="text-xs font-mono uppercase tracking-wider text-text-muted font-semibold">Frequently Asked Questions</h2>

          <div class="flex flex-col gap-3">
            
            <details class="group bg-surface-container-lowest border border-border rounded-xl p-4 transition-all">
              <summary class="flex justify-between items-center cursor-pointer font-medium text-sm text-on-surface select-none">
                <span>How do digital downloads work?</span>
                <span class="material-symbols-outlined text-base text-text-muted group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="mt-3 text-xs text-text-secondary leading-relaxed border-t border-border/40 pt-2.5">
                Digital products in the Store can be claimed with an active account. Once claimed, zip bundles and binary assets are stored in your personal dashboard library and can be downloaded at any time.
              </p>
            </details>

            <details class="group bg-surface-container-lowest border border-border rounded-xl p-4 transition-all">
              <summary class="flex justify-between items-center cursor-pointer font-medium text-sm text-on-surface select-none">
                <span>How do I reset my password?</span>
                <span class="material-symbols-outlined text-base text-text-muted group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="mt-3 text-xs text-text-secondary leading-relaxed border-t border-border/40 pt-2.5">
                Visit the <a href="/forgot-password.php" class="text-primary hover:underline">Forgot Password</a> page and enter your account email. You will receive a 60-minute recovery link to choose a new password.
              </p>
            </details>

            <details class="group bg-surface-container-lowest border border-border rounded-xl p-4 transition-all">
              <summary class="flex justify-between items-center cursor-pointer font-medium text-sm text-on-surface select-none">
                <span>Can I use code samples in my projects?</span>
                <span class="material-symbols-outlined text-base text-text-muted group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="mt-3 text-xs text-text-secondary leading-relaxed border-t border-border/40 pt-2.5">
                Yes! Architectural code snippets and educational examples published in technical essays and labs are free to study and adapt for educational and personal projects.
              </p>
            </details>

            <details class="group bg-surface-container-lowest border border-border rounded-xl p-4 transition-all">
              <summary class="flex justify-between items-center cursor-pointer font-medium text-sm text-on-surface select-none">
                <span>How are my preferences saved?</span>
                <span class="material-symbols-outlined text-base text-text-muted group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="mt-3 text-xs text-text-secondary leading-relaxed border-t border-border/40 pt-2.5">
                Visual themes (Light, Dark, Space Green) are stored instantly in your browser's local storage with zero server latency. Account bookmarks and likes synchronize to your account database record.
              </p>
            </details>

          </div>
        </div>

        <!-- Column 2: Inquiry Form (7 cols on lg) -->
        <div class="lg:col-span-7 flex flex-col gap-4">
          <h2 class="text-xs font-mono uppercase tracking-wider text-text-muted font-semibold">Send a Message</h2>

          <div class="card">
            <div id="support-alert" class="hidden p-3.5 rounded-lg text-xs leading-relaxed border mb-4"></div>

            <form id="support-form" class="flex flex-col gap-4" novalidate>
              <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
              
              <!-- Anti-Spam Honeypot Field (must remain empty) -->
              <div style="display:none;" aria-hidden="true">
                <label for="website_url">Leave this field blank</label>
                <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label for="support-name" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Your Name <span class="text-primary">*</span></label>
                  <input type="text" 
                         id="support-name" 
                         required 
                         maxlength="255"
                         value="<?= htmlspecialchars($userName) ?>"
                         placeholder="Mohammed"
                         class="input-text">
                </div>

                <div class="flex flex-col gap-1.5">
                  <label for="support-email" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Email Address <span class="text-primary">*</span></label>
                  <input type="email" 
                         id="support-email" 
                         required 
                         maxlength="255"
                         value="<?= htmlspecialchars($userEmail) ?>"
                         placeholder="you@example.com"
                         class="input-text">
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label for="support-category" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Inquiry Topic</label>
                  <select id="support-category" class="input-text appearance-none pr-8 bg-no-repeat bg-[right_12px_center] bg-[url('data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23a1a1aa\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpath d=\'m6 9 6 6 6-6\'/%3E%3C/svg%3E')]">
                    <option value="general">General Inquiry</option>
                    <option value="product">Digital Product &amp; Downloads</option>
                    <option value="technical">Technical Issue or Bug</option>
                    <option value="account">Account Access &amp; Security</option>
                    <option value="feedback">Feedback &amp; Suggestions</option>
                  </select>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label for="support-subject" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Subject <span class="text-primary">*</span></label>
                  <input type="text" 
                         id="support-subject" 
                         required 
                         maxlength="255"
                         placeholder="Brief summary..."
                         class="input-text">
                </div>
              </div>

              <div class="flex flex-col gap-1.5">
                <label for="support-message" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Message <span class="text-primary">*</span></label>
                <textarea id="support-message" 
                          required 
                          rows="5" 
                          maxlength="5000"
                          placeholder="Please provide details about your inquiry..."
                          class="input-text resize-y"></textarea>
              </div>

              <button type="submit" 
                      id="support-btn"
                      class="btn btn-primary mt-2">
                <span>Send Message</span>
                <span class="material-symbols-outlined text-[18px]">send</span>
              </button>
            </form>
          </div>
        </div>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="/js/support.js"></script>
</body>
</html>

