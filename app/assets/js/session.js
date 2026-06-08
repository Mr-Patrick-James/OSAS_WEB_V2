// Check Persistent Login
document.addEventListener('DOMContentLoaded', function () {
    const userSession = localStorage.getItem('userSession');

    if (userSession) {
        try {
            const session = JSON.parse(userSession);
            const now = new Date().getTime();

            if (session.expires && session.expires > now) {
                // Detect project root ('' on AWS root, '/OSAS_WEB' on local)
                const parts = window.location.pathname.split('/').filter(Boolean);
                const appDirs = ['app','api','includes','assets','public'];
                const root = (parts.length === 0 || appDirs.includes(parts[0])) ? '' : '/' + parts[0];

                if (session.role === 'admin') {
                    window.location.href = root + '/includes/dashboard.php';
                } else if (session.role === 'user') {
                    window.location.href = root + '/includes/user_dashboard.php';
                }
            } else {
                localStorage.removeItem('userSession');
            }
        } catch {
            localStorage.removeItem('userSession');
        }
    }
});

// Password Visibility Toggle
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.querySelector('.toggle-password i');

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleButton.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleButton.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Show Toast
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 50);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}
