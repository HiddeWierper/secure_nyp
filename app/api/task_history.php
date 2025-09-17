<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database connectie
try {
    $pdo = new PDO('sqlite:../../database/tasks.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDO\Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    $store_id = $_GET['store_id'] ?? '';
    $task_id = $_GET['task_id'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    // Base query voor task sets
    $sql = "SELECT ts.id, ts.manager, ts.day, ts.created_at, ts.submitted, ts.submitted_at, ts.store_id,
            s.name as store_name, s.address,
            r.name as region_name,
            u.username as manager_name
            FROM task_sets ts
            LEFT JOIN stores s ON ts.store_id = s.id
            LEFT JOIN regions r ON s.region_id = r.id
            LEFT JOIN users u ON ts.manager = u.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($store_id)) {
        $sql .= " AND ts.store_id = ?";
        $params[] = $store_id;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(ts.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(ts.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $sql .= " ORDER BY ts.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taskSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get task items voor elke task set
    foreach ($taskSets as &$taskSet) {
        $itemSql = "SELECT tsi.*, t.name as task_name, t.time, t.frequency, t.required, t.is_bk
                    FROM task_set_items tsi
                    LEFT JOIN tasks t ON tsi.task_id = t.id
                    WHERE tsi.task_set_id = ?";
        
        // Filter op specifieke taak indien gevraagd
        $itemParams = [$taskSet['id']];
        if (!empty($task_id)) {
            $itemSql .= " AND tsi.task_id = ?";
            $itemParams[] = $task_id;
        }
        
        $itemSql .= " ORDER BY t.name";
        
        $stmt = $pdo->prepare($itemSql);
        $stmt->execute($itemParams);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $taskSet['items'] = $items;
        $taskSet['total_time'] = array_sum(array_column($items, 'time'));
        
        // Als gefilterd op specifieke taak en geen items gevonden, verwijder deze task set
        if (!empty($task_id) && empty($items)) {
            $taskSet = null;
        }
    }
    
    // Verwijder null task sets (bij filteren op taak)
    $taskSets = array_filter($taskSets);
    
    echo json_encode([
        'success' => true,
        'history' => array_values($taskSets),
        'total' => count($taskSets)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>