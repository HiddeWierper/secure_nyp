<?php
// migrate_add_store_id.php
// Dit script voegt de kolommen store_id toe aan task_sets en users tabellen
// en maakt de stores tabel aan als die nog niet bestaat.
// Voer dit script één keer uit via browser of CLI.

try {
    $db = new PDO('sqlite:' . __DIR__ . '/../db/tasks.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Maak stores tabel aan als die nog niet bestaat
    $db->exec("
        CREATE TABLE IF NOT EXISTS stores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        );
    ");

    // Voeg store_id toe aan task_sets als kolom nog niet bestaat
    $columns = $db->query("PRAGMA table_info(task_sets)")->fetchAll(PDO::FETCH_ASSOC);
    $hasStoreId = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'store_id') {
            $hasStoreId = true;
            break;
        }
    }
    if (!$hasStoreId) {
        $db->exec("ALTER TABLE task_sets ADD COLUMN store_id INTEGER DEFAULT NULL;");
        echo "Kolom store_id toegevoegd aan task_sets.<br>";
    } else {
        echo "Kolom store_id bestaat al in task_sets.<br>";
    }

    // Voeg store_id toe aan users als kolom nog niet bestaat
    $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $hasStoreIdUsers = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'store_id') {
            $hasStoreIdUsers = true;
            break;
        }
    }
    if (!$hasStoreIdUsers) {
        $db->exec("ALTER TABLE users ADD COLUMN store_id INTEGER DEFAULT NULL;");
        echo "Kolom store_id toegevoegd aan users.<br>";
    } else {
        echo "Kolom store_id bestaat al in users.<br>";
    }

    // Optioneel: voeg voorbeeld winkels toe als stores tabel leeg is
    $countStores = $db->query("SELECT COUNT(*) FROM stores")->fetchColumn();
    if ($countStores == 0) {
        $db->exec("
            INSERT INTO stores (name) VALUES
            ('Winkel Amsterdam'),
            ('Winkel Rotterdam'),
            ('Winkel Utrecht');
        ");
        echo "Voorbeeld winkels toegevoegd.<br>";
    } else {
        echo "Stores tabel bevat al data.<br>";
    }

    echo "<br><strong>Migratie voltooid.</strong><br>";
    echo "Vergeet niet om gebruikers en task_sets te koppelen aan winkels via store_id.<br>";

} catch (PDOException $e) {
    echo "Fout bij migratie: " . htmlspecialchars($e->getMessage());
}