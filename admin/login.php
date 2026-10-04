<?php
// ============================================================
// ADMIN LOGIN PAGE — Mohammed Alrashadi Engineering Platform
// If a valid admin session already exists, skip login and redirect
// directly to the admin dashboard.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

_startSecureSession();
$csrfToken = getCsrfToken();

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php require_once dirname(__DIR__) . '/includes/settings.php'; ?>
    <title><?= htmlspecialchars(buildPageTitle('Admin Studio')) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <?php require_once dirname(__DIR__) . '/includes/favicon.php'; ?>

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

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Global Stylesheet & Login Stylesheet -->
    <link rel="stylesheet" href="/css/styles.css?v=<?= filemtime(dirname(__DIR__) . '/css/styles.css') ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= filemtime(__DIR__ . '/css/admin.css') ?>">
    <link rel="stylesheet" href="css/login.css?v=<?= filemtime(__DIR__ . '/css/login.css') ?>">
    <script src="/js/theme-toggle.js?v=<?= filemtime(dirname(__DIR__) . '/js/theme-toggle.js') ?>" defer></script>
</head>

<body>

    <div class="login-card">

        <!-- THEME TOGGLE -->
        <div class="login-theme-toggle-wrapper">
            <button type="button" class="theme-toggle-btn btn btn-secondary btn-sm" id="admin-login-theme-toggle" aria-label="Change theme" title="Change theme" style="width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border-subtle); background: transparent; color: var(--text-muted); cursor: pointer; transition: all 0.2s ease;">
                <i class="fas fa-sun" aria-hidden="true"></i>
            </button>
        </div>

        <!-- LOGO & BRAND -->
        <div class="logo">
            <div class="platform-logo-wrapper"></div>
            <h1>Admin Studio</h1>
            <p>Sign in to manage your platform</p>
        </div>

        <!-- LOGIN FORM -->
        <form id="loginForm" method="post" action="/api/auth/login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="admin_only" value="1">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username" placeholder="you@example.com">
            </div>

            <div class="form-group">
                <div class="password-header">
                    <label for="password">Password</label>
                    <a href="../forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>
                <div class="password-input-wrapper">
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password" aria-pressed="false">
                        <i class="far fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div id="capsLockWarning" class="caps-warning" style="display: none;" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> Caps Lock is on
                </div>
            </div>

            <!-- NOTIFICATION ALERTS -->
            <div id="errorMessage" class="error" role="alert" aria-live="polite"></div>
            <div id="successMessage" class="success" role="alert" aria-live="polite"></div>

            <button type="submit" id="loginButton">
                <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                <span>Sign In to Studio</span>
            </button>

        </form>

    </div>

    <!-- RETURN TO SITE -->
    <a href="../index.php" class="back-link">
        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        <span>Return to Public Site</span>
    </a>

    <!-- LOGIN JAVASCRIPT -->
    <script src="js/login.js?v=<?= filemtime(__DIR__ . '/js/login.js') ?>"></script>

</body>

</html>