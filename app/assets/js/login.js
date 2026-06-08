// Dark/Light Mode Functionality
let darkMode = false;

function toggleTheme() {
    darkMode = !darkMode;
    updateTheme();
    localStorage.setItem('theme', darkMode ? 'dark' : 'light');
}

function updateTheme() {
    // Toggle dark-mode class on body
    document.body.classList.toggle('dark-mode', darkMode);

    // Update theme toggle icon
    const themeToggle = document.querySelector('.theme-toggle i');
    if (themeToggle) {
        if (darkMode) {
            themeToggle.classList.remove('fa-sun');
            themeToggle.classList.add('fa-moon');
        } else {
            themeToggle.classList.remove('fa-moon');
            themeToggle.classList.add('fa-sun');
        }
    }

    // Update theme-color meta tag for PWA
    updateThemeColor();
}

function updateThemeColor() {
    const themeColorMeta = document.querySelector('meta[name="theme-color"]');
    if (themeColorMeta) {
        themeColorMeta.setAttribute('content', darkMode ? '#0F0F0F' : '#D4AF37');
    }
}

// Check for saved theme preference or system preference
function checkSavedTheme() {
    const savedTheme = localStorage.getItem('theme');

    if (savedTheme) {
        darkMode = savedTheme === 'dark';
    } else {
        // Default to light to match landing page default
        darkMode = false;
    }

    updateTheme();
}

// Password visibility toggle
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password i');

    if (passwordInput && toggleButton) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.classList.remove('fa-eye');
            toggleButton.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleButton.classList.remove('fa-eye-slash');
            toggleButton.classList.add('fa-eye');
        }
    }
}

/**
 * Modern Alert/Confirm Modal System
 */
function showModernAlert({ 
    title = 'Are you sure?', 
    message = '', 
    icon = 'warning', 
    confirmText = 'Confirm', 
    cancelText = 'Cancel',
    showCancel = true
}) {
    return new Promise((resolve) => {
        let modal = document.getElementById('modernAlertModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'modernAlertModal';
            modal.className = 'modern-alert';
            document.body.appendChild(modal);
        }

        const iconMap = {
            warning: 'fa-exclamation-triangle',
            danger: 'fa-trash',
            success: 'fa-check-circle',
            info: 'fa-info-circle',
            loading: 'fa-spinner fa-spin'
        };

        const iconClass = iconMap[icon] || iconMap.warning;
        const isDanger = icon === 'danger';

        modal.innerHTML = `
            <div class="modern-alert-content">
                <div class="modern-alert-icon ${icon}">
                    <i class="fas ${iconClass}"></i>
                </div>
                <h2>${title}</h2>
                <p>${message}</p>
                <div class="modern-alert-actions">
                    ${showCancel ? `<button class="modern-alert-btn cancel" id="modernAlertCancel">${cancelText}</button>` : ''}
                    <button class="modern-alert-btn confirm ${isDanger ? 'danger' : ''}" id="modernAlertConfirm">${confirmText}</button>
                </div>
            </div>
        `;

        setTimeout(() => modal.classList.add('active'), 10);
        document.body.style.overflow = 'hidden';

        const cleanup = (result) => {
            modal.classList.remove('active');
            setTimeout(() => {
                document.body.style.overflow = '';
                resolve(result);
            }, 300);
        };

        if (showCancel) {
            document.getElementById('modernAlertCancel').onclick = () => cleanup(false);
        }
        document.getElementById('modernAlertConfirm').onclick = () => cleanup(true);
        
        modal.onclick = (e) => {
            if (e.target === modal && showCancel) cleanup(false);
        };
    });
}

// Modern Toast Notification System
function showToast(message, type = 'info', title = null) {
    document.querySelectorAll('.toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const iconMap = {
        success: 'fa-check-circle',
        error:   'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info:    'fa-info-circle'
    };
    const titleMap = {
        success: 'Success',
        error:   'Error',
        warning: 'Warning',
        info:    'Info'
    };

    const icon      = iconMap[type]  || 'fa-info-circle';
    const titleText = title          || titleMap[type] || 'Notice';

    toast.innerHTML = `
        <div class="toast-accent"></div>
        <div class="toast-inner">
            <div class="toast-icon-wrap">
                <i class="fas ${icon}"></i>
            </div>
            <div class="toast-body">
                <span class="toast-title-text">${titleText}</span>
                <span class="toast-msg-text">${message}</span>
            </div>
            <button class="toast-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="toast-progress">
            <div class="toast-progress-bar"></div>
        </div>
    `;

    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));

    // Shrink progress bar over 5s
    const bar = toast.querySelector('.toast-progress-bar');
    bar.style.transition = 'transform 5s linear';
    requestAnimationFrame(() => requestAnimationFrame(() => {
        bar.style.transform = 'scaleX(0)';
    }));

    const timeout = setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 5000);

    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(timeout);
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    });
}

