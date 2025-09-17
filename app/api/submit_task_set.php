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

// Extract incomplete reasons if provided
$incompleteReasons = $data['incomplete_reasons'] ?? [];
logMail("Received incomplete reasons: " . json_encode($incompleteReasons));

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check of task set bestaat en haal manager username op
    $stmt = $db->prepare("
        SELECT ts.*, u.username as manager_name
        FROM task_sets ts
        LEFT JOIN users u ON ts.manager = u.id
        WHERE ts.id = :id
    ");
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
        SELECT t.*, tsi.completed, tsi.task_id
        FROM tasks t
        JOIN task_set_items tsi ON t.id = tsi.task_id
        WHERE tsi.task_set_id = :task_set_id
        ORDER BY t.name
    ");
    $stmt->execute([':task_set_id' => $data['task_set_id']]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Store incomplete reasons in database
    if (!empty($incompleteReasons)) {
        logMail("Storing incomplete reasons for " . count($incompleteReasons) . " tasks");
        foreach ($incompleteReasons as $taskId => $reason) {
            $stmt = $db->prepare("
                UPDATE task_set_items
                SET incomplete_reason = :reason
                WHERE task_set_id = :task_set_id AND task_id = :task_id
            ");
            $stmt->execute([
                ':reason' => $reason,
                ':task_set_id' => $data['task_set_id'],
                ':task_id' => $taskId
            ]);
            logMail("Stored reason for task $taskId: $reason");
        }
    }

    // Update als ingediend
    $stmt = $db->prepare("UPDATE task_sets SET submitted = 1, submitted_at = datetime('now') WHERE id = :id");
    $stmt->execute([':id' => $data['task_set_id']]);

    // Bereken statistieken
    $totalTasks = count($tasks);
    $completedTasks = array_filter($tasks, fn($t) => $t['completed'] == 1);
    $completedCount = count($completedTasks);
    $percentage = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100) : 0;

    // Probeer e-mail te versturen
    logMail("Starting email process for task_set_id: {$data['task_set_id']}");
    $mailResult = sendTaskEmail($db, $taskSet, $tasks, $completedCount, $totalTasks, $percentage);
    logMail("Email process completed. Result: " . json_encode($mailResult));

    echo json_encode([
        'success' => true, 
        'message' => 'Taken succesvol ingediend' . ($mailResult['sent'] ? ' en e-mail verzonden!' : ''),
        'mail_sent' => $mailResult['sent'],
        'mail_error' => $mailResult['error'] ?? null,
        'completion_rate' => $percentage,
        'attachments' => $mailResult['attachments'] ?? 0
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function logMail($message) {
    $logFile = __DIR__ . '/mail.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function sendTaskEmail($db, $taskSet, $tasks, $completedCount, $totalTasks, $percentage) {
    try {
        logMail("=== STARTING EMAIL FUNCTION ===");
        logMail("TaskSet ID: {$taskSet['id']}, Day: {$taskSet['day']}, Manager: {$taskSet['manager']}");
        logMail("Stats: $completedCount/$totalTasks tasks completed ($percentage%)");
        // Laad .env configuratie
        $envFile = __DIR__ . '/../../.env';
        logMail("Looking for .env file at: $envFile");
        if (!file_exists($envFile)) {
            logMail("ERROR: .env file not found!");
            return ['sent' => false, 'error' => '.env bestand niet gevonden'];
        }
        logMail(".env file found, loading configuration...");
        
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];
        foreach ($envLines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }

        // Controleer verplichte velden
        $required = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
        logMail("Checking required mail configuration fields...");
        foreach ($required as $field) {
            if (empty($config[$field])) {
                logMail("ERROR: Missing required field: $field");
                return ['sent' => false, 'error' => "Ontbrekende configuratie: $field"];
            }
        }
        logMail("All required fields present. Host: {$config['MAIL_HOST']}, Username: {$config['MAIL_USERNAME']}, From: {$config['MAIL_FROM_ADDRESS']}");

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

        // Haal ontvangers op uit database (storemanager, regio manager, admin)
        $recipients = getEmailRecipients((int)$taskSet['manager'], (int)$taskSet['store_id']);
        logMail("Found " . count($recipients) . " email recipients");

        $actualRecipients = 0;
        foreach ($recipients as $recipient) {
            if (str_ends_with(strtolower($recipient['email']), 'nyp.nl')) {
                logMail("SKIPPED nyp.nl recipient: {$recipient['name']} ({$recipient['email']}) - Role: {$recipient['role']} (would have been sent in production)");
            } else {
                $mail->addAddress($recipient['email'], $recipient['name']);
                $actualRecipients++;
                logMail("Added recipient: {$recipient['name']} ({$recipient['email']}) - Role: {$recipient['role']}");
            }
        }

        // Als geen ontvangers gevonden, log error
        if (empty($recipients)) {
            logMail("ERROR: No recipients found in database for manager: {$taskSet['manager']}");
            return ['sent' => false, 'error' => 'Geen e-mail ontvangers gevonden'];
        }

        // Als alle ontvangers nyp.nl waren, log dit
        if ($actualRecipients === 0 && !empty($recipients)) {
            logMail("INFO: All recipients were nyp.nl domains - email not sent but would have been sent to " . count($recipients) . " recipients in production");
            return ['sent' => true, 'attachments' => $attachmentCount, 'note' => 'Simulated send to nyp.nl domains'];
        }

        // Haal foto's en incomplete reasons op uit database
        $stmt = $db->prepare("
            SELECT t.name as task_name, tsi.photo_path, tsi.completed, tsi.incomplete_reason
            FROM tasks t
            JOIN task_set_items tsi ON t.id = tsi.task_id
            WHERE tsi.task_set_id = :task_set_id
        ");
        $stmt->execute([':task_set_id' => $taskSet['id']]);
        $taskDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter photos
        $photos = array_filter($taskDetails, fn($t) => !empty($t['photo_path']));

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
        $mail->Subject = "Taken ingediend - {$taskSet['day']} ({$taskSet['manager_name']}) - {$attachmentCount} foto's";
        
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
                    <p>{$taskSet['day']} - {$taskSet['manager_name']}</p>
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

            // Find task details including photo and incomplete reason
            $taskDetail = null;
            foreach ($taskDetails as $detail) {
                if ($detail['task_name'] === $task['name']) {
                    $taskDetail = $detail;
                    break;
                }
            }

            $photoIndicator = (!empty($taskDetail['photo_path'])) ? '<span class="photo-indicator">📷 Foto</span>' : '';

            // Add incomplete reason if task is not completed and reason exists
            $reasonText = '';
            if (!$isCompleted && !empty($taskDetail['incomplete_reason'])) {
                $reasonText = '<div class="task-details" style="color: #dc2626; font-style: italic; margin-top: 4px;">💬 Reden: ' . htmlspecialchars($taskDetail['incomplete_reason']) . '</div>';
            }

            $mail->Body .= "
                        <div class='task-item {$statusClass}'>
                            <div>
                                <div class='task-name'>{$icon} {$task['name']} {$photoIndicator}</div>
                                <div class='task-details'>⏱️ {$task['time']} minuten • 🔄 {$task['frequency']}</div>
                                {$reasonText}
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

function getEmailRecipients($managerUserId, $storeId) {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        logMail("Getting email recipients for managerUserId: $managerUserId, store: $storeId");

        // Sends to:
        // - all admins
        // - the storemanager assigned to the store (users.role='storemanager' AND users.store_id = :store_id)
        // - the regiomanager(s) matching the store’s region(s) via region_stores
        $stmt = $db->prepare("
            SELECT u.username AS name, u.email, u.role
            FROM users u
            WHERE u.role = 'admin'

            UNION

            SELECT u.username AS name, u.email, u.role
            FROM users u
            WHERE u.role = 'storemanager'
              AND u.store_id = :store_id

            UNION

            SELECT u.username AS name, u.email, u.role
            FROM users u
            WHERE u.role = 'regiomanager'
              AND u.region_id IN (
                  SELECT rs.region_id
                  FROM region_stores rs
                  WHERE rs.store_id = :store_id
              )
        ");
        $stmt->execute([
            ':store_id' => $storeId
        ]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logMail('Found recipients: ' . json_encode($recipients));
        return $recipients;

    } catch (Exception $e) {
        logMail('ERROR getting recipients: ' . $e->getMessage());
        return [];
    }
}
?>