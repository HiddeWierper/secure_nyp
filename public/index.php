<?php
session_start();

// Laad composer autoloader
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

// BASEPATH AANPASSEN naar /secure_nyp/public// Verbeterde BASEPATH detectie
define('BASEPATH',
    ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')
    ? '/secure_nyp/public'
    : ''
);

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

// Load version from config
require_once __DIR__ . '/../app/config/version.php';
$version = getVersion();

// Function to render version badge
function renderVersionBadge($version) {
    return '<div class="fixed bottom-4 left-4 z-50 ">
  <span id="versionBadge"
        class="px-3 py-1 rounded-full font-mono font-bold text-xs tracking-widest transition-all duration-300 ' . ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'developer') ? 'cursor-pointer pointer-events-auto hover:scale-105' : '') . '"
        style="background: rgba(91, 212, 125, 0.13); color: #00faa0; box-shadow: 0 2px 8px 0 rgba(91,212,125,0.07);"
        ' . ((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'developer') ? 'contenteditable="true" title="Click to edit version"' : '') . '>
    ' . htmlspecialchars($version) . '
  </span>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const versionBadge = document.getElementById("versionBadge");
  
  // Only enable version editing for developers
  if (versionBadge && versionBadge.isContentEditable) {
    let originalVersion = versionBadge.textContent.trim();

    versionBadge.addEventListener("focus", function() {
      originalVersion = versionBadge.textContent.trim();
      versionBadge.style.border = "2px solid #00faa0";
    });

    versionBadge.addEventListener("blur", function() {
      const newVersion = versionBadge.textContent.trim();
      versionBadge.style.border = "none";

      // Validate version format
      if (!newVersion.match(/^v?\d+\.\d+(\.\d+)?$/)) {
        alert("Invalid version format. Use format: v1.2.3, 1.2.3, v1.2, or 1.2");
        versionBadge.textContent = originalVersion;
        return;
      }

      // Ensure version starts with \'v\'
      const formattedVersion = newVersion.startsWith("v") ? newVersion : "v" + newVersion;
      if (formattedVersion !== originalVersion) {
        versionBadge.textContent = formattedVersion;
        
        // Save via AJAX
        fetch("' . BASEPATH . '/api/update_version", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ version: formattedVersion }),
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert("Version " + formattedVersion + " saved successfully!");
            originalVersion = formattedVersion;
          } else {
            console.error("Version update failed:", data.error || "Unknown error");
            alert("Failed to save version: " + (data.error || "Unknown error"));
            versionBadge.textContent = originalVersion;
          }
        })
        .catch(error => {
          console.error("Version update error:", error);
          alert("Failed to save version: " + error.message);
          versionBadge.textContent = originalVersion;
        });
      }
    });

    versionBadge.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        versionBadge.blur(); // Trigger the blur event to save
      }
    });
  }
});
</script>';
}

// Router
switch ($path) {
    case '/':
        require __DIR__ . '/../app/views/home.php';
        echo renderVersionBadge($version);
        break;

    case '/dashboard':
        require __DIR__ . '/../app/views/dashboard.php';
        echo renderVersionBadge($version);
        break;

    case '/login':
        require __DIR__ . '/../app/views/login.php';
        echo renderVersionBadge($version);
        break;

    case '/logout':
        require __DIR__ . '/../app/views/logout.php';
        echo renderVersionBadge($version);
        break;

    case '/forgot-password':
        require __DIR__ . '/../app/views/forgot_password.php';
        echo renderVersionBadge($version);
        break;

    case '/reset-password':
        require __DIR__ . '/../app/views/reset_password.php';
        echo renderVersionBadge($version);
        break;

    case '/set-password':
        require __DIR__ . '/../app/views/set_password.php';
        echo renderVersionBadge($version);
        break;

    case '/landing':
        require __DIR__ . '/../app/views/landing.php';
        echo renderVersionBadge($version);
        break;

    default:
        // Check if it's an API route
        if (strpos($path, '/api/') === 0) {
            // Security check: Only allow specific API methods
            $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE'];
            $request_method = $_SERVER['REQUEST_METHOD'];
            
            if (!in_array($request_method, $allowed_methods)) {
                http_response_code(405);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }
            
            $apiPath = substr($path, 5); // Remove '/api/' prefix
            $apiFile = __DIR__ . '/../app/api/' . $apiPath;

            // Check if file exists without .php extension first
            if (file_exists($apiFile)) {
                // Set request method for API files to check
                define('REQUEST_METHOD', $request_method);
                require $apiFile;
            } elseif (file_exists($apiFile . '.php')) {
                // Check if file exists with .php extension
                // Set request method for API files to check
                define('REQUEST_METHOD', $request_method);
                require $apiFile . '.php';
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
        echo renderVersionBadge($version);
        break;
}
?>