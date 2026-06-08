<?php
require_once __DIR__ . '/../../core/View.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Use passed student data if available
$hasProfilePic = false;
$userImage = '';
if (isset($student) && $student) {
    // Construct full name
    $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    if (!empty($fullName)) {
        $username = $fullName;
    }
    // Check for avatar (ignore generated ui-avatars URLs and default.png)
    if (!empty($student['avatar'])) {
        $avatar = $student['avatar'];
        $isGenerated = (strpos($avatar, 'ui-avatars.com') !== false);
        $isDefault = (strpos($avatar, 'default.png') !== false);
        if (!$isGenerated && !$isDefault) {
            if (filter_var($avatar, FILTER_VALIDATE_URL)) {
                $userImage = $avatar;
            } else {
                $userImage = View::asset($avatar);
            }
            $hasProfilePic = true;
        }
    }
}

// Also check session profile picture
if (!$hasProfilePic && isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $profilePic = $_SESSION['profile_picture'];
    if (strpos($profilePic, 'public/') === 0) {
        $userImage = View::url($profilePic) . '?t=' . time();
    } else {
        $userImage = View::asset($profilePic);
    }
    $hasProfilePic = true;
}

// Generate initials from the user's name (first letter of first & last name)
$nameParts = explode(' ', trim($username));
$initials = strtoupper(substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) {
    $initials .= strtoupper(substr(end($nameParts), 0, 1));
}
?>
<!-- TOP NAVIGATION -->
<nav class="top-nav">
  <!-- Logo Section -->
  <div class="nav-brand">
    <img src="<?= View::asset('img/default.png') ?>" alt="Osas Logo" class="nav-logo">
    <span class="nav-title" title="Office of Student Affairs and Services">E-Osas</span>
    <span class="nav-title-compact" title="Office of Student Affairs and Services">OSAS</span>
  </div>

  <!-- Desktop Navigation Menu (centre) -->
  <ul class="nav-menu nav-menu--desktop">
    <li class="nav-item active">
      <a href="#" data-page="user-page/user_dashcontent" class="nav-link">
        <i class='bx bxs-dashboard'></i>
        <span>My Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="user-page/my_violations" class="nav-link">
        <i class='bx bxs-shield-x'></i>
        <span>My Violations</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="user-page/announcements" class="nav-link">
        <i class='bx bxs-megaphone'></i>
        <span>Announcements</span>
      </a>
    </li>
  </ul>

  <!-- User Section (right) -->
  <div class="nav-user">
    <!-- Dark Mode Toggle -->
    <div class="nav-theme-toggle">
      <label class="theme-switch">
        <input type="checkbox" id="switch-mode">
        <span class="slider">
          <i class='bx bx-sun sun-icon'></i>
          <i class='bx bx-moon moon-icon'></i>
        </span>
      </label>
    </div>

    <!-- Notifications -->
    <div class="nav-notifications">
      <button class="notification-btn" id="notificationBtn">
        <i class='bx bx-bell'></i>
        <span class="notification-badge">0</span>
      </button>
      
      <!-- Notification Dropdown -->
      <div class="notification-dropdown" id="notificationDropdown">
        <div class="notif-header">
          <h3>Notifications</h3>
          <a href="#" id="markAllRead">Mark all as read</a>
        </div>
        <p style="padding:8px 12px;margin:0;font-size:12px;border-bottom:1px solid rgba(0,0,0,.08);">
          <a href="#" id="enablePhoneAlerts" style="color:#D4AF37;font-weight:600;">Enable alerts (in app)</a>
        </p>
        <div class="notif-list" id="notificationList">
          <div class="no-notifications">
            <i class='bx bx-bell-off'></i>
            <p>No new notifications</p>
          </div>
        </div>
        <div class="notif-footer">
          <a href="#" class="view-all" data-page="user-page/my_violations">View all violations</a>
        </div>
      </div>
    </div>

    <!-- User Menu -->
    <div class="nav-user-menu">
      <div class="user-avatar">
        <span class="user-avatar-ring">
          <?php if ($hasProfilePic): ?>
            <img src="<?= $userImage ?>" alt="User Avatar" class="user-avatar-img"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span class="user-avatar-initials" style="display:none;"><?= htmlspecialchars($initials) ?></span>
          <?php else: ?>
            <span class="user-avatar-initials"><?= htmlspecialchars($initials) ?></span>
          <?php endif; ?>
        </span>
        <span class="user-name"><?= htmlspecialchars($username) ?></span>
        <i class='bx bx-chevron-down'></i>
      </div>
      
      <!-- Dropdown Menu -->
      <div class="user-dropdown">
        <a href="#" class="dropdown-item settings-item settings-link">
          <i class='bx bx-cog'></i>
          <span>Settings</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item logout" onclick="logout()">
          <i class='bx bx-log-out'></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- Mobile Bottom Tab Bar -->
<nav class="mobile-bottom-nav">
  <ul class="nav-menu nav-menu--mobile">
    <li class="nav-item active">
      <a href="#" data-page="user-page/user_dashcontent" class="nav-link">
        <i class='bx bxs-dashboard'></i>
        <span>Dashboard</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="user-page/my_violations" class="nav-link">
        <i class='bx bxs-shield-x'></i>
        <span>Violations</span>
      </a>
    </li>
    <li class="nav-item">
      <a href="#" data-page="user-page/announcements" class="nav-link">
        <i class='bx bxs-megaphone'></i>
        <span>Announcements</span>
      </a>
    </li>
  </ul>
</nav>
<!-- TOP NAVIGATION -->
