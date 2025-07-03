<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'regiomanager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("
        SELECT s.id, s.name, s.address, s.manager_id, u.username as manager_name
        FROM stores s
        JOIN region_stores rs ON s.id = rs.store_id
        LEFT JOIN users u ON s.manager_id = u.id
        WHERE rs.region_id = (SELECT region_id FROM users WHERE id = ?)
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>