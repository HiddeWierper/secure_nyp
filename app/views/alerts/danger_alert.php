<div id="danger-modal" class="relative z-10 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
  <div class="fixed inset-0 bg-gray-500/30 transition-opacity" aria-hidden="true"></div>
  <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div id="modal-container" class="relative transform overflow-hidden rounded-lg bg-red-50 px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
        <div class="absolute top-0 right-0 hidden pt-4 pr-4 sm:block">
          <button type="button" onclick="hideDanger()" id="close-button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-hidden">
            <span class="sr-only">Close</span>
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="sm:flex sm:items-start">
          <div id="icon-container" class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10">
            <svg id="alert-icon" class="size-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
          </div>
          <div class="mt-3 sm:mt-0 sm:ml-4">
            <h3 class="text-base font-semibold text-gray-900" id="modal-title">Fout!</h3>
            <div class="mt-2">
              <p id="danger-modal-message" class="text-lg text-red-800 text-center"></p>
            </div>
          </div>
        </div>
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <button type="button" onclick="hideDanger()" id="ok-button" class="inline-flex w-full justify-center rounded-md bg-red-800 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 sm:ml-3 sm:w-auto">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function checkMessageForSuccess() {
    const messageElement = document.getElementById('danger-modal-message');
    const modalContainer = document.getElementById('modal-container');
    const iconContainer = document.getElementById('icon-container');
    const alertIcon = document.getElementById('alert-icon');
    const modalTitle = document.getElementById('modal-title');
    const closeButton = document.getElementById('close-button');
    const okButton = document.getElementById('ok-button');

    if (messageElement && messageElement.textContent) {
        const message = messageElement.textContent.toLowerCase();

        // Check if message contains success indicators (tick, checkmark, success, etc.)
        if (message.includes('✓') || message.includes('✔') || message.includes('success') ||
            message.includes('succes') || message.includes('gelukt') || message.includes('✅')|| message.includes('voltooid')) {

            // Change to success styling - remove red classes and add green ones
            modalContainer.classList.remove('bg-red-50');
            modalContainer.classList.add('bg-green-50');

            iconContainer.classList.remove('bg-red-100');
            iconContainer.classList.add('bg-green-100');

            alertIcon.classList.remove('text-red-600');
            alertIcon.classList.add('text-green-600');

            messageElement.classList.remove('text-red-800');
            messageElement.classList.add('text-green-800');

            modalTitle.textContent = 'Succes!';

            closeButton.classList.remove('focus:ring-red-500');
            closeButton.classList.add('focus:ring-green-500');

            okButton.classList.remove('bg-red-800', 'hover:bg-red-700');
            okButton.classList.add('bg-green-800', 'hover:bg-green-700');

            // Change icon to checkmark
            alertIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />';
        }
    }
}

// Check when modal is shown
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
            const modal = document.getElementById('danger-modal');
            if (modal && !modal.classList.contains('hidden')) {
                setTimeout(checkMessageForSuccess, 100); // Small delay to ensure message is set
            }
        }
    });
});

// Start observing
const modal = document.getElementById('danger-modal');
if (modal) {
    observer.observe(modal, { attributes: true });
}
</script>

<style>
.danger-alert {
    top: 4%; /* Position from the top */
    left: 50%; /* Center horizontally */
    transform: translateX(-50%) translateY(-100%); /* Center precisely and start off-screen */
    opacity: 0; /* Start invisible */
    transition: transform 0.5s ease-out, opacity 0.5s ease-out; /* Smooth transition */
}

.danger-alert.show {
    transform: translateX(-50%) translateY(0); /* Move into view */
    opacity: 1; /* Make visible */
}
</style>