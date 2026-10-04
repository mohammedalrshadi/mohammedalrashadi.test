<?php
// ============================================================
// EMAIL VERIFICATION — Mohammed Alrashadi Personal Platform
// verify-email.php
// GET: Inspects token and displays confirmation prompt (does NOT consume token)
// POST: Consumes token, updates users.email_verified_at, sends welcome email
// Security: Referrer-Policy: no-referrer, Cache-Control: no-store, CSRF protected
// ============================================================

header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/api/auth/guard.php';
require_once __DIR__ . '/api/helpers/mailer.php';
require_once __DIR__ . '/api/helpers/email_templates.php';

_startSecureSession();

$csrfToken = getCsrfToken();
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$rawToken    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$state       = 'initial'; // 'prompt', 'success', 'error'
$errorMessage= '';
$userInfo    = null;

if (empty($rawToken) || strlen($rawToken) !== 64) {
    $state = 'error';
    $errorMessage = 'The verification link is invalid or incomplete. Please request a new one.';
} else {
    try {
        $pdo = getDB();
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $pdo->prepare(
            'SELECT ev.id, ev.user_id, ev.expires_at, ev.used_at,
                    u.name, u.email, u.status, u.email_verified_at
             FROM email_verifications ev
             JOIN users u ON u.id = ev.user_id
             WHERE ev.token_hash = ?
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $state = 'error';
            $errorMessage = 'The verification link is invalid or does not exist.';
        } elseif ($record['used_at'] !== null) {
            $state = 'error';
            $errorMessage = 'This verification link has already been used.';
        } elseif (strtotime($record['expires_at']) < time()) {
            $state = 'error';
            $errorMessage = 'This verification link has expired (links are valid for 24 hours). Please request a new one.';
        } elseif (($record['status'] ?? 'active') !== 'active') {
            $state = 'error';
            $errorMessage = 'This account is suspended or inactive. Please contact support.';
        } else {
            $userInfo = $record;

            if ($method === 'POST') {
                // Verify CSRF
                $sentCsrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
                $expectedCsrf = $_SESSION['csrf_token'] ?? '';

                if (empty($expectedCsrf) || empty($sentCsrf) || !hash_equals($expectedCsrf, $sentCsrf)) {
                    $state = 'error';
                    $errorMessage = 'Security session expired. Please refresh the page and try again.';
                } else {
                    // Consume token and mark user verified
                    $pdo->beginTransaction();

                    $uStmt = $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?');
                    $uStmt->execute([$record['user_id']]);

                    $tStmt = $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?');
                    $tStmt->execute([$record['id']]);

                    // Invalidate other pending tokens for this user
                    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ? AND id != ?')
                        ->execute([$record['user_id'], $record['id']]);

                    $pdo->commit();

                    // Update session if user is logged in
                    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$record['user_id']) {
                        $_SESSION['email_verified_at'] = date('Y-m-d H:i:s');
                    }

                    // Log activity
                    try {
                        $aStmt = $pdo->prepare(
                            "INSERT INTO user_activities (user_id, activity_type, description) 
                             VALUES (?, 'email_verified', 'Verified primary email address.')"
                        );
                        $aStmt->execute([$record['user_id']]);
                    } catch (Throwable $_) { error_log('[verify-email.php:99] non-fatal, fallback used: ' . get_class($_)); }

                    // Send welcome email now that address is verified
                    try {
                        $siteUrl = 'https://mohammedalrashadi.com';
                        try {
                            $sUrlStmt = $pdo->query("SELECT site_url FROM site_settings LIMIT 1");
                            if ($sUrlStmt && ($sUrlRow = $sUrlStmt->fetch(PDO::FETCH_ASSOC)) && !empty($sUrlRow['site_url'])) {
                                $siteUrl = rtrim($sUrlRow['site_url'], '/');
                            }
                        } catch (Throwable $_) { error_log('[verify-email.php:109] non-fatal, fallback used: ' . get_class($_)); }

                        $welcomeTpl = buildWelcomeEmail([
                            'name'          => $record['name'],
                            'dashboard_url' => $siteUrl . '/dashboard/',
                        ]);
                        sendMail($record['email'], 'Welcome to Mohammed Alrashadi Platform', $welcomeTpl['html'], $welcomeTpl['text']);
                    } catch (Throwable $mailEx) {
                        error_log('[verify-email] Welcome email failed: ' . $mailEx->getMessage());
                    }

                    $state = 'success';
                }
            } else {
                // GET request: show confirmation prompt (do NOT consume token)
                $state = 'prompt';
            }
        }
    } catch (Throwable $e) {
        error_log('[verify-email] Validation error: ' . $e->getMessage());
        $state = 'error';
        $errorMessage = 'A database error occurred while validating the token. Please try again.';
    }
}

