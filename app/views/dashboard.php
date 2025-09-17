<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Aangepaste paden voor nieuwe structuur
require_once __DIR__ . '/../../vendor/autoload.php';


// Admin, regiomanager, storemanager en developer toegang
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] === 'manager') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Toegang geweigerd.']);
        exit;
    }
    http_response_code(403);
    echo "Toegang geweigerd.";
    exit;
}

// Check of gebruiker admin, regiomanager, storemanager of developer is
$isAdmin = $_SESSION['user_role'] === 'admin';
$isRegiomanager = $_SESSION['user_role'] === 'regiomanager';
$isStoremanager = $_SESSION['user_role'] === 'storemanager';
$isDeveloper = $_SESSION['user_role'] === 'developer';

// Debug logging voor storemanager en developer
$logFile = __DIR__ . '/dashboard.log';
if ($isStoremanager || $isDeveloper) {
    $debugInfo = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? 'not_set',
        'username' => $_SESSION['username'] ?? 'not_set',
        'user_role' => $_SESSION['user_role'] ?? 'not_set',
        'store_id' => $_SESSION['store_id'] ?? 'not_set',
        'region_id' => $_SESSION['region_id'] ?? 'not_set',
        'session_data' => $_SESSION
    ];
    $rolePrefix = $isDeveloper ? "DEVELOPER" : "STOREMANAGER";
    file_put_contents($logFile, "$rolePrefix DEBUG: " . json_encode($debugInfo, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND | LOCK_EX);
}


// Database pad aangepast
$db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Mailconfig pad aangepast
$mailConfig = require __DIR__ . '/../config/mail.php';

// Rest van je dashboard code...

