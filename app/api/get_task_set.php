<?php
header('Content-Type: application/json');

$manager = $_GET['manager'] ?? '';
$day = $_GET['day'] ?? '';

if (!$manager || !$day) {
    echo json_encode(['success' => false, 'error' => 'Manager en dag zijn verplicht']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT * FROM task_sets WHERE manager = :manager AND day = :day ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([':manager' => $manager, ':day' => $day]);
    $task_set = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task_set) {
        echo json_encode(['success' => false, 'error' => 'Geen taakset gevonden']);
        exit;
    }

    $stmt = $db->query("SELECT * FROM tasks ORDER BY id");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT task_id, completed FROM task_set_items WHERE task_set_id = :task_set_id");
    $stmt->execute([':task_set_id' => $task_set['id']]);
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