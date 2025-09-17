const BASEPATH = window.location.hostname === 'localhost' ? '/secure_nyp/public' : '';
const origFetch = window.fetch;
window.fetch = function(resource, options) {
    if (typeof resource === 'string' && resource.startsWith('/api/')) {
        resource = BASEPATH + resource;
    }
    return origFetch(resource, options);
};
// Globale variabelen
let currentTaskSetId = null;
let currentTasks = [];

// Pagina navigatie

// Taken genereren

// Gegenereerde taken weergeven - MET WHATSAPP PREVIEW
function displayGeneratedTasks(tasks, totalTime) {
    const container = document.getElementById('generated-tasks');
    
    // Only proceed if container exists (non-manager users)
    if (!container) {
        console.log('Generated tasks container not found - user might be manager');
        return;
    }
    
    // Show container when displaying tasks
    container.classList.remove('hidden');
    
    if (!tasks || tasks.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-clipboard text-4xl mb-4"></i>
                <p>Geen taken gegenereerd.</p>
                <p class="text-sm">Probeer opnieuw met andere instellingen.</p>
            </div>
        `;
        return;
    }
    
    // Your existing task display code here...
    let html = `
        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
            <div class="flex justify-between items-center">
                <h3 class="font-semibold text-blue-800">Gegenereerde Taken</h3>
                <span class="text-blue-600 font-medium">Totale tijd: ${totalTime} minuten</span>
            </div>
        </div>
        <div class="space-y-3">
    `;
    
    tasks.forEach(task => {
        const requiredBadge = task.required == 1 ? 
            '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full ml-2">Verplicht</span>' : '';
        
        html += `
            <div class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                <div>
                    <span class="font-medium text-gray-800">${task.name}</span>
                    ${requiredBadge}
                    <div class="text-sm text-gray-500 mt-1">
                        <i class="fas fa-clock mr-1"></i>${task.time} min
                        <span class="mx-2">•</span>
                        <i class="fas fa-repeat mr-1"></i>${task.frequency}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `
        </div>
        
        <!-- WhatsApp Preview -->
        <div id="whatsapp-preview-container" class="bg-gray-100 rounded-xl p-4 my-6 hidden">
            <h4 class="font-bold mb-3 text-gray-700">
                <i class="fas fa-eye text-primary mr-2"></i> Voorbeeld WhatsApp-bericht
            </h4>
            <textarea id="whatsapp-preview" class="w-full p-3 rounded-lg border border-gray-300 bg-white text-gray-800 font-mono text-sm" rows="12" readonly></textarea>
            <div class="mt-3 text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i> Je kunt de tekst hierboven aanpassen voordat je kopieert
            </div>
        </div>
        
        <div class="mt-6 flex space-x-4 justify-center">
            <button onclick="generateWhatsAppPreview()" class="bg-blue-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-600 transition-colors">
                <i class="fas fa-eye mr-2"></i>WhatsApp Preview
            </button>
            <button id="copyBtn" onclick="copyWhatsAppMessage()" class="bg-green-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-green-600 transition-colors hidden">
                <i class="fab fa-whatsapp mr-2"></i>Kopieer voor WhatsApp
            </button>
            <button onclick="regenerateTasks()" class="bg-primary text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-600 transition-colors">
                <i class="fas fa-redo mr-2"></i>Nieuwe Taken
            </button>
            <button onclick="saveTaskSet()" class="bg-purple-500 text-white px-6 py-3 rounded-lg font-medium hover:bg-purple-600 transition-colors">
                <i class="fas fa-save mr-2"></i>Opslaan voor Bijhouden
            </button>
        </div>
    `;
    
    container.innerHTML = html;
}

// Function to hide generated tasks (call when switching pages or resetting)
function hideGeneratedTasks() {
    const container = document.getElementById('generated-tasks');
    if (container) {
        container.classList.add('hidden');
        // Reset to default content
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-clipboard text-4xl mb-4"></i>
                <p>Geen taken gegenereerd.</p>
                <p class="text-sm">Vul de gegevens in en klik op "Genereer Taken".</p>
            </div>
        `;
    }
}

// Update the regenerateTasks function to hide preview properly
function regenerateTasks() {
    // Hide WhatsApp preview
    const previewContainer = document.getElementById('whatsapp-preview-container');
    const copyBtn = document.getElementById('copyBtn');
    if (previewContainer) previewContainer.classList.add('hidden');
    if (copyBtn) copyBtn.classList.add('hidden');
    
    // Generate new tasks (this will show the container again with new content)
    generateTasks();
}

// Update showPage function to hide generated tasks when switching pages


// WhatsApp preview genereren
function generateWhatsAppPreview() {
    const manager = document.getElementById('managerSelect').value;
    const day = document.getElementById('day').value;
    
    if (!currentTasks || currentTasks.length === 0) {
        showDangerAlert('Geen taken om preview van te maken');
        return;
    }
    
    // Genereer WhatsApp bericht
    let message = `Taken voor ${day} - ${manager}\n\n`;
    message += `Hoi! Hier zijn de taken voor vandaag.\n\n`;
    
    currentTasks.forEach((task, index) => {
        message += `${index + 1}. ${task.name} (${task.time} min)\n`;
    });
    
    message += `\nStuur foto's van de schoongemaakte punten\nSucces!`;
    
    // Toon preview
    const previewContainer = document.getElementById('whatsapp-preview-container');
    const previewTextarea = document.getElementById('whatsapp-preview');
    const copyBtn = document.getElementById('copyBtn');
    
    previewTextarea.value = message;
    previewContainer.classList.remove('hidden');
    copyBtn.classList.remove('hidden');
    
    // Scroll naar preview
    previewContainer.scrollIntoView({ behavior: 'smooth' });
}

// WhatsApp bericht kopiëren
function copyWhatsAppMessage() {
    const previewTextarea = document.getElementById('whatsapp-preview');
    
    if (!previewTextarea.value) {
        showDangerAlert('Geen bericht om te kopiëren');
        return;
    }
    
    // Kopieer naar clipboard
    previewTextarea.select();
    previewTextarea.setSelectionRange(0, 99999); // Voor mobiele apparaten
    
    try {
        document.execCommand('copy');
        showNotification('✅ WhatsApp bericht gekopieerd!', 'success');
        
        // Verander knop tijdelijk
        const copyBtn = document.getElementById('copyBtn');
        const originalText = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Gekopieerd!';
        copyBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
        copyBtn.classList.add('bg-green-600');
        
        setTimeout(() => {
            copyBtn.innerHTML = originalText;
            copyBtn.classList.remove('bg-green-600');
            copyBtn.classList.add('bg-green-500', 'hover:bg-green-600');
        }, 2000);
        
    } catch (err) {
        // Fallback voor moderne browsers
        navigator.clipboard.writeText(previewTextarea.value).then(() => {
            showNotification('✅ WhatsApp bericht gekopieerd!', 'success');
        }).catch(() => {
            showNotification('❌ Kon bericht niet kopiëren', 'error');
        });
    }
}

// Nieuwe taken genereren (herlaad functie)
function regenerateTasks() {
    // Verberg preview
    const previewContainer = document.getElementById('whatsapp-preview-container');
    const copyBtn = document.getElementById('copyBtn');
    previewContainer.classList.add('hidden');
    copyBtn.classList.add('hidden');
    
    // Genereer nieuwe taken
    generateTasks();
}


// Task set opslaan
async function saveTaskSet() {
    const manager = document.getElementById('managerSelect').value;  // aangepast van 'managerSelect'
    const day = document.getElementById('day').value;
    const storeId = document.getElementById('storeSelect').value;

    if (!manager || !day || !storeId) {
        showDangerAlert('Vul alle velden in');
        return;
    }
    
    try {
        const res = await fetch('/api/save_task_set.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                manager: manager,
                day: day,
                store_id: storeId,
                tasks: currentTasks
            })
        });
        
        const data = await res.json();
        if (!data.success) {
            console.log('Fout bij opslaan: ' + data.error);
            return;
        }
        
        showDangerAlert('✅ Taken succesvol opgeslagen voor bijhouden!');
        
        // Reset form
        document.getElementById('managerSelect').value = '';
        document.getElementById('day').value = '';
        document.getElementById('generated-tasks').innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-clipboard text-4xl mb-4"></i><p>Geen taken gegenereerd.</p><p class="text-sm">Vul de gegevens in en klik op "Genereer Taken".</p></div>';
        currentTasks = [];
        
    } catch (e) {
        console.log('Fout bij opslaan: ' + e.message);
    }
}

