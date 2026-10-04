<?php
// ============================================================
// FORGOT PASSWORD — Mohammed Alrashadi Personal Platform
// ============================================================

require_once __DIR__ . '/api/auth/guard.php';

if (isUserLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

header('X-Robots-Tag: noindex');

$currentPage = 'forgot-password';
$robots = 'noindex,follow';
$pageTitle = 'Forgot Password';
$pageDescription = 'Request a password reset link to regain access to your account.';
$canonicalUrl = 'https://mohammedalrashadi.com/forgot-password.php';
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary flex flex-col justify-between">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="w-full flex-grow flex items-center justify-center px-gutter py-8" style="min-height: calc(100dvh - 4rem);">
    <div class="w-full max-w-md my-auto">
      
      <!-- Card Container -->
      <div class="card p-space-lg sm:p-space-xl border border-border shadow-2xl rounded-2xl flex flex-col gap-space-md">
        
        <!-- Header -->
        <div class="flex flex-col gap-1 text-center">
          <div class="flex justify-center mb-2">
            <div class="w-12 h-12 rounded-xl bg-surface-container flex items-center justify-center border border-border">
              <span class="material-symbols-outlined text-2xl text-primary">lock_reset</span>
            </div>
          </div>
          <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Reset Your Password</h1>
          <p class="font-sans text-xs sm:text-sm text-text-secondary">
            Enter the email address associated with your account, and we'll send you a single-use recovery link.
          </p>
        </div>

        <!-- Notification Banner -->
        <div id="forgot-alert" class="hidden p-3 rounded-lg text-xs leading-relaxed border"></div>

        <!-- Request Form -->
        <form id="forgot-form" class="flex flex-col gap-space-sm" novalidate>
          <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

          <div class="flex flex-col gap-1.5">
            <label for="forgot-email" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Email Address</label>
            <input type="email" 
                   id="forgot-email" 
                   required 
                   autocomplete="email"
                   placeholder="your.email@example.com"
                   class="input-text">
          </div>

          <button type="submit" 
                  id="forgot-btn"
                  class="btn btn-primary w-full h-11 justify-center mt-2 font-medium tracking-wide">
            <span>Send Recovery Link</span>
            <span class="material-symbols-outlined text-[18px]">outgoing_mail</span>
          </button>
        </form>

        <!-- Footer / Return to login -->
        <div class="pt-4 border-t border-border/60 text-center flex flex-col gap-2">
          <p class="font-sans text-xs text-text-secondary">
            Remembered your credentials? 
            <a href="/login.php" class="text-primary hover:underline font-medium ml-1">Back to Sign In</a>
          </p>
        </div>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="/js/auth.js"></script>
</body>
</html>

