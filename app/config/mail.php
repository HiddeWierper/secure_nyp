<?php
// config/mail.php
function loadEnv($file) {
    if (!file_exists($file)) {
        throw new Exception('.env bestand niet gevonden');
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Laad environment variabelen
loadEnv(__DIR__ . '/../../.env');

return [
    'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
    'port' => $_ENV['MAIL_PORT'] ?? 587,
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Taakbeheer',
    'to_address' => $_ENV['MAIL_TO_ADDRESS'] ?? '',
];
?>