// Global variables for search functionality
let allTaskSetsData = []; // Store all task sets for filtering
let filteredTaskSetsData = []; // Store filtered results

// Search and filter functionality
function initializeSearch() {
    if (isManager) return; // Only for non-managers
    
    const searchInput = document.getElementById('searchInput');
    const storeFilter = document.getElementById('storeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const clearButton = document.getElementById('clearFilters');
    
    if (!searchInput || !storeFilter || !statusFilter || !clearButton) {
        console.log('Search elements not found');
        return;
    }
    
    // Add event listeners
    searchInput.addEventListener('input', debounce(performSearch, 300));
    storeFilter.addEventListener('change', performSearch);
    statusFilter.addEventListener('change', performSearch);
    clearButton.addEventListener('click', clearAllFilters);
    
    console.log('Search functionality initialized');
}

// Debounce function to limit search frequency
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Perform search and filtering
function performSearch() {
    if (isManager) return;
    
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const storeFilter = document.getElementById('storeFilter')?.value || '';
    const statusFilter = document.getElementById('statusFilter')?.value || '';
    
    console.log('Performing search:', { searchTerm, storeFilter, statusFilter });
    
    // Filter the data
    filteredTaskSetsData = allTaskSetsData.filter(taskSet => {
        // Search term filter (search in manager, day, store name, and task names)
        const matchesSearch = !searchTerm || 
            taskSet.manager.toLowerCase().includes(searchTerm) ||
            taskSet.day.toLowerCase().includes(searchTerm) ||
            (taskSet.store_name && taskSet.store_name.toLowerCase().includes(searchTerm)) ||
            (taskSet.tasks && taskSet.tasks.some(task => 
                task.name.toLowerCase().includes(searchTerm)
            ));
        
        // Store filter
        const matchesStore = !storeFilter || taskSet.store_id == storeFilter;
        
        // Status filter
        const matchesStatus = !statusFilter || 
            (statusFilter === 'submitted' && taskSet.submitted) ||
            (statusFilter === 'pending' && !taskSet.submitted);
        
        return matchesSearch && matchesStore && matchesStatus;
    });
    
    // Update search info
    updateSearchInfo(filteredTaskSetsData.length, allTaskSetsData.length);
    
    // Render filtered results
    renderAllTaskSets(filteredTaskSetsData);
}

// Update search results info
function updateSearchInfo(filteredCount, totalCount) {
    const searchInfo = document.getElementById('searchInfo');
    const searchResultsCount = document.getElementById('searchResultsCount');
    
    if (!searchInfo || !searchResultsCount) return;
    
    searchResultsCount.textContent = filteredCount;
    
    if (filteredCount < totalCount) {
        searchInfo.classList.remove('hidden');
        searchInfo.className = 'mt-3 text-sm text-blue-600';
    } else {
        searchInfo.classList.add('hidden');
    }
}

// Clear all filters
function clearAllFilters() {
    const searchInput = document.getElementById('searchInput');
    const storeFilter = document.getElementById('storeFilter');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) searchInput.value = '';
    if (storeFilter) storeFilter.value = '';
    if (statusFilter) statusFilter.value = '';
    
    // Reset to show all data
    filteredTaskSetsData = [...allTaskSetsData];
    updateSearchInfo(filteredTaskSetsData.length, allTaskSetsData.length);
    renderAllTaskSets(filteredTaskSetsData);
    
    showNotification('🔄 Filters gewist', 'info');
}

// Populate store filter dropdown
function populateStoreFilter(taskSets) {
    if (isManager) return;
    
    const storeFilter = document.getElementById('storeFilter');
    if (!storeFilter) return;
    
    // Get unique stores
    const uniqueStores = {};
    taskSets.forEach(taskSet => {
        if (taskSet.store_id && taskSet.store_name && !uniqueStores[taskSet.store_id]) {
            uniqueStores[taskSet.store_id] = taskSet.store_name;
        }
    });
    
    // Clear existing options (except first one)
    storeFilter.querySelectorAll('option:not(:first-child)').forEach(opt => opt.remove());
    
    // Add store options
    Object.entries(uniqueStores).forEach(([id, name]) => {
        const option = document.createElement('option');
        option.value = id;
        option.textContent = name;
        storeFilter.appendChild(option);
    });
}

// Alle task sets laden voor bijhouden
async function loadAllTaskSetsForTracking() {
    const container = document.getElementById('task-tracking-list');

    if (!container) {
        console.error('task-tracking-list element not found in DOM');
        return;
    }

    try {
        console.log('Loading all task sets...');
        console.log('User role:', userRole);
        console.log('Store ID:', typeof store_id !== 'undefined' ? store_id : 'undefined');

        // For store managers, add store filter parameter
        let url = '/api/get_all_task_sets.php';
        if (isStoremanager && typeof store_id !== 'undefined' && store_id) {
            url += `?store_id=${store_id}`;
            console.log('Adding store filter for store manager:', url);
        }

        const res = await fetch(url);

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const text = await res.text();
        console.log('Response text:', text);

        if (!text) {
            throw new Error('Empty response from server');
        }

        const data = JSON.parse(text);
        console.log('Parsed data:', data);

        if (!data.success) {
            container.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-clipboard-list text-4xl mb-4"></i><p>Geen opgeslagen taken gevonden.</p><p class="text-sm text-red-500">' + (data.error || 'Onbekende fout') + '</p></div>';
            return;
        }

        if (!data.task_sets || data.task_sets.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-clipboard-list text-4xl mb-4"></i><p>Geen opgeslagen taken gevonden.</p></div>';
            // Clear search data
            allTaskSetsData = [];
            filteredTaskSetsData = [];
            return;
        }

        // Store data globally for search functionality
        allTaskSetsData = data.task_sets;
        filteredTaskSetsData = [...allTaskSetsData]; // Copy all data initially

        // Populate store filter for non-managers (but not for store managers since they only see their store)
        if (!isManager && !isStoremanager) {
            populateStoreFilter(allTaskSetsData);
        }

        renderAllTaskSets(filteredTaskSetsData);

    } catch (e) {
        console.error('Error loading task sets:', e);
        if (container) {
            container.innerHTML = '<div class="text-center text-red-500 py-8"><i class="fas fa-exclamation-triangle text-4xl mb-4"></i><p>Fout bij laden taken:</p><p class="text-sm">' + e.message + '</p></div>';
        }
    }
}

