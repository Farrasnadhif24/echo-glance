<?php
/**
 * API: Get Keluarga History
 * GET - Fetch notifications as history for keluarga dashboard
 * With pagination support
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

$page  = max(1, intval($_GET['page'] ?? 1));
$limit = max(1, min(100, intval($_GET['limit'] ?? 10)));

try {
    $db = getDB();

    // Count total for today
    $countStmt = $db->prepare(
        'SELECT COUNT(*) as total FROM notifications WHERE patient_id = 1 AND DATE(timestamp) = CURDATE()'
    );
    $countStmt->execute();
    $total = (int)$countStmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $limit));

    // Get paginated data
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare(
        'SELECT id, jenis_kebutuhan, timestamp, is_read 
         FROM notifications 
         WHERE patient_id = 1 AND DATE(timestamp) = CURDATE() 
         ORDER BY timestamp DESC 
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    jsonResponse([
        'success'     => true,
        'data'        => $data,
        'page'        => $page,
        'total'       => $total,
        'total_pages' => $totalPages,
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil histori keluarga: ' . $e->getMessage(), 500);
}
