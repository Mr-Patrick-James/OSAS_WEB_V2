<?php
// api/upload_student_image.php - Handle student image uploads

header('Content-Type: application/json');

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../app/assets/img/students/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create upload directory.'
        ]);
        exit;
    }
}

// Check if file was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No file uploaded or upload error occurred.'
    ]);
    exit;
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.'
    ]);
    exit;
}

// Validate file size
if ($file['size'] > $maxSize) {
    echo json_encode([
        'status' => 'error',
        'message' => 'File size exceeds 5MB limit.'
    ]);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'student_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $relativePath = 'app/assets/img/students/' . $filename;

    // Detect project prefix ('' on AWS root, '/OSAS_WEB' on local subfolder)
    $appDirs = ['app', 'api', 'includes', 'assets', 'public'];
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = '';
    if ($scriptName) {
        $parts = explode('/', trim($scriptName, '/'));
        if (!empty($parts[0]) && !in_array($parts[0], $appDirs)) {
            $basePath = '/' . $parts[0];
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Image uploaded successfully.',
        'data' => [
            'path' => $relativePath,
            'url'  => $basePath . '/' . $relativePath
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save uploaded file.'
    ]);
}
?>

