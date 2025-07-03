<?php
if (session_status() == PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Aangepaste paden voor nieuwe structuur
require_once __DIR__ . '/../../vendor/autoload.php';

// Alleen admin toegang
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo "Toegang geweigerd.";
    exit;
}

// Database pad aangepast
$db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Mailconfig pad aangepast
$mailConfig = require __DIR__ . '/../config/mail.php';

// Rest van je dashboard code...

// AJAX acties afhandelen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'get_users') {
            // Haal gebruikers met winkelnaam (via LEFT JOIN)
            $stmt = $db->query("
                SELECT u.id, u.email, u.username, u.role, u.store_id, s.name AS store_name
                FROM users u
                LEFT JOIN stores s ON u.store_id = s.id
                ORDER BY u.id
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Haal ook alle winkels op voor dropdowns
            $storesStmt = $db->query("SELECT id, name FROM stores ORDER BY name");
            $stores = $storesStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'users' => $users, 'stores' => $stores]);
            exit;
        }

        if ($action === 'update_user') {
            $id = intval($_POST['id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? '';
            $storeId = intval($_POST['store_id'] ?? 0);

            if (!$id || !$email || !$username || !in_array($role, ['admin', 'manager'])) {
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
                $stmt = $db->prepare("SELECT id FROM stores WHERE id = ?");
                $stmt->execute([$storeId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Ongeldige winkel geselecteerd.');
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
          if (!$storeName) {
              throw new Exception('Winkelnaam is verplicht.');
          }
      
          // Check of winkel al bestaat (case-insensitive)
          $stmt = $db->prepare("SELECT id FROM stores WHERE LOWER(name) = LOWER(?)");
          $stmt->execute([$storeName]);
          if ($stmt->fetch()) {
              throw new Exception('Winkel bestaat al.');
          }
      
          $stmt = $db->prepare("INSERT INTO stores (name) VALUES (?)");
          $stmt->execute([$storeName]);
      
          echo json_encode(['success' => true, 'message' => 'Winkel toegevoegd.']);
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
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role = $_POST['role'] ?? '';
            $storeId = intval($_POST['store_id'] ?? 0);

            // Validatie
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Ongeldig e-mailadres.');
            }
            if (!$username) {
                throw new Exception('Gebruikersnaam is verplicht.');
            }
            if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                throw new Exception('Gebruikersnaam moet 3-20 tekens bevatten, letters, cijfers of underscores.');
            }
            if (!in_array($role, ['admin', 'manager'])) {
                throw new Exception('Ongeldige rol.');
            }

            // Validatie winkel
            if ($storeId !== 0) {
                $stmt = $db->prepare("SELECT id FROM stores WHERE id = ?");
                $stmt->execute([$storeId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Ongeldige winkel geselecteerd.');
                }
            } else {
                $storeId = null;
            }

            // Check of e-mail of gebruikersnaam al bestaat
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                throw new Exception('E-mail of gebruikersnaam bestaat al.');
            }

            $token = bin2hex(random_bytes(16));
            $expiry = time() + 24*3600; // 24 uur geldig

            $stmt = $db->prepare("INSERT INTO users (email, username, role, store_id, invite_token, invite_expiry) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $username, $role, $storeId, $token, $expiry]);

            // Verstuur uitnodigingsmail
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
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Uitnodiging om account aan te maken";
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $path = "/set-password?token=" . urlencode($token);
            $link = $protocol . $domain . $path;    
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
            $mail->send();

            echo json_encode(['success' => true, 'message' => 'Uitnodiging succesvol verzonden!']);
            exit;
        }

        throw new Exception('Onbekende actie.');
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
            <span class="hidden sm:inline">Admin Dashboard</span>
            <span class="sm:hidden">Admin</span>
          </h1>
          <p class="text-sm sm:text-base text-gray-600 ml-12 sm:ml-13">Beheer gebruikers en systeem instellingen</p>
        </div>

        <!-- Gebruikersinfo -->
        <div class="flex flex-col items-center cursor-pointer select-none">
          <div class="w-12 h-12 bg-gradient-to-br from-green-600 to-green-700 rounded-full flex items-center justify-center mb-2">
            <i class="fas fa-user-shield text-white text-lg"></i>
          </div>
          <span class="text-xs text-gray-700 font-medium"><?php echo htmlspecialchars($_SESSION['username']) ?></span>
          <span class="text-xs text-green-600 font-semibold">Admin</span>
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
            <h3 class="font-semibold text-gray-800">Admin Menu</h3>
            <button id="mobile-menu-close" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
        </div>
        
        <div class="p-4 space-y-2">
          <button onclick="scrollToSection('store-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-store mr-3 text-green-600"></i> Winkel Toevoegen
          </button>
          
          <button onclick="scrollToSection('invite-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-user-plus mr-3 text-green-600"></i> Gebruiker Uitnodigen
          </button>
          
          <button onclick="scrollToSection('users-section'); closeMobileMenu();" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
            <i class="fas fa-users mr-3 text-green-600"></i> Gebruikersbeheer
          </button>

          <div class="border-t border-white/20 pt-2 mt-4">
            <a href="/" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-arrow-left mr-3 text-green-600"></i> Terug naar Dashboard
            </a>
            
            <a href="/logout" class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 text-red-600 flex items-center">
              <i class="fas fa-sign-out-alt mr-3"></i> Uitloggen
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Desktop Navigation -->
    <div class="hidden sm:flex flex-wrap gap-2 lg:gap-4 mb-4 sm:mb-8">
      <a href="/" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors text-sm lg:text-base">
        <i class="fas fa-arrow-left mr-1 lg:mr-2"></i> 
        <span class="hidden md:inline">Terug naar Dashboard</span>
        <span class="md:hidden">Terug</span>
      </a>
      <a href="/logout" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium hover:from-red-600 hover:to-red-700 transition-all text-sm lg:text-base transform hover:-translate-y-1">
        <i class="fas fa-sign-out-alt mr-1 lg:mr-2"></i> 
        <span class="hidden sm:inline">Uitloggen</span>
      </a>
    </div>

    <!-- Toevoegen Winkel -->
    <div id="store-section" class="mb-4 sm:mb-6 glass-card p-4 sm:p-6 rounded-2xl">
      <h2 class="text-lg sm:text-xl font-semibold mb-4 responsive-text-xl flex items-center">
        <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 flex items-center justify-center">
          <i class="fas fa-store text-white text-sm"></i>
        </div>
        Winkel toevoegen
      </h2>
      <form id="addStoreForm" class="flex flex-col sm:flex-row gap-4">
        <input type="text" id="storeNameInput" placeholder="Winkelnaam" required
              class="input-field flex-grow rounded-xl px-3 py-2 focus:outline-none text-sm sm:text-base" />
        <button type="submit" class="btn-secondary text-white px-4 py-2 rounded-xl transition text-sm sm:text-base whitespace-nowrap">
          <i class="fas fa-plus mr-2"></i>Toevoegen
        </button>
      </form>
      <p id="storeMessage" class="mt-2 text-sm"></p>
    </div>

    <!-- Uitnodigen formulier -->
    <div id="invite-section" class="mb-4 sm:mb-6 glass-card p-4 sm:p-6 rounded-2xl">
      <h2 class="text-lg sm:text-xl font-semibold mb-4 responsive-text-xl flex items-center">
        <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 flex items-center justify-center">
          <i class="fas fa-user-plus text-white text-sm"></i>
        </div>
        Gebruiker uitnodigen
      </h2>
      <form id="inviteForm" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="inviteEmail" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-envelope mr-2 text-green-600"></i>E-mail
            </label>
            <input type="email" id="inviteEmail" name="email" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none" />
          </div>
          <div>
            <label for="inviteUsername" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-user mr-2 text-green-600"></i>Gebruikersnaam
            </label>
            <input type="text" id="inviteUsername" name="username" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+" title="Letters, cijfers en underscores toegestaan" class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none" />
          </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="inviteRole" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-user-tag mr-2 text-green-600"></i>Rol
            </label>
            <select id="inviteRole" name="role" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none">
              <option value="">Selecteer rol</option>
              <option value="admin">Admin</option>
              <option value="manager">Manager</option>
            </select>
          </div>
          <div>
            <label for="inviteStore" class="block font-medium mb-1 text-sm sm:text-base text-gray-700">
              <i class="fas fa-store mr-2 text-green-600"></i>Winkel
            </label>
            <select id="inviteStore" name="store_id" required class="input-field w-full rounded-xl px-3 py-2 text-sm sm:text-base focus:outline-none">
              <option value="">Selecteer winkel</option>
              <!-- Wordt dynamisch gevuld -->
            </select>
          </div>
        </div>
        
        <button type="submit" class="w-full sm:w-auto btn-primary text-white px-6 py-2 rounded-xl transition text-sm sm:text-base">
          <i class="fas fa-paper-plane mr-2"></i>Uitnodigen
        </button>
        <p id="inviteMessage" class="mt-2 text-sm"></p>
      </form>
    </div>

    <!-- Main Content -->
    <div id="users-section" class="glass-card rounded-2xl p-4 sm:p-6">
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
        toast.style.transform = 'translateX(100%)';
      }, 3000);
    }

    let stores = [];

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
                  <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
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

    // Store toevoegen formulier
    const addStoreForm = document.getElementById('addStoreForm');
    const storeMessage = document.getElementById('storeMessage');

    addStoreForm.addEventListener('submit', async e => {
      e.preventDefault();
      storeMessage.textContent = '';
      storeMessage.className = '';

      const storeName = document.getElementById('storeNameInput').value.trim();
      if (!storeName) {
        storeMessage.textContent = 'Vul een winkelnaam in.';
        storeMessage.className = 'text-red-600';
        return;
      }

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: new URLSearchParams({
            action: 'add_store',
            store_name: storeName
          })
        });
        const data = await res.json();

        if (data.success) {
          storeMessage.textContent = data.message;
          storeMessage.className = 'text-green-600';
          addStoreForm.reset();
          fetchUsers(); // herlaad winkels en gebruikers
        } else {
          storeMessage.textContent = data.error || 'Fout bij toevoegen winkel.';
          storeMessage.className = 'text-red-600';
        }
      } catch (err) {
        storeMessage.textContent = 'Fout bij verzenden: ' + err.message;
        storeMessage.className = 'text-red-600';
      }
    });

    // Laad gebruikers bij pagina laden
    document.addEventListener('DOMContentLoaded', fetchUsers);
    document.getElementById('searchInput').addEventListener('input', filterUsers);
  </script>
</body>
</html>