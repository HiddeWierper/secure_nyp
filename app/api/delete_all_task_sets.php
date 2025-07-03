<?php

if (!session_id()) if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$userRole = $_SESSION['user_role'];
if ($userRole === 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd: managers mogen geen taken genereren.']);
    exit;
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Alleen POST requests toegestaan']);
    exit;
}

try {
    $dbFile = __DIR__ . '/../db/tasks.db';
    
    // Check of database bestaat
    if (!file_exists($dbFile)) {
        echo json_encode(['success' => false, 'error' => 'Database niet gevonden']);
        exit;
    }

    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Begin transactie voor veiligheid
    $db->beginTransaction();

    try {
        // Verwijder eerst alle task_set_items (vanwege foreign key constraints)
        $stmt1 = $db->prepare("DELETE FROM task_set_items");
        $stmt1->execute();
        $deletedItems = $stmt1->rowCount();

        // Verwijder daarna alle task_sets
        $stmt2 = $db->prepare("DELETE FROM task_sets");
        $stmt2->execute();
        $deletedSets = $stmt2->rowCount();

        // Reset de auto-increment counters
        $db->exec("DELETE FROM sqlite_sequence WHERE name='task_sets'");
        $db->exec("DELETE FROM sqlite_sequence WHERE name='task_set_items'");

        // Commit de transactie
        $db->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Alle schoonmaakdagen succesvol verwijderd',
            'deleted_sets' => $deletedSets,
            'deleted_items' => $deletedItems
        ]);

    } catch (Exception $e) {
        // Rollback bij fout
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>