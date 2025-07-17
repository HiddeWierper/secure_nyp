<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
  header('Location: ' . url('/login'));
    exit;
}
// In your login handler, after successful login for regiomanagers:


$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Vul e-mail en wachtwoord in.';
    } else {
        try {
            // Aangepast database pad
            $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT id, password_hash, role, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Ongeldige e-mail of wachtwoord.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];

                header('Location:' .url( '/'));
                exit;
            }
        } catch (Exception $e) {
            $error = 'Serverfout: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inloggen - New York Pizza Taakbeheer</title>
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
    
    .login-card {
      backdrop-filter: blur(20px);
      background: rgba(255, 255, 255, 0.95);
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 1px solid rgba(255, 255, 255, 0.2);
      position: relative;
      z-index: 10;
      animation: slideUp 0.8s ease-out;
    }
    
    .login-card::before {
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
    
    .error-alert {
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
      .login-card {
        margin: 1rem;
        padding: 1.5rem;
      }
      
      .responsive-text-3xl {
        font-size: 1.875rem;
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
<body class="h-full flex items-center justify-center p-4">
  <div class="login-card rounded-2xl p-6 sm:p-8 max-w-md w-full">
    <!-- Logo/Header Section -->
    <div class="text-center mb-8">
      <div class="logo-container w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center">
        <i class="fas fa-pizza-slice text-white text-2xl"></i>
      </div>
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 mb-2 responsive-text-3xl">Welkom terug</h1>
      <p class="text-gray-600 text-sm sm:text-base">Log in om door te gaan naar je dashboard</p>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
    <div class="error-alert px-4 py-3 rounded-xl mb-6" role="alert">
      <div class="flex items-center">
        <i class="fas fa-exclamation-circle mr-3 text-red-600"></i>
        <span class="font-medium"><?php echo htmlspecialchars($error); ?></span>
      </div>
    </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="post" action="<?= url('/login') ?>" class="space-y-6" id="loginForm">
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
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 placeholder="jouw@email.nl"
                 class="input-field w-full px-4 py-3 pl-12 rounded-xl focus:outline-none text-sm sm:text-base" />
          <i class="fas fa-envelope absolute left-4 top-1/2 transform -translate-y-1/2 text-green-600 opacity-70 input-icon"></i>
        </div>
      </div>

      <div class="form-group">
        <label for="password" class="block text-gray-700 font-semibold mb-3 text-sm sm:text-base">
          <i class="fas fa-lock mr-2 text-green-600"></i>
          Wachtwoord
        </label>
        <div class="relative">
          <input type="password" 
                 id="password" 
                 name="password" 
                 required
                 placeholder="••••••••"
                 class="input-field w-full px-4 py-3 pl-12 pr-12 rounded-xl focus:outline-none text-sm sm:text-base" />
          <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-green-600 opacity-70 input-icon"></i>
          <button type="button" 
                  id="togglePassword"
                  class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-green-600 transition-colors">
            <i class="fas fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <button type="submit"
                id="submitBtn"
                class="btn-primary w-full text-white font-bold py-3.5 rounded-xl text-sm sm:text-lg shadow-lg">
          <i class="fas fa-sign-in-alt mr-2"></i>
          Inloggen
        </button>
      </div>

      <div class="form-group text-center pt-4">
        <a href="/forgot-password" 
           class="text-green-700 hover:text-green-800 font-medium text-sm hover:underline transition-colors">
          <i class="fas fa-key mr-1"></i>
          Wachtwoord vergeten?
        </a>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <div class="fixed bottom-4 left-1/2 transform -translate-x-1/2 z-20">
    <p class="text-white/80 text-xs text-center">
      <i class="fas fa-shield-alt mr-1"></i>
      Beveiligd door New York Pizza Taakbeheer
    </p>
  </div>

  <script>
    // Password toggle functionality
    document.getElementById('togglePassword').addEventListener('click', function() {
      const passwordField = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      }
    });

    // Form submission with loading state
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const submitBtn = document.getElementById('submitBtn');
      const originalText = submitBtn.innerHTML;
      
      // Show loading state
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Inloggen...';
      submitBtn.classList.add('btn-loading');
      
      // Reset after 3 seconds (in case of redirect issues)
      setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.classList.remove('btn-loading');
      }, 3000);
    });

    // Enhanced input field interactions
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

    // Add ripple effect to button
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