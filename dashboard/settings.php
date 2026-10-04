<?php
// ============================================================
// USER DASHBOARD — SETTINGS
// dashboard/settings.php
// Redesigned with clean sentence-case labels (>=14px), clear
// grouping: Profile, Security, Notifications, and Danger zone.
// ============================================================

$activeNav = 'settings';
$pageTitle = 'Settings';

require_once __DIR__ . '/partials/layout_top.php';

$userId = (int)$currentUser['id'];
$emailNotifications = 1;
$saveHistory = 1;
$userProfile = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.created_at, p.email_notifications, p.save_reading_history
         FROM users u
         LEFT JOIN user_profiles p ON u.id = p.user_id
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $userProfile = $row;
        $emailNotifications = (int)($row['email_notifications'] ?? 1);
        $saveHistory = !isset($row['save_reading_history']) || (int)$row['save_reading_history'] === 1;
    }
} catch (Exception $e) {
    error_log('[dashboard/settings] DB error: ' . $e->getMessage());
}

$displayName = (string)($userProfile['name'] ?? $currentUser['name'] ?? 'User');
$emailAddr   = (string)($userProfile['email'] ?? $currentUser['email'] ?? '');
$roleName    = (string)($userProfile['role'] ?? 'user');
$joinedDate  = !empty($userProfile['created_at']) ? date('F j, Y', strtotime($userProfile['created_at'])) : '—';
?>

