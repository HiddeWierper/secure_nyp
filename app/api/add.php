<?php
// update_database.php - Run this once to add region_id to users table

try {
    $db = new PDO('sqlite:db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if region_id column already exists
    $stmt = $db->query("PRAGMA table_info(users)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasRegionId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'region_id') {
            $hasRegionId = true;
            break;
        }
    }
    
    if (!$hasRegionId) {
        // Add region_id column to users table
        $db->exec("ALTER TABLE users ADD COLUMN region_id INTEGER");
        echo "Added region_id column to users table\\n";
        
        // Update existing regiomanager users to have region_id = 1 (you can change this)
        $db->exec("UPDATE users SET region_id = 1 WHERE role = 'regiomanager'");
        echo "Updated existing regiomanagers to region_id = 1\\n";
        
        echo "Database update completed successfully!\\n";
    } else {
        echo "region_id column already exists in users table\\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\\n";
}
?>
