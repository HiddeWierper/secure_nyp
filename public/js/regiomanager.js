
// ===== REGIOMANAGER SPECIFIEKE FUNCTIES =====

// Verbeterde loadRegionStores functie (gecombineerd met jouw tab functionaliteit)
async function loadRegionStores() {
    if (!isRegiomanager) return;
    
    const container = document.getElementById('region-stores-list');
    if (!container) {
        console.log('Region stores container not found, page might not be loaded yet');
        return;
    }
    
    // Toon loading state
    container.innerHTML = '<p class="text-gray-600">Regio winkels laden...</p>';
    
    try {
        const response = await fetch('api/get_region_stores.php');
        const data = await response.json();
        
        if (!data.success) {
            container.innerHTML = `<p class="text-red-600">Fout bij laden winkels: ${data.error}</p>`;
            return;
        }
        
        if (data.stores.length === 0) {
            container.innerHTML = '<p class="text-gray-600">Geen winkels gevonden in jouw regio.</p>';
            return;
        }
        
        // Update statistics - met null checks
        const totalStoresEl = document.getElementById('total-stores');
        const activeTasksEl = document.getElementById('active-tasks');
        const completionRateEl = document.getElementById('completion-rate');
        
        if (totalStoresEl) totalStoresEl.textContent = data.stores.length;
        if (activeTasksEl) activeTasksEl.textContent = data.statistics?.active_tasks || 0;
        if (completionRateEl) completionRateEl.textContent = (data.statistics?.completion_rate || 0) + '%';
        
        // Display stores (aangepast voor de nieuwe layout)
        container.innerHTML = data.stores.map(store => `
            <div class="bg-white rounded-lg p-4 border border-gray-200 flex items-center justify-between hover:shadow-md transition-shadow">
                <div class="flex-1">
                    <h4 class="font-semibold text-gray-800 text-lg">${store.name}</h4>
                    <p class="text-sm text-gray-600 mt-1">${store.address || 'Geen adres'}</p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                            ${store.manager_name || 'Geen manager'}
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
        
        // Update store selects if they exist
        updateStoreSelects(data.stores);
        
    } catch (err) {
        console.error('Error loading region stores:', err);
        if (container) {
            container.innerHTML = `<p class="text-red-600">Fout bij laden winkels: ${err.message}</p>`;
        }
    }
}

// Update store select dropdowns
function updateStoreSelects(stores) {
    const selects = ['store-select', 'assign-store-select'];
    
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

// Laad managers voor regiomanager
async function loadManagers() {
    if (!isRegiomanager) return;
    
    try {
        const response = await fetch('api/get_managers.php');
        const data = await response.json();
        
        if (!data.success) {
            console.error('Fout bij laden managers:', data.error);
            return;
        }
        
        const assignManagerSelect = document.getElementById('assign-manager-select');
        if (assignManagerSelect) {
            assignManagerSelect.innerHTML = '<option value="">Kies een manager...</option>';
            
            data.managers.forEach(manager => {
                assignManagerSelect.innerHTML += `<option value="${manager.id}">${manager.username}</option>`;
            });
        }
    } catch (error) {
        console.error('Fout bij laden managers:', error);
    }
}

// Genereer taken voor geselecteerde winkel
async function generateTasksForStore(storeId) {
    if (!storeId) {
        // Als geen storeId meegegeven, haal uit select
        storeId = document.getElementById('store-select')?.value;
    }
    
    if (!storeId) {
        alert('Selecteer eerst een winkel');
        return;
    }
    
    try {
        const response = await fetch('api/generate_tasks_for_store.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ store_id: storeId })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Taken succesvol gegenereerd voor winkel!');
            // Reload region data
            loadRegionStores();
        } else {
            alert('Fout: ' + (result.error || 'Onbekende fout'));
        }
    } catch (error) {
        alert('Fout bij genereren taken: ' + error.message);
    }
}

// Bekijk taken van een winkel
async function viewStoreTasks(storeId) {
    try {
        const response = await fetch(`api/get_store_tasks.php?store_id=${storeId}`);
        const data = await response.json();
        
        if (!data.success) {
            alert('Fout bij laden taken: ' + data.error);
            return;
        }
        
        // Toon taken in een modal of nieuwe sectie
        showTasksModal(data.tasks, data.store_name);
        
    } catch (error) {
        alert('Fout bij laden taken: ' + error.message);
    }
}

// Toon taken in een modal
function showTasksModal(tasks, storeName) {
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
                ${tasks.length === 0 ? 
                    '<p class="text-gray-500">Geen taken gevonden voor deze winkel.</p>' :
                    tasks.map(task => `
                        <div class="border border-gray-200 rounded-lg p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-medium">${task.task_name}</h4>
                                    <p class="text-sm text-gray-600">Dag: ${task.day}</p>
                                    <p class="text-sm text-gray-600">Manager: ${task.manager_name}</p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full ${task.completed ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                                    ${task.completed ? 'Voltooid' : 'Pending'}
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

// Maak nieuwe manager aan
async function createManager() {
    const username = document.getElementById('new-manager-username')?.value;
    const email = document.getElementById('new-manager-email')?.value;
    const password = document.getElementById('new-manager-password')?.value;
    
    if (!username || !email || !password) {
        alert('Vul alle velden in');
        return;
    }
    
    try {
        const response = await fetch('api/create_manager.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Manager succesvol aangemaakt!');
            // Clear form
            document.getElementById('new-manager-username').value = '';
            document.getElementById('new-manager-email').value = '';
            document.getElementById('new-manager-password').value = '';
            // Reload managers
            loadManagers();
        } else {
            alert('Fout: ' + (result.error || 'Onbekende fout'));
        }
    } catch (error) {
        alert('Fout bij aanmaken manager: ' + error.message);
    }
}

// Wijs manager toe aan winkel
async function assignManager() {
    const storeId = document.getElementById('assign-store-select')?.value;
    const managerId = document.getElementById('assign-manager-select')?.value;
    
    if (!storeId) {
        alert('Selecteer een winkel');
        return;
    }
    
    try {
        const response = await fetch('api/assign_manager_to_store.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ store_id: storeId, manager_id: managerId })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Manager succesvol toegewezen!');
            loadRegionStores(); // Reload stores to show updated manager
        } else {
            alert('Fout: ' + (result.error || 'Onbekende fout'));
        }
    } catch (error) {
        alert('Fout bij toewijzen manager: ' + error.message);
    }
}

// ===== BESTAANDE FUNCTIES (AANGEPAST) =====

// Verbeterde showPage functie
function showPage(pageId) {
    // Close mobile menu when navigating
    closeMobileMenu();
    
    // Hide all pages
    ['page-generator', 'page-track', 'page-manage', 'page-region'].forEach(id => {
        const page = document.getElementById(id);
        if (page) page.classList.add('hidden');
    });
    
    // Show selected page
    const page = document.getElementById(`page-${pageId}`);
    if (page) {
        page.classList.remove('hidden');
    }

    // Update desktop navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    const activeBtn = document.getElementById(`btn-${pageId}`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }

    // Update mobile navigation
    document.querySelectorAll('.mobile-nav-btn').forEach(btn => {
        btn.classList.remove('bg-green-50', 'text-green-600');
    });

    // Load specific page data AFTER page is shown
    if (pageId === 'region' && isRegiomanager) {
        // Wacht even tot de DOM is bijgewerkt
        setTimeout(() => {
            loadRegionStores();
            loadManagers();
        }, 100);
    }
    
    if (pageId === 'track') {
        setTimeout(() => {
            loadTaskSetsAndStores();
        }, 100);
    }
}

// Verbeterde DOMContentLoaded event
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, user role:', userRole);
    
    // Wacht tot alle elementen geladen zijn
    setTimeout(() => {
        // Show appropriate default page based on role
        if (isRegiomanager) {
            showPage('region');
        } else if (isManager) {
            showPage('track');
        } else if (isAdmin) {
            showPage('generator');
        } else {
            showPage('track');
        }
    }, 200);
});