<div class="flex flex-col gap-8 max-w-4xl">

  <!-- Page Header -->
  <div class="flex flex-col gap-1 pb-4 border-b border-border/70">
    <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
      <span class="material-symbols-outlined text-[18px]">tune</span>
      <span>Account &amp; Security</span>
    </div>
    <h1 class="font-headline-md text-2xl font-bold text-on-surface">Account Settings</h1>
    <p class="font-sans text-sm text-text-secondary">
      Manage your profile credentials, update your authentication password, and configure notifications.
    </p>
  </div>


  <!-- =====================================================
       SECTION 1: PROFILE DETAILS
  ====================================================== -->
  <div class="card p-6 sm:p-8 rounded-2xl border border-border/80 flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/60">
      <div>
        <h2 class="font-headline-sm text-base font-bold text-on-surface flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-[20px]">person</span>
          <span>Profile details</span>
        </h2>
        <p class="font-sans text-sm text-text-secondary mt-1">Your registered personal account information.</p>
      </div>
      <a href="/dashboard/profile.php" class="btn btn-secondary text-sm py-2 px-4 flex items-center gap-2 self-start sm:self-auto">
        <span class="material-symbols-outlined text-[18px]">edit</span>
        <span>Edit profile &amp; avatar</span>
      </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <div class="flex flex-col gap-1 p-4 rounded-xl bg-surface-container/50 border border-border/60">
        <span class="text-xs text-text-secondary">Full name</span>
        <strong class="text-sm text-text-primary font-medium"><?= htmlspecialchars($displayName) ?></strong>
      </div>

      <div class="flex flex-col gap-1 p-4 rounded-xl bg-surface-container/50 border border-border/60">
        <span class="text-xs text-text-secondary">Email address</span>
        <strong class="text-sm text-text-primary font-mono"><?= htmlspecialchars($emailAddr) ?></strong>
      </div>

      <div class="flex flex-col gap-1 p-4 rounded-xl bg-surface-container/50 border border-border/60">
        <span class="text-xs text-text-secondary">Account role</span>
        <strong class="text-sm text-text-primary capitalize flex items-center gap-2">
          <?= htmlspecialchars($roleName) ?>
          <?php if ($roleName === 'admin'): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-primary/20 text-primary font-mono font-semibold">ADMIN</span>
          <?php endif; ?>
        </strong>
      </div>

      <div class="flex flex-col gap-1 p-4 rounded-xl bg-surface-container/50 border border-border/60">
        <span class="text-xs text-text-secondary">Member since</span>
        <strong class="text-sm text-text-primary font-mono"><?= htmlspecialchars($joinedDate) ?></strong>
      </div>
    </div>
  </div>


  <!-- =====================================================
       SECTION 2: SECURITY & PASSWORD
  ====================================================== -->
  <div class="card p-6 sm:p-8 rounded-2xl border border-border/80 flex flex-col gap-6">
    <div class="flex flex-col gap-1 pb-4 border-b border-border/60">
      <h2 class="font-headline-sm text-base font-bold text-on-surface flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-[20px]">lock</span>
        <span>Security and password</span>
      </h2>
      <p class="font-sans text-sm text-text-secondary">Update your password to keep your account secure. Changing your password will sign out other active sessions.</p>
    </div>

    <div id="pass-alert" class="hidden p-4 rounded-xl text-sm font-mono"></div>

    <form id="pass-form" class="flex flex-col gap-5 max-w-xl">
      <div class="flex flex-col gap-2">
        <label for="current_password" class="text-sm font-medium text-text-primary">
          Current password <span class="text-primary">*</span>
        </label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password" class="input-text">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="new_password" class="text-sm font-medium text-text-primary">
            New password <span class="text-primary">*</span>
          </label>
          <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" class="input-text">
          <span class="text-xs text-text-muted">Minimum 8 characters</span>
        </div>

        <div class="flex flex-col gap-2">
          <label for="confirm_password" class="text-sm font-medium text-text-primary">
            Confirm new password <span class="text-primary">*</span>
          </label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password" class="input-text">
        </div>
      </div>

      <div class="flex items-center justify-start pt-3">
        <button type="submit" id="pass-save-btn" class="btn btn-secondary text-sm py-2 px-5 flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">key</span>
          <span>Update password</span>
        </button>
      </div>
    </form>
  </div>


  <!-- =====================================================
       SECTION 3: NOTIFICATION PREFERENCES
  ====================================================== -->
  <div class="card p-6 sm:p-8 rounded-2xl border border-border/80 flex flex-col gap-6">
    <div class="flex flex-col gap-1 pb-4 border-b border-border/60">
      <h2 class="font-headline-sm text-base font-bold text-on-surface flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-[20px]">notifications</span>
        <span>Notification preferences</span>
      </h2>
      <p class="font-sans text-sm text-text-secondary">Choose how you wish to receive updates and announcements.</p>
    </div>

    <div id="pref-alert" class="hidden p-4 rounded-xl text-sm font-mono"></div>

    <form id="pref-form" class="flex flex-col gap-5">
      <div class="flex flex-col gap-3">
        <label class="flex items-start gap-3 cursor-pointer select-none">
          <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?= $emailNotifications ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded border-border text-primary focus:ring-primary">
          <div class="flex flex-col">
            <span class="text-sm font-medium text-text-primary">Receive email notifications</span>
            <span class="text-xs text-text-secondary mt-0.5">Occasional emails regarding new technical articles, laboratory experiments, and platform tools.</span>
          </div>
        </label>
        <label class="flex items-start gap-3 cursor-pointer select-none mt-2">
          <input type="checkbox" id="save_reading_history" name="save_reading_history" value="1" <?= $saveHistory ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded border-border text-primary focus:ring-primary">
          <div class="flex flex-col">
            <span class="text-sm font-medium text-text-primary">Save reading history</span>
            <span class="text-xs text-text-secondary mt-0.5">Track your reading progress to seamlessly continue where you left off. Disabling this also pauses tracking.</span>
          </div>
        </label>
      </div>

      <div class="flex items-center justify-start pt-3 border-t border-border/60">
        <button type="submit" id="pref-save-btn" class="btn btn-primary text-sm py-2 px-5 flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">save</span>
          <span>Save preferences</span>
        </button>
      </div>
    </form>
  </div>


  <!-- =====================================================
       SECTION 4: DANGER ZONE
  ====================================================== -->
  <div class="card p-6 sm:p-8 rounded-2xl border border-red-500/30 bg-red-950/5 flex flex-col gap-6">
    <div class="flex flex-col gap-1 pb-4 border-b border-red-500/20">
      <h2 class="font-headline-sm text-base font-bold text-red-400 flex items-center gap-2">
        <span class="material-symbols-outlined text-[20px]">warning</span>
        <span>Danger zone</span>
      </h2>
      <p class="font-sans text-sm text-text-secondary">Irreversible account actions and personal data removal.</p>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex flex-col gap-1 max-w-lg">
        <h4 class="font-headline-sm text-sm font-semibold text-on-surface">Delete account</h4>
        <p class="font-sans text-xs sm:text-sm text-text-secondary leading-relaxed">
          Permanently delete your user profile, bookmarks, likes, reading history, and claimed library links. This action cannot be undone.
        </p>
      </div>
      <button type="button" id="open-delete-modal-btn" class="btn btn-danger text-sm py-2 px-4 self-start sm:self-auto bg-red-600/80 hover:bg-red-600 text-white border-0 flex items-center gap-1.5">
        <span class="material-symbols-outlined text-[18px]">delete_forever</span>
        <span>Delete account</span>
      </button>
    </div>
  </div>

</div>

