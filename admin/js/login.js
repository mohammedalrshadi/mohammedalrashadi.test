// ============================================================
// ADMIN LOGIN — PHP API
// Handles authentication via fetch() to /api/auth/login.php
// Clean 4-State Controller: idle | submitting | success | error
// ============================================================

// DOM Elements
const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const loginButton = document.getElementById('loginButton');
const errorMessage = document.getElementById('errorMessage');
const successMessage = document.getElementById('successMessage');
const togglePasswordBtn = document.getElementById('togglePassword');
const capsLockWarning = document.getElementById('capsLockWarning');

// Initial Page Load
document.addEventListener('DOMContentLoaded', async () => {
    await checkSession();
});

// ============================================================
// CHECK EXISTING SESSION
// ============================================================
async function checkSession() {
    try {
        const response = await fetch('/api/auth/session.php', {
            method: 'GET',
            credentials: 'same-origin',
        });
        const result = await response.json();
        if (result.success) {
            console.log('✅ Active session found. Redirecting to Studio...');
            window.location.href = 'index.php';
        }
    } catch (error) {
        // No active session — remain on login page
    }
}

// ============================================================
// PASSWORD VISIBILITY & CAPS LOCK
// ============================================================
if (togglePasswordBtn && passwordInput) {
    togglePasswordBtn.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        const isPressed = type === 'text';
        togglePasswordBtn.setAttribute('aria-pressed', isPressed);
        
        const icon = togglePasswordBtn.querySelector('i');
        if (isPressed) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
}

if (passwordInput && capsLockWarning) {
    passwordInput.addEventListener('keyup', (e) => {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsLockWarning.style.display = 'flex';
        } else {
            capsLockWarning.style.display = 'none';
        }
    });
}

// ============================================================
// LOGIN STATE MACHINE CONTROLLER
// States: 'idle' | 'submitting' | 'success' | 'error'
// ============================================================
function setLoginState(state) {
    if (!loginButton) return;

    switch (state) {
        case 'submitting':
            setTimeout(() => {
                if (loginButton) {
                    loginButton.disabled = true;
                    loginButton.innerHTML = `
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <span>Signing In...</span>
                    `;
                }
            }, 0);
            break;

        case 'success':
            loginButton.disabled = true;
            loginButton.innerHTML = `
                <i class="fas fa-check"></i>
                <span>Authorized — Entering Studio...</span>
            `;
            break;

        case 'error':
        case 'idle':
        default:
            loginButton.disabled = false;
            loginButton.innerHTML = `
                <i class="fas fa-right-to-bracket"></i>
                <span>Sign In to Studio</span>
            `;
            break;
    }
}

// ============================================================
// UI FEEDBACK HELPERS
// ============================================================
function showError(message) {
    if (!errorMessage) return;
    errorMessage.textContent = message;
    errorMessage.style.display = 'block';
    if (successMessage) successMessage.style.display = 'none';
}

function showSuccess(message) {
    if (!successMessage) return;
    successMessage.textContent = message;
    successMessage.style.display = 'block';
    if (errorMessage) errorMessage.style.display = 'none';
}

function clearMessages() {
    if (errorMessage) errorMessage.style.display = 'none';
    if (successMessage) successMessage.style.display = 'none';
}

// ============================================================
// FORM SUBMIT HANDLER
// ============================================================
if (loginForm) {
    loginForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearMessages();

        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';

        if (!email || !password) {
            showError('Please enter your administrator email and password.');
            return;
        }

        setLoginState('submitting');

        try {
            console.log('🔵 Attempting login...');

            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfTokenMeta ? csrfTokenMeta.content : '';

            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    email: email,
                    password: password,
                    admin_only: true,
                    csrf_token: csrfToken
                }),
            });

            const result = await response.json();

            if (!result.success) {
                console.error('❌ Login failed:', result.message);
                setLoginState('error');
                
                const msg = (result.message || '').toLowerCase();
                if (msg.includes('too many') || msg.includes('locked')) {
                    showError(result.message);
                } else {
                    showError('Invalid email or password');
                }
                return;
            }

            console.log('✅ Login successful.');
            setLoginState('success');
            
            let greeting = 'Authentication successful';
            if (result.user && result.user.name) {
                const firstName = result.user.name.trim().split(' ')[0];
                const capitalized = firstName.charAt(0).toUpperCase() + firstName.slice(1).toLowerCase();
                greeting = 'Welcome back, ' + capitalized;
            }
            
            showSuccess(greeting + '. Redirecting to Studio...');

            setTimeout(() => {
                window.location.href = 'index.php';
            }, 500);

        } catch (error) {
            console.error('❌ Login network error:', error);
            setLoginState('idle');
            showError('Unable to connect to the server. Please check your connection and try again.');
        }
    });
}