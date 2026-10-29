<?php
/**
 * API: Get FSR402 Data
 * GET - Fetch FSR sensor data for chart + metrics
 * Params: from, to
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

try {
    $db = getDB();

    $where = ['patient_id = 1'];
    $params = [];

    if (!empty($from) && !empty($to)) {
        $where[] = 'tanggal >= ?';
        $params[] = $from;
        $where[] = 'tanggal <= ?';
        $params[] = $to;
    } elseif (!empty($from)) {
        $where[] = 'tanggal >= ?';
        $params[] = $from;
    } else {
        // Default: last 5 days
        $where[] = 'tanggal >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)';
    }

    $whereClause = implode(' AND ', $where);

    // Get chart data
    $sql = "SELECT gaya_rata_rata, jumlah_gerakan, standar_deviasi, sesi_label, tanggal, timestamp 
            FROM fsr_log 
            WHERE {$whereClause} 
            ORDER BY tanggal ASC, FIELD(sesi_label, 'Pagi', 'Siang', 'Sore', 'Malam') ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    // Calculate baseline (first day's average, or overall minimum)
    $baseline = 70.0; // default baseline
    if (count($data) > 0) {
        // Use the first 3 data points as baseline (earliest recordings)
        $firstEntries = array_slice($data, 0, min(3, count($data)));
        $baselineSum = array_sum(array_column($firstEntries, 'gaya_rata_rata'));
        $baseline = round($baselineSum / count($firstEntries), 1);
    }

    // Today's stats
    $todayStmt = $db->prepare(
        'SELECT 
            ROUND(AVG(gaya_rata_rata), 1) as avg_force,
            ROUND(AVG(jumlah_gerakan), 1) as avg_movements,
            ROUND(AVG(standar_deviasi), 1) as avg_std_dev
         FROM fsr_log 
         WHERE patient_id = 1 AND tanggal = CURDATE()'
    );
    $todayStmt->execute();
    $todayStats = $todayStmt->fetch();

    jsonResponse([
        'success'     => true,
        'data'        => $data,
        'baseline'    => $baseline,
        'today_stats' => [
            'avg_force'     => $todayStats['avg_force'] ?? '0',
            'avg_movements' => $todayStats['avg_movements'] ?? '0',
            'avg_std_dev'   => $todayStats['avg_std_dev'] ?? '0',
        ],
        'count'       => count($data),
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil data FSR: ' . $e->getMessage(), 500);
}
