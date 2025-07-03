<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'], $data['password'])) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige inloggegevens']);
    exit;
}

$email = $data['email'];
$password = $data['password'];

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/users.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("SELECT id, password_hash, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Ongeldige e-mail of wachtwoord']);
        exit;
    }

    echo json_encode(['success' => true, 'role' => $user['role'], 'user_id' => $user['id']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Serverfout: ' . $e->getMessage()]);
}
?>