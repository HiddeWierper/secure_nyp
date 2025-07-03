<?php
header('Content-Type: application/json');

$task_set_id = $_GET['task_set_id'] ?? '';

if (!$task_set_id) {
    echo json_encode(['success' => false, 'error' => 'Task set ID is verplicht']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Haal task set info op
    $stmt = $db->prepare("SELECT * FROM task_sets WHERE id = :task_set_id");
    $stmt->execute([':task_set_id' => $task_set_id]);
    $task_set = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task_set) {
        echo json_encode(['success' => false, 'error' => 'Taakset niet gevonden']);
        exit;
    }

    // Haal taken op die bij deze set horen
    $stmt = $db->prepare("
        SELECT t.* 
        FROM tasks t
        JOIN task_set_items tsi ON t.id = tsi.task_id
        WHERE tsi.task_set_id = :task_set_id
        ORDER BY t.id
    ");
    $stmt->execute([':task_set_id' => $task_set_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Haal completion status op
    $stmt = $db->prepare("SELECT task_id, completed FROM task_set_items WHERE task_set_id = :task_set_id");
    $stmt->execute([':task_set_id' => $task_set_id]);
    $completionsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completions = [];
    foreach ($completionsRaw as $row) {
        $completions[$row['task_id']] = (bool)$row['completed'];
    }

    echo json_encode([
        'success' => true,
        'task_set' => $task_set,
        'tasks' => $tasks,
        'completions' => $completions
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>