<?php
/**
 * API: Mark Notification as Read
 * POST - Update is_read = TRUE for a notification
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();
requirePOST();

$body = getJsonBody();
$notifId = intval($body['id'] ?? 0);

if ($notifId <= 0) {
    jsonError('ID notifikasi tidak valid.');
}

try {
    $db = getDB();
    $stmt = $db->prepare('UPDATE notifications SET is_read = TRUE WHERE id = ? AND patient_id = 1');
    $stmt->execute([$notifId]);

    if ($stmt->rowCount() > 0) {
        // Get updated unread count
        $countStmt = $db->prepare(
            'SELECT COUNT(*) as cnt FROM notifications WHERE patient_id = 1 AND is_read = FALSE AND DATE(timestamp) = CURDATE()'
        );
        $countStmt->execute();
        $unreadCount = (int)$countStmt->fetch()['cnt'];

        jsonResponse([
            'success'      => true,
            'message'      => 'Notifikasi ditandai sudah ditangani.',
            'unread_count' => $unreadCount,
        ]);
    } else {
        jsonError('Notifikasi tidak ditemukan.', 404);
    }

} catch (PDOException $e) {
    jsonError('Gagal update notifikasi: ' . $e->getMessage(), 500);
}
