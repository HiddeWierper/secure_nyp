<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// require_once __DIR__ . '/vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } else {
        try {
            $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check of e-mail bestaat
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'E-mailadres niet gevonden.';
            } else {
                // Genereer token en expiry (1 uur geldig)
                $token = bin2hex(random_bytes(16));
                $expiry = time() + 3600;

                // Opslaan token in DB
                $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
                $stmt->execute([$token, $expiry, $user['id']]);

                // Mail versturen
                $mail = new PHPMailer(true);

                // Laad mailconfig (pas pad aan)
                $mailConfig = require __DIR__ . '/../config/mail.php';

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
                $mail->Subject = 'Wachtwoord reset verzoek - New York Pizza';

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
                $domain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                $path = "/reset-password?token=" . urlencode($token);
                $resetLink = $protocol . $domain . $path;



                // Mooie HTML mail met groene styling
                $mail->Body = "
                <!DOCTYPE html>
                <html lang='nl'>
                <head>
                  <meta charset='UTF-8'>
                  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                  <title>Wachtwoord Reset</title>
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
                      content: '🔑';
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
                    
                    .reset-button {
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
                    
                    .reset-button:hover {
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
                      content: '🛡️';
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
                      
                      .reset-button {
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
                      <div class='email-title'>Wachtwoord Reset</div>
                      <div class='email-subtitle'>New York Pizza Taakbeheer</div>
                    </div>
                    
                    <div class='email-content'>
                      <div class='greeting'>Hallo " . htmlspecialchars($user['username']) . "! 👋</div>
                      
                      <div class='message-text'>
                        We hebben een verzoek ontvangen om je wachtwoord te resetten voor je New York Pizza Taakbeheer account. 
                        Geen zorgen, dit gebeurt wel vaker!
                      </div>
                      
                      <div style='text-align: center;'>
                        <a href='$resetLink' class='reset-button'>
                          🔐 Wachtwoord Resetten
                        </a>
                      </div>
                      
                      <div class='security-notice'>
                        <div class='security-notice-title'>Belangrijk om te weten:</div>
                        <div class='security-notice-text'>
                          • Deze link is slechts <strong>1 uur geldig</strong><br>
                          • Als je dit niet hebt aangevraagd, kun je deze e-mail veilig negeren<br>
                          • Je wachtwoord blijft ongewijzigd tot je een nieuw wachtwoord instelt
                        </div>
                      </div>
                      
                      <div class='divider'></div>
                      
                      <div class='message-text' style='font-size: 14px; color: #9ca3af;'>
                        Als de knop niet werkt, kopieer dan deze link naar je browser:<br>
                        <a href='$resetLink' style='color: #059669; word-break: break-all;'>$resetLink</a>
                      </div>
                    </div>
                    
                    <div class='footer'>
                      <div class='footer-text'>
                        Met vriendelijke groet,<br>
                        <span class='company-name'>New York Pizza Taakbeheer Team</span>
                      </div>
                      <div class='footer-text' style='font-size: 12px; margin-top: 15px;'>
                        Deze e-mail is automatisch gegenereerd. Reageer niet op deze e-mail.
                      </div>
                    </div>
                  </div>
                </body>
                </html>
                ";

                $mail->send();

                $message = 'Er is een e-mail met instructies verzonden als het e-mailadres bestaat.';
                $_POST['email'] = '';
                
            }
        } catch (Exception $e) {
            $error = 'Fout bij verzenden e-mail: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <title>Wachtwoord vergeten - New York Pizza</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
      position: relative;
      overflow-x: hidden;
    }
    
    /* Animated background elements */
    body::before {
      content: '';
      position: absolute;
      top: -20%;
      left: -20%;
      width: 140%;
      height: 140%;
      background: radial-gradient(circle, rgba(16, 185, 129, 0.3) 0%, rgba(5, 150, 105, 0.2) 30%, transparent 70%);
      animation: float 15s ease-in-out infinite;
      z-index: 1;
      border-radius: 50%;
    }
    
    body::after {
      content: '';
      position: absolute;
      top: 10%;
      right: -20%;
      width: 80%;
      height: 80%;
      background: radial-gradient(circle, rgba(52, 211, 153, 0.25) 0%, rgba(16, 185, 129, 0.15) 40%, transparent 80%);
      animation: float 20s ease-in-out infinite reverse;
      z-index: 1;
      border-radius: 50%;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(5deg); }
    }
    
    .glass-card {
      backdrop-filter: blur(20px);
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.2);
      position: relative;
      z-index: 10;
      animation: slideUp 0.8s ease-out;
    }
    
    .glass-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #059669, #10b981, #34d399);
      border-radius: 1rem 1rem 0 0;
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(50px) scale(0.95);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .btn-primary::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }
    
    .btn-primary:hover::before {
      left: 100%;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #047857 0%, #065f46 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.4);
    }
    
    .btn-secondary {
      background: rgba(255, 255, 255, 0.9);
      color: #374151;
      border: 1px solid rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
    }
    
    .btn-secondary:hover {
      background: rgba(255, 255, 255, 1);
      transform: translateY(-1px);
      box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.1);
    }
    
    .input-field {
      background: rgba(255, 255, 255, 0.9);
      border: 2px solid rgba(5, 150, 105, 0.2);
      transition: all 0.3s ease;
    }
    
    .input-field:focus {
      background: rgba(255, 255, 255, 1);
      border-color: #059669;
      box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
      transform: translateY(-1px);
    }
    
    .input-field:hover {
      border-color: rgba(5, 150, 105, 0.4);
    }
    
    .alert-success {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      border: 1px solid #10b981;
      color: #065f46;
      animation: shake 0.5s ease-in-out;
    }
    
    .alert-error {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      border: 1px solid #ef4444;
      color: #991b1b;
      animation: shake 0.5s ease-in-out;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }
    
    .logo-container {
      background: linear-gradient(135deg, #059669 0%, #10b981 100%);
      animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    .form-group {
      animation: fadeInUp 0.6s ease-out forwards;
      opacity: 0;
      transform: translateY(20px);
    }
    
    .form-group:nth-child(1) { animation-delay: 0.1s; }
    .form-group:nth-child(2) { animation-delay: 0.2s; }
    .form-group:nth-child(3) { animation-delay: 0.3s; }
    .form-group:nth-child(4) { animation-delay: 0.4s; }
    
    @keyframes fadeInUp {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .input-icon {
      transition: color 0.3s ease;
    }
    
    .input-field:focus ~ .input-icon {
      color: #059669;
    }
    
    /* Mobile optimizations */
    @media (max-width: 640px) {
      .glass-card {
        margin: 1rem;
        padding: 1.5rem;
      }
      
      .responsive-text-2xl {
        font-size: 1.5rem;
      }
      
      .responsive-text-lg {
        font-size: 1.125rem;
      }
    }
    
    /* Loading state */
    .btn-loading {
      pointer-events: none;
      opacity: 0.7;
    }
    
    .btn-loading::after {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      margin: auto;
      border: 2px solid transparent;
      border-top-color: #ffffff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .ripple {
      position: absolute;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: scale(0);
      animation: ripple-animation 0.6s linear;
      pointer-events: none;
    }
    
    @keyframes ripple-animation {
      to {
        transform: scale(4);
        opacity: 0;
      }
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="glass-card rounded-2xl p-6 sm:p-8">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <div class="logo-container w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4">
          <i class="fas fa-key text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2 responsive-text-2xl">
          Wachtwoord vergeten?
        </h1>
        <p class="text-gray-600 text-sm sm:text-base">
          Geen zorgen, we sturen je een reset link
        </p>
      </div>

      <!-- Success/Error Messages -->
      <?php if ($error): ?>
        <div class="alert-error px-4 py-3 rounded-xl mb-6" role="alert">
          <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-3 text-red-600"></i>
            <span class="font-medium"><?=htmlspecialchars($error)?></span>
          </div>
        </div>
      <?php elseif ($message): ?>
        <div class="alert-success px-4 py-3 rounded-xl mb-6" role="alert">
          <div class="flex items-center">
            <i class="fas fa-check-circle mr-3 text-green-600"></i>
            <span class="font-medium"><?=htmlspecialchars($message)?></span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form method="post" action="" class="space-y-6" id="forgotForm">
        <div class="form-group">
          <label for="email" class="block text-gray-700 font-semibold mb-3 text-sm sm:text-base">
            <i class="fas fa-envelope mr-2 text-green-600"></i>
            E-mailadres
          </label>
          <div class="relative">
            <input type="email" 
                   id="email" 
                   name="email" 
                   required
                   placeholder="jouw@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   class="input-field w-full px-4 py-3 rounded-xl focus:outline-none text-sm sm:text-base pl-12" />
            <i class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-green-600 opacity-70 input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <button type="submit"
                  id="submitBtn"
                  class="btn-primary w-full text-white font-semibold py-3 px-6 rounded-xl text-sm sm:text-base">
            <i class="fas fa-paper-plane mr-2"></i>
            Verstuur reset link
          </button>
        </div>
      </form>

      <!-- Back to login -->
      <div class="form-group mt-8 text-center">
        <p class="text-gray-600 text-sm mb-4">Weet je je wachtwoord weer?</p>
        <a href="/login" 
           class="btn-secondary inline-flex items-center px-6 py-2 rounded-xl font-medium text-sm transition-all hover:no-underline">
          <i class="fas fa-arrow-left mr-2"></i>
          Terug naar inloggen
        </a>
      </div>
    </div>

    <!-- Footer -->
    <div class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-20">
      <p class="text-white/80 text-xs text-center">
        <i class="fas fa-shield-alt mr-1"></i>
        Beveiligd door New York Pizza Taakbeheer
      </p>
    </div>
  </div>

  <script>
    // Form submission handling with better UX
    document.getElementById('forgotForm').addEventListener('submit', function(e) {
      const submitBtn = document.getElementById('submitBtn');
      const originalText = submitBtn.innerHTML;
      
      // Show loading state
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Versturen...';
      submitBtn.classList.add('btn-loading');
      
      // Reset after 3 seconds (in case of redirect issues)
      setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.classList.remove('btn-loading');
      }, 3000);
    });

    // Input field animations
    document.querySelectorAll('.input-field').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
      });
      
      // Add floating label effect
      input.addEventListener('input', function() {
        if (this.value) {
          this.classList.add('has-value');
        } else {
          this.classList.remove('has-value');
        }
      });
    });

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('[role="alert"]').forEach(alert => {
      setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 300);
      }, 5000);
    });

    // Add ripple effect to primary button
    document.querySelector('.btn-primary').addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const x = e.clientX - rect.left - size / 2;
      const y = e.clientY - rect.top - size / 2;
      
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = x + 'px';
      ripple.style.top = y + 'px';
      ripple.classList.add('ripple');
      
      this.appendChild(ripple);
      
      setTimeout(() => {
        ripple.remove();
      }, 600);
    });

    
  </script>
</body>
</html>