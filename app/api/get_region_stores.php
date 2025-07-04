<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$userRole = $_SESSION['user_role'];

if ($userRole !== 'regiomanager') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $userId = $_SESSION['user_id'];
    
    // Get user's region_id
    $user_stmt = $db->prepare("SELECT region_id FROM users WHERE id = ?");
    $user_stmt->execute([$userId]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['region_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Region ID not found']);
        exit;
    }
    
    $region_id = $user['region_id'];
    
    // Get stores in the region with their managers
    $stmt = $db->prepare("
        SELECT 
            s.id,
            s.name,
            s.address,
            GROUP_CONCAT(u.username, ', ') as managers
        FROM stores s
        JOIN region_stores rs ON s.id = rs.store_id
        LEFT JOIN users u ON s.id = u.store_id AND u.role = 'manager'
        WHERE rs.region_id = ?
        GROUP BY s.id, s.name, s.address
        ORDER BY s.name
    ");
    $stmt->execute([$region_id]);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($stores);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>