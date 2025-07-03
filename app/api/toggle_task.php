<?php
header('Content-Type: application/json');

$dbFile = __DIR__ . '/../db/tasks.db';
if (!file_exists($dbFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database niet gevonden. Run init_db.php eerst.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['task_set_id'], $data['task_id'], $data['completed'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige data']);
    exit;
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Update completed status in task_set_items
    $stmt = $db->prepare("UPDATE task_set_items SET completed = :completed WHERE task_set_id = :task_set_id AND task_id = :task_id");
    $stmt->execute([
        ':completed' => $data['completed'] ? 1 : 0,
        ':task_set_id' => $data['task_set_id'],
        ':task_id' => $data['task_id']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>