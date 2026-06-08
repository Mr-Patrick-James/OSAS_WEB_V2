<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../core/Logger.php';

class AuthController extends Controller {
    private $model;

    public function __construct() {
        ob_start();
        header('Content-Type: application/json');

        if (session_status() === PHP_SESSION_NONE) {
            // Consistent session cookie settings across all pages
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.cookie_path', '/');
            ini_set('session.cookie_httponly', '0'); // JS needs to read cookies
            session_start();
        }

        try {
            $this->model = new UserModel();
        } catch (Exception $e) {
            error_log('AuthController constructor error: ' . $e->getMessage());
            $this->error('System initialization failed. Please try again.');
        }
    }

    public function login() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->error('Invalid request method');
                return;
            }

            $username = trim($this->getPost('username', ''));
            $password = trim($this->getPost('password', ''));
            $remember = isset($_POST['rememberMe']) && $_POST['rememberMe'] === 'true';

            if (empty($username) || empty($password)) {
                $this->error('Please fill in all fields.');
                return;
            }

            error_log("Login attempt for username: " . $username);

            $user = $this->model->authenticate($username, $password);

            if ($user) {
                $studentId     = null;
                $studentIdCode = null;

                if ($user['role'] === 'user' && !empty($user['student_id'])) {
                    $studentIdCode = $user['student_id'];
                    try {
                        require_once __DIR__ . '/../models/StudentModel.php';
                        $studentModel = new StudentModel();
                        $student = $studentModel->query(
                            "SELECT id FROM students WHERE student_id = ? LIMIT 1",
                            [$studentIdCode]
                        );
                        if (!empty($student)) $studentId = $student[0]['id'];
                    } catch (Exception $e) {
                        error_log("Error fetching student database ID: " . $e->getMessage());
                    }
                }

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?: $user['username'];
                $_SESSION['role']      = $user['role'];
                if ($studentIdCode) {
                    $_SESSION['student_id_code'] = $studentIdCode;
                    if ($studentId) $_SESSION['student_id'] = $studentId;
                }

                $expiryTime = time() + ($remember ? 30 * 24 * 60 * 60 : 6 * 60 * 60);

                // Use options-array form for proper SameSite support (PHP 7.3+)
                $cookieOpts = [
                    'expires'  => $expiryTime,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => false,  // false = works on plain HTTP (AWS without SSL)
                    'httponly' => false,  // false = JS can read these for UI updates
                    'samesite' => 'Lax',
                ];

                setcookie('user_id',   (string)$user['id'],                                    $cookieOpts);
                setcookie('username',  $user['username'],                                       $cookieOpts);
                setcookie('role',      $user['role'],                                           $cookieOpts);
                setcookie('full_name', $user['full_name'] ?: ($user['username'] ?: 'Admin'),    $cookieOpts);
                if ($studentIdCode) {
                    setcookie('student_id_code', $studentIdCode, $cookieOpts);
                    if ($studentId) setcookie('student_id', (string)$studentId, $cookieOpts);
                }

                $responseData = [
                    'role'          => $user['role'],
                    'name'          => $user['full_name'] ?: $user['username'],
                    'username'      => $user['username'],
                    'studentId'     => $studentId,
                    'studentIdCode' => $studentIdCode,
                    'expires'       => $expiryTime,
                ];

                error_log("Login successful for username: " . $username . ", role: " . $user['role']);
                // Only log admin activity to system logs
                if ($user['role'] !== 'user') {
                    Logger::log('Login', "User logged in: {$user['username']} (Role: {$user['role']})");
                }
                $this->success('Login successful', $responseData);

            } else {
                error_log("Login failed for username: " . $username);
                $userCheck = $this->model->query(
                    "SELECT is_active FROM users WHERE username = ? OR email = ? LIMIT 1",
                    [$username, $username]
                );
                if (empty($userCheck)) {
                    $this->error("The email or username you entered doesn't exist.");
                } elseif ($userCheck[0]['is_active'] == 0) {
                    $this->error('Your account is currently inactive. Please contact the administrator.');
                } else {
                    $this->error('Invalid password. Please try again.');
                }
            }
        } catch (Exception $e) {
            error_log("Login method exception: " . $e->getMessage());
            $this->error('Login failed. Please try again.');
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_samesite', 'Lax');
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            // Only log admin activity to system logs
            if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user') {
                Logger::log('Logout', "User logged out: {$_SESSION['username']}");
            }
        }
        session_destroy();

        $expired = ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax'];
        foreach (['user_id','username','role','full_name','student_id','student_id_code'] as $c) {
            setcookie($c, '', $expired);
        }
        $this->success('Logged out successfully');
    }

    public function check() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (isset($_SESSION['user_id'])) {
            $this->success('User is authenticated', [
                'user_id'  => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role'     => $_SESSION['role'],
            ]);
        } else {
            $this->error('User is not authenticated', '', 401);
        }
    }
}
