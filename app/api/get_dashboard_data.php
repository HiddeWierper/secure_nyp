<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'regiomanager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['region_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Region ID not found in session', 'session' => $_SESSION]);
    exit;
}

// Rest van je code blijft hetzelfde...

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $region_id = $_SESSION['region_id'];
    
    // Get total stores in region
    $stmt = $db->prepare("SELECT COUNT(*) FROM stores WHERE region_id = ?");
    $stmt->execute([$region_id]);
    $totalStores = $stmt->fetchColumn();
    
    // Get total managers in region
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u 
        JOIN stores s ON u.store_id = s.id 
        WHERE s.region_id = ? AND u.role = 'manager'
    ");
    $stmt->execute([$region_id]);
    $totalManagers = $stmt->fetchColumn();
    
    // Get active task sets (not submitted)
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM task_sets ts 
        JOIN stores s ON ts.store_id = s.id 
        WHERE s.region_id = ? AND ts.submitted = 0
    ");
    $stmt->execute([$region_id]);
    $activeTasks = $stmt->fetchColumn();
    
    // Get completion rate
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN tsi.completed = 1 THEN 1 END) as completed,
            COUNT(*) as total
        FROM task_set_items tsi 
        JOIN task_sets ts ON tsi.task_set_id = ts.id
        JOIN stores s ON ts.store_id = s.id 
        WHERE s.region_id = ?
    ");
    $stmt->execute([$region_id]);
    $taskStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $completionRate = $taskStats['total'] > 0 ? 
        round(($taskStats['completed'] / $taskStats['total']) * 100, 1) : 0;
    
    // Get tasks per store
    $stmt = $db->prepare("
        SELECT s.name, COUNT(tsi.id) as task_count
        FROM stores s 
        LEFT JOIN task_sets ts ON s.id = ts.store_id 
        LEFT JOIN task_set_items tsi ON ts.id = tsi.task_set_id
        WHERE s.region_id = ?
        GROUP BY s.id, s.name
        ORDER BY s.name
    ");
    $stmt->execute([$region_id]);
    $tasksPerStore = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get completion trend (last 7 days) - using submitted task sets
    $stmt = $db->prepare("
        SELECT 
            DATE(ts.submitted_at) as date,
            COUNT(*) as completed_tasks
        FROM task_sets ts 
        JOIN stores s ON ts.store_id = s.id 
        WHERE s.region_id = ? 
        AND ts.submitted = 1 
        AND ts.submitted_at >= DATE('now', '-7 days')
        GROUP BY DATE(ts.submitted_at)
        ORDER BY date
    ");
    $stmt->execute([$region_id]);
    $completionTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'totalStores' => $totalStores,
            'totalManagers' => $totalManagers,
            'activeTasks' => $activeTasks,
            'completionRate' => $completionRate,
            'tasksPerStore' => $tasksPerStore,
            'completionTrend' => $completionTrend
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}
?>