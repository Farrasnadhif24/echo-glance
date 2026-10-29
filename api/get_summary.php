<?php
/**
 * API: Get Dashboard Summary
 * GET - Today's summary statistics for medis dashboard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

try {
    $db = getDB();

    // Total events today (from notifications)
    $stmt = $db->prepare('SELECT COUNT(*) as total FROM notifications WHERE patient_id = 1 AND DATE(timestamp) = CURDATE()');
    $stmt->execute();
    $totalEvents = (int)$stmt->fetch()['total'];

    // Most common need today
    $stmt = $db->prepare(
        'SELECT jenis_kebutuhan, COUNT(*) as cnt 
         FROM notifications 
         WHERE patient_id = 1 AND DATE(timestamp) = CURDATE() 
         GROUP BY jenis_kebutuhan 
         ORDER BY cnt DESC 
         LIMIT 1'
    );
    $stmt->execute();
    $topNeed = $stmt->fetch();
    $mostCommon = $topNeed ? $topNeed['jenis_kebutuhan'] : '-';

    // Peak hour today
    $stmt = $db->prepare(
        'SELECT HOUR(timestamp) as hour, COUNT(*) as cnt 
         FROM notifications 
         WHERE patient_id = 1 AND DATE(timestamp) = CURDATE() 
         GROUP BY HOUR(timestamp) 
         ORDER BY cnt DESC 
         LIMIT 1'
    );
    $stmt->execute();
    $peakRow = $stmt->fetch();
    $peakHour = $peakRow ? sprintf('%02d:00', $peakRow['hour']) : '-';

    // Average response time today (from command_history)
    $stmt = $db->prepare(
        'SELECT ROUND(AVG(response_time_ms)) as avg_rt 
         FROM command_history 
         WHERE patient_id = 1 AND DATE(timestamp) = CURDATE() AND response_time_ms IS NOT NULL'
    );
    $stmt->execute();
    $avgRow = $stmt->fetch();
    $avgResponseTime = $avgRow['avg_rt'] ?? 0;

    // Layer distribution today
    $stmt = $db->prepare(
        'SELECT layer, COUNT(*) as cnt 
         FROM command_history 
         WHERE patient_id = 1 AND DATE(timestamp) = CURDATE() 
         GROUP BY layer'
    );
    $stmt->execute();
    $layers = $stmt->fetchAll();
    $layer1Count = 0;
    $layer2Count = 0;
    foreach ($layers as $l) {
        if ($l['layer'] === 'layer_1') $layer1Count = (int)$l['cnt'];
        if ($l['layer'] === 'layer_2') $layer2Count = (int)$l['cnt'];
    }

    jsonResponse([
        'success' => true,
        'data'    => [
            'total_events'      => $totalEvents,
            'most_common'       => $mostCommon,
            'peak_hour'         => $peakHour,
            'avg_response_time' => (int)$avgResponseTime,
            'layer1_count'      => $layer1Count,
            'layer2_count'      => $layer2Count,
        ],
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil ringkasan: ' . $e->getMessage(), 500);
}
