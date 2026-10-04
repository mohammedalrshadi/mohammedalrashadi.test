document.addEventListener('DOMContentLoaded', () => {
  // Login Form
  const loginForm = document.getElementById('login-form');
  if (loginForm) {
    const emailInput = document.getElementById('login-email');
    const passInput = document.getElementById('login-password');
    const submitBtn = document.getElementById('login-btn');
    const alertBox = document.getElementById('login-alert');

    function showAlert(msg, isError = true) {
      alertBox.textContent = msg;
      alertBox.className = isError 
        ? 'alert-error block'
        : 'alert-success block';
    }

    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('hidden');

      const email = emailInput.value.trim();
      const password = passInput.value;

      if (!email || !password) {
        showAlert('Please enter both email and password.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Signing in...</span>';

      try {
        const csrfToken = document.getElementById('csrf_token')?.value || '';
        const res = await fetch('/api/auth/login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          credentials: 'same-origin',
          body: JSON.stringify({ email, password, csrf_token: csrfToken })
        });

        const data = await res.json();
        if (data.success) {
          showAlert('Signed in successfully! Redirecting...', false);
          setTimeout(() => {
            window.location.href = data.redirect || '/dashboard/';
          }, 500);
        } else {
          showAlert(data.message || 'Authentication failed. Please check your credentials.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Sign In</span><span class="material-symbols-outlined text-[18px]">arrow_forward</span>';
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Sign In</span><span class="material-symbols-outlined text-[18px]">arrow_forward</span>';
      }
    });
  }

  // Register Form
  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    const nameInput = document.getElementById('reg-name');
    const emailInput = document.getElementById('reg-email');
    const passInput = document.getElementById('reg-password');
    const confirmInput = document.getElementById('reg-confirm-password');
    const submitBtn = document.getElementById('register-btn');
    const alertBox = document.getElementById('register-alert');

    function showAlert(msg, isError = true) {
      alertBox.textContent = msg;
      alertBox.className = isError 
        ? 'alert-error block'
        : 'alert-success block';
    }

    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('hidden');

      const name = nameInput.value.trim();
      const email = emailInput.value.trim();
      const password = passInput.value;
      const confirm_password = confirmInput.value;

      if (!name) {
        showAlert('Please enter your full name.');
        return;
      }

      if (!email) {
        showAlert('Please enter your email address.');
        return;
      }

      if (password.length < 8) {
        showAlert('Password must be at least 8 characters long.');
        return;
      }

      if (password !== confirm_password) {
        showAlert('Passwords do not match.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Creating Account...</span>';

      const csrfToken = document.getElementById('csrf_token')?.value || '';

      try {
        const res = await fetch('/api/auth/register.php', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          credentials: 'same-origin',
          body: JSON.stringify({ name, email, password, confirm_password, csrf_token: csrfToken })
        });

        const data = await res.json();
        if (data.success) {
          showAlert('Account created successfully! Entering dashboard...', false);
          setTimeout(() => {
            window.location.href = data.redirect || '/dashboard/';
          }, 500);
        } else {
          showAlert(data.message || 'Registration failed. Please review your details.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Create Account</span><span class="material-symbols-outlined text-[18px]">check</span>';
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Create Account</span><span class="material-symbols-outlined text-[18px]">check</span>';
      }
    });
  }

  // Forgot Password Form
  const forgotForm = document.getElementById('forgot-form');
  if (forgotForm) {
    const emailInput = document.getElementById('forgot-email');
    const csrfInput = document.getElementById('csrf_token');
    const submitBtn = document.getElementById('forgot-btn');
    const alertBox = document.getElementById('forgot-alert');

    function showAlert(msg, isError = true) {
      alertBox.textContent = msg;
      alertBox.className = isError 
        ? 'alert-error block'
        : 'alert-success block';
    }

    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('hidden');

      const email = emailInput.value.trim();
      const csrf_token = csrfInput.value;

      if (!email) {
        showAlert('Please enter your email address.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Sending link...</span>';

      try {
        const res = await fetch('/api/auth/forgot.php', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf_token
          },
          credentials: 'same-origin',
          body: JSON.stringify({ email, csrf_token })
        });

        const data = await res.json();
        showAlert(data.message || 'If an account exists, a recovery link has been dispatched.', !data.success);

        if (data.success) {
          forgotForm.reset();
          submitBtn.innerHTML = '<span>Link Sent</span><span class="material-symbols-outlined text-[18px]">check</span>';
        } else {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Send Recovery Link</span><span class="material-symbols-outlined text-[18px]">outgoing_mail</span>';
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Send Recovery Link</span><span class="material-symbols-outlined text-[18px]">outgoing_mail</span>';
      }
    });
  }

  // Reset Password Form
  const resetForm = document.getElementById('reset-form');
  if (resetForm) {
    const passInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const tokenInput = document.getElementById('reset_token');
    const csrfInput = document.getElementById('csrf_token');
    const submitBtn = document.getElementById('reset-btn');
    const alertBox = document.getElementById('reset-alert');

    function showAlert(msg, isError = true) {
      alertBox.textContent = msg;
      alertBox.className = isError 
        ? 'alert-error block'
        : 'alert-success block';
    }

    resetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.classList.add('hidden');

      const password = passInput.value;
      const password_confirm = confirmInput.value;
      const token = tokenInput.value;
      const csrf_token = csrfInput.value;

      if (!password || password.length < 8) {
        showAlert('Password must be at least 8 characters long.');
        return;
      }

      if (password !== password_confirm) {
        showAlert('Passwords do not match. Please verify.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Updating password...</span>';

      try {
        const res = await fetch('/api/auth/reset.php', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf_token
          },
          credentials: 'same-origin',
          body: JSON.stringify({ token, password, password_confirm, csrf_token })
        });

        const data = await res.json();
        if (data.success) {
          showAlert(data.message || 'Password updated successfully! Redirecting to sign in...', false);
          setTimeout(() => {
            window.location.href = '/login.php';
          }, 1500);
        } else {
          showAlert(data.message || 'Could not reset password. Please try again.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<span>Save New Password</span><span class="material-symbols-outlined text-[18px]">lock_reset</span>';
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Save New Password</span><span class="material-symbols-outlined text-[18px]">lock_reset</span>';
      }
    });
  }

  // Resend Verification Form
  const resendForm = document.getElementById('resend-form');
  if (resendForm) {
    resendForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('resend_email').value.trim();
      const alertBox = document.getElementById('resend-alert');
      const btn = document.getElementById('resend-btn');
      if (!email) return;

      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">sync</span><span>Sending...</span>';
      alertBox.classList.add('hidden');

      try {
        const csrfToken = resendForm.getAttribute('data-csrf') || '';
        const res = await fetch('/api/auth/resend_verification.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({ email })
        });
        const data = await res.json();
        alertBox.className = data.success ? 'alert-success block' : 'alert-error block';
        alertBox.textContent = data.message || 'Request completed.';
      } catch (err) {
        alertBox.className = 'alert-error block';
        alertBox.textContent = 'A network error occurred. Please try again.';
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">send</span><span>Resend Verification Email</span>';
      }
    });
  }
});