// Render alle task sets
function renderAllTaskSets(taskSets) {
    const container = document.getElementById('task-tracking-list');
    
    if (!container) {
        console.error('task-tracking-list element not found in renderAllTaskSets');
        return;
    }
    
    if (!taskSets || taskSets.length === 0) {
        const isFiltered = !isManager && allTaskSetsData.length > 0;
        const message = isFiltered ? 
            'Geen taken gevonden met de huidige filters.' : 
            'Geen opgeslagen taken gevonden.';
        const subMessage = isFiltered ? 
            'Probeer andere zoektermen of reset de filters.' : 
            '';
            
        container.innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-clipboard-list text-4xl mb-4"></i>
                <p>${message}</p>
                ${subMessage ? `<p class="text-sm">${subMessage}</p>` : ''}
            </div>
        `;
        return;
    }

    // Rest of your existing renderAllTaskSets code...
    let html = '';
    taskSets.forEach((taskSet, index) => {
        // Your existing task set rendering code
        const date = new Date(taskSet.created_at);
        const formattedDate = date.toLocaleDateString('nl-NL', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const formattedTime = date.toLocaleTimeString('nl-NL', {
            hour: '2-digit',
            minute: '2-digit'
        });

        const isExpanded = index === 0;
        const statusColor = taskSet.submitted ? 'text-green-600' : 'text-orange-600';
        const statusIcon = taskSet.submitted ? 'fas fa-check-circle' : 'fas fa-clock';
        const statusText = taskSet.submitted ? 'Ingediend' : 'Nog niet ingediend';

        const deleteBtnHtml = isManager ? '' : `
            <button onclick="event.stopPropagation(); deleteTaskSet(${taskSet.id}, '${taskSet.manager}', '${taskSet.day}')" 
                class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-colors" 
                title="Verwijder deze dag">
                <i class="fas fa-trash"></i>
            </button>
        `;

        // Add store name if available
        const storeInfo = taskSet.store_name ? ` - ${taskSet.store_name}` : '';

        html += `
            <div class="bg-gray-50 rounded-lg border border-gray-200">
                <div class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-100 transition-colors" 
                     onclick="toggleTaskSetExpansion(${taskSet.id})">
                    <div class="flex items-center space-x-4">
                        <i id="toggle-icon-${taskSet.id}" class="fas ${isExpanded ? 'fa-chevron-down' : 'fa-chevron-right'} text-gray-500 transition-transform duration-200"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800">${taskSet.manager} - ${taskSet.day}${storeInfo}</h3>
                            <p class="text-sm text-gray-600">Aangemaakt: ${formattedDate} om ${formattedTime}</p>
                            <p class="text-sm ${statusColor}"><i class="${statusIcon} mr-1"></i>${statusText}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        ${deleteBtnHtml}
                    </div>
                </div>
                <div id="task-set-content-${taskSet.id}" class="task-set-content ${isExpanded ? 'block' : 'hidden'} px-4 pb-4">
                    <div class="text-center text-gray-500 py-4">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Taken laden...
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;

    // Load tasks for the first (expanded) task set
    if (taskSets.length > 0) {
        loadTaskSetDetails(taskSets[0].id);
    }
}

// Toggle task set uitklappen/inklappen
function toggleTaskSetExpansion(taskSetId) {
    console.log('Toggling task set:', taskSetId);
    
    const content = document.getElementById(`task-set-content-${taskSetId}`);
    const icon = document.getElementById(`toggle-icon-${taskSetId}`);
    
    if (!content || !icon) {
        console.error('Content or icon not found for task set:', taskSetId);
        return;
    }
    
    const isHidden = content.classList.contains('hidden');
    console.log('Is currently hidden:', isHidden);
    
    if (isHidden) {
        // Uitklappen
        content.classList.remove('hidden');
        content.classList.add('block');
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
        
        console.log('Expanding and loading details...');
        
        // Laad taken als ze nog niet geladen zijn
        if (content.innerHTML.includes('Taken laden...')) {
            loadTaskSetDetails(taskSetId);
        }
    } else {
        // Inklappen
        content.classList.remove('block');
        content.classList.add('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
        
        console.log('Collapsing...');
    }
}

// Laad details van een specifieke task set
async function loadTaskSetDetails(taskSetId) {
    try {
        const res = await fetch(`api/get_task_set_details.php?task_set_id=${taskSetId}`);
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Fout bij laden taakdetails');
        }
        
        renderTaskSetDetails(taskSetId, data.task_set, data.tasks, data.completions);
        
    } catch (e) {
        console.error('Error loading task set details:', e);
        const container = document.getElementById(`task-set-content-${taskSetId}`);
        if (container) {
            container.innerHTML = `
                <div class="text-center text-red-500 py-4">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Fout bij laden taken: ${e.message}
                </div>
            `;
        }
    }
}

// Render task set details
function renderTaskSetDetails(taskSetId, taskSet, tasks, completions) {
    const container = document.getElementById(`task-set-content-${taskSetId}`);
    if (!container) return;

    if (!tasks || tasks.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-4">
                <i class="fas fa-clipboard mr-2"></i>
                Geen taken gevonden voor deze dag.
            </div>
        `;
        return;
    }

    let totalTime = 0;
    let completedCount = 0;
    
    // Check of de dag is ingediend
    const isSubmitted = taskSet.submitted;
    
    let html = '<div class="space-y-3">';
    
    tasks.forEach(task => {
        const isCompleted = completions[task.id] || false;
        if (isCompleted) completedCount++;
        totalTime += parseInt(task.time);
    
        const checkboxClass = isCompleted ? 'bg-green-500 border-green-500' : 'border-gray-300';
        const textClass = isCompleted ? 'line-through text-gray-500' : 'text-gray-800';
        const requiredBadge = task.required == 1 ? '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full ml-2">Verplicht</span>' : '';
    
        // Disable checkbox als ingediend
        const disabledAttr = isSubmitted ? 'disabled' : '';
        const cursorClass = isSubmitted ? 'cursor-not-allowed' : 'cursor-pointer';
        const opacityClass = isSubmitted ? 'opacity-60' : '';
    
        // Bepaal taskNameHtml buiten de template literal
        const taskNameHtml = isManager
            ? `<span class="font-medium text-gray-800">${task.name}</span>`
            : `<span class="font-medium cursor-pointer text-blue-600 hover:underline"
                    onclick="openReplaceTaskModal(${taskSetId}, ${task.id}, '${task.name.replace(/'/g, "\\'")}')">
                ${task.name}
              </span>`;
    
        html += `
            <div class="p-4 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors ${opacityClass}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center space-x-3">
                        <label class="flex items-center ${cursorClass}">
                            <input type="checkbox" 
                                   ${isCompleted ? 'checked' : ''} 
                                   ${disabledAttr}
                                   onchange="toggleTaskInSet(${taskSetId}, ${task.id}, this.checked)"
                                   class="sr-only">
                            <div class="w-5 h-5 rounded border-2 ${checkboxClass} flex items-center justify-center transition-colors">
                                ${isCompleted ? '<i class="fas fa-check text-white text-xs"></i>' : ''}
                            </div>
                        </label>
                        <div>
                            ${taskNameHtml}
                            ${requiredBadge}
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i>${task.time} min
                                <span class="mx-2">•</span>
                                <i class="fas fa-repeat mr-1"></i>${task.frequency}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Foto upload sectie - ALTIJD ZICHTBAAR -->
                <div class="border-t pt-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <input type="file" 
                                   id="photo-${taskSetId}-${task.id}" 
                                   accept="image/*" 
                                   onchange="uploadTaskPhoto(${taskSetId}, ${task.id}, this)"
                                   ${disabledAttr}
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">

                        </div>
                    </div>
                    <div id="photo-status-${taskSetId}-${task.id}" class="text-sm mt-2"></div>
                    <div id="photo-preview-${taskSetId}-${task.id}" class="mt-2"></div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    // Voeg samenvatting toe
    const progressPercentage = tasks.length > 0 ? Math.round((completedCount / tasks.length) * 100) : 0;
    const progressColor = progressPercentage === 100 ? 'bg-green-500' : progressPercentage >= 50 ? 'bg-yellow-500' : 'bg-red-500';
    
    html += `
        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Voortgang</span>
                <span class="text-sm text-gray-600">${completedCount}/${tasks.length} taken voltooid</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                <div class="${progressColor} h-2 rounded-full transition-all duration-300" style="width: ${progressPercentage}%"></div>
            </div>
            <div class="flex justify-between text-xs text-gray-600">
                <span><i class="fas fa-clock mr-1"></i>Totale tijd: ${totalTime} minuten</span>
                <span>${progressPercentage}% voltooid</span>
            </div>
        </div>
    `;
    
    // Voeg indienen knop toe als nog niet ingediend
    if (!taskSet.submitted) {
        html += `
            <div class="mt-4 text-center">
                <button onclick="submitTaskSet(${taskSetId})" 
                        class="bg-green-500 text-white px-6 py-2 rounded-lg font-medium hover:bg-green-600 transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>Indienen
                </button>
            </div>
        `;
    } else {
        html += `
            <div class="mt-4 text-center">
                <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i>
                    Reeds ingediend - Taken kunnen niet meer worden gewijzigd
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// Toggle task in set - SIMPELE VERSIE MET DIRECTE UPDATE
async function toggleTaskInSet(taskSetId, taskId, completed) {
    try {
        const response = await fetch('/api/toggle_task.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({task_set_id: taskSetId, task_id: taskId, completed: completed})
        });
        
        const data = await response.json();
        if (data.success) {
            // Vind het hele taak element
            const taskElement = document.querySelector(`input[onchange*="toggleTaskInSet(${taskSetId}, ${taskId}"]`).closest('.p-4');
            
            if (taskElement) {
                // Update alle visuele elementen in één keer
                const checkboxVisual = taskElement.querySelector('.w-5.h-5');
                const taskName = taskElement.querySelector('.font-medium');
                
                if (completed) {
                    // Voltooid styling
                    checkboxVisual.className = 'w-5 h-5 rounded border-2 bg-green-500 border-green-500 flex items-center justify-center transition-colors';
                    checkboxVisual.innerHTML = '<i class="fas fa-check text-white text-xs"></i>';
                    taskName.className = 'font-medium line-through text-gray-500';
                } else {
                    // Niet voltooid styling
                    checkboxVisual.className = 'w-5 h-5 rounded border-2 border-gray-300 flex items-center justify-center transition-colors';
                    checkboxVisual.innerHTML = '';
                    taskName.className = 'font-medium text-gray-800';
                }
            }
            
            showNotification(completed ? '✅ Taak voltooid!' : '↩️ Taak ongedaan gemaakt', 'success');
        } else {
            // Reset checkbox bij fout
            const checkbox = document.querySelector(`input[onchange*="toggleTaskInSet(${taskSetId}, ${taskId}"]`);
            if (checkbox) checkbox.checked = !completed;
            showNotification('❌ Fout: ' + data.error, 'error');
        }
    } catch (e) {
        // Reset checkbox bij fout
        const checkbox = document.querySelector(`input[onchange*="toggleTaskInSet(${taskSetId}, ${taskId}"]`);
        if (checkbox) checkbox.checked = !completed;
        showNotification('❌ Fout bij bijwerken: ' + e.message, 'error');
    }
}

// Submit task set
// Task set indienen - ZONDER RELOAD
async function submitTaskSet(taskSetId) {
    if (!confirm('Weet je zeker dat je deze dag wilt indienen? Dit kan niet ongedaan worden gemaakt.')) {
        return;
    }

    try {
        // First, get incomplete tasks from database
        const checkResponse = await fetch('/api/get_incomplete_tasks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_set_id: parseInt(taskSetId)
            })
        });

        if (!checkResponse.ok) {
            throw new Error(`HTTP error! status: ${checkResponse.status}`);
        }

        const checkData = await checkResponse.json();

        if (!checkData.success) {
            throw new Error(checkData.error || 'Failed to get incomplete tasks');
        }

        const incompleteTasks = checkData.incomplete_tasks || [];
        let incompleteReasons = {};

        // If there are incomplete tasks, ask for reasons
        console.log('Incomplete tasks detected from DB:', incompleteTasks);
        if (incompleteTasks.length > 0) {
            for (const task of incompleteTasks) {
                const reason = prompt(`Waarom is de taak "${task.name}" niet voltooid?\n\nGeef een korte uitleg:`);

                if (reason === null) {
                    // User cancelled
                    return;
                }

                if (!reason.trim()) {
                    showNotification('❌ Je moet een reden opgeven voor elke onvoltooide taak', 'error');
                    return;
                }

                incompleteReasons[task.id] = reason.trim();
            }
        }

        // Now submit the task set
        const response = await fetch('/api/submit_task_set.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                task_set_id: parseInt(taskSetId),
                incomplete_reasons: incompleteReasons
            })
        });

        // Check if response is ok first
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const responseText = await response.text();
        console.log('Raw response:', responseText); // Debug log

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Server returned invalid JSON response');
        }

        if (data.success) {
            let message = '✅ Taken succesvol ingediend!';

            // Voeg e-mail status toe aan melding
            if (data.mail_sent) {
                message += ` E-mail verzonden naar management (${data.completion_rate}% voltooid)`;
                 showPage('track')
                if (data.attachments && data.attachments > 0) {
                    message += ` met ${data.attachments} foto's`;
                }
            } else if (data.mail_error) {
                message += ` (E-mail kon niet worden verzonden: ${data.mail_error})`;
            }

            showNotification(message, 'success');

            // Update de UI direct zonder reload
            const taskSetElement = document.querySelector(`[data-task-set-id="${taskSetId}"]`);
            if (taskSetElement) {
                // Voeg "ingediend" styling toe
                taskSetElement.classList.add('opacity-60');
                const button = taskSetElement.querySelector('button[onclick*="submitTaskSet"]');
                if (button) {
                    button.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Ingediend';
                    button.disabled = true;
                    button.classList.remove('bg-green-500', 'hover:bg-green-600');
                    button.classList.add('bg-gray-400', 'cursor-not-allowed');
                }
            }
        } else {
            showNotification('❌ Fout: ' + data.error, 'error');
        }
    } catch (e) {
        console.error('Error:', e);
        if (e.message.includes('JSON')) {
            showNotification('❌ Server fout: Ongeldige response ontvangen. Controleer de server logs.', 'error');
        } else {
            showNotification('❌ Fout bij indienen: ' + e.message, 'error');
        }
    }
}



