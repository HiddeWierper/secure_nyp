<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle developer panel AJAX requests
if (isset($_POST['dev_action']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'developer') {
    header('Content-Type: application/json');
    
    switch ($_POST['dev_action']) {
        case 'clear_cache':
            // Clear any cache files or session cache
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            // Clear session cache if needed
            session_regenerate_id(true);
            echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
            exit;
            
        case 'get_logs':
            $logFile = __DIR__ . '/../logs/error.log';
            if (file_exists($logFile)) {
                $logs = array_slice(file($logFile), -50); // Last 50 lines
                echo json_encode(['success' => true, 'logs' => $logs]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Log file not found']);
            }
            exit;
            
        case 'get_db_info':
            try {
                $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
                $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                $info = [
                    'database_path' => __DIR__ . '/../db/tasks.db',
                    'tables' => $tables,
                    'database_size' => filesize(__DIR__ . '/../db/tasks.db')
                ];
                echo json_encode(['success' => true, 'info' => $info]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'execute_query':
            if (!isset($_POST['query'])) {
                echo json_encode(['success' => false, 'message' => 'No query provided']);
                exit;
            }
            
            try {
                $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
                $query = trim($_POST['query']);
                
                // Only allow SELECT queries for safety
                if (!preg_match('/^SELECT/i', $query)) {
                    echo json_encode(['success' => false, 'message' => 'Only SELECT queries are allowed']);
                    exit;
                }
                
                $stmt = $db->query($query);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'results' => $results]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location:' .url( '/landing'));
    exit;
}


$userRole = $_SESSION['user_role'];
$username = $_SESSION['username'];
$isManager = ($userRole === 'manager');
$isRegiomanager = ($userRole === 'regiomanager');
$isStoremanager = ($userRole === 'storemanager');
$isAdmin = ($userRole === 'admin');
$isDeveloper = ($userRole === 'developer');

// Set region_id for regiomanagers if not already set
if ($isRegiomanager && !isset($_SESSION['region_id'])) {
  try {
      $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');  // Gebruik __DIR__
      $stmt = $db->prepare("SELECT region_id FROM users WHERE username = ? AND role = 'regiomanager'");
      $stmt->execute([$username]);
      $regionId = $stmt->fetchColumn();
      if ($regionId) {
          $_SESSION['region_id'] = $regionId;
          echo "<script>console.log('Region ID set to: " . $regionId . "');</script>";
      } else {
          echo "<script>console.log('No region_id found for user: " . $username . "');</script>";
      }
  } catch (PDOException $e) {
      echo "<script>console.log('Database error: " . $e->getMessage() . "');</script>";
      error_log("Error setting region_id: " . $e->getMessage());
  }
}

// Set store_id for storemanagers if not already set
if ($isStoremanager && !isset($_SESSION['store_id'])) {
  try {
      $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');  // Gebruik __DIR__
      $stmt = $db->prepare("SELECT store_id FROM users WHERE username = ? AND role = 'storemanager'");
      $stmt->execute([$username]);
      $storeId = $stmt->fetchColumn();
      if ($storeId) {
          $_SESSION['store_id'] = $storeId;
          echo "<script>console.log('Store ID set to: " . $storeId . "');</script>";
      } else {
          echo "<script>console.log('No store_id found for user: " . $username . "');</script>";
      }
  } catch (PDOException $e) {
      echo "<script>console.log('Database error: " . $e->getMessage() . "');</script>";
      error_log("Error setting store_id: " . $e->getMessage());
  }
}

// Developer can override region_id and store_id
if ($isDeveloper) {
    // Allow developer to set any region_id or store_id for testing
    if (isset($_GET['dev_region_id'])) {
        $_SESSION['region_id'] = $_GET['dev_region_id'];
        echo "<script>console.log('Developer override: Region ID set to: " . $_GET['dev_region_id'] . "');</script>";
    }
    if (isset($_GET['dev_store_id'])) {
        $_SESSION['store_id'] = $_GET['dev_store_id'];
        echo "<script>console.log('Developer override: Store ID set to: " . $_GET['dev_store_id'] . "');</script>";
    }

    // Handle role override for developers
    if (isset($_GET['dev_role_override'])) {
        $_SESSION['dev_role_override'] = $_GET['dev_role_override'];
        echo "<script>console.log('Developer role override set to: " . $_GET['dev_role_override'] . "');</script>";
    }

    // Use overridden role if set
    if (isset($_SESSION['dev_role_override'])) {
        $userRole = $_SESSION['dev_role_override'];
        $isManager = ($userRole === 'manager');
        $isRegiomanager = ($userRole === 'regiomanager');
        $isStoremanager = ($userRole === 'storemanager');
        $isAdmin = ($userRole === 'admin');
        $isDeveloper = ($userRole === 'developer');

        // Recalculate permissions based on overridden role
        $canGenerate = ($isAdmin || $isRegiomanager || $isStoremanager || $isDeveloper);
        $canTrack = true;
        $canManage = ($isAdmin || $isRegiomanager || $isStoremanager || $isDeveloper);
        $canViewDashboard = ($isAdmin || $isRegiomanager || $isStoremanager || $isDeveloper);
        $canViewRegioDashboard = ($isRegiomanager || $isDeveloper);
    }
}

// Store manager restrictions - they can only access their own store
$storeRestriction = '';
if ($isStoremanager && isset($_SESSION['store_id'])) {
    $storeRestriction = $_SESSION['store_id'];
}
// NIEUWE PERMISSIES - Regiomanagers en Storemanagers kunnen meer!
$canGenerate = ($isAdmin || $isRegiomanager || $isStoremanager || $isDeveloper); // Admin + Regiomanager + Storemanager + Developer
$canTrack = true; // Iedereen kan taken bijhouden
$canManage = ($isAdmin || $isRegiomanager || $isStoremanager || $isDeveloper); // Admin + Regiomanager + Storemanager + Developer
$canViewDashboard = ($isAdmin || $isRegiomanager || $isStoremanager || $isDeveloper); // Admin + Regiomanager + Storemanager + Developer
$canViewRegioDashboard = ($isRegiomanager || $isDeveloper); // Regiomanager + Developer hebben regio dashboard
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8" />
  <title>Schoonmaak Systeem <?=htmlspecialchars($userRole)?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <meta name="description" content="NYP Schoonmaak - Professioneel schoonmaakbeheer platform voor efficiënt en betrouwbaar schoonmaakmanagement. Voor bedrijven en organisaties.">
  <meta name="keywords" content="schoonmaak, schoonmaakbeheer, schoonmaakmanagement, schoonmaakbedrijf, NYP Schoonmaak, professioneel schoonmaak, Hidde Wierper, New York Pizza, Dream Team Holding Bv, DTH">
  <meta name="author" content="Hidde Wierper">
  <meta name="robots" content="index, follow">

  <meta property="og:title" content="NYP Schoonmaak - Professioneel Schoonmaakbeheer">
  <meta property="og:description" content="Een efficiënt en betrouwbaar platform voor schoonmaakbeheer. Op maat gemaakt voor New York Pizza.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://nypschoonmaak.nl">
  <meta property="og:image" content="https://nypschoonmaak.nl/assets/logo.webp">
  <meta property="og:site_name" content="NYP Schoonmaak">

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="NYP Schoonmaak - Professioneel Schoonmaakbeheer">
  <meta name="twitter:description" content="Een efficiënt en betrouwbaar platform voor schoonmaakbeheer. Voor bedrijven en organisaties.">
  <meta name="twitter:image" content="https://nypschoonmaak.nl/assets/logo.webp">

  <link rel="icon" type="image/x-icon" href="https://nypschoonmaak.nl/assets/logo.webp">  
  
  <!-- Gebruikersrol variabelen definiëren EERST -->
  <script>
    const isManager = <?= json_encode($isManager) ?>;
    const isRegiomanager = <?= json_encode($isRegiomanager) ?>;
    const isStoremanager = <?= json_encode($isStoremanager) ?>;
    const isAdmin = <?= json_encode($isAdmin) ?>;
    const isDeveloper = <?= json_encode($isDeveloper) ?>;
    const userRole = '<?= $userRole ?>';
    const username = '<?= htmlspecialchars($username) ?>';
    const storeRestriction = '<?= $storeRestriction ?>';
  </script>

  <!-- Dan pas de externe scripts laden -->
  <script defer src="js/script.js"></script>
  <?php if ($isRegiomanager): ?>
  <script defer src="js/regiomanager.js"></script>
  <?php endif; ?>

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
    
    .role-admin { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); }
    .role-manager { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
    .role-regiomanager { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }
    .role-storemanager { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
    .role-developer { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    
    @media (max-width: 640px) {
      .responsive-text-lg { font-size: 1rem; }
      .responsive-text-xl { font-size: 1.125rem; }
      .responsive-text-2xl { font-size: 1.25rem; }
      .responsive-text-3xl { font-size: 1.5rem; }
    }
  </style>
</head>

<body class="min-h-screen">
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
            <?php elseif ($isStoremanager): ?>
              Beheer taken voor je winkel en managers
            <?php elseif ($isDeveloper): ?>
              Ontwikkelaar modus - Volledige toegang tot alle functies
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

        <?php if ($isRegiomanager || $isDeveloper): ?>
        <button onclick="showPage('region')" id="btn-region" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-map-marked-alt mr-1 lg:mr-2"></i>
          <span class="hidden md:inline">Regio Overzicht</span>
          <span class="md:hidden">Regio</span>
        </button>
        <?php endif; ?>

        <?php if ($isDeveloper): ?>
        <button onclick="showPage('developer')" id="btn-developer" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-code mr-1 lg:mr-2"></i>
          <span class="hidden md:inline">Developer Panel</span>
          <span class="md:hidden">Dev</span>
        </button>
        <?php endif; ?>

        <button onclick="showPage('completed')" id="btn-completed" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-check-circle mr-1 lg:mr-2"></i>
          <span class="hidden md:inline">Voltooide Taken</span>
          <span class="md:hidden">Voltooid</span>
        </button>

        <button onclick="showPage('feedback')" id="btn-feedback" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-comment-alt mr-1 lg:mr-2"></i>
          <span class="hidden md:inline">Feedback</span>
          <span class="md:hidden">Feedback</span>
        </button>

        <?php if ($canViewDashboard): ?>
        <a href="<?= url('/dashboard') ?>" class="nav-btn px-3 lg:px-6 py-2 lg:py-3 rounded-xl font-medium transition-colors flex items-center text-sm lg:text-base">
          <i class="fas fa-tachometer-alt mr-1 lg:mr-2"></i>
          <span class="hidden lg:inline">Managers Dashboard</span>
          <span class="lg:hidden">Dashboard</span>
        </a>
        <?php endif; ?>

        <a href="<?= url('/logout') ?>" class="ml-auto bg-gradient-to-r from-red-500 to-red-600 text-white px-3 lg:px-4 py-2 lg:py-3 rounded-xl font-medium hover:from-red-600 hover:to-red-700 transition-all flex items-center text-sm lg:text-base transform hover:-translate-y-1" title="Uitloggen">
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

            <?php if ($isRegiomanager || $isDeveloper): ?>
            <button onclick="showPage('region'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-map-marked-alt mr-3 text-green-600"></i> Regio Overzicht
            </button>
            <?php endif; ?>

            <?php if ($isDeveloper): ?>
            <button onclick="showPage('developer'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-code mr-3 text-green-600"></i> Developer Panel
            </button>
            <?php endif; ?>

            <button onclick="showPage('completed'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-check-circle mr-3 text-green-600"></i> Voltooide Taken
            </button>

            <button onclick="showPage('feedback'); closeMobileMenu();" class="mobile-nav-btn w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-comment-alt mr-3 text-green-600"></i> Feedback
            </button>

            <?php if ($canViewDashboard): ?>
            <a href="<?= url('/dashboard') ?>" class="w-full text-left px-4 py-3 rounded-xl hover:bg-green-50 flex items-center">
              <i class="fas fa-tachometer-alt mr-3 text-green-600"></i> Managers Dashboard
            </a>
            <?php endif; ?>

            <div class="border-t border-white/20 pt-2 mt-4">
              <a href="<?= url('/logout') ?>" class="w-full text-left px-4 py-3 rounded-xl hover:bg-red-50 text-red-600 flex items-center">
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
          <?php elseif ($isStoremanager): ?>
          <span class="ml-2 text-sm bg-green-100 text-green-800 px-2 py-1 rounded-full">Voor je winkel</span>
          <?php elseif ($isDeveloper): ?>
          <span class="ml-2 text-sm bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">Developer modus</span>
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
            <div class="h-64">
              <canvas id="tasksPerStoreChart"></canvas>
            </div>
          </div>

          <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">Voltooiing Trend</h3>
            <div class="h-64">
              <canvas id="completionTrendChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Developer Panel (alleen voor developers) -->
    <?php if ($isDeveloper): ?>
    <div id="page-developer" class="space-y-4 sm:space-y-6 hidden">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-code text-white text-sm"></i>
          </div>
          Developer Panel
        </h2>

        <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-2xl p-4 sm:p-6 mb-6 border-l-4 border-yellow-400">
          <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 responsive-text-lg flex items-center">
            <div class="w-6 h-6 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-lg mr-2 flex items-center justify-center">
              <i class="fas fa-tools text-white text-xs"></i>
            </div>
            Profile Override Settings
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div>
              <label for="dev-role-select" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-user-tag mr-2 text-yellow-600"></i> Override Role
              </label>
              <select id="dev-role-select" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="regiomanager">Regiomanager</option>
                <option value="storemanager">Storemanager</option>
                <option value="manager">Manager</option>
                <option value="developer">Developer (Original)</option>
              </select>
            </div>

            <div>
              <label for="dev-region-select" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-map-marked-alt mr-2 text-yellow-600"></i> Override Region ID
              </label>
              <select id="dev-region-select" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                <option value="">Select Region</option>
              </select>
            </div>

            <div>
              <label for="dev-store-select" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-store mr-2 text-yellow-600"></i> Override Store ID
              </label>
              <select id="dev-store-select" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                <option value="">Select Store</option>
              </select>
            </div>
          </div>

          <div class="flex flex-wrap gap-2">
            <button id="apply-dev-overrides" class="btn-primary text-white px-4 py-2 rounded-xl font-medium text-sm">
              <i class="fas fa-check mr-2"></i> Apply Overrides
            </button>
            <button id="clear-dev-overrides" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-2 rounded-xl font-medium text-sm hover:from-gray-600 hover:to-gray-700">
              <i class="fas fa-times mr-2"></i> Clear Overrides
            </button>
            <button id="refresh-page" class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2 rounded-xl font-medium text-sm hover:from-blue-600 hover:to-blue-700">
              <i class="fas fa-sync-alt mr-2"></i> Refresh Page
            </button>
          </div>

          <script>
          // Developer override functionality
          document.addEventListener('DOMContentLoaded', function() {
            // Load regions and stores for developer overrides
            loadDevRegions();
            loadDevStores();
            
            // Apply overrides button
            document.getElementById('apply-dev-overrides').addEventListener('click', function() {
              const role = document.getElementById('dev-role-select').value;
              const regionId = document.getElementById('dev-region-select').value;
              const storeId = document.getElementById('dev-store-select').value;
              
              let url = window.location.href.split('?')[0] + '?';
              const params = [];
              
              if (role) params.push(`dev_role_override=${role}`);
              if (regionId) params.push(`dev_region_id=${regionId}`);
              if (storeId) params.push(`dev_store_id=${storeId}`);
              
              if (params.length > 0) {
                url += params.join('&');
                window.location.href = url;
              } else {
                alert('Please select at least one override option');
              }
            });
            
            // Clear overrides button
            document.getElementById('clear-dev-overrides').addEventListener('click', function() {
              // Clear all overrides by redirecting to clean URL
              window.location.href = window.location.href.split('?')[0];
            });
            
            // Refresh page button
            document.getElementById('refresh-page').addEventListener('click', function() {
              window.location.reload();
            });

            // Reset to developer button
            document.getElementById('reset-to-developer').addEventListener('click', function() {
              // Set role override back to developer and clear other overrides
              let url = window.location.href.split('?')[0] + '?dev_role_override=developer';
              window.location.href = url;
            });
          });
          
          function loadDevRegions() {
            const regionSelect = document.getElementById('dev-region-select');

            // Clear existing options except the first one
            while (regionSelect.children.length > 1) {
              regionSelect.removeChild(regionSelect.lastChild);
            }

            // Load regions from database
            fetch('<?= url('/api/get_regions_and_stores') ?>')
              .then(response => response.json())
              .then(data => {
                if (data.success && data.regions && data.regions.length > 0) {
                  data.regions.forEach(region => {
                    const option = document.createElement('option');
                    option.value = region.region_id;
                    option.textContent = `${region.region_name || 'Region'} (ID: ${region.region_id})`;
                    regionSelect.appendChild(option);
                  });
                  console.log('Loaded', data.regions.length, 'regions for developer override');
                } else {
                  console.log('No regions found in database');
                  // Fallback to query method if API fails
                  runQuickQuery('SELECT DISTINCT region_id, region_name FROM stores WHERE region_id IS NOT NULL ORDER BY region_id', (results) => {
                    if (results && results.length > 0) {
                      results.forEach(row => {
                        const option = document.createElement('option');
                        option.value = row.region_id;
                        option.textContent = `${row.region_name || 'Region'} (ID: ${row.region_id})`;
                        regionSelect.appendChild(option);
                      });
                      console.log('Loaded', results.length, 'regions via fallback query');
                    }
                  });
                }
              })
              .catch(error => {
                console.error('Error loading regions:', error);
                // Fallback to query method
                runQuickQuery('SELECT DISTINCT region_id, region_name FROM stores WHERE region_id IS NOT NULL ORDER BY region_id', (results) => {
                  if (results && results.length > 0) {
                    results.forEach(row => {
                      const option = document.createElement('option');
                      option.value = row.region_id;
                      option.textContent = `${row.region_name || 'Region'} (ID: ${row.region_id})`;
                      regionSelect.appendChild(option);
                    });
                    console.log('Loaded', results.length, 'regions via fallback query');
                  }
                });
              });
          }

          function loadDevStores() {
            const storeSelect = document.getElementById('dev-store-select');

            // Clear existing options except the first one
            while (storeSelect.children.length > 1) {
              storeSelect.removeChild(storeSelect.lastChild);
            }

            // Load stores from database
            fetch('<?= url('/api/get_regions_and_stores') ?>')
              .then(response => response.json())
              .then(data => {
                if (data.success && data.stores && data.stores.length > 0) {
                  data.stores.forEach(store => {
                    const option = document.createElement('option');
                    option.value = store.id;
                    option.textContent = `${store.name} (ID: ${store.id}) - Region: ${store.region_name || store.region_id || 'N/A'}`;
                    storeSelect.appendChild(option);
                  });
                  console.log('Loaded', data.stores.length, 'stores for developer override');
                } else {
                  console.log('No stores found in database');
                  // Fallback to query method if API fails
                  runQuickQuery('SELECT id, name, region_id, region_name FROM stores ORDER BY name', (results) => {
                    if (results && results.length > 0) {
                      results.forEach(store => {
                        const option = document.createElement('option');
                        option.value = store.id;
                        option.textContent = `${store.name} (ID: ${store.id}) - Region: ${store.region_name || store.region_id || 'N/A'}`;
                        storeSelect.appendChild(option);
                      });
                      console.log('Loaded', results.length, 'stores via fallback query');
                    }
                  });
                }
              })
              .catch(error => {
                console.error('Error loading stores:', error);
                // Fallback to query method
                runQuickQuery('SELECT id, name, region_id, region_name FROM stores ORDER BY name', (results) => {
                  if (results && results.length > 0) {
                    results.forEach(store => {
                      const option = document.createElement('option');
                      option.value = store.id;
                      option.textContent = `${store.name} (ID: ${store.id}) - Region: ${store.region_name || store.region_id || 'N/A'}`;
                      storeSelect.appendChild(option);
                    });
                    console.log('Loaded', results.length, 'stores via fallback query');
                  }
                });
              });
          }
          </script>

          <div class="mt-4 p-3 bg-white rounded-xl border">
            <h4 class="font-medium text-gray-800 mb-2">Current Session Values:</h4>
            <div class="text-sm text-gray-600 space-y-1">
              <div><strong>Active Role:</strong> <span id="current-active-role"><?= isset($_SESSION['dev_role_override']) ? $_SESSION['dev_role_override'] : $userRole ?></span></div>
              <div><strong>Original Role:</strong> <?= $userRole ?> (in database)</div>
              <div><strong>Region ID:</strong> <span id="current-region-id"><?= isset($_SESSION['region_id']) ? $_SESSION['region_id'] : 'Not set' ?></span></div>
              <div><strong>Store ID:</strong> <span id="current-store-id"><?= isset($_SESSION['store_id']) ? $_SESSION['store_id'] : 'Not set' ?></span></div>
            </div>
          </div>
        </div>

        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-4 sm:p-6 border-l-4 border-blue-400">
          <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-4 responsive-text-lg flex items-center">
            <div class="w-6 h-6 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg mr-2 flex items-center justify-center">
              <i class="fas fa-info-circle text-white text-xs"></i>
            </div>
            Developer Information
          </h3>

          <div class="text-sm text-gray-700 space-y-2">
            <p><i class="fas fa-check text-green-600 mr-2"></i> You have full access to all system features</p>
            <p><i class="fas fa-check text-green-600 mr-2"></i> You can override region and store restrictions</p>
            <p><i class="fas fa-check text-green-600 mr-2"></i> You can view all dashboards and manage all tasks</p>
            <p><i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i> Use these powers responsibly for testing and development</p>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Regio Overzicht (voor regiomanagers en developers) -->
    <?php if ($isRegiomanager || $isDeveloper): ?>
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
        
      </div>
    </div>
    <?php endif; ?>

    <!-- Feedback Pagina -->
    <div id="page-feedback" class="space-y-4 sm:space-y-6 hidden">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-comment-alt text-white text-sm"></i>
          </div>
          Feedback Versturen
        </h2>

        <!-- Feedback Information -->
        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-4 sm:p-6 mb-6 border-l-4 border-blue-400">
          <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-2 responsive-text-lg flex items-center">
            <div class="w-6 h-6 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg mr-2 flex items-center justify-center">
              <i class="fas fa-info-circle text-white text-xs"></i>
            </div>
            Feedback Informatie
          </h3>
          <div class="text-sm text-gray-700 space-y-2">
            <p><i class="fas fa-check text-green-600 mr-2"></i> Verstuur feedback, bug reports of feature requests naar het development team</p>
            <p><i class="fas fa-check text-green-600 mr-2"></i> Voeg bestanden toe zoals screenshots of logs voor betere ondersteuning</p>
            <p><i class="fas fa-check text-green-600 mr-2"></i> Versienummer is verplicht voor tracking en debugging</p>
            <p><i class="fas fa-exclamation-triangle text-orange-500 mr-2"></i> Alle feedback wordt automatisch naar alle developers gestuurd</p>
          </div>
        </div>

        <!-- Feedback Form -->
        <form id="feedback-form" enctype="multipart/form-data" class="space-y-6">
          <!-- Feedback Type -->
          <div>
            <label for="feedback-type" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-tag mr-2 text-blue-600"></i> Type Feedback
            </label>
            <select id="feedback-type" name="feedback_type" required class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
              <option value="">Selecteer type</option>
              <option value="bug">🐛 Bug Report</option>
              <option value="feature">💡 Feature Request</option>
              <option value="improvement">⚡ Verbetering</option>
              <option value="question">❓ Vraag</option>
              <option value="other">📝 Overig</option>
            </select>
          </div>

          <!-- Version Number (Required) -->
          <div>
            <label for="version-number" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-code-branch mr-2 text-blue-600"></i> Versie Nummer <span class="text-red-500">*</span>
            </label>
            <input type="text" id="version-number" name="version_number" required
                   placeholder="bijv. v1.2.3 of 2024.01.15"
                   class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
            <p class="text-xs text-gray-500 mt-1">Voer de huidige versie van het systeem in</p>
          </div>

          <!-- Priority Level -->
          <div>
            <label for="priority-level" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-exclamation-circle mr-2 text-blue-600"></i> Prioriteit
            </label>
            <select id="priority-level" name="priority" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
              <option value="low">🟢 Laag - Algemene feedback</option>
              <option value="medium" selected>🟡 Gemiddeld - Normale issue</option>
              <option value="high">🟠 Hoog - Belangrijke issue</option>
              <option value="critical">🔴 Kritiek - Systeem down/blocker</option>
            </select>
          </div>

          <!-- Subject -->
          <div>
            <label for="feedback-subject" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-heading mr-2 text-blue-600"></i> Onderwerp
            </label>
            <input type="text" id="feedback-subject" name="subject" required
                   placeholder="Korte beschrijving van het probleem of verzoek"
                   class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
          </div>

          <!-- Description -->
          <div>
            <label for="feedback-description" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-align-left mr-2 text-blue-600"></i> Beschrijving
            </label>
            <textarea id="feedback-description" name="description" required rows="6"
                      placeholder="Beschrijf je feedback in detail. Voor bugs: wat deed je toen het gebeurde? Wat verwachtte je? Wat gebeurde er eigenlijk?"
                      class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base resize-vertical"></textarea>
          </div>

          <!-- Steps to Reproduce (for bugs) -->
          <div id="steps-container" class="hidden">
            <label for="steps-reproduce" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-list-ol mr-2 text-blue-600"></i> Stappen om te Reproduceren
            </label>
            <textarea id="steps-reproduce" name="steps_reproduce" rows="4"
                      placeholder="1. Ga naar...&#10;2. Klik op...&#10;3. Vul in...&#10;4. Zie fout..."
                      class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base resize-vertical"></textarea>
          </div>

          <!-- File Upload -->
          <div>
            <label for="feedback-files" class="block text-sm font-medium text-gray-700 mb-2">
              <i class="fas fa-paperclip mr-2 text-blue-600"></i> Bestanden (Optioneel)
            </label>
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-400 transition-colors">
              <input type="file" id="feedback-files" name="files[]" multiple
                     accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.log,.zip,.doc,.docx"
                     class="hidden">
              <div id="file-drop-zone" class="cursor-pointer">
                <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                <p class="text-gray-600 mb-1">Klik om bestanden te selecteren of sleep ze hierheen</p>
                <p class="text-xs text-gray-500">Screenshots, logs, documenten (max 10MB per bestand)</p>
              </div>
              <div id="file-list" class="mt-4 space-y-2 hidden"></div>
            </div>
          </div>

          <!-- Browser/System Info -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
              <i class="fas fa-desktop mr-2 text-gray-500"></i> Systeem Informatie (Automatisch verzameld)
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-gray-600">
              <div>
                <strong>Browser:</strong> <span id="browser-info">-</span>
              </div>
              <div>
                <strong>Schermresolutie:</strong> <span id="screen-info">-</span>
              </div>
              <div>
                <strong>Gebruiker:</strong> <span><?= htmlspecialchars($username) ?> (<?= htmlspecialchars($userRole) ?>)</span>
              </div>
              <div>
                <strong>Tijdstip:</strong> <span id="timestamp-info">-</span>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex flex-col sm:flex-row gap-4">
            <button type="submit" id="submit-feedback" class="flex-1 btn-primary text-white px-6 py-3 rounded-xl font-medium text-sm sm:text-base">
              <i class="fas fa-paper-plane mr-2"></i> Feedback Versturen
            </button>
            <button type="button" id="clear-feedback" class="bg-gradient-to-r from-gray-500 to-gray-600 text-white px-6 py-3 rounded-xl font-medium hover:from-gray-600 hover:to-gray-700 transition-all text-sm sm:text-base">
              <i class="fas fa-times mr-2"></i> Formulier Wissen
            </button>
          </div>
        </form>

        <!-- Success/Error Messages -->
        <div id="feedback-message" class="hidden mt-6 p-4 rounded-xl"></div>
      </div>
    </div>

    <!-- Voltooide Taken -->
    <div id="page-completed" class="space-y-4 sm:space-y-6 hidden">
      <div class="glass-card rounded-2xl p-4 sm:p-6">
        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-800 mb-4 sm:mb-6 responsive-text-2xl flex items-center">
          <div class="w-8 h-8 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 sm:mr-3 flex items-center justify-center">
            <i class="fas fa-check-circle text-white text-sm"></i>
          </div>
          Voltooide Taken
        </h2>

        <!-- Reset Information -->
        <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-2xl p-4 sm:p-6 mb-6 border-l-4 border-blue-400">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-base sm:text-lg font-bold text-gray-800 mb-2 responsive-text-lg flex items-center">
                <div class="w-6 h-6 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg mr-2 flex items-center justify-center">
                  <i class="fas fa-info-circle text-white text-xs"></i>
                </div>
                Taak Reset Informatie
              </h3>
              <p class="text-sm text-gray-600">Taken worden automatisch gereset op basis van hun frequentie</p>
            </div>
            <div class="text-center">
              <div id="next-reset-info" class="text-sm font-medium text-blue-800">
                <div class="mb-1">Volgende resets:</div>
                <div id="reset-schedule" class="text-xs text-blue-600 space-y-1">
                  Laden...
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-3 sm:p-4 mb-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Date Range Filter -->
            <div>
              <label for="completedDateFrom" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-calendar mr-2 text-green-600"></i>Van Datum
              </label>
              <input type="date" id="completedDateFrom" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
            </div>

            <div>
              <label for="completedDateTo" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-calendar mr-2 text-green-600"></i>Tot Datum
              </label>
              <input type="date" id="completedDateTo" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
            </div>

            <!-- Store Filter -->
            <div>
              <label for="completedStoreFilter" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-store mr-2 text-green-600"></i>Winkel
              </label>
              <select id="completedStoreFilter" class="input-field w-full p-3 rounded-xl focus:outline-none text-sm sm:text-base">
                <option value="">Alle winkels</option>
              </select>
            </div>

            <!-- Clear Button -->
            <div class="flex items-end">
              <button id="clearCompletedFilters" class="w-full bg-gradient-to-r from-gray-500 to-gray-600 text-white px-4 py-3 rounded-xl font-medium hover:from-gray-600 hover:to-gray-700 transition-all text-sm sm:text-base transform hover:-translate-y-1">
                <i class="fas fa-times mr-2"></i>Reset Filters
              </button>
            </div>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border-l-4 border-green-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-green-600 font-medium">Totaal Voltooid</p>
                <p id="total-completed-tasks" class="text-2xl font-bold text-green-800">0</p>
              </div>
              <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-check text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border-l-4 border-blue-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-blue-600 font-medium">Deze Week</p>
                <p id="week-completed-tasks" class="text-2xl font-bold text-blue-800">0</p>
              </div>
              <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar-week text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4 border-l-4 border-purple-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-purple-600 font-medium">Vandaag</p>
                <p id="today-completed-tasks" class="text-2xl font-bold text-purple-800">0</p>
              </div>
              <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar-day text-white text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-4 border-l-4 border-yellow-400">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-yellow-600 font-medium">Gemiddeld/Dag</p>
                <p id="avg-completed-tasks" class="text-2xl font-bold text-yellow-800">0</p>
              </div>
              <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-white text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Completed Tasks List -->
        <div id="completed-tasks-list" class="space-y-4">
          <div class="text-center text-gray-500 py-8">
            <div class="w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl mx-auto mb-4 flex items-center justify-center">
              <i class="fas fa-check-circle text-2xl text-gray-400"></i>
            </div>
            <p class="font-medium">Geen voltooide taken gevonden</p>
            <p class="text-sm">Voltooide taken verschijnen hier automatisch</p>
          </div>
        </div>
      </div>
    </div>

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
          <?php elseif ($isStoremanager): ?>
          <span class="ml-2 text-sm bg-green-100 text-green-800 px-2 py-1 rounded-full">Voor je winkel</span>
          <?php elseif ($isDeveloper): ?>
          <span class="ml-2 text-sm bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">Developer modus</span>
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

            <!-- Required and BurgerKitchen Checkboxes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
              <div class="flex items-start">
                <input type="checkbox" id="task-required" class="mr-2 h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-1" />
                <div>
                  <label for="task-required" class="text-sm text-gray-700">
                    <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i> Verplicht elke dag toevoegen
                  </label>
                  <p class="text-xs text-gray-500 mt-1">Deze taak wordt altijd toegevoegd aan de gegenereerde lijst</p>
                </div>
              </div>

              <div class="flex items-start">
                <input type="checkbox" id="task-burgerkitchen" class="mr-2 h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded mt-1" />
                <div>
                  <label for="task-burgerkitchen" class="text-sm text-gray-700">
                    <i class="fas fa-hamburger text-orange-500 mr-1"></i> BurgerKitchen taak
                  </label>
                  <p class="text-xs text-gray-500 mt-1">Deze taak is specifiek voor BurgerKitchen winkels</p>
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

    <!-- Developer Panel -->
    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'developer'): ?>
    <div id="developer-panel" class="fixed bottom-4 right-4 bg-gray-900 text-white rounded-lg shadow-2xl max-w-sm z-50 transition-all duration-300">
      <div class="flex justify-between items-center p-4 border-b border-gray-700">
        <h4 class="font-bold text-sm flex items-center">
          <i class="fas fa-code mr-2 text-yellow-400"></i>
          Developer Panel
        </h4>
        <div class="flex items-center space-x-2">
          <button onclick="minimizeDevPanel()" class="text-gray-400 hover:text-white text-xs" title="Minimize">
            <i class="fas fa-minus"></i>
          </button>
          <button onclick="toggleDevPanel()" class="text-gray-400 hover:text-white" title="Close">
            <i class="fas fa-times"></i>
          </button>
        </div>
      </div>

      <div id="dev-panel-content" class="p-4 max-h-96 overflow-y-auto">
        <!-- Tabs -->
        <div class="flex space-x-1 mb-3 bg-gray-800 rounded p-1">
          <button onclick="showDevTab('info')" id="dev-tab-info" class="dev-tab active px-2 py-1 rounded text-xs">Info</button>
          <button onclick="showDevTab('actions')" id="dev-tab-actions" class="dev-tab px-2 py-1 rounded text-xs">Actions</button>
          <button onclick="showDevTab('database')" id="dev-tab-database" class="dev-tab px-2 py-1 rounded text-xs">Database</button>
          <button onclick="showDevTab('logs')" id="dev-tab-logs" class="dev-tab px-2 py-1 rounded text-xs">Logs</button>
        </div>

        <!-- Info Tab -->
        <div id="dev-content-info" class="dev-tab-content space-y-2 text-xs">
          <!-- Session Info -->
          <div class="bg-gray-800 p-2 rounded">
            <strong class="text-blue-400">Session:</strong>
            <div class="ml-2 mt-1">
              <div>User ID: <span class="text-green-400"><?php echo $_SESSION['user_id'] ?? 'Not set'; ?></span></div>
              <div>Role: <span class="text-green-400"><?php echo $_SESSION['user_role'] ?? 'Not set'; ?></span></div>
              <?php if (isset($_SESSION['region_id'])): ?>
              <div>Region ID: <span class="text-green-400"><?php echo $_SESSION['region_id']; ?></span></div>
              <?php endif; ?>
              <?php if (isset($_SESSION['store_id'])): ?>
              <div>Store ID: <span class="text-green-400"><?php echo $_SESSION['store_id']; ?></span></div>
              <?php endif; ?>
              <?php if (isset($_SESSION['dev_role_override'])): ?>
              <div>Override Role: <span class="text-yellow-400"><?php echo $_SESSION['dev_role_override']; ?></span></div>
              <?php endif; ?>
            </div>
          </div>

          <!-- System Info -->
          <div class="bg-gray-800 p-2 rounded">
            <strong class="text-blue-400">System:</strong>
            <div class="ml-2 mt-1">
              <div>PHP: <span class="text-green-400"><?php echo PHP_VERSION; ?></span></div>
              <div>Memory: <span class="text-green-400"><?php echo ini_get('memory_limit'); ?></span></div>
              <div>Time: <span class="text-green-400"><?php echo date('Y-m-d H:i:s'); ?></span></div>
              <div>Debug Mode: <span id="debug-status" class="text-green-400">Off</span></div>
            </div>
          </div>
        </div>

        <!-- Actions Tab -->
        <div id="dev-content-actions" class="dev-tab-content space-y-2 text-xs hidden">
          <div class="grid grid-cols-2 gap-2">
            <button onclick="clearCache()" class="bg-blue-600 hover:bg-blue-700 px-2 py-2 rounded text-xs transition-colors">
              <i class="fas fa-trash mr-1"></i>Clear Cache
            </button>
            <button onclick="refreshData()" class="bg-green-600 hover:bg-green-700 px-2 py-2 rounded text-xs transition-colors">
              <i class="fas fa-sync mr-1"></i>Refresh Data
            </button>
            <button onclick="debugMode()" class="bg-yellow-600 hover:bg-yellow-700 px-2 py-2 rounded text-xs transition-colors">
              <i class="fas fa-bug mr-1"></i>Debug Mode
            </button>
            <button onclick="exportSession()" class="bg-purple-600 hover:bg-purple-700 px-2 py-2 rounded text-xs transition-colors">
              <i class="fas fa-download mr-1"></i>Export Session
            </button>
            <button onclick="resetToDeveloper()" class="bg-orange-600 hover:bg-orange-700 px-2 py-2 rounded text-xs transition-colors col-span-2">
              <i class="fas fa-undo mr-1"></i>Reset to Developer
            </button>
          </div>
          
          <div class="bg-gray-800 p-2 rounded">
            <strong class="text-blue-400">Quick SQL:</strong>
            <div class="mt-2 space-y-1">
              <button onclick="runQuickQuery('SELECT COUNT(*) as total_users FROM users')" class="w-full text-left bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded text-xs">
                Count Users
              </button>
              <button onclick="runQuickQuery('SELECT COUNT(*) as total_stores FROM stores')" class="w-full text-left bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded text-xs">
                Count Stores
              </button>
              <button onclick="runQuickQuery('SELECT COUNT(*) as total_tasks FROM tasks')" class="w-full text-left bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded text-xs">
                Count Tasks
              </button>
            </div>
          </div>
        </div>

        <!-- Database Tab -->
        <div id="dev-content-database" class="dev-tab-content space-y-2 text-xs hidden">
          <div class="bg-gray-800 p-2 rounded">
            <strong class="text-blue-400">Database Info:</strong>
            <div id="db-info" class="ml-2 mt-1 text-gray-300">
              Loading...
            </div>
          </div>
          
          <div class="bg-gray-800 p-2 rounded">
            <strong class="text-blue-400">Custom Query:</strong>
            <textarea id="custom-query" class="w-full mt-1 p-1 bg-gray-700 text-white rounded text-xs" rows="3" placeholder="SELECT * FROM users LIMIT 5"></textarea>
            <button onclick="runCustomQuery()" class="mt-1 bg-green-600 hover:bg-green-700 px-2 py-1 rounded text-xs">
              <i class="fas fa-play mr-1"></i>Execute
            </button>
          </div>
          
          <div id="query-results" class="bg-gray-800 p-2 rounded hidden">
            <strong class="text-blue-400">Results:</strong>
            <div id="query-output" class="mt-1 text-xs font-mono"></div>
          </div>
        </div>

        <!-- Logs Tab -->
        <div id="dev-content-logs" class="dev-tab-content space-y-2 text-xs hidden">
          <div class="flex justify-between items-center">
            <strong class="text-blue-400">Recent Logs:</strong>
            <button onclick="refreshLogs()" class="bg-gray-700 hover:bg-gray-600 px-2 py-1 rounded text-xs">
              <i class="fas fa-sync mr-1"></i>Refresh
            </button>
          </div>
          <div id="logs-content" class="bg-gray-800 p-2 rounded max-h-48 overflow-y-auto">
            <div class="text-gray-400">Loading logs...</div>
          </div>
        </div>
      </div>
    </div>

    <script>
    // Developer Panel JavaScript
    let devPanelMinimized = false;

    function toggleDevPanel() {
      const panel = document.getElementById('developer-panel');
      panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    }

    function minimizeDevPanel() {
      const content = document.getElementById('dev-panel-content');
      devPanelMinimized = !devPanelMinimized;
      content.style.display = devPanelMinimized ? 'none' : 'block';
    }

    function showDevTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.dev-tab-content').forEach(content => {
        content.classList.add('hidden');
      });
      
      // Remove active class from all tabs
      document.querySelectorAll('.dev-tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Show selected tab content
      document.getElementById(`dev-content-${tabName}`).classList.remove('hidden');
      document.getElementById(`dev-tab-${tabName}`).classList.add('active');
      
      // Load data for specific tabs
      if (tabName === 'database') {
        loadDatabaseInfo();
      } else if (tabName === 'logs') {
        refreshLogs();
      }
    }

    function clearCache() {
      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'dev_action=clear_cache'
      })
      .then(response => response.json())
      .then(data => {
        showDevNotification(data.message || 'Cache cleared', data.success ? 'success' : 'error');
      })
      .catch(error => {
        console.error('Error:', error);
        showDevNotification('Error clearing cache', 'error');
      });
    }

    function refreshData() {
      location.reload();
    }

    function debugMode() {
      const isDebug = localStorage.getItem('debug_mode') === 'true';
      localStorage.setItem('debug_mode', !isDebug);
      document.getElementById('debug-status').textContent = !isDebug ? 'On' : 'Off';
      document.getElementById('debug-status').className = !isDebug ? 'text-yellow-400' : 'text-green-400';
      showDevNotification(`Debug mode ${!isDebug ? 'enabled' : 'disabled'}`, 'info');
    }

    function exportSession() {
      const sessionData = {
        user_id: '<?php echo $_SESSION['user_id'] ?? 'Not set'; ?>',
        user_role: '<?php echo $_SESSION['user_role'] ?? 'Not set'; ?>',
        region_id: '<?php echo $_SESSION['region_id'] ?? 'Not set'; ?>',
        store_id: '<?php echo $_SESSION['store_id'] ?? 'Not set'; ?>',
        timestamp: new Date().toISOString()
      };
      
      const blob = new Blob([JSON.stringify(sessionData, null, 2)], {type: 'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'session_data.json';
      a.click();
      URL.revokeObjectURL(url);
    }

    function loadDatabaseInfo() {
      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'dev_action=get_db_info'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const info = data.info;
          document.getElementById('db-info').innerHTML = `
            <div>Path: <span class="text-green-400">${info.database_path}</span></div>
            <div>Size: <span class="text-green-400">${(info.database_size / 1024).toFixed(2)} KB</span></div>
            <div>Tables: <span class="text-green-400">${info.tables.join(', ')}</span></div>
          `;
        } else {
          document.getElementById('db-info').innerHTML = `<div class="text-red-400">Error: ${data.message}</div>`;
        }
      })
      .catch(error => {
        document.getElementById('db-info').innerHTML = `<div class="text-red-400">Error loading database info</div>`;
      });
    }

    function runQuickQuery(query, callback = null) {
      if (callback) {
        // If callback provided, execute query and call callback with results
        fetch(window.location.href, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `dev_action=execute_query&query=${encodeURIComponent(query)}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            callback(data.results);
          } else {
            console.error('Query error:', data.message);
            callback(null);
          }
        })
        .catch(error => {
          console.error('Error executing query:', error);
          callback(null);
        });
      } else {
        // Original behavior: set query in textarea and run
        document.getElementById('custom-query').value = query;
        runCustomQuery();
      }
    }

    function runCustomQuery() {
      const query = document.getElementById('custom-query').value.trim();
      if (!query) return;
      
      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `dev_action=execute_query&query=${encodeURIComponent(query)}`
      })
      .then(response => response.json())
      .then(data => {
        const resultsDiv = document.getElementById('query-results');
        const outputDiv = document.getElementById('query-output');
        
        if (data.success) {
          outputDiv.innerHTML = `<pre>${JSON.stringify(data.results, null, 2)}</pre>`;
          resultsDiv.classList.remove('hidden');
        } else {
          outputDiv.innerHTML = `<div class="text-red-400">Error: ${data.message}</div>`;
          resultsDiv.classList.remove('hidden');
        }
      })
      .catch(error => {
        document.getElementById('query-output').innerHTML = `<div class="text-red-400">Error executing query</div>`;
        document.getElementById('query-results').classList.remove('hidden');
      });
    }

    function refreshLogs() {
      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'dev_action=get_logs'
      })
      .then(response => response.json())
      .then(data => {
        const logsContent = document.getElementById('logs-content');
        if (data.success) {
          logsContent.innerHTML = `<pre class="text-xs">${data.logs.join('')}</pre>`;
        } else {
          logsContent.innerHTML = `<div class="text-red-400">Error: ${data.message}</div>`;
        }
      })
      .catch(error => {
        document.getElementById('logs-content').innerHTML = `<div class="text-red-400">Error loading logs</div>`;
      });
    }

    function resetToDeveloper() {
      // Set role override back to developer and clear other overrides
      let url = window.location.href.split('?')[0] + '?dev_role_override=developer';
      window.location.href = url;
    }

    function showDevNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 p-3 rounded-lg shadow-lg z-50 text-white text-sm ${
        type === 'success' ? 'bg-green-600' :
        type === 'error' ? 'bg-red-600' :
        type === 'warning' ? 'bg-yellow-600' : 'bg-blue-600'
      }`;
      notification.textContent = message;
      document.body.appendChild(notification);

      setTimeout(() => {
        notification.remove();
      }, 3000);
    }

    // Initialize debug status
    document.addEventListener('DOMContentLoaded', function() {
      const isDebug = localStorage.getItem('debug_mode') === 'true';
      document.getElementById('debug-status').textContent = isDebug ? 'On' : 'Off';
      document.getElementById('debug-status').className = isDebug ? 'text-yellow-400' : 'text-green-400';
    });

    // Auto-hide panel after 15 seconds
    setTimeout(() => {
      const panel = document.getElementById('developer-panel');
      if (panel && !devPanelMinimized) {
        panel.style.opacity = '0.8';
      }
    }, 15000);
    </script>

    <style>
    .dev-tab.active {
      background-color: #374151;
      color: #fbbf24;
    }
    .dev-tab {
      transition: all 0.2s ease;
    }
    .dev-tab:hover {
      background-color: #4b5563;
    }
    #developer-panel {
      min-width: 320px;
      max-width: 400px;
    }
    </style>
    <?php endif; ?>
  </div>
  
  <?php require_once __DIR__ . '/alerts/danger_alert.php'; ?>
  <script src="js/alert.js"></script>

  <script>
  // Feedback Page Functionality
  document.addEventListener('DOMContentLoaded', function() {
    initializeFeedbackPage();

    // Completed Tasks Page Functionality
    // Initialize reset information
    updateResetInformation();
    setInterval(updateResetInformation, 60000); // Update every minute

    // Set default date filters (last 7 days)
    const today = new Date();
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);

    document.getElementById('completedDateFrom').value = weekAgo.toISOString().split('T')[0];
    document.getElementById('completedDateTo').value = today.toISOString().split('T')[0];

    // Load completed tasks when page is shown
    const originalShowPage = window.showPage;
    window.showPage = function(page) {
      originalShowPage(page);
      if (page === 'completed') {
        loadCompletedTasks();
        loadCompletedStores();
        loadActiveTaskSets(); // Load active task sets to show reset schedule
      } else if (page === 'feedback') {
        updateSystemInfo();
      }
    };

    // Filter event listeners
    document.getElementById('completedDateFrom').addEventListener('change', loadCompletedTasks);
    document.getElementById('completedDateTo').addEventListener('change', loadCompletedTasks);
    document.getElementById('completedStoreFilter').addEventListener('change', loadCompletedTasks);
    document.getElementById('clearCompletedFilters').addEventListener('click', clearCompletedFilters);
  });

  function initializeFeedbackPage() {
    // Feedback type change handler
    document.getElementById('feedback-type').addEventListener('change', function() {
      const stepsContainer = document.getElementById('steps-container');
      if (this.value === 'bug') {
        stepsContainer.classList.remove('hidden');
      } else {
        stepsContainer.classList.add('hidden');
      }
    });

    // File upload handlers
    const fileInput = document.getElementById('feedback-files');
    const dropZone = document.getElementById('file-drop-zone');
    const fileList = document.getElementById('file-list');

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('border-blue-400', 'bg-blue-50');
    });

    dropZone.addEventListener('dragleave', (e) => {
      e.preventDefault();
      dropZone.classList.remove('border-blue-400', 'bg-blue-50');
    });

    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('border-blue-400', 'bg-blue-50');
      fileInput.files = e.dataTransfer.files;
      updateFileList();
    });

    fileInput.addEventListener('change', updateFileList);

    // Form submission
    document.getElementById('feedback-form').addEventListener('submit', submitFeedback);

    // Clear form button
    document.getElementById('clear-feedback').addEventListener('click', clearFeedbackForm);

    // Initialize system info
    updateSystemInfo();
  }

  function updateFileList() {
    const fileInput = document.getElementById('feedback-files');
    const fileList = document.getElementById('file-list');

    if (fileInput.files.length === 0) {
      fileList.classList.add('hidden');
      return;
    }

    fileList.classList.remove('hidden');
    fileList.innerHTML = '';

    Array.from(fileInput.files).forEach((file, index) => {
      const fileItem = document.createElement('div');
      fileItem.className = 'flex items-center justify-between bg-white p-3 rounded-lg border';

      const fileInfo = document.createElement('div');
      fileInfo.className = 'flex items-center';

      const fileIcon = getFileIcon(file.type);
      const fileSize = (file.size / 1024 / 1024).toFixed(2);

      fileInfo.innerHTML = `
        <i class="fas ${fileIcon} mr-2 text-gray-500"></i>
        <div>
          <div class="text-sm font-medium text-gray-800">${file.name}</div>
          <div class="text-xs text-gray-500">${fileSize} MB</div>
        </div>
      `;

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'text-red-500 hover:text-red-700 ml-2';
      removeBtn.innerHTML = '<i class="fas fa-times"></i>';
      removeBtn.onclick = () => removeFile(index);

      fileItem.appendChild(fileInfo);
      fileItem.appendChild(removeBtn);
      fileList.appendChild(fileItem);
    });
  }

  function getFileIcon(mimeType) {
    if (mimeType.startsWith('image/')) return 'fa-image';
    if (mimeType.includes('pdf')) return 'fa-file-pdf';
    if (mimeType.includes('text')) return 'fa-file-alt';
    if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fa-file-archive';
    if (mimeType.includes('word')) return 'fa-file-word';
    return 'fa-file';
  }

  function removeFile(index) {
    const fileInput = document.getElementById('feedback-files');
    const dt = new DataTransfer();

    Array.from(fileInput.files).forEach((file, i) => {
      if (i !== index) dt.items.add(file);
    });

    fileInput.files = dt.files;
    updateFileList();
  }

  function updateSystemInfo() {
    // Browser info
    const browserInfo = navigator.userAgent.split(' ').slice(-2).join(' ');
    document.getElementById('browser-info').textContent = browserInfo;

    // Screen resolution
    document.getElementById('screen-info').textContent = `${screen.width}x${screen.height}`;

    // Current timestamp
    document.getElementById('timestamp-info').textContent = new Date().toLocaleString('nl-NL');
  }

  function submitFeedback(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submit-feedback');
    const originalText = submitBtn.innerHTML;

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Versturen...';

    const formData = new FormData();

    // Add form fields
    formData.append('feedback_type', document.getElementById('feedback-type').value);
    formData.append('version_number', document.getElementById('version-number').value);
    formData.append('priority', document.getElementById('priority-level').value);
    formData.append('subject', document.getElementById('feedback-subject').value);
    formData.append('description', document.getElementById('feedback-description').value);
    formData.append('steps_reproduce', document.getElementById('steps-reproduce').value);

    // Add system info
    formData.append('browser_info', document.getElementById('browser-info').textContent);
    formData.append('screen_info', document.getElementById('screen-info').textContent);
    formData.append('user_info', '<?= htmlspecialchars($username) ?> (<?= htmlspecialchars($userRole) ?>)');
    formData.append('timestamp', document.getElementById('timestamp-info').textContent);

    // Add files
    const files = document.getElementById('feedback-files').files;
    for (let i = 0; i < files.length; i++) {
      formData.append('files[]', files[i]);
    }

    // Submit to API
    fetch('<?= url('/api/submit_feedback') ?>', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      showFeedbackMessage(data.message, data.success ? 'success' : 'error');

      if (data.success) {
        clearFeedbackForm();
      }
    })
    .catch(error => {
      console.error('Error submitting feedback:', error);
      showFeedbackMessage('Er is een fout opgetreden bij het versturen van de feedback. Probeer het opnieuw.', 'error');
    })
    .finally(() => {
      // Re-enable submit button
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    });
  }

  function showFeedbackMessage(message, type) {
    const messageDiv = document.getElementById('feedback-message');
    messageDiv.className = `mt-6 p-4 rounded-xl ${
      type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
      type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
      'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    const icon = type === 'success' ? 'fa-check-circle' :
                 type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle';

    messageDiv.innerHTML = `
      <div class="flex items-center">
        <i class="fas ${icon} mr-2"></i>
        <span>${message}</span>
      </div>
    `;

    messageDiv.classList.remove('hidden');

    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
      setTimeout(() => {
        messageDiv.classList.add('hidden');
      }, 5000);
    }
  }

  function clearFeedbackForm() {
    document.getElementById('feedback-form').reset();
    document.getElementById('steps-container').classList.add('hidden');
    document.getElementById('file-list').classList.add('hidden');
    document.getElementById('feedback-message').classList.add('hidden');
    updateSystemInfo();
  }

  function updateResetInformation() {
    // This will be updated when we load active task sets
    // For now, show general information
    const scheduleDiv = document.getElementById('reset-schedule');
    if (!scheduleDiv) return;

    scheduleDiv.innerHTML = `
      <div>• Dagelijks: Elke dag om 00:00</div>
      <div>• Wekelijks: Elke 7 dagen</div>
      <div>• 2-wekelijks: Elke 14 dagen</div>
      <div>• Maandelijks: Elke 30 dagen</div>
    `;
  }

  function loadActiveTaskSets() {
    // Load active task sets to show when they will reset
    fetch('<?= url('/api/get_active_task_sets') ?>')
      .then(response => response.json())
      .then(data => {
        if (data.success && data.task_sets) {
          updateResetSchedule(data.task_sets);
        }
      })
      .catch(error => {
        console.error('Error loading active task sets:', error);
      });
  }

  function updateResetSchedule(taskSets) {
    const scheduleDiv = document.getElementById('reset-schedule');
    if (!scheduleDiv || !taskSets.length) {
      updateResetInformation(); // Fallback to general info
      return;
    }

    const now = new Date();
    const upcomingResets = [];

    taskSets.forEach(taskSet => {
      const createdAt = new Date(taskSet.created_at);
      const frequency = taskSet.frequency || 'dagelijks';

      let nextReset = new Date(createdAt);

      // Calculate next reset based on frequency
      switch (frequency.toLowerCase()) {
        case 'dagelijks':
          // Daily tasks reset every day at 00:00
          nextReset = new Date(now);
          nextReset.setDate(now.getDate() + 1);
          nextReset.setHours(0, 0, 0, 0);
          break;

        case 'wekelijks':
          // Weekly tasks reset every 7 days from creation
          const daysSinceCreation = Math.floor((now - createdAt) / (1000 * 60 * 60 * 24));
          const weeksSinceCreation = Math.floor(daysSinceCreation / 7);
          nextReset = new Date(createdAt);
          nextReset.setDate(createdAt.getDate() + ((weeksSinceCreation + 1) * 7));
          break;

        case '2-wekelijks':
          // Bi-weekly tasks reset every 14 days from creation
          const daysSinceCreationBi = Math.floor((now - createdAt) / (1000 * 60 * 60 * 24));
          const biweeksSinceCreation = Math.floor(daysSinceCreationBi / 14);
          nextReset = new Date(createdAt);
          nextReset.setDate(createdAt.getDate() + ((biweeksSinceCreation + 1) * 14));
          break;

        case 'maandelijks':
          // Monthly tasks reset every 30 days from creation
          const daysSinceCreationMonth = Math.floor((now - createdAt) / (1000 * 60 * 60 * 24));
          const monthsSinceCreation = Math.floor(daysSinceCreationMonth / 30);
          nextReset = new Date(createdAt);
          nextReset.setDate(createdAt.getDate() + ((monthsSinceCreation + 1) * 30));
          break;
      }

      // Only include future resets
      if (nextReset > now) {
        upcomingResets.push({
          store: taskSet.store_name,
          manager: taskSet.manager_name,
          frequency: frequency,
          resetDate: nextReset,
          daysUntil: Math.ceil((nextReset - now) / (1000 * 60 * 60 * 24))
        });
      }
    });

    // Sort by reset date (soonest first)
    upcomingResets.sort((a, b) => a.resetDate - b.resetDate);

    // Display next few resets
    let html = '';
    const nextResets = upcomingResets.slice(0, 5); // Show next 5 resets

    if (nextResets.length === 0) {
      html = '<div class="text-gray-500">Geen actieve taken gevonden</div>';
    } else {
      nextResets.forEach(reset => {
        const timeText = reset.daysUntil === 1 ? 'morgen' : `over ${reset.daysUntil} dagen`;
        html += `
          <div class="flex justify-between items-center py-1">
            <span class="text-xs">${reset.store} (${reset.frequency})</span>
            <span class="text-xs font-medium">${timeText}</span>
          </div>
        `;
      });
    }

    scheduleDiv.innerHTML = html;
  }

  function loadCompletedStores() {
    const storeFilter = document.getElementById('completedStoreFilter');

    // Clear existing options except the first one
    while (storeFilter.children.length > 1) {
      storeFilter.removeChild(storeFilter.lastChild);
    }

    // Load stores based on user role
    let storeRestriction = '';
    if (typeof window.storeRestriction !== 'undefined' && window.storeRestriction) {
      storeRestriction = window.storeRestriction;
    }

    fetch('<?= url('/api/get_stores') ?>' + (storeRestriction ? `?store_id=${storeRestriction}` : ''))
      .then(response => response.json())
      .then(data => {
        if (data.success && data.stores) {
          data.stores.forEach(store => {
            const option = document.createElement('option');
            option.value = store.id;
            option.textContent = store.name;
            storeFilter.appendChild(option);
          });
        }
      })
      .catch(error => {
        console.error('Error loading stores:', error);
      });
  }

  function loadCompletedTasks() {
    const dateFrom = document.getElementById('completedDateFrom').value;
    const dateTo = document.getElementById('completedDateTo').value;
    const storeId = document.getElementById('completedStoreFilter').value;

    const params = new URLSearchParams();
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    if (storeId) params.append('store_id', storeId);

    // Add user restrictions
    if (typeof window.storeRestriction !== 'undefined' && window.storeRestriction) {
      params.append('user_store_restriction', window.storeRestriction);
    }

    fetch(`<?= url('/api/get_completed_tasks') ?>?${params.toString()}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayCompletedTasks(data.tasks);
          updateCompletedStatistics(data.statistics);
        } else {
          console.error('Error loading completed tasks:', data.message);
          showEmptyCompletedTasks();
        }
      })
      .catch(error => {
        console.error('Error loading completed tasks:', error);
        showEmptyCompletedTasks();
      });
  }

