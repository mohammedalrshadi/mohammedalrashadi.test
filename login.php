<?php
// ============================================================
// SIGN IN — Mohammed Alrashadi Personal Platform
// ============================================================

require_once __DIR__ . '/api/auth/guard.php';

_startSecureSession();
$csrfToken = getCsrfToken();

if (isUserLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

header('X-Robots-Tag: noindex');

$currentPage = 'login';
$robots = 'noindex,follow';
$pageTitle = 'Sign In';
$pageDescription = 'Sign in to access your personal dashboard, bookmarks, reading history, and digital resource library.';
$canonicalUrl = 'https://mohammedalrashadi.com/login.php';
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
      <div class="card flex flex-col gap-space-md">
        
        <!-- Header -->
        <div class="flex flex-col gap-1 text-center">
          <div class="flex justify-center mb-2">
            <div class="w-12 h-12 rounded-xl bg-surface-container flex items-center justify-center border border-border">
              <span class="material-symbols-outlined text-2xl text-primary">person</span>
            </div>
          </div>
          <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Welcome Back</h1>
          <p class="font-sans text-xs sm:text-sm text-text-secondary">
            Sign in to access your dashboard, library, and reading history.
          </p>
        </div>

        <!-- Notification Banner -->
        <div id="login-alert" class="hidden p-3 rounded-lg text-xs leading-relaxed border"></div>

        <!-- Sign In Form -->
        <form id="login-form" class="flex flex-col gap-space-sm" novalidate>
          <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="flex flex-col gap-1.5">
            <label for="login-email" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Email Address</label>
            <input type="email" 
                   id="login-email" 
                   required 
                   autocomplete="email"
                   placeholder="your.email@example.com"
                   class="input-text">
          </div>

          <div class="flex flex-col gap-1.5">
            <div class="flex items-center justify-between">
              <label for="login-password" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Password</label>
              <a href="/forgot-password.php" class="font-sans text-xs text-primary hover:underline">Forgot password?</a>
            </div>

            <input type="password" 
                   id="login-password" 
                   required 
                   autocomplete="current-password"
                   placeholder="••••••••"
                   class="input-text">
          </div>

          <button type="submit" 
                  id="login-btn"
                  class="btn btn-primary w-full mt-2">
            <span>Sign In</span>
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
          </button>
        </form>

        <!-- Footer / Registration link -->
        <div class="pt-4 border-t border-border/60 text-center flex flex-col gap-2">
          <p class="font-sans text-xs text-text-secondary">
            Don't have an account yet? 
            <a href="/register.php" class="text-primary hover:underline font-medium ml-1">Create Account</a>
          </p>
        </div>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <script src="/js/auth.js"></script>
</body>
</html>