// Global Form Validation Interceptor (Synced with Dashboard)
document.addEventListener('invalid', (function() {
    return function(e) {
        // Prevent the browser from showing default error bubbles
        e.preventDefault();
        
        // Show custom modern notification instead
        const fieldName = e.target.getAttribute('placeholder') || e.target.getAttribute('name') || 'This field';
        const message = e.target.validationMessage || 'Please fill out this field.';
        
        showToast(`${message} (${fieldName})`, 'warning', 'Validation Error');
    };
})(), true);

function removeToast(toast) {
    toast.classList.remove('show');
    toast.style.transform = 'translateX(120%)';
    setTimeout(() => toast.remove(), 500);
}

// Form validation
function validateForm(username, password) {
    if (!username || !password) {
        showToast('Please fill in all fields.', 'error');
        return false;
    }

    if (username.length < 3) {
        showToast('Username must be at least 3 characters long.', 'error');
        return false;
    }

    if (password.length < 6) {
        showToast('Password must be at least 6 characters long.', 'error');
        return false;
    }

    return true;
}

function showLoginAlert(message, type = 'error') {
    const alert = document.getElementById('loginAlert');
    if (!alert) return;
    const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
    alert.className = `login-alert alert-${type}`;
    alert.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span>`;
    alert.style.display = 'flex';
}

function hideLoginAlert() {
    const alert = document.getElementById('loginAlert');
    if (alert) alert.style.display = 'none';
}

// AJAX Login Handler
function handleLoginFormSubmit(e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const loginButton = document.getElementById('loginButton');
    const rememberMe = document.getElementById('rememberMe')?.checked || false;

    hideLoginAlert();

    if (!validateForm(username, password)) {
        return;
    }

    // Loading state
    loginButton.disabled = true;
    loginButton.innerHTML = `<div class="spinner"></div><span>Logging in...</span>`;

    fetch('./app/views/auth/login.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}&rememberMe=${rememberMe}`
    })
        .then(async res => {
            const data = await res.json();
            return data;
        })
        .then(data => {
            if (data.status === 'success') {
                loginButton.innerHTML = `<i class="fas fa-check-circle"></i><span>Login Successful!</span>`;
                loginButton.classList.add('btn-success');
                showLoginAlert('Login successful! Redirecting you now...', 'success');
                showToast('Welcome back! Redirecting...', 'success', 'Login Successful');

                const payload = data.data || data;
                const sessionData = {
                    username: payload.username || username,
                    name: payload.name,
                    full_name: payload.name,
                    role: payload.role,
                    user_id: payload.user_id || payload.studentId,
                    studentId: payload.studentId,
                    studentIdCode: payload.studentIdCode,
                    expires: payload.expires * 1000,
                    theme: darkMode ? 'dark' : 'light'
                };
                localStorage.setItem('userSession', JSON.stringify(sessionData));
                // Cache credentials hash for offline login (username only, never store plain password)
                localStorage.setItem('offlineUser', JSON.stringify({
                    username: sessionData.username,
                    name: sessionData.name,
                    role: sessionData.role,
                    studentId: sessionData.studentId,
                    studentIdCode: sessionData.studentIdCode
                }));
                if (payload.studentId) {
                    localStorage.setItem('student_id', payload.studentId);
                    if (payload.studentIdCode) localStorage.setItem('student_id_code', payload.studentIdCode);
                }

                if (payload.role === 'user' && typeof window.upgradePushToStudent === 'function') {
                    window.upgradePushToStudent().catch(() => {});
                }

                setTimeout(() => {
                    // Admin and staff roles go to admin dashboard, students go to user dashboard
                    const isStaffRole = ['admin', 'OSAS Staff', 'CSC Officer', 'Officer', 'Faculty Member'].includes(payload.role);
                    window.location.href = isStaffRole
                        ? './includes/dashboard.php'
                        : './includes/user_dashboard.php';
                }, 1200);
            } else {
                loginButton.disabled = false;
                loginButton.innerHTML = `<span>Login</span>`;
                loginButton.classList.remove('btn-success');
                const msg = data.message || 'Invalid credentials. Please try again.';
                showLoginAlert(msg, 'error');
                showToast(msg, 'error', 'Login Failed');
            }
        })
        .catch(err => {
            console.error('Login error:', err);

            // OFFLINE LOGIN — check if we have a cached user profile
            if (!navigator.onLine) {
                const offlineUser = localStorage.getItem('offlineUser');
                if (offlineUser) {
                    try {
                        const cached = JSON.parse(offlineUser);
                        // Only allow offline login for the same username
                        if (cached.username === username.trim()) {
                            loginButton.innerHTML = `<i class="fas fa-check-circle"></i><span>Offline Login</span>`;
                            loginButton.classList.add('btn-success');
                            showLoginAlert('Offline Mode — using cached session', 'success');
                            showToast('Offline Mode active. Data will sync when online.', 'warning', 'Offline Login');

                            // Restore session from cache
                            const sessionData = {
                                ...cached,
                                expires: Date.now() + 6 * 60 * 60 * 1000, // 6h
                                offlineMode: true
                            };
                            localStorage.setItem('userSession', JSON.stringify(sessionData));

                            setTimeout(() => {
                                const root = (function() {
                                    const p = window.location.pathname.split('/').filter(Boolean);
                                    const d = ['app','api','includes','assets','public'];
                                    return (p.length === 0 || d.includes(p[0])) ? '' : '/' + p[0];
                                })();
                                const isCachedAdmin = ['admin', 'OSAS Staff', 'CSC Officer', 'Officer', 'Faculty Member'].includes(cached.role);
                                window.location.href = isCachedAdmin
                                    ? root + '/includes/dashboard.php'
                                    : root + '/includes/user_dashboard.php';
                            }, 1200);
                            return;
                        }
                    } catch(e) {}
                }
                showLoginAlert('You are offline. Please connect to the internet to log in for the first time.', 'error');
                showToast('No internet connection', 'error', 'Offline');
            } else {
                showLoginAlert('Unable to connect. Please try again.', 'error');
                showToast('Connection error', 'error', 'Error');
            }

            loginButton.disabled = false;
            loginButton.innerHTML = `<span>Login</span>`;
            loginButton.classList.remove('btn-success');
        });
}

