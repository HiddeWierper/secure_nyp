// ==== REGIOMANAGER SPECIFIEKE FUNCTIES ====
// Deze functies werken samen met script.js zonder conflicten

// Alleen laden voor regiomanagers
if (typeof isRegiomanager !== 'undefined' && isRegiomanager) {
    
    // Dashboard data laden
    async function loadRegioDashboard() {
        console.log('Loading regio dashboard...');
        
        try {
            const response = await fetch('/api/get_dashboard_data.php');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.error || 'Unknown error');
            }
            
            updateDashboardStats(result.data);
            createDashboardCharts(result.data);
            
        } catch (error) {
            console.error('Error loading dashboard:', error);
            // Show user-friendly error
            const errorElements = [
                'dashboard-total-stores',
                'dashboard-active-tasks', 
                'dashboard-completion-rate',
                'dashboard-total-managers'
            ];
            
            errorElements.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = 'Error';
            });
        }
    }
    
    // Update dashboard statistics
    function updateDashboardStats(data) {
        const updates = {
            'dashboard-total-stores': data.totalStores || 0,
            'dashboard-active-tasks': data.activeTasks || 0,
            'dashboard-completion-rate': (data.completionRate || 0) + '%',
            'dashboard-total-managers': data.totalManagers || 0
        };
        
        Object.entries(updates).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }
    
    // Create dashboard charts
    function createDashboardCharts(data) {
        // Tasks per store chart
        const tasksChart = document.getElementById('tasksPerStoreChart');
        if (tasksChart && data.tasksPerStore) {
            const ctx = tasksChart.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.tasksPerStore.map(item => item.store_name),
                    datasets: [{
                        label: 'Aantal Taken',
                        data: data.tasksPerStore.map(item => item.task_count),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Completion trend chart
        const trendChart = document.getElementById('completionTrendChart');
        if (trendChart && data.completionTrend) {
            const ctx = trendChart.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.completionTrend.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('nl-NL', { weekday: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Voltooiingspercentage',
                        data: data.completionTrend.map(item => {
                            return item.total > 0 ? Math.round((item.completed / item.total) * 100) : 0;
                        }),
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Update dashboard KPIs
    function updateDashboardKPIs(data) {
        // Calculate totals from the data
        const totalStores = data.tasksPerStore ? data.tasksPerStore.length : 0;
        const totalTasks = data.tasksPerStore ? data.tasksPerStore.reduce((sum, store) => sum + store.task_count, 0) : 0;
        const completedTasks = data.completionTrend ? data.completionTrend.reduce((sum, day) => sum + day.completed, 0) : 0;
        const totalTasksInTrend = data.completionTrend ? data.completionTrend.reduce((sum, day) => sum + day.total, 0) : 0;
        const completionRate = totalTasksInTrend > 0 ? Math.round((completedTasks / totalTasksInTrend) * 100) : 0;
        
        // Update KPI elements
        const totalStoresEl = document.getElementById('dashboard-total-stores');
        const activeTasksEl = document.getElementById('dashboard-active-tasks');
        const completionRateEl = document.getElementById('dashboard-completion-rate');
        const totalManagersEl = document.getElementById('dashboard-total-managers');
        
        if (totalStoresEl) totalStoresEl.textContent = totalStores;
        if (activeTasksEl) activeTasksEl.textContent = totalTasks;
        if (completionRateEl) completionRateEl.textContent = completionRate + '%';
        if (totalManagersEl) totalManagersEl.textContent = '-'; // We'll need to add this to the API later
    }

    // Update tasks per store chart (HTML/CSS version)
    function updateTasksPerStoreChart(tasksData) {
        const chartContainer = document.getElementById('tasks-per-store-chart');
        if (!chartContainer || !tasksData) return;
        
        // Simple bar chart using HTML/CSS
        const maxTasks = Math.max(...tasksData.map(store => store.task_count), 1);
        
        chartContainer.innerHTML = `
            <div class="space-y-2">
                ${tasksData.map(store => `
                    <div class="flex items-center">
                        <div class="w-24 text-xs text-gray-600 truncate" title="${store.store_name}">
                            ${store.store_name.replace('Nyp ', '')}
                        </div>
                        <div class="flex-1 mx-2">
                            <div class="bg-gray-200 rounded-full h-4 relative">
                                <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-4 rounded-full transition-all duration-500" 
                                     style="width: ${(store.task_count / maxTasks) * 100}%"></div>
                            </div>
                        </div>
                        <div class="w-8 text-xs text-gray-800 font-medium">
                            ${store.task_count}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // Update completion trend chart (HTML/CSS version)
    function updateCompletionTrendChart(trendData) {
        const chartContainer = document.getElementById('completion-trend-chart');
        if (!chartContainer || !trendData) return;
        
        const maxPercentage = 100;
        
        chartContainer.innerHTML = `
            <div class="flex items-end justify-between h-32 space-x-1">
                ${trendData.map(day => {
                    const percentage = day.total > 0 ? Math.round((day.completed / day.total) * 100) : 0;
                    const date = new Date(day.date);
                    const dayName = date.toLocaleDateString('nl-NL', { weekday: 'short' });
                    
                    return `
                        <div class="flex flex-col items-center flex-1">
                            <div class="w-full bg-gray-200 rounded-t" style="height: ${Math.max(percentage, 5)}%">
                                <div class="w-full bg-gradient-to-t from-green-500 to-green-400 rounded-t transition-all duration-500" 
                                     style="height: 100%" title="${percentage}% voltooid"></div>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">${dayName}</div>
                            <div class="text-xs text-gray-800 font-medium">${percentage}%</div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    // Load region stores
    async function loadRegionStores() {
        try {
            console.log('Loading region stores...');
            const response = await fetch('/api/get_region_stores.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const stores = await response.json();
            
            if (stores.error) {
                throw new Error(stores.error);
            }
            
            console.log('Region stores loaded:', stores);
            
            // Update region stores list if container exists
            const container = document.getElementById('region-stores-list');
            if (container) {
                if (!Array.isArray(stores) || stores.length === 0) {
                    container.innerHTML = '<p class="text-gray-600">Geen winkels gevonden in jouw regio.</p>';
                } else {
                    container.innerHTML = stores.map(store => `
                        <div class="bg-white rounded-lg p-4 border border-gray-200 flex items-center justify-between hover:shadow-md transition-shadow">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-lg">${store.name}</h4>
                                <p class="text-sm text-gray-600 mt-1">${store.address || 'Geen adres'}</p>
                                <div class="flex items-center mt-2">
                                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                        ${store.managers || 'Geen manager'}
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col space-y-2">
                                <button onclick="generateTasksForStore('${store.id}')" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                    <i class="fas fa-tasks mr-1"></i> Taken Genereren
                                </button>
                                <button onclick="viewStoreTasks('${store.id}')" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition-colors">
                                    <i class="fas fa-eye mr-1"></i> Bekijk Taken
                                </button>
                            </div>
                        </div>
                    `).join('');
                }
            }
            
            // Update store dropdown
            const storeSelect = document.getElementById('store-select');
            if (storeSelect) {
                storeSelect.innerHTML = '<option value="">Selecteer winkel...</option>';
                stores.forEach(store => {
                    const option = document.createElement('option');
                    option.value = store.id;
                    option.textContent = `${store.name} (${store.managers || 'Geen managers'})`;
                    storeSelect.appendChild(option);
                });
            }
            
            // Update store selects for generator
            updateStoreSelects(stores);
            
            return stores;
        } catch (error) {
            console.error('Error loading region stores:', error);
            showNotification('Fout bij laden van winkels: ' + error.message, 'error');
            return [];
        }
    }

    // Load region managers
    async function loadRegionManagers() {
        try {
            console.log('Loading region managers...');
            const response = await fetch('/api/get_region_managers.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const managers = await response.json();
            
            if (managers.error) {
                throw new Error(managers.error);
            }
            
            console.log('Region managers loaded:', managers);
            
            // Update manager dropdown
            const managerSelect = document.getElementById('manager-select');
            if (managerSelect) {
                managerSelect.innerHTML = '<option value="">Selecteer manager...</option>';
                managers.forEach(manager => {
                    const option = document.createElement('option');
                    option.value = manager.username;
                    option.textContent = `${manager.username} (${manager.store_name})`;
                    managerSelect.appendChild(option);
                });
            }
            
            return managers;
        } catch (error) {
            console.error('Error loading region managers:', error);
            showNotification('Fout bij laden van managers: ' + error.message, 'error');
            return [];
        }
    }

    // Update store select dropdowns
    function updateStoreSelects(stores) {
        const selects = ['storeSelect']; // Alleen de generator dropdown
        
        selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            if (select) {
                select.innerHTML = '<option value="">Kies een winkel...</option>';
                stores.forEach(store => {
                    select.innerHTML += `<option value="${store.id}">${store.name}</option>`;
                });
            }
        });
    }

    // Genereer taken voor geselecteerde winkel - GEBRUIK BESTAANDE API
    window.generateTasksForStore = async function(storeId) {
        if (!storeId) {
            storeId = document.getElementById('storeSelect')?.value;
        }
        
        if (!storeId) {
            alert('Selecteer eerst een winkel');
            return;
        }
        
        // Vraag om manager en dag input
        const manager = prompt('Voer manager naam in:');
        const day = prompt('Voer dag in (bijv. Maandag):');
        
        if (!manager || !day) {
            alert('Manager en dag zijn verplicht');
            return;
        }
        
        try {
            // Gebruik de bestaande generate_tasks.php API
            const response = await fetch('/api/generate_tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    store_id: storeId,
                    manager: manager,
                    day: day,
                    maxDuration: 90
                })
            });
            
            const result = await response.json();
            if (result.success) {
                alert(`Taken succesvol gegenereerd voor winkel!\\nAantal taken: ${result.tasks.length}\\nTotale tijd: ${result.total_time} minuten`);
                loadRegionStores();
            } else {
                alert('Fout: ' + (result.error || 'Onbekende fout'));
            }
        } catch (error) {
            alert('Fout bij genereren taken: ' + error.message);
        }
    }

    // Bekijk taken van een winkel
    window.viewStoreTasks = async function(storeId) {
        try {
            const response = await fetch('/api/get_all_task_sets.php');
            const data = await response.json();
            
            if (!data.success) {
                alert('Fout bij laden taken: ' + data.error);
                return;
            }
            
            // Filter taken voor deze winkel
            const storeTasks = data.task_sets.filter(taskSet => taskSet.store_id == storeId);
            
            if (storeTasks.length === 0) {
                alert('Geen taken gevonden voor deze winkel');
                return;
            }
            
            // Toon taken in een modal
            showTasksModal(storeTasks, `Winkel ${storeId}`);
            
        } catch (error) {
            alert('Fout bij laden taken: ' + error.message);
        }
    }

    // Toon taken in een modal
    function showTasksModal(taskSets, storeName) {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-2xl p-6 max-w-2xl w-full max-h-96 overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">Taken voor ${storeName}</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="space-y-3">
                    ${taskSets.length === 0 ? 
                        '<p class="text-gray-500">Geen taken gevonden voor deze winkel.</p>' :
                        taskSets.map(taskSet => `
                            <div class="border border-gray-200 rounded-lg p-3">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium">${taskSet.manager} - ${taskSet.day}</h4>
                                        <p class="text-sm text-gray-600">Aangemaakt: ${new Date(taskSet.created_at).toLocaleDateString('nl-NL')}</p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full ${taskSet.submitted ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                        ${taskSet.submitted ? 'Ingediend' : 'Pending'}
                                    </span>
                                </div>
                            </div>
                        `).join('')
                    }
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    // Show region page
    function showRegionPage() {
        console.log('Showing region page...');
        setTimeout(() => {
            loadRegionStores();
            loadRegionManagers();
        }, 100);
    }

    // Override loadStoresAndManagers voor regiomanagers
    const originalLoadStoresAndManagers = window.loadStoresAndManagers;
    window.loadStoresAndManagers = async function() {
        try {
            const res = await fetch('/api/api_get_stores_and_managers.php');
            const data = await res.json();
            if (data.error) {
                console.log('Fout bij laden winkels: ' + data.error);
                return;
            }
            
            // Update globale variabelen (uit script.js)
            if (window.stores !== undefined) window.stores = data.stores || [];
            if (window.managersByStore !== undefined) window.managersByStore = data.managersByStore || {};

            const storeSelect = document.getElementById('storeSelect');
            if (storeSelect) {
                storeSelect.innerHTML = '<option value="">Selecteer winkel</option>';
                (data.stores || []).forEach(store => {
                    storeSelect.innerHTML += `<option value="${store.id}">${store.name}</option>`;
                });

                storeSelect.disabled = false;
                
                // Hergebruik de originele onStoreChange functie
                if (window.onStoreChange) {
                    storeSelect.addEventListener('change', window.onStoreChange);
                }
            }

            // Manager dropdown init
            const managerSelect = document.getElementById('managerSelect');
            if (managerSelect) {
                managerSelect.innerHTML = '<option value="">Selecteer winkel eerst</option>';
                managerSelect.disabled = true;
            }

        } catch (err) {
            console.log('Fout bij laden winkels: ' + err.message);
        }
    }

    // Initialize region manager functions
    function initializeRegionManager() {
        console.log('Initializing region manager functions...');
        
        // Load stores and managers for generator
        if (window.loadStoresAndManagers) {
            window.loadStoresAndManagers();
        }
    }

    // DOM loaded event
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded for regiomanager, initializing...');
        
        setTimeout(() => {
            initializeRegionManager();
            showRegionPage();
        }, 100);
    });

    // Make functions globally available
    window.loadRegioDashboard = loadRegioDashboard;
    window.loadRegionStores = loadRegionStores;
    window.showRegionPage = showRegionPage;
}

console.log('Regiomanager.js loaded for user role:', typeof userRole !== 'undefined' ? userRole : 'undefined');