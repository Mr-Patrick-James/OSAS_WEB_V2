<?php
require_once __DIR__ . '/../../core/View.php';
// Get user profile image from session if available
$userImage = View::asset('img/user.jpg');
if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
    $profilePic = $_SESSION['profile_picture'];
    
    // Check if it's an uploaded file (starts with public/)
    if (strpos($profilePic, 'public/') === 0) {
        $userImage = View::url($profilePic);
        // Ensure we bust cache for uploaded images
        $userImage .= '?t=' . time();
    } else {
        // Assume it's an asset
        $userImage = View::asset($profilePic);
    }
} elseif (isset($role) && $role === 'user') {
    $userImage = View::asset('img/default.png');
}
?>
<!-- NAVBAR -->
<nav class="top-navbar">
  <input type="checkbox" id="switch-mode" hidden>
  <label for="switch-mode" class="switch-mode"></label>
  <input type="checkbox" id="eye-care-toggle" hidden>
  <label for="eye-care-toggle" class="eye-care-toggle" title="Eye Care (Light Mode Only)">
    <i class='bx bx-brightness'></i>
  </label>
  <div class="nav-notifications">
    <button class="notification-btn" id="notifBtn">
      <i class='bx bxs-bell'></i>
      <span class="notification-badge" id="notifBadge"><?= isset($notificationCount) ? $notificationCount : '0' ?></span>
    </button>
    
    <!-- Notification Modal -->
    <div id="notifModal" class="notif-modal">
      <div class="notif-modal-content">
        <div class="notif-modal-header">
          <h3>Notifications</h3>
          <button class="notif-close-btn">&times;</button>
        </div>
        <div class="notif-modal-body" id="notifList">
          <div class="notif-loading">Loading notifications...</div>
        </div>
        <div class="notif-modal-footer">
          <button class="notif-view-all" onclick="loadContent('admin_page/Violations')">View All Violations</button>
        </div>
      </div>
    </div>
  </div>
  <a href="#" class="nav-settings" id="openSettingsModal" title="Settings">
    <i class='bx bxs-cog'></i>
  </a>
  <a href="#" class="profile">
    <img src="<?= $userImage ?>" class="profile-photo">
  </a>
</nav>
<!-- NAVBAR -->


