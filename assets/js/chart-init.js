
const EchoCharts = {
    charts: {},
    realtimeDataPoints: [],
    MAX_REALTIME_POINTS: 60,

    updateMPURealtime(pitch, roll, timestamp) {
        const textPitch = document.getElementById('textMpuPitch');
        const textRoll = document.getElementById('textMpuRoll');
        
        if (textPitch) {
            textPitch.textContent = parseFloat(pitch).toFixed(1) + '\u00B0';
        }
        if (textRoll) {
            textRoll.textContent = parseFloat(roll).toFixed(1) + '\u00B0';
        }
    },

    updateFSRRealtime(force, avg, timestamp) {
        // Update metric display
        const avgForce = document.getElementById('metricAvgForce');
        if (avgForce) {
            avgForce.textContent = parseFloat(force).toFixed(1) + ' g (Real-time)';
        }

        // Tambahkan data point ke chart secara langsung
        if (this.charts.fsr) {
            const chart = this.charts.fsr;
            const now = new Date();
            const timeLabel = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            
            // Tambah data point baru
            chart.data.labels.push(timeLabel);
            chart.data.datasets[0].data.push(parseFloat(force) || 0);
            
            // Update baseline dataset agar panjangnya sama
            if (chart.data.datasets[1]) {
                chart.data.datasets[1].data.push(chart.data.datasets[1].data[0] || 70);
            }

            // Batasi jumlah data point (rolling window)
            if (chart.data.labels.length > this.MAX_REALTIME_POINTS) {
                chart.data.labels.shift();
                chart.data.datasets[0].data.shift();
                if (chart.data.datasets[1]) {
                    chart.data.datasets[1].data.shift();
                }
            }

            // Update warna area (gunakan satu warna untuk line chart fill)
            chart.data.datasets[0].backgroundColor = 'rgba(37, 65, 178, 0.2)';
            chart.data.datasets[0].borderColor = '#2541B2';

            chart.update('none'); // 'none' = tanpa animasi agar cepat
        }
    },

    async initFSRChart(canvasId, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        let data = [];
        let baseline = 70.0;
        let todayStats = { avg_force: '0', avg_movements: '0', avg_std_dev: '0' };

        // Coba ambil data dari MySQL API
        const result = await this.fetchFSRData(options);
        if (result && result.data && result.data.length > 0) {
            data = result.data;
            baseline = result.baseline;
            todayStats = result.today_stats;
        }

        const labels = data.length > 0 
            ? data.map(d => {
                const dateStr = new Date(d.tanggal).toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit' });
                return `${dateStr} (${d.sesi_label})`;
            })
            : [];

        const forceData = data.map(d => parseFloat(d.gaya_rata_rata));
        const baselineData = new Array(labels.length).fill(baseline);

        if (this.charts.fsr) {
            this.charts.fsr.destroy();
        }

        this.charts.fsr = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Gaya Rata-rata (gram)',
                        data: forceData,
                        type: 'line',
                        backgroundColor: 'rgba(37, 65, 178, 0.2)', // Warna fill (gunung)
                        borderColor: '#2541B2',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4, // Kurva halus
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        order: 2,
                    },
                    {
                        label: `Baseline (${baseline} g)`,
                        data: baselineData,
                        type: 'line',
                        borderColor: '#DC2626',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 0,
                        fill: false,
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 0
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(26, 26, 46, 0.9)',
                        titleFont: { family: 'Inter', size: 12, weight: '600' },
                        bodyFont: { family: 'Inter', size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Inter', size: 10 },
                            color: '#9CA3AF',
                            maxRotation: 45,
                        },
                        border: { display: false },
                    },
                    y: {
                        grid: {
                            color: 'rgba(229, 231, 235, 0.5)',
                            drawBorder: false,
                        },
                        ticks: {
                            font: { family: 'Inter', size: 11 },
                            color: '#9CA3AF',
                            callback: v => v + ' g',
                        },
                        border: { display: false },
                        title: {
                            display: true,
                            text: 'Gaya Rata-rata (gram)',
                            font: { family: 'Inter', size: 11, weight: '500' },
                            color: '#6B7280',
                        }
                    }
                }
            }
        });

        this.updateFSRMetrics(todayStats);
    },

    async fetchFSRData(options = {}) {
        try {
            const params = new URLSearchParams();
            if (options.from) params.set('from', options.from);
            if (options.to) params.set('to', options.to);

            const res = await fetch(`api/get_fsr_data.php?${params}`);
            const json = await res.json();
            return json.success ? json : null;
        } catch (err) {
            console.warn('[ECHO-GLANCE] FSR data fetch from API failed, chart will use real-time data:', err.message);
            return null;
        }
    },

    updateFSRMetrics(stats) {
        const avgForce = document.getElementById('metricAvgForce');
        const avgMov = document.getElementById('metricAvgMovements');
        const avgStd = document.getElementById('metricStdDev');

        if (avgForce) avgForce.textContent = (stats.avg_force || '0') + ' g';
        if (avgMov) avgMov.textContent = stats.avg_movements || '0';
        if (avgStd) avgStd.textContent = stats.avg_std_dev || '0';
    },

    async refreshCharts(from, to) {
        // MPU is now real-time text UI only, no need to init chart
        await this.initFSRChart('fsrChart', { from, to });
    },

    destroyAll() {
        Object.values(this.charts).forEach(chart => {
            if (chart) chart.destroy();
        });
        this.charts = {};
    }
};

window.EchoCharts = EchoCharts;

