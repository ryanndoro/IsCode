// Main JavaScript file for MedTrack

// DOM Content Loaded Event
document.addEventListener("DOMContentLoaded", () => {
  // Initialize tabs if present
  if (document.querySelector(".auth-tabs")) {
    initAuthTabs();
  }

  if (document.querySelector(".profile-tabs")) {
    initProfileTabs();
  }

  if (document.querySelector(".patient-tabs")) {
    initTabs(".patient-tabs", ".patient-tab-content");
  }

  if (document.querySelector(".settings-sidebar")) {
    initSettingsTabs();
  }

  // Initialize emergency button if present
  if (document.getElementById("emergency-btn")) {
    initEmergencyButton();
  }

  // Initialize dashboard navigation
  if (document.querySelector(".sidebar-nav")) {
    initDashboardNav();
  }

  // Initialize patient search and filter if on doctor dashboard
  if (document.getElementById("patient-search")) {
    initPatientSearch();
  }

  // Initialize back to patients button
  if (document.getElementById("back-to-patients")) {
    initBackToPatients();
  }

  // Initialize view patient buttons
  const viewPatientBtns = document.querySelectorAll(".view-patient-btn");
  if (viewPatientBtns.length > 0) {
    initViewPatientButtons();
  }

  // Initialize message patient buttons
  const messagePatientBtns = document.querySelectorAll(".message-patient-btn");
  if (messagePatientBtns.length > 0) {
    initMessagePatientButtons();
  }

  // Initialize respond to alert buttons
  const respondAlertBtns = document.querySelectorAll(".respond-alert-btn");
  if (respondAlertBtns.length > 0) {
    initRespondAlertButtons();
  }

  // Initialize FAQ items
  const faqItems = document.querySelectorAll(".faq-item");
  if (faqItems.length > 0) {
    initFaqItems();
  }
});

// Initialize auth tabs
function initAuthTabs() {
  const loginTab = document.getElementById("login-tab");
  const registerTab = document.getElementById("register-tab");
  const loginForm = document.getElementById("login-form");
  const registerForm = document.getElementById("register-form");

  if (loginTab && registerTab && loginForm && registerForm) {
    loginTab.addEventListener("click", () => {
      loginTab.classList.add("active");
      registerTab.classList.remove("active");
      loginForm.classList.remove("hidden");
      registerForm.classList.add("hidden");
    });

    registerTab.addEventListener("click", () => {
      registerTab.classList.add("active");
      loginTab.classList.remove("active");
      registerForm.classList.remove("hidden");
      loginForm.classList.add("hidden");
    });
  }
}

// Initialize profile tabs
function initProfileTabs() {
  initTabs(".profile-tabs", ".profile-tab-content");
}

// Initialize tabs
function initTabs(tabContainerSelector, contentSelector) {
  const tabButtons = document.querySelectorAll(`${tabContainerSelector} [data-tab]`);
  
  tabButtons.forEach((button) => {
    button.addEventListener("click", () => {
      // Remove active class from all tabs
      tabButtons.forEach((btn) => btn.classList.remove("active"));
      
      // Add active class to clicked tab
      button.classList.add("active");
      
      // Hide all tab content
      const tabContents = document.querySelectorAll(contentSelector);
      tabContents.forEach((content) => content.classList.add("hidden"));
      
      // Show selected tab content
      const tabId = button.getAttribute("data-tab");
      const selectedContent = document.getElementById(`${tabId}-tab`);
      if (selectedContent) {
        selectedContent.classList.remove("hidden");
      }
    });
  });
}

// Initialize settings tabs
function initSettingsTabs() {
  const settingsLinks = document.querySelectorAll(".settings-sidebar a");
  const settingsSections = document.querySelectorAll(".settings-section");

  settingsLinks.forEach((link) => {
    link.addEventListener("click", function(e) {
      e.preventDefault();
      
      // Remove active class from all links
      settingsLinks.forEach((l) => l.classList.remove("active"));
      
      // Add active class to clicked link
      this.classList.add("active");
      
      // Hide all sections
      settingsSections.forEach((section) => section.classList.add("hidden"));
      
      // Show selected section
      const targetId = this.getAttribute("href").substring(1);
      document.getElementById(targetId).classList.remove("hidden");
    });
  });
}

