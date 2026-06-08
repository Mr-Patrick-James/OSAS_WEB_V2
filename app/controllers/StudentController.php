<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/StudentModel.php';

class StudentController extends Controller {
    private $model;

    public function __construct() {
        ob_start();
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        header('Content-Type: application/json');
        @session_start();
        
        $this->model = new StudentModel();
    }

    public function index() {
        // Check if student_id parameter is provided for single student lookup
        $studentId = trim($this->getGet('student_id', ''));
        
        if (!empty($studentId)) {
            // Get single student by student_id
            try {
                error_log("StudentController::index - Looking for student_id: " . $studentId);
                $student = $this->model->getByStudentId($studentId);
                if ($student) {
                    error_log("StudentController::index - Student found: " . json_encode($student));
                    $this->success('Student retrieved successfully', $student);
                } else {
                    error_log("StudentController::index - Student not found for student_id: " . $studentId);
                    // Try to see if student exists but is archived
                    $allStudents = $this->model->query("SELECT student_id, status FROM students WHERE student_id = ?", [$studentId]);
                    if (!empty($allStudents)) {
                        error_log("StudentController::index - Student exists but status is: " . $allStudents[0]['status']);
                        $this->error('Student not found or is archived');
                    } else {
                        $this->error('Student not found');
                    }
                }
            } catch (Exception $e) {
                error_log("StudentController::index - Exception: " . $e->getMessage());
                error_log("StudentController::index - Stack trace: " . $e->getTraceAsString());
                $this->error('Failed to retrieve student: ' . $e->getMessage());
            }
            return;
        }
        
        // Otherwise, get all students (paginated)
        $filter = $this->getGet('filter', 'all');
        $search = $this->getGet('search', '');
        $department = $this->getGet('department', 'all');
        $section = $this->getGet('section', 'all');
        $page = $this->getGet('page', '1');
        $limit = $this->getGet('limit', '10');
        
        // Handle 'all' limit for exports
        $pageParam = ($limit === 'all') ? null : intval($page);
        $limitParam = ($limit === 'all') ? null : intval($limit);

        try {
            $students = $this->model->getAllWithDetails($filter, $search, $pageParam, $limitParam, $department, $section);
            $totalCount = $this->model->getCountWithFilters($filter, $search, $department, $section);
            $this->success('Students retrieved successfully', [
                'students' => $students,
                'total' => $totalCount,
                'page' => $pageParam,
                'limit' => $limitParam,
                'total_pages' => $limitParam ? ceil($totalCount / max(1, $limitParam)) : 1
            ]);
        } catch (Exception $e) {
            $this->error('Failed to retrieve students: ' . $e->getMessage());
        }
    }

