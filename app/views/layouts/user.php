<?php
// Start session and check authentication
require_once __DIR__ . '/../../core/Session.php';
Session::start();

// Check if user is logged in - check cookies first (more reliable)
if (isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    // Restore session from cookies
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['username'] = $_COOKIE['username'] ?? '';
    $_SESSION['role'] = $_COOKIE['role'];
} elseif (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // No session or cookies, redirect to login
    header('Location: ' . View::baseUrl('index.php'));
    exit;
}

// Check if user is regular user (required for user dashboard)
if ($_SESSION['role'] !== 'user') {
    // If user is admin, redirect to admin dashboard
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . View::baseUrl('includes/dashboard.php'));
    } else {
        header('Location: ' . View::baseUrl('index.php'));
    }
    exit;
}

require_once __DIR__ . '/../../core/View.php';
$role = $_SESSION['role'] ?? 'user';
$notificationCount = 7; // Can be dynamic later
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <title>E-OSAS SYSTEM</title>
  <link rel="stylesheet" href="<?= View::asset('styles/user_dashboard.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/user_topnav.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/announcements.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/settings.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/chatbot.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

</head>

<body>
  <?php View::partial('user_sidebar'); ?>

  <!-- CONTENT -->
  <section id="content">
    <?php View::partial('user_topnav', ['role' => $role, 'notificationCount' => $notificationCount]); ?>

    <!-- MAIN CONTENT CONTAINER -->
    <div id="main-content">
      <?= $content ?? '' ?>
    </div>
  </section>
  <!-- CONTENT -->

  <script src="<?= View::asset('js/utils/theme.js') ?>"></script>
  <script src="<?= View::asset('js/utils/eyeCare.js') ?>"></script>
  <script src="<?= View::asset('js/utils/slipGenerator.js') ?>"></script>
  <script src="<?= View::asset('js/utils/offlineDB.js') ?>"></script>
  <script src="<?= View::asset('js/initModules.js') ?>"></script>
  <script src="<?= View::asset('js/user_dashboard.js') ?>"></script>
  <script src="<?= View::asset('js/userViolations.js') ?>"></script>
  <script src="<?= View::asset('js/userAnnouncements.js') ?>"></script>
  <script src="<?= View::asset('js/chatbot.js') ?>"></script>
  <script src="<?= View::asset('js/pwa.js') ?>"></script>
  <?php View::partial('logout_modal'); ?>
</body>

</html>