// Initialize dashboard navigation
function initDashboardNav() {
  const navLinks = document.querySelectorAll(".sidebar-nav a");
  const dashboardSections = document.querySelectorAll(".dashboard-section");
  
  navLinks.forEach((link) => {
    link.addEventListener("click", function(e) {
      e.preventDefault();
      
      // Remove active class from all links
      navLinks.forEach((l) => l.classList.remove("active"));
      
      // Add active class to clicked link
      this.classList.add("active");
      
      // Hide all sections
      dashboardSections.forEach((section) => section.classList.add("hidden"));
      
      // Show selected section
      const targetId = this.getAttribute("href").substring(1);
      document.getElementById(targetId).classList.remove("hidden");
    });
  });
}

// Initialize emergency button
function initEmergencyButton() {
  const emergencyBtn = document.getElementById("emergency-btn");
  
  emergencyBtn.addEventListener("click", function() {
    if (confirm("Are you sure you want to send an emergency alert? This should only be used in genuine medical emergencies.")) {
      // In a real app, this would send an AJAX request to the server
      showToast("Emergency alert sent. A healthcare provider will contact you shortly.", "success");
    }
  });
}

// Initialize patient search and filter
function initPatientSearch() {
  const searchInput = document.getElementById("patient-search");
  const filterSelect = document.getElementById("patient-filter");
  
  if (searchInput) {
    searchInput.addEventListener("input", filterPatients);
  }
  
  if (filterSelect) {
    filterSelect.addEventListener("change", filterPatients);
  }
  
  function filterPatients() {
    const searchTerm = searchInput.value.toLowerCase();
    const statusFilter = filterSelect.value;
    
    const patientCards = document.querySelectorAll(".patient-card");
    
    patientCards.forEach((card) => {
      const patientName = card.querySelector(".patient-name").textContent.toLowerCase();
      const patientId = card.querySelector("p").textContent.toLowerCase();
      const patientStatus = card.getAttribute("data-status").toLowerCase();
      
      const matchesSearch = patientName.includes(searchTerm) || patientId.includes(searchTerm);
      const matchesFilter = statusFilter === "all" || patientStatus === statusFilter.toLowerCase();
      
      if (matchesSearch && matchesFilter) {
        card.style.display = "block";
      } else {
        card.style.display = "none";
      }
    });
  }
}

// Initialize back to patients button
function initBackToPatients() {
  const backBtn = document.getElementById("back-to-patients");
  
  backBtn.addEventListener("click", function() {
    document.getElementById("patient-details").classList.add("hidden");
    document.getElementById("patients").classList.remove("hidden");
  });
}

// Initialize view patient buttons
function initViewPatientButtons() {
  const viewPatientBtns = document.querySelectorAll(".view-patient-btn");
  
  viewPatientBtns.forEach((btn) => {
    btn.addEventListener("click", function() {
      const patientId = this.getAttribute("data-id");
      
      // In a real app, this would fetch patient details from the server
      // For demo purposes, we'll just show the patient details section
      document.getElementById("patients").classList.add("hidden");
      document.getElementById("patient-details").classList.remove("hidden");
      
      // Set patient ID on message button
      const messagePatientBtn = document.getElementById("message-patient-btn");
      if (messagePatientBtn) {
        messagePatientBtn.setAttribute("data-patient-id", patientId);
      }
    });
  });
}

// Initialize message patient buttons
function initMessagePatientButtons() {
  const messagePatientBtns = document.querySelectorAll(".message-patient-btn");
  
  messagePatientBtns.forEach((btn) => {
    btn.addEventListener("click", function() {
      const patientId = this.getAttribute("data-patient-id");
      
      // Switch to messages tab
      const messagesTab = document.querySelector('.sidebar-nav a[href="#messages"]');
      if (messagesTab) {
        messagesTab.click();
        
        // In a real app, this would select the patient in the contacts list
        showToast("Messaging interface opened", "info");
      }
    });
  });
}

// Initialize respond to alert buttons
function initRespondAlertButtons() {
  const respondAlertBtns = document.querySelectorAll(".respond-alert-btn");
  
  respondAlertBtns.forEach((btn) => {
    btn.addEventListener("click", function() {
      const alertId = this.getAttribute("data-id");
      const patientId = this.getAttribute("data-patient-id");
      
      // In a real app, this would open a modal to respond to the alert
      const response = prompt("Enter your response to the emergency alert:");
      
      if (response) {
        // In a real app, this would send an AJAX request to the server
        showToast("Response sent successfully", "success");
        
        // Update UI to reflect the response
        this.closest(".alert-item").style.opacity = "0.5";
        this.textContent = "Responded";
        this.disabled = true;
      }
    });
  });
}

