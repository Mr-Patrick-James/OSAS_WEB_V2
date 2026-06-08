<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../core/Logger.php';

class UserController extends Controller {
    private $model;

    public function __construct() {
        ob_start();
        header('Content-Type: application/json');
        @session_start();
        $this->model = new UserModel();
    }

    public function listAdmins() {
        try {
            $this->requireAdmin();
            $admins = $this->model->getAdmins();
            $formatted = array_map(function ($row) {
                return [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'username' => $row['username'] ?? '',
                    'email' => $row['email'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'student_id' => $row['student_id'] ?? '',
                    'role' => $row['role'] ?? 'Admin',
                    'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
                    'created_at' => $row['created_at'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null
                ];
            }, $admins);

            $this->success('Admins retrieved successfully', ['admins' => $formatted]);
        } catch (Exception $e) {
            $this->error('Failed to load admins: ' . $e->getMessage());
        }
    }

    public function listUsers() {
        try {
            $this->requireAdmin();
            $users = $this->model->getUsers();
            $formatted = array_map(function ($row) {
                return [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'username' => $row['username'] ?? '',
                    'email' => $row['email'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'student_id' => $row['student_id'] ?? '',
                    'role' => $row['role'] ?? 'User',
                    'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : true,
                    'created_at' => $row['created_at'] ?? null,
                    'updated_at' => $row['updated_at'] ?? null
                ];
            }, $users);

            $this->success('Users retrieved successfully', ['users' => $formatted]);
        } catch (Exception $e) {
            $this->error('Failed to load users: ' . $e->getMessage());
        }
    }

    public function createAdmin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $this->requireAdmin();

        $username = $this->sanitize($this->getPost('username', ''));
        $email = $this->sanitize($this->getPost('email', ''));
        $fullName = $this->sanitize($this->getPost('full_name', ''));
        $role = $this->sanitize($this->getPost('role', 'admin'));
        $password = $this->getPost('password', '');
        $confirmPassword = $this->getPost('confirm_password', '');
        $studentId = $this->sanitize($this->getPost('student_id', ''));

        if ($username === '' || $email === '' || $fullName === '' || $password === '' || $confirmPassword === '') {
            $this->error('All required fields must be filled.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
        }

        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match.');
        }

        if ($this->model->usernameExists($username)) {
            $this->error('Username already exists.');
        }

        if ($this->model->emailExists($email)) {
            $this->error('Email already exists.');
        }

        try {
            $data = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'full_name' => $fullName,
                'student_id' => $studentId !== '' ? $studentId : null,
                'is_active' => 1
            ];

            $id = $this->model->create($data);
            $admin = $this->model->getById($id);

            if (!$admin) {
                $this->error('Admin account created but could not be loaded.');
            }

            // Log admin creation
            Logger::log('Admin Created', "New admin created: {$username} (Role: {$role})");

            $response = [
                'id' => isset($admin['id']) ? (int)$admin['id'] : 0,
                'username' => $admin['username'] ?? '',
                'email' => $admin['email'] ?? '',
                'full_name' => $admin['full_name'] ?? '',
                'student_id' => $admin['student_id'] ?? '',
                'role' => $admin['role'] ?? 'Admin',
                'is_active' => isset($admin['is_active']) ? (bool)$admin['is_active'] : true,
                'created_at' => $admin['created_at'] ?? null,
                'updated_at' => $admin['updated_at'] ?? null
            ];

            $this->success('Admin account created successfully', ['admin' => $response]);
        } catch (Exception $e) {
            $this->error('Failed to create admin: ' . $e->getMessage());
        }
    }

    public function deleteAdmin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $this->requireAdmin();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->error('Invalid user ID.');
        }

