function showSuccessAlert(message) {
  const alert = document.getElementById("success-alert");
  const messageElement = document.getElementById("success-message");

  messageElement.textContent = message;
  alert.classList.add("show");
  alert.style.display = "block";
  setTimeout(() => {
    alert.style.transform = "translate(-50%, 0)";
    alert.style.opacity = "1";
  }, 50);
}

function showDangerAlert(message) {
  const alert = document.getElementById("error-alert");
  const messageElement = document.getElementById("error-message");

  messageElement.textContent = message;
  alert.classList.add("show");
  alert.style.display = "block";
  setTimeout(() => {
    alert.style.transform = "translate(-50%, 0)";
    alert.style.opacity = "1";
  }, 50);
} 

function hideSuccess() {
  const alert = document.getElementById("success-alert");
  alert.style.transform = "translate(-50%, -100%)";
  alert.style.opacity = "0";
  setTimeout(() => {
    alert.style.display = "none";
  }, 500);
}

function hideError() {
  const alert = document.getElementById("error-alert");
  alert.classList.remove("show");
  alert.style.transform = "translate(-50%, -100%)";
  alert.style.opacity = "0";
  setTimeout(() => {
    alert.style.display = "none";
  }, 500);
}

function showDangerAlert(message) {
  document.getElementById("danger-modal-message").textContent =
    message || "This action cannot be undone!";
  document.getElementById("danger-modal").classList.remove("hidden");
}

function hideDanger() {
  document.getElementById("danger-modal").classList.add("hidden");
}