// Initialize FAQ items
function initFaqItems() {
  const faqItems = document.querySelectorAll(".faq-item");
  
  faqItems.forEach((item) => {
    const question = item.querySelector(".faq-question");
    const answer = item.querySelector(".faq-answer");
    
    question.addEventListener("click", function() {
      // Toggle answer visibility
      if (answer.style.display === "block") {
        answer.style.display = "none";
        question.classList.remove("active");
      } else {
        answer.style.display = "block";
        question.classList.add("active");
      }
    });
  });
}

// Show toast notification
function showToast(message, type = "info", duration = 3000) {
  // Create toast container if it doesn't exist
  let toastContainer = document.querySelector(".toast-container");
  
  if (!toastContainer) {
    toastContainer = document.createElement("div");
    toastContainer.className = "toast-container";
    document.body.appendChild(toastContainer);
  }
  
  // Create toast
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  
  // Add close button
  const closeButton = document.createElement("button");
  closeButton.className = "toast-close";
  closeButton.innerHTML = "&times;";
  closeButton.addEventListener("click", () => {
    removeToast(toast);
  });
  
  toast.appendChild(closeButton);
  
  // Add to container
  toastContainer.appendChild(toast);
  
  // Animate in
  setTimeout(() => {
    toast.classList.add("show");
  }, 10);
  
  // Auto remove after duration
  setTimeout(() => {
    removeToast(toast);
  }, duration);
  
  return toast;
}

// Remove toast
function removeToast(toast) {
  toast.classList.remove("show");
  
  setTimeout(() => {
    if (toast.parentNode) {
      toast.parentNode.removeChild(toast);
      
      // Remove container if empty
      const toastContainer = document.querySelector(".toast-container");
      if (toastContainer && !toastContainer.hasChildNodes()) {
        document.body.removeChild(toastContainer);
      }
    }
  }, 300);
}

// Create modal
function createModal(title, content, buttons) {
  // Create modal container
  const modalOverlay = document.createElement("div");
  modalOverlay.className = "modal-overlay";
  
  const modalContainer = document.createElement("div");
  modalContainer.className = "modal-container";
  
  // Create modal header
  const modalHeader = document.createElement("div");
  modalHeader.className = "modal-header";
  
  const modalTitle = document.createElement("h3");
  modalTitle.textContent = title;
  
  const closeButton = document.createElement("button");
  closeButton.className = "modal-close";
  closeButton.innerHTML = "&times;";
  closeButton.addEventListener("click", () => closeModal(modalOverlay));
  
  modalHeader.appendChild(modalTitle);
  modalHeader.appendChild(closeButton);
  
  // Create modal body
  const modalBody = document.createElement("div");
  modalBody.className = "modal-body";
  
  if (typeof content === "string") {
    modalBody.innerHTML = content;
  } else {
    modalBody.appendChild(content);
  }
  
  // Create modal footer
  const modalFooter = document.createElement("div");
  modalFooter.className = "modal-footer";
  
  if (buttons && buttons.length) {
    buttons.forEach((action) => {
      const button = document.createElement("button");
      button.className = `btn ${action.class || "secondary-btn"}`;
      button.textContent = action.text;
      
      if (action.onClick) {
        button.addEventListener("click", () => {
          action.onClick();
          if (action.closeOnClick !== false) {
            closeModal(modalOverlay);
          }
        });
      } else {
        button.addEventListener("click", () => closeModal(modalOverlay));
      }
      
      modalFooter.appendChild(button);
    });
  }
  
  // Assemble modal
  modalContainer.appendChild(modalHeader);
  modalContainer.appendChild(modalBody);
  modalContainer.appendChild(modalFooter);
  modalOverlay.appendChild(modalContainer);
  
  // Add to document
  document.body.appendChild(modalOverlay);
  
  // Prevent body scrolling
  document.body.style.overflow = "hidden";
  
  return modalOverlay;
}

// Close modal
function closeModal(modalOverlay) {
  modalOverlay.classList.add("closing");
  
  setTimeout(() => {
    document.body.removeChild(modalOverlay);
    
    // Restore body scrolling
    if (!document.querySelector(".modal-overlay")) {
      document.body.style.overflow = "";
    }
  }, 300);
}

// Format date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}

// Get time ago
function getTimeAgo(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const seconds = Math.floor((now - date) / 1000);
  
  if (seconds < 60) return "just now";
  
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
  
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
  
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days} day${days > 1 ? 's' : ''} ago`;
  
  const months = Math.floor(days / 30);
  if (months < 12) return `${months} month${months > 1 ? 's' : ''} ago`;
  
  const years = Math.floor(months / 12);
  return `${years} year${years > 1 ? 's' : ''} ago`;
}