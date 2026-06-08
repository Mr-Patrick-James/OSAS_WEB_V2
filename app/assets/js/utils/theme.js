// Theme Manager
function initializeTheme() {
  const savedTheme = localStorage.getItem('theme');

  if (savedTheme) {
    window.darkMode = savedTheme === 'dark';
  } else {
    // Default is always light — force-save so system preference doesn't override
    window.darkMode = false;
    localStorage.setItem('theme', 'light');
  }
  
  updateTheme();
  
  // Sync the switch mode checkbox - try multiple ways to find it
  const switchMode = window.switchMode || document.getElementById('switch-mode');
  if (switchMode) {
    switchMode.checked = window.darkMode;
    window.switchMode = switchMode; // Store globally
  }
  
  // Initialize PWA theme colors immediately
  updateThemeColor();
}

// Enhanced theme update with full PWA support
function updateTheme() {
  // Toggle dark class on body (for your current CSS)
  document.body.classList.toggle('dark', window.darkMode);
  
  // Also add dark-mode class for compatibility with login.js
  document.body.classList.toggle('dark-mode', window.darkMode);
  
  // Update theme toggle icon if exists
  const themeToggle = document.querySelector('.theme-toggle i');
  if (themeToggle) {
    if (window.darkMode) {
      themeToggle.classList.remove('fa-sun');
      themeToggle.classList.add('fa-moon');
    } else {
      themeToggle.classList.remove('fa-moon');
      themeToggle.classList.add('fa-sun');
    }
  }
  
  // Update theme-color meta tag for PWA
  updateThemeColor();
  
  // Update CSS variables for consistent theming
  updateCSSVariables();
}

// Enhanced PWA theme color update
function updateThemeColor() {
  const themeColorMeta = document.querySelector('meta[name="theme-color"]');
  const msThemeColorMeta = document.querySelector('meta[name="msapplication-TileColor"]');
  const appleStatusBarMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
  
  // Primary theme colors - darkest dark mode
  const themeColor = window.darkMode ? '#0F0F0F' : '#D4AF37';
  const secondaryColor = window.darkMode ? '#1A1A1A' : '#D4AF37';
  
  // Update or create standard theme-color meta tag
  if (themeColorMeta) {
    themeColorMeta.setAttribute('content', themeColor);
  } else {
    const meta = document.createElement('meta');
    meta.name = 'theme-color';
    meta.content = themeColor;
    document.head.appendChild(meta);
  }
  
  // Update Microsoft Tile Color
  if (msThemeColorMeta) {
    msThemeColorMeta.setAttribute('content', themeColor);
  } else {
    const meta = document.createElement('meta');
    meta.name = 'msapplication-TileColor';
    meta.content = themeColor;
    document.head.appendChild(meta);
  }
  
  // Update Apple Status Bar (for iOS Safari)
  const appleStatusBar = window.darkMode ? 'black-translucent' : 'default';
  if (appleStatusBarMeta) {
    appleStatusBarMeta.setAttribute('content', appleStatusBar);
  } else {
    const meta = document.createElement('meta');
    meta.name = 'apple-mobile-web-app-status-bar-style';
    meta.content = appleStatusBar;
    document.head.appendChild(meta);
  }
  
  // Update manifest theme color if manifest exists
  updateManifestThemeColor(themeColor);
  
  console.log('🎨 PWA Theme updated:', { theme: window.darkMode ? 'dark' : 'light', color: themeColor });
}

// Update manifest.json theme color dynamically
function updateManifestThemeColor(themeColor) {
  const manifestLink = document.querySelector('link[rel="manifest"]');
  if (manifestLink) {
    // Note: This requires the manifest to be served with CORS headers
    fetch(manifestLink.href)
      .then(response => response.json())
      .then(manifest => {
        manifest.theme_color = themeColor;
        // Create a new blob URL with updated manifest
        const updatedManifest = JSON.stringify(manifest);
        const blob = new Blob([updatedManifest], { type: 'application/json' });
        const newManifestUrl = URL.createObjectURL(blob);
        manifestLink.href = newManifestUrl;
      })
      .catch(error => {
        console.log('⚠️ Could not update manifest theme color:', error);
      });
  }
}

// Update CSS variables for consistent theming
function updateCSSVariables() {
  const root = document.documentElement;
  
  if (window.darkMode) {
    // Darkest dark mode - very dark but not pure black
    root.style.setProperty('--primary-bg', '#0F0F0F');
    root.style.setProperty('--secondary-bg', '#1A1A1A');
    root.style.setProperty('--text-primary', '#FFFFFF');
    root.style.setProperty('--text-secondary', '#B8B8B8');
    root.style.setProperty('--accent-color', '#FFD700');
    root.style.setProperty('--border-color', '#2A2A2A');
  } else {
    // Warmer, softer light mode - less harsh white
    root.style.setProperty('--primary-bg', '#F0F0ED');
    root.style.setProperty('--secondary-bg', '#FAFAF8');
    root.style.setProperty('--text-primary', '#1A1A1A');
    root.style.setProperty('--text-secondary', '#555555');
    root.style.setProperty('--accent-color', '#D4AF37');
    root.style.setProperty('--border-color', '#D8D8D5');
  }
}

// Enhanced theme toggle with PWA support
function toggleTheme() {
  window.darkMode = !window.darkMode;
  updateTheme();
  localStorage.setItem('theme', window.darkMode ? 'dark' : 'light');
  console.log('🔁 Theme toggled to:', window.darkMode ? 'dark' : 'light');
  
  // Sync switch mode checkbox
  const switchMode = window.switchMode || document.getElementById('switch-mode');
  if (switchMode) {
    switchMode.checked = window.darkMode;
  }
  
  // Force PWA theme update
  updateThemeColor();
  
  // Reinitialize charts if they exist (to update chart colors)
  if (typeof initializeCharts === 'function') {
    setTimeout(initializeCharts, 300);
  }
  
  // Dispatch custom event for other components to listen to
  document.dispatchEvent(new CustomEvent('themeChanged', { 
    detail: { darkMode: window.darkMode } 
  }));
}

// System theme changes are intentionally ignored —
// the app defaults to light mode and respects only the user's explicit toggle.