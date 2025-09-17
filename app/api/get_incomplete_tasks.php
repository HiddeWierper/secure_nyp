<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    $error = 'Niet ingelogd';
    error_log(date('Y-m-d H:i:s') . " - " . $error . "\n", 3, __DIR__ . '/incomplete.log');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = 'Method not allowed';
    error_log(date('Y-m-d H:i:s') . " - " . $error . "\n", 3, __DIR__ . '/incomplete.log');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['task_set_id'])) {
        $error = 'Task set ID is required';
        error_log(date('Y-m-d H:i:s') . " - " . $error . "\n", 3, __DIR__ . '/incomplete.log');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    $taskSetId = intval($input['task_set_id']);

    if ($taskSetId <= 0) {
        $error = 'Invalid task set ID';
        error_log(date('Y-m-d H:i:s') . " - " . $error . "\n", 3, __DIR__ . '/incomplete.log');
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get incomplete tasks for the task set
    $stmt = $db->prepare("
        SELECT t.id, t.name
        FROM task_set_items tsi
        INNER JOIN tasks t ON tsi.task_id = t.id
        WHERE tsi.task_set_id = ? AND tsi.completed = 0
        ORDER BY t.name 
    ");

    $stmt->execute([$taskSetId]);
    $incompleteTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'incomplete_tasks' => $incompleteTasks
    ]);

} catch (PDOException $e) {
    $error = 'Database fout: ' . $e->getMessage();
    error_log(date('Y-m-d H:i:s') . " - " . $error . "\n", 3, __DIR__ . '/incomplete.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $error]);
}
?>