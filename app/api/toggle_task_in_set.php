<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['task_set_id'], $data['task_id'], $data['completed'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("UPDATE task_set_items SET completed = :completed WHERE task_set_id = :task_set_id AND task_id = :task_id");
    $stmt->execute([
        ':completed' => $data['completed'] ? 1 : 0,
        ':task_set_id' => $data['task_set_id'],
        ':task_id' => $data['task_id']
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>