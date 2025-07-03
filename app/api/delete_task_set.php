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

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['task_set_id']) || !is_numeric($data['task_set_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige task set ID']);
    exit;
}

$taskSetId = (int)$data['task_set_id'];

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
        // Controleer eerst of de task set bestaat
        $checkStmt = $db->prepare("SELECT id, manager, day FROM task_sets WHERE id = :task_set_id");
        $checkStmt->execute([':task_set_id' => $taskSetId]);
        $taskSet = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$taskSet) {
            $db->rollBack();
            echo json_encode(['success' => false, 'error' => 'Taakset niet gevonden']);
            exit;
        }

        // Verwijder eerst alle task_set_items voor deze task set
        $deleteItemsStmt = $db->prepare("DELETE FROM task_set_items WHERE task_set_id = :task_set_id");
        $deleteItemsStmt->execute([':task_set_id' => $taskSetId]);
        $deletedItems = $deleteItemsStmt->rowCount();

        // Verwijder daarna de task set zelf
        $deleteSetStmt = $db->prepare("DELETE FROM task_sets WHERE id = :task_set_id");
        $deleteSetStmt->execute([':task_set_id' => $taskSetId]);
        $deletedSets = $deleteSetStmt->rowCount();

        // Commit de transactie
        $db->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Taakset succesvol verwijderd',
            'deleted_task_set' => $taskSet,
            'deleted_items_count' => $deletedItems
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