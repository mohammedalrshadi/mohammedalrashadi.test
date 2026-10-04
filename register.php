<?php
// ============================================================
// CREATE ACCOUNT — Mohammed Alrashadi Personal Platform
// ============================================================

require_once __DIR__ . '/api/auth/guard.php';

$csrfToken = getCsrfToken();

if (isUserLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

header('X-Robots-Tag: noindex');

$currentPage = 'register';
$robots = 'noindex,follow';
$pageTitle = 'Create Account';
$pageDescription = 'Create a personal account to track reading history, save bookmarks, and access digital resources.';
$canonicalUrl = 'https://mohammedalrashadi.com/register.php';
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
              <span class="material-symbols-outlined text-2xl text-primary">person_add</span>
            </div>
          </div>
          <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Join the Platform</h1>
          <p class="font-sans text-xs sm:text-sm text-text-secondary">
            Create an account to save bookmarks, sync history, and manage digital resources.
          </p>
        </div>

        <!-- Notification Banner -->
        <div id="register-alert" class="hidden p-3 rounded-lg text-xs leading-relaxed border"></div>

        <!-- Registration Form -->
        <form id="register-form" class="flex flex-col gap-space-sm" novalidate>
          <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="flex flex-col gap-1.5">
            <label for="reg-name" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Your Name</label>
            <input type="text" 
                   id="reg-name" 
                   required 
                   autocomplete="name"
                   class="input-text">
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="reg-email" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Email Address</label>
            <input type="email" 
                   id="reg-email" 
                   required 
                   autocomplete="email"
                   class="input-text">
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="reg-password" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Password (Min 8 Characters)</label>
            <input type="password" 
                   id="reg-password" 
                   required 
                   autocomplete="new-password"
                   class="input-text">
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="reg-confirm-password" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Confirm Password</label>
            <input type="password" 
                   id="reg-confirm-password" 
                   required 
                   autocomplete="new-password"
                   class="input-text">
          </div>

          <button type="submit" 
                  id="register-btn"
                  class="btn btn-primary w-full h-11 justify-center mt-2 font-medium tracking-wide">
            <span>Create Account</span>
            <span class="material-symbols-outlined text-[18px]">check</span>
          </button>
        </form>

        <!-- Footer / Sign in link -->
        <div class="pt-4 border-t border-border/60 text-center flex flex-col gap-2">
          <p class="font-sans text-xs text-text-secondary">
            Already have an account? 
            <a href="/login.php" class="text-primary hover:underline font-medium ml-1">Sign In</a>
          </p>
        </div>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="/js/auth.js"></script>
</body>
</html>

