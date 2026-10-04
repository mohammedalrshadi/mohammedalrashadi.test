<?php
// ============================================================
// USER DASHBOARD — PROFILE
// dashboard/profile.php
// ============================================================

$activeNav = 'profile';
$pageTitle = 'Profile';

require_once __DIR__ . '/partials/layout_top.php';

$userId = (int)$currentUser['id'];
$profile = [];

try {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.created_at,
                p.bio, p.avatar_url, p.theme_preference, p.language_preference, 
                p.email_notifications, p.activity_visibility
         FROM users u
         LEFT JOIN user_profiles p ON u.id = p.user_id
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('[dashboard/profile] DB error: ' . $e->getMessage());
}

$userName  = (string)($profile['name'] ?? $currentUser['name'] ?? 'User');
$userEmail = (string)($profile['email'] ?? $currentUser['email'] ?? '');
$userBio   = (string)($profile['bio'] ?? '');
$userAvatar = (string)($profile['avatar_url'] ?? '');
$userRole  = (string)($profile['role'] ?? 'user');
$createdAt = !empty($profile['created_at']) ? date('F j, Y', strtotime($profile['created_at'])) : 'Unknown';
?>

<div class="flex flex-col gap-6 max-w-4xl">

  <!-- Header -->
  <div class="flex flex-col gap-1 pb-4 border-b border-border/70">
    <div class="flex items-center gap-2 text-primary font-mono text-xs uppercase tracking-wider font-semibold">
      <span class="material-symbols-outlined text-[18px]">account_circle</span>
      <span>Personal Identity</span>
    </div>
    <h1 class="font-headline-md text-2xl font-bold text-on-surface">Profile Information</h1>
    <p class="font-sans text-xs sm:text-sm text-text-secondary">
      Manage your personal display name, avatar, and background bio visible across your dashboard.
    </p>
  </div>

  <!-- Alert Banner -->
  <div id="profile-alert" class="hidden p-4 rounded-xl text-xs font-mono"></div>

  <!-- Profile Form Card -->
  <form id="profile-form" class="card p-6 sm:p-8 rounded-2xl border border-border/80 flex flex-col gap-6">
    
    <!-- Top Identity / Avatar preview -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 pb-6 border-b border-border/60">
      <div class="relative w-20 h-20 rounded-2xl bg-surface-container border border-border/80 flex items-center justify-center overflow-hidden shrink-0" id="avatar-preview-box">
        <?php if (!empty($userAvatar)): ?>
          <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Avatar" class="w-full h-full object-cover" id="avatar-img-preview">
        <?php else: ?>
          <span class="font-mono text-2xl font-bold text-primary" id="avatar-initial-preview">
            <?= htmlspecialchars(strtoupper(substr($userName, 0, 1))) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="flex flex-col gap-1 flex-1">
        <h3 class="font-headline-sm text-lg font-bold text-on-surface" id="display-name-header">
          <?= htmlspecialchars($userName) ?>
        </h3>
        <p class="font-mono text-xs text-text-muted">
          <?= htmlspecialchars($userEmail) ?>
        </p>
        <div class="flex items-center gap-2 mt-1">
          <span class="badge badge-neutral text-[10px] font-mono uppercase">
            Role: <?= htmlspecialchars(ucfirst($userRole)) ?>
          </span>
          <span class="font-mono text-[11px] text-text-muted">
            Joined <?= htmlspecialchars($createdAt) ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Inputs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <!-- Full Name -->
      <div class="flex flex-col gap-1.5">
        <label for="name" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-semibold">
          Full Name <span class="text-primary">*</span>
        </label>
        <input type="text" 
               id="name" 
               name="name" 
               value="<?= htmlspecialchars($userName) ?>" 
               required 
               maxlength="255"
               class="input-text">
      </div>

      <!-- Email Address -->
      <div class="flex flex-col gap-1.5">
        <label for="email" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-semibold">
          Email Address
        </label>
        <input type="email" 
               id="email" 
               name="email"
               value="<?= htmlspecialchars($userEmail) ?>" 
               required 
               class="input-text">
        <span class="font-mono text-[10px] text-text-muted">Changing your email will require re-verifying the new address.</span>
      </div>
    </div>

    <!-- Avatar Image URL -->
    <div class="flex flex-col gap-1.5">
      <label for="avatar_url" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-semibold">
        Avatar Image URL
      </label>
      <input type="url" 
             id="avatar_url" 
             name="avatar_url" 
             value="<?= htmlspecialchars($userAvatar) ?>" 
             placeholder="https://example.com/avatar.jpg"
             class="input-text">
      <span class="font-mono text-[10px] text-text-muted">Provide a direct secure link (HTTPS) to your profile picture</span>
    </div>

    <!-- Bio / Description -->
    <div class="flex flex-col gap-1.5">
      <div class="flex items-center justify-between">
        <label for="bio" class="font-mono text-xs text-text-secondary uppercase tracking-wider font-semibold">
          Personal Bio & Background
        </label>
        <span id="bio-count" class="font-mono text-[10px] text-text-muted">
          <?= strlen($userBio) ?> / 2000
        </span>
      </div>
      <textarea id="bio" 
                name="bio" 
                rows="4" 
                maxlength="2000"
                placeholder="Software engineer interested in cloud architecture, distributed systems, and performance tuning..."
                class="input-text resize-y leading-relaxed"><?= htmlspecialchars($userBio) ?></textarea>
    </div>

    <!-- Submit Button -->
    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border/60">
      <button type="submit" id="save-btn" class="btn btn-primary text-sm py-2 px-6 flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">save</span>
        <span>Save Changes</span>
      </button>
    </div>

  </form>

