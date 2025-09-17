<?php
// API endpoint for getting completed tasks (with next availability)
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $storeId = isset($_GET['store_id']) ? $_GET['store_id'] : null;
    $userStoreRestriction = isset($_GET['user_store_restriction']) ? $_GET['user_store_restriction'] : null;

    // Main query: include task frequency and last_submitted_at (per task_id + store)
    $query = "SELECT 
                t.id AS task_id,
                t.name AS task_description,
                t.frequency,
                ts.store_id,
                s.name AS store_name,
                ts.manager AS manager_id,
                u.username AS manager_name,
                ts.submitted_at AS submitted_at,
                -- last submitted for this task_id & store (most recent completion)
                (
                    SELECT MAX(ts2.submitted_at)
                    FROM task_set_items tsi2
                    JOIN task_sets ts2 ON tsi2.task_set_id = ts2.id
                    WHERE tsi2.task_id = t.id
                      AND ts2.store_id = ts.store_id
                      AND tsi2.completed = 1
                      AND ts2.submitted = 1
                ) AS last_submitted_at,
                tsi.id AS task_set_item_id,
                t.time AS duration,
                t.required,
                ts.day,
                ts.created_at
              FROM task_set_items tsi
              JOIN task_sets ts ON tsi.task_set_id = ts.id
              JOIN tasks t ON tsi.task_id = t.id
              JOIN stores s ON ts.store_id = s.id
              JOIN users u ON ts.manager = u.id
              WHERE tsi.completed = 1 AND ts.submitted = 1";

    $params = [];

    if ($dateFrom) {
        $query .= " AND DATE(ts.submitted_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo) {
        $query .= " AND DATE(ts.submitted_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    if ($storeId) {
        $query .= " AND ts.store_id = :store_id";
        $params[':store_id'] = $storeId;
    }

    if ($userStoreRestriction) {
        $query .= " AND ts.store_id = :user_store_restriction";
        $params[':user_store_restriction'] = $userStoreRestriction;
    }

    $query .= " ORDER BY ts.submitted_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Frequency -> DateInterval mapping (Nederlandstalige waardes)
    $freq_map = [
        'dagelijks' => 'P1D',
        'wekelijks' => 'P7D',
        'tweewekelijks' => 'P14D',
        'biweekly' => 'P14D',
        'maandelijks' => 'P1M',
        'monthly' => 'P1M'
    ];

    $now = new DateTime(); // current server time
    $tasks = [];

    foreach ($rows as $r) {
        $frequency_raw = isset($r['frequency']) ? trim(strtolower($r['frequency'])) : null;
        $last_submitted = $r['last_submitted_at'];

        $next_available = null;
        $is_available = false;

        if ($last_submitted && isset($freq_map[$frequency_raw])) {
            try {
                // Normalize parsing: submitted_at in DB can be 'YYYY-MM-DD HH:MM:SS' or ISO8601
                $last_dt = new DateTime($last_submitted);
                $intervalSpec = $freq_map[$frequency_raw];
                $interval = new DateInterval($intervalSpec);
                $next_dt = clone $last_dt;
                $next_dt->add($interval);
                $next_available = $next_dt->format('Y-m-d H:i:s');
                $is_available = ($next_dt <= $now);
            } catch (Exception $ex) {
                $next_available = null;
                $is_available = false;
            }
        } else {
            // If never submitted before (no last_submitted), consider it available
            if (!$last_submitted) {
                $is_available = true;
            }
        }

        $r['last_submitted_at'] = $last_submitted;
        $r['next_available'] = $next_available;
        $r['is_available'] = (bool)$is_available;
        $tasks[] = $r;
    }

    // Calculate statistics (optionally reuse your function or call a simpler stats function)
    $statistics = calculateStatistics($db, $dateFrom, $dateTo, $storeId, $userStoreRestriction);

    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'statistics' => $statistics
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function calculateStatistics($db, $dateFrom, $dateTo, $storeId, $userStoreRestriction) {
    try {
        // Base query for statistics
        $baseQuery = "FROM task_set_items tsi 
                      JOIN task_sets ts ON tsi.task_set_id = ts.id 
                      WHERE tsi.completed = 1 AND ts.submitted = 1";
        $params = [];

        // Apply filters
        if ($dateFrom) {
            $baseQuery .= " AND DATE(ts.submitted_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $baseQuery .= " AND DATE(ts.submitted_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        if ($storeId) {
            $baseQuery .= " AND ts.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        if ($userStoreRestriction) {
            $baseQuery .= " AND ts.store_id = :user_store_restriction";
            $params[':user_store_restriction'] = $userStoreRestriction;
        }

        // Total completed tasks
        $totalStmt = $db->prepare("SELECT COUNT(*) as total " . $baseQuery);
        $totalStmt->execute($params);
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Get frequency-based statistics
        $frequencyStats = calculateFrequencyBasedStats($db, $baseQuery, $params);

        // Completed today
        $todayStmt = $db->prepare("SELECT COUNT(*) as today_count " . $baseQuery . " AND DATE(ts.submitted_at) = DATE('now')");
        $todayStmt->execute($params);
        $today = $todayStmt->fetch(PDO::FETCH_ASSOC)['today_count'];

        // Average per day (for the selected period)
        $avgPerDay = 0;
        if ($total > 0) {
            $daysInPeriod = 7; // Default to 7 days
            if ($dateFrom && $dateTo) {
                $start = new DateTime($dateFrom);
                $end = new DateTime($dateTo);
                $daysInPeriod = $end->diff($start)->days + 1;
            }
            $avgPerDay = round($total / $daysInPeriod, 1);
        }

        return [
            'total' => (int)$total,
            'current_period' => $frequencyStats,
            'today' => (int)$today,
            'average_per_day' => $avgPerDay
        ];

    } catch (Exception $e) {
        // Return default statistics if calculation fails
        return [
            'total' => 0,
            'current_period' => [
                'weekly' => 0,
                'biweekly' => 0,
                'monthly' => 0
            ],
            'today' => 0,
            'average_per_day' => 0
        ];
    }
}

function calculateFrequencyBasedStats($db, $baseQuery, $params) {
    try {
        $stats = [
            'weekly' => 0,
            'biweekly' => 0,
            'monthly' => 0
        ];

        // Get tasks with their frequencies and submission dates
        $taskQuery = "SELECT t.frequency, ts.submitted_at, ts.created_at " .
                    str_replace("FROM task_set_items tsi", "FROM task_set_items tsi JOIN tasks t ON tsi.task_id = t.id", $baseQuery);

        $stmt = $db->prepare($taskQuery);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $task) {
            $frequency = strtolower($task['frequency']);
            $submittedAt = new DateTime($task['submitted_at']);
            $createdAt = new DateTime($task['created_at']);
            $now = new DateTime();

            // Calculate if task is in current period based on its frequency and submission pattern
            $isInCurrentPeriod = false;

            switch ($frequency) {
                case 'weekly':
                    // Check if submitted within the last 7 days from the task's creation pattern
                    $daysSinceCreation = $createdAt->diff($now)->days;
                    $weeksSinceCreation = floor($daysSinceCreation / 7);
                    $currentWeekStart = clone $createdAt;
                    $currentWeekStart->add(new DateInterval('P' . ($weeksSinceCreation * 7) . 'D'));
                    $currentWeekEnd = clone $currentWeekStart;
                    $currentWeekEnd->add(new DateInterval('P7D'));

                    if ($submittedAt >= $currentWeekStart && $submittedAt < $currentWeekEnd) {
                        $isInCurrentPeriod = true;
                    }
                    break;

                case 'biweekly':
                    // Check if submitted within the current 2-week period from creation pattern
                    $daysSinceCreation = $createdAt->diff($now)->days;
                    $biweeksSinceCreation = floor($daysSinceCreation / 14);
                    $currentBiweekStart = clone $createdAt;
                    $currentBiweekStart->add(new DateInterval('P' . ($biweeksSinceCreation * 14) . 'D'));
                    $currentBiweekEnd = clone $currentBiweekStart;
                    $currentBiweekEnd->add(new DateInterval('P14D'));

                    if ($submittedAt >= $currentBiweekStart && $submittedAt < $currentBiweekEnd) {
                        $isInCurrentPeriod = true;
                    }
                    break;

                case 'monthly':
                    // Check if submitted within the current month period from creation pattern
                    $monthsSinceCreation = ($now->format('Y') - $createdAt->format('Y')) * 12 + ($now->format('m') - $createdAt->format('m'));
                    $currentMonthStart = clone $createdAt;
                    $currentMonthStart->add(new DateInterval('P' . $monthsSinceCreation . 'M'));
                    $currentMonthEnd = clone $currentMonthStart;
                    $currentMonthEnd->add(new DateInterval('P1M'));

                    if ($submittedAt >= $currentMonthStart && $submittedAt < $currentMonthEnd) {
                        $isInCurrentPeriod = true;
                    }
                    break;
            }

            if ($isInCurrentPeriod && isset($stats[$frequency])) {
                $stats[$frequency]++;
            }
        }

        return $stats;

    } catch (Exception $e) {
        return [
            'weekly' => 0,
            'biweekly' => 0,
            'monthly' => 0
        ];
    }
}
?>