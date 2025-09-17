<?php
// api/get_region_managers.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

function logRegionManager($message) {
    $logFile = __DIR__ . '/getRegionManager.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

logRegionManager("=== GET REGION MANAGERS API CALLED ===");
logRegionManager("Session region_id: " . ($_SESSION['region_id'] ?? 'not set'));
logRegionManager("Session user_role: " . ($_SESSION['user_role'] ?? 'not set'));
logRegionManager("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));



// Check if user is logged in and is a regiomanager or developer
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['regiomanager', 'developer'])) {
    logRegionManager("ERROR: Access denied - user role: " . ($_SESSION['user_role'] ?? 'not set'));
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Toegang geweigerd']);
    exit;
}

if (!isset($_SESSION['region_id'])) {
    logRegionManager("ERROR: Region ID not found in session");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Region ID not found in session']);
    exit;
}

logRegionManager("Access granted - proceeding with region_id: " . $_SESSION['region_id']);

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $regionId = $_SESSION['region_id'];
    
    // Get managers in the region
    error_log("Region ID in session: " . $regionId);

    $stmt = $db->prepare("
        SELECT u.id, u.username, u.store_id, s.name as store_name 
        FROM users u 
        JOIN stores s ON u.store_id = s.id 
        JOIN region_stores rs ON s.id = rs.store_id
        WHERE u.role = 'manager' AND rs.region_id = ?
        ORDER BY s.name, u.username
    ");
    
    error_log("Executing query with region_id: $regionId");
    $stmt->execute([$regionId]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logRegionManager("DB Query executed - managers found: " . count($managers));
    
    echo json_encode(['success' => true, 'managers' => $managers]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
}
?>