</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('profile-form');
    const alertBox = document.getElementById('profile-alert');
    const saveBtn = document.getElementById('save-btn');
    const nameInput = document.getElementById('name');
    const avatarInput = document.getElementById('avatar_url');
    const bioInput = document.getElementById('bio');
    const bioCount = document.getElementById('bio-count');
    const previewBox = document.getElementById('avatar-preview-box');
    const nameHeader = document.getElementById('display-name-header');

    // Live bio character counter
    bioInput.addEventListener('input', () => {
      bioCount.textContent = `${bioInput.value.length} / 2000`;
    });

    // Live avatar preview
    avatarInput.addEventListener('input', () => {
      const url = avatarInput.value.trim();
      if (url) {
        previewBox.innerHTML = `<img src="${encodeURI(url)}" alt="Avatar" class="w-full h-full object-cover">`;
      } else {
        const initial = (nameInput.value.trim().charAt(0) || 'U').toUpperCase();
        previewBox.innerHTML = `<span class="font-mono text-2xl font-bold text-primary">${initial}</span>`;
      }
    });

    // Live name update header
    nameInput.addEventListener('input', () => {
      nameHeader.textContent = nameInput.value.trim() || 'User';
      if (!avatarInput.value.trim()) {
        const initial = (nameInput.value.trim().charAt(0) || 'U').toUpperCase();
        previewBox.innerHTML = `<span class="font-mono text-2xl font-bold text-primary">${initial}</span>`;
      }
    });

    // Form submit handler
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('hidden');
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">refresh</span><span>Saving...</span>';

      try {
        const emailInput = document.getElementById('email');
        const res = await window.UserAPI.post('/api/user/profile.php', {
          name: nameInput.value.trim(),
          email: emailInput ? emailInput.value.trim() : '',
          avatar_url: avatarInput.value.trim(),
          bio: bioInput.value.trim()
        });

        if (res.success) {
          alertBox.className = 'alert-success block';
          alertBox.textContent = res.message || 'Profile saved successfully.';
          
          // Also update name in sidebar if present
          const sidebarName = document.querySelector('[data-user-name]');
          if (sidebarName) sidebarName.textContent = res.name;
        } else {
          alertBox.className = 'alert-error block';
          alertBox.textContent = res.message || 'Failed to update profile.';
        }
      } catch (err) {
        alertBox.className = 'alert-error block';
        alertBox.textContent = err.message || 'An unexpected error occurred.';
      } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">save</span><span>Save Changes</span>';
      }
    });
  });
</script>

<?php require_once __DIR__ . '/partials/layout_bottom.php'; ?>

