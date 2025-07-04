<?php
session_start();
header('Content-Type: application/json');

// Check if user is authorized (regiomanager or admin)
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['regiomanager', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // If regiomanager, filter by region using the junction table
    if ($_SESSION['user_role'] === 'regiomanager') {
        if (!isset($_SESSION['region_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Region ID not found in session']);
            exit;
        }

        $region_id = $_SESSION['region_id'];

        $stmt = $db->prepare("
            SELECT u.id, u.username, u.store_id, s.name as store_name 
            FROM users u 
            JOIN stores s ON u.store_id = s.id 
            JOIN region_stores rs ON s.id = rs.store_id
            WHERE u.role = 'manager' AND rs.region_id = ?
            ORDER BY s.name, u.username
        ");
        $stmt->execute([$region_id]);
    } else {
        // Admin can see all managers
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.store_id, s.name as store_name 
            FROM users u 
            JOIN stores s ON u.store_id = s.id 
            WHERE u.role = 'manager'
            ORDER BY s.name, u.username
        ");
        $stmt->execute();
    }

    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'managers' => $managers
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>