
<?php

if (!session_id()) if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$userRole = $_SESSION['user_role'];
if ($userRole === 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd: managers mogen geen taken genereren.']);
    exit;
}
header('Content-Type: application/json');


// Check of ingelogd en admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['email'], $data['role'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige data']);
    exit;
}

$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
$role = $data['role'];

if (!$email || !in_array($role, ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige e-mail of rol']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check of gebruiker al bestaat
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Gebruiker bestaat al']);
        exit;
    }

    // Genereer token en expiry (24 uur)
    $token = bin2hex(random_bytes(16));
    $expiry = time() + 24*3600;

    // Voeg gebruiker toe zonder wachtwoord, met invite token
    $stmt = $db->prepare("INSERT INTO users (email, role, invite_token, invite_expiry) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $role, $token, $expiry]);

    // Stuur mail met link (pas URL aan naar jouw domein)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $path = "/set-password?token=" . urlencode($token);
    $resetLink = $protocol . $domain . $path;    
    $subject = "Uitnodiging om account aan te maken";
    $message = "Je bent uitgenodigd om een account aan te maken. Klik op de link om je wachtwoord in te stellen (24 uur geldig):\n\n$resetLink";
    $headers = "From: no-reply@jouwsite.nl";

    mail($email, $subject, $message, $headers);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>