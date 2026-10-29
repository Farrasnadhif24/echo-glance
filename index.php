<?php
require_once __DIR__ . '/config/session.php';
initSession();

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['status_verifikasi'] === 'pending') {
        header('Location: pending.php');
        exit;
    }
    switch ($user['role']) {
        case 'keluarga':
            header('Location: dashboard-keluarga.php');
            exit;
        case 'tenaga_medis':
        case 'admin':
            header('Location: dashboard-medis.php');
            exit;
    }
}

$error = $_GET['error'] ?? '';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ECHO-GLANCE - Dual-Layer Eye Blink Messenger IoT Dashboard">
    <title>ECHO-GLANCE - Login</title>
    <link rel="icon" type="image/png" href="assets/img/logo_echo_glance.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">

    <div class="auth-brand">
        <div class="floating-shape floating-shape-1"></div>
        <div class="floating-shape floating-shape-2"></div>
        <div class="floating-shape floating-shape-3"></div>

        <div class="auth-brand-content">
            <img src="assets/img/logo_echo_glance.png" alt="ECHO-GLANCE" class="auth-brand-logo">
            <h1>ECHO<span>-GLANCE</span></h1>
            <p>Dual-Layer Eye Blink Messenger - Sistem komunikasi asistif berbasis IoT untuk pasien stroke dan paralisis.</p>

            <div class="auth-features">
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="eye"></i></div>
                    <span class="auth-feature-text">Deteksi kedipan mata real-time</span>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="brain"></i></div>
                    <span class="auth-feature-text">Monitoring posisi kepala (MPU6050)</span>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="activity"></i></div>
                    <span class="auth-feature-text">Progres terapi otot pipi (FSR402)</span>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="bell"></i></div>
                    <span class="auth-feature-text">Notifikasi kebutuhan pasien instan</span>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-form-panel">
        <div class="auth-form-container">
            <div class="auth-form-header">
                <img src="assets/img/logo_echo_glance.png" alt="ECHO-GLANCE" class="auth-logo-mobile">
                <h2>Selamat Datang</h2>
                <p>Masuk ke dashboard monitoring ECHO-GLANCE</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: var(--space-lg);">
                    <span><i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="loginForm" action="api/login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="nama@email.com"
                        value=""
                        required
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Masukkan password"
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" id="btnLogin">
                    Masuk
                </button>
            </form>

            <div class="auth-footer">
                <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
            </div>

            <div class="auth-divider">Demo Akun</div>

            <div style="display: flex; flex-direction: column; gap: 8px; font-size: 0.8125rem; color: var(--color-text-muted);">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="badge badge-primary" style="font-size: 0.625rem;">Keluarga</span>
                    <code style="font-size: 0.75rem;">siti.keluarga@gmail.com</code>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="badge badge-info" style="font-size: 0.625rem;">Medis</span>
                    <code style="font-size: 0.75rem;">dr.ahmad@hospital.id</code>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="badge badge-warning" style="font-size: 0.625rem;">Admin</span>
                    <code style="font-size: 0.75rem;">admin@echoglance.id</code>
                </div>
                <div style="font-size: 0.75rem; color: var(--color-text-light); margin-top: 4px;">
                    Password semua akun: <code style="font-size: 0.75rem;">password</code>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');

    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // Refresh icon
        if(type === 'password') {
            this.innerHTML = '<i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        } else {
            this.innerHTML = '<i data-lucide="eye-off" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        }
        lucide.createIcons();
    });

    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('btnLogin');
        btn.classList.add('btn-loading');
        btn.disabled = true;
    });
</script>

</body>
</html>