// Helpers
function escapeHtml(s) {
  if (s == null) return '';
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatDatePart(value) {
  if (!value) return '—';
  if (typeof value !== 'string') value = String(value);
  if (value.indexOf('T') !== -1) return value.split('T')[0];
  if (value.indexOf(' ') !== -1) return value.split(' ')[0];
  return value;
}

function formatTimePart(value, locale = 'nl-NL') {
  try {
    if (!value) return '—';
    const d = new Date(value);
    if (isNaN(d)) return '—';
    return d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
  } catch (e) {
    return '—';
  }
}

function makeSafeId(input) {
  // maak een geldige id (geen spaties/speciale tekens)
  return String(input).replace(/[^a-z0-9_\-]/gi, '-');
}

// Toggle functie voor collapsible date-groepen
function toggleCompletedDate(dateKey) {
  const safe = makeSafeId(dateKey);
  const el = document.getElementById(`tasks-${safe}`);
  const icon = document.getElementById(`toggle-${safe}`);
  if (!el) return;
  const isHidden = el.style.display === 'none' || getComputedStyle(el).display === 'none';
  el.style.display = isHidden ? '' : 'none';
  if (icon) {
    icon.classList.toggle('fa-chevron-down', !isHidden);
    icon.classList.toggle('fa-chevron-up', isHidden);
  }
}

// Main display function: verwacht een array tasks
function displayCompletedTasks(tasks) {
  const container = document.getElementById('completed-tasks-list'); // let op: singular id

  if (!container) {
    console.error('Container element #completed-task-list niet gevonden');
    return;
  }

  if (!tasks || tasks.length === 0) {
    container.innerHTML = '<p>Geen voltooide taken in deze periode.</p>';
    return;
  }

  // Group tasks by date (use submitted_at or last_submitted_at as fallback)
  const tasksByDate = {};
  tasks.forEach(task => {
    // Veilig bepalen van datumveld (submitted_at of last_submitted_at)
    const submitted = task.submitted_at || task.last_submitted_at || task.completed_at || task.next_available || null;
    const datePart = submitted ? formatDatePart(submitted) : 'Onbekend';
    if (!tasksByDate[datePart]) tasksByDate[datePart] = [];
    tasksByDate[datePart].push(task);
  });

  // Sort dates descending (gebruikt Date parse; 'Onbekend' blijft achteraan)
  const sortedDates = Object.keys(tasksByDate).sort((a, b) => {
    if (a === 'Onbekend') return 1;
    if (b === 'Onbekend') return -1;
    return new Date(b) - new Date(a);
  });

  let html = '';

  sortedDates.forEach(date => {
    const dateTasks = tasksByDate[date];
    // maak veilige id key
    const safeKey = makeSafeId(date);
    const formattedDate = date === 'Onbekend' ? 'Onbekende datum' : new Date(date).toLocaleDateString('nl-NL', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });

    html += `
      <div class="task-card rounded-2xl p-4 sm:p-6 border-l-4 border-green-400 mb-4">
        <div class="flex items-center justify-between mb-4 cursor-pointer" onclick="toggleCompletedDate('${date}')">
          <h3 class="text-base sm:text-lg font-bold text-gray-800 responsive-text-lg flex items-center">
            <div class="w-6 h-6 bg-gradient-to-br from-green-500 to-green-600 rounded-lg mr-2 flex items-center justify-center">
              <i class="fas fa-calendar text-white text-xs"></i>
            </div>
            ${escapeHtml(formattedDate)}
            <span class="ml-2 bg-green-200 text-green-800 px-3 py-1 rounded-full text-sm font-medium">${dateTasks.length} taken</span>
          </h3>
          <i id="toggle-${safeKey}" class="fas fa-chevron-down text-gray-500 transition-transform"></i>
        </div>

        <div id="tasks-${safeKey}" class="space-y-3">
    `;

    dateTasks.forEach(task => {
      // veilig bepalen van voltooiingstijd
      const submitted = task.submitted_at || task.last_submitted_at || task.completed_at || null;
      const completedTime = submitted ? formatTimePart(submitted) : '—';
      const nextAvailable = task.next_available ? formatDatePart(task.next_available) + ' ' + (new Date(task.next_available).toLocaleTimeString('nl-NL', {hour:'2-digit', minute:'2-digit'})) : '—';
      const isAvailable = !!task.is_available;

      html += `
        <div class="bg-white rounded-xl p-4 border border-gray-200 hover:shadow-md transition-shadow">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center mb-2">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                <h4 class="font-medium text-gray-800">${escapeHtml(task.task_description || 'Onbekend')}</h4>
              </div>
              <div class="text-sm text-gray-600 space-y-1">
                <div class="flex items-center">
                  <i class="fas fa-store text-gray-400 mr-2 w-4"></i>
                  <span>${escapeHtml(task.store_name || ('Winkel ' + (task.store_id ?? '—')))}</span>
                </div>
                <div class="flex items-center">
                  <i class="fas fa-user text-gray-400 mr-2 w-4"></i>
                  <span>${escapeHtml(task.manager_name || task.manager_id || '—')}</span>
                </div>
                <div class="flex items-center">
                  <i class="fas fa-clock text-gray-400 mr-2 w-4"></i>
                  <span>Voltooid om ${escapeHtml(completedTime)}</span>
                </div>
                ${task.duration ? `
                  <div class="flex items-center">
                    <i class="fas fa-hourglass-half text-gray-400 mr-2 w-4"></i>
                    <span>${escapeHtml(task.duration)} minuten</span>
                  </div>
                ` : ''}
                <div class="flex items-center">
                  <i class="fas fa-sync-alt text-blue-400 mr-2 w-4"></i>
                  <span class="text-blue-600 font-medium">
                    ${calculateResetTime(task)}
                  </span>
                </div>
              </div>
            </div>
            <div class="ml-4 text-right">
              <div class="text-xs text-gray-500">
                ${escapeHtml(task.frequency || '')}
              </div>
              ${task.required ? '<div class="text-xs text-orange-600 font-medium">Verplicht</div>' : ''}
            </div>
          </div>
        </div>
      `;
    });

    html += `
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}
// Helpers
function parseDbDate(value) {
  if (!value) return null;
  // accepteer 'YYYY-MM-DD HH:MM:SS' en ISO 'YYYY-MM-DDTHH:MM:SS+02:00'
  try {
    // If it already has a 'T' or timezone, let Date parse it
    if (value.indexOf('T') !== -1 || value.indexOf('+') !== -1 || value.indexOf('Z') !== -1) {
      const d = new Date(value);
      if (!isNaN(d)) return d;
    }
    // convert 'YYYY-MM-DD HH:MM:SS' -> 'YYYY-MM-DDTHH:MM:SS'
    const iso = value.replace(' ', 'T');
    const d2 = new Date(iso);
    if (!isNaN(d2)) return d2;
  } catch (e) {
    // fallback below
  }
  // Last resort: try Date constructor
  const d = new Date(value);
  return isNaN(d) ? null : d;
}

function addDays(date, days) {
  const d = new Date(date.getTime());
  d.setDate(d.getDate() + days);
  return d;
}

function addMonths(date, months) {
  const d = new Date(date.getTime());
  const day = d.getDate();
  d.setMonth(d.getMonth() + months);

  // handle month overflow (e.g., 31 Jan + 1 month -> 3 Mar), correct by stepping back to last day of month
  if (d.getDate() < day) {
    d.setDate(0); // last day of previous month
  }
  return d;
}

function formatDutchDatetime(date) {
  if (!date) return '—';
  try {
    return date.toLocaleString('nl-NL', {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return date.toISOString();
  }
}

// belangrijkste functie: return string en optioneel update task.is_available
function calculateResetTime(task) {
  // mapping: frequency -> {type:'days'|'months', value: n}
  const map = {
    'dagelijks': { type: 'days', value: 1 },
    'daily': { type: 'days', value: 1 },
    'wekelijks': { type: 'days', value: 7 },
    'weekly': { type: 'days', value: 7 },
    'tweewekelijks': { type: 'days', value: 14 },
    'biweekly': { type: 'days', value: 14 },
    'twee-wekelijks': { type: 'days', value: 14 },
    'maandelijks': { type: 'months', value: 1 },
    'monthly': { type: 'months', value: 1 }
  };

  // prefer last_submitted_at, fallback to submitted_at, fallback to next_available (from backend)
  const lastStr = (task.last_submitted_at || task.submitted_at || task.next_available || null);
  const freqRaw = (task.frequency || '').toString().trim().toLowerCase();

  // if backend already provided next_available, use it as authoritative if present and valid
  if (task.next_available) {
    const parsedNextBackend = parseDbDate(task.next_available);
    if (parsedNextBackend) {
      const now = new Date();
      const isAvail = parsedNextBackend <= now;
      // update flag if you want
      task.is_available = isAvail;
      return isAvail ? 'Nu beschikbaar' : 'Beschikbaar vanaf ' + formatDutchDatetime(parsedNextBackend);
    }
  }

  // if no last submission date -> consider available (business choice)
  const lastDate = parseDbDate(lastStr);
  if (!lastDate) {
    task.is_available = true;
    return 'Nu beschikbaar';
  }

  const freq = map[freqRaw];
  if (!freq) {
    // onbekende frequency => gebruik backend next_available als fallback of toon last_submitted
    task.is_available = true;
    return 'Beschikbaarheid onbekend';
  }

  let nextDate = null;
  if (freq.type === 'days') {
    nextDate = addDays(lastDate, freq.value);
  } else if (freq.type === 'months') {
    nextDate = addMonths(lastDate, freq.value);
  }

  if (!nextDate) {
    task.is_available = true;
    return 'Beschikbaarheid onbekend';
  }

  const now = new Date();
  const isAvailable = nextDate <= now;
  task.is_available = isAvailable;

  return isAvailable ? 'Nu beschikbaar' : 'Beschikbaar vanaf ' + formatDutchDatetime(nextDate);
}

  function toggleCompletedDate(date) {
    const tasksDiv = document.getElementById(`tasks-${date}`);
    const toggleIcon = document.getElementById(`toggle-${date}`);

    if (tasksDiv.style.display === 'none') {
      tasksDiv.style.display = 'block';
      toggleIcon.classList.remove('fa-chevron-right');
      toggleIcon.classList.add('fa-chevron-down');
    } else {
      tasksDiv.style.display = 'none';
      toggleIcon.classList.remove('fa-chevron-down');
      toggleIcon.classList.add('fa-chevron-right');
    }
  }

  function updateCompletedStatistics(stats) {
    document.getElementById('total-completed-tasks').textContent = stats.total || 0;
    document.getElementById('week-completed-tasks').textContent = stats.this_week || 0;
    document.getElementById('today-completed-tasks').textContent = stats.today || 0;
    document.getElementById('avg-completed-tasks').textContent = stats.average_per_day || 0;
  }

  function showEmptyCompletedTasks() {
    const container = document.getElementById('completed-tasks-list');
    container.innerHTML = `
      <div class="text-center text-gray-500 py-8">
        <div class="w-16 h-16 bg-gradient-to-br from-gray-100 to-gray-200 rounded-2xl mx-auto mb-4 flex items-center justify-center">
          <i class="fas fa-check-circle text-2xl text-gray-400"></i>
        </div>
        <p class="font-medium">Geen voltooide taken gevonden</p>
        <p class="text-sm">Voltooide taken verschijnen hier automatisch</p>
      </div>
    `;

    // Reset statistics
    updateCompletedStatistics({});
  }

function calculateResetTime(task) {
  const map = {
    'dagelijks': { type: 'days', value: 1 },
    'daily': { type: 'days', value: 1 },
    'wekelijks': { type: 'days', value: 7 },
    'weekly': { type: 'days', value: 7 },
    'tweewekelijks': { type: 'days', value: 14 },
    'biweekly': { type: 'days', value: 14 },
    'twee-wekelijks': { type: 'days', value: 14 },
    'maandelijks': { type: 'months', value: 1 },
    'monthly': { type: 'months', value: 1 }
  };

  const lastStr = task.last_submitted_at || task.submitted_at || null;
  const freqRaw = (task.frequency || '').toString().trim().toLowerCase();

  // als er geen frequency of geen datum is → meteen fallback
  if (!lastStr || !map[freqRaw]) {
    return 'Reset tijd onbekend';
  }

  const lastDate = parseDbDate(lastStr);
  if (!lastDate) {
    return 'Reset tijd onbekend';
  }

  let nextDate;
  if (map[freqRaw].type === 'days') {
    nextDate = addDays(lastDate, map[freqRaw].value);
  } else {
    nextDate = addMonths(lastDate, map[freqRaw].value);
  }

  if (!nextDate || isNaN(nextDate.getTime())) {
    return 'Reset tijd onbekend';
  }

  const now = new Date();
  if (nextDate <= now) {
    return 'Nu beschikbaar';
  }

  return 'Beschikbaar vanaf ' + formatDutchDatetime(nextDate);
}

  function clearCompletedFilters() {
    // Reset date filters to last 7 days
    const today = new Date();
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);

    document.getElementById('completedDateFrom').value = weekAgo.toISOString().split('T')[0];
    document.getElementById('completedDateTo').value = today.toISOString().split('T')[0];
    document.getElementById('completedStoreFilter').value = '';

    // Reload tasks
    loadCompletedTasks();
  }
  </script>
</body>
</html>

