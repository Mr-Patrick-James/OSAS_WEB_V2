<?php
// Start session and check authentication
session_start();

// Check if user is logged in - check cookies first (more reliable)
if (isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    // Restore session from cookies
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['username'] = $_COOKIE['username'] ?? '';
    $_SESSION['role'] = $_COOKIE['role'];
} elseif (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // No session or cookies, redirect to login
    header('Location: ../index.php');
    exit;
}

// Check if user is regular user (required for user dashboard)
if ($_SESSION['role'] !== 'user') {
    // If user is admin, redirect to admin dashboard
    if ($_SESSION['role'] === 'admin') {
        header('Location: dashboard.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <title>E-OSAS SYSTEM</title>
  <link rel="stylesheet" href="../app/assets/styles/user_dashboard.css">
  <link rel="stylesheet" href="../app/assets/styles/user_topnav.css">
  <link rel="stylesheet" href="../app/assets/styles/settings.css">
  <link rel="stylesheet" href="../app/assets/styles/chatbot.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
  <?php
  require_once __DIR__ . '/../core/View.php';
  View::partial('user_sidebar');
  ?>

  <!-- CONTENT -->
  <section id="content">
    <?php
    $role = $_SESSION['role'] ?? 'user';
    $notificationCount = 7;
    View::partial('user_topnav', ['role' => $role, 'notificationCount' => $notificationCount]);
    ?>

    <!-- MAIN CONTENT CONTAINER -->
    <div id="main-content">
      <!-- Content will be loaded here dynamically -->
    </div>
  </section>
  <!-- CONTENT -->
  <script src="../app/assets/js/utils/theme.js"></script>
  <script src="../app/assets/js/utils/eyeCare.js"></script>
  <script src="../app/assets/js/initModules.js"></script>
  <script src="../app/assets/js/user_dashboard.js"></script>
  <script src="../app/assets/js/userDashboardData.js"></script>
  <script src="../app/assets/js/userViolations.js"></script>
  <script src="../app/assets/js/userAnnouncements.js"></script>
  <script src="../app/assets/js/chatbot.js"></script>
</body>

</html>