    public function stats() {
        try {
            $stats = $this->model->getStats();
            $this->success('Statistics retrieved successfully', $stats);
        } catch (Exception $e) {
            $this->error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $studentId = $this->sanitize($this->getPost('studentIdCode', $this->getPost('studentId', '')));
        $firstName = $this->sanitize($this->getPost('firstName', ''));
        $middleName = $this->sanitize($this->getPost('middleName', ''));
        $lastName = $this->sanitize($this->getPost('lastName', ''));
        $email = $this->sanitize($this->getPost('studentEmail', ''));
        $contact = $this->sanitize($this->getPost('studentContact', ''));
        $address = $this->sanitize($this->getPost('studentAddress', ''));
        $department = $this->sanitize($this->getPost('studentDept', ''));
        $sectionId = intval($this->getPost('studentSection', 0));
        $yearlevel = $this->sanitize($this->getPost('studentYearlevel', ''));
        $status = $this->sanitize($this->getPost('studentStatus', 'active'));
        $avatar = $this->sanitize($this->getPost('studentAvatar', ''));

        // Handle Image Upload if present
        if (isset($_FILES['studentImage']) && $_FILES['studentImage']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['studentImage'];
            $uploadDir = __DIR__ . '/../assets/img/students/';
            
            // Create directory if not exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $avatar = 'app/assets/img/students/' . $filename;
            }
        }

        if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email)) {
            $this->error('Student ID, First Name, Last Name, and Email are required.');
        }

        try {
            $data = [
                'student_id' => $studentId,
                'first_name' => $firstName,
                'middle_name' => $middleName ?: null,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => $contact ?: null,
                'address' => $address ?: null,
                'department' => $department ?: null,
                'section_id' => $sectionId ?: null,
                'yearlevel' => $yearlevel ?: null,
                'year_level' => $yearlevel ?: '1st Year', // Include underscore version for DB compatibility
                'avatar' => $avatar ?: null,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->model->create($data);
            
            // Sync user account for the student
            if ($id) {
                $fullName = $firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName;
                $this->model->syncUser($studentId, $email, $fullName);
            }
            
            $this->success('Student added successfully!', ['id' => $id]);
        } catch (Exception $e) {
            $this->error('Failed to add student: ' . $e->getMessage());
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $id = intval($this->getPost('id', $this->getPost('studentId', $this->getGet('id', 0))));
        if ($id === 0) {
            $this->error('Invalid student ID');
        }

        $studentId = $this->sanitize($this->getPost('studentIdCode', ''));
        $firstName = $this->sanitize($this->getPost('firstName', ''));
        $middleName = $this->sanitize($this->getPost('middleName', ''));
        $lastName = $this->sanitize($this->getPost('lastName', ''));
        $email = $this->sanitize($this->getPost('studentEmail', ''));
        $contact = $this->sanitize($this->getPost('studentContact', ''));
        $address = $this->sanitize($this->getPost('studentAddress', ''));
        $department = $this->sanitize($this->getPost('studentDept', ''));
        $sectionId = intval($this->getPost('studentSection', 0));
        $yearlevel = $this->sanitize($this->getPost('studentYearlevel', ''));
        $status = $this->sanitize($this->getPost('studentStatus', 'active'));
        $avatar = $this->sanitize($this->getPost('studentAvatar', ''));

        // Handle Image Upload if present
        if (isset($_FILES['studentImage']) && $_FILES['studentImage']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['studentImage'];
            $uploadDir = __DIR__ . '/../assets/img/students/';
            
            // Create directory if not exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $avatar = 'app/assets/img/students/' . $filename;
            }
        }

        if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email)) {
            $this->error('Student ID, First Name, Last Name, and Email are required.');
        }

        try {
            $data = [
                'student_id' => $studentId,
                'first_name' => $firstName,
                'middle_name' => $middleName ?: null,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => $contact ?: null,
                'address' => $address ?: null,
                'department' => $department ?: null,
                'section_id' => $sectionId ?: null,
                'yearlevel' => $yearlevel ?: null,
                'year_level' => $yearlevel ?: '1st Year', // Include underscore version for DB compatibility
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($avatar !== '') {
                $data['avatar'] = $avatar;
            }

            $this->model->update($id, $data);
            
            // Sync user account for the student
            $fullName = $firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName;
            $this->model->syncUser($studentId, $email, $fullName);
            
            $this->success('Student updated successfully!');
        } catch (Exception $e) {
            $this->error('Failed to update student: ' . $e->getMessage());
        }
    }

    public function delete() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid student ID');
        }

        try {
            // Check if student is already archived
            $student = $this->model->getById($id);
            if ($student && $student['status'] === 'archived') {
                // If archived, perform permanent delete
                $this->model->delete($id);
                $this->success('Student permanently deleted!');
            } else {
                // Otherwise just archive it
                $this->model->archive($id);
                $this->success('Student archived successfully!');
            }
        } catch (Exception $e) {
            $this->error('Operation failed: ' . $e->getMessage());
        }
    }

    public function restore() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid student ID');
        }

        try {
            $this->model->restore($id);
            $this->success('Student restored successfully!');
        } catch (Exception $e) {
            $this->error('Failed to restore student: ' . $e->getMessage());
        }
    }

    /**
     * Delete all students and their associated user accounts
     */
    public function deleteAll() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        try {
            $this->model->deleteAll();
            $this->success('All students and associated user accounts deleted successfully!');
        } catch (Exception $e) {
            $this->error('Failed to delete all students: ' . $e->getMessage());
        }
    }

    /**
     * Import students from CSV or Excel file
     */
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        // Check for post_max_size exceeded
        if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $this->error('The file you are trying to upload is too large. Please check your server limits.');
        }

        if (!isset($_FILES['enrollmentList'])) {
            $this->error('No file was received. Please try again.');
        }

        $fileError = $_FILES['enrollmentList']['error'];
        if ($fileError !== UPLOAD_ERR_OK) {
            switch ($fileError) {
                case UPLOAD_ERR_INI_SIZE:
                    $this->error('The uploaded file exceeds the upload_max_filesize directive in php.ini.');
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->error('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->error('The uploaded file was only partially uploaded.');
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->error('No file was uploaded. Please select a file first.');
                    break;
                default:
                    $this->error('An unknown error occurred during upload. Error code: ' . $fileError);
                    break;
            }
        }

        $file = $_FILES['enrollmentList'];
        $uploadDir = __DIR__ . '/../../uploads/imports/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'import_' . time() . '_' . uniqid() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->error('Failed to save the uploaded file.');
        }

        try {
            // Temporarily enable verbose error reporting for debugging
            error_reporting(E_ALL);
            ini_set('display_errors', 1);

            $jsonData = null;

            if ($ext === 'csv') {
                // Parse CSV
                $jsonData = $this->parseCSV($filepath);
            } elseif ($ext === 'xlsx' || $ext === 'xls') {
                // Parse Excel using Python
                $jsonData = $this->parseExcelWithPython($filepath);
            } else {
                unlink($filepath);
                $this->error('Unsupported file format. Please upload a CSV or Excel file.');
            }

            if (!$jsonData || !isset($jsonData['students'])) {
                unlink($filepath);
                $this->error('Invalid import data format or failed to parse file.');
            }

            $results = $this->model->importAll($jsonData);
            
            // Cleanup
            unlink($filepath);
            $jsonFile = __DIR__ . '/../../../scripts/students_data_' . basename($filename, '.' . $ext) . '.json';
            if (file_exists($jsonFile)) unlink($jsonFile);

            $this->success('Import completed successfully!', $results);
        } catch (Exception $e) {
            if (file_exists($filepath)) unlink($filepath);
            $this->error('Import failed: ' . $e->getMessage());
        } finally {
            // Restore original error reporting settings
            ini_set('display_errors', 0);
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
        }
    }

    private function parseCSV($filepath) {
        $handle = fopen($filepath, 'r');
        if (!$handle) return null;

        $data = [
            "departments" => [],
            "sections" => [],
            "students" => []
        ];

        // Map column names to indexes (assume first row is header)
        $headers = fgetcsv($handle);
        $headerMap = array_flip($headers);

        // Required columns: student_id, first_name, last_name, section_code, department_code
        // We'll also try to be flexible with column names
        $idIdx = $headerMap['student_id'] ?? $headerMap['Student ID'] ?? $headerMap['ID'] ?? 0;
        $fnIdx = $headerMap['first_name'] ?? $headerMap['First Name'] ?? 1;
        $lnIdx = $headerMap['last_name'] ?? $headerMap['Last Name'] ?? 2;
        $mnIdx = $headerMap['middle_name'] ?? $headerMap['Middle Name'] ?? 3;
        $secIdx = $headerMap['section_code'] ?? $headerMap['Section'] ?? 4;
        $deptIdx = $headerMap['department_code'] ?? $headerMap['Department'] ?? 5;
        $sexIdx = $headerMap['gender'] ?? $headerMap['sex'] ?? $headerMap['Sex'] ?? $headerMap['Gender'] ?? 6;

        while (($row = fgetcsv($handle)) !== FALSE) {
            if (empty($row[$idIdx])) continue;

            $deptCode = $row[$deptIdx] ?? 'GEN';
            if (!isset($data['departments'][$deptCode])) {
                $data['departments'][$deptCode] = [
                    'code' => $deptCode,
                    'name' => $deptCode // Use code as name for now
                ];
            }

            $secCode = $row[$secIdx] ?? 'GEN-1';
            $data['sections'][] = [
                'code' => $secCode,
                'name' => $secCode,
                'department_code' => $deptCode
            ];

            $data['students'][] = [
                'student_id' => $row[$idIdx],
                'first_name' => $row[$fnIdx],
                'middle_name' => $row[$mnIdx] ?? '',
                'last_name' => $row[$lnIdx],
                'sex' => $row[$sexIdx] ?? '',
                'section_code' => $secCode,
                'department_code' => $deptCode
            ];
        }
        fclose($handle);
        return $data;
    }

    private function parseExcelWithPython($filepath) {
        $scriptPath = __DIR__ . '/../../scripts/parse_students_full.py';
        $outputPath = __DIR__ . '/../../scripts/students_data_' . uniqid() . '.json';
        
        if (!file_exists($scriptPath)) {
            throw new Exception('Excel parser script not found at: ' . $scriptPath);
        }

        // Use the absolute path to the Python executable as detected on this system
        $pythonPath = 'C:\Users\Amiepc\AppData\Local\Programs\Python\Python310\python.exe';
        
        // Fallback to 'python' if the absolute path doesn't exist for some reason
        if (!file_exists($pythonPath)) {
            $pythonPath = 'python';
        }

        $command = "\"$pythonPath\" \"$scriptPath\" \"$filepath\" \"$outputPath\" 2>&1";
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            $errorMsg = implode("\n", $output);
            error_log("Python script error: " . $errorMsg);
            throw new Exception("Excel parser error: " . $errorMsg);
        }

        if (!file_exists($outputPath)) {
            throw new Exception('Excel parser did not produce any output. Check if the file is a valid Excel and not empty.');
        }

        $jsonData = json_decode(file_get_contents($outputPath), true);
        unlink($outputPath); // Delete temp JSON
        return $jsonData;
    }
}
