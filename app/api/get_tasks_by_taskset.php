<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

if (!isset($_GET['task_set_id']) || !is_numeric($_GET['task_set_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige task_set_id']);
    exit;
}

$taskSetId = (int)$_GET['task_set_id'];

// Database connectie (pas aan naar jouw config)
$dbFile = __DIR__ . '/../db/tasks.db'; // voorbeeld pad
if (!file_exists($dbFile)) {
    echo json_encode(['success' => false, 'error' => 'Database niet gevonden']);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optioneel: check of gebruiker rechten heeft op deze task_set_id
    // Bijvoorbeeld: alleen taken tonen als gebruiker manager is van deze winkel
    // Dit kun je hier toevoegen als je wilt.

    $stmt = $pdo->prepare('SELECT id, description, time_minutes, frequency, completed FROM tasks WHERE task_set_id = :taskSetId ORDER BY id ASC');
    $stmt->execute([':taskSetId' => $taskSetId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Zet completed om naar boolean
    foreach ($tasks as &$task) {
        $task['completed'] = (bool)$task['completed'];
    }

    echo json_encode(['success' => true, 'tasks' => $tasks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}