<!-- Delete Account Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
  <div class="card max-w-md w-full p-6 rounded-2xl border border-red-500/40 bg-surface flex flex-col gap-5 shadow-2xl">
    <div class="flex items-center gap-3 text-red-400">
      <span class="material-symbols-outlined text-2xl">warning</span>
      <h3 class="font-headline-sm text-base font-bold text-on-surface">Confirm account deletion</h3>
    </div>

    <p class="font-sans text-sm text-text-secondary leading-relaxed">
      This will permanently delete your user profile, bookmarks, likes, reading history, and claimed library records.
    </p>

    <div id="delete-alert" class="hidden p-3 rounded-lg text-sm font-mono"></div>

    <form id="delete-form" class="flex flex-col gap-4">
      <div class="flex flex-col gap-2">
        <label for="delete_password" class="text-sm font-medium text-text-primary">
          Your account password
        </label>
        <input type="password" id="delete_password" required placeholder="Enter password to confirm" class="input-text input-error">
      </div>

      <div class="flex flex-col gap-2">
        <label for="delete_confirm_text" class="text-sm font-medium text-text-primary">
          Type <span class="text-red-400 font-bold">DELETE</span> to confirm
        </label>
        <input type="text" id="delete_confirm_text" required placeholder="DELETE" class="input-text input-error">
      </div>

      <div class="flex items-center justify-end gap-3 pt-3 border-t border-border/60">
        <button type="button" id="close-delete-modal-btn" class="btn btn-secondary text-sm py-2 px-4">Cancel</button>
        <button type="submit" id="confirm-delete-btn" class="btn btn-danger text-sm py-2 px-4 bg-red-600 hover:bg-red-700 text-white border-0">Permanently delete</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // 1. Notification Preferences form
    const prefForm = document.getElementById('pref-form');
    const prefAlert = document.getElementById('pref-alert');
    const prefSaveBtn = document.getElementById('pref-save-btn');

    if (prefForm) {
      prefForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        prefAlert.classList.add('hidden');
        prefSaveBtn.disabled = true;

        try {
          const res = await window.UserAPI.post('/api/user/settings.php', {
            action: 'update_preferences',
            email_notifications: document.getElementById('email_notifications').checked ? 1 : 0,
            save_reading_history: document.getElementById('save_reading_history').checked ? 1 : 0
          });

          if (res.success) {
            prefAlert.className = 'alert-success block';
            prefAlert.textContent = res.message || 'Preferences updated successfully.';
          } else {
            prefAlert.className = 'alert-error block';
            prefAlert.textContent = res.message || 'Failed to update preferences.';
          }
        } catch (err) {
          prefAlert.className = 'alert-error block';
          prefAlert.textContent = err.message || 'An unexpected error occurred.';
        } finally {
          prefSaveBtn.disabled = false;
        }
      });
    }

    // 2. Password form
    const passForm = document.getElementById('pass-form');
    const passAlert = document.getElementById('pass-alert');
    const passSaveBtn = document.getElementById('pass-save-btn');

    if (passForm) {
      passForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        passAlert.classList.add('hidden');

        const currentPass = document.getElementById('current_password').value;
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;

        if (newPass !== confirmPass) {
          passAlert.className = 'alert-error block';
          passAlert.textContent = 'New passwords do not match.';
          return;
        }

        passSaveBtn.disabled = true;

        try {
          const res = await window.UserAPI.post('/api/user/settings.php', {
            action: 'change_password',
            current_password: currentPass,
            new_password: newPass,
            confirm_password: confirmPass
          });

          if (res.success) {
            passAlert.className = 'alert-success block';
            passAlert.textContent = res.message || 'Password changed successfully.';
            passForm.reset();
          } else {
            passAlert.className = 'alert-error block';
            passAlert.textContent = res.message || 'Failed to change password.';
          }
        } catch (err) {
          passAlert.className = 'alert-error block';
          passAlert.textContent = err.message || 'An unexpected error occurred.';
        } finally {
          passSaveBtn.disabled = false;
        }
      });
    }

    // 3. Danger Zone / Delete Account Modal
    const deleteModal = document.getElementById('delete-modal');
    const openDeleteBtn = document.getElementById('open-delete-modal-btn');
    const closeDeleteBtn = document.getElementById('close-delete-modal-btn');
    const deleteForm = document.getElementById('delete-form');
    const deleteAlert = document.getElementById('delete-alert');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');

    if (openDeleteBtn && deleteModal) {
      openDeleteBtn.addEventListener('click', () => {
        deleteAlert.classList.add('hidden');
        deleteForm.reset();
        deleteModal.classList.remove('hidden');
      });

      closeDeleteBtn.addEventListener('click', () => {
        deleteModal.classList.add('hidden');
      });

      deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) {
          deleteModal.classList.add('hidden');
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !deleteModal.classList.contains('hidden')) {
          deleteModal.classList.add('hidden');
        }
      });

      deleteForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        deleteAlert.classList.add('hidden');

        const pass = document.getElementById('delete_password').value;
        const confirmText = document.getElementById('delete_confirm_text').value.trim();

        if (confirmText !== 'DELETE') {
          deleteAlert.className = 'alert-error block';
          deleteAlert.textContent = 'Please type DELETE in capital letters.';
          return;
        }

        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.textContent = 'Deleting...';

        try {
          const res = await window.UserAPI.post('/api/user/settings.php', {
            action: 'delete_account',
            password: pass,
            confirm_text: confirmText
          });

          if (res.success) {
            deleteAlert.className = 'alert-success block';
            deleteAlert.textContent = res.message || 'Account deleted. Redirecting...';
            setTimeout(() => {
              window.location.href = res.redirect || '/';
            }, 1500);
          } else {
            deleteAlert.className = 'alert-error block';
            deleteAlert.textContent = res.message || 'Failed to delete account.';
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.textContent = 'Permanently delete';
          }
        } catch (err) {
          deleteAlert.className = 'alert-error block';
          deleteAlert.textContent = err.message || 'An error occurred during deletion.';
          confirmDeleteBtn.disabled = false;
          confirmDeleteBtn.textContent = 'Permanently delete';
        }
      });
    }
  });
</script>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>
