// Eye Care Manager - Blue Light Filter for Light Mode
// This feature reduces eye strain by applying a warm filter in light mode

// Initialize eye care functionality
function initializeEyeCare() {
  // Check saved preference
  const savedEyeCare = localStorage.getItem('eyeCareEnabled');
  const eyeCareEnabled = savedEyeCare === 'true';
  
  // Ensure darkMode is set (fallback if not initialized)
  if (typeof window.darkMode === 'undefined') {
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    window.darkMode = savedTheme ? savedTheme === 'dark' : systemPrefersDark;
  }
  
  // Check if body has dark class (another way to check dark mode)
  const isDarkMode = window.darkMode || document.body.classList.contains('dark');
  
  // Only enable if in light mode
  if (!isDarkMode && eyeCareEnabled) {
    enableEyeCare();
  } else {
    disableEyeCare();
  }
  
  // Sync toggle button if exists
  const eyeCareToggle = document.getElementById('eye-care-toggle');
  if (eyeCareToggle) {
    eyeCareToggle.checked = eyeCareEnabled && !isDarkMode;
    updateEyeCareButtonState();
  }
  
  console.log('👁️ Eye Care initialized:', { enabled: eyeCareEnabled && !isDarkMode, darkMode: isDarkMode });
}

// Enable eye care filter (blue light reduction)
function enableEyeCare() {
  // Don't enable in dark mode - check multiple sources
  const isDarkMode = window.darkMode || document.body.classList.contains('dark');
  if (isDarkMode) {
    console.log('⚠️ Eye Care cannot be enabled in dark mode');
    return;
  }
  
  // Create or update the eye care filter overlay
  let eyeCareOverlay = document.getElementById('eye-care-overlay');
  
  if (!eyeCareOverlay) {
    eyeCareOverlay = document.createElement('div');
    eyeCareOverlay.id = 'eye-care-overlay';
    eyeCareOverlay.setAttribute('aria-hidden', 'true');
    document.body.appendChild(eyeCareOverlay);
  }
  
  eyeCareOverlay.classList.add('active');
  localStorage.setItem('eyeCareEnabled', 'true');
  
  // Update toggle button
  const eyeCareToggle = document.getElementById('eye-care-toggle');
  if (eyeCareToggle) {
    eyeCareToggle.checked = true;
    updateEyeCareButtonState();
  }
  
  console.log('✅ Eye Care enabled');
  
  // Dispatch event for other components
  document.dispatchEvent(new CustomEvent('eyeCareChanged', { 
    detail: { enabled: true } 
  }));
}

// Disable eye care filter
function disableEyeCare() {
  const eyeCareOverlay = document.getElementById('eye-care-overlay');
  
  if (eyeCareOverlay) {
    eyeCareOverlay.classList.remove('active');
  }
  
  localStorage.setItem('eyeCareEnabled', 'false');
  
  // Update toggle button
  const eyeCareToggle = document.getElementById('eye-care-toggle');
  if (eyeCareToggle) {
    eyeCareToggle.checked = false;
    updateEyeCareButtonState();
  }
  
  console.log('❌ Eye Care disabled');
  
  // Dispatch event for other components
  document.dispatchEvent(new CustomEvent('eyeCareChanged', { 
    detail: { enabled: false } 
  }));
}

// Toggle eye care on/off
function toggleEyeCare() {
  // Don't allow enabling in dark mode - check multiple sources
  const isDarkMode = window.darkMode || document.body.classList.contains('dark');
  if (isDarkMode) {
    console.log('⚠️ Eye Care is not available in dark mode');
    showEyeCareNotification('Eye Care is only available in light mode', 'info');
    // Ensure toggle is unchecked
    const eyeCareToggle = document.getElementById('eye-care-toggle');
    if (eyeCareToggle && eyeCareToggle.checked) {
      eyeCareToggle.checked = false;
      updateEyeCareButtonState();
    }
    return;
  }
  
  const eyeCareToggle = document.getElementById('eye-care-toggle');
  const isEnabled = eyeCareToggle ? eyeCareToggle.checked : false;
  
  if (isEnabled) {
    enableEyeCare();
    showEyeCareNotification('Eye Care enabled - Reduced blue light filter active', 'success');
  } else {
    disableEyeCare();
    showEyeCareNotification('Eye Care disabled', 'info');
  }
}

// Update eye care button visual state
function updateEyeCareButtonState() {
  const eyeCareToggle = document.getElementById('eye-care-toggle');
  const eyeCareLabel = document.querySelector('label[for="eye-care-toggle"]');
  
  if (eyeCareToggle && eyeCareLabel) {
    const isEnabled = eyeCareToggle.checked;
    // Check multiple sources for dark mode
    const isDisabled = window.darkMode || document.body.classList.contains('dark');
    
    if (isDisabled) {
      eyeCareLabel.classList.add('disabled');
      eyeCareLabel.title = 'Eye Care is only available in light mode';
      // Uncheck if in dark mode
      if (eyeCareToggle.checked) {
        eyeCareToggle.checked = false;
      }
    } else {
      eyeCareLabel.classList.remove('disabled');
      eyeCareLabel.title = isEnabled ? 'Disable Eye Care' : 'Enable Eye Care';
    }
  }
}

// Show notification for eye care actions
function showEyeCareNotification(message, type = 'info') {
  // Use existing notification system if available
  if (typeof showNotification === 'function') {
    showNotification(message, type);
    return;
  }
  
  // Fallback: create simple notification
  const notification = document.createElement('div');
  notification.className = `eye-care-notification eye-care-notification-${type}`;
  notification.textContent = message;
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.classList.add('show');
  }, 10);
  
  setTimeout(() => {
    notification.classList.remove('show');
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Listen for theme changes to auto-disable eye care in dark mode
document.addEventListener('themeChanged', function(e) {
  const isDarkMode = e.detail.darkMode;
  
  if (isDarkMode) {
    // Automatically disable eye care when switching to dark mode
    const eyeCareToggle = document.getElementById('eye-care-toggle');
    if (eyeCareToggle && eyeCareToggle.checked) {
      disableEyeCare();
      showEyeCareNotification('Eye Care disabled automatically in dark mode', 'info');
    }
  } else {
    // Update button state when switching to light mode
    updateEyeCareButtonState();
    
    // Re-enable if it was previously enabled
    const savedEyeCare = localStorage.getItem('eyeCareEnabled');
    if (savedEyeCare === 'true') {
      setTimeout(() => {
        enableEyeCare();
      }, 100);
    }
  }
  
  updateEyeCareButtonState();
});

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeEyeCare);
} else {
  initializeEyeCare();
}

// Export functions for global use
window.initializeEyeCare = initializeEyeCare;
window.enableEyeCare = enableEyeCare;
window.disableEyeCare = disableEyeCare;
window.toggleEyeCare = toggleEyeCare;

