<?php
// update_database.php - Run this once to add region_id to users table

try {
    $db = new PDO('sqlite:../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Add role column to users table if it doesn't exist
    $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'manager' CHECK (role IN ('admin', 'manager', 'regiomanager', 'storemanager'))");
    
    // Update existing users to have storemanager role (optional - you can modify this logic)
    // $db->exec("UPDATE users SET role = 'storemanager' WHERE id = 1"); // Example for specific user
    
    echo "Successfully added role column to users table\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "Role column already exists\n";
    } else if (strpos($e->getMessage(), 'storemanager') !== false) {
        echo "Store manager role already configured\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
