<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// API endpoint to get regions and stores (SQLite)
// Note: we intentionally do NOT return or echo the DB filename/path to clients.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && strpos($_SERVER['REQUEST_URI'], '/api/get_regions_and_stores') !== false) {
    header('Content-Type: application/json; charset=utf-8');

    // Require login
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $userRole = $_SESSION['user_role'] ?? '';
    if ($userRole !== 'regiomanager' && $userRole !== 'developer' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        // Configurable DB path (set DB_PATH env var in production). Default internal fallback (server-side only).
        $dbPath = getenv('DB_PATH') ?: __DIR__ . '/../db/tasks.db';
        if (!file_exists($dbPath) || !is_readable($dbPath)) {
            // Generic client error; log details server-side
            error_log('Database file missing or not readable (get_regions_and_stores)');
            echo json_encode(['success' => false, 'error' => 'Internal server error', 'regions' => [], 'stores' => []]);
            exit;
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // If user is regiomanager, try to get their region_id (session or users table)
        $regionFilter = null;
        if ($userRole === 'regiomanager') {
            if (!empty($_SESSION['region_id'])) {
                $regionFilter = (int) $_SESSION['region_id'];
            } else {
                // fallback: query users table for region_id (do not reveal DB path to client)
                $stmt = $pdo->prepare("SELECT region_id FROM users WHERE id = :uid LIMIT 1");
                $stmt->execute([':uid' => $_SESSION['user_id']]);
                $regionFilter = $stmt->fetchColumn();
                if ($regionFilter !== false) $regionFilter = (int) $regionFilter;
                else $regionFilter = null;
            }
        }

        // Build regions list using regions table (preferred) as it contains names
        $regions = [];
        $regionsStmt = $pdo->query("SELECT id AS region_id, name AS region_name FROM regions ORDER BY name");
        if ($regionsStmt) {
            $regions = $regionsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Build stores list — join with regions for region_name if possible
        $sql = "SELECT s.id, s.name, s.address, s.region_id, r.name AS region_name
                FROM stores s
                LEFT JOIN regions r ON s.region_id = r.id";
        $params = [];

        if ($regionFilter) {
            $sql .= " WHERE s.region_id = :region_id";
            $params[':region_id'] = $regionFilter;
        }

        $sql .= " ORDER BY s.name COLLATE NOCASE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'regions' => $regions, 'stores' => $stores], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        // Log full error server-side, return generic error to client
        error_log('Database error in get_regions_and_stores: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error', 'regions' => [], 'stores' => []]);
        exit;
    }
}
?>