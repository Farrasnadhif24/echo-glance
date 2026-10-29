<?php
/**
 * API: Get Command History
 * GET - Fetch command_history with pagination, filters, search
 * Also supports CSV export via ?export=csv
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

requireAPILogin();

$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = max(1, min(100, intval($_GET['limit'] ?? 10)));
$layer  = $_GET['layer'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';
$export = $_GET['export'] ?? '';

try {
    $db = getDB();

    $where = ['patient_id = 1'];
    $params = [];

    if ($layer !== 'all' && in_array($layer, ['layer_1', 'layer_2'])) {
        $where[] = 'layer = ?';
        $params[] = $layer;
    }

    if (!empty($search)) {
        $where[] = 'command_text LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if (!empty($from)) {
        $where[] = 'DATE(timestamp) >= ?';
        $params[] = $from;
    }

    if (!empty($to)) {
        $where[] = 'DATE(timestamp) <= ?';
        $params[] = $to;
    }

    $whereClause = implode(' AND ', $where);

    // CSV Export
    if ($export === 'csv') {
        $sql = "SELECT timestamp, command_text, layer, response_time_ms 
                FROM command_history 
                WHERE {$whereClause} 
                ORDER BY timestamp DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="echo-glance-history-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['Waktu', 'Command', 'Layer', 'Response Time (ms)']);
        
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['timestamp'],
                $row['command_text'],
                $row['layer'] === 'layer_1' ? 'Layer 1 - Ya/Tidak' : 'Layer 2 - Menu',
                $row['response_time_ms'],
            ]);
        }
        fclose($output);
        exit;
    }

    // Count total
    $countSql = "SELECT COUNT(*) as total FROM command_history WHERE {$whereClause}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['total'];
    $totalPages = max(1, ceil($total / $limit));

    // Get page data
    $offset = ($page - 1) * $limit;
    $sql = "SELECT id, command_text, layer, response_time_ms, timestamp 
            FROM command_history 
            WHERE {$whereClause} 
            ORDER BY timestamp DESC 
            LIMIT ? OFFSET ?";
    
    $allParams = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($sql);
    
    // Bind params with types
    $i = 1;
    foreach ($params as $p) {
        $stmt->bindValue($i++, $p);
    }
    $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll();

    jsonResponse([
        'success'     => true,
        'data'        => $data,
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => $totalPages,
    ]);

} catch (PDOException $e) {
    jsonError('Gagal mengambil histori: ' . $e->getMessage(), 500);
}