$currentPage     = 'verify-email';
$robots = 'noindex,follow';
$pageTitle       = 'Verify Email Address';
$pageDescription = 'Verify your email address to activate all platform privileges.';
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
      
      <div class="card p-space-lg sm:p-space-xl border border-border shadow-2xl rounded-2xl flex flex-col gap-space-md">
        
        <?php if ($state === 'prompt'): ?>
          <!-- Prompt: GET request does NOT consume token. User clicks button to POST -->
          <div class="flex flex-col gap-3 text-center">
            <div class="flex justify-center mb-1">
              <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center border border-primary/20">
                <span class="material-symbols-outlined text-2xl">mark_email_read</span>
              </div>
            </div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Confirm Email Address</h1>
            <p class="font-sans text-xs sm:text-sm text-text-secondary leading-relaxed">
              Click the button below to complete verification for:
              <br>
              <strong class="text-text-primary font-mono text-sm"><?= htmlspecialchars($userInfo['email'] ?? '') ?></strong>
            </p>

            <form method="POST" action="/verify-email.php" class="pt-3">
              <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
              <button type="submit" class="btn btn-primary w-full justify-center">
                <span>Confirm My Email</span>
                <span class="material-symbols-outlined text-[18px]">verified</span>
              </button>
            </form>
          </div>

        <?php elseif ($state === 'success'): ?>
          <!-- Success State -->
          <div class="flex flex-col gap-3 text-center">
            <div class="flex justify-center mb-1">
              <div class="w-12 h-12 rounded-xl bg-success/10 text-success flex items-center justify-center border border-success/20">
                <span class="material-symbols-outlined text-2xl">check_circle</span>
              </div>
            </div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Email Verified</h1>
            <p class="font-sans text-xs sm:text-sm text-text-secondary leading-relaxed">
              Your email address has been confirmed! Your account is now fully verified with complete access to reviews, support, and community tools.
            </p>
            <div class="pt-4 flex flex-col gap-2">
              <a href="/dashboard/" class="btn btn-primary w-full justify-center">
                <span>Go to Dashboard</span>
                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
              </a>
            </div>
          </div>

        <?php else: ?>
          <!-- Error State with Resend option -->
          <div class="flex flex-col gap-3 text-center">
            <div class="flex justify-center mb-1">
              <div class="w-12 h-12 rounded-xl bg-error/10 text-error flex items-center justify-center border border-error/20">
                <span class="material-symbols-outlined text-2xl">error</span>
              </div>
            </div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Verification Failed</h1>
            <p class="font-sans text-xs sm:text-sm text-text-secondary leading-relaxed">
              <?= htmlspecialchars($errorMessage) ?>
            </p>

            <!-- Resend form block -->
            <div class="mt-4 pt-4 border-t border-border/60 text-left flex flex-col gap-2.5">
              <span class="font-mono text-[11px] uppercase tracking-wider text-text-muted font-semibold">Request a New Link</span>
              <div id="resend-alert" class="hidden p-3 rounded-lg text-xs leading-relaxed font-mono"></div>
              
              <form id="resend-form" class="flex flex-col gap-2" data-csrf="<?= htmlspecialchars($csrfToken) ?>">
                <input type="email" 
                       id="resend_email" 
                       required 
                       placeholder="Enter your account email..." 
                       class="input-text">
                <button type="submit" id="resend-btn" class="btn btn-secondary w-full justify-center text-xs">
                  <span class="material-symbols-outlined text-[16px]">send</span>
                  <span>Resend Verification Email</span>
                </button>
              </form>
            </div>

            <div class="pt-2">
              <a href="/login.php" class="text-xs text-text-muted hover:text-text-primary transition-colors">
                Return to Sign In
              </a>
            </div>
          </div>

          <script src="/js/auth.js"></script>
        <?php endif; ?>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>

