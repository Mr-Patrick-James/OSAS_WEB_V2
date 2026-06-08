// Dashboard Module
function initDashboardModule() {
  console.log('📊 Initializing Dashboard Module');
  
  // Initialize dashboard data (will update stats, charts, and tables with real database data)
  if (typeof initDashboardData === 'function') {
    setTimeout(() => {
      initDashboardData();
    }, 200);
  } else {
    // Fallback to old chart initialization if dashboard data loader not available
    if (typeof initializeCharts === 'function') {
      setTimeout(initializeCharts, 100);
    }
  }
  
  // Initialize announcements
  if (typeof initializeAnnouncements === 'function') {
    setTimeout(initializeAnnouncements, 100);
  }
  
  // Initialize any dashboard-specific event listeners
  initializeDashboardEvents();
}

// Dashboard-specific functions
function initializeDashboardEvents() {
  // Add any dashboard-specific event listeners here
  console.log('🎯 Dashboard events initialized');
}

// Enhanced announcements functionality
function toggleAnnouncements() {
  const content = document.getElementById('announcementsContent');
  const toggle = document.querySelector('.announcement-toggle');
  
  if (content && toggle) {
    content.classList.toggle('collapsed');
    toggle.classList.toggle('rotated');
    
    // Save state to localStorage
    const isCollapsed = content.classList.contains('collapsed');
    localStorage.setItem('announcementsCollapsed', isCollapsed);
  }
}

// Initialize announcements with saved state
function initializeAnnouncements() {
  console.log('📢 Initializing announcements...');
  
  // Restore collapsed state
  const savedState = localStorage.getItem('announcementsCollapsed');
  const content = document.getElementById('announcementsContent');
  const toggle = document.querySelector('.announcement-toggle');
  
  if (content && toggle && savedState === 'true') {
    content.classList.add('collapsed');
    toggle.classList.add('rotated');
  }
  
  // Add click events to read more buttons
  const readMoreButtons = document.querySelectorAll('.btn-read-more');
  readMoreButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      e.stopPropagation();
      const announcementId = this.closest('.announcement-item').dataset.id;
      openAnnouncement(announcementId);
    });
  });

  // Auto-collapse announcements after 5 seconds (optional)
  setTimeout(() => {
    const content = document.getElementById('announcementsContent');
    const toggle = document.querySelector('.announcement-toggle');
    if (content && !content.classList.contains('collapsed')) {
      content.classList.add('collapsed');
      toggle.classList.add('rotated');
      localStorage.setItem('announcementsCollapsed', 'true');
    }
  }, 5000);
  
  // Initialize announcement count
  updateAnnouncementCount();
}

// Enhanced announcement functions
function markAsRead(button) {
  const announcementItem = button.closest('.announcement-item');
  const announcementId = announcementItem.dataset.id;
  
  announcementItem.classList.remove('unread');
  button.style.display = 'none';
  
  // Save to localStorage
  const readAnnouncements = JSON.parse(localStorage.getItem('readAnnouncements') || '[]');
  if (!readAnnouncements.includes(announcementId)) {
    readAnnouncements.push(announcementId);
    localStorage.setItem('readAnnouncements', JSON.stringify(readAnnouncements));
  }
  
  // Update announcement count
  updateAnnouncementCount();
  
  // Show success message
  showNotification('Announcement marked as read', 'success');
}

function markAllAsRead() {
  const unreadItems = document.querySelectorAll('.announcement-item.unread');
  const readAnnouncements = JSON.parse(localStorage.getItem('readAnnouncements') || '[]');
  
  unreadItems.forEach(item => {
    const announcementId = item.dataset.id;
    item.classList.remove('unread');
    const markButton = item.querySelector('.btn-mark-read');
    if (markButton) {
      markButton.style.display = 'none';
    }
    
    if (!readAnnouncements.includes(announcementId)) {
      readAnnouncements.push(announcementId);
    }
  });
  
  localStorage.setItem('readAnnouncements', JSON.stringify(readAnnouncements));
  updateAnnouncementCount();
  showNotification('All announcements marked as read', 'success');
}

