<?php
// check_current_status.php - Bekijk huidige regio configuratie

try {
    $db_path = '../db/tasks.db';
    
    if (!file_exists($db_path)) {
        $db_path = 'C:\\xampp\\htdocs\\secure_nyp\\app\\db\\tasks.db';
    }
    
    echo "📊 HUIDIGE REGIO STATUS CHECK\\n";
    echo "============================\\n\\n";
    
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Bekijk alle regio's
    echo "1. REGIO'S IN DATABASE:\\n";
    $regions = $db->query("SELECT id, name FROM regions ORDER BY id")->fetchAll();
    foreach ($regions as $region) {
        echo "   • ID {$region['id']}: {$region['name']}\\n";
    }
    
    // 2. Bekijk alle winkels
    echo "\\n2. WINKELS IN DATABASE:\\n";
    $stores = $db->query("SELECT id, name FROM stores ORDER BY id")->fetchAll();
    foreach ($stores as $store) {
        echo "   • ID {$store['id']}: {$store['name']}\\n";
    }
    
    // 3. Bekijk huidige koppelingen
    echo "\\n3. HUIDIGE REGIO-WINKEL KOPPELINGEN:\\n";
    $links = $db->query("
        SELECT r.name as region_name, s.name as store_name 
        FROM region_stores rs 
        JOIN regions r ON rs.region_id = r.id 
        JOIN stores s ON rs.store_id = s.id 
        ORDER BY r.name, s.name
    ")->fetchAll();
    
    if ($links) {
        $current_region = '';
        foreach ($links as $link) {
            if ($current_region !== $link['region_name']) {
                $current_region = $link['region_name'];
                echo "\\n   🏢 {$current_region}:\\n";
            }
            echo "      • {$link['store_name']}\\n";
        }
    } else {
        echo "   ❌ Geen koppelingen gevonden\\n";
    }
    
    // 4. Bekijk regiomanager accounts
    echo "\\n4. REGIOMANAGER ACCOUNTS:\\n";
    $managers = $db->query("
        SELECT u.username, u.email, r.name as region_name 
        FROM users u 
        LEFT JOIN regions r ON u.region_id = r.id 
        WHERE u.role = 'regiomanager' 
        ORDER BY r.name
    ")->fetchAll();
    
    if ($managers) {
        foreach ($managers as $manager) {
            $region = $manager['region_name'] ?? 'Geen regio';
            echo "   👤 {$manager['username']} ({$manager['email']}) → {$region}\\n";
        }
    } else {
        echo "   ❌ Geen regiomanagers gevonden\\n";
    }
    
    // 5. Bekijk alle users met hun regio
    echo "\\n5. ALLE USERS MET REGIO INFO:\\n";
    $all_users = $db->query("
        SELECT u.username, u.role, r.name as region_name, u.store_id
        FROM users u 
        LEFT JOIN regions r ON u.region_id = r.id 
        ORDER BY u.role, u.username
    ")->fetchAll();
    
    $current_role = '';
    foreach ($all_users as $user) {
        if ($current_role !== $user['role']) {
            $current_role = $user['role'];
            echo "\\n   📋 {$current_role}s:\\n";
        }
        $region = $user['region_name'] ?? 'Geen regio';
        $store = $user['store_id'] ? " (Store ID: {$user['store_id']})" : '';
        echo "      • {$user['username']} → {$region}{$store}\\n";
    }
    
    // 6. Probleem diagnose
    echo "\\n🔍 PROBLEEM DIAGNOSE:\\n";
    echo "====================\\n";
    
    // Check of alle regio's winkels hebben
    foreach ($regions as $region) {
        $store_count = $db->prepare("
            SELECT COUNT(*) 
            FROM region_stores rs 
            WHERE rs.region_id = ?
        ");
        $store_count->execute([$region['id']]);
        $count = $store_count->fetchColumn();
        
        if ($count == 0) {
            echo "⚠️  Regio '{$region['name']}' heeft geen winkels gekoppeld\\n";
        } else {
            echo "✅ Regio '{$region['name']}' heeft $count winkel(s)\\n";
        }
    }
    
    // Check of alle regiomanagers een regio hebben
    $managers_without_region = $db->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE role = 'regiomanager' AND region_id IS NULL
    ")->fetchColumn();
    
    if ($managers_without_region > 0) {
        echo "⚠️  $managers_without_region regiomanager(s) hebben geen regio toegewezen\\n";
    } else {
        echo "✅ Alle regiomanagers hebben een regio\\n";
    }
    
} catch (Exception $e) {
    echo "❌ FOUT: " . $e->getMessage() . "\\n";
}
?>