<?php
// api_get_stores_and_managers.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$userRole = $_SESSION['user_role'];
if ($userRole === 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd: managers mogen geen taken genereren.']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Haal alle winkels op
    $stores = $db->query("SELECT id, name FROM stores ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // Haal managers per winkel op
    $managersByStore = [];
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