// Individuele task set verwijderen
async function deleteTaskSet(taskSetId, manager, day) {
    if (!confirm(`Weet je zeker dat je de schoonmaakdag wilt verwijderen?\n\nManager: ${manager}\nDag: ${day}\n\nDit kan niet ongedaan worden gemaakt!`)) {
        return;
    }

    try {
        const res = await fetch('/api/delete_task_set.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                task_set_id: taskSetId
            })
        });
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Onbekende fout bij verwijderen');
        }
        
        // Toon succesbericht
        showNotification('✅ Schoonmaakdag succesvol verwijderd!', 'success');
        
        // Herlaad de lijst
        loadAllTaskSetsForTracking();
        
    } catch (e) {
        console.error('Error deleting task set:', e);
        showNotification('❌ Fout bij verwijderen: ' + e.message, 'error');
    }
}

// Alle task sets verwijderen
async function deleteAllTaskSets() {
    if (!confirm('⚠️ WAARSCHUWING ⚠️\n\nWeet je ZEKER dat je ALLE opgeslagen schoonmaakdagen wilt verwijderen?\n\nDit kan NIET ongedaan worden gemaakt!\n\n- Alle opgeslagen taken worden verwijderd\n- Alle voortgang gaat verloren\n- Alle ingediende taken worden gewist\n\nKlik OK om door te gaan of Annuleren om te stoppen.')) {
        return;
    }
    
    // Dubbele bevestiging voor veiligheid
    if (!confirm('Laatste kans!\n\nAlle schoonmaakdagen en taken worden permanent verwijderd.\n\nWeet je het ZEKER?')) {
        return;
    }

    try {
        const res = await fetch('/api/delete_all_task_sets.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        });
        
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Onbekende fout bij verwijderen');
        }
        
        showDangerAlert('✅ Alle schoonmaakdagen zijn succesvol verwijderd!');
        
        // Herlaad de pagina om de lege staat te tonen
        loadAllTaskSetsForTracking();
        
    } catch (e) {
        console.error('Error deleting all task sets:', e);
        showDangerAlert('❌ Fout bij verwijderen van schoonmaakdagen:\n' + e.message);
    }
}

