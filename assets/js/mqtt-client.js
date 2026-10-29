/**
 * Echo-Glance Firebase Realtime Client Module
 * Menggantikan MQTT Client untuk mendengarkan data langsung dari Firebase RTDB
 */

const EchoMqtt = {
    init() {
        console.log('[ECHO-GLANCE] Memulai koneksi Firebase Realtime Database');
        
        if (!window.db) {
            console.error('[ECHO-GLANCE] window.db (Firebase) belum diinisialisasi!');
            return;
        }

        if (window.EchoGlance && EchoGlance.showToast) {
            EchoGlance.showToast('Terhubung ke Firebase Realtime Database', 'success');
        }

        this.subscribeTopics();
    },

    subscribeTopics() {
        const db = window.db;

        // 1. Listen to Device Status
        db.ref('echoglance/device/status').on('value', (snapshot) => {
            if (snapshot.exists()) {
                const data = snapshot.val();
                if (window.EchoGlance && EchoGlance.renderDeviceStatus) {
                    
                    // Calculate device time offset unconditionally
                    if (data.last_updated) {
                        let deviceTime = data.last_updated;
                        if (deviceTime < 1e11) deviceTime *= 1000;
                        const newOffset = Date.now() - deviceTime;
                        
                        // Deteksi jika offset berubah drastis (> 10 detik) misal alat baru restart / online kembali
                        if (Math.abs(EchoGlance.state.deviceTimeOffset - newOffset) > 10000) {
                            EchoGlance.state.deviceTimeOffset = newOffset;
                            
                            // AUTO-REFRESH WEBSITE DATA (tanpa reload halaman)
                            if (window.location.pathname.includes('keluarga')) {
                                if (typeof loadHistory === 'function') loadHistory(window._currentHistoryPage || 1);
                                if (EchoGlance.fetchNotifications) EchoGlance.fetchNotifications({ today: true, limit: 20, renderTarget: 'notificationList', showAction: true });
                            } else {
                                if (EchoGlance.fetchHistory) EchoGlance.fetchHistory({ limit: 10, renderTarget: 'historyTableBody', paginationTarget: 'historyPagination' });
                            }
                            console.log("[ECHO-GLANCE] Alat terdeteksi online/restart, data website di-refresh otomatis.");
                        } else {
                            EchoGlance.state.deviceTimeOffset = newOffset;
                        }
                    }

                    EchoGlance.renderDeviceStatus(data);
                    // Reset MPU baseline saat mode berubah ke aktif (kalibrasi baru)
                    if (data.mode && data.mode !== EchoGlance.state.currentMode) {
                        if (data.mode === 'aktif' && EchoGlance.resetMPUBaseline) {
                            EchoGlance.resetMPUBaseline();
                        }
                        EchoGlance.state.currentMode = data.mode;
                    }
                }
            }
        });

        // 2. Listen to MPU Sensor (Pitch/Roll)
        db.ref('echoglance/device/sensor/mpu').on('value', (snapshot) => {
            if (snapshot.exists()) {
                const data = snapshot.val();
                let rawPitch = parseFloat(data.pitch) || 0;
                let rawRoll = parseFloat(data.roll) || 0;

                // Terapkan baseline offset agar posisi = 0 setelah kalibrasi
                let adjusted = { pitch: rawPitch, roll: rawRoll };
                if (window.EchoGlance && EchoGlance.applyMPUBaseline) {
                    adjusted = EchoGlance.applyMPUBaseline(rawPitch, rawRoll);
                }

                // Perbaiki arah: negate agar sesuai label maju/mundur dan kanan/kiri
                let pitch = -adjusted.pitch;  // Pitch: positif = maju, negatif = mundur
                let roll = -adjusted.roll;    // Roll: positif = kanan, negatif = kiri
                
                // Update Grafik MPU (text display)
                if (window.EchoCharts && EchoCharts.updateMPURealtime) {
                    EchoCharts.updateMPURealtime(pitch, roll, data.timestamp);
                }
                // Cek Peringatan Kemiringan (gunakan raw values, bukan adjusted)
                if (window.EchoGlance && EchoGlance.checkHeadTiltWarning) {
                    EchoGlance.checkHeadTiltWarning(adjusted.pitch, adjusted.roll);
                }
                // Update widget Live Monitor
                const badge = document.getElementById('liveKemiringanBadge');
                if (badge) {
                    if (Math.abs(adjusted.pitch) > 15 || Math.abs(adjusted.roll) > 15) {
                        badge.className = 'badge badge-danger';
                        badge.textContent = 'Miring!';
                    } else {
                        badge.className = 'badge badge-success';
                        badge.textContent = 'Normal';
                    }
                }
            }
        });

        // 3. Listen to FSR Sensor (Kekuatan Pipi)
        db.ref('echoglance/device/sensor/fsr').on('value', (snapshot) => {
            if (snapshot.exists()) {
                const data = snapshot.val();
                let force = data.force || 0;
                let avg = data.averageStrength || 0;
                
                // Update widget liveOtotPipi
                const liveOtotPipi = document.getElementById('liveOtotPipi');
                if (liveOtotPipi) {
                    liveOtotPipi.textContent = force + ' gram';
                }

                if (window.EchoCharts && EchoCharts.updateFSRRealtime) {
                    EchoCharts.updateFSRRealtime(force, avg, data.timestamp);
                }
            }
        });

        // 4. Listen to TCRT5000 (Blink)
        db.ref('echoglance/device/sensor/tcrt5000/blink').on('value', (snapshot) => {
            if (snapshot.exists()) {
                const blinkState = snapshot.val();
                if (window.EchoGlance && EchoGlance.tcrtProcess) {
                    EchoGlance.tcrtProcess(blinkState);
                }
            }
        });

        // 5. Listen to New Notifications (Child Added)
        db.ref('notifications').limitToLast(1).on('child_added', (snapshot) => {
            const data = snapshot.val();
            if (window.EchoGlance && EchoGlance.onNewNotification) {
                // Hanya proses notifikasi kebutuhan pasien yang dikonfirmasi
                if (EchoGlance.isPatientNeed && EchoGlance.isPatientNeed(data.jenis_kebutuhan)) {
                    if (window.loadHistory) window.loadHistory(1);
                    if (window.loadMedisHistory) window.loadMedisHistory(1);
                    EchoGlance.fetchNotifications({ renderTarget: 'notificationList', showAction: true });
                    EchoGlance.fetchSummaryFromFirebase();
                }
            }
        });
    },

    sendCommand(command, params = {}) {
        if (!window.db) return;
        const payload = { command, ...params, timestamp: Math.floor(Date.now()/1000) };
        window.db.ref('echoglance/device/status/set').set(payload);
        console.log('[FIREBASE] Command sent:', payload);
    }
};