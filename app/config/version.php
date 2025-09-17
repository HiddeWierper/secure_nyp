<?php
function getVersion() {
    $versionFile = __DIR__ . '/version.json';
    $defaultVersion = 'v3.27.16';
    
    if (file_exists($versionFile)) {
        $versionData = json_decode(file_get_contents($versionFile), true);
        return $versionData['version'] ?? $defaultVersion;
    }
    
    return $defaultVersion;
}

function setVersion($version) {
    $versionFile = __DIR__ . '/version.json';
    $configDir = dirname($versionFile);
    
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    $versionData = [
        'version' => $version,
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['user_name'] ?? $_SESSION['user_id'] ?? 'system'
    ];
    
    return file_put_contents($versionFile, json_encode($versionData, JSON_PRETTY_PRINT)) !== false;
}
?>