        // Prevent deleting yourself
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            $this->error('You cannot delete your own account.');
        }

        try {
            // Check if user exists
            $user = $this->model->getById($id);
            if (!$user) {
                $this->error('User not found.');
            }

            // Perform archiving (soft delete)
            if ($this->model->archive($id)) {
                // Log admin archiving
                Logger::log('Admin Archived', "Admin account archived: {$user['username']} (ID: {$id})");
                $this->success('User archived successfully.');
            } else {
                $this->error('Failed to archive user.');
            }
        } catch (Exception $e) {
            $this->error('Error deleting user: ' . $e->getMessage());
        }
    }

    public function updateProfile() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        if (!isset($_SESSION['user_id'])) {
            $this->error('User not logged in.');
        }

        $userId = $_SESSION['user_id'];
        
        try {
            $user = $this->model->getById($userId);
            if (!$user) {
                $this->error('User not found.');
            }

            $username = $this->sanitize($this->getPost('username', ''));
            $fullName = $this->sanitize($this->getPost('full_name', ''));
            $currentPassword = $this->getPost('current_password', '');
            $newPassword = $this->getPost('new_password', '');
            $confirmPassword = $this->getPost('confirm_password', '');

            if ($username === '') {
                $this->error('Username is required.');
            }

            if ($fullName === '') {
                $this->error('Full name is required.');
            }

            // Verify current password only if setting a new password or if provided
            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    $this->error('Current password is required to set a new password.');
                }
                if (!password_verify($currentPassword, $user['password'])) {
                    $this->error('Incorrect current password.');
                }
            } elseif (!empty($currentPassword)) {
                // If user provided current password voluntarily, verify it
                if (!password_verify($currentPassword, $user['password'])) {
                    $this->error('Incorrect current password.');
                }
            }

            // Handle Profile Picture Upload
            $profilePicture = isset($user['profile_picture']) ? $user['profile_picture'] : null; // Keep existing by default
            
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
                $fileName = $_FILES['profile_picture']['name'];
                $fileSize = $_FILES['profile_picture']['size'];
                $fileType = $_FILES['profile_picture']['type'];
                
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
                
                if (in_array($fileExtension, $allowedfileExtensions)) {
                    // Check file size (limit to 5MB)
                    if ($fileSize > 5 * 1024 * 1024) {
                        $this->error('File size exceeds 5MB limit.');
                    }
                    
                    // Generate unique filename
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    
                    // Upload directory - Relative to the controller file location
                    // Controller is in app/controllers, we want to go to public/uploads/profile_pictures
                    $uploadDir = __DIR__ . '/../../public/uploads/profile_pictures/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $dest_path = $uploadDir . $newFileName;
                    
                    if(move_uploaded_file($fileTmpPath, $dest_path)) {
                        // Store relative path in database
                        $profilePicture = 'public/uploads/profile_pictures/' . $newFileName;
                        
                        // Delete old profile picture if exists and not default
                        if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../../' . $user['profile_picture'])) {
                            // Don't delete if it's a default image or if we just uploaded it
                            // Only delete if it's a different file
                            if ($user['profile_picture'] !== $profilePicture) {
                                unlink(__DIR__ . '/../../' . $user['profile_picture']);
                            }
                        }
                    } else {
                        $this->error('Error moving uploaded file.');
                    }
                } else {
                    $this->error('Upload failed. Allowed file types: ' . implode(', ', $allowedfileExtensions));
                }
            }

            // Check if username is being changed and if it's already taken
            if ($username !== $user['username']) {
                if ($this->model->usernameExists($username, $userId)) {
                    $this->error('Username already exists.');
                }
            }

            $updateData = [
                'username' => $username,
                'full_name' => $fullName,
                'profile_picture' => $profilePicture,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // If new password is provided
            if ($newPassword !== '') {
                if ($newPassword !== $confirmPassword) {
                    $this->error('New passwords do not match.');
                }
                $updateData['password'] = $newPassword;
            }

            if ($this->model->update($userId, $updateData)) {
             // Update session if username or full name changed
             $_SESSION['username'] = $username;
             $_SESSION['full_name'] = $fullName;
             
             // Update cookies as well
             $expiryTime = time() + (30*24*60*60); // 30 days
             setcookie("username", $username, $expiryTime, "/", "", false, false);
             setcookie("full_name", $fullName, $expiryTime, "/", "", false, false);

             if (isset($profilePicture)) {
                $_SESSION['profile_picture'] = $profilePicture;
            }
            $this->success('Profile updated successfully.', [
                'username' => $username, 
                'full_name' => $fullName,
                'profile_picture' => $profilePicture
            ]);
        } else {
            $this->error('Failed to update profile.');
        }
        } catch (Exception $e) {
            $this->error('Error updating profile: ' . $e->getMessage());
        }
    }

    public function getProfile() {
        if (!isset($_SESSION['user_id'])) {
            $this->error('User not logged in.');
        }

        try {
            $user = $this->model->getById($_SESSION['user_id']);
            if (!$user) {
                $this->error('User not found.');
            }

            $profile = [
                'username' => $user['username'],
                'full_name' => $user['full_name'] ?? '',
                'email' => $user['email'] ?? '',
                'profile_picture' => $user['profile_picture'] ?? ''
            ];

            $this->success('Profile retrieved successfully', ['profile' => $profile]);
        } catch (Exception $e) {
            $this->error('Error retrieving profile: ' . $e->getMessage());
        }
    }

    public function updateStatus() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }
        $this->requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        if ($id <= 0) {
            $this->error('Invalid user ID.');
        }
        try {
            $user = $this->model->getById($id);
            if (!$user) {
                $this->error('User not found.');
            }
            if ($this->model->update($id, ['is_active' => $isActive])) {
                $this->success('User status updated.', ['id' => $id, 'is_active' => $isActive]);
            } else {
                $this->error('Failed to update user status.');
            }
        } catch (Exception $e) {
            $this->error('Error updating user status: ' . $e->getMessage());
        }
    }

    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }
        $this->requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $this->error('Invalid user ID.');
        }
        try {
            $user = $this->model->getById($id);
            if (!$user) {
                $this->error('User not found.');
            }
            if ($this->model->update($id, ['password' => 'password123'])) {
                $this->success('Password reset to default.', ['id' => $id]);
            } else {
                $this->error('Failed to reset password.');
            }
        } catch (Exception $e) {
            $this->error('Error resetting password: ' . $e->getMessage());
        }
    }

    public function deleteUser() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }
        $this->requireAdmin();
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $this->error('Invalid user ID.');
        }
        try {
            $user = $this->model->getById($id);
            if (!$user) {
                $this->error('User not found.');
            }
            if ($this->model->archive($id)) {
                $this->success('User archived successfully.');
            } else {
                $this->error('Failed to archive user.');
            }
        } catch (Exception $e) {
            $this->error('Error deleting user: ' . $e->getMessage());
        }
    }

    public function listArchived() {
        try {
            $this->requireAdmin();
            $archived = $this->model->getArchived();
            $formatted = array_map(function ($row) {
                return [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'username' => $row['username'] ?? '',
                    'email' => $row['email'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'student_id' => $row['student_id'] ?? '',
                    'role' => $row['role'] ?? 'User',
                    'is_active' => isset($row['is_active']) ? (bool)$row['is_active'] : false,
                    'status' => $row['status'] ?? 'archived',
                    'deleted_at' => $row['deleted_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null
                ];
            }, $archived);

            $this->success('Archived users retrieved successfully', ['archived' => $formatted]);
        } catch (Exception $e) {
            $this->error('Failed to load archived users: ' . $e->getMessage());
        }
    }

    public function restoreUser() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $this->requireAdmin();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->error('Invalid user ID.');
        }

        try {
            if ($this->model->restore($id)) {
                $this->success('User restored successfully.');
            } else {
                $this->error('Failed to restore user.');
            }
        } catch (Exception $e) {
            $this->error('Error restoring user: ' . $e->getMessage());
        }
    }

    public function permanentDelete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $this->requireAdmin();

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            $this->error('Invalid user ID.');
        }

        // Prevent deleting yourself
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
            $this->error('You cannot delete your own account.');
        }

        try {
            $user = $this->model->getById($id);
            if (!$user) {
                $this->error('User not found.');
            }

            // Only allow permanent delete of archived accounts
            if (($user['status'] ?? '') !== 'archived') {
                $this->error('Only archived accounts can be permanently deleted.');
            }

            if ($this->model->permanentDelete($id)) {
                Logger::log('User Permanently Deleted', "Account permanently deleted: {$user['username']} (ID: {$id})");
                $this->success('User permanently deleted.');
            } else {
                $this->error('Failed to permanently delete user.');
            }
        } catch (Exception $e) {
            $this->error('Error permanently deleting user: ' . $e->getMessage());
        }
    }
}
