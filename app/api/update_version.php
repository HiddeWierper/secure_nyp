<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if user is developer
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'developer') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Your not a developer.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$newVersion = trim($input['version'] ?? '');

// Validate version format
if (!preg_match('/^v?\d+\.\d+(\.\d+)?$/', $newVersion)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid version format. Use format: v1.2.3, 1.2.3, v1.2, or 1.2']);
    exit;
}

// Ensure version starts with 'v'
if (!str_starts_with($newVersion, 'v')) {
    $newVersion = 'v' . $newVersion;
}

// Path to version file
$versionFile = __DIR__ . '/../config/version.json';
$configDir = dirname($versionFile);

// Create config directory if it doesn't exist
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

// Save version to JSON file
$versionData = [
    'version' => $newVersion,
    'updated_at' => date('Y-m-d H:i:s'),
    'updated_by' => $_SESSION['user_name'] ?? $_SESSION['user_id']
];

if (file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT))) {
    // Update session
    $_SESSION['version'] = $newVersion;
    
    echo json_encode([
        'success' => true,
        'version' => $newVersion,
        'message' => 'Version updated successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save version']);
    
		
}
?>