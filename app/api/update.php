<?php
// session_fix.php - Temporary fix to set region_id in current session
session_start();

// Check if user is logged in as regiomanager
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'regiomanager') {
    echo "This fix is only for regiomanagers. Current role: " . ($_SESSION['user_role'] ?? 'not set');
    exit;
}

// Set region_id to 1 (you can change this based on your needs)
$_SESSION['region_id'] = 1;

echo "Session fix applied! region_id set to: " . $_SESSION['region_id'];
echo "<br><br>";
echo "Current session data:<br>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "<br><a href='/'>← Go back to home</a>";
?>