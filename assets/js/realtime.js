
const EchoGlance = {

    config: {
        pollingInterval: 3000,
        chartPollingInterval: 10000,
        basePath: '',
        audioEnabled: false,
    },

    state: {
        lastNotifId: 0,
        pollingTimers: [],
        audioContext: null,
        notifCount: 0,
        currentMode: 'aktif',
        deviceTimeOffset: 0,
        fsr: {
            ADC_MIN: 80, ADC_MAX: 2000, NUM_SAMPLES: 20,
            samples: new Array(20).fill(0), sampleIndex: 0, totalSum: 0,
            currentShiftStart: localStorage.getItem('fsrCurrentShiftStart') ? parseInt(localStorage.getItem('fsrCurrentShiftStart')) : Date.now(),
            currentShiftSum: localStorage.getItem('fsrCurrentShiftSum') ? parseFloat(localStorage.getItem('fsrCurrentShiftSum')) : 0,
            currentShiftCount: localStorage.getItem('fsrCurrentShiftCount') ? parseInt(localStorage.getItem('fsrCurrentShiftCount')) : 0,
            trendData: JSON.parse(localStorage.getItem('fsrTrendData') || '[]'),
            SHIFT_DURATION: 8 * 60 * 60 * 1000
        },
        mpu: {
            baselinePitch: null,
            baselineRoll: null,
            calibrated: false
        },
        tcrt: {
            lastBlinkState: 0,
            lastBlinkTime: 0,
            menuIndex: 0
        }
    },

    init(options = {}) {
        Object.assign(this.config, options);
        this.setupAudio();
        console.log('[ECHO-GLANCE] Real-time engine initialized');
        
        // Auto-render Lucide icons on DOM changes
        if (window.lucide) {
            const observer = new MutationObserver((mutations) => {
                let shouldCreate = false;
                for (let mutation of mutations) {
                    if (mutation.addedNodes.length > 0) {
                        shouldCreate = true;
                        break;
                    }
                }
                if (shouldCreate) {
                    // Disconnect to avoid infinite loop when createIcons modifies the DOM
                    observer.disconnect();
                    lucide.createIcons();
                    // Reconnect after modifications
                    observer.observe(document.body, { childList: true, subtree: true });
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    },

    BLINK_PATTERNS: {
        layer1: [
            { pattern: 'Kedip 1×, tutup mata ±2 detik', meaning: 'Konfirmasi YA', icon: '<i data-lucide="check-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>' },
            { pattern: 'Kedip 2× cepat, interval ≤1 detik', meaning: 'Konfirmasi TIDAK', icon: '<i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>' },
        ],
        layer2_trigger: 'Kedip panjang ganda (2× tutup mata 1.5 detik, jeda 0.5 detik)',
        menu: [
            'Ingin makan?',
            'Ingin minum?',
            'Ingin buang air kecil?',
            'Ingin buang air besar?',
            'Ingin berjemur?',
            'Ingin tidur?',
        ]
    },

    MODES: {
        aktif: {
            label: 'AKTIF',
            desc: 'Semua sensor aktif - sesi terapi / kegiatan sehari-hari',
            sensors: { mata: true, mpu: true, fsr: true },
            color: 'success',
        },
        tidak_aktif: {
            label: 'TIDAK AKTIF',
            desc: 'Hanya sensor mata - pasien sedang istirahat/tidur',
            sensors: { mata: true, mpu: false, fsr: false },
            color: 'default',
        }
    },

    async fetchDeviceStatus() {

        this.renderDeviceStatus({
            mode: this.state.currentMode,
            sensors: { mata: true, mpu: true, fsr: true },
            last_updated: new Date().toISOString()
        });
    },

    async toggleMode() {
        if (typeof EchoMqtt !== 'undefined' && EchoMqtt.sendCommand) {
            EchoMqtt.sendCommand('toggle_mode');
            this.showToast('Perintah ganti mode dikirim ke alat...', 'info');
        } else {
            this.showToast('Gagal mengirim perintah. Klien tidak siap.', 'warning');
        }
    },

    togglePower(isOn) {
        const statusStr = isOn ? 'dinyalakan' : 'dimatikan';
        this.showToast(`Daya Alat ${statusStr}`, isOn ? 'success' : 'warning');
        if (typeof EchoMqtt !== 'undefined' && EchoMqtt.publishMode) {
            EchoMqtt.publishPower(isOn ? 'ON' : 'OFF');
        }
    },

    checkHeadTiltWarning(pitch, roll) {
        if (Math.abs(pitch) > 15 || Math.abs(roll) > 15) {
            const notifData = {
                id: 'warning_' + Date.now(),
                jenis_kebutuhan: 'Peringatan: Posisi kepala miring berlebih!',
                timestamp: new Date().toISOString(),
                is_read: 0,
                layer: 'pesan'
            };
            this.onNewNotification(notifData);
            this.updateNotifCount(this.state.notifCount + 1);
            
            // Add directly to notification list if available
            const container = document.getElementById('notificationList');
            if (container) {
                const urgency = 'notif-urgent';
                const html = `
                    <div class="notif-card ${urgency} new animate-fadeInUp" id="notif-${notifData.id}" data-id="${notifData.id}">
                        <div class="notif-icon"><i data-lucide="alert-triangle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                        <div class="notif-content">
                            <div class="notif-title">${this.escapeHtml(this.stripHtml(notifData.jenis_kebutuhan))}</div>
                            <div class="notif-time">
                                ${this.formatTime(notifData.timestamp)}
                                <span class="badge badge-urgent" style="font-size:0.5625rem;margin-left:6px;">Sistem</span>
                            </div>
                        </div>
                    </div>`;
                // Insert at top, remove empty state if exists
                const empty = container.querySelector('.empty-state');
                if (empty) empty.remove();
                container.insertAdjacentHTML('afterbegin', html);
            }
        }
    },

    arduinoMap(x, inMin, inMax, outMin, outMax) {
        return Math.floor((x - inMin) * (outMax - outMin) / (inMax - inMin) + outMin);
    },

    arduinoConstrain(x, lo, hi) {
        return Math.max(lo, Math.min(hi, x));
    },

    fsrProcess(rawADC) {
        let s = this.arduinoMap(rawADC, this.state.fsr.ADC_MIN, this.state.fsr.ADC_MAX, 0, 100);
        s = this.arduinoConstrain(s, 0, 100);
        if (s <= 8) s = 0;

        this.state.fsr.totalSum -= this.state.fsr.samples[this.state.fsr.sampleIndex];
        this.state.fsr.samples[this.state.fsr.sampleIndex] = s;
        this.state.fsr.totalSum += s;
        this.state.fsr.sampleIndex = (this.state.fsr.sampleIndex + 1) % this.state.fsr.NUM_SAMPLES;
        
        let avg = Math.floor(this.state.fsr.totalSum / this.state.fsr.NUM_SAMPLES);
        if (avg <= 3) avg = 0;

        // Record for Medical Trend
        this.recordShiftData(avg > 0 ? avg : s);

        return { currentStrength: s, averageStrength: avg, rawADC: rawADC };
    },

    recordShiftData(val) {
        if (val === 0) return;
        
        this.state.fsr.currentShiftSum += parseFloat(val);
        this.state.fsr.currentShiftCount++;
        
        localStorage.setItem('fsrCurrentShiftSum', this.state.fsr.currentShiftSum.toString());
        localStorage.setItem('fsrCurrentShiftCount', this.state.fsr.currentShiftCount.toString());
        
        const now = Date.now();
        if (now - this.state.fsr.currentShiftStart >= this.state.fsr.SHIFT_DURATION) {
            this.commitCurrentShift(now);
        }
    },

    commitCurrentShift(newStartTime) {
        if (this.state.fsr.currentShiftCount > 0) {
            const avg = (this.state.fsr.currentShiftSum / this.state.fsr.currentShiftCount).toFixed(1);
            this.state.fsr.trendData.push({ value: parseFloat(avg) });
            localStorage.setItem('fsrTrendData', JSON.stringify(this.state.fsr.trendData));
            
            // If chart object exists, it should be updated here. 
            // The dashboard might load it differently, but data is saved.
            if (typeof window.EchoCharts !== 'undefined' && window.EchoCharts.refreshTrendChart) {
                window.EchoCharts.refreshTrendChart();
            }
        }
        
        this.state.fsr.currentShiftStart = newStartTime;
        this.state.fsr.currentShiftSum = 0;
        this.state.fsr.currentShiftCount = 0;
        localStorage.setItem('fsrCurrentShiftStart', this.state.fsr.currentShiftStart.toString());
        localStorage.setItem('fsrCurrentShiftSum', '0');
        localStorage.setItem('fsrCurrentShiftCount', '0');
    },

    mpuProcess(ax, ay, az) {
        // Calculate Pitch and Roll from raw accelerometer data
        // pitch = atan2(-ax, sqrt(ay*ay + az*az)) * 180 / PI
        // roll = atan2(ay, az) * 180 / PI
        const pitch = Math.atan2(-ax, Math.sqrt(ay * ay + az * az)) * 180 / Math.PI;
        const roll = Math.atan2(ay, az) * 180 / Math.PI;
        
        return { pitch: pitch, roll: roll };
    },

    tcrtProcess(blinkState) {
        const now = Date.now();
        // Simple debounce: 1 second
        if (blinkState === 1 && this.state.tcrt.lastBlinkState === 0 && (now - this.state.tcrt.lastBlinkTime > 1000)) {
            this.state.tcrt.lastBlinkTime = now;
            
            // Move to next menu item for the next blink
            // Notifikasi hanya akan muncul jika dikonfirmasi (iya) dari Firebase.
            const menuItems = this.BLINK_PATTERNS.menu;
            this.state.tcrt.menuIndex = (this.state.tcrt.menuIndex + 1) % menuItems.length;
        }
        this.state.tcrt.lastBlinkState = blinkState;
    },

    renderDeviceStatus(data) {
        const mode = data.mode;
        const modeInfo = this.MODES[mode] || this.MODES.tidak_aktif;

        const badge = document.getElementById('deviceModeBadge');
        if (badge) {
            if (mode === 'aktif') {
                badge.className = 'status-badge-lg aktif';
                badge.innerHTML = '● ' + modeInfo.label;
            } else {
                badge.className = 'status-badge-lg tidak-aktif';
                badge.innerHTML = '○ ' + modeInfo.label;
            }
        }

        const modeDesc = document.getElementById('deviceModeDesc');
        if (modeDesc) {
            modeDesc.textContent = modeInfo.desc;
        }

        const sensorMap = {
            'sensorMata': { active: data.sensors.mata, modeActive: modeInfo.sensors.mata },
            'sensorMPU':  { active: data.sensors.mpu,  modeActive: modeInfo.sensors.mpu },
            'sensorFSR':  { active: data.sensors.fsr,  modeActive: modeInfo.sensors.fsr },
        };

        for (const [id, info] of Object.entries(sensorMap)) {
            const el = document.getElementById(id);
            if (el) {
                if (!info.modeActive) {
                    el.className = 'sensor-status';
                    el.style.background = '#F3F4F6';
                    el.style.color = '#9CA3AF';
                    el.textContent = 'SLEEP';
                } else if (info.active) {
                    el.className = 'sensor-status ok';
                    el.style.background = '';
                    el.style.color = '';
                    el.textContent = 'OK';
                } else {
                    el.className = 'sensor-status error';
                    el.style.background = '';
                    el.style.color = '';
                    el.textContent = 'ERROR';
                }
            }
        }

        const lastUpdated = document.getElementById('deviceLastUpdated');
        if (lastUpdated && data.last_updated) {
            lastUpdated.textContent = 'Terakhir: ' + this.formatTime(data.last_updated);
        }
    },

    // Daftar kebutuhan pasien yang valid (sesuai firmware)
    VALID_NEEDS: [
        'ingin makan',
        'ingin minum',
        'ingin buang air kecil',
        'ingin buang air besar',
        'ingin berjemur',
        'ingin tidur',
        'peringatan',
        'posisi kepala',
        'konfirmasi ya',
        'ya',
        'iya'
    ],

    isPatientNeed(jenisKebutuhan) {
        if (!jenisKebutuhan) return false;
        const lower = jenisKebutuhan.toLowerCase();
        return this.VALID_NEEDS.some(need => lower.includes(need));
    },

    async ensureTimeOffset() {
        // Timestamp dari ESP32 sudah berupa Unix epoch seconds (NTP + WIB)
        // Tidak perlu menghitung offset — langsung gunakan timestamp apa adanya
        // Fungsi ini dipertahankan agar pemanggilan yang ada tidak error
    },

    async fetchNotifications(options = {}) {
        if (!window.db) return { success: false, data: [], unread_count: 0 };
        try {
            // Menggunakan query identik dengan fetchHistory agar data selaras
            const snapshot = await window.db.ref('notifications').orderByChild('timestamp').limitToLast(200).once('value');
            let data = [];
            snapshot.forEach(child => {
                data.push({ id: child.key, ...child.val() });
            });

            // Sorting descending by timestamp (sama persis dengan fetchHistory)
            data.sort((a, b) => {
                let tsA = a.timestamp || 0;
                let tsB = b.timestamp || 0;
                if (typeof tsA === 'number' && tsA < 1e11) tsA *= 1000;
                if (typeof tsB === 'number' && tsB < 1e11) tsB *= 1000;
                const tA = new Date(tsA).getTime();
                const tB = new Date(tsB).getTime();
                return (tB || 0) - (tA || 0);
            });

            // Filter: hanya kebutuhan pasien yang dikonfirmasi + peringatan
            data = data.filter(n => this.isPatientNeed(n.jenis_kebutuhan));

            // Hitung unread terpisah dari data yang ditampilkan
            const unreadCount = data.filter(n => !n.is_read || n.is_read === 0).length;
            
            if (options.renderTarget) {
                // Batasi tampilan di Overview agar UI tetap rapi
                const displayData = data.slice(0, options.displayLimit || 20);
                this.renderNotifications(displayData, options.renderTarget, options.showAction);
            }
            this.updateNotifCount(unreadCount);
            return { success: true, data: data, unread_count: unreadCount };
        } catch (error) {
            console.error('[ECHO-GLANCE] Error fetchNotifications:', error);
            return { success: false, data: [], unread_count: 0 };
        }
    },

    renderNotifications(notifications, targetId, showAction = true) {
        const container = document.getElementById(targetId);
        if (!container) return;

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="bell" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i></div>
                    <div class="empty-title">Tidak ada notifikasi</div>
                    <div class="empty-desc">Belum ada kebutuhan pasien saat ini</div>
                </div>`;
            return;
        }

        container.innerHTML = notifications.map((notif, idx) => {
            const urgency = this.getUrgencyClass(notif.jenis_kebutuhan);
            const icon = this.getUrgencyIcon(notif.jenis_kebutuhan);
            const layer = this.getLayer(notif.jenis_kebutuhan);
            const isNew = !notif.is_read && idx < 3;

            return `
                <div class="notif-card ${urgency} ${isNew ? 'new' : ''} animate-fadeInUp stagger-${Math.min(idx + 1, 6)}" 
                     id="notif-${notif.id}" data-id="${notif.id}">
                    <div class="notif-icon">${icon}</div>
                    <div class="notif-content">
                        <div class="notif-title">${this.escapeHtml(this.stripHtml(notif.jenis_kebutuhan))}</div>
                        <div class="notif-time">
                            ${this.formatTime(notif.timestamp)}
                        </div>
                        </div>
                    </div>
                </div>`;
        }).join('');
    },

    getUrgencyClass(jenis) {
        const lower = jenis.toLowerCase();
        if (lower.includes('peringatan')) return 'notif-urgent';
        if (lower.includes('nyeri') || lower.includes('sakit')) return 'notif-urgent';
        if (lower.includes('bak') || lower.includes('bab') || lower.includes('buang air')) return 'notif-warning';
        if (lower.includes('makan') || lower.includes('minum') || lower.includes('tidur') || lower.includes('berjemur') || lower.includes('posisi') || lower.includes('nonton')) return 'notif-info';
        return 'notif-default';
    },

    getUrgencyIcon(jenis) {
        const lower = jenis.toLowerCase();
        if (lower.includes('peringatan')) return '<i data-lucide="alert-triangle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('nyeri') || lower.includes('sakit')) return '<i data-lucide="alert-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('buang air kecil') || lower.includes('bak')) return '<i data-lucide="droplet" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('buang air besar') || lower.includes('bab')) return '<i data-lucide="droplet" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('berjemur')) return '<i data-lucide="sun" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('makan')) return '<i data-lucide="utensils" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('minum')) return '<i data-lucide="glass-water" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('tidur')) return '<i data-lucide="moon" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('posisi') || lower.includes('ubah posisi')) return '<i data-lucide="refresh-ccw" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('nonton') || lower.includes('tv')) return '<i data-lucide="tv" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        if (lower.includes('perubahan posisi kepala')) return '<i data-lucide="brain" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        return '<i data-lucide="megaphone" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
    },

    getLayer(jenis) {
        const lower = jenis.toLowerCase();

        const menuKeywords = ['makan', 'minum', 'tidur', 'berjemur', 'bak', 'bab', 'buang air', 'nonton', 'nyeri', 'sakit', 'posisi'];
        for (const kw of menuKeywords) {
            if (lower.includes(kw)) return 2;
        }
        return 1;
    },

    updateNotifCount(count) {
        const badge = document.getElementById('notifCountBadge');
        if (badge) {
            badge.textContent = count;
            badge.className = 'notification-count' + (count === 0 ? ' zero' : '');
        }
        this.state.notifCount = count;
    },

    onNewNotification(notif) {
        if (this.config.audioEnabled) {
            this.playNotifSound();
        }
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('ECHO-GLANCE', {
                body: notif.jenis_kebutuhan,
                icon: 'assets/img/logo_echo_glance.png'
            });
        }

    },

    async markAsRead(notifId) {
        if (!window.db) return;
        try {
            await window.db.ref('notifications/' + notifId).update({ is_read: 1 });
            const card = document.getElementById(`notif-${notifId}`);
            if (card) {
                card.classList.remove('new');
                const actionDiv = card.querySelector('.notif-action');
                if (actionDiv) {
                    actionDiv.innerHTML = '<span class="badge badge-aktif" style="font-size:0.625rem;"><i data-lucide="check" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Ditangani</span>';
                }
            }
            this.fetchNotifications({ renderTarget: null });
        } catch (err) {
            console.error('[ECHO-GLANCE] Mark read error:', err);
        }
    },

    async fetchHistory(options = {}) {
        if (!window.db) return { success: false, data: [] };
        await this.ensureTimeOffset();
        try {
            // Baca dari 'notifications' node (firmware menulis ke sini)
            const snapshot = await window.db.ref('notifications').orderByChild('timestamp').limitToLast(200).once('value');
            let data = [];
            snapshot.forEach(child => {
                const val = child.val();
                data.push({
                    id: child.key,
                    command_text: val.jenis_kebutuhan || '-',
                    layer: val.layer || 'layer_1',
                    response_time_ms: val.response_time_ms || 0,
                    timestamp: val.timestamp,
                    is_read: val.is_read,
                    ...val
                });
            });
            
            data.sort((a, b) => {
                let tsA = a.timestamp || 0;
                let tsB = b.timestamp || 0;
                if (typeof tsA === 'number' && tsA < 1e11) tsA *= 1000;
                if (typeof tsB === 'number' && tsB < 1e11) tsB *= 1000;
                const tA = new Date(tsA).getTime();
                const tB = new Date(tsB).getTime();
                return (tB || 0) - (tA || 0); // Descending
            });

            // Filter: hanya tampilkan kebutuhan yang dikonfirmasi pasien
            data = data.filter(n => this.isPatientNeed(n.jenis_kebutuhan || n.command_text));

            // Client-side filter: layer
            if (options.layer && options.layer !== 'all') {
                data = data.filter(d => d.layer === options.layer);
            }
            // Client-side filter: search
            if (options.search) {
                const q = options.search.toLowerCase();
                data = data.filter(d => (d.command_text || '').toLowerCase().includes(q));
            }

            // Client-side pagination
            const page = options.page || 1;
            const limit = options.limit || 10;
            const total = data.length;
            const totalPages = Math.max(1, Math.ceil(total / limit));
            const offset = (page - 1) * limit;
            const pageData = data.slice(offset, offset + limit);

            if (options.renderTarget) {
                this.renderHistoryTable(pageData, options.renderTarget, options.startNo || (offset + 1));
                if (options.paginationTarget) {
                    this.renderPagination(page, totalPages, options.paginationTarget, options.onPageChange);
                }
            }
            return { success: true, data: pageData, page: page, total_pages: totalPages };
        } catch (error) {
            console.error('[ECHO-GLANCE] Error fetchHistory:', error);
            return { success: false, data: [] };
        }
    },

    renderHistoryTable(data, targetId, startNo = 1) {
        const tbody = document.getElementById(targetId);
        if (!tbody) return;

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted" style="padding:2rem;">Tidak ada data</td></tr>`;
            return;
        }

        tbody.innerHTML = data.map((item, idx) => {
            return `
                <tr>
                    <td>${startNo + idx}</td>
                    <td>${this.formatDateTime(item.timestamp)}</td>
                    <td><strong>${this.escapeHtml(item.command_text)}</strong></td>
                </tr>`;
        }).join('');
    },

    renderPagination(currentPage, totalPages, targetId, onPageChange) {
        const container = document.getElementById(targetId);
        if (!container || totalPages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        let html = '';
        html += `<button class="page-btn" ${currentPage <= 1 ? 'disabled' : ''} 
                  onclick="${onPageChange}(${currentPage - 1})">‹</button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" 
                          onclick="${onPageChange}(${i})">${i}</button>`;
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                html += `<span class="page-btn" style="border:none;cursor:default;">…</span>`;
            }
        }

        html += `<button class="page-btn" ${currentPage >= totalPages ? 'disabled' : ''} 
                  onclick="${onPageChange}(${currentPage + 1})">›</button>`;

        container.innerHTML = html;
    },

    async fetchKeluargaHistory(options = {}) {
        if (!window.db) return { success: false, data: [] };
        await this.ensureTimeOffset();
        try {
            const snapshot = await window.db.ref('notifications').orderByChild('timestamp').limitToLast(options.limit || 50).once('value');
            let data = [];
            snapshot.forEach(child => {
                data.push({ id: child.key, ...child.val() });
            });
            
            data.sort((a, b) => {
                const tA = a.timestamp ? new Date(a.timestamp).getTime() : 0;
                const tB = b.timestamp ? new Date(b.timestamp).getTime() : 0;
                return tB - tA; // Descending
            });

            // Filter: hanya tampilkan kebutuhan yang dikonfirmasi pasien
            data = data.filter(n => this.isPatientNeed(n.jenis_kebutuhan));
            
            if (options.renderTarget) {
                this.renderKeluargaTable(data, options.renderTarget);
                if (options.paginationTarget) {
                    this.renderPagination(1, 1, options.paginationTarget, options.onPageChange);
                }
            }
            return { success: true, data: data, page: 1, total_pages: 1 };
        } catch (error) {
            console.error('[ECHO-GLANCE] Error fetchKeluargaHistory:', error);
            return { success: false, data: [] };
        }
    },

    renderKeluargaTable(data, targetId) {
        const tbody = document.getElementById(targetId);
        if (!tbody) return;

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="2" class="text-center text-muted" style="padding:2rem;">Tidak ada data hari ini</td></tr>`;
            return;
        }

        tbody.innerHTML = data.map(item => {
            const urgencyBadge = this.getUrgencyBadge(item.jenis_kebutuhan);

            return `
                <tr>
                    <td>${this.formatTime(item.timestamp)}</td>
                    <td>
                        ${urgencyBadge} ${this.escapeHtml(item.jenis_kebutuhan)}
                    </td>
                </tr>`;
        }).join('');
    },

    getUrgencyBadge(jenis) {
        const lower = jenis.toLowerCase();
        if (lower.includes('nyeri') || lower.includes('sakit')) return '<span class="badge badge-urgent" style="font-size:0.625rem;">Urgent</span>';
        if (lower.includes('bak') || lower.includes('bab') || lower.includes('buang air') || lower.includes('popok')) return '<span class="badge badge-warning" style="font-size:0.625rem;">Perlu</span>';
        return '';
    },

    startPolling(callbacks, interval) {
        const timer = setInterval(() => {
            callbacks.forEach(cb => cb());
        }, interval || this.config.pollingInterval);
        this.state.pollingTimers.push(timer);
        return timer;
    },

    stopAllPolling() {
        this.state.pollingTimers.forEach(timer => clearInterval(timer));
        this.state.pollingTimers = [];
    },

    setupAudio() {
        try {
            this.state.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('[ECHO-GLANCE] Web Audio API not supported');
        }
    },

    playNotifSound() {
        if (!this.state.audioContext) return;
        const ctx = this.state.audioContext;
        const oscillator = ctx.createOscillator();
        const gainNode = ctx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(ctx.destination);
        oscillator.frequency.value = 880;
        oscillator.type = 'sine';
        gainNode.gain.setValueAtTime(0.3, ctx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
        oscillator.start(ctx.currentTime);
        oscillator.stop(ctx.currentTime + 0.5);

        setTimeout(() => {
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.frequency.value = 1100;
            osc2.type = 'sine';
            gain2.gain.setValueAtTime(0.3, ctx.currentTime);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc2.start(ctx.currentTime);
            osc2.stop(ctx.currentTime + 0.4);
        }, 200);
    },

    toggleAudio() {
        this.config.audioEnabled = !this.config.audioEnabled;
        const btn = document.getElementById('audioToggleBtn');
        if (btn) {
            btn.classList.toggle('active', this.config.audioEnabled);
            btn.innerHTML = this.config.audioEnabled ? '<i data-lucide="volume-2" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Suara Aktif' : '<i data-lucide="volume-x" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i> Suara Mati';
        }
        if (this.config.audioEnabled && this.state.audioContext && this.state.audioContext.state === 'suspended') {
            this.state.audioContext.resume();
        }
    },

    async adminAction(action, userId) {
        if (!confirm(`Apakah Anda yakin ingin ${action === 'approve' ? 'menyetujui' : 'menolak'} akun ini?`)) {
            return;
        }
        try {
            const res = await fetch(`${this.config.basePath}api/admin_action.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, user_id: userId })
            });
            const json = await res.json();
            if (json.success) {
                const row = document.getElementById(`pending-row-${userId}`);
                if (row) {
                    row.style.transition = 'opacity 0.3s, transform 0.3s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => row.remove(), 300);
                }
                this.showToast(json.message, 'success');
            } else {
                this.showToast(json.message || 'Gagal memproses aksi.', 'danger');
            }
        } catch (err) {
            this.showToast('Terjadi kesalahan jaringan.', 'danger');
        }
    },

    showToast(message, type = 'info') {
        document.querySelectorAll('.toast').forEach(t => t.remove());
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} animate-fadeInUp`;
        let iconHtml = '';
        if (type === 'success') iconHtml = '<i data-lucide="check-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        else if (type === 'danger') iconHtml = '<i data-lucide="x-circle" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        else iconHtml = '<i data-lucide="info" style="width:1.2em;height:1.2em;display:inline-block;vertical-align:-0.125em"></i>';
        
        toast.innerHTML = `<span>${iconHtml}</span><span>${message}</span>`;
        document.body.appendChild(toast);
        if (typeof lucide !== 'undefined') lucide.createIcons();
        setTimeout(() => toast.classList.add('show'), 50);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    formatTime(datetime) {
        if (!datetime) return '-';
        let ts = datetime;
        if (typeof ts === 'number' || (typeof ts === 'string' && !isNaN(ts) && ts.trim() !== '')) {
            const num = Number(ts);
            // Konversi Unix epoch seconds ke milliseconds
            ts = num > 1e11 ? num : num * 1000;
        }
        
        const d = new Date(ts);
        // Validasi: pastikan tanggal wajar (setelah tahun 2020)
        if (d.getFullYear() < 2020) return '-';
        return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    },

    formatDateTime(datetime) {
        if (!datetime) return '-';
        let ts = datetime;
        if (typeof ts === 'number' || (typeof ts === 'string' && !isNaN(ts) && ts.trim() !== '')) {
            const num = Number(ts);
            // Konversi Unix epoch seconds ke milliseconds
            ts = num > 1e11 ? num : num * 1000;
        }
        
        const d = new Date(ts);
        // Validasi: pastikan tanggal wajar (setelah tahun 2020)
        if (d.getFullYear() < 2020) return '-';
        return d.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
               ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    stripHtml(text) {
        if (!text) return '';
        return text.replace(/<[^>]*>/g, '').trim();
    },

    requestNotifPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    },

    // === Issue 1: Ringkasan hari ini dari Firebase ===
    async fetchSummaryFromFirebase() {
        if (!window.db) return;
        try {
            const snapshot = await window.db.ref('notifications').once('value');
            if (!snapshot.exists()) {
                this._renderSummary({ total: 0, mostCommon: '-', layer1: 0, layer2: 0 });
                return;
            }

            const now = new Date();
            const todayStr = now.toISOString().slice(0, 10); // YYYY-MM-DD
            const todayStart = new Date(todayStr + 'T00:00:00').getTime() / 1000;
            const todayEnd = todayStart + 86400;

            let totalEvents = 0;
            let layer1Count = 0;
            let layer2Count = 0;
            const needCounts = {};

            snapshot.forEach(child => {
                const val = child.val();
                const ts = val.timestamp || 0;

                // Hanya data hari ini & hanya kebutuhan pasien yang dikonfirmasi
                if (ts >= todayStart && ts < todayEnd && this.isPatientNeed(val.jenis_kebutuhan)) {
                    totalEvents++;

                    // Layer distribution
                    if (val.layer === 'layer_2') layer2Count++;
                    else layer1Count++;

                    // Most common need
                    const need = val.jenis_kebutuhan || 'Unknown';
                    needCounts[need] = (needCounts[need] || 0) + 1;
                }
            });

            // Find most common
            let mostCommon = '-';
            let maxCount = 0;
            for (const [need, count] of Object.entries(needCounts)) {
                if (count > maxCount) {
                    maxCount = count;
                    mostCommon = need;
                }
            }

            this._renderSummary({ total: totalEvents, mostCommon, layer1: layer1Count, layer2: layer2Count });
        } catch (err) {
            console.error('[ECHO-GLANCE] Summary error:', err);
        }
    },

    _renderSummary(data) {
        const el = (id) => document.getElementById(id);
        if (el('summaryTotalEvents')) el('summaryTotalEvents').textContent = data.total;
        if (el('summaryMostCommon')) el('summaryMostCommon').textContent = data.mostCommon;
        if (el('summaryLayer1')) el('summaryLayer1').textContent = data.layer1;
        if (el('summaryLayer2')) el('summaryLayer2').textContent = data.layer2;
    },

    // === Issue 4: Reset MPU baseline ===
    resetMPUBaseline() {
        this.state.mpu.baselinePitch = null;
        this.state.mpu.baselineRoll = null;
        this.state.mpu.calibrated = false;
        console.log('[ECHO-GLANCE] MPU baseline reset — will recalibrate on next data');
    },

    applyMPUBaseline(pitch, roll) {
        if (!this.state.mpu.calibrated) {
            this.state.mpu.baselinePitch = pitch;
            this.state.mpu.baselineRoll = roll;
            this.state.mpu.calibrated = true;
            console.log(`[ECHO-GLANCE] MPU baseline set: pitch=${pitch}, roll=${roll}`);
        }
        return {
            pitch: pitch - this.state.mpu.baselinePitch,
            roll: roll - this.state.mpu.baselineRoll
        };
    }
};

window.EchoGlance = EchoGlance;
