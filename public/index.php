<?php
session_start();

// Laad composer autoloader
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

// BASEPATH AANPASSEN naar /secure_nyp/public
define('BASEPATH', '/secure_nyp/public');

// Get request path
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove basepath if present
if (BASEPATH !== '' && strpos($path, BASEPATH) === 0) {
    $path = substr($path, strlen(BASEPATH));
}

// Remove trailing slash
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// Helper function voor URL generatie
function url($path = '') {
    return BASEPATH . $path;
}

// Router
switch ($path) {
    case '/':
        require __DIR__ . '/../app/views/home.php';
        break;
        
    case '/dashboard':
        require __DIR__ . '/../app/views/dashboard.php';
        break;
        
    case '/login':
        require __DIR__ . '/../app/views/login.php';
        break;
        
    case '/logout':
        require __DIR__ . '/../app/views/logout.php';
        break;
        
    case '/forgot-password':
        require __DIR__ . '/../app/views/forgot_password.php';
        break;
        
    case '/reset-password':
        require __DIR__ . '/../app/views/reset_password.php';
        break;
        
    case '/set-password':
        require __DIR__ . '/../app/views/set_password.php';
        break;
        
    default:
        // Check if it's an API route
        if (strpos($path, '/api/') === 0) {
            $apiPath = substr($path, 5);
            $apiFile = __DIR__ . '/../app/api/' . $apiPath;
            if (!file_exists($apiFile)) {
                $apiFile .= '.php';
            }
            if (file_exists($apiFile)) {
                require $apiFile;
            } else {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'API endpoint not found: ' . $apiPath]);
            }
            break;
        }
        
        // Regular 404
        http_response_code(404);
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>404 - Pagina niet gevonden</title>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                h1 { color: #e74c3c; }
                a { color: #3498db; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <h1>404 - Pagina niet gevonden</h1>
            <p>De opgevraagde pagina bestaat niet.</p>
            <a href='" . url('/') . "'>← Terug naar home</a>
        </body>
        </html>";
        break;
}
?>