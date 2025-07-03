<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'regiomanager') {
    header('Location: /nyp/login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regiomanager Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Regiomanager Dashboard</h1>
            <div class="space-x-4">
                <span class="text-gray-600">Welkom, <?= htmlspecialchars($_SESSION['username']) ?></span>
                <a href="/nyp/logout" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Uitloggen</a>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button onclick="showTab('stores')" id="stores-tab" class="tab-button active py-2 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                        Mijn Winkels
                    </button>
                    <button onclick="showTab('tasks')" id="tasks-tab" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        Taken Genereren
                    </button>
                    <button onclick="showTab('managers')" id="managers-tab" class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                        Managers Beheren
                    </button>
                </nav>
            </div>
        </div>

        <!-- Stores Tab -->
        <div id="stores-content" class="tab-content">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Mijn Winkels</h2>
                <div id="stores-list" class="space-y-4">
                    <!-- Wordt geladen via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Tasks Tab -->
        <div id="tasks-content" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Taken Genereren voor Winkels</h2>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Selecteer Winkel:</label>
                    <select id="store-select" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">Kies een winkel...</option>
                    </select>
                </div>
                <button onclick="generateTasksForStore()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Taken Genereren
                </button>
            </div>
        </div>

        <!-- Managers Tab -->
        <div id="managers-content" class="tab-content hidden">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Managers Beheren</h2>
                
                <!-- Nieuwe manager aanmaken -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-lg font-medium mb-3">Nieuwe Manager Aanmaken</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="text" id="new-manager-username" placeholder="Gebruikersnaam" class="p-2 border border-gray-300 rounded-md">
                        <input type="email" id="new-manager-email" placeholder="Email" class="p-2 border border-gray-300 rounded-md">
                        <input type="password" id="new-manager-password" placeholder="Wachtwoord" class="p-2 border border-gray-300 rounded-md">
                    </div>
                    <button onclick="createManager()" class="mt-3 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        Manager Aanmaken
                    </button>
                </div>

                <!-- Manager toewijzen aan winkel -->
                <div class="p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-lg font-medium mb-3">Manager Toewijzen aan Winkel</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <select id="assign-store-select" class="p-2 border border-gray-300 rounded-md">
                            <option value="">Kies een winkel...</option>
                        </select>
                        <select id="assign-manager-select" class="p-2 border border-gray-300 rounded-md">
                            <option value="">Kies een manager...</option>
                        </select>
                    </div>
                    <button onclick="assignManager()" class="mt-3 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Manager Toewijzen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/nyp/assets/js/regiomanager.js"></script>
</body>
</html>