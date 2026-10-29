<?php
/**
 * API: Toggle Device Mode
 * POST - Switch between aktif/tidak_aktif mode
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();
requirePOST();

try {
    $db = getDB();

    // Get current mode
    $stmt = $db->prepare('SELECT mode FROM device_status WHERE patient_id = 1 LIMIT 1');
    $stmt->execute();
    $device = $stmt->fetch();

    if (!$device) {
        jsonError('Device status tidak ditemukan.', 404);
    }

    // Toggle
    $newMode = ($device['mode'] === 'aktif') ? 'tidak_aktif' : 'aktif';

    // Update sensor states based on mode
    if ($newMode === 'aktif') {
        $stmt = $db->prepare(
            'UPDATE device_status SET mode = ?, sensor_mata = TRUE, sensor_mpu = TRUE, sensor_fsr = TRUE WHERE patient_id = 1'
        );
    } else {
        $stmt = $db->prepare(
            'UPDATE device_status SET mode = ?, sensor_mata = TRUE, sensor_mpu = FALSE, sensor_fsr = FALSE WHERE patient_id = 1'
        );
    }
    $stmt->execute([$newMode]);

    jsonResponse([
        'success' => true,
        'message' => 'Mode perangkat diubah menjadi: ' . strtoupper(str_replace('_', ' ', $newMode)),
        'mode'    => $newMode,
    ]);

} catch (PDOException $e) {
    jsonError('Gagal toggle mode: ' . $e->getMessage(), 500);
}
