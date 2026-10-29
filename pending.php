<?php
require_once __DIR__ . '/config/session.php';
initSession();

// Must be logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();

// If already approved, redirect to dashboard
if ($user['status_verifikasi'] === 'approved') {
    switch ($user['role']) {
        case 'keluarga':
            header('Location: dashboard-keluarga.php');
            break;
        case 'tenaga_medis':
        case 'admin':
            header('Location: dashboard-medis.php');
            break;
        default:
            header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ECHO-GLANCE - Menunggu Verifikasi">
    <title>ECHO-GLANCE - Menunggu Verifikasi</title>
    <link rel="icon" type="image/png" href="assets/img/logo_echo_glance.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
<div class="pending-wrapper">
    <div class="pending-card">
        <div class="pending-icon"><i data-lucide="hourglass"></i></div>
        <h2>Menunggu Verifikasi</h2>
        <p>
            Halo <strong><?= htmlspecialchars($user['nama']) ?></strong>, akun Anda sedang dalam proses verifikasi oleh admin.
            Kami akan memproses Nomor STR Anda dan menghubungi Anda segera setelah verifikasi selesai.
        </p>

        <div class="alert alert-info" style="text-align: left; margin-bottom: var(--space-xl);">
            <span><i data-lucide="info" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
            <span>Status saat ini: <strong>Menunggu Persetujuan</strong></span>
        </div>

        <div id="statusResult" class="hidden" style="margin-bottom: var(--space-lg);"></div>

        <div class="pending-actions">
            <button class="btn btn-primary" id="btnCheckStatus" onclick="checkStatus()">
                <i data-lucide="refresh-cw" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Cek Status
            </button>
            <a href="api/logout.php" class="btn btn-outline">
                <i data-lucide="log-out" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Logout
            </a>
        </div>
    </div>

    <p style="margin-top: var(--space-xl); color: var(--color-text-light); font-size: 0.8125rem;">
        © 2026 ECHO-GLANCE - KMIPN 2026
    </p>
</div>

<script>
function checkStatus() {
    const btn = document.getElementById('btnCheckStatus');
    const result = document.getElementById('statusResult');

    btn.classList.add('btn-loading');
    btn.disabled = true;

    fetch('api/check_status.php')
        .then(res => res.json())
        .then(data => {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            result.classList.remove('hidden');

            if (data.status === 'approved') {
                result.innerHTML = '<div class="alert alert-success"><span><i data-lucide="check-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Akun Anda telah disetujui! Mengalihkan ke dashboard...</span></div>';
                lucide.createIcons();
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard-medis.php';
                }, 2000);
            } else if (data.status === 'rejected') {
                result.innerHTML = '<div class="alert alert-danger"><span><i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Maaf, akun Anda ditolak. Hubungi admin untuk informasi lebih lanjut.</span></div>';
                lucide.createIcons();
            } else {
                result.innerHTML = '<div class="alert alert-warning"><span><i data-lucide="hourglass" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Status masih menunggu. Silakan cek kembali nanti.</span></div>';
                lucide.createIcons();
            }
        })
        .catch(() => {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            result.classList.remove('hidden');
            result.innerHTML = '<div class="alert alert-danger"><span><i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Gagal mengecek status. Coba lagi.</span></div>';
            lucide.createIcons();
        });
}
</script>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
</script>

</body>
</html>