function updateAnnouncementCount() {
  const unreadCount = document.querySelectorAll('.announcement-item.unread').length;
  const countElement = document.querySelector('.announcement-count');
  if (countElement) {
    if (unreadCount > 0) {
      countElement.textContent = `${unreadCount} New`;
      countElement.style.display = 'inline-block';
    } else {
      countElement.style.display = 'none';
    }
  }
}

function openAnnouncement(id) {
  // Navigate to announcements page and possibly filter (optional)
  if (typeof loadContent === 'function') {
    loadContent('admin_page/Announcements');
  } else {
    window.location.href = 'Announcements.php';
  }
}

// Export functions to global scope
window.initDashboardModule = initDashboardModule;
window.toggleAnnouncements = toggleAnnouncements;
window.openAnnouncement = openAnnouncement;
window.viewAllAnnouncements = viewAllAnnouncements;
window.markAsRead = markAsRead;

// Enhanced chart initialization function
function initializeCharts() {
  // Check if Chart.js is available
  if (typeof Chart === 'undefined') {
    console.warn('Chart.js is not loaded');
    return;
  }

  // Initialize dashboard data loader (will update charts with real data)
  if (typeof initDashboardData === 'function') {
    initDashboardData();
    return; // Dashboard data loader will handle chart initialization
  }

  // Fallback to old method if dashboard data loader not available
  // Destroy existing charts to prevent duplicates
  Chart.helpers.each(Chart.instances, (instance) => {
    instance.destroy();
  });

  // Get colors based on current theme
  const isDark = document.body.classList.contains('dark');
  const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
  const textColor = isDark ? '#ffffff' : '#333333';
  const bgColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';

  // Violation Types Pie Chart
  const violationTypesCtx = document.getElementById('violationTypesChart');
  if (violationTypesCtx) {
    new Chart(violationTypesCtx, {
      type: 'pie',
      data: {
        labels: ['Improper Uniform', 'Improper Footwear', 'No ID', 'Late', 'Other'],
        datasets: [{
          data: [35, 25, 20, 15, 5],
          backgroundColor: [
            '#FFD700',
            '#FFCE26',
            '#FD7238',
            '#1bb44eff',
            '#6c757d'
          ],
          borderWidth: 2,
          borderColor: isDark ? '#2d3748' : '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20,
              usePointStyle: true,
              font: {
                size: 12
              },
              color: textColor
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.raw || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((value / total) * 100);
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        }
      }
    });
  }

  // Department Violations Bar Chart
  const departmentViolationsCtx = document.getElementById('departmentViolationsChart');
  if (departmentViolationsCtx) {
    new Chart(departmentViolationsCtx, {
      type: 'bar',
      data: {
        labels: ['BSIS', 'WFT', 'BTVTED', 'CHS', 'BSCS', 'BSN'],
        datasets: [{
          label: 'Violations',
          data: [28, 22, 18, 15, 12, 8],
          backgroundColor: [
            '#FFD700',
            '#FFCE26',
            '#FD7238',
            '#3fbe18ff',
            '#DB504A',
            '#6c757d'
          ],
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: gridColor
            },
            ticks: {
              color: textColor
            },
            background: {
              color: bgColor
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              color: textColor
            }
          }
        }
      }
    });
  }

  // Monthly Trends Line Chart
  const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart');
  if (monthlyTrendsCtx) {
    new Chart(monthlyTrendsCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'Violations',
          data: [12, 19, 15, 25, 22, 30, 28, 35, 32, 28, 24, 20],
          borderColor: '#FFD700',
          backgroundColor: isDark ? 'rgba(255, 215, 0, 0.2)' : 'rgba(255, 215, 0, 0.1)',
          tension: 0.4,
          fill: true,
          borderWidth: 3,
          pointBackgroundColor: '#FFD700',
          pointBorderColor: isDark ? '#2d3748' : '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 6,
          pointHoverRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: gridColor
            },
            ticks: {
              color: textColor
            },
            background: {
              color: bgColor
            }
          },
          x: {
            grid: {
              color: gridColor
            },
            ticks: {
              color: textColor
            }
          }
        }
      }
    });
  }
  
  console.log('📊 Charts initialized with theme:', isDark ? 'dark' : 'light');
}