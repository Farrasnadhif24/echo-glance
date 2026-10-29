<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
initSession();
requireLogin();
requireApproved();

$user = getCurrentUser();
$userRole = $user['role'];

if (!in_array($userRole, ['tenaga_medis', 'admin'])) {
    header('Location: dashboard-keluarga.php');
    exit;
}

$initials = getInitials($user['nama']);
$roleDisplay = getRoleDisplayName($userRole);
$isAdmin = ($userRole === 'admin');

$pendingCount = 0;
if ($isAdmin) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE status_verifikasi = 'pending'");
        $stmt->execute();
        $pendingCount = (int)$stmt->fetch()['cnt'];
    } catch (Exception $e) {
        $pendingCount = 0;
    }
}
$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ECHO-GLANCE - Dashboard Tenaga Medis - Analisis Sensor & Terapi">
    <title>ECHO-GLANCE - Dashboard Medis</title>
    <link rel="icon" type="image/png" href="assets/img/logo_echo_glance.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Memaksa layout khusus untuk Dashboard Medis agar tidak terpotong */
        .grid-medis-summary {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: var(--space-xl) !important;
            margin-bottom: var(--space-xl) !important;
            align-items: flex-start !important;
        }
        .grid-medis-summary > .device-status-card {
            flex: 1 1 350px !important;
            min-width: 350px !important;
        }
        .grid-medis-summary > .card:not(.device-status-card) {
            flex: 1 1 320px !important;
            min-width: 300px !important;
        }
        .device-status-card .sensor-grid {
            display: flex !important;
            flex-wrap: wrap !important;
            justify-content: center !important;
            gap: var(--space-sm) !important;
            margin-top: var(--space-lg) !important;
        }
        .device-status-card .sensor-item {
            flex: 1 1 80px !important;
            min-width: 80px !important;
            padding: 8px 4px !important;
            overflow: hidden !important;
        }
        .device-status-card .sensor-name {
            font-size: 0.7rem !important;
            width: 100% !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            text-align: center !important;
        }
        .notif-card-summary .notification-list {
            max-height: 420px !important;
            overflow-y: auto !important;
        }
    </style>
    <!-- Firebase SDK (v8) -->
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-auth.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <!-- Firebase Config & Logic -->
    <script src="assets/js/firebase-config.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu"><i data-lucide="menu"></i></button>

