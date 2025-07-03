<?php
session_start();

// Laad composer autoloader
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

// Get request path
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Remove trailing slash
$path = rtrim($path, '/');
if ($path === '') $path = '/';

// Router
switch ($path) {
    case '/':
        require '../app/views/home.php';
        break;
        
    case '/dashboard':
        require '../app/views/dashboard.php';
        break;
        
    case '/login':
        require '../app/views/login.php';
        break;
        
    case '/logout':
        require '../app/views/logout.php';
        break;
        
    case '/forgot-password':
        require '../app/views/forgot_password.php';
        break;

    case '/reset-password':
        require '../app/views/reset_password.php';
        break;
        
    case '/set-password':
        require '../app/views/set_password.php';
        break;

    // case '/regiomanager-dashboard':
    //     if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'regiomanager') {
    //         header('Location: /nyp/login');
    //         exit;
    //     }
    //     require '../app/views/regiomanager_dashboard.php';
    //     break;
        
    // API routes
// API routes - VERBETERDE VERSIE
// API routes - ACCEPTEERT .php extensie
default:
    // Check if it's an API route
    if (strpos($path, '/api/') === 0) {
        // Remove /api/ from path
        $apiPath = substr($path, 5); // Remove '/api/'
        
        // Try with .php extension first
        $apiFile = '../app/api/' . $apiPath;
        if (!file_exists($apiFile)) {
            // If not found, try adding .php
            $apiFile = '../app/api/' . $apiPath . '.php';
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
    
    // Regular 404 for non-API routes
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
        <a href='/'>← Terug naar home</a>
    </body>
    </html>";
    break;
}
?>