<?php
// ============================================================
// RESET PASSWORD — Mohammed Alrashadi Personal Platform
// ============================================================

header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');

require_once __DIR__ . '/api/auth/guard.php';

if (isUserLoggedIn()) {
    header('Location: /dashboard/');
    exit;
}

$rawToken = trim($_GET['token'] ?? '');
$isValidToken = false;
$tokenError = '';

if (empty($rawToken) || strlen($rawToken) !== 64) {
    $tokenError = 'The password reset link is invalid or incomplete.';
} else {
    try {
        $pdo = getDB();
        $tokenHash = hash('sha256', $rawToken);
        $stmt = $pdo->prepare(
            'SELECT pr.id, pr.expires_at, pr.used_at, u.status 
             FROM password_resets pr 
             JOIN users u ON u.id = pr.user_id 
             WHERE pr.token_hash = ? 
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $tokenError = 'The reset link is invalid or has already been used.';
        } elseif ($row['used_at'] !== null) {
            $tokenError = 'This reset link has already been used. Please request a new link.';
        } elseif (strtotime($row['expires_at']) < time()) {
            $tokenError = 'This reset link has expired. Reset links are valid for 60 minutes.';
        } elseif (($row['status'] ?? 'active') !== 'active') {
            $tokenError = 'This account is currently suspended or inactive.';
        } else {
            $isValidToken = true;
        }
    } catch (Throwable $e) {
        error_log('[reset-password.php] Validation error: ' . $e->getMessage());
        $tokenError = 'Could not verify token. Please try again.';
    }
}

$currentPage = 'reset-password';
$robots = 'noindex,follow';
$pageTitle = 'Reset Password';
$pageDescription = 'Set a new password for your account.';
$canonicalUrl = 'https://mohammedalrashadi.com/reset-password.php';
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="referrer" content="no-referrer">
  <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen selection:bg-primary-container selection:text-on-primary flex flex-col justify-between">
  
  <?php require_once __DIR__ . '/includes/header.php'; ?>

  <main class="w-full flex-grow flex items-center justify-center px-gutter py-8" style="min-height: calc(100dvh - 4rem);">
    <div class="w-full max-w-md my-auto">
      
      <!-- Card Container -->
      <div class="card p-space-lg sm:p-space-xl border border-border shadow-2xl rounded-2xl flex flex-col gap-space-md">
        
        <?php if (!$isValidToken): ?>
          <!-- Invalid / Expired Token State -->
          <div class="flex flex-col gap-3 text-center">
            <div class="flex justify-center mb-1">
              <div class="w-12 h-12 rounded-xl bg-error/10 text-error flex items-center justify-center border border-error/20">
                <span class="material-symbols-outlined text-2xl">error</span>
              </div>
            </div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Reset Link Unavailable</h1>
            <p class="font-sans text-xs sm:text-sm text-text-secondary leading-relaxed">
              <?= htmlspecialchars($tokenError) ?>
            </p>
            <div class="pt-4">
              <a href="/forgot-password.php" class="btn btn-primary w-full justify-center">
                <span>Request a New Link</span>
                <span class="material-symbols-outlined text-[18px]">outgoing_mail</span>
              </a>
            </div>
          </div>
        <?php else: ?>
          <!-- Valid Token Form -->
          <div class="flex flex-col gap-1 text-center">
            <div class="flex justify-center mb-2">
              <div class="w-12 h-12 rounded-xl bg-surface-container flex items-center justify-center border border-border">
                <span class="material-symbols-outlined text-2xl text-primary">key</span>
              </div>
            </div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Create New Password</h1>
            <p class="font-sans text-xs sm:text-sm text-text-secondary">
              Enter and confirm your new account password (at least 8 characters).
            </p>
          </div>

          <!-- Notification Banner -->
          <div id="reset-alert" class="hidden p-3 rounded-lg text-xs leading-relaxed border"></div>

          <!-- Reset Form -->
          <form id="reset-form" class="flex flex-col gap-space-sm" novalidate>
            <input type="hidden" id="reset_token" value="<?= htmlspecialchars($rawToken) ?>">
            <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="flex flex-col gap-1.5">
              <label for="new_password" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">New Password</label>
              <input type="password" 
                     id="new_password" 
                     required 
                     minlength="8"
                     autocomplete="new-password"
                     placeholder="••••••••"
                     class="input-text">
            </div>

            <div class="flex flex-col gap-1.5">
              <label for="confirm_password" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-medium">Confirm New Password</label>
              <input type="password" 
                     id="confirm_password" 
                     required 
                     minlength="8"
                     autocomplete="new-password"
                     placeholder="••••••••"
                     class="input-text">
            </div>

            <button type="submit" 
                    id="reset-btn"
                    class="btn btn-primary w-full h-11 justify-center mt-2 font-medium tracking-wide">
              <span>Save New Password</span>
              <span class="material-symbols-outlined text-[18px]">lock_reset</span>
            </button>
          </form>
        <?php endif; ?>

        <!-- Return to Sign In -->
        <div class="pt-4 border-t border-border/60 text-center">
          <a href="/login.php" class="font-sans text-xs text-text-secondary hover:text-primary transition-colors">
            &larr; Back to Sign In
          </a>
        </div>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

  <?php if ($isValidToken): ?>
  <script src="/js/auth.js"></script>
  <?php endif; ?>
</body>
</html>

