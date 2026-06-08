<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/../app/core/View.php';

// Restore session from cookies if session is empty
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['username'] = $_COOKIE['username'] ?? '';
    $_SESSION['role']    = $_COOKIE['role'];
}

// No session — redirect to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . View::url('index.php'));
    exit;
}

// Wrong role — only admin and staff roles can access admin dashboard
if (!in_array($_SESSION['role'] ?? '', ['admin', 'OSAS Staff', 'CSC Officer', 'Officer', 'Faculty Member'])) {
    $dest = $_SESSION['role'] === 'user' ? 'includes/user_dashboard.php' : 'index.php';
    header('Location: ' . View::url($dest));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <title>E-OSAS SYSTEM</title>
  <link rel="manifest" href="<?= View::url('manifest.json') ?>">
  <meta name="theme-color" content="#D4AF37">
  <link rel="icon" type="image/png" href="<?= View::asset('img/default.png') ?>">
  <link rel="apple-touch-icon" href="<?= View::asset('img/default.png') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/dashboard.css') ?>?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/topnav.css') ?>?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/content-layout.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/settings.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/department.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/section.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/students.css') ?>">
  <link rel="stylesheet" href="<?= View::asset('styles/chatbot.css') ?>?v=<?= time() ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>
  <?php View::partial('admin_topnav'); ?>

  <section id="content">
    <div id="main-content"></div>
  </section>

  <script src="<?= View::asset('js/dashboard.js') ?>?v=<?= time() ?>"></script>
  <script src="<?= View::asset('js/lib/jspdf.umd.min.js') ?>"></script>
  <script src="<?= View::asset('js/lib/jspdf.plugin.autotable.min.js') ?>"></script>
  <script src="<?= View::asset('js/lib/docx.js') ?>"></script>
  <script src="<?= View::asset('js/lib/FileSaver.js') ?>"></script>
  <script src="<?= View::asset('js/utils/notification.js') ?>?v=<?= time() ?>"></script>
  <script src="<?= View::asset('js/utils/admin_notifications.js') ?>?v=<?= time() ?>"></script>
  <script src="<?= View::asset('js/utils/offlineDB.js') ?>"></script>
  <script src="<?= View::asset('js/dashboardData.js') ?>"></script>
  <script src="<?= View::asset('js/modules/dashboardModule.js') ?>"></script>
  <script src="<?= View::asset('js/utils/theme.js') ?>"></script>
  <script src="<?= View::asset('js/utils/eyeCare.js') ?>"></script>
  <script src="<?= View::asset('js/department.js') ?>"></script>
  <script src="<?= View::asset('js/section.js') ?>"></script>
  <script src="<?= View::asset('js/student.js') ?>"></script>
  <script src="<?= View::asset('js/violation.js') ?>"></script>
  <script src="<?= View::asset('js/reports.js') ?>"></script>
  <script src="<?= View::asset('js/announcement.js') ?>"></script>
  <script src="<?= View::asset('js/chatbot.js') ?>"></script>
  <?php View::partial('logout_modal'); ?>
  <script src="<?= View::asset('js/pwa.js') ?>"></script>
  <script src="<?= View::asset('js/push-notifications.js') ?>?v=<?= time() ?>"></script>
</body>
</html>
