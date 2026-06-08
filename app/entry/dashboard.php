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

// Check if user is admin (required for admin dashboard)
if ($_SESSION['role'] !== 'admin') {
    // If user is not admin, redirect to appropriate dashboard
    if ($_SESSION['role'] === 'user') {
        header('Location: user_dashboard.php');
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
  <link rel="stylesheet" href="../app/assets/styles/dashboard.css">
  <link rel="stylesheet" href="../app/assets/styles/chatbot.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
  <?php
  require_once __DIR__ . '/../core/View.php';
  View::partial('admin_sidebar');
  ?>

  <!-- CONTENT -->
  <section id="content">
    <?php
    $role = $_SESSION['role'] ?? 'admin';
    $notificationCount = 1;
    View::partial('navbar', ['role' => $role, 'notificationCount' => $notificationCount]);
    ?>

    <!-- MAIN CONTENT CONTAINER -->
    <div id="main-content">
      <!-- Content will be loaded here dynamically -->
    </div>
  </section>
  <!-- CONTENT -->

  <script src="../app/assets/js/dashboard.js?v=<?= time() ?>"></script>
  <script src="../app/assets/js/utils/notification.js?v=<?= time() ?>"></script>
  <script src="../app/assets/js/utils/admin_notifications.js?v=<?= time() ?>"></script>
  <script src="../app/assets/js/dashboardData.js"></script>
  <script src="../app/assets/js/modules/dashboardModule.js"></script>
  <script src="../app/assets/js/utils/theme.js"></script>
  <script src="../app/assets/js/utils/eyeCare.js"></script>
  
  <script src="../app/assets/js/department.js"></script>
  <script src="../app/assets/js/section.js"></script>
  <script src="../app/assets/js/student.js"></script>
  <script src="../app/assets/js/violation.js"></script>
  <script src="../app/assets/js/reports.js"></script>
  <script src="../app/assets/js/announcement.js"></script>
  <script src="../app/assets/js/chatbot.js"></script>
</body>

</html>