<?php
require_once __DIR__ . '/../../core/View.php';

$username = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
$role     = $_SESSION['role'] ?? 'admin';

// Generate initials from the user's name (first letter of first & last name)
$nameParts = explode(' ', trim($username));
$initials = strtoupper(substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) {
    $initials .= strtoupper(substr(end($nameParts), 0, 1));
}

// Check if user has a profile picture
$hasProfilePic = false;
$userImage = '';
if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $profilePic = $_SESSION['profile_picture'];
    if (strpos($profilePic, 'public/') === 0) {
        $userImage = View::url($profilePic) . '?t=' . time();
    } else {
        $userImage = View::asset($profilePic);
    }
    $hasProfilePic = true;
}
?>
<!-- TOP NAVIGATION -->
<nav class="top-nav">

  <!-- Brand -->
  <div class="nav-brand">
    <img src="<?= View::asset('img/default.png') ?>" alt="E-OSAS" class="nav-logo">
    <span class="nav-title">E-OSAS</span>
  </div>

  <!-- Nav links -->
  <ul class="nav-menu">
    <li class="nav-item active">
      <a href="#" data-page="admin_page/dashcontent" class="nav-link" title="Dashboard">
        <i class='bx bxs-dashboard'></i><span>Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="admin_page/Department" class="nav-link" title="Department">
        <i class='bx bxs-building'></i><span>Department</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="admin_page/Sections" class="nav-link" title="Sections">
        <i class='bx bxs-layer'></i><span>Sections</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="admin_page/Students" class="nav-link" title="Students">
        <i class='bx bxs-group'></i><span>Students</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="admin_page/Violations" class="nav-link" title="Violations">
        <i class='bx bxs-shield-x'></i><span>Violations</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="admin_page/Reports" class="nav-link" title="Reports">
        <i class='bx bxs-file'></i><span>Reports</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="admin_page/Announcements" class="nav-link" title="Announcements">
        <i class='bx bxs-megaphone'></i><span>Announcements</span>
      </a>
    </li>
  </ul>

  <!-- Right controls -->
  <div class="nav-user">

    <!-- Dark-mode pill toggle -->
    <label class="tn-theme-pill" title="Toggle Dark Mode">
      <input type="checkbox" id="switch-mode-top" hidden>
      <span class="tn-pill-track">
        <span class="tn-pill-thumb"></span>
        <i class='bx bx-sun  tn-icon tn-sun'></i>
        <i class='bx bx-moon tn-icon tn-moon'></i>
      </span>
    </label>

    <!-- Notification bell -->
    <div class="nav-notifications">
      <button class="notification-btn" id="notifBtn" aria-label="Notifications">
        <i class='bx bx-bell'></i>
        <span class="notification-badge" id="notifBadge">0</span>
      </button>
      <div id="notifModal" class="notif-modal">
        <div class="notif-modal-content">
          <div class="notif-modal-header">
            <h3><i class='bx bxs-bell-ring'></i> Notifications</h3>
            <button class="notif-close-btn" aria-label="Close">&times;</button>
          </div>
          <div class="notif-modal-body" id="notifList">
            <div class="notif-loading">Loading notifications...</div>
          </div>
          <div class="notif-modal-footer">
            <button class="notif-view-all" onclick="loadContent('admin_page/Violations')">
              View All Violations <i class='bx bx-right-arrow-alt'></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- User profile pill -->
    <div class="tn-user-pill" id="tnUserPill">
      <span class="tn-avatar-ring">
        <?php if ($hasProfilePic): ?>
          <img src="<?= $userImage ?>" alt="Avatar" class="tn-avatar-img"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
          <span class="tn-avatar-initials" style="display:none;"><?= htmlspecialchars($initials) ?></span>
        <?php else: ?>
          <span class="tn-avatar-initials"><?= htmlspecialchars($initials) ?></span>
        <?php endif; ?>
      </span>
      <span class="tn-user-name"><?= htmlspecialchars($username) ?></span>
      <i class='bx bx-chevron-down tn-chevron'></i>
      <div class="tn-user-dropdown" id="tnUserDropdown">
        <div class="tn-dropdown-header">
          <?php if ($hasProfilePic): ?>
            <img src="<?= $userImage ?>" alt="Avatar" class="tn-dropdown-avatar"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span class="tn-dropdown-avatar-initials" style="display:none;"><?= htmlspecialchars($initials) ?></span>
          <?php else: ?>
            <span class="tn-dropdown-avatar-initials"><?= htmlspecialchars($initials) ?></span>
          <?php endif; ?>
          <div class="tn-dropdown-info">
            <span class="tn-dropdown-name"><?= htmlspecialchars($username) ?></span>
            <span class="tn-dropdown-role"><?= ucfirst($role) ?></span>
          </div>
        </div>
        <div class="tn-dropdown-divider"></div>
        <a href="#" class="tn-dropdown-item settings-link">
          <i class='bx bxs-cog'></i> Settings
        </a>
        <div class="tn-dropdown-divider"></div>
        <a href="#" class="tn-dropdown-item tn-logout" onclick="logout()">
          <i class='bx bx-log-out'></i> Sign Out
        </a>
      </div>
    </div>

  </div>
</nav>
<!-- /TOP NAVIGATION -->

<script>
(function () {
  var pill     = document.getElementById('tnUserPill');
  var dropdown = document.getElementById('tnUserDropdown');
  if (!pill || !dropdown) return;
  pill.addEventListener('click', function (e) {
    e.stopPropagation();
    dropdown.classList.toggle('show');
    pill.classList.toggle('open');
  });
  document.addEventListener('click', function () {
    dropdown.classList.remove('show');
    pill.classList.remove('open');
  });
})();
</script>