<?php
// API endpoint for getting active task sets
header('Content-Type: application/json');

// Start session to check user authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    // Connect to the database
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get parameters
    $storeId = isset($_GET['store_id']) ? $_GET['store_id'] : null;
    $userStoreRestriction = isset($_GET['user_store_restriction']) ? $_GET['user_store_restriction'] : null;
    $managerId = isset($_GET['manager_id']) ? $_GET['manager_id'] : null;
    $includeSubmitted = isset($_GET['include_submitted']) ? $_GET['include_submitted'] : false;

    // Build the query for active task sets
    $query = "SELECT 
                ts.id,
                ts.manager as manager_id,
                u.username as manager_name,
                ts.day,
                ts.created_at,
                ts.submitted,
                ts.submitted_at,
                ts.store_id,
                s.name as store_name,
                s.address as store_address,
                COUNT(tsi.id) as total_tasks,
                COUNT(CASE WHEN tsi.completed = 1 THEN 1 END) as completed_tasks,
                GROUP_CONCAT(DISTINCT t.frequency) as frequencies
              FROM task_sets ts
              JOIN stores s ON ts.store_id = s.id
              JOIN users u ON ts.manager = u.id
              LEFT JOIN task_set_items tsi ON ts.id = tsi.task_set_id
              LEFT JOIN tasks t ON tsi.task_id = t.id
              WHERE 1=1";

    $params = [];

    // Apply store filter
    if ($storeId) {
        $query .= " AND ts.store_id = :store_id";
        $params[':store_id'] = $storeId;
    }

    // Apply user store restriction
    if ($userStoreRestriction) {
        $query .= " AND ts.store_id = :user_store_restriction";
        $params[':user_store_restriction'] = $userStoreRestriction;
    }

    // Apply manager filter
    if ($managerId) {
        $query .= " AND ts.manager = :manager_id";
        $params[':manager_id'] = $managerId;
    }

    // Filter by submission status
    if (!$includeSubmitted) {
        $query .= " AND ts.submitted = 0";
    }

    // Group by task set
    $query .= " GROUP BY ts.id, ts.manager, u.username, ts.day, ts.created_at, ts.submitted, ts.submitted_at, ts.store_id, s.name, s.address";

    // Order by creation date (newest first)
    $query .= " ORDER BY ts.created_at DESC";

    // Execute query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $taskSets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process the results to add additional information
    foreach ($taskSets as &$taskSet) {
        // Calculate completion percentage
        $taskSet['completion_percentage'] = $taskSet['total_tasks'] > 0 
            ? round(($taskSet['completed_tasks'] / $taskSet['total_tasks']) * 100, 1) 
            : 0;

        // Parse frequencies
        $taskSet['frequencies_array'] = $taskSet['frequencies'] 
            ? array_unique(explode(',', $taskSet['frequencies'])) 
            : [];

        // Determine primary frequency (most common)
        $taskSet['primary_frequency'] = !empty($taskSet['frequencies_array']) 
            ? $taskSet['frequencies_array'][0] 
            : 'dagelijks';

        // Add status information
        $taskSet['status'] = $taskSet['submitted'] ? 'submitted' : 'active';
        
        // Calculate days since creation
        $createdDate = new DateTime($taskSet['created_at']);
        $now = new DateTime();
        $taskSet['days_since_creation'] = $now->diff($createdDate)->days;

        // Format dates for display
        $taskSet['created_at_formatted'] = $createdDate->format('d-m-Y H:i');
        if ($taskSet['submitted_at']) {
            $submittedDate = new DateTime($taskSet['submitted_at']);
            $taskSet['submitted_at_formatted'] = $submittedDate->format('d-m-Y H:i');
        }
    }

    // Calculate summary statistics
    $statistics = calculateTaskSetStatistics($db, $storeId, $userStoreRestriction, $managerId);

    // Return success response
    echo json_encode([
        'success' => true,
        'task_sets' => $taskSets,
        'statistics' => $statistics,
        'total_count' => count($taskSets)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function calculateTaskSetStatistics($db, $storeId, $userStoreRestriction, $managerId) {
    try {
        // Base query for statistics
        $baseQuery = "FROM task_sets ts 
                      JOIN task_set_items tsi ON ts.id = tsi.task_set_id 
                      WHERE 1=1";
        $params = [];

        // Apply filters
        if ($storeId) {
            $baseQuery .= " AND ts.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        if ($userStoreRestriction) {
            $baseQuery .= " AND ts.store_id = :user_store_restriction";
            $params[':user_store_restriction'] = $userStoreRestriction;
        }

        if ($managerId) {
            $baseQuery .= " AND ts.manager = :manager_id";
            $params[':manager_id'] = $managerId;
        }

        // Total active task sets
        $activeStmt = $db->prepare("SELECT COUNT(DISTINCT ts.id) as total " . $baseQuery . " AND ts.submitted = 0");
        $activeStmt->execute($params);
        $activeSets = $activeStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total submitted task sets
        $submittedStmt = $db->prepare("SELECT COUNT(DISTINCT ts.id) as total " . $baseQuery . " AND ts.submitted = 1");
        $submittedStmt->execute($params);
        $submittedSets = $submittedStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total tasks in active sets
        $activeTasksStmt = $db->prepare("SELECT COUNT(*) as total " . $baseQuery . " AND ts.submitted = 0");
        $activeTasksStmt->execute($params);
        $activeTasks = $activeTasksStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Completed tasks in active sets
        $completedTasksStmt = $db->prepare("SELECT COUNT(*) as total " . $baseQuery . " AND ts.submitted = 0 AND tsi.completed = 1");
        $completedTasksStmt->execute($params);
        $completedTasks = $completedTasksStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Calculate completion rate
        $completionRate = $activeTasks > 0 ? round(($completedTasks / $activeTasks) * 100, 1) : 0;

        // Tasks created today
        $todayStmt = $db->prepare("SELECT COUNT(DISTINCT ts.id) as total " . $baseQuery . " AND DATE(ts.created_at) = DATE('now')");
        $todayStmt->execute($params);
        $createdToday = $todayStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Tasks created this week
        $weekStmt = $db->prepare("SELECT COUNT(DISTINCT ts.id) as total " . $baseQuery . " AND DATE(ts.created_at) >= DATE('now', 'weekday 1', '-7 days')");
        $weekStmt->execute($params);
        $createdThisWeek = $weekStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'active_task_sets' => (int)$activeSets,
            'submitted_task_sets' => (int)$submittedSets,
            'total_task_sets' => (int)($activeSets + $submittedSets),
            'active_tasks' => (int)$activeTasks,
            'completed_tasks' => (int)$completedTasks,
            'completion_rate' => $completionRate,
            'created_today' => (int)$createdToday,
            'created_this_week' => (int)$createdThisWeek
        ];

    } catch (Exception $e) {
        // Return default statistics if calculation fails
        return [
            'active_task_sets' => 0,
            'submitted_task_sets' => 0,
            'total_task_sets' => 0,
            'active_tasks' => 0,
            'completed_tasks' => 0,
            'completion_rate' => 0,
            'created_today' => 0,
            'created_this_week' => 0
        ];
    }
}
?>