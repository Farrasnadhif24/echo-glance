<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
initSession();
requireLogin();

$user = getCurrentUser();
$userRole = $user['role'];

$initials = getInitials($user['nama']);
$roleDisplay = getRoleDisplayName($userRole);
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ECHO-GLANCE - Dashboard Keluarga - Monitoring Kebutuhan Pasien">
    <title>ECHO-GLANCE - Dashboard Keluarga</title>
    <link rel="icon" type="image/png" href="assets/img/logo_echo_glance.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
        <a href="dashboard-keluarga.php" class="nav-item active"><span class="nav-icon"><i data-lucide="layout-dashboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Dashboard Keluarga</span></a>
        
        <?php if (in_array($userRole, ['tenaga_medis', 'admin'])): ?>
        <a href="dashboard-medis.php" class="nav-item"><span class="nav-icon"><i data-lucide="layout-dashboard" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Dashboard Medis</span></a>
        <?php endif; ?>
        

        <div class="nav-section-label" style="margin-top: var(--space-md);">Pintasan Navigasi</div>
        <a href="#overview" class="nav-item"><span class="nav-icon"><i data-lucide="bell" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Notifikasi</span></a>
        <a href="#grafik-sensor" class="nav-item"><span class="nav-icon"><i data-lucide="line-chart" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Grafik Sensor</span></a>
        <a href="#histori" class="nav-item"><span class="nav-icon"><i data-lucide="clipboard-list" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span><span>Histori Hari Ini</span></a>
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
            <h1 style="margin:0;font-size:1.75rem;">Dashboard Keluarga</h1>
            <p style="font-size:0.8125rem;color:var(--color-text-muted);margin:2px 0 0;">Monitoring kebutuhan pasien secara real-time</p>
        </div>
        <div style="display:flex;align-items:center;gap:var(--space-md);">
            <div class="topbar-user-info" style="align-items: center;">
                <div style="text-align: right;">
                    <div style="font-size:0.8125rem;font-weight:600;color:var(--color-text);"><?= htmlspecialchars($user['nama']) ?></div>
                    <div style="font-size:0.6875rem;color:var(--color-text-muted);"><?= htmlspecialchars($roleDisplay) ?></div>
                </div>
                <div class="user-avatar-sm" style="margin-left: 8px;"><?= htmlspecialchars($initials) ?></div>
            </div>
                <button id="audioToggleBtn" class="btn btn-outline" style="width:100%; justify-content:center; padding:8px 0;" onclick="EchoGlance.toggleAudio()">
                    <span class="nav-icon"><i data-lucide="volume-x" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span> Suara Mati
                </button>
        </div>
    </div>

    <div class="grid-keluarga-top">

        <div class="card device-status-card animate-fadeInUp stagger-1">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-primary"><i data-lucide="zap" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Status Alat
                </h3>
                <span class="label-small" id="deviceLastUpdated">-</span>
            </div>

            <div class="status-device">
                <div class="device-mode-label">Mode Perangkat</div>
                <div class="status-badge-lg tidak-aktif" id="deviceModeBadge">
                    ○ MEMUAT...
                </div>
                <p id="deviceModeDesc" style="font-size:0.75rem;color:var(--color-text-muted);margin-top:6px;margin-bottom:var(--space-md);">
                    Memuat status perangkat...
                </p>

                <button class="btn btn-outline" style="width:100%; margin-bottom:var(--space-md); padding:8px; font-size:0.8125rem; display:flex; justify-content:center; align-items:center; gap:8px;" onclick="EchoGlance.toggleMode()">
                    <i data-lucide="repeat" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Ganti Mode (Non-Fisik)
                </button>

                <div class="sensor-grid">
                    <div class="sensor-item">
                        <span class="sensor-icon"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <span class="sensor-name">Mata (TCRT5000)</span>
                        <span class="sensor-status ok" id="sensorMata">—</span>
                    </div>
                    <div class="sensor-item">
                        <span class="sensor-icon"><i data-lucide="brain" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <span class="sensor-name">Kepala (MPU6050)</span>
                        <span class="sensor-status ok" id="sensorMPU">—</span>
                    </div>
                    <div class="sensor-item">
                        <span class="sensor-icon"><i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <span class="sensor-name">Pipi (FSR402)</span>
                        <span class="sensor-status ok" id="sensorFSR">—</span>
                    </div>
                </div>

                <div style="margin-top:var(--space-md); padding-top:var(--space-md); border-top:1px solid var(--color-border);">
                    <div class="device-mode-label" style="margin-bottom:8px;">Live Monitor Sensor</div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; background:var(--color-bg); padding:10px; border-radius:6px; border:1px solid var(--color-border);">
                            <span style="font-size:0.8125rem; color:var(--color-text-muted);"><i data-lucide="brain" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Posisi Kepala</span>
                            <span id="liveKemiringanBadge" class="badge badge-success" style="font-size:0.75rem;">Normal</span>
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

        </div>

        <div class="card notification-panel animate-fadeInUp stagger-2">
            <div class="card-header">
                <h3>
                    <span class="card-icon card-icon-accent"><i data-lucide="bell" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                    Notifikasi Real-Time
                </h3>
                <!-- <div class="notification-count zero" id="notifCountBadge">0</div> -->
            </div>

            <div class="notification-list" id="notificationList">
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="hourglass" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                    <div class="empty-title">Memuat notifikasi...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card animate-fadeInUp stagger-3" style="margin-bottom:var(--space-xl);">
        <div class="card-header">
            <h3>
                <span class="card-icon card-icon-primary"><i data-lucide="eye" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                Panduan Pola Kedipan Pasien
            </h3>
        </div>

        <div class="grid-2col gap-lg">
            <div style="padding:var(--space-md);border-radius:var(--radius-md);background:var(--color-primary-surface);border:1px solid rgba(37,65,178,0.15);">
                <div class="label-small" style="color:var(--color-primary);margin-bottom:var(--space-sm);">Lapisan 1 - Respon Cepat</div>
                <div style="display:flex;flex-direction:column;gap:var(--space-sm);">
                    <div style="display:flex;align-items:center;gap:var(--space-sm);font-size:0.8125rem;">
                        <span style="font-size:1.25rem;"><i data-lucide="check-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <div>
                            <strong style="color:var(--color-text);">YA</strong>
                            <div style="color:var(--color-text-muted);font-size:0.75rem;">Kedip 1×, tutup mata ±2 detik</div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:var(--space-sm);font-size:0.8125rem;">
                        <span style="font-size:1.25rem;"><i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                        <div>
                            <strong style="color:var(--color-text);">TIDAK</strong>
                            <div style="color:var(--color-text-muted);font-size:0.75rem;">Kedip 2× cepat, interval ≤1 detik</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="padding:var(--space-md);border-radius:var(--radius-md);background:var(--color-accent-surface);border:1px solid rgba(242,114,30,0.15);">
                <div class="label-small" style="color:var(--color-accent);margin-bottom:var(--space-sm);">Lapisan 2 - Menu Kebutuhan</div>
                <div style="font-size:0.75rem;color:var(--color-text-muted);margin-bottom:var(--space-sm);">
                    <strong style="color:var(--color-text);">Buka menu:</strong> Kedip panjang ganda (2× tutup 1.5 detik, jeda 0.5 detik)
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;" id="menuItemsList">
                </div>
                <div style="font-size:0.6875rem;color:var(--color-text-light);margin-top:var(--space-xs);">
                    DFPlayer membacakan pilihan <i data-lucide="megaphone" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> pasien kedip YA/TIDAK
                </div>
            </div>
        </div>
    </div>

    <!-- === GRAFIK SENSOR REAL-TIME === -->
    <div class="card chart-section animate-fadeInUp stagger-3" id="grafik-sensor" style="margin-bottom:var(--space-xl);">
        <div class="card-header">
            <h3>
                <span class="card-icon card-icon-primary"><i data-lucide="brain" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                Posisi Kepala - MPU6050
            </h3>
            <span class="label-small" style="color:var(--color-text-muted);"><span class="refresh-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--color-success);margin-right:4px;"></span> Real-time</span>
        </div>
        <div class="chart-container" style="display:flex;justify-content:space-around;align-items:center;padding:2rem 1rem;">
            <div style="text-align:center;flex:1;">
                <div style="font-size:0.875rem;color:var(--color-text-muted);margin-bottom:0.5rem;"><i data-lucide="move-vertical" style="width:1em;height:1em;display:inline-block;vertical-align:-0.125em"></i> Pitch (Maju/Mundur)</div>
                <div id="textMpuPitch" style="font-size:3rem;font-weight:700;color:#2541B2;font-family:var(--font-display);transition:color 0.3s;">0.0°</div>
            </div>
            <div style="width:1px;height:60px;background:var(--color-border);"></div>
            <div style="text-align:center;flex:1;">
                <div style="font-size:0.875rem;color:var(--color-text-muted);margin-bottom:0.5rem;"><i data-lucide="move-horizontal" style="width:1em;height:1em;display:inline-block;vertical-align:-0.125em"></i> Roll (Kanan/Kiri)</div>
                <div id="textMpuRoll" style="font-size:3rem;font-weight:700;color:#F2721E;font-family:var(--font-display);transition:color 0.3s;">0.0°</div>
            </div>
        </div>
    </div>

    <div class="card chart-section animate-fadeInUp stagger-4" style="margin-bottom:var(--space-xl);">
        <div class="card-header">
            <h3>
                <span class="card-icon card-icon-accent"><i data-lucide="activity" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                Kekuatan Otot Pipi - FSR402
            </h3>
            <div class="chart-legend-custom">
                <div class="legend-item"><span class="legend-dot" style="background:#2541B2;"></span> Gaya (gram)</div>
                <div class="legend-item"><span class="legend-dot baseline"></span> Baseline</div>
            </div>
        </div>
        <div class="chart-container" style="position:relative;height:280px;">
            <canvas id="fsrChart"></canvas>
        </div>
        <div class="chart-metrics" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--space-md);padding:var(--space-md);">
            <div class="chart-metric-card" style="text-align:center;padding:var(--space-md);background:var(--color-bg);border-radius:var(--radius-md);border:1px solid var(--color-border);">
                <div class="metric-value" id="metricAvgForce" style="font-size:1.25rem;font-weight:700;color:var(--color-primary);">-</div>
                <div class="metric-label" style="font-size:0.6875rem;color:var(--color-text-muted);margin-top:2px;">Rata-rata Gaya Tekan</div>
            </div>
            <div class="chart-metric-card" style="text-align:center;padding:var(--space-md);background:var(--color-bg);border-radius:var(--radius-md);border:1px solid var(--color-border);">
                <div class="metric-value" id="metricAvgMovements" style="font-size:1.25rem;font-weight:700;color:var(--color-accent);">-</div>
                <div class="metric-label" style="font-size:0.6875rem;color:var(--color-text-muted);margin-top:2px;">Gerakan / Menit</div>
            </div>
            <div class="chart-metric-card" style="text-align:center;padding:var(--space-md);background:var(--color-bg);border-radius:var(--radius-md);border:1px solid var(--color-border);">
                <div class="metric-value" id="metricStdDev" style="font-size:1.25rem;font-weight:700;color:var(--color-text);">-</div>
                <div class="metric-label" style="font-size:0.6875rem;color:var(--color-text-muted);margin-top:2px;">Standar Deviasi</div>
            </div>
        </div>
    </div>

    <div class="card history-section animate-fadeInUp stagger-5" id="histori">
        <div class="card-header">
            <h3>
                <span class="card-icon card-icon-primary"><i data-lucide="clipboard-list" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></span>
                Histori Kebutuhan Hari Ini
            </h3>
        </div>

        <div class="table-wrapper">
            <table class="table" id="keluargaHistoryTable">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Kebutuhan</th>
                    </tr>
                </thead>
                <tbody id="keluargaHistoryBody">
                    <tr><td colspan="2" class="text-center text-muted" style="padding:2rem;">Memuat data...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="keluargaHistoryPagination"></div>
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
document.addEventListener('DOMContentLoaded', function() {
    EchoGlance.init({ pollingInterval: 3000 });
    
    if (typeof EchoMqtt !== 'undefined') {
        EchoMqtt.init();
    }

    EchoGlance.requestNotifPermission();

    // Render menu items - sama persis dengan firmware
    const menuList = document.getElementById('menuItemsList');
    if (menuList) {
        const menuItems = [
            'Ingin makan?',
            'Ingin minum?',
            'Ingin buang air kecil?',
            'Ingin buang air besar?',
            'Ingin berjemur?',
            'Ingin tidur?',
        ];
        menuItems.forEach(item => {
            const span = document.createElement('span');
            span.style.cssText = 'font-size:0.6875rem;padding:3px 8px;border-radius:var(--radius-full);background:rgba(242,114,30,0.08);color:var(--color-text);white-space:nowrap;';
            span.textContent = item;
            menuList.appendChild(span);
        });
    }

    loadAllData();
    // Polling is now handled by MQTT (assets/js/mqtt-client.js)

    // Inisialisasi grafik sensor FSR
    if (typeof EchoCharts !== 'undefined') {
        EchoCharts.initFSRChart('fsrChart', {});
    }

    function loadAllData() {
        EchoGlance.fetchDeviceStatus();
        EchoGlance.fetchNotifications({
            today: true,
            limit: 20,
            renderTarget: 'notificationList',
            showAction: true
        });
    }

    loadHistory(1);
    setInterval(() => loadHistory(window._currentHistoryPage || 1), 10000);
});

function loadHistory(page) {
    window._currentHistoryPage = page;
    EchoGlance.fetchKeluargaHistory({
        page: page,
        limit: 10,
        renderTarget: 'keluargaHistoryBody',
        paginationTarget: 'keluargaHistoryPagination',
        onPageChange: 'loadHistory'
    });
}
</script>

    <footer class="main-footer">
        <p>© 2026 <strong>ECHO-GLANCE</strong> - Dual-Layer Eye Blink Messenger · ppns</p>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>