<div class="mobile-overlay" id="mobileOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="assets/img/logo_echo_glance.png" alt="ECHO-GLANCE Logo">
        <div>
            <div class="brand-name">ECHO-GLANCE</div>
            <div class="brand-tagline">IoT Dashboard</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Menu Utama</div>
        <a href="dashboard-keluarga.php" class="nav-item"><span class="nav-icon"><i data-lucide="layout-dashboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Dashboard Keluarga</span></a>
        <a href="dashboard-medis.php" class="nav-item active"><span class="nav-icon"><i data-lucide="layout-dashboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Dashboard Medis</span></a>

        <div class="nav-section-label" style="margin-top: var(--space-md);">Halaman</div>
        <a href="#" class="nav-item sidebar-tab active" data-tab="overview" onclick="switchTab('overview');highlightSidebarTab(this);return false;"><span class="nav-icon"><i data-lucide="layout-dashboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Overview</span></a>
        <a href="#" class="nav-item sidebar-tab" data-tab="grafik" onclick="switchTab('grafik');highlightSidebarTab(this);return false;"><span class="nav-icon"><i data-lucide="line-chart" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Grafik Sensor</span></a>
        <a href="#" class="nav-item sidebar-tab" data-tab="histori" onclick="switchTab('histori');highlightSidebarTab(this);return false;"><span class="nav-icon"><i data-lucide="clipboard-list" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Histori Command</span></a>
        <a href="#" class="nav-item sidebar-tab" data-tab="panduan" onclick="switchTab('panduan');highlightSidebarTab(this);return false;"><span class="nav-icon"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Pola Kedipan</span></a>

        <a href="#" class="nav-item sidebar-tab" data-tab="admin" onclick="switchTab('admin');highlightSidebarTab(this);return false;"><span class="nav-icon"><i data-lucide="settings" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Kelola Akun <span class="tab-badge"></span></span></a>

    </nav>
    <div class="sidebar-footer">
        <a href="api/logout.php" class="nav-item" style="color: rgba(255,255,255,0.5);">
            <span class="nav-icon"><i data-lucide="log-out" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Logout</span>
        </a>
    </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    if (toggle) toggle.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('show'); });
    if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });

    document.querySelectorAll('.sidebar-nav a').forEach(a => {
        a.addEventListener('click', () => {
            if(sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        });
    });
});
</script>
<main class="main-content">

    <div class="topbar-user animate-fadeInUp">
        <div>
            <h1 style="margin:0;font-size:1.75rem;">Dashboard Tenaga Medis</h1>
            <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:2px 0 0;">Analisis data sensor & progres terapi pasien</p>
        </div>
        <div class="topbar-user-info" style="align-items: center;">
            <div style="text-align: right;">
                <div style="font-size:0.8125rem;font-weight:600;color:var(--color-text);"><?= htmlspecialchars($user['nama']) ?></div>
                <div style="font-size:0.6875rem;color:var(--color-text-muted);"><?= htmlspecialchars($roleDisplay) ?> · Admin</div>
            </div>
            <div class="user-avatar-sm" style="margin-left: 8px;"><?= htmlspecialchars($initials) ?></div>
        </div>
    </div>

    <div class="tab-content active" id="tab-overview">
        <div class="grid-medis-summary animate-fadeInUp">

            <div class="card device-status-card">
                <div class="card-header">
                    <h3>
                        <span class="card-icon card-icon-primary"><i data-lucide="zap" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        Status Alat
                    </h3>
                    <span class="label-small" id="deviceLastUpdated">-</span>
                </div>
                <div class="status-device">
                    <div class="device-mode-label">Mode Perangkat</div>
                    <div class="status-badge-lg tidak-aktif" id="deviceModeBadge">○ MEMUAT...</div>
                    <p id="deviceModeDesc" style="font-size:0.6875rem;color:var(--color-text-muted);margin-top:4px;margin-bottom:var(--space-md);">Memuat...</p>
                    <button class="btn btn-outline" style="width:100%; margin-bottom:var(--space-md); padding:8px; font-size:0.8125rem; display:flex; justify-content:center; align-items:center; gap:8px;" onclick="EchoGlance.toggleMode()">
                        <i data-lucide="repeat" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Ganti Mode (Non-Fisik)
                    </button>
                    <div class="sensor-grid">
                        <div class="sensor-item">
                            <span class="sensor-icon"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                            <span class="sensor-name">TCRT5000</span>
                            <span class="sensor-status ok" id="sensorMata">-</span>
                        </div>
                        <div class="sensor-item">
                            <span class="sensor-icon"><i data-lucide="brain" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                            <span class="sensor-name">MPU6050</span>
                            <span class="sensor-status ok" id="sensorMPU">-</span>
                        </div>
                        <div class="sensor-item">
                            <span class="sensor-icon"><i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                            <span class="sensor-name">FSR402</span>
                            <span class="sensor-status ok" id="sensorFSR">-</span>
                        </div>
                    </div>

                    <div class="device-mode-label" style="margin-top:var(--space-lg);margin-bottom:8px;">Live Monitor Sensor</div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; background:var(--color-bg); padding:10px; border-radius:6px; border:1px solid var(--color-border);">
                            <span style="font-size:0.8125rem; color:var(--color-text-muted);"><i data-lucide="brain" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Posisi Kepala</span>
                            <span id="liveKemiringanBadge" class="badge badge-success" style="font-size:0.75rem;">-</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; background:var(--color-bg); padding:10px; border-radius:6px; border:1px solid var(--color-border);">
                            <span style="font-size:0.8125rem; color:var(--color-text-muted);"><i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Kekuatan Pipi</span>
                            <strong id="liveOtotPipi" style="font-size:0.875rem; color:var(--color-text);">- gram</strong>
                        </div>
                        <div style="background:var(--color-bg); padding:10px; border-radius:6px; border:1px solid var(--color-border); margin-top:4px;">
                            <div style="font-size:0.75rem; font-weight:600; color:var(--color-text); margin-bottom:6px;"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Instruksi Kedipan Pasien:</div>
                            <div style="display:flex; flex-direction:column; gap:4px; font-size:0.75rem; color:var(--color-text-muted);">
                                <div style="display:flex; align-items:center; gap:6px;"><span style="color:var(--color-success);font-weight:bold;">1× Panjang</span><span>= Iya</span></div>
                                <div style="display:flex; align-items:center; gap:6px;"><span style="color:var(--color-danger);font-weight:bold;">2× Pendek</span><span>= Tidak</span></div>
                                <div style="display:flex; align-items:center; gap:6px;"><span style="color:var(--color-accent);font-weight:bold;">2× Panjang</span><span>= Masuk Menu</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>
                        <span class="card-icon card-icon-accent"><i data-lucide="layout-dashboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        Ringkasan Hari Ini
                    </h3>
                </div>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-icon"><i data-lucide="megaphone" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                        <div class="summary-value" style="color:var(--color-primary);" id="summaryTotalEvents">-</div>
                        <div class="summary-label">Total Event</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-icon"><i data-lucide="flame" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                        <div class="summary-value" style="color:var(--color-accent);font-size:0.9rem;" id="summaryMostCommon">-</div>
                        <div class="summary-label">Kebutuhan Terbanyak</div>
                    </div>
                </div>
            </div>

            <div class="card notif-card-summary">
                <div class="card-header">
                    <h3>
                        <span class="card-icon card-icon-accent"><i data-lucide="bell" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        Notifikasi Terbaru
                    </h3>
                    <!-- <div class="notification-count zero" id="notifCountBadge">0</div> -->
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="empty-state">
                        <div class="empty-icon"><i data-lucide="hourglass" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                        <div class="empty-title">Memuat...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="tab-content" id="tab-grafik">

        <div class="card chart-section animate-fadeInUp" id="grafik-mpu" style="margin-bottom:var(--space-xl);">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-primary"><i data-lucide="check-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Posisi Kepala - MPU6050
                </h3>
            </div>

            <div class="chart-container" style="display: flex; justify-content: space-around; align-items: center; padding: 2rem 0;">
                <div style="text-align: center;">
                    <div style="font-size: 1.2rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">Pitch (Maju/Mundur)</div>
                    <div id="textMpuPitch" style="font-size: 3.5rem; font-weight: 700; color: #2541B2;">0.0°</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.2rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">Roll (Kanan/Kiri)</div>
                    <div id="textMpuRoll" style="font-size: 3.5rem; font-weight: 700; color: #F2721E;">0.0°</div>
                </div>
            </div>
        </div>

        <div class="card chart-section animate-fadeInUp stagger-2" id="grafik-fsr">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-accent"><i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Progres Terapi Otot Pipi - FSR402
                </h3>
                <div class="chart-legend-custom">
                    <div class="legend-item"><span class="legend-dot force"></span> Gaya (gram)</div>
                    <div class="legend-item"><span class="legend-dot baseline"></span> Baseline</div>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="fsrChart"></canvas>
            </div>
            <div class="chart-metrics">
                <div class="chart-metric-card">
                    <div class="metric-value" id="metricAvgForce">-</div>
                    <div class="metric-label">Rata-rata Gaya Tekan Hari Ini</div>
                </div>
                <div class="chart-metric-card">
                    <div class="metric-value" id="metricAvgMovements">-</div>
                    <div class="metric-label">Gerakan Terdeteksi / Menit</div>
                </div>
                <div class="chart-metric-card">
                    <div class="metric-value" id="metricStdDev">-</div>
                    <div class="metric-label">Standar Deviasi (Variasi)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-content" id="tab-histori">
        <div class="card history-section animate-fadeInUp" id="histori-command">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-primary"><i data-lucide="clipboard-list" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Histori Command Lengkap
                </h3>
            </div>

            <div class="table-controls">
                <div class="table-controls-left">
                    <div class="search-input-wrapper">
                        <span class="search-icon"><i data-lucide="search" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <input type="text" class="form-input" id="historySearch" placeholder="Cari command..." style="padding-left:36px;max-width:250px;">
                    </div>

                </div>
                <div class="table-controls-right">
                    <button class="btn btn-outline btn-sm btn-export" onclick="exportCSV()">
                        <i data-lucide="download" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Export CSV
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Waktu</th>
                            <th>Command</th>

                        </tr>
                    </thead>
                    <tbody id="medisHistoryBody">
                        <tr><td colspan="3" class="text-center text-muted" style="padding:2rem;">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="pagination" id="medisHistoryPagination"></div>
        </div>
    </div>

    <div class="tab-content" id="tab-panduan">
        <div class="card animate-fadeInUp">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-primary"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Sistem Deteksi Kedipan Mata - Dual Layer
                </h3>
            </div>

            <div style="padding:var(--space-md);background:var(--color-bg);border-radius:var(--radius-md);margin-bottom:var(--space-xl);font-size:0.8125rem;color:var(--color-text-muted);line-height:1.8;">
                <strong style="color:var(--color-text);"><i data-lucide="lightbulb" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Alur Kerja:</strong><br>
                1. ESP32-C3 dinyalakan → DFPlayer memutar: <em>"Sistem Siap Dimulai"</em><br>
                2. Sensor TCRT5000 mendeteksi pola kedipan mata pasien<br>
                3. Pola dianalisis menggunakan <em>state machine</em> (rule-based) pada ESP32<br>
                4. Hasil dikirim via WiFi ke Firebase / Backend → Notifikasi ke dashboard
            </div>

            <div class="grid-2col gap-xl">
                <div>
                    <div style="padding:var(--space-lg);border-radius:var(--radius-lg);border:2px solid rgba(37,65,178,0.2);background:linear-gradient(135deg, rgba(37,65,178,0.03), rgba(37,65,178,0.08));">
                        <div class="label-small" style="color:var(--color-primary);margin-bottom:var(--space-md);font-size:0.75rem;">
                            <i data-lucide="zap" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Lapisan 1 - Respon Cepat
                        </div>

                        <div style="display:flex;flex-direction:column;gap:var(--space-lg);">
                            <div style="display:flex;align-items:flex-start;gap:var(--space-md);">
                                <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--color-success-surface);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;"><i data-lucide="check" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                                <div>
                                    <strong style="font-size:0.9375rem;color:var(--color-text);">Konfirmasi YA</strong>
                                    <div style="font-size:0.8125rem;color:var(--color-text-muted);margin-top:2px;">
                                        Kedip <strong>1×</strong>, tutup mata selama <strong>±2 detik</strong>
                                    </div>
                                    <div style="margin-top:6px;display:flex;gap:4px;">
                                        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:var(--color-primary);"></span>
                                        <span style="display:inline-block;width:40px;height:16px;border-radius:8px;background:var(--color-primary-dark);"></span>
                                        <span style="font-size:0.625rem;color:var(--color-text-light);align-self:center;margin-left:4px;">~2 detik</span>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex;align-items:flex-start;gap:var(--space-md);">
                                <div style="width:48px;height:48px;border-radius:var(--radius-md);background:var(--color-danger-surface);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;"><i data-lucide="x" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                                <div>
                                    <strong style="font-size:0.9375rem;color:var(--color-text);">Konfirmasi TIDAK</strong>
                                    <div style="font-size:0.8125rem;color:var(--color-text-muted);margin-top:2px;">
                                        Kedip <strong>2×</strong> cepat, interval <strong>≤1 detik</strong>
                                    </div>
                                    <div style="margin-top:6px;display:flex;gap:4px;">
                                        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:var(--color-danger);"></span>
                                        <span style="display:inline-block;width:8px;height:16px;background:transparent;"></span>
                                        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:var(--color-danger);"></span>
                                        <span style="font-size:0.625rem;color:var(--color-text-light);align-self:center;margin-left:4px;">≤1 detik</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div style="padding:var(--space-lg);border-radius:var(--radius-lg);border:2px solid rgba(242,114,30,0.2);background:linear-gradient(135deg, rgba(242,114,30,0.03), rgba(242,114,30,0.08));">
                        <div class="label-small" style="color:var(--color-accent);margin-bottom:var(--space-md);font-size:0.75rem;">
                            <i data-lucide="menu" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Lapisan 2 - Menu Kebutuhan
                        </div>

                        <div style="margin-bottom:var(--space-md);padding:var(--space-md);background:rgba(242,114,30,0.06);border-radius:var(--radius-md);">
                            <strong style="font-size:0.8125rem;color:var(--color-text);"><i data-lucide="key" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Buka Menu:</strong>
                            <div style="font-size:0.8125rem;color:var(--color-text-muted);margin-top:4px;">
                                Kedip panjang ganda - <strong>2× tutup mata 1.5 detik</strong>, jeda 0.5 detik
                            </div>
                            <div style="margin-top:6px;display:flex;gap:4px;align-items:center;">
                                <span style="display:inline-block;width:30px;height:16px;border-radius:8px;background:var(--color-accent);"></span>
                                <span style="display:inline-block;width:10px;height:16px;background:transparent;"></span>
                                <span style="display:inline-block;width:30px;height:16px;border-radius:8px;background:var(--color-accent);"></span>
                                <span style="font-size:0.625rem;color:var(--color-text-light);margin-left:4px;">1.5s + 0.5s + 1.5s</span>
                            </div>
                        </div>

                        <div class="label-small" style="margin-bottom:var(--space-sm);font-size:0.625rem;">Daftar Menu (DFPlayer bacakan satu per satu)</div>
                        <div style="display:flex;flex-direction:column;gap:6px;" id="panduanMenuList">
                            <div style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;">
                                <span style="width:20px;height:20px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700;color:var(--color-text-muted);flex-shrink:0;">1</span>
                                <span style="color:var(--color-text);">Ingin makan?</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;">
                                <span style="width:20px;height:20px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700;color:var(--color-text-muted);flex-shrink:0;">2</span>
                                <span style="color:var(--color-text);">Ingin minum?</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;">
                                <span style="width:20px;height:20px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700;color:var(--color-text-muted);flex-shrink:0;">3</span>
                                <span style="color:var(--color-text);">Ingin buang air kecil?</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;">
                                <span style="width:20px;height:20px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700;color:var(--color-text-muted);flex-shrink:0;">4</span>
                                <span style="color:var(--color-text);">Ingin buang air besar?</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;">
                                <span style="width:20px;height:20px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700;color:var(--color-text-muted);flex-shrink:0;">5</span>
                                <span style="color:var(--color-text);">Ingin berjemur?</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;font-size:0.8125rem;">
                                <span style="width:20px;height:20px;border-radius:50%;background:var(--color-bg);display:flex;align-items:center;justify-content:center;font-size:0.625rem;font-weight:700;color:var(--color-text-muted);flex-shrink:0;">6</span>
                                <span style="color:var(--color-text);">Ingin tidur?</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:var(--space-xl);">
                <h4 style="margin-bottom:var(--space-md);"><i data-lucide="wrench" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Mode Perangkat</h4>
                <div class="grid-2col gap-lg">
                    <div style="padding:var(--space-lg);border-radius:var(--radius-lg);background:var(--color-bg);border:1px solid var(--color-border);">
                        <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-md);">
                            <span class="badge badge-nonaktif">○ Mode Tidak Aktif</span>
                            <span style="font-size:0.75rem;color:var(--color-text-muted);">(Pasien tidur/istirahat)</span>
                        </div>
                        <div style="font-size:0.8125rem;color:var(--color-text-muted);line-height:1.8;">
                            <i data-lucide="moon" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> <strong>TCRT5000 (Mata)</strong> - Aktif untuk komunikasi dasar<br>
                            <i data-lucide="moon" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> <strong>MPU6050 (Kepala)</strong> - Non-aktif, hemat daya<br>
                            <i data-lucide="moon" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> <strong>FSR402 (Pipi)</strong> - Non-aktif, hindari alarm palsu
                        </div>
                    </div>
                    <div style="padding:var(--space-lg);border-radius:var(--radius-lg);background:var(--color-success-surface);border:1px solid rgba(22,163,74,0.2);">
                        <div style="display:flex;align-items:center;gap:var(--space-sm);margin-bottom:var(--space-md);">
                            <span class="badge badge-aktif">● Mode Aktif</span>
                            <span style="font-size:0.75rem;color:var(--color-text-muted);">(Sesi terapi / kegiatan)</span>
                        </div>
                        <div style="font-size:0.8125rem;color:var(--color-text-muted);line-height:1.8;">
                            <i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> <strong>TCRT5000 (Mata)</strong> - Aktif penuh<br>
                            <i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> <strong>MPU6050 (Kepala)</strong> - Deteksi &gt;15° → notifikasi<br>
                            <i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> <strong>FSR402 (Pipi)</strong> - Ukur amplitudo & frekuensi terapi
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:var(--space-xl);padding:var(--space-lg);border-radius:var(--radius-lg);background:linear-gradient(135deg, var(--color-primary-surface), rgba(37,65,178,0.04));border:1px solid rgba(37,65,178,0.1);">
                <h4 style="margin-bottom:var(--space-sm);"><i data-lucide="bar-chart-2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Klasifikasi Pola Kedipan</h4>
                <p style="font-size:0.8125rem;color:var(--color-text-muted);line-height:1.7;">
                    Versi awal menggunakan pendekatan <strong>rule-based (state machine)</strong> pada ESP32 - sudah cukup akurat untuk pola yang jelas bedanya.
                    Data logging dikumpulkan untuk melatih model <strong>Random Forest / Decision Tree</strong> dari fitur turunan (durasi rata-rata, interval, jumlah kedip per sesi) guna personalisasi per pasien.
                    Model dilatih di server (Python + scikit-learn) → parameter diekspor ke ESP32.
                </p>
            </div>
        </div>
    </div>


    <div class="tab-content" id="tab-admin">
        <div class="card animate-fadeInUp">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-danger"><i data-lucide="settings" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Verifikasi Akun Tenaga Medis
                </h3>
                <span class="badge badge-warning" id="adminPendingBadge"> Pending</span>
            </div>

            <div id="adminEmptyState" class="empty-state" style="display:none;">
                <div class="empty-icon"><i data-lucide="clipboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                <div class="empty-title">Semua akun sudah diverifikasi</div>
                <div class="empty-desc">Tidak ada akun yang menunggu persetujuan saat ini.</div>
            </div>

            <div class="table-wrapper" id="adminTableWrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. STR</th>
                            <th>Tanggal Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="adminPendingBody">
                        <tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