// Notificatie functie
function showNotification(message, type = 'info') {
    // Verwijder bestaande notificaties
    const existing = document.querySelector('.notification');
    if (existing) {
        existing.remove();
    }

    const notification = document.createElement('div');
    notification.className = `notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Verwijder na 3 seconden
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

// TAKEN BEHEREN SECTIE

// Taken laden
async function loadTasks() {
    try {
        const res = await fetch('/api/get_tasks.php');
        const data = await res.json();
        
        if (!data.success) {
            console.log('Fout bij laden taken: ' + data.error);
            return;
        }
        
        displayTasks(data.tasks);
    } catch (e) {
        console.log('Fout bij laden taken: ' + e.message);
    }
}

// Taken weergeven
// Taken weergeven - AANGEPASTE VERSIE
function displayTasks(tasks) {
    // Reset alle containers
    const containers = {
        'dagelijks': document.getElementById('daily-tasks'),
        'wekelijks': document.getElementById('weekly-tasks'),
        '2-wekelijks': document.getElementById('biweekly-tasks'),
        'maandelijks': document.getElementById('monthly-tasks')
    };

    const counts = {
        'dagelijks': 0,
        'wekelijks': 0,
        '2-wekelijks': 0,
        'maandelijks': 0
    };

    // Leeg alle containers
    Object.values(containers).forEach(container => {
        if (container) container.innerHTML = '';
    });

    if (!tasks || tasks.length === 0) {
        Object.values(containers).forEach(container => {
            if (container) {
                container.innerHTML = '<div class="text-center text-gray-500 py-4"><i class="fas fa-tasks mr-2"></i>Geen taken</div>';
            }
        });
        return;
    }

    // Verdeel taken per frequentie
    tasks.forEach((task, index) => {
        const container = containers[task.frequency];
        if (!container) return;

        counts[task.frequency]++;

        const requiredBadge = task.required == 1 ?
            '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">Verplicht</span>' :
            '<span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">Optioneel</span>';

        const bkBadge = task.is_bk == 1 ?
            '<span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full"><i class="fas fa-hamburger m-auto mr-auto flex justify-center text-orange-500 mr-1"></i></span>' : '';

        const deleteBtnHtml = isManager ? '' : `
            <button onclick="removeTask(${index})" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-colors">
                <i class="fas fa-trash"></i>
            </button>
        `;

        const bkToggleHtml = isManager ? '' : `
            <label class="flex items-center space-x-2 cursor-pointer">
                <input type="checkbox"
                       ${task.is_bk == 1 ? 'checked' : ''}
                       onchange="toggleTaskBK(${index}, this.checked)"
                       class="w-4 h-4 text-orange-600 bg-gray-100 border-gray-300 rounded focus:ring-orange-500">
                <span class="text-sm text-gray-600">BK</span>
            </label>
        `;

        const taskHtml = `
        <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
            <div class="flex-1">
                <div class="flex items-center space-x-3 mb-2">
                    <span class="font-medium text-gray-800">${task.name}</span>
                    ${requiredBadge}
                    ${bkBadge}
                </div>
                <div class="text-sm text-gray-600">
                    <i class="fas fa-clock mr-1"></i>${task.time} min
                </div>
            </div>
            <div class="flex items-center space-x-3">
                ${bkToggleHtml}
                ${deleteBtnHtml}
            </div>
        </div>
        `;

        container.innerHTML += taskHtml;
    });

    // Update counters
    document.getElementById('daily-count').textContent = counts['dagelijks'];
    document.getElementById('weekly-count').textContent = counts['wekelijks'];
    document.getElementById('biweekly-count').textContent = counts['2-wekelijks'];
    document.getElementById('monthly-count').textContent = counts['maandelijks'];
}

// Globale taken array
let allTasks = [];

// Update task
function updateTask(index, field, value) {
    if (!allTasks[index]) return;
    allTasks[index][field] = value;
}

// Remove task
// Toggle BK status for task
async function toggleTaskBK(index, isBK) {
    if (!allTasks[index]) return;

    const oldValue = allTasks[index].is_bk;
    allTasks[index].is_bk = isBK ? 1 : 0;

    // Update display direct
    displayTasks(allTasks);

    // Sla wijzigingen op naar database
    try {
        await saveAllTasks();
        showNotification(isBK ? '✅ Taak gemarkeerd als BK!' : '✅ BK markering verwijderd!', 'success');
    } catch (e) {
        // Bij fout, herstel oude waarde
        allTasks[index].is_bk = oldValue;
        displayTasks(allTasks);
        showNotification('❌ Fout bij bijwerken: ' + e.message, 'error');
    }
}

// Remove task - AANGEPASTE VERSIE
async function removeTask(index) {
    if (!confirm('Weet je zeker dat je deze taak wilt verwijderen?')) {
        return;
    }

    const taskToRemove = allTasks[index];

    // Verwijder uit lokale array
    allTasks.splice(index, 1);

    // Update display direct
    displayTasks(allTasks);

    // Sla wijzigingen op naar database
    try {
        await saveAllTasks();
        showNotification('✅ Taak succesvol verwijderd!', 'success');
    } catch (e) {
        // Bij fout, voeg taak terug toe
        allTasks.splice(index, 0, taskToRemove);
        displayTasks(allTasks);
        showNotification('❌ Fout bij verwijderen: ' + e.message, 'error');
    }
}
// Foto uploaden voor taak
// Foto uploaden voor taak - VERBETERDE VERSIE
// Foto uploaden - SIMPELE VERSIE
async function uploadTaskPhoto(taskSetId, taskId, fileInput) {
    const file = fileInput.files[0];
    if (!file) return;
    
    // Validatie
    if (!file.type.startsWith('image/')) {
        showNotification('❌ Alleen afbeeldingen zijn toegestaan', 'error');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        showNotification('❌ Bestand te groot (max 5MB)', 'error');
        return;
    }
    
    const statusDiv = document.getElementById(`photo-status-${taskSetId}-${taskId}`);
    const previewDiv = document.getElementById(`photo-preview-${taskSetId}-${taskId}`);
    
    statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Uploaden...';
    statusDiv.className = 'text-sm text-blue-600 mt-2';
    
    try {
        const formData = new FormData();
        formData.append('photo', file);
        formData.append('task_set_id', taskSetId);
        formData.append('task_id', taskId);
        
        const response = await fetch('/api/upload_photo.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            statusDiv.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i>Foto geüpload!';
            statusDiv.className = 'text-sm text-green-600 mt-2';
            
            // Toon preview
            previewDiv.innerHTML = `
                <img src="${data.photo_url}?t=${Date.now()}" alt="Taak foto" class="w-20 h-20 object-cover rounded border mt-2">
            `;
            
            showNotification('✅ Foto geüpload!', 'success');
        } else {
            statusDiv.innerHTML = '<i class="fas fa-times text-red-500 mr-1"></i>Fout: ' + data.error;
            statusDiv.className = 'text-sm text-red-600 mt-2';
            showNotification('❌ Upload fout: ' + data.error, 'error');
        }
    } catch (e) {
        statusDiv.innerHTML = '<i class="fas fa-times text-red-500 mr-1"></i>Netwerk fout';
        statusDiv.className = 'text-sm text-red-600 mt-2';
        showNotification('❌ Upload fout: ' + e.message, 'error');
    }
}


function addNewTask() {
    const taskName = document.getElementById('new-task').value.trim();
    const taskTime = parseInt(document.getElementById('task-time').value);
    const taskFrequency = document.getElementById('task-frequency').value;
    const taskRequired = document.getElementById('task-required').checked;
    const BkTask = document.getElementById('task-burgerkitchen').checked;

    if (!taskName) {
        showDangerAlert('Vul een taaknaam in');
        return;
    }

    const newTask = {
        name: taskName,
        time: taskTime,
        frequency: taskFrequency,
        required: taskRequired ? 1 : 0,
        is_bk: BkTask ? 1 : 0   // ✅ matcht met PHP
    };

    allTasks.push(newTask);
    displayTasks(allTasks);

    // Reset form
    document.getElementById('new-task').value = '';
    document.getElementById('task-time').value = 5;
    document.getElementById('task-frequency').value = 'dagelijks';
    document.getElementById('task-required').checked = false;
    document.getElementById('task-burgerkitchen').checked = false;

    // Save to backend
    saveAllTasks();
}
// Save all tasks
async function saveAllTasks() {
    if (!allTasks || allTasks.length === 0) {
        showDangerAlert('Geen taken om op te slaan');
        return;
    }

    try {
        const res = await fetch('/api/save_tasks.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                tasks: allTasks,
                bk: true
            })
        });

        const data = await res.json();

        if (!data.success) {
            console.log('Fout bij opslaan: ' + data.error);
            return;
        }

        showDangerAlert('✅ Taken succesvol opgeslagen!');
        loadTasks(); // Herlaad taken

    } catch (e) {
        console.log('Fout bij opslaan: ' + e.message);
    }
}

// Load tasks into global array when displaying
async function loadTasks() {
    try {
        const res = await fetch('/api/get_tasks.php');
        const data = await res.json();
        
        if (!data.success) {
            console.log('Fout bij laden taken: ' + data.error);
            return;
        }
        
        allTasks = data.tasks || [];
        displayTasks(allTasks);
    } catch (e) {
        console.log('Fout bij laden taken: ' + e.message);
    }
}



// Event listeners toevoegen
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    // Toon standaard de generator pagina
    showPage('generator');
    
    // Add task button
    if (!isManager) {
        loadStoresAndManagers();
    }

    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', (e) => {
            e.preventDefault();
            saveTaskSet();
        });
    }
    
    const addTaskBtn = document.getElementById('addTaskBtn');
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', addNewTask);
    }
  
    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', generateTasks);
    }
  
    if (isManager) {
        const genBtn = document.getElementById('btn-generator');
        if (genBtn) genBtn.style.display = 'none';
        showPage('track');
    } else {
        showPage('generator');
    }
});




let replaceModalTaskSetId = null;
let replaceModalOldTaskId = null;
let allTasksCache = null; // Cache alle taken voor selectie

// Open modal en vul data
function openReplaceTaskModal(taskSetId, oldTaskId, oldTaskName) {
    replaceModalTaskSetId = taskSetId;
    replaceModalOldTaskId = oldTaskId;
    
    document.getElementById('currentTaskName').textContent = `Vervang taak: "${oldTaskName}"`;
    
    // Laad alle taken als nog niet gedaan
    if (!allTasksCache) {
        fetch('/api/get_all_tasks.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allTasksCache = data.tasks;
                    populateReplacementSelect();
                } else {
                    console.log('Fout bij laden taken: ' + data.error);
                }
            });
    } else {
        populateReplacementSelect();
    }
    
    document.getElementById('replaceTaskModal').classList.remove('hidden');
}

// Vul select met taken die nog niet in deze task set zitten
function populateReplacementSelect() {
    const select = document.getElementById('replacementTaskSelect');
    select.innerHTML = '';
    
    // Haal taken die al in deze task set zitten
    const currentTaskIds = getCurrentTaskIds(replaceModalTaskSetId);
    
    allTasksCache.forEach(task => {
        if (task.id !== replaceModalOldTaskId && !currentTaskIds.includes(task.id)) {
            const option = document.createElement('option');
            option.value = task.id;
            option.textContent = task.name;
            select.appendChild(option);
        }
    });
    
    if (select.options.length === 0) {
        const option = document.createElement('option');
        option.textContent = 'Geen beschikbare taken om te vervangen';
        option.disabled = true;
        select.appendChild(option);
    }
}

// Sluit modal
function closeReplaceTaskModal() {
    document.getElementById('replaceTaskModal').classList.add('hidden');
}

// Haal huidige taak IDs van een task set (uit je data structuur)
function getCurrentTaskIds(taskSetId) {
    // Pas aan naar jouw data structuur, bijvoorbeeld:
    // return Object.keys(taskSets[taskSetId].tasks).map(id => parseInt(id));
    // Of als je een globale variabele hebt met de data:
    if (!window.taskSets || !window.taskSets[taskSetId]) return [];
    return window.taskSets[taskSetId].tasks.map(t => t.id);
}

// Bevestig vervanging
function confirmReplaceTask() {
    const select = document.getElementById('replacementTaskSelect');
    const newTaskId = parseInt(select.value);
    if (!newTaskId) {
        showDangerAlert('Selecteer een taak om te vervangen');
        return;
    }
    
    // Verstuur vervangingsverzoek naar backend
    fetch('/api/replace_task.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            task_set_id: replaceModalTaskSetId,
            old_task_id: replaceModalOldTaskId,
            new_task_id: newTaskId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showDangerAlert('Taak succesvol vervangen!');
            closeReplaceTaskModal();
            // Herlaad takenlijst (pas aan naar jouw functie)
            loadTaskSetDetails(replaceModalTaskSetId);
        } else {
            console.log('Fout: ' + data.error);
        }
    })
    .catch(() => showDangerAlert('Fout bij verbinden met server'));
}

let stores = [];
let managersByStore = {};

// Laad winkels en managers bij pagina laden
function loadStoresAndManagers() {
  let url = '/api/api_get_stores_and_managers.php';
  if (isStoremanager && typeof window.store_id !== 'undefined' && window.store_id) {
    url += `?store_id=${window.store_id}`; // store_id is Bussum winkel ID
  }

  fetch(url)
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        console.log('Fout bij laden winkels: ' + data.error);
        return;
      }
      stores = data.stores || [];
      managersByStore = data.managersByStore || {};

      const storeSelect = document.getElementById('storeSelect');

      if (isStoremanager && typeof window.store_id !== 'undefined' && window.store_id) {
        const managerStore = stores.find(store => store.id == window.store_id);
        if (managerStore) {
          storeSelect.innerHTML = `<option value="${managerStore.id}" selected>${managerStore.name}</option>`;
          storeSelect.disabled = true;
          // Managers dropdown vullen met managers van Bussum
          setTimeout(() => {
            storeSelect.value = managerStore.id;
            onStoreChange.call(storeSelect);
          }, 100);
        }
      } else {
        storeSelect.innerHTML = '<option value="">Selecteer winkel</option>';
        stores.forEach(store => {
          storeSelect.innerHTML += `<option value="${store.id}">${store.name}</option>`;
        });
        storeSelect.disabled = false;
        storeSelect.addEventListener('change', onStoreChange);
      }

      const managerSelect = document.getElementById('managerSelect');
      managerSelect.innerHTML = '<option value="">Selecteer winkel eerst</option>';
      managerSelect.disabled = true;
    })
    .catch(err => {
      console.log('Fout bij laden winkels: ' + err.message);
    });
}
function onStoreChange() {
  const storeId = this.value;
  const managerSelect = document.getElementById('managerSelect');
  managerSelect.innerHTML = '';

  if (!storeId || !managersByStore[storeId] || managersByStore[storeId].length === 0) {
    managerSelect.innerHTML = '<option value="">Geen managers beschikbaar</option>';
    managerSelect.disabled = true;
    return;
  }

  managerSelect.disabled = false;
  managerSelect.innerHTML = '<option value="">Selecteer manager</option>';
  managersByStore[storeId].forEach(manager => {
    managerSelect.innerHTML += `<option value="${manager.id}">${manager.username}</option>`;
  });
}

// Pas generateTasks aan om manager uit juiste dropdown te halen
async function generateTasks() {
  console.log('Generate tasks clicked');

  const managerSelect = document.getElementById('managerSelect');
  const storeSelect = document.getElementById('storeSelect').value;
  const manager = managerSelect.value;
  const day = document.getElementById('day').value;
  const maxDuration = document.getElementById('max-duration').value;

  console.log('Values:', { manager, day, maxDuration, storeSelect });

  if (!manager || !day) {
    showDangerAlert('Vul alle velden in');
    return;
  }

  // For store managers, validate they can only generate for their own store
  if (isStoremanager && window.store_id && storeSelect != window.store_id) {
    showDangerAlert('Je kunt alleen taken genereren voor je eigen winkel');
    return;
  }

  try {
    console.log('Sending request...');
    const res = await fetch('/api/generate_tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ manager, day, maxDuration: parseInt(maxDuration), storeSelect })
    });

    console.log('Response status:', res.status);
    const data = await res.json();
    console.log('Response data:', data);

    if (data.error) {
      console.log('Fout: ' + data.error);
      return;
    }

    currentTasks = data.tasks;
    displayGeneratedTasks(data.tasks, data.total_time);
  } catch (e) {
    console.error('Error:', e);
    console.log('Fout bij genereren taken: ' + e.message);
  }
} 



document.addEventListener('DOMContentLoaded', function () {
    if (!isManager) {
      loadStoresAndManagers();
    }

    const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', (e) => {
      e.preventDefault();  // voorkom standaard gedrag als het een button in een form is
      saveTaskSet();
    });
  }
  
    // Jouw bestaande eventlisteners
    const addTaskBtn = document.getElementById('addTaskBtn');
    if (addTaskBtn) {
      addTaskBtn.addEventListener('click', addNewTask);
    }
  
    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
      generateBtn.addEventListener('click', generateTasks);
    }
  
    if (isManager) {
      const genBtn = document.getElementById('btn-generator');
      if (genBtn) genBtn.style.display = 'none';
      showPage('track');
    } else {
      showPage('generator');
    }
  });

  // NIEUWE FUNCTIES DIE MOETEN WORDEN TOEGEVOEGD

// Laad gefilterde winkels op basis van gebruikersrol
async function loadFilteredStores() {
    try {
        const response = await fetch('/api/filtered_stores.php');
        const data = await response.json();
        
        if (data.success) {
            populateStoreDropdown(data.stores);
        } else {
            console.error('Fout bij laden winkels:', data.error);
        }
    } catch (error) {
        console.error('Netwerk fout:', error);
    }
}

// Laad managers op basis van regio
async function loadRegionManagers() {
    try {
        const response = await fetch('/api/region_managers.php');
        const data = await response.json();
        
        if (data.success) {
            populateManagerDropdown(data.managers);
        }
    } catch (error) {
        console.error('Fout bij laden managers:', error);
    }
}

// Vervang bestaande task generatie functie
async function generateTasksForRegion() {
    const selectedStores = getSelectedStores();
    const selectedManagers = getSelectedManagers();
    const taskData = getTaskFormData();
    
    if (!validateTaskData(taskData)) {
        showError('Vul alle verplichte velden in');
        return;
    }
    
    try {
        const response = await fetch('/api/generate_regional_tasks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                stores: selectedStores,
                managers: selectedManagers,
                taskData: taskData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(`${result.tasksCreated} taken succesvol aangemaakt`);
            refreshTaskList();
        } else {
            showError(result.error);
        }
    } catch (error) {
        showError('Fout bij aanmaken taken');
    }
}

// ===== FUNCTIES UIT HOME.PHP =====

// Mobile menu functionality
function toggleMobileMenu() {
    console.log('toggleMobileMenu called');
    const mobileNav = document.getElementById('mobile-nav');
    mobileNav.classList.toggle('mobile-menu-hidden');
}

function closeMobileMenu() {
    console.log('closeMobileMenu called');
    const mobileNav = document.getElementById('mobile-nav');
    mobileNav.classList.add('mobile-menu-hidden');
}

// Verbeterde showPage functie
function showPage(pageId) {
    try {
        console.log('=== SHOWPAGE CALLED ===');
        console.log('Page ID:', pageId);
        console.log('User role:', userRole);
        
        // Managers kunnen niet naar generator
        if (isManager && pageId === 'generator') {
            console.log('Manager blocked from generator');
            return;
        }
        
        // Verberg alle pagina's
        document.querySelectorAll('[id^="page-"]').forEach(page => {
            page.classList.add('hidden');
        });
        
        // Toon gewenste pagina
        const page = document.getElementById(`page-${pageId}`);
        if (page) {
            page.classList.remove('hidden');
            console.log('Page shown:', pageId);
        } else {
            console.warn(`Pagina met id page-${pageId} niet gevonden`);
            return;
        }
        
        // Update navigatie buttons
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        const activeBtn = document.getElementById(`btn-${pageId}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
        
        // Laad specifieke pagina data
        if (pageId === 'manage') {
            console.log('Loading tasks for manage page...');
            setTimeout(() => {
                if (typeof loadTasks === 'function') {
                    loadTasks();
                } else {
                    console.error('loadTasks function not found');
                }
            }, 100);
        }
        
        if (pageId === 'region' && isRegiomanager) {
            console.log('Loading region data...');
            setTimeout(() => {
                if (typeof loadRegionStores === 'function') {
                    loadRegionStores();
                } else {
                    console.error('loadRegionStores function not found');
                }
            }, 100);
        }
        
        if (pageId === 'regio-dashboard' && isRegiomanager) {
            console.log('Should load regio dashboard...');
            setTimeout(() => {
                if (typeof loadRegioDashboard === 'function') {
                    console.log('Calling loadRegioDashboard...');
                    loadRegioDashboard();
                } else {
                    console.error('loadRegioDashboard function not found');
                }
            }, 100);
        }
        
        if (pageId === 'track') {
            console.log('Loading task sets...');
            setTimeout(() => {
                if (typeof loadAllTaskSetsForTracking === 'function') {
                    loadAllTaskSetsForTracking();
                } else {
                    console.error('loadAllTaskSetsForTracking function not found');
                }
            }, 100);
        }
        
        console.log('showPage completed successfully');
        
    } catch (error) {
        console.error('Error in showPage:', error);
        console.error('Error stack:', error.stack);
    }
}

