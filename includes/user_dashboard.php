<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
session_start();

require_once __DIR__ . '/../app/core/View.php';

// Restore session from cookies if session is empty
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    $_SESSION['user_id']          = $_COOKIE['user_id'];
    $_SESSION['username']         = $_COOKIE['username'] ?? '';
    $_SESSION['role']             = $_COOKIE['role'];
    $_SESSION['student_id']       = $_COOKIE['student_id'] ?? null;
    $_SESSION['student_id_code']  = $_COOKIE['student_id_code'] ?? null;
}

// No session — redirect to login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . View::url('index.php'));
    exit;
}

// Redirect based on role
switch ($_SESSION['role']) {
    case 'admin':
        header('Location: ' . View::url('includes/dashboard.php'));
        exit;
    case 'user':
        break;
    default:
        header('Location: ' . View::url('index.php'));
        exit;
}

// --------------------
// STUDENT ID HANDLING
// --------------------
$student_id = $_SESSION['student_id_code']
           ?? $_COOKIE['student_id_code']
           ?? $_SESSION['student_id']
           ?? $_COOKIE['student_id']
           ?? $_GET['student_id']
           ?? null;

if ($student_id && !isset($_SESSION['student_id_code']) && isset($_COOKIE['student_id_code'])) {
    $_SESSION['student_id_code'] = $_COOKIE['student_id_code'];
    if (isset($_COOKIE['student_id'])) $_SESSION['student_id'] = $_COOKIE['student_id'];
}

if (!$student_id && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../app/core/Model.php';
    require_once __DIR__ . '/../app/models/UserModel.php';
    try {
        $userModel = new UserModel();
        $user = $userModel->getById($_SESSION['user_id']);
        if ($user && !empty($user['student_id'])) {
            $student_id = $user['student_id'];
            $_SESSION['student_id_code'] = $student_id;
            $exp = ['expires' => time() + 6*60*60, 'path' => '/', 'samesite' => 'Lax'];
            setcookie('student_id_code', $student_id, $exp);
        }
    } catch (Exception $e) {
        error_log("Error fetching student_id: " . $e->getMessage());
    }
}

if (!$student_id) {
    die("Student ID not found. Please login again.");
}
?>
<!DOCTYPE html>
<html lang="en">
<script>window.STUDENT_ID = <?= json_encode($student_id) ?>;</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <title>E-OSAS SYSTEM</title>
    <link rel="manifest" href="<?= View::url('manifest.json') ?>">
    <meta name="theme-color" content="#D4AF37">
    <link rel="icon" type="image/png" href="<?= View::asset('img/default.png') ?>">
    <link rel="apple-touch-icon" href="<?= View::asset('img/default.png') ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= View::asset('styles/user_dashboard.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= View::asset('styles/user_topnav.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= View::asset('styles/settings.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= View::asset('styles/announcements.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= View::asset('styles/violation.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= View::asset('styles/chatbot.css') ?>?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
</head>
<body data-eosas-push="student">
    <?php
    require_once __DIR__ . '/../app/models/StudentModel.php';
    $studentModel = new StudentModel();
    $studentData  = $studentModel->getByStudentId($student_id);
    $viewData = ['role' => $_SESSION['role'] ?? 'user', 'student' => $studentData];
    View::partial('user_sidebar', $viewData);
    ?>

    <section id="content">
        <?php
        $topnavData = array_merge($viewData, ['notificationCount' => 0]);
        View::partial('user_topnav', $topnavData);
        ?>
        <div id="main-content" data-student-id="<?= htmlspecialchars($student_id) ?>"></div>
    </section>

    <script src="<?= View::asset('js/lib/FileSaver.js') ?>"></script>
    <script src="<?= View::asset('js/lib/jspdf.umd.min.js') ?>"></script>
    <script src="<?= View::asset('js/lib/jspdf.plugin.autotable.min.js') ?>"></script>
    <script src="<?= View::asset('js/lib/docx.js') ?>"></script>
    <script src="<?= View::asset('js/utils/theme.js') ?>"></script>
    <script src="<?= View::asset('js/utils/eyeCare.js') ?>"></script>
    <script src="<?= View::asset('js/initModules.js') ?>"></script>
    <script src="<?= View::asset('js/user_dashboard.js') ?>?v=<?= time() ?>"></script>
    <script src="<?= View::asset('js/userDashboardData.js') ?>?v=<?= time() ?>"></script>
    <script src="<?= View::asset('js/userViolations.js') ?>?v=<?= time() ?>"></script>
    <script src="<?= View::asset('js/userAnnouncements.js') ?>"></script>
    <script src="<?= View::asset('js/chatbot.js') ?>"></script>
    <?php View::partial('logout_modal'); ?>
    <script src="<?= View::asset('js/pwa.js') ?>"></script>
    <script src="<?= View::asset('js/push-notifications.js') ?>?v=<?= time() ?>"></script>
    <script src="<?= View::asset('js/realtimeAlerts.js') ?>?v=<?= time() ?>"></script>

    <div id="DownloadFormatModal" class="download-modal" style="display:none;">
        <div class="download-modal-overlay" onclick="closeDownloadModal()"></div>
        <div class="download-modal-container">
            <div class="download-modal-header">
                <h3>Select Download Format</h3>
                <button class="close-btn" onclick="closeDownloadModal()"><i class='bx bx-x'></i></button>
            </div>
            <div class="download-modal-body">
                <p>Please choose your preferred file format:</p>
                <div class="download-options">
                    <button class="download-option" onclick="confirmDownload('csv')">
                        <i class='bx bxs-file-txt' style="color:#28a745"></i><span>CSV</span><small>Spreadsheet compatible</small>
                    </button>
                    <button class="download-option" onclick="confirmDownload('pdf')">
                        <i class='bx bxs-file-pdf' style="color:#dc3545"></i><span>PDF</span><small>Portable Document Format</small>
                    </button>
                    <button class="download-option" onclick="confirmDownload('docx')">
                        <i class='bx bxs-file-doc' style="color:#007bff"></i><span>DOCX</span><small>Microsoft Word</small>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