// Initialize application
function initApp() {
    console.log('Initializing app...');

    // Initialize theme
    checkSavedTheme();

    // Add event listeners
    const loginForm = document.getElementById('loginForm');
    const themeToggle = document.getElementById('themeToggle');
    const passwordToggle = document.getElementById('passwordToggle');

    console.log('Elements found:', {
        loginForm: !!loginForm,
        themeToggle: !!themeToggle,
        passwordToggle: !!passwordToggle
    });

    if (loginForm) {
        loginForm.addEventListener('submit', handleLoginFormSubmit);
        console.log('Login form event listener added');

        // Clear alert when user starts typing
        ['username', 'password'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', () => hideLoginAlert());
        });
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
        console.log('Theme toggle event listener added');
    }

    if (passwordToggle) {
        passwordToggle.addEventListener('click', togglePasswordVisibility);
        console.log('Password toggle event listener added');
    }

    // Forgot Password Modal Logic
    const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const closeForgotModal = document.getElementById('closeForgotModal');
    const gotItBtn = document.getElementById('gotItBtn');

    if (forgotPasswordBtn && forgotPasswordModal) {
        forgotPasswordBtn.addEventListener('click', (e) => {
            e.preventDefault();
            forgotPasswordModal.style.display = 'flex';
            setTimeout(() => {
                forgotPasswordModal.classList.add('show');
            }, 10);
        });

        const closeModal = () => {
            forgotPasswordModal.classList.remove('show');
            setTimeout(() => {
                forgotPasswordModal.style.display = 'none';
            }, 300);
        };

        if (closeForgotModal) closeForgotModal.addEventListener('click', closeModal);
        if (gotItBtn) gotItBtn.addEventListener('click', closeModal);

        // Close on overlay click
        forgotPasswordModal.addEventListener('click', (e) => {
            if (e.target === forgotPasswordModal) {
                closeModal();
            }
        });
    }

    // Listen for system theme changes
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', function (e) {
        // Only update if user hasn't explicitly set a preference
        if (!localStorage.getItem('theme')) {
            darkMode = e.matches;
            updateTheme();
        }
    });

    console.log('App initialization complete');
}

// Initialize when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM fully loaded');
    initApp();
});

// Also initialize if DOM is already loaded
if (document.readyState === 'interactive' || document.readyState === 'complete') {
    console.log('DOM already ready, initializing immediately');
    initApp();
}