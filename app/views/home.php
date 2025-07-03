 <?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$userRole = $_SESSION['user_role'];
$username = $_SESSION['username'];
$isManager = ($userRole === 'manager');
$isRegiomanager = ($userRole === 'regiomanager');
$isAdmin = ($userRole === 'admin');

// NIEUWE PERMISSIES - Regiomanagers kunnen meer!
$canGenerate = ($isAdmin || $isRegiomanager); // Admin + Regiomanager
$canTrack = true; // Iedereen kan taken bijhouden
$canManage = ($isAdmin || $isRegiomanager); // Admin + Regiomanager
$canViewDashboard = ($isAdmin); // Alleen admin heeft algemeen dashboard
$canViewRegioDashboard = ($isRegiomanager); // Alleen regiomanager heeft regio dashboard
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <title>Schoonmaak Systeem <?=htmlspecialchars($userRole)?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <script defer src="js/script.js"></script>

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
    
    .nav-btn.active {
      background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
      color: white !important;
      box-shadow: 0 4px 15px -3px rgba(5, 150, 105, 0.4);
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
    
    .task-card {
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
    }
    
    .task-card:hover {
      background: rgba(255, 255, 255, 1);
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .frequency-card {
      border-left: 4px solid;
    }
    
    .frequency-daily { border-left-color: #f59e0b; background: rgba(251, 191, 36, 0.1); }
    .frequency-weekly { border-left-color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
    .frequency-biweekly { border-left-color: #8b5cf6; background: rgba(139, 92, 246, 0.1); }
    .frequency-monthly { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.1); }
    
    /* Role indicator styles */
    .role-admin { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); }
    .role-manager { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
    .role-regiomanager { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }
    
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
  </style>
</head>

<body class="min-h-screen ">
  <div class="container w-full mx-[auto] px-2 sm:px-4 py-4 sm:py-8">
    <!-- Header -->
    <div class="glass-card rounded-2xl p-4 sm:p-6 mb-4 sm:mb-8 relative">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div class="mb-4 sm:mb-0">
          <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800 mb-2 flex items-center responsive-text-3xl">
            <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-green-700 rounded-xl mr-2 sm:mr-3 flex items-center justify-center">
              <i class="fas fa-tasks text-white text-lg sm:text-xl lg:text-2xl"></i>
            </div>
            <span class="hidden sm:inline">Taakbeheer Systeem</span>
            <span class="sm:hidden">Taakbeheer</span>
          </h1>
          <p class="text-sm sm:text-base text-gray-600 ml-12 sm:ml-13">
            <?php if ($isAdmin): ?>
              Genereer en beheer dagelijkse taken voor je team
            <?php elseif ($isRegiomanager): ?>
              Beheer taken en winkels voor je regio
            <?php else: ?>
              Volg en beheer je dagelijkse taken
            <?php endif; ?>
          </p>
        </div>

        <!-- Gebruikersinfo -->
        <div class="flex flex-col items-center cursor-pointer select-none">
          <div class="w-12 h-12 rounded-full flex items-center justify-center mb-2 role-<?= $userRole ?>">
            <i class="fas fa-user text-white text-lg"></i>
          </div>
          <span class="text-xs text-gray-700 font-medium"><?= htmlspecialchars($username) ?></span>
          <span class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($userRole) ?></span>
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

    <!-- Navigation -->
    <div id="navigation-menu" class="mb-4 sm:mb-6">
      <!-- Desktop Navigation -->
      <div class="hidden sm:flex flex-wrap items-center gap-2 lg:gap-4">
        <?php if ($canGenerate): ?>
        <button onclick="showPage('generator')" id="btn-generator" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-magic mr-1 lg:mr-2"></i> 
          <span class="hidden md:inline">Taken Genereren</span>
          <span class="md:hidden">Genereren</span>
        </button>
        <?php endif; ?>

        <?php if ($canTrack): ?>
        <button onclick="showPage('track')" id="btn-track" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-clipboard-check mr-1 lg:mr-2"></i> 
          <span class="hidden md:inline">Taken Bijhouden</span>
          <span class="md:hidden">Bijhouden</span>
        </button>
        <?php endif; ?>

        <?php if ($canManage): ?>
        <button onclick="showPage('manage')" id="btn-manage" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-cog mr-1 lg:mr-2"></i> 
          <span class="hidden md:inline">Taken Beheren</span>
          <span class="md:hidden">Beheren</span>
        </button>
        <?php endif; ?>

        <?php if ($canViewRegioDashboard): ?>
        <button onclick="showPage('regio-dashboard')" id="btn-regio-dashboard" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-chart-line mr-1 lg:mr-2"></i> 
          <span class="hidden md:inline">Regio Dashboard</span>
          <span class="md:hidden">Dashboard</span>
        </button>
        <?php endif; ?>

        <?php if ($isRegiomanager): ?>
        <button onclick="showPage('region')" id="btn-region" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-map-marked-alt mr-1 lg:mr-2"></i> 
          <span class="hidden md:inline">Regio Overzicht</span>
          <span class="md:hidden">Regio</span>
        </button>
        <?php endif; ?>

        <?php if ($canViewDashboard): ?>
        <a href="/dashboard" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-tachometer-alt mr-1 lg:mr-2"></i> 
          <span class="hidden lg:inline">Admin Dashboard</span>
          <span class="lg:hidden">Admin</span>
        </a>
        <?php endif; ?>

        <a href="/logout" class="ml-auto bg-gradient-to-r from-red-500 to-red-600 text-white px-3 lg:px-4 py-2 lg:py-3 rounded-xl font-medium hover:from-red-600 hover:to-red-700 transition-all flex items-center text-sm lg:text-base transform hover:-translate-y-1" title="Uitloggen">
          <i class="fas fa-sign-out-alt mr-1 lg:mr-2"></i> 
          <span class="hidden sm:inline">Uitloggen</span>
        </a>
      </div>

      <!-- Mobile Navigation -->
      <div id="mobile-nav" class="sm:hidden mobile-menu-hidden fixed inset-0 bg-black bg-opacity-50 z-50 transition-transform duration-300">
        <div class="glass-card w-64 h-full shadow-lg">
          <div class="p-4 border-b border-white/20">
            <div class="flex items-center justify-between">
              <h3 class="font-semibold text-gray-800">Menu</h3>
              <button id="mobile-menu-close" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>
          </div>
          
          <div class="p-4 space-y-2">
            <?php if ($canGenerate): ?>
            <button onclick="showPage('generator'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-magic mr-3 text-green-600"></i> Taken Genereren
            </button>
            <?php endif; ?>

            <?php if ($canTrack): ?>
            <button onclick="showPage('track'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-clipboard-check mr-3 text-green-600"></i> Taken Bijhouden
            </button>
            <?php endif; ?>

            <?php if ($canManage): ?>
            <button onclick="showPage('manage'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-cog mr-3 text-green-600"></i> Taken Beheren
            </button>
            <?php endif; ?>

            <?php if ($canViewRegioDashboard): ?>
            <button onclick="showPage('regio-dashboard'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-chart-line mr-3 text-green-600"></i> Regio Dashboard
            </button>
            <?php endif; ?>

            <?php if ($isRegiomanager): ?>
            <button onclick="showPage('region'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-map-marked-alt mr-3 text-green-600"></i> Regio Overzicht
            </button>
            <?php endif; ?>

            <?php if ($canViewDashboard): ?>
            <a href="/dashboard" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-tachometer-alt mr-3 text-green-600"></i> Admin Dashboard
            </a>
            <?php endif; ?>

            <div class="border-t border-white/20 pt-2 mt-4">
              <a href="/logout" class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 text-red-600 flex items-center">
                <i class="fas fa-sign-out-alt mr-3"></i> Uitloggen
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pages -->
    <!-- Taken Genereren -->
    <?php if ($canGenerate): ?>
    <div id="page-generator" class="space-y-4 sm:space-y-6">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-calendar-day text-white text-sm"></i>
          </div>
          Taken Genereren
          <?php if ($isRegiomanager): ?>
          <span class="ml-2 text-sm bg-purple-100 text-purple-800 px-2 py-1 rounded-full">Voor je regio</span>
          <?php endif; ?>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-4 sm:mb-6">
          <div>
            <label for="storeSelect" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-store mr-2 text-green-600"></i> Winkel
            </label>
            <select id="storeSelect" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
              <option value="">Selecteer winkel</option>
            </select>
          </div>

          <div>
            <label for="managerSelect" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-user-tie mr-2 text-green-600"></i> Manager
            </label>
            <select id="managerSelect" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base" disabled>
              <option value="">Selecteer winkel eerst</option>
            </select>
          </div>

          <div class="sm:col-span-2 lg:col-span-1">
            <label for="day" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-calendar mr-2 text-green-600"></i> Dag
            </label>
            <select id="day" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
              <option>Maandag</option>
              <option>Dinsdag</option>
              <option>Woensdag</option>
              <option>Donderdag</option>
              <option>Vrijdag</option>
              <option>Zaterdag</option>
              <option>Zondag</option>
            </select>
          </div>
        </div>

        <div class="mb-4 sm:mb-6">
          <label for="max-duration" class="block text-sm font-medium text-gray-700 mb-2">
            <i class="fas fa-hourglass-half mr-2 text-green-600"></i> Maximale duur (minuten)
          </label>
          <select id="max-duration" class="input-field w-full sm:w-auto p-3 rounded-xl focus:outline-none text-sm sm:text-base">
            <option value="30">30 minuten</option>
            <option value="45">45 minuten</option>
            <option value="60">1 uur</option>
            <option value="75">1 uur 15 min</option>
            <option value="90" selected>1,5 uur</option>
          </select>
        </div>

        <button id="generateBtn" class="w-full sm:w-auto btn-secondary text-white px-6 sm:px-8 py-3 rounded-xl font-medium">
          <i class="fas fa-play mr-2"></i> Genereer Taken
        </button>
      </div>

      <!-- Generated Tasks Container -->
      <div id="generated-tasks" class="glass-card rounded-2xl p-4 sm:p-6 hidden">
        <div class="text-center text-gray-500 py-8">
          <div class="w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl mx-auto mb-4 flex items-center justify-center">
            <i class="fas fa-clipboard text-2xl text-gray-400"></i>
          </div>
          <p class="font-medium">Geen taken gegenereerd</p>
          <p class="text-sm">Vul de gegevens in en klik op "Genereer Taken"</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Regio Dashboard (alleen voor regiomanagers) -->
    <?php if ($canViewRegioDashboard): ?>
    <div id="page-regio-dashboard" class="space-y-4 sm:space-y-6 hidden">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-chart-line text-white text-sm"></i>
          </div>
          Regio Dashboard
        </h2>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border-l-4 border-blue-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-blue-600 font-medium">Totaal Winkels</p>
                <p id="dashboard-total-stores" class="text-2xl font-bold text-blue-800">-</p>
              </div>
              <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-store text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border-l-4 border-green-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-green-600 font-medium">Actieve Taken</p>
                <p id="dashboard-active-tasks" class="text-2xl font-bold text-green-800">-</p>
              </div>
              <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-tasks text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 border-l-4 border-yellow-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-yellow-600 font-medium">Voltooiingspercentage</p>
                <p id="dashboard-completion-rate" class="text-2xl font-bold text-yellow-800">-%</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-pie text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border-l-4 border-purple-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-purple-600 font-medium">Managers</p>
                <p id="dashboard-total-managers" class="text-2xl font-bold text-purple-800">-</p>
              </div>
              <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-white text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">Taken per Winkel</h3>
            <div id="tasks-per-store-chart" class="h-64">
              <p class="text-gray-500 text-center pt-20">Chart wordt geladen...</p>
            </div>
          </div>

          <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">Voltooiing Trend</h3>
            <div id="completion-trend-chart" class="h-64">
              <p class="text-gray-500 text-center pt-20">Chart wordt geladen...</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Regio Overzicht (alleen voor regiomanagers) -->
    <?php if ($isRegiomanager): ?>
    <div id="page-region" class="space-y-4 sm:space-y-6 hidden">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-map-marked-alt text-white text-sm"></i>
          </div>
          Regio Overzicht
        </h2>

        <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-2xl p-4 sm:p-6 mb-6 border-l-4 border-purple-400">
          <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 responsive-text-lg flex items-center">
            <div class="w-6 h-6 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg mr-2 flex items-center justify-center">
              <i class="fas fa-info-circle text-white text-xs"></i>
            </div>
            Jouw Regio Winkels
          </h3>
          
          <div id="region-stores-list" class="space-y-3">
            <p class="text-gray-600">Regio winkels laden...</p>
          </div>
        </div>

        <!-- Regio Statistieken -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border-l-4 border-blue-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-blue-600 font-medium">Totaal Winkels</p>
                <p id="total-stores" class="text-2xl font-bold text-blue-800">-</p>
              </div>
              <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-store text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border-l-4 border-green-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-green-600 font-medium">Actieve Taken</p>
                <p id="active-tasks" class="text-2xl font-bold text-green-800">-</p>
              </div>
              <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-tasks text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 border-l-4 border-yellow-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-yellow-600 font-medium">Voltooiingspercentage</p>
                <p id="completion-rate" class="text-2xl font-bold text-yellow-800">-%</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-pie text-white text-xl"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Taken Bijhouden -->
    <?php if ($canTrack): ?>
    <div id="page-track" class="glass-card rounded-2xl p-4 sm:p-6 mt-4 sm:mt-6 hidden">
      <div class="mb-4 sm:mb-6 text-sm text-gray-700 bg-gradient-to-r from-blue-50 to-blue-100 p-3 sm:p-4 rounded-xl border-l-4 border-blue-400">
        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
        <strong>Instructies:</strong> 
        <?php if ($isRegiomanager): ?>
          Hier kun je de taken van alle winkels in jouw regio bijhouden en controleren.
        <?php else: ?>
          Hier kunnen managers de uitgevoerde taken afvinken. Klik op een dag om de taken uit te vouwen.
        <?php endif; ?>
      </div>

      <!-- Search Section -->
      <?php if (!$isManager): ?>
      <div class="mb-4 sm:mb-6 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-3 sm:p-4">
        <div class="space-y-4">
          <!-- Search Input -->
          <div>
            <label for="searchInput" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-search mr-2 text-green-600"></i>Zoeken in taken
            </label>
            <input type="text" 
                   id="searchInput" 
                   placeholder="Zoek op manager, dag, of taak naam..." 
                   class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
          </div>
          
          <!-- Filters Row -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Store Filter -->
            <div class="sm:col-span-1">
              <label for="storeFilter" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-store mr-2 text-green-600"></i>Winkel
              </label>
              <select id="storeFilter" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                <option value="">Alle winkels</option>
              </select>
            </div>
            
            <!-- Status Filter -->
            <div class="sm:col-span-1">
              <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-filter mr-2 text-green-600"></i>Status
              </label>
              <select id="statusFilter" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                <option value="">Alle statussen</option>
                <option value="submitted">Ingediend</option>
                <option value="pending">Nog niet ingediend</option>
              </select>
            </div>
            
            <!-- Clear Button -->
            <div class="sm:col-span-2 lg:col-span-2 flex items-end">
              <button id="clearFilters" class="w-full bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-3 rounded-xl font-medium hover:from-gray-600 hover:to-gray-700 transition-all text-sm sm:text-base transform hover:-translate-y-1">
                <i class="fas fa-times mr-2"></i>Reset Filters
              </button>
            </div>
          </div>
        </div>
        
        <!-- Search Results Info -->
        <div id="searchInfo" class="mt-3 text-sm text-gray-600 hidden">
          <i class="fas fa-info-circle mr-1"></i>
          <span id="searchResultsCount">0</span> resultaten gevonden
        </div>
      </div>
      <?php endif; ?>

      <!-- Task tracking list -->
      <div id="task-tracking-list" class="space-y-4">
        <div class="text-center text-gray-500 py-8">
          <div class="w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl mx-auto mb-4 flex items-center justify-center">
            <i class="fas fa-clipboard-list text-2xl text-gray-400"></i>
          </div>
          <p class="font-medium">Geen taken om bij te houden</p>
          <p class="text-sm">Genereer eerst taken en sla ze op voor bijhouden</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Taken Beheren -->
    <?php if ($canManage): ?>
    <div id="page-manage" class="space-y-4 sm:space-y-6 hidden">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-cog text-white text-sm"></i>
          </div>
          Taken Beheren
          <?php if ($isRegiomanager): ?>
          <span class="ml-2 text-sm bg-purple-100 text-purple-800 px-2 py-1 rounded-full">Voor je regio</span>
          <?php endif; ?>
        </h2>

        <!-- Add Task Form -->
        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-4 sm:p-6 mb-6 sm:mb-8 border-l-4 border-blue-400">
          <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 responsive-text-lg flex items-center">
            <div class="w-6 h-6 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 flex items-center justify-center">
              <i class="fas fa-plus text-white text-xs"></i>
            </div>
            Nieuwe Taak Toevoegen
          </h3>

          <div class="space-y-4">
            <!-- Task Description -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-clipboard-list mr-2 text-green-600"></i> Taak Beschrijving
              </label>
              <input type="text" id="new-task" placeholder="Bijv. Schoonmaken bovenkant makeline" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base" />
            </div>

            <!-- Time and Frequency Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-clock mr-2 text-green-600"></i> Tijd (minuten)
                </label>
                <input type="number" id="task-time" min="5" max="120" step="5" value="5" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base" />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fas fa-repeat mr-2 text-green-600"></i> Frequentie
                </label>
                <select id="task-frequency" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                  <option value="dagelijks">Dagelijks</option>
                  <option value="wekelijks">Wekelijks</option>
                  <option value="2-wekelijks">2-wekelijks</option>
                  <option value="maandelijks">Maandelijks</option>
                </select>
              </div>
            </div>

            <!-- Required Checkbox -->
            <div class="mb-4">
              <div class="flex items-start">
                <input type="checkbox" id="task-required" class="mr-2 h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-1" />
                <div>
                  <label for="task-required" class="text-sm text-gray-700">
                    <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i> Verplicht elke dag toevoegen
                  </label>
                  <p class="text-xs text-gray-500 mt-1">Deze taak wordt altijd toegevoegd aan de gegenereerde lijst</p>
                </div>
              </div>
            </div>

            <button id="addTaskBtn" class="w-full sm:w-auto btn-secondary text-white px-6 py-3 rounded-xl font-medium text-sm sm:text-base">
              <i class="fas fa-plus mr-2"></i> Taak Toevoegen
            </button>
          </div>
        </div>

        <!-- Task Lists by Frequency -->
        <div class="space-y-4 sm:space-y-6">
          <div class="task-card frequency-card frequency-daily rounded-2xl p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 flex flex-wrap items-center responsive-text-lg">
              <i class="fas fa-sun text-yellow-500 mr-2"></i> 
              <span class="mr-2">Dagelijkse Taken</span>
              <span id="daily-count" class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">0</span>
            </h3>
            <div id="daily-tasks" class="space-y-2"></div>
          </div>

          <div class="task-card frequency-card frequency-weekly rounded-2xl p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 flex flex-wrap items-center responsive-text-lg">
              <i class="fas fa-calendar-week text-blue-500 mr-2"></i> 
              <span class="mr-2">Wekelijkse Taken</span>
              <span id="weekly-count" class="bg-blue-200 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">0</span>
            </h3>
            <div id="weekly-tasks" class="space-y-2"></div>
          </div>

          <div class="task-card frequency-card frequency-biweekly rounded-2xl p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 flex flex-wrap items-center responsive-text-lg">
              <i class="fas fa-calendar-alt text-purple-500 mr-2"></i> 
              <span class="mr-2">2-wekelijkse Taken</span>
              <span id="biweekly-count" class="bg-purple-200 text-purple-800 px-3 py-1 rounded-full text-sm font-medium">0</span>
            </h3>
            <div id="biweekly-tasks" class="space-y-2"></div>
          </div>

          <div class="task-card frequency-card frequency-monthly rounded-2xl p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 flex flex-wrap items-center responsive-text-lg">
              <i class="fas fa-calendar text-red-500 mr-2"></i> 
              <span class="mr-2">Maandelijkse Taken</span>
              <span id="monthly-count" class="bg-red-200 text-red-800 px-3 py-1 rounded-full text-sm font-medium">0</span>
            </h3>
            <div id="monthly-tasks" class="space-y-2"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

<script>
  const isManager = <?= json_encode($isManager) ?>;
  const isRegiomanager = <?= json_encode($isRegiomanager) ?>;
  const isAdmin = <?= json_encode($isAdmin) ?>;
  const userRole = '<?= $userRole ?>';

  // Mobile menu functionality
  function toggleMobileMenu() {
    const mobileNav = document.getElementById('mobile-nav');
    mobileNav.classList.toggle('mobile-menu-hidden');
  }

  function closeMobileMenu() {
    const mobileNav = document.getElementById('mobile-nav');
    mobileNav.classList.add('mobile-menu-hidden');
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

  // Verbeterde showPage functie
  function showPage(pageId) {
    // Close mobile menu when navigating
    closeMobileMenu();
    
    // Hide all pages
    ['page-generator', 'page-track', 'page-manage', 'page-region', 'page-regio-dashboard'].forEach(id => {
      const page = document.getElementById(id);
      if (page) page.classList.add('hidden');
    });
    
    // Show selected page
    const page = document.getElementById(`page-${pageId}`);
    if (page) {
      page.classList.remove('hidden');
    }

    // Update desktop navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.classList.remove('active');
    });

    const activeBtn = document.getElementById(`btn-${pageId}`);
    if (activeBtn) {
      activeBtn.classList.add('active');
    }

    // Update mobile navigation
    document.querySelectorAll('.mobile-nav-btn').forEach(btn => {
      btn.classList.remove('bg-green-50', 'text-green-600');
    });

    // Load specific page data AFTER page is shown
    if (pageId === 'region' && isRegiomanager) {
      setTimeout(() => {
        loadRegionStores();
      }, 100);
    }
    
    if (pageId === 'regio-dashboard' && isRegiomanager) {
      setTimeout(() => {
        loadRegioDashboard();
      }, 100);
    }
    
    if (pageId === 'track') {
      setTimeout(() => {
        loadTaskSetsAndStores();
      }, 100);
    }
  }

  // Placeholder functie voor regio dashboard
  async function loadRegioDashboard() {
    console.log('Loading regio dashboard...');
    // Hier komt de dashboard data loading logica
  }

  // Verbeterde DOMContentLoaded event
  document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, user role:', userRole);
    
    // Wacht tot alle elementen geladen zijn
    setTimeout(() => {
      // Show appropriate default page based on role
      if (isRegiomanager) {
        showPage('regio-dashboard'); // Start met dashboard voor regiomanagers
      } else if (isManager) {
        showPage('track');
      } else if (isAdmin) {
        showPage('generator');
      } else {
        showPage('track');
      }
    }, 200);
  });
</script>

</body>
</html>