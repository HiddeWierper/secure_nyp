<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$message = '';
$showForm = false;

$token = $_GET['token'] ?? '';

if (!$token) {
    $error = 'Ongeldige of ontbrekende token.';
} else {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check token en expiry
        $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > ?");
        $stmt->execute([$token, time()]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Ongeldige of verlopen token.';
        } else {
            $showForm = true;

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $password_confirm = $_POST['password_confirm'] ?? '';

                if (!$password || !$password_confirm) {
                    $error = 'Vul beide wachtwoordvelden in.';
                } elseif ($password !== $password_confirm) {
                    $error = 'Wachtwoorden komen niet overeen.';
                } elseif (strlen($password) < 6) {
                    $error = 'Wachtwoord moet minimaal 6 tekens zijn.';
                } else {
                    // Hash en update wachtwoord
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
                    $stmt->execute([$password_hash, $user['id']]);

                    $message = 'Wachtwoord succesvol gewijzigd. Je kunt nu inloggen.';
                    $showForm = false;
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Serverfout: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <title>Wachtwoord resetten - New York Pizza</title>
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
    .form-group:nth-child(5) { animation-delay: 0.5s; }
    
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
    
    /* Password strength indicator */
    .password-strength {
      height: 4px;
      background: #e5e7eb;
      border-radius: 2px;
      margin-top: 8px;
      overflow: hidden;
    }
    
    .password-strength-bar {
      height: 100%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }
    
    .strength-weak { background: #ef4444; width: 25%; }
    .strength-fair { background: #f59e0b; width: 50%; }
    .strength-good { background: #10b981; width: 75%; }
    .strength-strong { background: #059669; width: 100%; }
    
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
    
    .success-checkmark {
      animation: checkmark 0.6s ease-in-out;
    }
    
    @keyframes checkmark {
      0% { transform: scale(0) rotate(45deg); }
      50% { transform: scale(1.2) rotate(45deg); }
      100% { transform: scale(1) rotate(45deg); }
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="glass-card rounded-2xl p-6 sm:p-8">
      <!-- Logo/Header -->
      <div class="text-center mb-8">
        <div class="logo-container w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4">
          <i class="fas fa-lock text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2 responsive-text-2xl">
          Wachtwoord resetten
        </h1>
        <p class="text-gray-600 text-sm sm:text-base">
          Voer je nieuwe wachtwoord in
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
            <i class="fas fa-check-circle mr-3 text-green-600 success-checkmark"></i>
            <span class="font-medium"><?=htmlspecialchars($message)?></span>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($showForm): ?>
        <!-- Form -->
        <form method="post" action="" class="space-y-6" id="resetForm">
          <div class="form-group">
            <label for="password" class="block text-gray-700 font-semibold mb-3 text-sm sm:text-base">
              <i class="fas fa-key mr-2 text-green-600"></i>
              Nieuw wachtwoord
            </label>
            <div class="relative">
              <input type="password" 
                     id="password" 
                     name="password" 
                     required 
                     minlength="6"
                     placeholder="Minimaal 6 tekens"
                     class="input-field w-full px-4 py-3 rounded-xl focus:outline-none text-sm sm:text-base pl-12 pr-12" />
              <i class="fas fa-key absolute left-4 top-1/2 transform -translate-y-1/2 text-green-600 opacity-70 input-icon"></i>
              <button type="button" 
                      id="togglePassword1"
                      class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-green-600 transition-colors">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <div class="password-strength">
              <div class="password-strength-bar" id="strengthBar"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2" id="strengthText">Voer een wachtwoord in</p>
          </div>

          <div class="form-group">
            <label for="password_confirm" class="block text-gray-700 font-semibold mb-3 text-sm sm:text-base">
              <i class="fas fa-check-double mr-2 text-green-600"></i>
              Bevestig nieuw wachtwoord
            </label>
            <div class="relative">
              <input type="password" 
                     id="password_confirm" 
                     name="password_confirm" 
                     required 
                     minlength="6"
                     placeholder="Herhaal je wachtwoord"
                     class="input-field w-full px-4 py-3 rounded-xl focus:outline-none text-sm sm:text-base pl-12 pr-12" />
              <i class="fas fa-check-double absolute left-4 top-1/2 transform -translate-y-1/2 text-green-600 opacity-70 input-icon"></i>
              <button type="button" 
                      id="togglePassword2"
                      class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-green-600 transition-colors">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <p class="text-xs text-gray-500 mt-2" id="matchText"></p>
          </div>

          <div class="form-group">
            <button type="submit"
                    id="submitBtn"
                    class="btn-primary w-full text-white font-semibold py-3 px-6 rounded-xl text-sm sm:text-base">
              <i class="fas fa-save mr-2"></i>
              Wachtwoord wijzigen
            </button>
          </div>
        </form>
      <?php else: ?>
        <!-- Success state or back to login -->
        <div class="form-group text-center">
          <?php if ($message): ?>
            <div class="mb-6">
              <div class="w-20 h-20 mx-auto bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-check text-green-600 text-3xl success-checkmark"></i>
              </div>
              <p class="text-gray-600 mb-6">Je kunt nu inloggen met je nieuwe wachtwoord</p>
            </div>
          <?php endif; ?>
          
          <a href="/login" 
             class="btn-primary inline-flex items-center px-8 py-3 rounded-xl font-medium text-sm transition-all hover:no-underline text-white">
            <i class="fas fa-sign-in-alt mr-2"></i>
            Naar inloggen
          </a>
        </div>
      <?php endif; ?>
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
    // Password visibility toggles
    function setupPasswordToggle(inputId, toggleId) {
      const input = document.getElementById(inputId);
      const toggle = document.getElementById(toggleId);
      
      toggle.addEventListener('click', function() {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      });
    }
    
    setupPasswordToggle('password', 'togglePassword1');
    setupPasswordToggle('password_confirm', 'togglePassword2');

    // Password strength checker
    function checkPasswordStrength(password) {
      let strength = 0;
      let text = '';
      
      if (password.length >= 6) strength++;
      if (password.match(/[a-z]/)) strength++;
      if (password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;
      
      const strengthBar = document.getElementById('strengthBar');
      const strengthText = document.getElementById('strengthText');
      
      strengthBar.className = 'password-strength-bar';
      
      switch(strength) {
        case 0:
        case 1:
          strengthBar.classList.add('strength-weak');
          text = 'Zwak wachtwoord';
          break;
        case 2:
          strengthBar.classList.add('strength-fair');
          text = 'Redelijk wachtwoord';
          break;
        case 3:
        case 4:
          strengthBar.classList.add('strength-good');
          text = 'Goed wachtwoord';
          break;
        case 5:
          strengthBar.classList.add('strength-strong');
          text = 'Sterk wachtwoord';
          break;
      }
      
      strengthText.textContent = text;
      return strength;
    }

    // Password confirmation checker
    function checkPasswordMatch() {
      const password = document.getElementById('password').value;
      const confirm = document.getElementById('password_confirm').value;
      const matchText = document.getElementById('matchText');
      
      if (confirm === '') {
        matchText.textContent = '';
        return;
      }
      
      if (password === confirm) {
        matchText.textContent = '✓ Wachtwoorden komen overeen';
        matchText.className = 'text-xs text-green-600 mt-2';
      } else {
        matchText.textContent = '✗ Wachtwoorden komen niet overeen';
        matchText.className = 'text-xs text-red-600 mt-2';
      }
    }

    // Event listeners
    document.getElementById('password').addEventListener('input', function() {
      checkPasswordStrength(this.value);
      checkPasswordMatch();
    });

    document.getElementById('password_confirm').addEventListener('input', checkPasswordMatch);

    // Form submission handling
    document.getElementById('resetForm')?.addEventListener('submit', function(e) {
      const submitBtn = document.getElementById('submitBtn');
      const originalText = submitBtn.innerHTML;
      
      // Show loading state
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Wijzigen...';
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
    });

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('[role="alert"]').forEach(alert => {
      setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.style.display = 'none', 300);
      }, 5000);
    });

    // Add ripple effect to primary button
    document.querySelectorAll('.btn-primary').forEach(btn => {
      btn.addEventListener('click', function(e) {
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
    });
  </script>
</body>
</html>