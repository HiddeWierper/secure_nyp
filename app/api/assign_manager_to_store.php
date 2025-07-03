<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'regiomanager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$store_id = $input['store_id'] ?? null;
$manager_id = $input['manager_id'] ?? null;

if (!$store_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Store ID is verplicht']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Controleer of de winkel bij deze regiomanager hoort
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM region_stores rs
        JOIN users u ON u.region_id = rs.region_id
        WHERE rs.store_id = ? AND u.id = ?
    ");
    $stmt->execute([$store_id, $_SESSION['user_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Deze winkel behoort niet tot jouw regio']);
        exit;
    }
    
    // Update manager
    $stmt = $db->prepare("UPDATE stores SET manager_id = ? WHERE id = ?");
    $stmt->execute([$manager_id, $store_id]);
    
    echo json_encode(['success' => true, 'message' => 'Manager toegewezen']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>