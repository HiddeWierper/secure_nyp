<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

function logDashboard($message) {
    $logFile = __DIR__ . '/getDashboardData.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

logDashboard("=== get_dashboard_data.php CALLED ===");
logDashboard("Session user_role=" . ($_SESSION['user_role'] ?? 'not set') . ", region_id=" . ($_SESSION['region_id'] ?? 'not set'));

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['regiomanager', 'developer'])) {
    logDashboard("AUTH ERROR: Unauthorized user_role=" . ($_SESSION['user_role'] ?? 'not set'));
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['region_id'])) {
    logDashboard("AUTH ERROR: Region ID not found in session");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Region ID not found in session', 'session' => $_SESSION]);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    logDashboard("DB: Connected to SQLite");

    $region_id = (int)$_SESSION['region_id'];

    // 1) Total stores in region (via region_stores)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM stores s
        JOIN region_stores rs ON rs.store_id = s.id
        WHERE rs.region_id = ?
    ");
    $stmt->execute([$region_id]);
    $totalStores = (int)$stmt->fetchColumn();
    logDashboard("Query totalStores for region {$region_id}: {$totalStores}");

    // 2) Total managers in region (via region_stores)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        JOIN stores s ON u.store_id = s.id
        JOIN region_stores rs ON rs.store_id = s.id
        WHERE rs.region_id = ? AND u.role = 'manager'
    ");
    $stmt->execute([$region_id]);
    $totalManagers = (int)$stmt->fetchColumn();
    logDashboard("Query totalManagers for region {$region_id}: {$totalManagers}");

    // 3) Active task sets (not submitted) (via region_stores)
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM task_sets ts
        JOIN stores s ON ts.store_id = s.id
        JOIN region_stores rs ON rs.store_id = s.id
        WHERE rs.region_id = ? AND ts.submitted = 0
    ");
    $stmt->execute([$region_id]);
    $activeTasks = (int)$stmt->fetchColumn();
    logDashboard("Query activeTasks (unsubmitted task_sets) for region {$region_id}: {$activeTasks}");

    // 4) Completion rate (all time) from task_set_items (via region_stores)
    $stmt = $db->prepare("
        SELECT
            COUNT(CASE WHEN tsi.completed = 1 THEN 1 END) AS completed,
            COUNT(*) AS total
        FROM task_set_items tsi
        JOIN task_sets ts ON tsi.task_set_id = ts.id
        JOIN stores s ON ts.store_id = s.id
        JOIN region_stores rs ON rs.store_id = s.id
        WHERE rs.region_id = ?
    ");
    $stmt->execute([$region_id]);
    $taskStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['completed' => 0, 'total' => 0];

    $completedCount = (int)($taskStats['completed'] ?? 0);
    $totalCount = (int)($taskStats['total'] ?? 0);
    $completionRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0.0;
    logDashboard("Query completionRate: completed={$completedCount}, total={$totalCount}, rate={$completionRate}%");

    // 5) Tasks per store (via region_stores)
    $stmt = $db->prepare("
        SELECT s.name AS store_name, COUNT(tsi.id) AS task_count
        FROM stores s
        JOIN region_stores rs ON rs.store_id = s.id
        LEFT JOIN task_sets ts ON s.id = ts.store_id
        LEFT JOIN task_set_items tsi ON ts.id = tsi.task_set_id
        WHERE rs.region_id = ?
        GROUP BY s.id, s.name
        ORDER BY s.name
    ");
    $stmt->execute([$region_id]);
    $tasksPerStore = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    logDashboard("Query tasksPerStore: rows=" . count($tasksPerStore));

    // 6) Completion trend last 7 days, percentage-ready: completed and total per day (via region_stores)
    $stmt = $db->prepare("
        SELECT
            DATE(ts.submitted_at) AS date,
            SUM(CASE WHEN tsi.completed = 1 THEN 1 ELSE 0 END) AS completed,
            COUNT(tsi.id) AS total
        FROM task_set_items tsi
        JOIN task_sets ts ON tsi.task_set_id = ts.id
        JOIN stores s ON ts.store_id = s.id
        JOIN region_stores rs ON rs.store_id = s.id
        WHERE rs.region_id = ?
          AND ts.submitted = 1
          AND ts.submitted_at >= DATE('now', '-7 days')
        GROUP BY DATE(ts.submitted_at)
        ORDER BY date
    ");
    $stmt->execute([$region_id]);
    $completionTrend = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    logDashboard("Query completionTrend (last 7 days): rows=" . count($completionTrend));

    // Optioneel: kleine sample in log
    if (!empty($tasksPerStore)) {
        logDashboard("tasksPerStore sample=" . json_encode(array_slice($tasksPerStore, 0, 3)));
    }
    if (!empty($completionTrend)) {
        logDashboard("completionTrend sample=" . json_encode(array_slice($completionTrend, 0, 3)));
    }

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
    logDashboard("RESPONSE: success=true with data sent");

} catch (Exception $e) {
    http_response_code(500);
    logDashboard("ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}