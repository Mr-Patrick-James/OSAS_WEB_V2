<?php
/**
 * API to get user information including student_id from users table
 */
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/UserModel.php';

@session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_COOKIE['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

$userId = $_SESSION['user_id'] ?? $_COOKIE['user_id'] ?? null;

if (!$userId) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User ID not found'
    ]);
    exit;
}

try {
    $userModel = new UserModel();
    $user = $userModel->getById($userId);
    
    if (!$user) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not found'
        ]);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'user_id' => $user['id'],
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'student_id' => $user['student_id'] ?? null,
            'role' => $user['role'] ?? 'user'
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve user info: ' . $e->getMessage()
    ]);
}

