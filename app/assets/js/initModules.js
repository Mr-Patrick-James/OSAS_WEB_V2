// ===== INIT MODULES =====

// ✅ User Dashboard module
function initializeUserDashboard() {
  console.log("📊 User Dashboard Initialized");
  const dashElement = document.querySelector('#dashboard-summary');
  if (dashElement) {
    dashElement.innerHTML = `
      <div style="padding:20px;">
        <h2>Welcome to Your Dashboard</h2>
        <p>This section provides an overview of your current status and announcements.</p>
      </div>`;
  }
}

// ✅ My Violations module
function initViolationsModule() {
  console.log("⚠️ Violations Module Initialized");
  const container = document.querySelector('#violations-list');
  if (container) {
    container.innerHTML = `
      <div style="padding:20px;">
        <h3>Your Violation Records</h3>
        <p>Currently, you have no recorded violations.</p>
      </div>`;
  }
}

// ✅ My Profile module
function initProfileModule() {
  console.log("👤 Profile Module Initialized");
  const profile = document.querySelector('#profile-info');
  if (profile) {
    profile.innerHTML = `
      <div style="padding:20px;">
        <h3>My Profile</h3>
        <p>Name: <strong>Student Name</strong></p>
        <p>Student ID: <strong>123456</strong></p>
        <p>Department: <strong>BSIT</strong></p>
      </div>`;
  }
}

// ✅ Announcements module
function initAnnouncementsModule() {
  console.log("📢 Announcements Module Initialized");
  const announcementArea = document.querySelector('#announcement-list');
  if (announcementArea) {
    announcementArea.innerHTML = `
      <div style="padding:20px;">
        <h3>Latest Announcements</h3>
        <ul>
          <li>No classes on Monday due to holiday.</li>
          <li>Uniform inspection this Wednesday.</li>
        </ul>
      </div>`;
  }
}

console.log("✅ All init modules loaded successfully");