</main>

<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
<script src="assets/js/firebase-config.js"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="assets/js/mqtt-client.js?v=2"></script>
<script src="assets/js/realtime.js?v=2"></script>
<script src="assets/js/chart-init.js"></script>
<script>
function switchTab(tabName) {
    // Update tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === `tab-${tabName}`);
    });
    // Sync sidebar tab highlights
    document.querySelectorAll('.sidebar-tab').forEach(link => {
        link.classList.toggle('sidebar-active', link.dataset.tab === tabName);
    });
    if (tabName === 'grafik' && !window._chartsInitialized) {
        initCharts();
        window._chartsInitialized = true;
    }
    if (tabName === 'histori' && !window._historyLoaded) {
        loadMedisHistory(1);
        window._historyLoaded = true;
    }

    if (tabName === 'admin' && !window._adminLoaded) {
        loadPendingUsers();
        window._adminLoaded = true;
    }

}

function highlightSidebarTab(el) {
    document.querySelectorAll('.sidebar-tab').forEach(a => a.classList.remove('sidebar-active'));
    el.classList.add('sidebar-active');
}

document.addEventListener('DOMContentLoaded', function() {
    EchoGlance.init({ pollingInterval: 10000 });

    if (typeof EchoMqtt !== 'undefined') {
        EchoMqtt.init();
    }

    EchoGlance.fetchDeviceStatus();
    EchoGlance.fetchNotifications({
        renderTarget: 'notificationList', showAction: true, displayLimit: 20
    });

    // Load summary dari Firebase
    EchoGlance.fetchSummaryFromFirebase();

    // Polling summary & notifikasi setiap 10 detik
    setInterval(() => {
        EchoGlance.fetchSummaryFromFirebase();
        EchoGlance.fetchNotifications({
            renderTarget: 'notificationList', showAction: true, displayLimit: 20
        });
    }, 10000);

    let searchTimer;
    const searchInput = document.getElementById('historySearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadMedisHistory(1), 400);
        });
    }
});

