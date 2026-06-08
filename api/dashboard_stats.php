<?php
/**
 * Dashboard Statistics API
 * Returns all statistics needed for the dashboard
 */

header('Content-Type: application/json');
session_start();

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors to JSON

require_once __DIR__ . '/../app/models/StudentModel.php';
require_once __DIR__ . '/../app/models/DepartmentModel.php';
require_once __DIR__ . '/../app/models/SectionModel.php';
require_once __DIR__ . '/../app/models/ViolationModel.php';

// Get student ID if user is logged in as 'user' role
$userRole = $_SESSION['role'] ?? null;
$studentId = null;
if ($userRole === 'user') {
    // Prefer student_id_code (the actual student ID string) over student_id (database ID)
    $studentId = $_SESSION['student_id_code'] ?? $_SESSION['student_id'] ?? null;
}

try {
    // Instantiate models
    // Note: Each model instantiation might create a database connection depending on Model.php implementation
    $studentModel = new StudentModel();
    $deptModel = new DepartmentModel();
    $sectionModel = new SectionModel();
    $violationModel = new ViolationModel();

    // 1. Get counts
    $studentsCount = $studentModel->countActive();
    
    // For departments, use getCountWithFilters if available, or fallback
    // DepartmentModel has getCountWithFilters($filter, $search)
    $departmentsCount = $deptModel->getCountWithFilters('active');
    
    // SectionModel has countActive
    $sectionsCount = $sectionModel->countActive();
    
    // ViolationModel methods we added
    $violationsCount = $violationModel->countViolations($studentId);
    $violatorsCount = $violationModel->countViolators($studentId);
    $penaltiesCount = $violationModel->countPenalties($studentId);
    
    // 2. Get recent violations
    // We added getRecent to ViolationModel
    $recentViolations = $violationModel->getRecent(10, $studentId);
    
    // 3. Get top violators
    // We added getTopViolators to ViolationModel
    $topViolators = $violationModel->getTopViolators(5, $studentId);
    
    // Format response
    $response = [
        'ok' => true,
        'status' => 'success',
        'data' => [
            'stats' => [
                'students' => $studentsCount,
                'departments' => $departmentsCount,
                'sections' => $sectionsCount,
                'violations' => $violationsCount,
                'violators' => $violatorsCount,
                'penalties' => $penaltiesCount
            ],
            'recentViolations' => $recentViolations,
            'topViolators' => $topViolators
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Dashboard Stats API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'error' => 'Internal Server Error'
    ]);
} catch (Error $e) {
    error_log("Dashboard Stats API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'error' => 'Internal Server Error'
    ]);
}
?>
