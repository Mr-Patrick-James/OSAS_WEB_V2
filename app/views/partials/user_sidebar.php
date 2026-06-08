<?php
require_once __DIR__ . '/../../core/View.php';
// Get user profile image or default
$userImage = View::asset('img/default.png');
if (file_exists(__DIR__ . '/../../assets/img/user.jpg')) {
    $userImage = View::asset('img/user.jpg');
}
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Use passed student data if available
if (isset($student) && $student) {
    // Construct full name
    $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
    if (!empty($fullName)) {
        $username = $fullName;
    }
    
    // Set avatar
    if (!empty($student['avatar'])) {
        // If avatar is a URL, use it directly
        if (filter_var($student['avatar'], FILTER_VALIDATE_URL)) {
            $userImage = $student['avatar'];
        } else {
            // Use View::asset to resolve the path
            $userImage = View::asset($student['avatar']);
        }
    }
}
?>
<!-- SIDEBAR -->
<section id="sidebar">
  <!-- Sidebar Header with Logo -->
  <div class="sidebar-header">
    <div class="sidebar-logo-section">
      <img src="<?= View::asset('img/default.png') ?>" alt="Osas Logo" class="sidebar-logo sidebar-toggle-logo" style="cursor: pointer;">
      <span class="sidebar-title">E-Osas</span>
      <i class='bx bx-chevron-left sidebar-close-icon'></i>
    </div>
    <div class="sidebar-search-section">
      <form action="#" class="sidebar-search-form">
        <div class="sidebar-form-input">
          <i class='bx bx-search'></i>
          <input type="search" placeholder="Search..." class="sidebar-search-input">
        </div>
      </form>
    </div>
  </div>

  <!-- Profile Section -->
  <div class="sidebar-profile-section" id="sidebarProfileSection">
    <div class="sidebar-profile-image-wrapper">
      <img src="<?= $userImage ?>" alt="Profile" class="sidebar-profile-image" id="sidebarProfileImage">
      <div class="profile-status-indicator"></div>
    </div>
    <div class="sidebar-profile-info">
      <p class="sidebar-username" id="sidebarUsername"><?= htmlspecialchars($username) ?></p>
      <span class="sidebar-role-badge">Student</span>
    </div>
  </div>

  <ul class="side-menu top">
    <li class="active">
      <a href="#" data-page="user-page/user_dashcontent">
        <i class='bx bxs-dashboard'></i>
        <span class="text">My Dashboard</span>
      </a>
    </li>
    <li>
      <a href="#" data-page="user-page/my_violations">
        <i class='bx bxs-shield-x'></i>
        <span class="text">My Violations</span>
      </a>
    </li>
    <li>
      <a href="#" data-page="user-page/announcements">
        <i class='bx bxs-megaphone'></i>
        <span class="text">Announcements</span>
      </a>
    </li>
    <li>
      <a href="#" class="settings-link">
        <i class='bx bxs-cog'></i>
        <span class="text">Settings</span>
      </a>
    </li>
  </ul>
  <!-- Logout Fixed at Bottom -->
  <div class="sidebar-logout">
    <a href="#" class="logout" onclick="logout()">
      <i class='bx bx-log-out'></i>
      <span class="text">Logout</span>
    </a>
  </div>
</section>
<!-- SIDEBAR -->