// Dashboard functie (placeholder - wordt ingevuld door regiomanager.js)
function loadRegioDashboard() {
    console.log('loadRegioDashboard called from script.js - should be overridden by regiomanager.js');
}

// Event listeners voor DOM loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== DOM LOADED IN SCRIPT.JS ===');
    console.log('User role on DOM load:', userRole);
    
    // Check if elements exist
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const mobileClose = document.getElementById('mobile-menu-close');
    const mobileNav = document.getElementById('mobile-nav');
    
    console.log('Mobile elements found:', {
        toggle: !!mobileToggle,
        close: !!mobileClose,
        nav: !!mobileNav
    });

    // Event listeners for mobile menu
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleMobileMenu);
        console.log('Mobile toggle listener added');
    }
    
    if (mobileClose) {
        mobileClose.addEventListener('click', closeMobileMenu);
        console.log('Mobile close listener added');
    }

    // Close mobile menu when clicking outside
    if (mobileNav) {
        mobileNav.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMobileMenu();
            }
        });
        console.log('Mobile nav outside click listener added');
    }
    
    // Initialize search functionality
    initializeSearch();
    
    // Load stores and managers for non-managers
    if (!isManager) {
        loadStoresAndManagers();
    }

    // Add event listeners for buttons
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', (e) => {
            e.preventDefault();
            saveTaskSet();
        });
    }
    
    const addTaskBtn = document.getElementById('addTaskBtn');
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', addNewTask);
    }

    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', generateTasks);
    }
    
    // Show default page
    setTimeout(() => {
        console.log('Setting default page...');
        if (isRegiomanager) {
            console.log('Showing region page for regiomanager');
            showPage('regio-dashboard');
        } else if (isManager) {
            console.log('Showing track page for manager');
            showPage('track');
        } else if (isAdmin) {
            console.log('Showing generator page for admin');
            showPage('generator');
        } else {
            console.log('Showing track page for default');
            showPage('track');
        }
    }, 200);
    
    console.log('=== SCRIPT SETUP COMPLETE ===');
});