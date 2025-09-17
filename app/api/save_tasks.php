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

if (!isset($data['tasks']) || !is_array($data['tasks'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->beginTransaction();

    // Verwijder alle taken
    $db->exec("DELETE FROM tasks");

    $stmt = $db->prepare("INSERT INTO tasks (name, time, frequency, required, is_bk) 
                        VALUES (:name, :time, :frequency, :required, :is_bk)");

    foreach ($data['tasks'] as $task) {
        $stmt->execute([
            ':name'      => $task['name'],
            ':time'      => $task['time'],
            ':frequency' => $task['frequency'],
            ':required'  => isset($task['required']) ? (int)$task['required'] : 0,
            ':is_bk'     => isset($task['is_bk']) ? (int)$task['is_bk'] : 0  // ✅ consistent!
        ]);
    }

    $db->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>