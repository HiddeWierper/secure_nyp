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
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['manager'], $data['day'], $data['tasks'], $data['store_id']) || !is_array($data['tasks'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO task_sets (manager, day, store_id, created_at, submitted) VALUES (:manager, :day, :store_id, :created_at, 0)");
    $stmt->execute([
        ':manager' => $data['manager'],
        ':day' => $data['day'],
        ':store_id' => $data['store_id'],
        ':created_at' => date('c')
    ]);
    $task_set_id = $db->lastInsertId();

    $stmtItem = $db->prepare("INSERT INTO task_set_items (task_set_id, task_id, completed) VALUES (:task_set_id, :task_id, 0)");
    foreach ($data['tasks'] as $task) {
        $stmtItem->execute([
            ':task_set_id' => $task_set_id,
            ':task_id' => $task['id']
        ]);
    }

    $db->commit();

    echo json_encode(['success' => true, 'task_set_id' => $task_set_id]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>