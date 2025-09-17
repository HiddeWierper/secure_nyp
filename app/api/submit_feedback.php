<?php

// Zet error reporting aan voor debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 0); // Niet tonen in output
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/feedback.log');

// // Start output buffering om ongewenste output te vangen
// ob_start();

// // Functie om buffer te loggen als er iets onverwachts wordt uitgeprint
// function logUnexpectedOutput() {
//     $output = ob_get_contents();
//     if (!empty($output)) {
//         $logFile = __DIR__ . '/feedback.log';
//         $timestamp = date('Y-m-d H:i:s');
//         $logMessage = "[{$timestamp}] UNEXPECTED OUTPUT: " . trim($output) . PHP_EOL;
//         file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
//     }
//     ob_end_clean();
// }

// // Registreer shutdown functie om onverwachte output te loggen
// register_shutdown_function('logUnexpectedOutput');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PHPMailer autoload via Composer
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load mail configuration
$mailConfig = require __DIR__ . '/../config/mail.php';

// Database configuration for SQLite
$dbConfig = [
    'type' => 'sqlite',
    'path' => __DIR__ . '/../db/tasks.db'
];
// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Niet geautoriseerd']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Alleen POST requests toegestaan']);
    exit;
}

// Function to log feedback errors
function logFeedbackError($message, $context = []) {
    $logFile = __DIR__ . '/feedback.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] ERROR: {$message}{$contextStr}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Function to log feedback info
function logFeedbackInfo($message, $context = []) {
    $logFile = __DIR__ . '/feedback.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] INFO: {$message}{$contextStr}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Function to get database connection
function getDatabaseConnection($config) {
    try {
        if ($config['type'] === 'sqlite') {
            $dsn = "sqlite:" . $config['path'];
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } else {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        }
        return $pdo;
    } catch (PDOException $e) {
        logFeedbackError('Database connection failed', ['error' => $e->getMessage()]);
        return null;
    }
}

// Function to get developers from database
function getDevelopers($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role = 'developer' AND email IS NOT NULL AND email != ''");
        $stmt->execute();
        $developers = $stmt->fetchAll();
        
        logFeedbackInfo('Retrieved developers from database', ['count' => count($developers)]);
        return $developers;
    } catch (PDOException $e) {
        logFeedbackError('Failed to retrieve developers', ['error' => $e->getMessage()]);
        return [];
    }
}

// PHPMailer based function to send SMTP email with attachments
function sendSMTPEmail($to, $subject, $body, $headers, $config, $attachments = []) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption']; // 'tls' or 'ssl'
        $mail->Port = $config['port'];

        // Afzender
        $mail->setFrom($config['from_address'], $config['from_name']);

        // Ontvanger
        $mail->addAddress($to);

        // Reply-To (optioneel, hier op username met from_address)
        if (!empty($headers)) {
            foreach ($headers as $header) {
                if (stripos($header, 'Reply-To:') === 0) {
                    $replyTo = trim(substr($header, 9));
                    // Parse naam en email uit header
                    if (preg_match('/^(.*)<(.+)>$/', $replyTo, $matches)) {
                        $mail->addReplyTo(trim($matches[2]), trim($matches[1]));
                    } else {
                        $mail->addReplyTo($replyTo);
                    }
                }
            }
        }

        // Voeg attachments toe
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $mail->addAttachment($attachment['path'], $attachment['original_name']);
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        // Prioriteit instellen via X-Priority header
        foreach ($headers as $header) {
            if (stripos($header, 'X-Priority:') === 0) {
                $priority = trim(substr($header, 11));
                $mail->Priority = (int)$priority;
            }
        }

        // Verstuur mail
        $mail->send();
        return true;

    } catch (Exception $e) {
        logFeedbackError('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

try {
    // Validate required fields
    $requiredFields = ['feedback_type', 'version_number', 'subject', 'description'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        logFeedbackError('Missing required fields', ['missing_fields' => $missingFields, 'user_id' => $_SESSION['user_id']]);
        echo json_encode(['success' => false, 'message' => 'Verplichte velden ontbreken: ' . implode(', ', $missingFields)]);
        exit;
    }

    // Get form data
    $feedbackType = trim($_POST['feedback_type']);
    $versionNumber = trim($_POST['version_number']);
    $priority = trim($_POST['priority'] ?? 'medium');
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $stepsReproduce = trim($_POST['steps_reproduce'] ?? '');

    // System info
    $browserInfo = trim($_POST['browser_info'] ?? 'Onbekend');
    $screenInfo = trim($_POST['screen_info'] ?? 'Onbekend');
    $userInfo = trim($_POST['user_info'] ?? 'Onbekend');
    $timestamp = trim($_POST['timestamp'] ?? date('Y-m-d H:i:s'));

    // User session info
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Onbekend';
    $userRole = $_SESSION['user_role'] ?? 'Onbekend';
    $regionId = $_SESSION['region_id'] ?? null;
    $storeId = $_SESSION['store_id'] ?? null;

    // Handle file uploads
    $attachments = [];
    $uploadDir = __DIR__ . '/feedback_uploads/';

    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0750, true)) {
            logFeedbackError('Failed to create upload directory', ['upload_dir' => $uploadDir]);
            echo json_encode(['success' => false, 'message' => 'Kan upload directory niet aanmaken']);
            exit;
        }
        // Create .htaccess to prevent direct access
        $htaccessContent = "Order Deny,Allow\nDeny from all\n";
        file_put_contents($uploadDir . '.htaccess', $htaccessContent);
    }

    if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
        $fileCount = count($_FILES['files']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['files']['name'][$i];
                $fileSize = $_FILES['files']['size'][$i];
                $fileTmpName = $_FILES['files']['tmp_name'][$i];
                $fileType = $_FILES['files']['type'][$i];

                // Validate file size (max 10MB)
                if ($fileSize > 10 * 1024 * 1024) {
                    logFeedbackError('File too large', ['filename' => $fileName, 'size' => $fileSize]);
                    continue;
                }

                // Validate file type
                $allowedTypes = [
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                    'application/pdf', 'text/plain', 'text/log',
                    'application/zip', 'application/x-zip-compressed',
                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];

                if (!in_array($fileType, $allowedTypes)) {
                    logFeedbackError('Invalid file type', ['filename' => $fileName, 'type' => $fileType]);
                    continue;
                }

                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uniqueFileName = date('Y-m-d_H-i-s') . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($fileName, PATHINFO_FILENAME)) . '.' . $fileExtension;
                $uploadPath = $uploadDir . $uniqueFileName;

                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    $attachments[] = [
                        'original_name' => $fileName,
                        'stored_name' => $uniqueFileName,
                        'path' => $uploadPath,
                        'size' => $fileSize,
                        'type' => $fileType
                    ];
                    logFeedbackInfo('File uploaded successfully', ['filename' => $fileName, 'stored_as' => $uniqueFileName]);
                } else {
                    logFeedbackError('Failed to move uploaded file', ['filename' => $fileName, 'upload_path' => $uploadPath]);
                }
            }
        }
    }

    // Prepare email content
    $emailSubject = "[Taakbeheer Feedback] {$feedbackType} - {$subject}";

    // Priority emoji mapping
    $priorityEmojis = [
        'low' => '🟢',
        'medium' => '🟡',
        'high' => '🟠',
        'critical' => '🔴'
    ];

    $priorityEmoji = $priorityEmojis[$priority] ?? '🟡';

    // Type emoji mapping
    $typeEmojis = [
        'bug' => '🐛',
        'feature' => '💡',
        'improvement' => '⚡',
        'question' => '❓',
        'other' => '📝'
    ];

    $typeEmoji = $typeEmojis[$feedbackType] ?? '📝';

    $emailBody = "
