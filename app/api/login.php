<?php
// login.php - Quick fix to add region_id to session
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(['error' => 'Email en wachtwoord zijn verplicht']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user with region_id
    $stmt = $db->prepare("SELECT id, email, username, password_hash, role, store_id, region_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['error' => 'Ongeldige inloggegevens']);
        exit;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['store_id'] = $user['store_id'];
    
    if ($user['role'] === 'regiomanager' && !empty($user['region_id'])) {
        $_SESSION['region_id'] = $user['region_id'];
    } else {
        unset($_SESSION['region_id']);
    }
    error_log('Login: user_role=' . $user['role'] . ', region_id=' . ($user['region_id'] ?? 'none'));
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'store_id' => $user['store_id'],
            'region_id' => $user['region_id']
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>