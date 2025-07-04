<?php
// api/get_region_managers.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
    $stmt = $db->prepare("
        SELECT u.id, u.username, s.name as store_name, s.id as store_id
        FROM users u
        JOIN stores s ON u.store_id = s.id
        WHERE u.role = 'manager' AND s.region_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$regionId]);
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($managers);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
}
?>