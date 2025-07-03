<?php
header('Content-Type: application/json');

// Laad PHPMailer
require_once __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['task_set_id']) || empty($data['task_set_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check of task set bestaat
    $stmt = $db->prepare("SELECT * FROM task_sets WHERE id = :id");
    $stmt->execute([':id' => $data['task_set_id']]);
    $taskSet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taskSet) {
        echo json_encode(['success' => false, 'error' => 'Task set niet gevonden']);
        exit;
    }
    
    if ($taskSet['submitted']) {
        echo json_encode(['success' => false, 'error' => 'Deze dag is al ingediend']);
        exit;
    }

    // Haal taken en voltooiing op
    $stmt = $db->prepare("
        SELECT t.*, tsi.completed 
        FROM tasks t
        JOIN task_set_items tsi ON t.id = tsi.task_id
        WHERE tsi.task_set_id = :task_set_id
        ORDER BY t.name
    ");
    $stmt->execute([':task_set_id' => $data['task_set_id']]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update als ingediend
    $stmt = $db->prepare("UPDATE task_sets SET submitted = 1, submitted_at = datetime('now') WHERE id = :id");
    $stmt->execute([':id' => $data['task_set_id']]);

    // Bereken statistieken
    $totalTasks = count($tasks);
    $completedTasks = array_filter($tasks, fn($t) => $t['completed'] == 1);
    $completedCount = count($completedTasks);
    $percentage = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100) : 0;

    // Probeer e-mail te versturen
    $mailResult = sendTaskEmail($taskSet, $tasks, $completedCount, $totalTasks, $percentage);

    echo json_encode([
        'success' => true, 
        'message' => 'Taken succesvol ingediend' . ($mailResult['sent'] ? ' en e-mail verzonden!' : ''),
        'mail_sent' => $mailResult['sent'],
        'mail_error' => $mailResult['error'] ?? null,
        'completion_rate' => $percentage
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function sendTaskEmail($taskSet, $tasks, $completedCount, $totalTasks, $percentage) {
    try {
        // Laad .env configuratie
        $envFile = __DIR__ . '/../../.env';
        if (!file_exists($envFile)) {
            return ['sent' => false, 'error' => '.env bestand niet gevonden'];
        }
        
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        foreach ($envLines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        // Controleer verplichte velden
        $required = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_TO_ADDRESS'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                return ['sent' => false, 'error' => "Ontbrekende configuratie: $field"];
            }
        }

        $mail = new PHPMailer(true);
        
        // SMTP instellingen
        $mail->isSMTP();
        $mail->Host = $config['MAIL_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['MAIL_USERNAME'];
        $mail->Password = $config['MAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['MAIL_PORT'] ?? 587;
        $mail->CharSet = 'UTF-8';

        // Ontvangers
        $mail->setFrom($config['MAIL_FROM_ADDRESS'], $config['MAIL_FROM_NAME'] ?? 'Taakbeheer NYP');
        $mail->addAddress($config['MAIL_TO_ADDRESS']);

        // Haal foto's op uit database
        $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
        $stmt = $db->prepare("
            SELECT t.name as task_name, tsi.photo_path 
            FROM tasks t
            JOIN task_set_items tsi ON t.id = tsi.task_id
            WHERE tsi.task_set_id = :task_set_id AND tsi.photo_path IS NOT NULL
        ");
        $stmt->execute([':task_set_id' => $taskSet['id']]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Voeg foto's toe als bijlagen
        $attachmentCount = 0;
        foreach ($photos as $photo) {
            $photoPath = __DIR__ . '/../../public/' . $photo['photo_path'];
            if (file_exists($photoPath)) {
                $safeTaskName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $photo['task_name']);
                $extension = pathinfo($photoPath, PATHINFO_EXTENSION);
                $attachmentName = $safeTaskName . '.' . $extension;
                
                $mail->addAttachment($photoPath, $attachmentName);
                $attachmentCount++;
            }
        }

        // Inhoud
        $mail->isHTML(true);
        $mail->Subject = "Taken ingediend - {$taskSet['day']} ({$taskSet['manager']}) - {$attachmentCount} foto's";
        
        // Bepaal status kleur
        $statusColor = $percentage >= 80 ? '#22c55e' : ($percentage >= 50 ? '#f59e0b' : '#ef4444');
        $statusText = $percentage >= 80 ? 'Uitstekend' : ($percentage >= 50 ? 'Goed' : 'Aandacht nodig');
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; }
                .header { background: #3b82f6; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.9; }
                .content { padding: 30px 20px; }
                .stats { background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {$statusColor}; }
                .stats h3 { margin: 0 0 15px 0; color: #1f2937; }
                .stat-item { display: flex; justify-content: space-between; margin: 8px 0; }
                .stat-label { font-weight: 500; }
                .stat-value { font-weight: bold; color: {$statusColor}; }
                .task-list { margin: 25px 0; }
                .task-item { padding: 12px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
                .task-item:last-child { border-bottom: none; }
                .completed { background: #dcfce7; }
                .incomplete { background: #fef2f2; }
                .task-name { font-weight: 500; }
                .task-details { font-size: 12px; color: #6b7280; margin-top: 4px; }
                .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
                .badge-completed { background: #22c55e; color: white; }
                .badge-incomplete { background: #ef4444; color: white; }
                .photo-indicator { background: #3b82f6; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; margin-left: 8px; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
                .attachment-info { background: #e0f2fe; padding: 15px; border-radius: 8px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📋 Taken Ingediend</h1>
                    <p>{$taskSet['day']} - {$taskSet['manager']}</p>
                </div>
                
                <div class='content'>
                    <div class='stats'>
                        <h3>📊 Samenvatting</h3>
                        <div class='stat-item'>
                            <span class='stat-label'>Voltooide taken:</span>
                            <span class='stat-value'>{$completedCount} van {$totalTasks}</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-label'>Voltooiingspercentage:</span>
                            <span class='stat-value'>{$percentage}% ({$statusText})</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-label'>Foto's bijgevoegd:</span>
                            <span class='stat-value'>{$attachmentCount} foto's</span>
                        </div>
                        <div class='stat-item'>
                            <span class='stat-label'>Ingediend op:</span>
                            <span class='stat-value'>" . date('d-m-Y H:i') . "</span>
                        </div>
                    </div>";
        
        if ($attachmentCount > 0) {
            $mail->Body .= "
                    <div class='attachment-info'>
                        <h4>📎 Bijlagen</h4>
                        <p>Er zijn {$attachmentCount} foto's als bijlage toegevoegd aan deze e-mail.</p>
                    </div>";
        }
        
        $mail->Body .= "
                    <div class='task-list'>
                        <h3>📝 Takenlijst</h3>";
        
        foreach ($tasks as $task) {
            $isCompleted = $task['completed'] == 1;
            $statusClass = $isCompleted ? 'completed' : 'incomplete';
            $badgeClass = $isCompleted ? 'badge-completed' : 'badge-incomplete';
            $statusText = $isCompleted ? '✅ Voltooid' : '❌ Niet voltooid';
            $icon = $isCompleted ? '✅' : '❌';
            
            // Check of er een foto is voor deze taak
            $hasPhoto = false;
            foreach ($photos as $photo) {
                if ($photo['task_name'] === $task['name']) {
                    $hasPhoto = true;
                    break;
                }
            }
            $photoIndicator = $hasPhoto ? '<span class="photo-indicator">📷 Foto</span>' : '';
            
            $mail->Body .= "
                        <div class='task-item {$statusClass}'>
                            <div>
                                <div class='task-name'>{$icon} {$task['name']} {$photoIndicator}</div>
                                <div class='task-details'>⏱️ {$task['time']} minuten • 🔄 {$task['frequency']}</div>
                            </div>
                            <span class='status-badge {$badgeClass}'>{$statusText}</span>
                        </div>";
        }
        
        $mail->Body .= "
                    </div>
                </div>
                
                <div class='footer'>
                    <p>🏢 Automatisch gegenereerd door Taakbeheer Systeem NYP</p>
                    <p>📧 Dit is een automatisch bericht, niet beantwoorden</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return ['sent' => true, 'attachments' => $attachmentCount];
        
    } catch (Exception $e) {
        return ['sent' => false, 'error' => $e->getMessage()];
    }
}
?>