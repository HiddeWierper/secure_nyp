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
$storeId = $data['storeSelect'] ?? null;
$maxDuration = isset($data['maxDuration']) ? (int)$data['maxDuration'] : 90;
$includeBkTasks = isset($data['includeBkTasks']) ? (bool)$data['includeBkTasks'] : false; // NIEUW: BK taken checkbox

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

    // Controleer toegang tot winkel (bestaande code blijft hetzelfde)
    $hasAccess = false;

    if ($user['role'] === 'admin') {
        $hasAccess = true;
    } elseif ($user['role'] === 'developer') {
        $hasAccess = true;
    } elseif ($user['role'] === 'regiomanager') {
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
    } elseif ($user['role'] === 'storemanager') {
        if (!$user['store_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Storemanager heeft geen winkel toegewezen']);
            exit;
        }

        $hasAccess = ($user['store_id'] == $storeId);
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['error' => 'Geen toegang tot deze winkel']);
        exit;
    }

    // Controleer of de geselecteerde winkel bestaat en haal is_bk status op
    $store_check = $db->prepare("SELECT name, is_bk FROM stores WHERE id = ?");
    $store_check->execute([$storeId]);
    $store = $store_check->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        http_response_code(404);
        echo json_encode(['error' => 'Winkel niet gevonden']);
        exit;
    }

    // AANGEPAST: Haal taken op gebaseerd op winkel BK status
    $taskQuery = "SELECT * FROM tasks";
    if ($store['is_bk']) {
        // Als winkel BK is, haal alle taken op (inclusief BK taken)
        // Geen extra filter nodig
    } else {
        // Als winkel niet BK is, alleen niet-BK taken
        $taskQuery .= " WHERE is_bk = 0";
    }

    $stmt = $db->query($taskQuery);
    $allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Functie om te controleren of een taak recent is voltooid
    function isTaskRecentlyCompleted($db, $taskId, $frequency, $storeId) {
        if ($frequency === 'dagelijks') {
            return false;
        }

        $daysBack = match($frequency) {
            'wekelijks' => 7,
            '2-wekelijks' => 14,
            'maandelijks' => 30,
            default => 0
        };

        if ($daysBack === 0) return false;

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

    // Functie om te controleren of een taak is toegewezen maar nog niet voltooid (ongeacht wanneer)
    function hasIncompleteTask($db, $taskId, $storeId) {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM task_set_items tsi
            JOIN task_sets ts ON tsi.task_set_id = ts.id
            WHERE tsi.task_id = :task_id
            AND tsi.completed = 0
            AND ts.store_id = :store_id
        ");

        $stmt->execute([
            ':task_id' => $taskId,
            ':store_id' => $storeId
        ]);

        return $stmt->fetchColumn() > 0;
    }

    // Filter taken op basis van frequentie en recent voltooide taken
    $filteredTasks = [];
    $priorityTasks = []; // Taken die zijn toegewezen maar nog niet voltooid krijgen prioriteit

    foreach ($allTasks as $task) {
        // Check eerst of er onvoltooide taken zijn - deze hebben altijd prioriteit
        if (hasIncompleteTask($db, $task['id'], $storeId)) {
            $filteredTasks[] = $task;
            $priorityTasks[] = $task; // Onvoltooide taken krijgen altijd prioriteit
        } elseif (!isTaskRecentlyCompleted($db, $task['id'], $task['frequency'], $storeId)) {
            // Alleen als er geen onvoltooide taken zijn, kijk naar frequentie
            $filteredTasks[] = $task;
        }
    }

    // Debug info met BK status
    $debugInfo = [];
    foreach ($allTasks as $task) {
        $isBlocked = isTaskRecentlyCompleted($db, $task['id'], $task['frequency'], $storeId);
        $hasIncomplete = hasIncompleteTask($db, $task['id'], $storeId);
        $debugInfo[] = [
            'task' => $task['name'],
            'frequency' => $task['frequency'],
            'blocked' => $isBlocked,
            'is_bk' => (bool)$task['is_bk'],
            'has_incomplete' => $hasIncomplete, // NIEUW: of er onvoltooide taken zijn
            'priority' => $hasIncomplete, // Prioriteit gebaseerd op onvoltooide taken
            'store_specific' => true
        ];
    }

    // Voeg verplichte taken altijd toe
    $requiredTasks = array_filter($allTasks, fn($t) => $t['required'] == 1);

    // Splits optionele taken in prioriteit en normale taken
    $optionalPriorityTasks = array_filter($priorityTasks, fn($t) => $t['required'] == 0);
    $optionalNormalTasks = array_filter($filteredTasks, fn($t) => $t['required'] == 0 && !in_array($t, $priorityTasks));

    // Shuffle alleen de normale taken, prioriteit taken blijven vooraan
    shuffle($optionalNormalTasks);

    $selectedTasks = [];
    $totalTime = 0;
    $minTime = $maxDuration - 15;

    // Voeg verplichte taken toe
    foreach ($requiredTasks as $task) {
        $selectedTasks[] = $task;
        $totalTime += $task['time'];
    }

    // Voeg eerst prioriteit taken toe (gisteren niet voltooid)
    foreach ($optionalPriorityTasks as $task) {
        if (count($selectedTasks) >= 6) break;
        if ($totalTime + $task['time'] <= $maxDuration) {
            $selectedTasks[] = $task;
            $totalTime += $task['time'];
        }
    }

    // Voeg daarna normale optionele taken toe tot max 6 taken of max tijd
    foreach ($optionalNormalTasks as $task) {
        if (count($selectedTasks) >= 6) break;
        if ($totalTime + $task['time'] <= $maxDuration) {
            $selectedTasks[] = $task;
            $totalTime += $task['time'];
        }
    }

    // Check minimum tijd, voeg kleine taken toe indien nodig
    if ($totalTime < $minTime) {
        // Probeer eerst prioriteit taken
        foreach ($optionalPriorityTasks as $task) {
            if (!in_array($task, $selectedTasks) && $task['time'] <= 15 && $totalTime + $task['time'] <= $maxDuration) {
                $selectedTasks[] = $task;
                $totalTime += $task['time'];
                break;
            }
        }

        // Als nog steeds onder minimum, probeer normale taken
        if ($totalTime < $minTime) {
            foreach ($optionalNormalTasks as $task) {
                if (!in_array($task, $selectedTasks) && $task['time'] <= 15 && $totalTime + $task['time'] <= $maxDuration) {
                    $selectedTasks[] = $task;
                    $totalTime += $task['time'];
                    break;
                }
            }
        }
    }

    // NIEUW: Tel BK taken in de selectie
    $bkTaskCount = count(array_filter($selectedTasks, fn($t) => $t['is_bk'] == 1));
    $priorityTaskCount = count(array_filter($selectedTasks, fn($t) => in_array($t, $priorityTasks)));

    echo json_encode([
        'success' => true,
        'tasks' => $selectedTasks,
        'total_time' => $totalTime,
        'bk_tasks_included' => $includeBkTasks, // NIEUW: of BK taken zijn meegenomen
        'bk_task_count' => $bkTaskCount, // NIEUW: aantal BK taken in selectie
        'priority_task_count' => $priorityTaskCount, // NIEUW: aantal prioriteit taken
        'store_info' => [
            'id' => $storeId,
            'name' => $store['name']
        ],
        'user_info' => [
            'role' => $user['role'],
            'region' => $user['region_name']
        ],
        'debug' => $debugInfo
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>