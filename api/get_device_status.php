<?php
/**
 * API: Get Device Status
 * GET - Fetch device status from database
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

try {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM device_status WHERE patient_id = 1 LIMIT 1');
    $stmt->execute();
    $device = $stmt->fetch();

    if (!$device) {
        jsonError('Device status tidak ditemukan.', 404);
    }

    jsonResponse([
        'success' => true,
        'data'    => [
            'mode'         => $device['mode'],
            'sensors'      => [
                'mata' => (bool)$device['sensor_mata'],
                'mpu'  => (bool)$device['sensor_mpu'],
                'fsr'  => (bool)$device['sensor_fsr'],
            ],
            'last_updated' => $device['last_updated'],
        ],
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil status perangkat: ' . $e->getMessage(), 500);
}