<!DOCTYPE html>
<html lang='nl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Taakbeheer Feedback</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .content {
            padding: 30px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }
        .info-card h3 {
            margin: 0 0 10px 0;
            color: #059669;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-card p {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
        }
        .priority-critical { border-left: 4px solid #dc2626; }
        .priority-high { border-left: 4px solid #ea580c; }
        .priority-medium { border-left: 4px solid #d97706; }
        .priority-low { border-left: 4px solid #059669; }
        .description-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .description-section h3 {
            margin: 0 0 15px 0;
            color: #374151;
            font-size: 18px;
        }
        .description-content {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            white-space: pre-wrap;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
        }
        .system-info {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .system-info h3 {
            margin: 0 0 15px 0;
            color: #475569;
            font-size: 16px;
        }
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .system-info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .system-info-item:last-child {
            border-bottom: none;
        }
        .system-info-label {
            font-weight: 600;
            color: #64748b;
        }
        .system-info-value {
            color: #1e293b;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 13px;
        }
        .attachments {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .attachments h3 {
            margin: 0 0 15px 0;
            color: #92400e;
            font-size: 16px;
        }
        .attachment-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #fbbf24;
        }
        .attachment-item:last-child {
            border-bottom: none;
        }
        .attachment-icon {
            margin-right: 10px;
            font-size: 16px;
        }
        .attachment-info {
            flex: 1;
        }
        .attachment-name {
            font-weight: 500;
            color: #92400e;
        }
        .attachment-size {
            font-size: 12px;
            color: #a16207;
        }
        .footer {
            background: #f8fafc;
            border-top: 1px solid #e5e7eb;
            padding: 20px 30px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .footer p {
            margin: 5px 0;
        }
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            .content {
                padding: 20px;
            }
            .header {
                padding: 20px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>{$typeEmoji} Nieuwe Feedback Ontvangen</h1>
            <p>Taakbeheer Systeem - Feedback Portal</p>
        </div>

        <div class='content'>
            <div class='info-grid'>
                <div class='info-card priority-{$priority}'>
                    <h3>Prioriteit</h3>
                    <p>{$priorityEmoji} " . ucfirst($priority) . "</p>
                </div>

                <div class='info-card'>
                    <h3>Type</h3>
                    <p>{$typeEmoji} " . ucfirst($feedbackType) . "</p>
                </div>

                <div class='info-card'>
                    <h3>Versie</h3>
                    <p>{$versionNumber}</p>
                </div>

                <div class='info-card'>
                    <h3>Gebruiker</h3>
                    <p>{$username} ({$userRole})</p>
                </div>
            </div>

            <div class='description-section'>
                <h3>📋 Onderwerp</h3>
                <div class='description-content'>{$subject}</div>
            </div>

            <div class='description-section'>
                <h3>📝 Beschrijving</h3>
                <div class='description-content'>{$description}</div>
            </div>";

    if (!empty($stepsReproduce)) {
        $emailBody .= "
            <div class='description-section'>
                <h3>🔄 Stappen om te Reproduceren</h3>
                <div class='description-content'>{$stepsReproduce}</div>
            </div>";
    }

    if (!empty($attachments)) {
        $emailBody .= "
            <div class='attachments'>
                <h3>📎 Bijlagen (" . count($attachments) . ")</h3>
                <ul class='attachment-list'>";

        foreach ($attachments as $attachment) {
            $sizeKB = round($attachment['size'] / 1024, 1);
            $icon = '📄';
            if (strpos($attachment['type'], 'image/') === 0) $icon = '🖼️';
            elseif (strpos($attachment['type'], 'pdf') !== false) $icon = '📕';
            elseif (strpos($attachment['type'], 'zip') !== false) $icon = '🗜️';

            $emailBody .= "
                <li class='attachment-item'>
                    <span class='attachment-icon'>{$icon}</span>
                    <div class='attachment-info'>
                        <div class='attachment-name'>{$attachment['original_name']}</div>
                        <div class='attachment-size'>{$sizeKB} KB</div>
                    </div>
                </li>";
        }

        $emailBody .= "
                </ul>
            </div>";
    }

    $emailBody .= "
            <div class='system-info'>
                <h3>🖥️ Systeem Informatie</h3>
                <div class='system-info-grid'>
                    <div class='system-info-item'>
                        <span class='system-info-label'>Browser:</span>
                        <span class='system-info-value'>{$browserInfo}</span>
                    </div>
                    <div class='system-info-item'>
                        <span class='system-info-label'>Scherm:</span>
                        <span class='system-info-value'>{$screenInfo}</span>
                    </div>
                    <div class='system-info-item'>
                        <span class='system-info-label'>Tijdstip:</span>
                        <span class='system-info-value'>{$timestamp}</span>
                    </div>
                    <div class='system-info-item'>
                        <span class='system-info-label'>User ID:</span>
                        <span class='system-info-value'>{$userId}</span>
                    </div>";

    if ($regionId) {
        $emailBody .= "
                    <div class='system-info-item'>
                        <span class='system-info-label'>Regio ID:</span>
                        <span class='system-info-value'>{$regionId}</span>
                    </div>";
    }

    if ($storeId) {
        $emailBody .= "
                    <div class='system-info-item'>
                        <span class='system-info-label'>Winkel ID:</span>
                        <span class='system-info-value'>{$storeId}</span>
                    </div>";
    }

    $emailBody .= "
                </div>
            </div>
        </div>

        <div class='footer'>
            <p><strong>Taakbeheer Systeem</strong> - Automatisch gegenereerde feedback email</p>
            <p>Ontvangen op " . date('d-m-Y H:i:s') . "</p>
        </div>
    </div>
</body>
</html>";

    // Get database connection and fetch developers
    $pdo = getDatabaseConnection($dbConfig);
    $developers = [];
    
    if ($pdo) {
        $developers = getDevelopers($pdo);
    }

    // Email configuration from config file
    $fromAddress = $mailConfig['from_address'] ?: 'noreply@yourcompany.com';
    $fromName = $mailConfig['from_name'] ?: 'Taakbeheer Systeem';
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddress . '>',
        'Reply-To: ' . $username . ' <' . $fromAddress . '>',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: ' . ($priority === 'critical' ? '1' : ($priority === 'high' ? '2' : '3'))
    ];

    $emailsSent = 0;
    $emailsFailed = 0;
    $emailResults = [];

    // Send emails to developers from database
    if (!empty($developers)) {
        foreach ($developers as $developer) {
            $to = $developer['email'];
            
            // Send email using PHPMailer if configured, otherwise fallback to mail()
            if (!empty($mailConfig['host']) && !empty($mailConfig['username'])) {
                $mailSent = sendSMTPEmail($to, $emailSubject, $emailBody, $headers, $mailConfig, $attachments);
            } else {
                $mailSent = mail($to, $emailSubject, $emailBody, implode("\r\n", $headers));
            }

            if ($mailSent) {
                $emailsSent++;
                $emailResults[] = "✅ {$developer['username']} ({$to})";
                logFeedbackInfo('Feedback email sent to developer', [
                    'developer_id' => $developer['id'],
                    'developer_email' => $to,
                    'user_id' => $userId
                ]);
            } else {
                $emailsFailed++;
                $emailResults[] = "❌ {$developer['username']} ({$to})";
                logFeedbackError('Failed to send feedback email to developer', [
                    'developer_id' => $developer['id'],
                    'developer_email' => $to,
                    'user_id' => $userId
                ]);
            }
        }
    }

    // Fallback: send to configured address if no developers found or all failed
    if (empty($developers) || $emailsSent === 0) {
        $fallbackEmail = $mailConfig['to_address'] ?: 'hmrwierper@gmail.com';
        
        if (!empty($mailConfig['host']) && !empty($mailConfig['username'])) {
            $mailSent = sendSMTPEmail($fallbackEmail, $emailSubject, $emailBody, $headers, $mailConfig, $attachments);
        } else {
            $mailSent = mail($fallbackEmail, $emailSubject, $emailBody, implode("\r\n", $headers));
        }

        if ($mailSent) {
            $emailsSent++;
            $emailResults[] = "✅ Fallback email ({$fallbackEmail})";
            logFeedbackInfo('Feedback email sent to fallback address', [
                'fallback_email' => $fallbackEmail,
                'user_id' => $userId
            ]);
        } else {
            $emailsFailed++;
            $emailResults[] = "❌ Fallback email ({$fallbackEmail})";
            logFeedbackError('Failed to send feedback email to fallback address', [
                'fallback_email' => $fallbackEmail,
                'user_id' => $userId
            ]);
        }
    }

    // Log overall results
    logFeedbackInfo('Feedback submission completed', [
        'user_id' => $userId,
        'username' => $username,
        'type' => $feedbackType,
        'priority' => $priority,
        'subject' => $subject,
        'attachments_count' => count($attachments),
        'emails_sent' => $emailsSent,
        'emails_failed' => $emailsFailed,
        'developers_count' => count($developers),
        'results' => $emailResults
    ]);

    if ($emailsSent > 0) {
        $message = "Feedback succesvol verstuurd! ";
        if ($emailsSent === 1) {
            $message .= "Het development team heeft je bericht ontvangen en zal er zo snel mogelijk naar kijken.";
        } else {
            $message .= "{$emailsSent} developers hebben je bericht ontvangen en zullen er zo snel mogelijk naar kijken.";
        }
        
        if ($emailsFailed > 0) {
            $message .= " (Let op: {$emailsFailed} email(s) konden niet worden verzonden)";
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'details' => [
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
                'results' => $emailResults
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Er is een fout opgetreden bij het versturen van de feedback. Geen enkele email kon worden verzonden. Probeer het later opnieuw of neem direct contact op met de beheerder.',
            'details' => [
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
                'results' => $emailResults
            ]
        ]);
    }

} catch (Exception $e) {
    logFeedbackError('Exception in feedback submission', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Er is een onverwachte fout opgetreden. Probeer het later opnieuw.'
    ]);
}
?>