<?php
header('Content-Type: application/json');

if (!isset($_FILES['photo']) || !isset($_POST['task_set_id']) || !isset($_POST['task_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ontbrekende gegevens']);
    exit;
}

$taskSetId = (int)$_POST['task_set_id'];
$taskId = (int)$_POST['task_id'];
$uploadedFile = $_FILES['photo'];

// Validatie
if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload fout']);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($uploadedFile['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Alleen afbeeldingen toegestaan']);
    exit;
}

if ($uploadedFile['size'] > 5 * 1024 * 1024) { // 5MB
    echo json_encode(['success' => false, 'error' => 'Bestand te groot (max 5MB)']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Haal taak naam op voor bestandsnaam
    $stmt = $db->prepare("
        SELECT t.name 
        FROM tasks t
        JOIN task_set_items tsi ON t.id = tsi.task_id
        WHERE tsi.task_set_id = :task_set_id AND tsi.task_id = :task_id
    ");
    $stmt->execute([':task_set_id' => $taskSetId, ':task_id' => $taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Taak niet gevonden']);
        exit;
    }
    
    // Maak bestandsnaam
    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $safeTaskName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $task['name']);
    $fileName = $safeTaskName . '_' . $taskSetId . '_' . $taskId . '.' . $extension;
    
    // Upload directory
    $uploadDir = __DIR__ . '/../../public/uploads/photos/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filePath = $uploadDir . $fileName;
    
    // Verplaats bestand
    if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'error' => 'Kon bestand niet opslaan']);
        exit;
    }
    
    // Update database
    $relativePath = 'uploads/photos/' . $fileName;
    $stmt = $db->prepare("UPDATE task_set_items SET photo_path = :photo_path WHERE task_set_id = :task_set_id AND task_id = :task_id");
    $stmt->execute([
        ':photo_path' => $relativePath,
        ':task_set_id' => $taskSetId,
        ':task_id' => $taskId
    ]);
    
    echo json_encode([
        'success' => true,
        'photo_url' => $relativePath,
        'file_name' => $fileName
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>