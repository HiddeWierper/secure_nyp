<?php
// api_get_stores_and_managers.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$userRole = $_SESSION['user_role'];

if ($userRole === 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd: managers mogen geen taken genereren.']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stores = [];
    $managersByStore = [];

    if ($userRole === 'regiomanager') {
        // Regiomanager: only stores in their region
        if (!isset($_SESSION['region_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Region ID not found in session']);
            exit;
        }

        $region_id = $_SESSION['region_id'];

        $stmt = $db->prepare("
            SELECT s.id, s.name
            FROM stores s
            INNER JOIN region_stores rs ON s.id = rs.store_id
            WHERE rs.region_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$region_id]);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($userRole === 'storemanager') {
        // Storemanager: only their own store
        if (!isset($_SESSION['store_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Store ID not found in session']);
            exit;
        }

        $store_id = $_SESSION['store_id'];

        $stmt = $db->prepare("SELECT id, name FROM stores WHERE id = ?");
        $stmt->execute([$store_id]);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Admin: all stores
        $stores = $db->query("SELECT id, name FROM stores ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get managers for each store
    foreach ($stores as $store) {
        $stmt = $db->prepare("SELECT id, username FROM users WHERE role = 'manager' AND store_id = ?");
        $stmt->execute([$store['id']]);
        $managersByStore[$store['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'stores' => $stores,
        'managersByStore' => $managersByStore,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database fout: ' . $e->getMessage()]);
}
?>