function initCharts() {
    EchoCharts.initFSRChart('fsrChart', {});
}

function applyDateFilter() {
    if (window._chartsInitialized) {
        EchoCharts.refreshCharts();
    }
    if (window._historyLoaded) {
        loadMedisHistory(1);
    }
    EchoGlance.showToast('Filter diterapkan', 'success');
}

let _medisHistoryPage = 1;
function loadMedisHistory(page) {
    _medisHistoryPage = page;
    const layerEl = document.getElementById('historyLayerFilter');
    const searchEl = document.getElementById('historySearch');
    const layer = layerEl ? layerEl.value : 'all';
    const search = searchEl ? searchEl.value : '';
    EchoGlance.fetchHistory({
        page, limit: 10, layer, search,
        renderTarget: 'medisHistoryBody',
        paginationTarget: 'medisHistoryPagination',
        onPageChange: 'loadMedisHistory',
        startNo: (page - 1) * 10 + 1
    });
}

function exportCSV() {
    const layerEl = document.getElementById('historyLayerFilter');
    const searchEl = document.getElementById('historySearch');
    const layer = layerEl ? layerEl.value : 'all';
    const search = searchEl ? searchEl.value : '';
    let url = `api/get_history.php?export=csv`;
    if (layer !== 'all') url += `&layer=${layer}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    window.open(url, '_blank');
}


function loadPendingUsers() {
    fetch('api/get_pending_users.php')
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const tbody = document.getElementById('adminPendingBody');
                const emptyState = document.getElementById('adminEmptyState');
                const tableWrapper = document.getElementById('adminTableWrapper');

                if (json.data.length === 0) {
                    emptyState.style.display = '';
                    tableWrapper.style.display = 'none';
                } else {
                    emptyState.style.display = 'none';
                    tableWrapper.style.display = '';
                    tbody.innerHTML = json.data.map(u => `
                        <tr id="pending-row-${u.id}">
                            <td><strong>${EchoGlance.escapeHtml(u.nama)}</strong></td>
                            <td>${EchoGlance.escapeHtml(u.email)}</td>
                            <td><code>${EchoGlance.escapeHtml(u.str_number || '-')}</code></td>
                            <td>${EchoGlance.formatDateTime(u.created_at)}</td>
                            <td>
                                <div class="admin-action-btns">
                                    <button class="btn btn-sm btn-success" onclick="EchoGlance.adminAction('approve', ${u.id})">
                                        <i data-lucide="check" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Setujui
                                    </button>
                                    <button class="btn btn-sm btn-outline btn-danger" onclick="updateAccountStatus(${u.id}, 'rejected')">
                                        <i data-lucide="x" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Tolak
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                }

                // Update badge
                const badge = document.getElementById('adminPendingBadge');
                if (badge) badge.textContent = json.count + ' Pending';
            }
        })
        .catch(err => console.error('[Admin]', err));
}

</script>

    <footer class="main-footer">
        <p>© 2026 <strong>ECHO-GLANCE</strong> - Dual-Layer Eye Blink Messenger · IT COMP</p>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>