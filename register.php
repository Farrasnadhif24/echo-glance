<?php
require_once __DIR__ . '/config/session.php';
initSession();

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$role = $_GET['role'] ?? '';
$csrfToken = generateCSRFToken();

// Pre-fill form values on error
$prevNama = $_GET['nama'] ?? '';
$prevEmail = $_GET['email'] ?? '';
$prevRole = $_GET['role'] ?? 'keluarga';
$prevSTR = $_GET['str_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ECHO-GLANCE - Daftar Akun Baru">
    <title>ECHO-GLANCE - Daftar</title>
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
            <p>Bergabunglah dengan platform monitoring ECHO-GLANCE untuk memantau kebutuhan pasien secara real-time.</p>

            <div class="auth-features">
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="users"></i></div>
                    <span class="auth-feature-text">Keluarga: Pantau kebutuhan pasien 24/7</span>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="stethoscope"></i></div>
                    <span class="auth-feature-text">Tenaga Medis: Analisis grafik terapi</span>
                </div>
                <div class="auth-feature-item">
                    <div class="auth-feature-icon"><i data-lucide="lock"></i></div>
                    <span class="auth-feature-text">Data aman dengan autentikasi terverifikasi</span>
                </div>
            </div>
        </div>
    </div>

    <div class="auth-form-panel">
        <div class="auth-form-container">

            <?php if ($success === '1'): ?>
                <div class="auth-success animate-fadeInUp">
                    <div class="success-icon"><i data-lucide="check-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                    <?php if ($role === 'keluarga'): ?>
                        <h3>Akun Berhasil Dibuat!</h3>
                        <p>Anda dapat langsung masuk ke dashboard monitoring.</p>
                        <a href="index.php" class="btn btn-primary btn-lg">Masuk Sekarang</a>
                    <?php else: ?>
                        <h3>Pendaftaran Berhasil!</h3>
                        <p>Akun Anda sedang menunggu verifikasi admin. Anda akan dihubungi setelah Nomor STR diverifikasi.</p>
                        <a href="index.php" class="btn btn-primary btn-lg">Kembali ke Login</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>

                <div class="auth-form-header">
                    <img src="assets/img/logo_echo_glance.png" alt="ECHO-GLANCE" class="auth-logo-mobile">
                    <h2>Buat Akun Baru</h2>
                    <p>Daftar untuk mengakses dashboard monitoring</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="margin-bottom: var(--space-lg);">
                        <span><i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form class="auth-form" id="registerForm" action="api/register.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="form-group">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            class="form-input"
                            placeholder="Masukkan nama lengkap"
                            value="<?= htmlspecialchars($prevNama) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="reg-email" class="form-label">Email</label>
                        <input
                            type="email"
                            id="reg-email"
                            name="email"
                            class="form-input"
                            placeholder="nama@email.com"
                            value="<?= htmlspecialchars($prevEmail) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pilih Peran</label>
                        <div class="role-selector">
                            <div class="role-option">
                                <input type="radio" id="roleKeluarga" name="role" value="keluarga" <?= $prevRole === 'keluarga' ? 'checked' : '' ?>>
                                <label for="roleKeluarga">
                                    <span class="role-icon"><i data-lucide="users"></i></span>
                                    <span class="role-name">Keluarga</span>
                                    <span class="role-desc">Pantau kebutuhan pasien</span>
                                </label>
                            </div>
                            <div class="role-option">
                                <input type="radio" id="roleMedis" name="role" value="tenaga_medis" <?= $prevRole === 'tenaga_medis' ? 'checked' : '' ?>>
                                <label for="roleMedis">
                                    <span class="role-icon"><i data-lucide="stethoscope"></i></span>
                                    <span class="role-name">Tenaga Medis</span>
                                    <span class="role-desc">Analisis & terapi</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="str-field form-group <?= $prevRole === 'tenaga_medis' ? 'visible' : '' ?>" id="strFieldContainer">
                        <label for="str_number" class="form-label">Nomor STR</label>
                        <input
                            type="text"
                            id="str_number"
                            name="str_number"
                            class="form-input"
                            placeholder="Contoh: STR-2024-001234"
                            value="<?= htmlspecialchars($prevSTR) ?>"
                        >
                        <span class="form-hint">Nomor Surat Tanda Registrasi akan diverifikasi oleh admin.</span>
                    </div>

                    <div class="form-group">
                        <label for="reg-password" class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="reg-password"
                                name="password"
                                class="form-input"
                                placeholder="Minimal 6 karakter"
                                required
                                minlength="6"
                            >
                            <button type="button" class="password-toggle" id="toggleRegPassword" aria-label="Toggle password"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-input"
                                placeholder="Ketik ulang password"
                                required
                            >
                        </div>
                        <div class="form-error hidden" id="passwordMatchError">⚠ Password tidak cocok</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" id="btnRegister">
                        Daftar
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Sudah punya akun? <a href="index.php">Masuk di sini</a></p>
                </div>

            <?php endif; ?>

        </div>
    </div>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        lucide.createIcons();
    });
</script>
<script src="assets/js/auth.js?v=2"></script>

</body>
</html>
