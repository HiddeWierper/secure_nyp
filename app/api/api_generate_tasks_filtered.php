<?php
// api_generate_tasks_filtered.php - Gefilterde taken genereren per regio

session_start();
header('Content-Type: application/json');

try {
    // Check of gebruiker is ingelogd
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Niet ingelogd');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Database connectie
    $db_path = '../db/tasks.db';
    if (!file_exists($db_path)) {
        $db_path = 'C:\\xampp\\htdocs\\secure_nyp\\app\\db\\tasks.db';
    }
    
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Haal gebruiker info op inclusief regio
    $user_stmt = $db->prepare("
        SELECT u.*, r.name as region_name 
        FROM users u 
        LEFT JOIN regions r ON u.region_id = r.id 
        WHERE u.id = ?
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Gebruiker niet gevonden');
    }
    
    // Bepaal welke winkels de gebruiker mag zien
    $stores_query = "";
    $stores_params = [];
    
    if ($user['role'] === 'admin') {
        // Admin ziet alle winkels
        $stores_query = "SELECT id, name, address FROM stores ORDER BY name";
    } elseif ($user['role'] === 'regiomanager') {
        // Regiomanager ziet alleen winkels in zijn regio
        if (!$user['region_id']) {
            throw new Exception('Regiomanager heeft geen regio toegewezen');
        }
        
        $stores_query = "
            SELECT s.id, s.name, s.address 
            FROM stores s 
            JOIN region_stores rs ON s.id = rs.store_id 
            WHERE rs.region_id = ? 
            ORDER BY s.name
        ";
        $stores_params = [$user['region_id']];
    } elseif ($user['role'] === 'manager') {
        // Manager ziet alleen zijn eigen winkel
        if (!$user['store_id']) {
            throw new Exception('Manager heeft geen winkel toegewezen');
        }
        
        $stores_query = "
            SELECT id, name, address 
            FROM stores 
            WHERE id = ? 
            ORDER BY name
        ";
        $stores_params = [$user['store_id']];
    } else {
        throw new Exception('Onbekende gebruikersrol');
    }
    
    // Haal gefilterde winkels op
    $stores_stmt = $db->prepare($stores_query);
    $stores_stmt->execute($stores_params);
    $stores = $stores_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Haal alle taken op
    $tasks_stmt = $db->query("SELECT id, name, time, frequency, required FROM tasks ORDER BY name");
    $tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Response samenstellen
    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'region_name' => $user['region_name']
        ],
        'stores' => $stores,
        'tasks' => $tasks,
        'filter_info' => [
            'role' => $user['role'],
            'region' => $user['region_name'],
            'store_count' => count($stores),
            'can_see_all' => $user['role'] === 'admin'
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>