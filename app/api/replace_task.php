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

if (!isset($data['task_set_id'], $data['old_task_id'], $data['new_task_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

$taskSetId = (int)$data['task_set_id'];
$oldTaskId = (int)$data['old_task_id'];
$newTaskId = (int)$data['new_task_id'];

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check of nieuwe taak al in task set zit
    $stmt = $db->prepare("SELECT COUNT(*) FROM task_set_items WHERE task_set_id = :task_set_id AND task_id = :new_task_id");
    $stmt->execute([':task_set_id' => $taskSetId, ':new_task_id' => $newTaskId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Taak zit al in deze set']);
        exit;
    }

    // Vervang oude taak door nieuwe in task_set_items
    $stmt = $db->prepare("UPDATE task_set_items SET task_id = :new_task_id WHERE task_set_id = :task_set_id AND task_id = :old_task_id");
    $stmt->execute([
        ':new_task_id' => $newTaskId,
        ':task_set_id' => $taskSetId,
        ':old_task_id' => $oldTaskId
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>