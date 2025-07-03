<?php
if (!session_id()) if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

if ($userRole === 'manager') {
    http_response_code(403);
    echo json_encode(['error' => 'Toegang geweigerd: managers mogen geen taken genereren.']);
    exit;
}

header('Content-Type: application/json');

$dbFile = __DIR__ . '/../db/tasks.db';
if (!file_exists($dbFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database niet gevonden. Run init_db.php eerst.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$manager = $data['manager'] ?? '';
$day = $data['day'] ?? '';
$storeId = $data['store_id'] ?? null; // Nieuwe parameter voor winkel selectie
$maxDuration = isset($data['maxDuration']) ? (int)$data['maxDuration'] : 90; // standaard 90 min

if (empty($manager) || empty($day)) {
    echo json_encode(['error' => 'Manager en dag zijn verplicht']);
    exit;
}

if (empty($storeId)) {
    echo json_encode(['error' => 'Winkel selectie is verplicht']);
    exit;
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Haal gebruiker info op inclusief regio
    $user_stmt = $db->prepare("
        SELECT u.*, r.name as region_name 
        FROM users u 
        LEFT JOIN regions r ON u.region_id = r.id 
        WHERE u.id = ?
    ");
    $user_stmt->execute([$userId]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Gebruiker niet gevonden']);
        exit;
    }

    // Controleer of de gebruiker toegang heeft tot de geselecteerde winkel
    $hasAccess = false;
    
    if ($user['role'] === 'admin') {
        // Admin heeft toegang tot alle winkels
        $hasAccess = true;
    } elseif ($user['role'] === 'regiomanager') {
        // Regiomanager heeft alleen toegang tot winkels in zijn regio
        if (!$user['region_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Regiomanager heeft geen regio toegewezen']);
            exit;
        }
        
        $access_check = $db->prepare("
            SELECT COUNT(*) 
            FROM region_stores rs 
            WHERE rs.region_id = ? AND rs.store_id = ?
        ");
        $access_check->execute([$user['region_id'], $storeId]);
        $hasAccess = $access_check->fetchColumn() > 0;
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen toegang tot deze winkel']);
        exit;
    }

    // Controleer of de geselecteerde winkel bestaat
    $store_check = $db->prepare("SELECT name FROM stores WHERE id = ?");
    $store_check->execute([$storeId]);
    $store = $store_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        http_response_code(404);
        echo json_encode(['error' => 'Winkel niet gevonden']);
        exit;
    }

    // Haal alle taken op
    $stmt = $db->query("SELECT * FROM tasks");
    $allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Functie om te controleren of een taak recent is voltooid (door ELKE manager in DEZE winkel)
    function isTaskRecentlyCompleted($db, $taskId, $frequency, $storeId) {
        if ($frequency === 'dagelijks') {
            return false; // Dagelijkse taken zijn altijd beschikbaar
        }
        
        // Bepaal hoeveel dagen terug we moeten kijken
        $daysBack = match($frequency) {
            'wekelijks' => 7,
            '2-wekelijks' => 14,
            'maandelijks' => 30,
            default => 0
        };
        
        if ($daysBack === 0) return false;
        
        // Zoek naar voltooide taken binnen de periode voor deze specifieke winkel
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysBack} days"));
        
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM task_set_items tsi
            JOIN task_sets ts ON tsi.task_set_id = ts.id
            WHERE tsi.task_id = :task_id 
            AND tsi.completed = 1 
            AND ts.store_id = :store_id
            AND ts.created_at >= :cutoff_date
        ");
        
        $stmt->execute([
            ':task_id' => $taskId,
            ':store_id' => $storeId,
            ':cutoff_date' => $cutoffDate
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    // Filter taken op basis van frequentie en recent voltooide taken voor deze winkel
    $filteredTasks = [];
    foreach ($allTasks as $task) {
        if (!isTaskRecentlyCompleted($db, $task['id'], $task['frequency'], $storeId)) {
            $filteredTasks[] = $task;
        }
    }

    // Debug info (optioneel - verwijder in productie)
    $debugInfo = [];
    foreach ($allTasks as $task) {
        $isBlocked = isTaskRecentlyCompleted($db, $task['id'], $task['frequency'], $storeId);
        $debugInfo[] = [
            'task' => $task['name'],
            'frequency' => $task['frequency'],
            'blocked' => $isBlocked,
            'store_specific' => true
        ];
    }

    // Voeg verplichte taken altijd toe (ook als ze recent zijn voltooid)
    $requiredTasks = array_filter($allTasks, fn($t) => $t['required'] == 1);

    // Voor optionele taken, gebruik gefilterde lijst
    $optionalTasks = array_filter($filteredTasks, fn($t) => $t['required'] == 0);
    shuffle($optionalTasks);

    $selectedTasks = [];
    $totalTime = 0;
    $minTime = $maxDuration - 15;

    // Voeg verplichte taken toe
    foreach ($requiredTasks as $task) {
        $selectedTasks[] = $task;
        $totalTime += $task['time'];
    }

    // Voeg optionele taken toe tot max 6 taken of max tijd
    foreach ($optionalTasks as $task) {
        if (count($selectedTasks) >= 6) break;
        if ($totalTime + $task['time'] <= $maxDuration) {
            $selectedTasks[] = $task;
            $totalTime += $task['time'];
        }
    }

    // Check minimum tijd, voeg kleine taken toe indien nodig
    if ($totalTime < $minTime) {
        foreach ($optionalTasks as $task) {
            if (!in_array($task, $selectedTasks) && $task['time'] <= 15 && $totalTime + $task['time'] <= $maxDuration) {
                $selectedTasks[] = $task;
                $totalTime += $task['time'];
                break;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'tasks' => $selectedTasks,
        'total_time' => $totalTime,
        'store_info' => [
            'id' => $storeId,
            'name' => $store['name']
        ],
        'user_info' => [
            'role' => $user['role'],
            'region' => $user['region_name']
        ],
        'debug' => $debugInfo // Verwijder deze regel in productie
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>