// AJAX acties afhandelen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean any output buffer before processing
    if (ob_get_level()) {
        ob_clean();
    }

    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'get_users') {
            if ($isAdmin || $isDeveloper) {
                // Admin en Developer zien alle gebruikers behalve zichzelf
                $stmt = $db->prepare("
                    SELECT u.id, u.email, u.username, u.role, u.store_id, u.region_id, s.name AS store_name, r.name AS region_name
                    FROM users u
                    LEFT JOIN stores s ON u.store_id = s.id
                    LEFT JOIN regions r ON u.region_id = r.id
                    WHERE u.id != ?
                    ORDER BY u.id
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $storesStmt = $db->query("SELECT id, name FROM stores ORDER BY name");
                $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);

                // Haal alle regio's op voor admin/developer met regiomanager info
                $regionsStmt = $db->query("
                    SELECT r.id, r.name, u.username AS manager_name, u.id AS manager_id
                    FROM regions r
                    LEFT JOIN users u ON r.id = u.region_id AND u.role = 'regiomanager'
                    ORDER BY r.name
                ");
                $regions = $regionsStmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($isRegiomanager) {
                // Regiomanager ziet alleen gebruikers uit hun regio behalve zichzelf
                $regionId = $_SESSION['region_id'] ?? null;
                if (!$regionId) {
                    throw new Exception('Geen regio toegewezen aan gebruiker.');
                }

                $stmt = $db->prepare("
                    SELECT u.id, u.email, u.username, u.role, u.store_id, s.name AS store_name
                    FROM users u
                    LEFT JOIN stores s ON u.store_id = s.id
                    LEFT JOIN region_stores rs ON s.id = rs.store_id
                    WHERE (rs.region_id = ? OR u.store_id IS NULL) AND u.id != ?
                    ORDER BY u.id
                ");
                $stmt->execute([$regionId, $_SESSION['user_id']]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Alleen winkels uit de regio van de regiomanager
                $storesStmt = $db->prepare("
                    SELECT s.id, s.name
                    FROM stores s
                    INNER JOIN region_stores rs ON s.id = rs.store_id
                    WHERE rs.region_id = ?
                    ORDER BY s.name
                ");
                $storesStmt->execute([$regionId]);
                $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);

                // Regiomanager ziet alleen hun eigen regio
                $regionsStmt = $db->prepare("SELECT id, name FROM regions WHERE id = ? ORDER BY name");
                $regionsStmt->execute([$regionId]);
                $regions = $regionsStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Storemanager ziet alleen gebruikers uit hun eigen winkel behalve zichzelf
                $storeId = $_SESSION['store_id'] ?? null;
                if (!$storeId) {
                    throw new Exception('Geen winkel toegewezen aan gebruiker.');
                }

                $stmt = $db->prepare("
                    SELECT u.id, u.email, u.username, u.role, u.store_id, s.name AS store_name
                    FROM users u
                    LEFT JOIN stores s ON u.store_id = s.id
                    WHERE u.store_id = ? AND u.id != ?
                    ORDER BY u.id
                ");
                $stmt->execute([$storeId, $_SESSION['user_id']]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Alleen hun eigen winkel
                $storesStmt = $db->prepare("SELECT id, name FROM stores WHERE id = ? ORDER BY name");
                $storesStmt->execute([$storeId]);
                $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);

                // Geen regio's voor storemanager
                $regions = [];
            }

            echo json_encode(['success' => true, 'users' => $users, 'stores' => $stores, 'regions' => $regions]);
            exit;
        }

        // Nieuwe actie: regio toevoegen (alleen admin en developer)
        if ($action === 'add_region') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen regio\'s toevoegen.');
            }

            $regionName = trim($_POST['region_name'] ?? '');

            if (!$regionName) {
                throw new Exception('Regionaam is verplicht.');
            }

            // Check of regio al bestaat (case-insensitive)
            $stmt = $db->prepare("SELECT id FROM regions WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$regionName]);
            if ($stmt->fetch()) {
                throw new Exception('Regio bestaat al.');
            }

            // Voeg regio toe
            $stmt = $db->prepare("INSERT INTO regions (name) VALUES (?)");
            $stmt->execute([$regionName]);

            echo json_encode(['success' => true, 'message' => 'Regio toegevoegd.']);
            exit;
        }

        // Nieuwe actie: regiomanager toewijzen aan regio (alleen admin en developer)
        if ($action === 'assign_region_manager') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen regiomanagers toewijzen.');
            }

            $userId = intval($_POST['user_id'] ?? 0);
            $regionId = intval($_POST['region_id'] ?? 0);

            if (!$userId || !$regionId) {
                throw new Exception('Gebruiker en regio zijn verplicht.');
            }

            // Valideer dat gebruiker bestaat en admin of manager is
            $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Gebruiker niet gevonden.');
            }

            if (!in_array($user['role'], ['admin', 'regiomanager', 'storemanager', 'manager', 'developer'])) {
                throw new Exception('Alleen admins, regiomanagers, storemanagers, managers en developers kunnen regiomanager worden.');
            }

            // Valideer dat regio bestaat
            $stmt = $db->prepare("SELECT id FROM regions WHERE id = ?");
            $stmt->execute([$regionId]);
            if (!$stmt->fetch()) {
                throw new Exception('Regio niet gevonden.');
            }

            // Check of er al een regiomanager is voor deze regio
            $stmt = $db->prepare("SELECT id FROM users WHERE region_id = ? AND role = 'regiomanager'");
            $stmt->execute([$regionId]);
            if ($stmt->fetch()) {
                throw new Exception('Deze regio heeft al een regiomanager.');
            }

            // Wijs regio toe en verander rol naar regiomanager
            $stmt = $db->prepare("UPDATE users SET region_id = ?, role = 'regiomanager' WHERE id = ?");
            $stmt->execute([$regionId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Regiomanager toegewezen aan regio.']);
            exit;
        }

        // Nieuwe actie: regiomanager verwijderen van regio (alleen admin en developer)
        if ($action === 'remove_region_manager') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen regiomanagers verwijderen.');
            }

            $userId = intval($_POST['user_id'] ?? 0);

            if (!$userId) {
                throw new Exception('Gebruiker ID is verplicht.');
            }

            // Valideer dat gebruiker regiomanager is
            $stmt = $db->prepare("SELECT role, region_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $user['role'] !== 'regiomanager') {
                throw new Exception('Gebruiker is geen regiomanager.');
            }

            // Verwijder regio toewijzing en verander rol naar admin
            $stmt = $db->prepare("UPDATE users SET region_id = NULL, role = 'admin' WHERE id = ?");
            $stmt->execute([$userId]);

            echo json_encode(['success' => true, 'message' => 'Regiomanager verwijderd van regio.']);
            exit;
        }

        // Nieuwe actie: regio naam wijzigen (alleen admin en developer)
        if ($action === 'update_region') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen regio\'s wijzigen.');
            }

            $regionId = intval($_POST['region_id'] ?? 0);
            $regionName = trim($_POST['region_name'] ?? '');

            if (!$regionId || !$regionName) {
                throw new Exception('Regio ID en naam zijn verplicht.');
            }

            // Check of regio bestaat
            $stmt = $db->prepare("SELECT id FROM regions WHERE id = ?");
            $stmt->execute([$regionId]);
            if (!$stmt->fetch()) {
                throw new Exception('Regio niet gevonden.');
            }

            // Check of nieuwe naam al bestaat (case-insensitive, behalve voor deze regio)
            $stmt = $db->prepare("SELECT id FROM regions WHERE LOWER(name) = LOWER(?) AND id != ?");
            $stmt->execute([$regionName, $regionId]);
            if ($stmt->fetch()) {
                throw new Exception('Regionaam bestaat al.');
            }

            // Update regio naam
            $stmt = $db->prepare("UPDATE regions SET name = ? WHERE id = ?");
            $stmt->execute([$regionName, $regionId]);

            echo json_encode(['success' => true, 'message' => 'Regio naam bijgewerkt.']);
            exit;
        }

        // Nieuwe actie: regio verwijderen (alleen admin en developer)
        if ($action === 'delete_region') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen regio\'s verwijderen.');
            }

            $regionId = intval($_POST['region_id'] ?? 0);

            if (!$regionId) {
                throw new Exception('Regio ID is verplicht.');
            }

            // Check of regio bestaat
            $stmt = $db->prepare("SELECT id FROM regions WHERE id = ?");
            $stmt->execute([$regionId]);
            if (!$stmt->fetch()) {
                throw new Exception('Regio niet gevonden.');
            }

            // Check of er nog winkels gekoppeld zijn aan deze regio
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM region_stores WHERE region_id = ?");
            $stmt->execute([$regionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] > 0) {
                throw new Exception('Kan regio niet verwijderen: er zijn nog winkels gekoppeld aan deze regio.');
            }

            // Check of er nog gebruikers gekoppeld zijn aan deze regio
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE region_id = ?");
            $stmt->execute([$regionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] > 0) {
                throw new Exception('Kan regio niet verwijderen: er zijn nog gebruikers gekoppeld aan deze regio.');
            }

            // Verwijder regio
            $stmt = $db->prepare("DELETE FROM regions WHERE id = ?");
            $stmt->execute([$regionId]);

            echo json_encode(['success' => true, 'message' => 'Regio verwijderd.']);
            exit;
        }

        // Nieuwe actie: winkel toevoegen aan regio (alleen admin en developer)
        if ($action === 'add_store_to_region') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen winkels aan regio\'s toevoegen.');
            }

            $regionId = intval($_POST['region_id'] ?? 0);
            $storeId = intval($_POST['store_id'] ?? 0);

            if (!$regionId || !$storeId) {
                throw new Exception('Regio ID en winkel ID zijn verplicht.');
            }

            // Check of regio bestaat
            $stmt = $db->prepare("SELECT id FROM regions WHERE id = ?");
            $stmt->execute([$regionId]);
            if (!$stmt->fetch()) {
                throw new Exception('Regio niet gevonden.');
            }

            // Check of winkel bestaat
            $stmt = $db->prepare("SELECT id FROM stores WHERE id = ?");
            $stmt->execute([$storeId]);
            if (!$stmt->fetch()) {
                throw new Exception('Winkel niet gevonden.');
            }

            // Check of winkel al gekoppeld is aan een regio
            $stmt = $db->prepare("SELECT region_id FROM region_stores WHERE store_id = ?");
            $stmt->execute([$storeId]);
            if ($stmt->fetch()) {
                throw new Exception('Winkel is al gekoppeld aan een regio.');
            }

            // Koppel winkel aan regio
            $stmt = $db->prepare("INSERT INTO region_stores (region_id, store_id) VALUES (?, ?)");
            $stmt->execute([$regionId, $storeId]);

            echo json_encode(['success' => true, 'message' => 'Winkel toegevoegd aan regio.']);
            exit;
        }

        // Nieuwe actie: winkel verwijderen uit regio (alleen admin en developer)
        if ($action === 'remove_store_from_region') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen winkels uit regio\'s verwijderen.');
            }

            $regionId = intval($_POST['region_id'] ?? 0);
            $storeId = intval($_POST['store_id'] ?? 0);

            if (!$regionId || !$storeId) {
                throw new Exception('Regio ID en winkel ID zijn verplicht.');
            }

            // Check of koppeling bestaat
            $stmt = $db->prepare("SELECT * FROM region_stores WHERE region_id = ? AND store_id = ?");
            $stmt->execute([$regionId, $storeId]);
            if (!$stmt->fetch()) {
                throw new Exception('Winkel is niet gekoppeld aan deze regio.');
            }

            // Check of er nog gebruikers gekoppeld zijn aan deze winkel
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE store_id = ?");
            $stmt->execute([$storeId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result['count'] > 0) {
                throw new Exception('Kan winkel niet uit regio verwijderen: er zijn nog gebruikers gekoppeld aan deze winkel.');
            }

            // Verwijder koppeling
            $stmt = $db->prepare("DELETE FROM region_stores WHERE region_id = ? AND store_id = ?");
            $stmt->execute([$regionId, $storeId]);

            echo json_encode(['success' => true, 'message' => 'Winkel verwijderd uit regio.']);
            exit;
        }

        // Nieuwe actie: beschikbare winkels ophalen (winkels zonder regio)
        if ($action === 'get_available_stores') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen beschikbare winkels bekijken.');
            }

            // Haal winkels op die nog niet gekoppeld zijn aan een regio
            $stmt = $db->prepare("
                SELECT s.id, s.name
                FROM stores s
                LEFT JOIN region_stores rs ON s.id = rs.store_id
                WHERE rs.store_id IS NULL
                ORDER BY s.name
            ");
            $stmt->execute();
            $availableStores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'stores' => $availableStores]);
            exit;
        }

        if ($action === 'update_user') {
            $id = intval($_POST['id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? '';
            $storeId = intval($_POST['store_id'] ?? 0);

            if (!$id || !$email || !$username || !in_array($role, ['admin', 'manager', 'regiomanager', 'storemanager', 'developer'])) {
                throw new Exception('Ongeldige invoer.');
            }

            // Check unieke e-mail en username behalve voor deze gebruiker
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                throw new Exception('E-mail is al in gebruik.');
            }
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                throw new Exception('Gebruikersnaam is al in gebruik.');
            }

            // Validatie store_id (optioneel)
            if ($storeId !== 0) {
                if ($isAdmin || $isDeveloper) {
                    // Admin en Developer kunnen elke winkel selecteren
                    $stmt = $db->prepare("SELECT id FROM stores WHERE id = ?");
                    $stmt->execute([$storeId]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Ongeldige winkel geselecteerd.');
                    }
                } elseif ($isRegiomanager) {
                    // Regiomanager kan alleen winkels uit hun regio selecteren
                    $regionId = $_SESSION['region_id'] ?? null;
                    if (!$regionId) {
                        throw new Exception('Geen regio toegewezen aan gebruiker.');
                    }

                    $stmt = $db->prepare("
                        SELECT s.id
                        FROM stores s
                        INNER JOIN region_stores rs ON s.id = rs.store_id
                        WHERE s.id = ? AND rs.region_id = ?
                    ");
                    $stmt->execute([$storeId, $regionId]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Je kunt alleen winkels uit jouw regio toewijzen.');
                    }
                } else {
                    // Storemanager kan alleen hun eigen winkel selecteren
                    $userStoreId = $_SESSION['store_id'] ?? null;
                    if (!$userStoreId || $storeId !== $userStoreId) {
                        throw new Exception('Je kunt alleen gebruikers toewijzen aan jouw eigen winkel.');
                    }
                }
            } else {
                $storeId = null;
            }

            $stmt = $db->prepare("UPDATE users SET email = ?, username = ?, role = ?, store_id = ? WHERE id = ?");
            $stmt->execute([$email, $username, $role, $storeId, $id]);

            echo json_encode(['success' => true, 'message' => 'Gebruiker bijgewerkt.']);
            exit;
        }

        if ($action === 'add_store') {
            $storeName = trim($_POST['store_name'] ?? '');
            $regionId = intval($_POST['region_id'] ?? 0);
            $isBurgerKitchen = isset($_POST['is_burger_kitchen']) && $_POST['is_burger_kitchen'] === '1' ? 1 : 0;

            if (!$storeName) {
                throw new Exception('Winkelnaam is verplicht.');
            }

            if (!$regionId) {
                throw new Exception('Regio is verplicht.');
            }

            // Check of winkel al bestaat (case-insensitive)
            $stmt = $db->prepare("SELECT id FROM stores WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$storeName]);
            if ($stmt->fetch()) {
                throw new Exception('Winkel bestaat al.');
            }

            // Valideer regio
            if ($isAdmin || $isDeveloper) {
                // Admin en Developer kunnen elke regio selecteren
                $stmt = $db->prepare("SELECT id FROM regions WHERE id = ?");
                $stmt->execute([$regionId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Ongeldige regio geselecteerd.');
                }
            } elseif ($isRegiomanager) {
                // Regiomanager kan alleen hun eigen regio selecteren
                $userRegionId = $_SESSION['region_id'] ?? null;
                if (!$userRegionId || $regionId !== $userRegionId) {
                    throw new Exception('Je kunt alleen winkels toevoegen aan jouw eigen regio.');
                }
            } else {
                // Storemanager kan geen winkels toevoegen
                throw new Exception('Je hebt geen rechten om winkels toe te voegen.');
            }

            // Voeg winkel toe met BurgerKitchen status
            $stmt = $db->prepare("INSERT INTO stores (name, is_bk) VALUES (?, ?)");
            $stmt->execute([$storeName, $isBurgerKitchen]);
            $storeId = $db->lastInsertId();

            // Koppel winkel aan regio
            $stmt = $db->prepare("INSERT INTO region_stores (region_id, store_id) VALUES (?, ?)");
            $stmt->execute([$regionId, $storeId]);

            $storeType = $isBurgerKitchen ? 'BurgerKitchen winkel' : 'Reguliere winkel';
            echo json_encode(['success' => true, 'message' => "$storeType toegevoegd en gekoppeld aan regio."]);
            exit;
        }

        if ($action === 'send_reset') {
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Ongeldig ID.');

            $stmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) throw new Exception('Gebruiker niet gevonden.');

            $token = bin2hex(random_bytes(16));
            $expiry = time() + 3600;

            $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $id]);

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = $mailConfig['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailConfig['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($mailConfig['from_address'], $mailConfig['from_name']);
            $mail->addAddress($user['email']);

            $mail->isHTML(true);
            $mail->Subject = 'Wachtwoord reset verzoek';

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $path = "/set-password?token=" . urlencode($token);
            $resetLink = $protocol . $domain . $path;

            $mail->Body = "
            <html>
            <head>
              <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
                .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto; }
                h2 { color: #333; }
                a.button {
                  background-color: #059669;
                  color: white;
                  padding: 12px 20px;
                  text-decoration: none;
                  border-radius: 5px;
                  display: inline-block;
                  margin-top: 20px;
                }
                a.button:hover {
                  background-color: #047857;
                }
                p { color: #555; }
              </style>
            </head>
            <body>
              <div class='container'>
                <h2>Wachtwoord reset verzoek</h2>
                <p>Hallo " . htmlspecialchars($user['username']) . ",</p>
                <p>We hebben een verzoek ontvangen om je wachtwoord te resetten. Klik op de knop hieronder om een nieuw wachtwoord in te stellen. Deze link is 1 uur geldig.</p>
                <a href='$resetLink' class='button'>Wachtwoord resetten</a>
                <p>Als je dit niet hebt aangevraagd, kun je deze e-mail negeren.</p>
                <p>Met vriendelijke groet,<br>Taakbeheer Team</p>
              </div>
            </body>
            </html>
            ";

            $mail->send();

            echo json_encode(['success' => true, 'message' => 'Reset e-mail verzonden.']);
            exit;
        }

        if ($action === 'delete_user') {
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception('Ongeldig ID.');

            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Gebruiker verwijderd.']);
            exit;
        }

        // Nieuwe actie: gebruiker uitnodigen
        if ($action === 'send_invite') {
            $debugInfo = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => 'send_invite',
                'user_id' => $_SESSION['user_id'] ?? 'not_set',
                'username' => $_SESSION['username'] ?? 'not_set',
                'user_role' => $_SESSION['user_role'] ?? 'not_set',
                'post_data' => $_POST,
                'session_data' => $_SESSION
            ];
            file_put_contents($logFile, "INVITE DEBUG START: " . json_encode($debugInfo, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND | LOCK_EX);

            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? '';
            $storeId = intval($_POST['store_id'] ?? 0);

            $debugInfo['parsed_data'] = [
                'email' => $email,
                'username' => $username,
                'role' => $role,
                'store_id' => $storeId
            ];
            file_put_contents($logFile, "INVITE PARSED DATA: " . json_encode($debugInfo['parsed_data'], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND | LOCK_EX);

            // Validatie
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                file_put_contents($logFile, "INVITE ERROR: Invalid email format - $email\n\n", FILE_APPEND | LOCK_EX);
                throw new Exception('Ongeldig e-mailadres.');
            }
            if (!$username) {
                file_put_contents($logFile, "INVITE ERROR: Username is empty\n\n", FILE_APPEND | LOCK_EX);
                throw new Exception('Gebruikersnaam is verplicht.');
            }
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                file_put_contents($logFile, "INVITE ERROR: Username format invalid - $username\n\n", FILE_APPEND | LOCK_EX);
                throw new Exception('Gebruikersnaam moet 3-20 tekens bevatten, letters, cijfers of underscores.');
            }

            file_put_contents($logFile, "INVITE: Basic validation passed\n\n", FILE_APPEND | LOCK_EX);

            // Rol validatie gebaseerd op gebruikerstype
            if ($isAdmin || $isDeveloper) {
                if (!in_array($role, ['admin', 'manager', 'regiomanager', 'storemanager', 'developer'])) {
                    $userType = $isDeveloper ? 'Developer' : 'Admin';
                    file_put_contents($logFile, "INVITE ERROR: $userType selected invalid role - $role\n\n", FILE_APPEND | LOCK_EX);
                    throw new Exception('Ongeldige rol.');
                }
                $userType = $isDeveloper ? 'Developer' : 'Admin';
                file_put_contents($logFile, "INVITE: $userType role validation passed - $role\n\n", FILE_APPEND | LOCK_EX);
            } elseif ($isRegiomanager) {
                if (!in_array($role, ['manager', 'storemanager'])) {
                    file_put_contents($logFile, "INVITE ERROR: Regiomanager selected invalid role - $role\n\n", FILE_APPEND | LOCK_EX);
                    throw new Exception('Je kunt alleen managers en storemanagers uitnodigen.');
                }
                file_put_contents($logFile, "INVITE: Regiomanager role validation passed - $role\n\n", FILE_APPEND | LOCK_EX);
            } elseif ($isStoremanager) {
                // Storemanager kan alleen managers uitnodigen
                if ($role !== 'manager') {
                    file_put_contents($logFile, "INVITE ERROR: Storemanager selected invalid role - $role\n\n", FILE_APPEND | LOCK_EX);
                    throw new Exception('Je kunt alleen managers uitnodigen.');
                }
                file_put_contents($logFile, "INVITE: Storemanager role validation passed - $role\n\n", FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($logFile, "INVITE ERROR: User has insufficient rights to invite\n\n", FILE_APPEND | LOCK_EX);
                throw new Exception('Onvoldoende rechten om gebruikers uit te nodigen.');
            }

            if ($storeId !== 0) {
                file_put_contents($logFile, "INVITE: Validating store ID - $storeId\n\n", FILE_APPEND | LOCK_EX);

                if ($isAdmin || $isDeveloper) {
                    // Admin en Developer kunnen elke winkel selecteren
                    $stmt = $db->prepare("SELECT id FROM stores WHERE id = ?");
                    $stmt->execute([$storeId]);
                    if (!$stmt->fetch()) {
                        $userType = $isDeveloper ? 'Developer' : 'Admin';
                        file_put_contents($logFile, "INVITE ERROR: $userType selected invalid store - $storeId\n\n", FILE_APPEND | LOCK_EX);
                        throw new Exception('Ongeldige winkel geselecteerd.');
                    }
                    $userType = $isDeveloper ? 'Developer' : 'Admin';
                    file_put_contents($logFile, "INVITE: $userType store validation passed - $storeId\n\n", FILE_APPEND | LOCK_EX);
                } elseif ($isRegiomanager) {
                    // Regiomanager kan alleen winkels uit hun regio selecteren
                    $regionId = $_SESSION['region_id'] ?? null;
                    if (!$regionId) {
                        file_put_contents($logFile, "INVITE ERROR: Regiomanager has no region assigned\n\n", FILE_APPEND | LOCK_EX);
                        throw new Exception('Geen regio toegewezen aan gebruiker.');
                    }

                    file_put_contents($logFile, "INVITE: Regiomanager checking store $storeId in region $regionId\n\n", FILE_APPEND | LOCK_EX);

                    $stmt = $db->prepare("
                        SELECT s.id
                        FROM stores s
                        INNER JOIN region_stores rs ON s.id = rs.store_id
                        WHERE s.id = ? AND rs.region_id = ?
                    ");
                    $stmt->execute([$storeId, $regionId]);
                    if (!$stmt->fetch()) {
                        file_put_contents($logFile, "INVITE ERROR: Regiomanager selected store outside their region - store: $storeId, region: $regionId\n\n", FILE_APPEND | LOCK_EX);
                        throw new Exception('Je kunt alleen gebruikers uitnodigen voor winkels in jouw regio.');
                    }
                    file_put_contents($logFile, "INVITE: Regiomanager store validation passed - store: $storeId, region: $regionId\n\n", FILE_APPEND | LOCK_EX);
                } elseif ($isStoremanager) {
                    // Storemanager kan alleen hun eigen winkel selecteren
                    $userStoreId = $_SESSION['store_id'] ?? null;
                    if (!$userStoreId || $storeId !== $userStoreId) {
                        file_put_contents($logFile, "INVITE ERROR: Storemanager selected wrong store - selected: $storeId, user store: $userStoreId\n\n", FILE_APPEND | LOCK_EX);
                        throw new Exception('Je kunt alleen gebruikers uitnodigen voor jouw eigen winkel.');
                    }
                    file_put_contents($logFile, "INVITE: Storemanager store validation passed - $storeId\n\n", FILE_APPEND | LOCK_EX);
                }
            } else {
                $storeId = null;
                file_put_contents($logFile, "INVITE: No store selected, setting to null\n\n", FILE_APPEND | LOCK_EX);
            }

            // Check of e-mail of gebruikersnaam al bestaat
            file_put_contents($logFile, "INVITE: Checking if email or username already exists\n\n", FILE_APPEND | LOCK_EX);
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                file_put_contents($logFile, "INVITE ERROR: Email or username already exists - email: $email, username: $username\n\n", FILE_APPEND | LOCK_EX);
                throw new Exception('E-mail of gebruikersnaam bestaat al.');
            }
            file_put_contents($logFile, "INVITE: Email and username are unique\n\n", FILE_APPEND | LOCK_EX);

            $token = bin2hex(random_bytes(16));
            $expiry = time() + 24*3600; // 24 uur geldig

            file_put_contents($logFile, "INVITE: Generated token - $token, expiry: " . date('Y-m-d H:i:s', $expiry) . "\n\n", FILE_APPEND | LOCK_EX);

            $stmt = $db->prepare("INSERT INTO users (email, username, role, store_id, invite_token, invite_expiry) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $username, $role, $storeId, $token, $expiry]);

            $newUserId = $db->lastInsertId();
            file_put_contents($logFile, "INVITE: User created in database with ID: $newUserId\n\n", FILE_APPEND | LOCK_EX);

            // Verstuur uitnodigingsmail
            file_put_contents($logFile, "INVITE: Starting email setup\n\n", FILE_APPEND | LOCK_EX);

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = $mailConfig['encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailConfig['port'];
            $mail->CharSet = 'UTF-8';

            file_put_contents($logFile, "INVITE: Email configuration set - Host: " . $mailConfig['host'] . ", Port: " . $mailConfig['port'] . "\n\n", FILE_APPEND | LOCK_EX);

            $mail->setFrom($mailConfig['from_address'], $mailConfig['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Uitnodiging om account aan te maken";
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $path = BASEPATH . "/set-password?token=" . urlencode($token);
            $link = $protocol . $domain . $path;

            file_put_contents($logFile, "INVITE: Generated invite link - $link\n\n", FILE_APPEND | LOCK_EX);
            $mail->Body = "<!DOCTYPE html>
            <html lang='nl'>
            <head>
              <meta charset='UTF-8'>
              <meta name='viewport' content='width=device-width, initial-scale=1.0'>
              <title>Account Uitnodiging</title>
              <style>
                * {
                  margin: 0;
                  padding: 0;
                  box-sizing: border-box;
                }

                body {
                  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                  background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
                  padding: 20px;
                  min-height: 100vh;
                }

                .email-container {
                  max-width: 600px;
                  margin: 0 auto;
                  background: rgba(255, 255, 255, 0.95);
                  border-radius: 20px;
                  overflow: hidden;
                  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                  border: 1px solid rgba(255, 255, 255, 0.2);
                }

                .email-header {
                  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
                  padding: 30px 20px;
                  text-align: center;
                  position: relative;
                }

                .email-header::before {
                  content: '';
                  position: absolute;
                  top: 0;
                  left: 0;
                  right: 0;
                  bottom: 0;
                  background: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"50\" cy=\"50\" r=\"1\" fill=\"%23ffffff\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg>') repeat;
                  opacity: 0.3;
                }

                .logo {
                  width: 60px;
                  height: 60px;
                  background: rgba(255, 255, 255, 0.2);
                  border-radius: 15px;
                  display: inline-flex;
                  align-items: center;
                  justify-content: center;
                  margin-bottom: 15px;
                  position: relative;
                  z-index: 1;
                }

                .logo::before {
                  content: '👤';
                  font-size: 24px;
                }

                .email-title {
                  color: white;
                  font-size: 24px;
                  font-weight: bold;
                  margin-bottom: 8px;
                  position: relative;
                  z-index: 1;
                }

                .email-subtitle {
                  color: rgba(255, 255, 255, 0.9);
                  font-size: 14px;
                  position: relative;
                  z-index: 1;
                }

                .email-content {
                  padding: 40px 30px;
                  line-height: 1.6;
                }

                .greeting {
                  font-size: 18px;
                  color: #374151;
                  margin-bottom: 20px;
                  font-weight: 600;
                }

                .message-text {
                  color: #6b7280;
                  margin-bottom: 30px;
                  font-size: 16px;
                }

                .invite-button {
                  display: inline-block;
                  background: linear-gradient(135deg, #059669 0%, #047857 100%);
                  color: white;
                  padding: 15px 30px;
                  text-decoration: none;
                  border-radius: 12px;
                  font-weight: 600;
                  font-size: 16px;
                  margin: 20px 0;
                  box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.4);
                  transition: all 0.3s ease;
                }

                .invite-button:hover {
                  background: linear-gradient(135deg, #047857 0%, #065f46 100%);
                  transform: translateY(-2px);
                  box-shadow: 0 15px 35px -5px rgba(5, 150, 105, 0.5);
                }

                .security-notice {
                  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                  border: 1px solid #10b981;
                  border-radius: 12px;
                  padding: 20px;
                  margin: 30px 0;
                }

                .security-notice-title {
                  color: #065f46;
                  font-weight: 600;
                  margin-bottom: 8px;
                  display: flex;
                  align-items: center;
                }

                .security-notice-title::before {
                  content: '⏰';
                  margin-right: 8px;
                }

                .security-notice-text {
                  color: #047857;
                  font-size: 14px;
                }

                .footer {
                  background: #f9fafb;
                  padding: 30px;
                  text-align: center;
                  border-top: 1px solid #e5e7eb;
                }

                .footer-text {
                  color: #6b7280;
                  font-size: 14px;
                  margin-bottom: 10px;
                }

                .company-name {
                  color: #059669;
                  font-weight: 600;
                }

                .divider {
                  height: 1px;
                  background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
                  margin: 30px 0;
                }

                @media only screen and (max-width: 600px) {
                  .email-container {
                    margin: 10px;
                    border-radius: 15px;
                  }

                  .email-content {
                    padding: 30px 20px;
                  }

                  .email-title {
                    font-size: 20px;
                  }

                  .invite-button {
                    display: block;
                    text-align: center;
                    padding: 12px 20px;
                  }
                }
              </style>
            </head>
            <body>
              <div class='email-container'>
                <div class='email-header'>
                  <div class='logo'></div>
                  <div class='email-title'>Account Uitnodiging</div>
                  <div class='email-subtitle'>Welkom bij ons platform</div>
                </div>

                <div class='email-content'>
                  <div class='greeting'>Hallo $username! 👋</div>

                  <div class='message-text'>
                    Je bent uitgenodigd om een account aan te maken op ons platform.
                    Klik op de onderstaande knop om je wachtwoord in te stellen en je account te activeren.
                  </div>

                  <div style='text-align: center;'>
                    <a href='$link' class='invite-button'>
                      🔐 Wachtwoord Instellen
                    </a>
                  </div>

                  <div class='security-notice'>
                    <div class='security-notice-title'>Belangrijk om te weten:</div>
                    <div class='security-notice-text'>
                      • Deze link is slechts <strong>24 uur geldig</strong><br>
                      • Als je deze uitnodiging niet verwacht had, kun je deze e-mail veilig negeren<br>
                      • Je account wordt pas geactiveerd nadat je een wachtwoord hebt ingesteld
                    </div>
                  </div>

                  <div class='divider'></div>

                  <div class='message-text' style='font-size: 14px; color: #9ca3af;'>
                    Als de knop niet werkt, kopieer dan deze link naar je browser:<br>
                    <a href='$link' style='color: #059669; word-break: break-all;'>$link</a>
                  </div>
                </div>

                <div class='footer'>
                  <div class='footer-text'>
                    Met vriendelijke groet,<br>
                    <span class='company-name'>Het Team</span>
                  </div>
                  <div class='footer-text' style='font-size: 12px; margin-top: 15px;'>
                    Deze e-mail is automatisch gegenereerd. Reageer niet op deze e-mail.
                  </div>
                </div>
              </div>
            </body>
            </html>";
            try {
                $mail->send();
                file_put_contents($logFile, "INVITE SUCCESS: Email sent successfully to $email\n\n", FILE_APPEND | LOCK_EX);

                $finalDebugInfo = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'success' => true,
                    'new_user_id' => $newUserId,
                    'email' => $email,
                    'username' => $username,
                    'role' => $role,
                    'store_id' => $storeId,
                    'token' => $token,
                    'invite_link' => $link
                ];
                file_put_contents($logFile, "INVITE FINAL SUCCESS: " . json_encode($finalDebugInfo, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND | LOCK_EX);

                // Clean any output buffer before sending JSON
                if (ob_get_level()) {
                    ob_clean();
                }

                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Uitnodiging succesvol verzonden!'], JSON_UNESCAPED_UNICODE);
                exit;
            } catch (Exception $mailException) {
                file_put_contents($logFile, "INVITE EMAIL ERROR: " . $mailException->getMessage() . "\n\n", FILE_APPEND | LOCK_EX);

                // Delete the user record since email failed
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$newUserId]);

                // Clean any output buffer before sending JSON
                if (ob_get_level()) {
                    ob_clean();
                }

                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Fout bij versturen e-mail: ' . $mailException->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        if ($action === 'get_region_stores') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen winkels per regio bekijken.');
            }

            $regionId = intval($_POST['region_id'] ?? 0);

            if (!$regionId) {
                throw new Exception('Regio ID is verplicht.');
            }

            // Check of regio bestaat
            $stmt = $db->prepare("SELECT name FROM regions WHERE id = ?");
            $stmt->execute([$regionId]);
            $region = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$region) {
                throw new Exception('Regio niet gevonden.');
            }

            // Haal winkels op uit de regio met aantal gebruikers en BurgerKitchen status
            $stmt = $db->prepare("
                SELECT s.id, s.name, s.is_bk, COUNT(u.id) as user_count
                FROM stores s
                INNER JOIN region_stores rs ON s.id = rs.store_id
                LEFT JOIN users u ON s.id = u.store_id
                WHERE rs.region_id = ?
                GROUP BY s.id, s.name, s.is_bk
                ORDER BY s.name
            ");
            $stmt->execute([$regionId]);
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Haal ook beschikbare winkels op (winkels zonder regio)
            $stmt = $db->prepare("
                SELECT s.id, s.name
                FROM stores s
                LEFT JOIN region_stores rs ON s.id = rs.store_id
                WHERE rs.store_id IS NULL
                ORDER BY s.name
            ");
            $stmt->execute();
            $availableStores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'stores' => $stores, 'available_stores' => $availableStores]);
            exit;
        }

        // Nieuwe actie: BurgerKitchen status wijzigen
        if ($action === 'toggle_burger_kitchen') {
            if (!$isAdmin && !$isDeveloper) {
                throw new Exception('Alleen admins en developers kunnen BurgerKitchen status wijzigen.');
            }

            $storeId = intval($_POST['store_id'] ?? 0);
            $isBurgerKitchen = intval($_POST['is_bk'] ?? 0);

            if (!$storeId) {
                throw new Exception('Winkel ID is verplicht.');
            }

            // Check of winkel bestaat
            $stmt = $db->prepare("SELECT id, name FROM stores WHERE id = ?");
            $stmt->execute([$storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$store) {
                throw new Exception('Winkel niet gevonden.');
            }

            // Update BurgerKitchen status
            $stmt = $db->prepare("UPDATE stores SET is_bk = ? WHERE id = ?");
            $stmt->execute([$isBurgerKitchen, $storeId]);

            $statusText = $isBurgerKitchen ? 'BurgerKitchen' : 'Reguliere winkel';
            echo json_encode(['success' => true, 'message' => "Winkel '{$store['name']}' is nu een {$statusText}."]);
            exit;
        }

        throw new Exception('Onbekende actie.');
    } catch (Exception $e) {
        // Log the error for debugging
        if ($isStoremanager || $isDeveloper) {
            $errorInfo = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $_POST['action'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            $rolePrefix = $isDeveloper ? "DEVELOPER" : "STOREMANAGER";
            file_put_contents($logFile, "$rolePrefix ERROR: " . json_encode($errorInfo, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND | LOCK_EX);
        }

        // Clean any output buffer before sending JSON
        if (ob_get_level()) {
            ob_clean();
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard - Gebruikersbeheer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="https://nypschoonmaak.nl/assets/logo.webp">  


  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#059669',
            secondary: '#10B981'
          }
        }
      }
    }
  </script>

  <style>
    * {
      transition: all 0.3s ease;
    }

    body {
      background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
      min-height: 100vh;
    }

    .glass-card {
      backdrop-filter: blur(10px);
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn-primary {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #047857 0%, #065f46 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.4);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      transition: all 0.3s ease;
    }

    .btn-secondary:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);
    }

    .nav-btn {
      background: rgba(255, 255, 255, 0.9);
      color: #374151;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .nav-btn:hover {
      background: rgba(255, 255, 255, 1);
      transform: translateY(-1px);
      box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .input-field {
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(5, 150, 105, 0.2);
      transition: all 0.3s ease;
    }

    .input-field:focus {
      background: rgba(255, 255, 255, 1);
      border-color: #059669;
      box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    /* Mobile menu toggle */
    .mobile-menu-hidden {
      transform: translateX(-100%);
    }

    /* Responsive text sizing */
    @media (max-width: 640px) {
      .responsive-text-lg {
        font-size: 1rem;
      }
      .responsive-text-xl {
        font-size: 1.125rem;
      }
      .responsive-text-2xl {
        font-size: 1.25rem;
      }
      .responsive-text-3xl {
        font-size: 1.5rem;
      }
    }

    /* Mobile table improvements */
    @media (max-width: 768px) {
      .mobile-table-card {
        display: block;
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 1rem;
        margin-bottom: 1rem;
        padding: 1rem;
        transition: all 0.3s ease;
      }

      .mobile-table-card:hover {
        background: rgba(255, 255, 255, 1);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
      }

      .mobile-table-card .table-row {
        display: block;
        margin-bottom: 0.5rem;
      }

      .mobile-table-card .table-label {
        font-weight: 600;
        color: #374151;
        display: inline-block;
        width: 100px;
      }
    }
  </style>
</head>

<body class="min-h-screen">
  <div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
    <!-- Header -->
    <div class="glass-card rounded-2xl p-4 sm:p-6 mb-4 sm:mb-8 relative">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div class="mb-4 sm:mb-0">
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800 mb-2 flex items-center responsive-text-3xl">
            <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-green-700 rounded-xl mr-2 sm:mr-3 flex items-center justify-center">
              <i class="fas fa-users-cog text-white text-lg sm:text-xl lg:text-2xl"></i>
            </div>
            <span class="hidden sm:inline"><?php echo $isDeveloper ? 'Developer Dashboard' : ($isAdmin ? 'Admin Dashboard' : ($isRegiomanager ? 'Regio Dashboard' : 'Winkel Dashboard')); ?></span>
            <span class="sm:hidden"><?php echo $isDeveloper ? 'Developer' : ($isAdmin ? 'Admin' : ($isRegiomanager ? 'Regio' : 'Winkel')); ?></span>
          </h1>
          <p class="text-sm sm:text-base text-gray-600 ml-12 sm:ml-13"><?php echo $isDeveloper ? 'Volledige systeem toegang en beheer' : ($isAdmin ? 'Beheer gebruikers en systeem instellingen' : ($isRegiomanager ? 'Beheer gebruikers in jouw regio' : 'Beheer gebruikers in jouw winkel')); ?></p>
        </div>

        <!-- Gebruikersinfo -->
        <div class="flex flex-col items-center cursor-pointer select-none">
          <div class="w-12 h-12 bg-gradient-to-br from-green-600 to-green-700 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-user-shield text-white text-lg"></i>
          </div>
          <span class="text-xs text-gray-700 font-medium"><?php echo htmlspecialchars($_SESSION['username']) ?></span>
          <span class="text-xs text-green-600 font-semibold"><?php echo ucfirst($_SESSION['user_role']); ?></span>
        </div>
      </div>
    </div>

    <!-- Mobile Navigation Toggle -->
    <div class="sm:hidden mb-4">
      <button id="mobile-menu-toggle" class="w-full btn-primary text-white px-4 py-3 rounded-xl font-medium flex items-center justify-center">
        <i class="fas fa-bars mr-2"></i>
        Menu
      </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-nav" class="sm:hidden mobile-menu-hidden fixed inset-0 bg-black bg-opacity-50 z-50 transition-transform duration-300">
      <div class="glass-card w-64 h-full shadow-lg">
        <div class="p-4 border-b border-white/20">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><?php echo $isAdmin ? 'Admin' : ($isRegiomanager ? 'Regio' : 'Winkel'); ?> Menu</h3>
            <button id="mobile-menu-close" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
        </div>

        <div class="p-4 space-y-2">

          <?php if ($isAdmin || $isDeveloper): ?>
          <button onclick="scrollToSection('region-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-map-marked-alt mr-3 text-green-600"></i> Regiobeheer
          </button>
          <?php endif; ?>

          <?php if ($isAdmin || $isRegiomanager || $isDeveloper): ?>
          <button onclick="scrollToSection('store-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-store mr-3 text-green-600"></i> Winkel Toevoegen
          </button>
          <?php endif; ?>

          <button onclick="scrollToSection('invite-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-user-plus mr-3 text-green-600"></i> Gebruiker Uitnodigen
          </button>

          <button onclick="scrollToSection('users-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-users mr-3 text-green-600"></i> Gebruikersbeheer
          </button>
          

          <div class="border-t border-white/20 pt-2 mt-4">
            <a href="<?= url('/') ?>" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-arrow-left mr-3 text-green-600"></i> Terug naar Dashboard
            </a>

            <a href="<?= url('/logout') ?>" class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 text-red-600 flex items-center">
              <i class="fas fa-sign-out-alt mr-3"></i> Uitloggen
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Desktop Navigation -->
    <div class="hidden sm:flex flex-wrap gap-2 lg:gap-4 mb-4 sm:mb-8">
      <a href="<?= url('/') ?>" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors text-sm lg:text-base">
        <i class="fas fa-arrow-left mr-1 lg:mr-2"></i>
        <span class="hidden md:inline">Terug naar Dashboard</span>
        <span class="md:hidden">Terug</span>
      </a>
      <a href="<?= url('/logout') ?>" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium hover:from-red-600 hover:to-red-700 transition-all text-sm lg:text-base transform hover:-translate-y-1">
        <i class="fas fa-sign-out-alt mr-1 lg:mr-2"></i>
        <span class="hidden sm:inline">Uitloggen</span>
      </a>
    </div>

    <!-- Uitnodigen formulier -->
    <div id="invite-section" class="mb-4 sm:mb-6 glass-card p-4 sm:p-6 rounded-2xl">
      <h2 class="text-lg sm:text-xl font-semibold mb-4 responsive-text-xl flex items-center">
        <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg mr-2 flex items-center justify-center">
          <i class="fas fa-user-plus text-white text-sm"></i>
        </div>
        Gebruiker uitnodigen
      </h2>
      <form id="inviteForm" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="inviteEmail" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-envelope mr-2 text-purple-600"></i>E-mail
            </label>
            <input type="email" id="inviteEmail" name="email" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none" />
          </div>
          <div>
            <label for="inviteUsername" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-user mr-2 text-purple-600"></i>Gebruikersnaam
            </label>
            <input type="text" id="inviteUsername" name="username" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+" title="Letters, cijfers en underscores toegestaan" class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="inviteRole" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-user-tag mr-2 text-purple-600"></i>Rol
            </label>
            <select id="inviteRole" name="role" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none">
              <option value="">Selecteer rol</option>
              <?php if ($isAdmin || $isDeveloper): ?>
              <option value="admin">Admin</option>
              <option value="regiomanager">Regiomanager</option>
              <option value="storemanager">Storemanager</option>
              <?php if ($isDeveloper): ?>
              <option value="developer">Developer</option>
              <?php endif; ?>
              <?php elseif ($isRegiomanager): ?>
              <option value="storemanager">Storemanager</option>
              <?php endif; ?>
              <option value="manager">Manager</option>
            </select>
          </div>
          <div>
            <label for="inviteStore" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-store mr-2 text-purple-600"></i>Winkel
            </label>
            <select id="inviteStore" name="store_id" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none">
              <option value="">Selecteer winkel</option>
              <!-- Wordt dynamisch gevuld -->
            </select>
          </div>
        </div>

        <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white px-6 py-2 rounded-xl transition text-sm sm:text-base transform hover:-translate-y-1">
          <i class="fas fa-paper-plane mr-2"></i>Uitnodigen
        </button>
        <p id="inviteMessage" class="mt-2 text-sm"></p>
      </form>
    </div>

    <!-- Main Content -->
    <div id="users-section" class="glass-card rounded-2xl p-4 sm:p-6 mb-4 sm:mb-6">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 sm:mb-6 gap-4">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-users text-white text-sm"></i>
          </div>
          Gebruikersbeheer
        </h2>
        <div class="text-sm text-gray-600 bg-gradient-to-r from-blue-50 to-blue-100 px-3 sm:px-4 py-2 rounded-xl border-l-4 border-blue-400">
          <i class="fas fa-info-circle mr-2 text-blue-600"></i>
          <span class="hidden sm:inline">Klik in de velden om gegevens te bewerken</span>
          <span class="sm:hidden">Klik om te bewerken</span>
        </div>
      </div>

      <!-- Search -->
      <div class="mb-4">
        <div class="relative">
          <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-green-600"></i>
          <input type="text" id="searchInput" placeholder="Zoek gebruikers..."
                class="input-field w-full pl-10 pr-4 py-2 rounded-xl focus:outline-none text-sm sm:text-base" />
        </div>
      </div>

      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="min-w-full glass-card rounded-2xl overflow-hidden">
          <thead class="bg-gradient-to-r from-green-600 to-green-700 text-white">
            <tr>
              <th class="px-3 lg:px-6 py-3 lg:py-4 text-left text-xs lg:text-sm font-semibold uppercase tracking-wider">
                <i class="fas fa-hashtag mr-1 lg:mr-2"></i>ID
              </th>
              <th class="px-3 lg:px-6 py-3 lg:py-4 text-left text-xs lg:text-sm font-semibold uppercase tracking-wider">
                <i class="fas fa-user mr-1 lg:mr-2"></i>Gebruiker
              </th>
              <th class="px-3 lg:px-6 py-3 lg:py-4 text-left text-xs lg:text-sm font-semibold uppercase tracking-wider">
                <i class="fas fa-envelope mr-1 lg:mr-2"></i>E-mail
              </th>
              <th class="px-3 lg:px-6 py-3 lg:py-4 text-left text-xs lg:text-sm font-semibold uppercase tracking-wider">
                <i class="fas fa-user-tag mr-1 lg:mr-2"></i>Rol
              </th>
              <th class="px-3 lg:px-6 py-3 lg:py-4 text-left text-xs lg:text-sm font-semibold uppercase tracking-wider">
                <i class="fas fa-store mr-1 lg:mr-2"></i>Winkel
              </th>
              <th class="px-3 lg:px-6 py-3 lg:py-4 text-center text-xs lg:text-sm font-semibold uppercase tracking-wider">
                <i class="fas fa-cogs mr-1 lg:mr-2"></i>Acties
              </th>
            </tr>
          </thead>
          <tbody id="usersTableBody" class="divide-y divide-gray-200">
            <!-- Wordt dynamisch geladen -->
          </tbody>
        </table>
      </div>

      <!-- Mobile Cards -->
      <div id="mobileUsersContainer" class="md:hidden space-y-4">
        <!-- Wordt dynamisch geladen -->
      </div>

      <!-- Loading State -->
      <div id="loadingState" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
        <p class="mt-4 text-gray-600 text-sm sm:text-base">Gebruikers laden...</p>
      </div>
    </div>

        <!-- Toevoegen Winkel -->
    <?php if ($isAdmin || $isRegiomanager || $isDeveloper): ?>
    <div id="store-section" class="mb-4 sm:mb-6 glass-card p-4 sm:p-6 rounded-2xl">
      <h2 class="text-lg sm:text-xl font-semibold mb-4 responsive-text-xl flex items-center">
        <div class="w-8 h-8 bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg mr-2 flex items-center justify-center">
          <i class="fas fa-store text-white text-sm"></i>
        </div>
        Winkel toevoegen
      </h2>
      <form id="addStoreForm" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="storeNameInput" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-store mr-2 text-orange-600"></i>Winkelnaam
            </label>
            <input type="text" id="storeNameInput" placeholder="Winkelnaam" required
                  class="input-field w-full rounded-xl px-3 py-2 focus:outline-none text-sm sm:text-base" />
          </div>
          <div>
            <label for="storeRegionSelect" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-map-marker-alt mr-2 text-orange-600"></i>Regio
            </label>
            <select id="storeRegionSelect" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none">
              <option value="">Selecteer regio</option>
              <!-- Wordt dynamisch gevuld -->
            </select>
          </div>
        </div>

        <!-- BurgerKitchen Toggle -->
        <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-4 rounded-xl border-l-4 border-purple-400">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <i class="fas fa-hamburger mr-3 text-purple-600 text-lg"></i>
              <div>
                <label for="burgerKitchenToggle" class="font-medium text-gray-700 cursor-pointer">
                  BurgerKitchen winkel
                </label>
                <p class="text-sm text-gray-600">Schakel in als dit een BurgerKitchen locatie is</p>
              </div>
            </div>
            <div class="relative">
              <input type="checkbox" id="burgerKitchenToggle" class="sr-only">
              <div class="toggle-bg w-12 h-6 bg-gray-300 rounded-full shadow-inner cursor-pointer transition-colors duration-300"></div>
              <div class="toggle-dot absolute w-5 h-5 bg-white rounded-full shadow-md top-0.5 left-0.5 transition-transform duration-300"></div>
            </div>
          </div>
        </div>

        <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white px-6 py-2 rounded-xl transition text-sm sm:text-base transform hover:-translate-y-1">
          <i class="fas fa-plus mr-2"></i>Winkel Toevoegen
        </button>
        <p id="storeMessage" class="mt-2 text-sm"></p>
      </form>
    </div>
    <?php endif; ?>

    <!-- Regiobeheer -->
    <?php if ($isAdmin || $isDeveloper): ?>
    <div id="region-section" class="mb-4 sm:mb-8 glass-card p-6 sm:p-8 rounded-2xl">
      <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold mb-6 responsive-text-3xl flex items-center">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl mr-3 flex items-center justify-center">
          <i class="fas fa-map-marked-alt text-white text-lg"></i>
        </div>
        Regiobeheer
      </h2>

      <!-- Regio toevoegen -->
      <div class="mb-8 bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-2xl border-l-4 border-blue-400">
        <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
          <i class="fas fa-plus-circle mr-3 text-blue-600"></i>Nieuwe regio toevoegen
        </h3>
        <form id="addRegionForm" class="flex flex-col sm:flex-row gap-4">
          <div class="flex-1">
            <input type="text" id="regionNameInput" placeholder="Regionaam" required
                  class="input-field w-full rounded-xl px-4 py-3 focus:outline-none text-base" />
          </div>
          <button type="submit" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-8 py-3 rounded-xl transition text-base font-medium transform hover:-translate-y-1 shadow-lg">
            <i class="fas fa-plus mr-2"></i>Regio Toevoegen
          </button>
        </form>
        <p id="regionMessage" class="mt-3 text-sm"></p>
      </div>

      <!-- Bestaande regio's en regiomanagers -->
      <div>
        <h3 class="text-lg font-semibold mb-6 text-gray-800 flex items-center">
          <i class="fas fa-list-ul mr-3 text-blue-600"></i>Bestaande regio's beheren
        </h3>

        <!-- Desktop tabel -->
        <div class="hidden lg:block overflow-x-auto shadow-xl rounded-2xl">
          <table class="min-w-full glass-card overflow-hidden">
            <thead class="bg-gradient-to-r from-blue-600 to-blue-700 text-white">
              <tr>
                <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">
                  <i class="fas fa-map-marker-alt mr-2"></i>Regio
                </th>
                <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">
                  <i class="fas fa-user-tie mr-2"></i>Regiomanager
                </th>
                <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">
                  <i class="fas fa-cogs mr-2"></i>Acties
                </th>
              </tr>
            </thead>
            <tbody id="regionsTableBody" class="divide-y divide-gray-200">
              <!-- Wordt dynamisch geladen -->
            </tbody>
          </table>
        </div>

        <!-- Mobile/Tablet cards -->
        <div id="mobileRegionsContainer" class="lg:hidden space-y-4">
          <!-- Wordt dynamisch geladen -->
        </div>

        <!-- Regiomanager toewijzen modal -->
        <div id="assignManagerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
          <div class="glass-card rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
              <i class="fas fa-user-plus mr-2 text-blue-600"></i>Regiomanager toewijzen
            </h3>
            <form id="assignManagerForm">
              <input type="hidden" id="assignRegionId" />
              <div class="mb-4">
                <label for="managerSelect" class="block font-medium mb-2 text-sm text-gray-700">
                  Selecteer gebruiker:
                </label>
                <select id="managerSelect" required class="input-field w-full rounded-xl px-3 py-2 text-sm focus:outline-none">
                  <option value="">Selecteer gebruiker</option>
                  <!-- Wordt dynamisch gevuld -->
                </select>
              </div>
              <div class="flex gap-3">
                <button type="submit" class="flex-1 btn-primary text-white px-4 py-2 rounded-xl transition text-sm">
                  <i class="fas fa-check mr-2"></i>Toewijzen
                </button>
                <button type="button" onclick="closeAssignModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-xl transition text-sm">
                  <i class="fas fa-times mr-2"></i>Annuleren
                </button>
              </div>
            </form>
            <p id="assignMessage" class="mt-2 text-sm"></p>
          </div>
        </div>

        <!-- Regio bewerken modal -->
        <div id="editRegionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
          <div class="glass-card rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <h3 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
              <i class="fas fa-edit mr-2 text-blue-600"></i>Regio naam wijzigen
            </h3>
            <form id="editRegionForm">
              <input type="hidden" id="editRegionId" />
              <div class="mb-4">
                <label for="editRegionName" class="block font-medium mb-2 text-sm text-gray-700">
                  Nieuwe regionaam:
                </label>
                <input type="text" id="editRegionName" required class="input-field w-full rounded-xl px-3 py-2 text-sm focus:outline-none" />
              </div>
              <div class="flex gap-3">
                <button type="submit" class="flex-1 btn-primary text-white px-4 py-2 rounded-xl transition text-sm">
                  <i class="fas fa-save mr-2"></i>Opslaan
                </button>
                <button type="button" onclick="closeEditRegionModal()" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-xl transition text-sm">
                  <i class="fas fa-times mr-2"></i>Annuleren
                </button>
              </div>
            </form>
            <p id="editRegionMessage" class="mt-2 text-sm"></p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Success/Error Toast -->
  <div id="toast" class="fixed top-4 right-4 px-4 sm:px-6 py-3 rounded-xl shadow-lg transform translate-x-full transition-transform duration-300 z-50 max-w-sm">
    <div class="flex items-center">
      <i id="toast-icon" class="mr-2 flex-shrink-0"></i>
      <span id="toast-message" class="text-sm sm:text-base"></span>
    </div>
  </div>

  <script>
    // Mobile menu functionality
    function toggleMobileMenu() {
      const mobileNav = document.getElementById('mobile-nav');
      mobileNav.classList.toggle('mobile-menu-hidden');
    }

    function closeMobileMenu() {
      const mobileNav = document.getElementById('mobile-nav');
      mobileNav.classList.add('mobile-menu-hidden');
    }

    function scrollToSection(sectionId) {
      document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
    }

    // Event listeners for mobile menu
    document.getElementById('mobile-menu-toggle').addEventListener('click', toggleMobileMenu);
    document.getElementById('mobile-menu-close').addEventListener('click', closeMobileMenu);

    // Close mobile menu when clicking outside
    document.getElementById('mobile-nav').addEventListener('click', function(e) {
      if (e.target === this) {
        closeMobileMenu();
      }
    });

    // Toast notification function
    function showToast(message, type = 'success') {
      const toast = document.getElementById('toast');
      const icon = document.getElementById('toast-icon');
      const messageEl = document.getElementById('toast-message');

      messageEl.textContent = message;

      if (type === 'success') {
        toast.className = 'fixed top-4 right-4 px-4 sm:px-6 py-3 rounded-xl shadow-lg bg-green-500 text-white transform transition-transform duration-300 z-50 max-w-sm';
        icon.className = 'fas fa-check-circle mr-2 flex-shrink-0';
      } else {
        toast.className = 'fixed top-4 right-4 px-4 sm:px-6 py-3 rounded-xl shadow-lg bg-red-500 text-white transform transition-transform duration-300 z-50 max-w-sm';
        icon.className = 'fas fa-exclamation-circle mr-2 flex-shrink-0';
      }

      toast.style.transform = 'translateX(0)';

      setTimeout(() => {
        toast.style.transform = 'translateX(calc(100% + 2rem))';
      }, 3000);
    }

    let stores = [];
    let regions = [];
    let availableManagers = [];

    function createMobileUserCard(user) {
      const roleIcon = user.role === 'admin' ? 'fas fa-user-shield' : 'fas fa-user-tie';
      const storeName = user.store_name || 'Geen winkel';

      // Winkel opties voor dropdown
      const storeOptions = stores.map(store => {
        const selected = store.id === user.store_id ? 'selected' : '';
        return `<option value="${store.id}" ${selected}>${store.name}</option>`;
      }).join('');

      return `
        <div class="mobile-table-card">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="font-semibold text-gray-800 flex items-center">
                <i class="${roleIcon} mr-2 text-green-600"></i>
                ${user.username}
              </h3>
              <p class="text-sm text-gray-600">ID: ${user.id}</p>
            </div>
            <div class="flex space-x-2">
              <button onclick="sendReset(${user.id})"
                      class="bg-gradient-to-r from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                      title="Reset mail">
                <i class="fas fa-envelope text-sm"></i>
              </button>
              <button onclick="deleteUser(${user.id})"
                      class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                      title="Verwijderen">
                <i class="fas fa-trash text-sm"></i>
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <div>
              <label class="table-label text-sm text-gray-700">E-mail:</label>
              <input type="email" value="${user.email}" data-id="${user.id}" data-field="email"
                     class="input-field w-full mt-1 rounded-xl px-3 py-2 text-sm focus:outline-none" />
            </div>

            <div>
              <label class="table-label text-sm text-gray-700">Gebruiker:</label>
              <input type="text" value="${user.username}" data-id="${user.id}" data-field="username"
                     class="input-field w-full mt-1 rounded-xl px-3 py-2 text-sm focus:outline-none" />
            </div>

            <div>
              <label class="table-label text-sm text-gray-700">Rol:</label>
              <select data-id="${user.id}" data-field="role"
                      class="input-field w-full mt-1 rounded-xl px-3 py-2 text-sm focus:outline-none">
                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                <option value="manager" ${user.role === 'manager' ? 'selected' : ''}>Manager</option>
              </select>
            </div>

            <div>
              <label class="table-label text-sm text-gray-700">Winkel:</label>
              <select data-id="${user.id}" data-field="store_id"
                      class="input-field w-full mt-1 rounded-xl px-3 py-2 text-sm focus:outline-none">
                <option value="">Geen winkel</option>
                ${storeOptions}
              </select>
            </div>
          </div>
        </div>
      `;
    }

    async function fetchUsers() {
      document.getElementById('loadingState').style.display = 'block';

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({action: 'get_users'})
        });
        const data = await res.json();

        if (!data.success) {
          showToast('Fout bij laden gebruikers: ' + data.error, 'error');
          return;
        }

        stores = data.stores || [];
        regions = data.regions || [];

        // Sorteer gebruikers op winkelnaam
        data.users.sort((a, b) => {
          const storeA = a.store_name ? a.store_name.toLowerCase() : 'zzzzzz';
          const storeB = b.store_name ? b.store_name.toLowerCase() : 'zzzzzz';
          if (storeA < storeB) return -1;
          if (storeA > storeB) return 1;
          return 0;
        });

        // Vul winkel dropdown in uitnodigen formulier
        const inviteStoreSelect = document.getElementById('inviteStore');
        inviteStoreSelect.innerHTML = '<option value="">Selecteer winkel</option>';
        stores.forEach(store => {
          inviteStoreSelect.innerHTML += `<option value="${store.id}">${store.name}</option>`;
        });

        // Vul regio dropdown in winkel toevoegen formulier
        const storeRegionSelect = document.getElementById('storeRegionSelect');
        if (storeRegionSelect) {
          storeRegionSelect.innerHTML = '<option value="">Selecteer regio</option>';
          regions.forEach(region => {
            storeRegionSelect.innerHTML += `<option value="${region.id}">${region.name}</option>`;
          });
        }

        // Desktop table
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = '';

        // Mobile container
        const mobileContainer = document.getElementById('mobileUsersContainer');
        mobileContainer.innerHTML = '';

        // Variabele om huidige winkel te tracken voor scheiding
        let currentStore = null;

        data.users.forEach((user) => {
          const storeName = user.store_name ? user.store_name : 'Geen winkel';

          // Desktop table row
          if (storeName !== currentStore) {
            currentStore = storeName;
            const trSeparator = document.createElement('tr');
            trSeparator.className = 'bg-gradient-to-r from-gray-50 to-gray-100';
            trSeparator.innerHTML = `
              <td colspan="6" class="px-3 lg:px-6 py-2 font-semibold text-gray-700 border-b border-gray-300 text-sm lg:text-base">
                <i class="fas fa-store mr-2 text-green-600"></i>Winkel: ${currentStore}
              </td>
            `;
            tbody.appendChild(trSeparator);
          }

          const roleIcon = user.role === 'admin' ? 'fas fa-user-shield' : 'fas fa-user-tie';

          // Winkel opties voor dropdown in tabel
          const storeOptions = stores.map(store => {
            const selected = store.id === user.store_id ? 'selected' : '';
            return `<option value="${store.id}" ${selected}>${store.name}</option>`;
          }).join('');

          const tr = document.createElement('tr');
          tr.className = 'hover:bg-green-50 transition-colors duration-200';

          tr.innerHTML = `
            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
              ${user.id}
            </td>
            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <i class="fas fa-user text-green-600 mr-2 lg:mr-3"></i>
                <input type="text" value="${user.username}" data-id="${user.id}" data-field="username"
                       class="w-full border-0 bg-transparent hover:bg-green-50 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent rounded-xl px-2 py-1 transition-all duration-200 text-sm lg:text-base" />
              </div>
            </td>
            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <i class="fas fa-envelope text-green-600 mr-2 lg:mr-3"></i>
                <input type="email" value="${user.email}" data-id="${user.id}" data-field="email"
                       class="w-full border-0 bg-transparent hover:bg-green-50 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent rounded-xl px-2 py-1 transition-all duration-200 text-sm lg:text-base" />
              </div>
            </td>
            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <i class="${roleIcon} text-green-600 mr-2 lg:mr-3"></i>
                <select data-id="${user.id}" data-field="role"
                        class="border-0 bg-transparent hover:bg-green-50 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent rounded-xl px-2 py-1 transition-all duration-200 text-sm lg:text-base">
                  <?php if ($isAdmin || $isDeveloper): ?>
                  <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                  <option value="regiomanager" ${user.role === 'regiomanager' ? 'selected' : ''}>Regiomanager</option>
                  <option value="storemanager" ${user.role === 'storemanager' ? 'selected' : ''}>Storemanager</option>
                  <?php if ($isDeveloper): ?>
                  <option value="developer" ${user.role === 'developer' ? 'selected' : ''}>Developer</option>
                  <?php endif; ?>
                  <?php elseif ($isRegiomanager): ?>
                  <option value="storemanager" ${user.role === 'storemanager' ? 'selected' : ''}>Storemanager</option>
                  <?php endif; ?>
                  <option value="manager" ${user.role === 'manager' ? 'selected' : ''}>Manager</option>
                </select>
              </div>
            </td>
            <td class="px-3 lg:px-6 py-4 whitespace-nowrap">
              <select data-id="${user.id}" data-field="store_id"
                      class="border-0 bg-transparent hover:bg-green-50 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent rounded-xl px-2 py-1 transition-all duration-200 text-sm lg:text-base">
                <option value="">Geen winkel</option>
                ${storeOptions}
              </select>
            </td>
            <td class="px-3 lg:px-6 py-4 whitespace-nowrap text-center">
              <div class="flex justify-center space-x-1 lg:space-x-2">
                <button onclick="sendReset(${user.id})"
                        class="bg-gradient-to-r from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 text-white p-1 lg:p-2 rounded-xl transition-all duration-200 group transform hover:-translate-y-1"
                        title="Wachtwoord reset mail versturen">
                  <i class="fas fa-envelope text-xs lg:text-sm group-hover:scale-110 transition-transform duration-200"></i>
                </button>
                <button onclick="deleteUser(${user.id})"
                        class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-1 lg:p-2 rounded-xl transition-all duration-200 group transform hover:-translate-y-1"
                        title="Gebruiker verwijderen">
                  <i class="fas fa-trash text-xs lg:text-sm group-hover:scale-110 transition-transform duration-200"></i>
                </button>
              </div>
            </td>
          `;
          tbody.appendChild(tr);

          // Mobile card
          mobileContainer.innerHTML += createMobileUserCard(user);
        });

        // Voeg eventlisteners toe voor inputs/selects
        document.querySelectorAll('input[data-field], select[data-field]').forEach(el => {
          el.addEventListener('change', e => {
            const id = e.target.getAttribute('data-id');
            const field = e.target.getAttribute('data-field');
            const value = e.target.value;
            updateUser(id, field, value);
          });
        });

        document.getElementById('loadingState').style.display = 'none';
      } catch (error) {
        showToast('Fout bij laden gebruikers', 'error');
        document.getElementById('loadingState').style.display = 'none';
      }
    }

    function filterUsers() {
      const filter = document.getElementById('searchInput').value.toLowerCase();

      // Filter desktop table
      const rows = document.querySelectorAll('#usersTableBody tr');
      rows.forEach(row => {
        // Rijen met winkelnaam scheiding hebben colspan=6 en andere styling, die tonen we altijd
        if (row.querySelector('td[colspan="6"]')) {
          row.style.display = '';
          return;
        }

        // Zoek in username, email, role en winkelnaam (in inputs/selects)
        const username = row.querySelector('input[data-field="username"]')?.value.toLowerCase() || '';
        const email = row.querySelector('input[data-field="email"]')?.value.toLowerCase() || '';
        const role = row.querySelector('select[data-field="role"]')?.value.toLowerCase() || '';
        const storeSelect = row.querySelector('select[data-field="store_id"]');
        const storeName = storeSelect?.options[storeSelect.selectedIndex]?.text.toLowerCase() || '';

        if (username.includes(filter) || email.includes(filter) || role.includes(filter) || storeName.includes(filter)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });

      // Filter mobile cards
      const mobileCards = document.querySelectorAll('.mobile-table-card');
      mobileCards.forEach(card => {
        const username = card.querySelector('input[data-field="username"]')?.value.toLowerCase() || '';
        const email = card.querySelector('input[data-field="email"]')?.value.toLowerCase() || '';
        const role = card.querySelector('select[data-field="role"]')?.value.toLowerCase() || '';
        const storeSelect = card.querySelector('select[data-field="store_id"]');
        const storeName = storeSelect?.options[storeSelect.selectedIndex]?.text.toLowerCase() || '';

        if (username.includes(filter) || email.includes(filter) || role.includes(filter) || storeName.includes(filter)) {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    }

    async function updateUser(id, field, value) {
      // Haal huidige waarden uit de rij (desktop of mobile)
      let email, username, role, storeId;

      // Probeer eerst desktop table
      let row = document.querySelector(`input[data-id="${id}"][data-field="username"]`)?.closest('tr');
      if (row) {
        email = row.querySelector('input[data-field="email"]').value;
        username = row.querySelector('input[data-field="username"]').value;
        role = row.querySelector('select[data-field="role"]').value;
        storeId = row.querySelector('select[data-field="store_id"]').value;
      } else {
        // Probeer mobile card
        const mobileCard = document.querySelector(`.mobile-table-card input[data-id="${id}"][data-field="username"]`)?.closest('.mobile-table-card');
        if (mobileCard) {
          email = mobileCard.querySelector('input[data-field="email"]').value;
          username = mobileCard.querySelector('input[data-field="username"]').value;
          role = mobileCard.querySelector('select[data-field="role"]').value;
          storeId = mobileCard.querySelector('select[data-field="store_id"]').value;
        }
      }

      if (!email || !username) return;

      const formData = new URLSearchParams();
      formData.append('action', 'update_user');
      formData.append('id', id);
      formData.append('email', email);
      formData.append('username', username);
      formData.append('role', role);
      formData.append('store_id', storeId);

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          showToast('Gebruiker bijgewerkt');
        } else {
          showToast('Fout bij bijwerken: ' + data.error, 'error');
          fetchUsers(); // refresh om oude waarden terug te zetten
        }
      } catch (error) {
        showToast('Fout bij bijwerken gebruiker', 'error');
        fetchUsers();
      }
    }

    async function sendReset(id) {
      if (!confirm('Weet je zeker dat je een wachtwoord reset mail wilt versturen?')) return;

      const formData = new URLSearchParams();
      formData.append('action', 'send_reset');
      formData.append('id', id);

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message);
        } else {
          showToast('Fout: ' + data.error, 'error');
        }
      } catch (error) {
        showToast('Fout bij versturen reset mail', 'error');
      }
    }

    async function deleteUser(id) {
      if (!confirm('Weet je zeker dat je deze gebruiker definitief wilt verwijderen?')) return;

      const formData = new URLSearchParams();
      formData.append('action', 'delete_user');
      formData.append('id', id);

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message);
          fetchUsers();
        } else {
          showToast('Fout: ' + data.error, 'error');
        }
      } catch (error) {
        showToast('Fout bij verwijderen gebruiker', 'error');
      }
    }

    // Uitnodigen formulier submit
    const inviteForm = document.getElementById('inviteForm');
    const inviteMessage = document.getElementById('inviteMessage');

    inviteForm.addEventListener('submit', async e => {
      e.preventDefault();
      inviteMessage.textContent = '';
      inviteMessage.className = '';

      const email = inviteForm.email.value.trim();
      const username = inviteForm.username.value.trim();
      const role = inviteForm.role.value;
      const storeId = inviteForm.store_id.value;

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'send_invite',
            email,
            username,
            role,
            store_id: storeId
          })
        });
        const data = await res.json();

        if (data.success) {
          inviteMessage.textContent = data.message;
          inviteMessage.className = 'text-green-600';
          inviteForm.reset();
          fetchUsers();
        } else {
          inviteMessage.textContent = data.error || 'Fout bij uitnodigen.';
          inviteMessage.className = 'text-red-600';
        }
      } catch (err) {
        inviteMessage.textContent = 'Fout bij verzenden: ' + err.message;
        inviteMessage.className = 'text-red-600';
      }
    });

    // Toggle functionality for BurgerKitchen
    const burgerKitchenToggle = document.getElementById('burgerKitchenToggle');
    if (burgerKitchenToggle) {
      const toggleBg = burgerKitchenToggle.nextElementSibling;
      const toggleDot = toggleBg.nextElementSibling;

      // Click handler for the toggle
    function handleToggleClick() {
      burgerKitchenToggle.checked = !burgerKitchenToggle.checked;
      updateToggleVisual();
    }

    function updateToggleVisual() {
      if (burgerKitchenToggle.checked) {
        toggleBg.style.backgroundColor = '#059669'; // groen
        toggleDot.style.transform = 'translateX(1.5rem)'; // rechts
      } else {
        toggleBg.style.backgroundColor = '#d1d5db'; // grijs
        toggleDot.style.transform = 'translateX(0)'; // links
      }
    }

      // Add click listeners
      toggleBg.addEventListener('click', handleToggleClick);
      toggleDot.addEventListener('click', handleToggleClick);
      burgerKitchenToggle.addEventListener('change', updateToggleVisual);

      // Initialize visual state
      updateToggleVisual();
    }

    // Store toevoegen formulier
    const addStoreForm = document.getElementById('addStoreForm');
    const storeMessage = document.getElementById('storeMessage');

    if (addStoreForm) {
      addStoreForm.addEventListener('submit', async e => {
        e.preventDefault();
        storeMessage.textContent = '';
        storeMessage.className = '';

        const storeName = document.getElementById('storeNameInput').value.trim();
        const regionId = document.getElementById('storeRegionSelect').value;
        const isBurgerKitchen = document.getElementById('burgerKitchenToggle').checked ? '1' : '0'; 
        if (!storeName) {
          storeMessage.textContent = 'Vul een winkelnaam in.';
          storeMessage.className = 'text-red-600';
          return;
        }

        if (!regionId) {
          storeMessage.textContent = 'Selecteer een regio.';
          storeMessage.className = 'text-red-600';
          return;
        }

        try {
          console.log("Checked:", burgerKitchenToggle.checked);
          console.log("is_burger_kitchen value:", isBurgerKitchen);
          const res = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              action: 'add_store',
              store_name: storeName,
              region_id: regionId,
              is_burger_kitchen: isBurgerKitchen
            })
          });

          // Check if response is ok
          if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
          }

          // Get response text first to check if it's valid JSON
          const responseText = await res.text();

          let data;
          try {
            data = JSON.parse(responseText);
          } catch (parseError) {
            console.error('Invalid JSON response:', responseText);
            throw new Error('Server returned invalid response. Please check server logs.');
          }

          if (data.success) {
            storeMessage.textContent = data.message;
            storeMessage.className = 'text-green-600';
            addStoreForm.reset();
            // Reset toggle visual state
            const burgerKitchenToggle = document.getElementById('burgerKitchenToggle');
            if (burgerKitchenToggle) {
              burgerKitchenToggle.checked = false;
              const toggleBg = burgerKitchenToggle.nextElementSibling;
              const toggleDot = toggleBg.nextElementSibling;
              if (toggleBg) toggleBg.style.backgroundColor = '#d1d5db';
              if (toggleDot) toggleDot.style.transform = 'translateX(0)';
            }
            fetchUsers(); // herlaad winkels en gebruikers
          } else {
            console.error('Add store error:', data.error);
            storeMessage.textContent = data.error || 'Fout bij toevoegen winkel.';
            storeMessage.className = 'text-red-600';
          }
        } catch (err) {
          console.error('Add store fetch error:', err);
          storeMessage.textContent = 'Fout bij verzenden: ' + err.message;
          storeMessage.className = 'text-red-600';
        }
      });
    }

    // Regio toevoegen formulier
    const addRegionForm = document.getElementById('addRegionForm');
    const regionMessage = document.getElementById('regionMessage');

    if (addRegionForm) {
      addRegionForm.addEventListener('submit', async e => {
        e.preventDefault();
        regionMessage.textContent = '';
        regionMessage.className = '';

        const regionName = document.getElementById('regionNameInput').value.trim();

        if (!regionName) {
          regionMessage.textContent = 'Vul een regionaam in.';
          regionMessage.className = 'text-red-600';
          return;
        }

        try {
          const res = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              action: 'add_region',
              region_name: regionName
            })
          });
          const data = await res.json();

          if (data.success) {
            regionMessage.textContent = data.message;
            regionMessage.className = 'text-green-600';
            addRegionForm.reset();
            fetchUsers(); // herlaad regio's en gebruikers
            loadRegions(); // herlaad regio tabel
          } else {
            console.error('Add region error:', data.error);
            regionMessage.textContent = data.error || 'Fout bij toevoegen regio.';
            regionMessage.className = 'text-red-600';
          }
        } catch (err) {
          console.error('Add region fetch error:', err);
          regionMessage.textContent = 'Fout bij verzenden: ' + err.message;
          regionMessage.className = 'text-red-600';
        }
      });
    }

    // Regio's laden en weergeven
    async function loadRegions() {
      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({action: 'get_users'})
        });
        const data = await res.json();

        if (!data.success) {
          showToast('Fout bij laden regio\'s: ' + data.error, 'error');
          return;
        }

        const regionsTableBody = document.getElementById('regionsTableBody');
        const mobileRegionsContainer = document.getElementById('mobileRegionsContainer');

        if (regionsTableBody) {
          regionsTableBody.innerHTML = '';
        }
        if (mobileRegionsContainer) {
          mobileRegionsContainer.innerHTML = '';
        }

        // Bewaar beschikbare managers (admin, regiomanager en storemanager rollen zonder regio, behalve huidige gebruiker)
        availableManagers = data.users.filter(user =>
          (user.role === 'admin' || user.role === 'regiomanager' || user.role === 'storemanager') && !user.region_id
        );

        data.regions.forEach(region => {
          // Desktop tabel rij
          if (regionsTableBody) {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-blue-50 transition-colors duration-200';

            const managerInfo = region.manager_name ?
              `<div class="flex items-center">
                <i class="fas fa-user-tie text-blue-600 mr-2"></i>
                <span class="font-medium">${region.manager_name}</span>
              </div>` :
              `<span class="text-gray-500 italic">Geen manager</span>`;

            const managerActionButton = region.manager_name ?
              `<button onclick="removeRegionManager(${region.manager_id})"
                       class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1 mr-2"
                       title="Manager verwijderen">
                <i class="fas fa-user-minus"></i>
              </button>` :
              `<button onclick="openAssignModal(${region.id})"
                       class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1 mr-2"
                       title="Manager toewijzen">
                <i class="fas fa-user-plus"></i>
              </button>`;

            tr.innerHTML = `
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <i class="fas fa-map-marker-alt text-blue-600 mr-3"></i>
                  <input type="text" value="${region.name}" data-region-id="${region.id}" data-field="region_name"
                         class="font-semibold text-gray-900 text-lg border-0 bg-transparent hover:bg-blue-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent rounded-xl px-2 py-1 transition-all duration-200" />
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                ${managerInfo}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex flex-wrap justify-center gap-2">
                  <button onclick="viewRegionStores(${region.id}, '${region.name.replace(/'/g, "\\'")}')"
                          class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                          title="Bekijk winkels in regio">
                    <i class="fas fa-store"></i>
                  </button>
                  ${managerActionButton}
                  <button onclick="deleteRegion(${region.id}, '${region.name.replace(/'/g, "\\'")}')"
                          class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                          title="Regio verwijderen">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            `;

            // Add event listener for region name update
            const regionNameInput = tr.querySelector('input[data-field="region_name"]');
            regionNameInput.addEventListener('blur', function() {
              const newName = this.value.trim();
              const originalName = region.name;
              if (newName && newName !== originalName) {
                updateRegionName(region.id, newName);
              }
            });

            regionNameInput.addEventListener('keypress', function(e) {
              if (e.key === 'Enter') {
                this.blur();
              }
            });

            regionsTableBody.appendChild(tr);
          }

          // Mobile card
          if (mobileRegionsContainer) {
            const managerInfo = region.manager_name ?
              `<div class="flex items-center text-sm text-gray-700 mb-2">
                <i class="fas fa-user-tie text-blue-600 mr-2"></i>
                <span class="font-medium">${region.manager_name}</span>
              </div>` :
              `<div class="text-sm text-gray-500 italic mb-2">Geen manager toegewezen</div>`;

            const managerActionButton = region.manager_name ?
              `<button onclick="removeRegionManager(${region.manager_id})"
                       class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1 mb-2 mr-2"
                       title="Manager verwijderen">
                <i class="fas fa-user-minus"></i>
              </button>` :
              `<button onclick="openAssignModal(${region.id})"
                       class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1 mb-2 mr-2"
                       title="Manager toewijzen">
                <i class="fas fa-user-plus"></i>
              </button>`;

            const mobileCard = document.createElement('div');
            mobileCard.className = 'mobile-table-card p-6';
            mobileCard.innerHTML = `
              <div class="mb-4">
                <h3 class="font-bold text-gray-800 flex items-center text-lg mb-2">
                  <i class="fas fa-map-marker-alt mr-3 text-blue-600"></i>
                  <input type="text" value="${region.name}" data-region-id="${region.id}" data-field="region_name"
                         class="font-bold text-gray-800 text-lg border-0 bg-transparent hover:bg-blue-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent rounded-xl px-2 py-1 transition-all duration-200 flex-1" />
                </h3>
                ${managerInfo}
              </div>
              <div class="space-y-2">
                <div class="flex flex-wrap gap-2">
                  <button onclick="viewRegionStores(${region.id}, '${region.name.replace(/'/g, "\\'")}')"
                          class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                          title="Bekijk winkels in regio">
                    <i class="fas fa-store"></i>
                  </button>
                  ${managerActionButton}
                </div>
                <div class="flex flex-wrap gap-2">
                  <button onclick="deleteRegion(${region.id}, '${region.name.replace(/'/g, "\\'")}')"
                          class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                          title="Regio verwijderen">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            `;

            // Add event listener for mobile region name update
            const mobileRegionNameInput = mobileCard.querySelector('input[data-field="region_name"]');
            mobileRegionNameInput.addEventListener('blur', function() {
              const newName = this.value.trim();
              const originalName = region.name;
              if (newName && newName !== originalName) {
                updateRegionName(region.id, newName);
              }
            });

            mobileRegionNameInput.addEventListener('keypress', function(e) {
              if (e.key === 'Enter') {
                this.blur();
              }
            });

            mobileRegionsContainer.appendChild(mobileCard);
          }
        });

        // Add event listeners for region name updates (for any remaining inputs)
        document.querySelectorAll('input[data-field="region_name"]').forEach(input => {
          // Remove any existing listeners to avoid duplicates
          input.removeEventListener('blur', handleRegionNameUpdate);
          input.removeEventListener('keypress', handleRegionNameKeypress);

          // Add new listeners
          input.addEventListener('blur', handleRegionNameUpdate);
          input.addEventListener('keypress', handleRegionNameKeypress);
        });

        // Add the missing modal HTML and functions
        // Add this right before the closing </div> of the main container
        const regionStoresModalHTML = `
        <!-- Region Stores Modal -->
        <div id="regionStoresModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
          <div class="glass-card rounded-2xl p-6 max-w-4xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-store mr-3 text-green-600"></i>
                Winkels in regio: <span id="regionStoresTitle" class="text-green-600 ml-2"></span>
              </h3>
              <button onclick="closeRegionStoresModal()" class="text-gray-500 hover:text-gray-700 text-2xl">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <!-- Add Store to Region Section -->
            <div class="mb-8 bg-gradient-to-r from-blue-50 to-blue-100 p-6 rounded-2xl border-l-4 border-blue-400">
              <h4 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                <i class="fas fa-plus-circle mr-3 text-blue-600"></i>Winkel toevoegen aan regio
              </h4>
              <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                  <select id="availableStoresSelect" class="input-field w-full rounded-xl px-4 py-3 focus:outline-none text-base">
                    <option value="">Selecteer winkel om toe te voegen</option>
                  </select>
                </div>
                <button onclick="addStoreToRegion()" class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white px-8 py-3 rounded-xl transition text-base font-medium transform hover:-translate-y-1 shadow-lg">
                  <i class="fas fa-plus mr-2"></i>Toevoegen
                </button>
              </div>
              <p id="addStoreMessage" class="mt-3 text-sm"></p>
            </div>

            <!-- Current Stores List -->
            <div>
              <h4 class="text-lg font-semibold mb-4 text-gray-800 flex items-center">
                <i class="fas fa-list mr-3 text-green-600"></i>Huidige winkels in regio
              </h4>
              <div id="regionStoresList" class="space-y-4">
                <!-- Stores will be loaded here -->
              </div>

              <div id="noStoresMessage" class="text-center py-8 text-gray-500 hidden">
                <i class="fas fa-store text-4xl mb-4 opacity-50"></i>
                <p>Geen winkels gevonden in deze regio</p>
              </div>
            </div>

            <div class="mt-6 text-center">
              <button onclick="closeRegionStoresModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-xl transition">
                <i class="fas fa-times mr-2"></i>Sluiten
              </button>
            </div>
          </div>
        </div>`;

        // Insert the modal HTML into the page
        document.body.insertAdjacentHTML('beforeend', regionStoresModalHTML);

      } catch (error) {
        showToast('Fout bij laden regio\'s', 'error');
      }
    }

    // Helper functions for region name updates
    function handleRegionNameUpdate(e) {
      const regionId = e.target.getAttribute('data-region-id');
      const newName = e.target.value.trim();
      const originalName = e.target.defaultValue;

      if (newName && newName !== originalName && regionId) {
        updateRegionName(regionId, newName);
      }
    }

    function handleRegionNameKeypress(e) {
      if (e.key === 'Enter') {
        e.target.blur();
      }
    }
    

    // Modal functies voor regiomanager toewijzen
    function openAssignModal(regionId) {
      document.getElementById('assignRegionId').value = regionId;

      const managerSelect = document.getElementById('managerSelect');
      managerSelect.innerHTML = '<option value="">Selecteer gebruiker</option>';

      availableManagers.forEach(user => {
        managerSelect.innerHTML += `<option value="${user.id}">${user.username} (${user.email})</option>`;
      });

      document.getElementById('assignManagerModal').classList.remove('hidden');
    }

    function closeAssignModal() {
      document.getElementById('assignManagerModal').classList.add('hidden');
      document.getElementById('assignMessage').textContent = '';
      document.getElementById('assignMessage').className = '';
    }

    // Regiomanager toewijzen formulier
    const assignManagerForm = document.getElementById('assignManagerForm');
    const assignMessage = document.getElementById('assignMessage');

    if (assignManagerForm) {
      assignManagerForm.addEventListener('submit', async e => {
        e.preventDefault();
        assignMessage.textContent = '';
        assignMessage.className = '';

        const regionId = document.getElementById('assignRegionId').value;
        const userId = document.getElementById('managerSelect').value;

        if (!userId) {
          assignMessage.textContent = 'Selecteer een gebruiker.';
          assignMessage.className = 'text-red-600';
          return;
        }

        try {
          const res = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              action: 'assign_region_manager',
              user_id: userId,
              region_id: regionId
            })
          });
          const data = await res.json();

          if (data.success) {
            assignMessage.textContent = data.message;
            assignMessage.className = 'text-green-600';
            setTimeout(() => {
              closeAssignModal();
              fetchUsers(); // herlaad gebruikers
              loadRegions(); // herlaad regio tabel
            }, 1500);
          } else {
            assignMessage.textContent = data.error || 'Fout bij toewijzen manager.';
            assignMessage.className = 'text-red-600';
          }
        } catch (err) {
          assignMessage.textContent = 'Fout bij verzenden: ' + err.message;
          assignMessage.className = 'text-red-600';
        }
      });
    }

    // Regiomanager verwijderen
    async function removeRegionManager(userId) {
      if (!confirm('Weet je zeker dat je deze regiomanager wilt verwijderen van de regio?')) return;

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'remove_region_manager',
            user_id: userId
          })
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message);
          fetchUsers(); // herlaad gebruikers
          loadRegions(); // herlaad regio tabel
        } else {
          showToast('Fout: ' + data.error, 'error');
        }
      } catch (error) {
        showToast('Fout bij verwijderen regiomanager', 'error');
      }
    }

    // Regio bewerken modal functies
    function openEditRegionModal(regionId, regionName) {
      document.getElementById('editRegionId').value = regionId;
      document.getElementById('editRegionName').value = regionName;
      document.getElementById('editRegionModal').classList.remove('hidden');
    }

    function closeEditRegionModal() {
      document.getElementById('editRegionModal').classList.add('hidden');
      document.getElementById('editRegionMessage').textContent = '';
      document.getElementById('editRegionMessage').className = '';
    }

    // Regio naam wijzigen formulier
    const editRegionForm = document.getElementById('editRegionForm');
    const editRegionMessage = document.getElementById('editRegionMessage');

    if (editRegionForm) {
      editRegionForm.addEventListener('submit', async e => {
        e.preventDefault();
        editRegionMessage.textContent = '';
        editRegionMessage.className = '';

        const regionId = document.getElementById('editRegionId').value;
        const regionName = document.getElementById('editRegionName').value.trim();

        if (!regionName) {
          editRegionMessage.textContent = 'Vul een regionaam in.';
          editRegionMessage.className = 'text-red-600';
          return;
        }

        try {
          const res = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              action: 'update_region',
              region_id: regionId,
              region_name: regionName
            })
          });
          const data = await res.json();

          if (data.success) {
            editRegionMessage.textContent = data.message;
            editRegionMessage.className = 'text-green-600';
            setTimeout(() => {
              closeEditRegionModal();
              fetchUsers(); // herlaad regio's en gebruikers
              loadRegions(); // herlaad regio tabel
            }, 1500);
          } else {
            editRegionMessage.textContent = data.error || 'Fout bij wijzigen regio.';
            editRegionMessage.className = 'text-red-600';
          }
        } catch (err) {
          editRegionMessage.textContent = 'Fout bij verzenden: ' + err.message;
          editRegionMessage.className = 'text-red-600';
        }
      });
    }

    // Regio naam bijwerken
    async function updateRegionName(regionId, regionName) {
      if (!regionName) return;

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'update_region',
            region_id: regionId,
            region_name: regionName
          })
        });
        const data = await res.json();

        if (data.success) {
          showToast('Regio naam bijgewerkt');
          fetchUsers(); // herlaad regio's en gebruikers
          loadRegions(); // herlaad regio tabel
        } else {
          showToast('Fout bij bijwerken: ' + data.error, 'error');
          loadRegions(); // refresh om oude waarden terug te zetten
        }
      } catch (error) {
        showToast('Fout bij bijwerken regio naam', 'error');
        loadRegions();
      }
    }

    // Regio verwijderen
    async function deleteRegion(regionId, regionName) {
      if (!confirm(`Weet je zeker dat je de regio "${regionName}" definitief wilt verwijderen?\n\nLet op: Dit kan alleen als er geen winkels of gebruikers meer gekoppeld zijn aan deze regio.`)) return;

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'delete_region',
            region_id: regionId
          })
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message);
          fetchUsers(); // herlaad regio's en gebruikers
          loadRegions(); // herlaad regio tabel
        } else {
          showToast('Fout: ' + data.error, 'error');
        }
      } catch (error) {
        showToast('Fout bij verwijderen regio', 'error');
      }
    }

    // Global variable to store current region ID for store management
    let currentRegionId = null;

    // Functie om winkels in regio te bekijken
    async function viewRegionStores(regionId, regionName) {
      currentRegionId = regionId;

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'get_region_stores',
            region_id: regionId
          })
        });
        const data = await res.json();

        if (!data.success) {
          showToast('Fout bij laden winkels: ' + data.error, 'error');
          return;
        }

        // Vul modal met gegevens
        document.getElementById('regionStoresTitle').textContent = regionName;
        const storesList = document.getElementById('regionStoresList');
        const noStoresMessage = document.getElementById('noStoresMessage');
        const availableStoresSelect = document.getElementById('availableStoresSelect');

        // Vul beschikbare winkels dropdown
        availableStoresSelect.innerHTML = '<option value="">Selecteer winkel om toe te voegen</option>';
        if (data.available_stores && data.available_stores.length > 0) {
          data.available_stores.forEach(store => {
            availableStoresSelect.innerHTML += `<option value="${store.id}">${store.name}</option>`;
          });
        }

        // Clear add store message
        document.getElementById('addStoreMessage').textContent = '';
        document.getElementById('addStoreMessage').className = '';

        if (data.stores.length === 0) {
          storesList.innerHTML = '';
          noStoresMessage.classList.remove('hidden');
        } else {
          noStoresMessage.classList.add('hidden');
          storesList.innerHTML = '';

          data.stores.forEach(store => {
            const storeCard = document.createElement('div');
            storeCard.className = 'bg-gradient-to-r from-green-50 to-green-100 border border-green-200 rounded-xl p-4 hover:shadow-md transition-all duration-200';

            const isBurgerKitchen = parseInt(store.is_bk) === 1;
            const storeTypeIcon = isBurgerKitchen ? 'fas fa-hamburger' : 'fas fa-store';
            const storeTypeColor = isBurgerKitchen ? 'from-purple-500 to-purple-600' : 'from-green-500 to-green-600';
            const storeTypeBadge = isBurgerKitchen ?
              '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 ml-2"><i class="fas fa-hamburger mr-1"></i>BK</span>' :
              '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2"><i class="fas fa-store mr-1"></i>REG</span>';

            storeCard.innerHTML = `
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                  <div class="w-10 h-10 bg-gradient-to-br ${storeTypeColor} rounded-lg mr-3 flex items-center justify-center">
                    <i class="${storeTypeIcon} text-white"></i>
                  </div>
                  <div>
                    <div class="flex items-center">
                      <h4 class="font-semibold text-gray-800">${store.name}</h4>
                      ${storeTypeBadge}
                    </div>
                    <p class="text-sm text-gray-600">
                      <i class="fas fa-users mr-1"></i>
                      ${store.user_count} gebruiker${store.user_count !== 1 ? 's' : ''}
                    </p>
                  </div>
                </div>
                <div class="flex items-center space-x-3">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    ID: ${store.id}
                  </span>
                  <button onclick="removeStoreFromRegion(${store.id}, '${store.name.replace(/'/g, "\\'")}')"
                          class="bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white p-2 rounded-xl transition-all duration-200 transform hover:-translate-y-1"
                          title="Winkel uit regio verwijderen"
                          ${store.user_count > 0 ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                    <i class="fas fa-minus text-sm"></i>
                  </button>
                </div>
              </div>

              <!-- BurgerKitchen Toggle -->
              <div class="bg-gradient-to-r from-purple-50 to-purple-100 p-3 rounded-xl border-l-4 border-purple-400">
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <i class="fas fa-hamburger mr-2 text-purple-600"></i>
                    <div>
                      <span class="font-medium text-gray-700 text-sm">BurgerKitchen winkel</span>
                      <p class="text-xs text-gray-600">Schakel in als dit een BurgerKitchen locatie is</p>
                    </div>
                  </div>
                  <div class="relative">
                    <input type="checkbox" id="bkToggle_${store.id}" class="sr-only" ${isBurgerKitchen ? 'checked' : ''} onchange="toggleBurgerKitchen(${store.id}, this.checked)">
                    <div class="toggle-bg w-10 h-5 bg-gray-300 rounded-full shadow-inner cursor-pointer transition-colors duration-300" onclick="document.getElementById('bkToggle_${store.id}').click()"></div>
                    <div class="toggle-dot absolute w-4 h-4 bg-white rounded-full shadow-md top-0.5 left-0.5 transition-transform duration-300" onclick="document.getElementById('bkToggle_${store.id}').click()"></div>
                  </div>
                </div>
              </div>

              ${store.user_count > 0 ? '<p class="text-xs text-red-600 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Kan niet verwijderen: winkel heeft nog gebruikers</p>' : ''}
            `;
            storesList.appendChild(storeCard);

            // Update toggle visual state
            const toggle = storeCard.querySelector(`#bkToggle_${store.id}`);
            const toggleBg = toggle.nextElementSibling;
            const toggleDot = toggleBg.nextElementSibling;

            if (isBurgerKitchen) {
              toggleBg.style.backgroundColor = '#059669';
              toggleDot.style.transform = 'translateX(1.25rem)';
            } else {
              toggleBg.style.backgroundColor = '#d1d5db';
              toggleDot.style.transform = 'translateX(0)';
            }
          });
        }

        // Toon modal
        document.getElementById('regionStoresModal').classList.remove('hidden');
      } catch (error) {
        showToast('Fout bij laden winkels', 'error');
      }
    }

    // Functie om winkel toe te voegen aan regio
    async function addStoreToRegion() {
      const storeId = document.getElementById('availableStoresSelect').value;
      const addStoreMessage = document.getElementById('addStoreMessage');

      if (!storeId) {
        addStoreMessage.textContent = 'Selecteer een winkel om toe te voegen.';
        addStoreMessage.className = 'text-red-600';
        return;
      }

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'add_store_to_region',
            region_id: currentRegionId,
            store_id: storeId
          })
        });
        const data = await res.json();

        if (data.success) {
          addStoreMessage.textContent = data.message;
          addStoreMessage.className = 'text-green-600';

          // Refresh the modal content
          const regionName = document.getElementById('regionStoresTitle').textContent;
          setTimeout(() => {
            viewRegionStores(currentRegionId, regionName);
          }, 1000);
        } else {
          addStoreMessage.textContent = data.error || 'Fout bij toevoegen winkel aan regio.';
          addStoreMessage.className = 'text-red-600';
        }
      } catch (error) {
        addStoreMessage.textContent = 'Fout bij toevoegen winkel aan regio.';
        addStoreMessage.className = 'text-red-600';
      }
    }

    // Functie om winkel uit regio te verwijderen
    async function removeStoreFromRegion(storeId, storeName) {
      if (!confirm(`Weet je zeker dat je winkel "${storeName}" uit deze regio wilt verwijderen?\n\nLet op: Dit kan alleen als er geen gebruikers meer gekoppeld zijn aan deze winkel.`)) {
        return;
      }

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'remove_store_from_region',
            region_id: currentRegionId,
            store_id: storeId
          })
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message);

          // Refresh the modal content
          const regionName = document.getElementById('regionStoresTitle').textContent;
          viewRegionStores(currentRegionId, regionName);
        } else {
          showToast('Fout: ' + data.error, 'error');
        }
      } catch (error) {
        showToast('Fout bij verwijderen winkel uit regio', 'error');
      }
    }

    // Functie om winkels modal te sluiten
    function closeRegionStoresModal() {
      document.getElementById('regionStoresModal').classList.add('hidden');
    }

    // Functie om BurgerKitchen status te wijzigen
    async function toggleBurgerKitchen(storeId, isChecked) {
      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'toggle_burger_kitchen',
            store_id: storeId,
            is_bk: isChecked ? '1' : '0'
          })
        });
        const data = await res.json();

        if (data.success) {
          showToast(data.message);

          // Update toggle visual state
          const toggle = document.getElementById(`bkToggle_${storeId}`);
          const toggleBg = toggle.nextElementSibling;
          const toggleDot = toggleBg.nextElementSibling;

          if (isChecked) {
            toggleBg.style.backgroundColor = '#059669';
            toggleDot.style.transform = 'translateX(1.25rem)';
          } else {
            toggleBg.style.backgroundColor = '#d1d5db';
            toggleDot.style.transform = 'translateX(0)';
          }

          // Refresh the modal content to update the store type badge and icon
          const regionName = document.getElementById('regionStoresTitle').textContent;
          setTimeout(() => {
            viewRegionStores(currentRegionId, regionName);
          }, 1000);
        } else {
          showToast('Fout: ' + data.error, 'error');
          // Revert toggle state on error
          toggle.checked = !isChecked;
        }
      } catch (error) {
        showToast('Fout bij wijzigen BurgerKitchen status', 'error');
        // Revert toggle state on error
        const toggle = document.getElementById(`bkToggle_${storeId}`);
        toggle.checked = !isChecked;
      }
    }

    // Laad gebruikers bij pagina laden
    document.addEventListener('DOMContentLoaded', () => {
      fetchUsers();
      loadRegions();
    });
    document.getElementById('searchInput').addEventListener('input', filterUsers);
  </script>

  <?php include __DIR__ . '/alerts/danger_alert.php'; ?>
  <script src="js/alert.js"></script>
</body>
</html>


