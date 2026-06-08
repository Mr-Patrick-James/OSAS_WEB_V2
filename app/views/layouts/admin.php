<?php
// Start session and check authentication
require_once __DIR__ . '/../../core/Session.php';
Session::start();

// Check if user is logged in - check cookies first (more reliable)
if (isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    // Restore session from cookies
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $_COOKIE['user_id'];
        $_SESSION['username'] = $_COOKIE['username'] ?? '';
        $_SESSION['role'] = $_COOKIE['role'];
        $_SESSION['full_name'] = $_COOKIE['full_name'] ?? ($_COOKIE['username'] ?? '');
    }
} elseif (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // No session or cookies, redirect to login
    header('Location: ' . View::baseUrl('index.php'));
    exit;
}

// Check if user is admin (required for admin dashboard)
if ($_SESSION['role'] !== 'admin') {
    // If user is not admin, redirect to appropriate dashboard
    if ($_SESSION['role'] === 'user') {
        header('Location: ' . View::baseUrl('includes/user_dashboard.php'));
    } else {
        header('Location: ' . View::baseUrl('index.php'));
    }
    exit;
}

require_once __DIR__ . '/../../core/View.php';
$role = $_SESSION['role'] ?? 'admin';
$notificationCount = 1; // Can be dynamic later
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <title>E-OSAS SYSTEM</title>
  <link rel="stylesheet" href="<?= View::asset('styles/dashboard.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/chatbot.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/docxtemplater/3.40.2/docxtemplater.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pizzip/3.1.4/pizzip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

</head>

<body>
  <?php View::partial('admin_sidebar'); ?>

  <!-- CONTENT -->
  <section id="content">
    <?php View::partial('navbar', ['role' => $role, 'notificationCount' => $notificationCount]); ?>

    <!-- MAIN CONTENT CONTAINER -->
    <div id="main-content">
      <?= $content ?? '' ?>
    </div>
  </section>
  <!-- CONTENT -->

  <script src="<?= View::asset('js/dashboard.js') ?>?v=<?= time() ?>"></script>
  <script src="<?= View::asset('js/utils/notification.js') ?>?v=<?= time() ?>"></script>
  <script src="<?= View::asset('js/utils/offlineDB.js') ?>?v=<?= time() ?>"></script>
  <script src="<?= View::asset('js/dashboardData.js') ?>"></script>
  <script src="<?= View::asset('js/modules/dashboardModule.js') ?>"></script>
  <script src="<?= View::asset('js/utils/theme.js') ?>"></script>
  <script src="<?= View::asset('js/utils/eyeCare.js') ?>"></script>
  <script src="<?= View::asset('js/utils/slipGenerator.js') ?>"></script>
  <script src="<?= View::asset('js/department.js') ?>"></script>
  <script src="<?= View::asset('js/section.js') ?>"></script>
  <script src="<?= View::asset('js/student.js') ?>"></script>
  <script src="<?= View::asset('js/violation.js') ?>"></script>
  <script src="<?= View::asset('js/reports.js') ?>"></script>
  <script src="<?= View::asset('js/announcement.js') ?>"></script>
  <script src="<?= View::asset('js/chatbot.js') ?>"></script>
  <script src="<?= View::asset('js/pwa.js') ?>"></script>
  <?php View::partial('notif_modal'); ?>
  <?php View::partial('logout_modal'); ?>
</body>

</html>


