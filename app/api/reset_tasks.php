<?php
header('Content-Type: application/json');

$dbFile = __DIR__ . '/../db/tasks.db';
if (!file_exists($dbFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database niet gevonden. Run init_db.php eerst.']);
    exit;
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("DELETE FROM task_completions");

    echo json_encode(['success' => true, 'message' => 'Alle afvink-statussen zijn gereset']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>