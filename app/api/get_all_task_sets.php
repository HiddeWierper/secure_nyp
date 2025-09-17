<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $storeId = null;
    $params = [];
    $sql = "SELECT * FROM task_sets ";

    if ($userRole === 'manager' || $userRole === 'storemanager') {
        // Manager en storemanager zien alleen taken van hun winkel
        $stmtUser = $db->prepare("SELECT store_id FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $storeId = $stmtUser->fetchColumn();

        if (!$storeId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Geen winkel toegewezen']);
            exit;
        }

        $sql .= "WHERE store_id = ? ORDER BY created_at DESC";
        $params = [$storeId];
    }  elseif ($userRole === 'regiomanager') {
    // Regiomanager ziet taken van alle winkels in zijn regio
    $stmtUser = $db->prepare("SELECT region_id FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $regionId = $stmtUser->fetchColumn();

    if (!$regionId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Geen regio toegewezen']);
        exit;
    }

    // Gebruik region_stores als autoritatieve koppeltabel tussen regio en winkels
    $sql .= "WHERE store_id IN (SELECT store_id FROM region_stores WHERE region_id = ?) ORDER BY created_at DESC";
    $params = [$regionId];
} elseif ($userRole === 'admin' || $userRole === 'developer') {
        // Admin kan filteren op winkel via GET param
        if (isset($_GET['store_id']) && is_numeric($_GET['store_id'])) {
            $storeId = (int)$_GET['store_id'];
            $sql .= "WHERE store_id = ? ORDER BY created_at DESC";
            $params = [$storeId];
        } else {
            $sql .= "ORDER BY created_at DESC";
        }
    } else {
        // Onbekende rol
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Onvoldoende rechten ', 'user_role' => $userRole]);
        exit;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $taskSets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Manager naam en winkel naam ophalen
    foreach ($taskSets as &$taskSet) {
        $stmt2 = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt2->execute([$taskSet['manager']]);
        $managerName = $stmt2->fetchColumn();
        if ($managerName) {
            $taskSet['manager'] = $managerName;
        }

        if (!empty($taskSet['store_id'])) {
            $stmt3 = $db->prepare("SELECT name FROM stores WHERE id = ?");
            $stmt3->execute([$taskSet['store_id']]);
            $storeName = $stmt3->fetchColumn();
            $taskSet['store_name'] = $storeName ?: 'Onbekend';
        } else {
            $taskSet['store_name'] = 'Onbekend';
        }
    }

    echo json_encode(['success' => true, 'task_sets' => $taskSets]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
}