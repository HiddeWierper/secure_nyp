<?php
// api/get_region_managers.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
error_log('API get_region_managers.php session region_id: ' . ($_SESSION['region_id'] ?? 'not set'));
error_log('API get_region_managers.php session user_role: ' . ($_SESSION['user_role'] ?? 'not set'));

// Check if user is logged in and is a regiomanager
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'regiomanager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd']);
    exit;
}

if (!isset($_SESSION['region_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Region ID not found in session']);
    exit;
}

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
    
    error_log("Managers found: " . count($managers));
    $stmt->execute([$regionId]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($managers);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
}
?>