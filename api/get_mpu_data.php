<?php
/**
 * API: Get MPU6050 Data
 * GET - Fetch MPU sensor data for chart
 * Params: from, to, range (default: today)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

$from  = $_GET['from'] ?? '';
$to    = $_GET['to'] ?? '';
$range = $_GET['range'] ?? 'today';

try {
    $db = getDB();

    $where = ['patient_id = 1'];
    $params = [];

    if (!empty($from) && !empty($to)) {
        $where[] = 'DATE(timestamp) >= ?';
        $params[] = $from;
        $where[] = 'DATE(timestamp) <= ?';
        $params[] = $to;
    } elseif (!empty($from)) {
        $where[] = 'DATE(timestamp) >= ?';
        $params[] = $from;
    } elseif ($range === 'today') {
        $where[] = 'DATE(timestamp) = CURDATE()';
    } elseif ($range === 'week') {
        $where[] = 'timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    }

    $whereClause = implode(' AND ', $where);

    $sql = "SELECT pitch, roll, event_label, timestamp 
            FROM mpu_log 
            WHERE {$whereClause} 
            ORDER BY timestamp ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data'    => $data,
        'count'   => count($data),
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil data MPU: ' . $e->getMessage(), 500);
}
