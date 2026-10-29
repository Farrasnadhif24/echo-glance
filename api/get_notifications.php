<?php
/**
 * API: Get Notifications
 * GET - Fetch notifications from database
 * Params: today=1, limit=N, unread=1
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

$today  = isset($_GET['today']) && $_GET['today'] == '1';
$limit  = max(1, min(100, intval($_GET['limit'] ?? 20)));
$unread = isset($_GET['unread']) && $_GET['unread'] == '1';

try {
    $db = getDB();

    $where = ['patient_id = 1'];
    $params = [];

    if ($today) {
        $where[] = 'DATE(timestamp) = CURDATE()';
    }
    if ($unread) {
        $where[] = 'is_read = FALSE';
    }

    $whereClause = implode(' AND ', $where);

    // Get notifications
    $sql = "SELECT id, patient_id, jenis_kebutuhan, timestamp, is_read 
            FROM notifications 
            WHERE {$whereClause} 
            ORDER BY timestamp DESC 
            LIMIT ?";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    // Get unread count (always for today)
    $countStmt = $db->prepare(
        'SELECT COUNT(*) as cnt FROM notifications WHERE patient_id = 1 AND is_read = FALSE AND DATE(timestamp) = CURDATE()'
    );
    $countStmt->execute();
    $unreadCount = (int)$countStmt->fetch()['cnt'];

    jsonResponse([
        'success'      => true,
        'data'         => $notifications,
        'unread_count' => $unreadCount,
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil notifikasi: ' . $e->getMessage(), 500);
}
