<?php
require_once __DIR__ . '/../core/Model.php';

class StudentModel extends Model {
    protected $table = 'students';
    protected $primaryKey = 'id';

    /**
     * Get all students with filters and search
     */
    public function getAllWithDetails($filter = 'all', $search = '', $page = null, $limit = null, $department = 'all', $sectionId = 'all') {
        // Check if sections and departments tables exist
        $sectionsExist = $this->tableExists('sections');
        $deptExist = $this->tableExists('departments');
        
        // Build query with JOINs
        if ($sectionsExist && $deptExist) {
            $query = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, 
                             s.email, s.contact_number, s.address, s.department, s.section_id, s.yearlevel, s.year_level,
                             s.avatar, s.status, s.created_at, s.updated_at,
                             COALESCE(sec.section_name, 'N/A') as section_name, 
                             COALESCE(sec.section_code, 'N/A') as section_code, 
                             COALESCE(d.department_name, s.department) as department_name
                      FROM students s
                      LEFT JOIN sections sec ON s.section_id = sec.id
                      LEFT JOIN departments d ON s.department = d.department_code
                      WHERE 1=1";
        } elseif ($sectionsExist) {
            $query = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, 
                             s.email, s.contact_number, s.address, s.department, s.section_id, s.yearlevel, s.year_level,
                             s.avatar, s.status, s.created_at, s.updated_at,
                             COALESCE(sec.section_name, 'N/A') as section_name, 
                             COALESCE(sec.section_code, 'N/A') as section_code, 
                             s.department as department_name
                      FROM students s
                      LEFT JOIN sections sec ON s.section_id = sec.id
                      WHERE 1=1";
        } else {
            $query = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, 
                             s.email, s.contact_number, s.address, s.department, s.section_id, s.yearlevel, s.year_level,
                             s.avatar, s.status, s.created_at, s.updated_at,
                             'N/A' as section_name, 
                             'N/A' as section_code, 
                             s.department as department_name
                      FROM students s
                      WHERE 1=1";
        }
        
        $params = [];
        $types = "";
        
        // Add status filter
        if ($filter === 'active') {
            $query .= " AND s.status = 'active'";
        } elseif ($filter === 'inactive') {
            $query .= " AND s.status = 'inactive'";
        } elseif ($filter === 'graduating') {
            $query .= " AND s.status = 'graduating'";
        } elseif ($filter === 'archived') {
            $query .= " AND s.status = 'archived'";
        } else {
            $query .= " AND s.status != 'archived'";
        }

        // Add department filter
        if ($department !== 'all') {
            $query .= " AND s.department = ?";
            $params[] = $department;
            $types .= "s";
        }

        // Add section filter
        if ($sectionId !== 'all') {
            $query .= " AND s.section_id = ?";
            $params[] = (int)$sectionId;
            $types .= "i";
        }
        
        // Add search
        if (!empty($search)) {
            $searchTerm = "%$search%";
            if ($sectionsExist && $deptExist) {
                $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR d.department_name LIKE ? OR sec.section_code LIKE ? OR sec.section_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
                for($i=0; $i<11; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            } elseif ($sectionsExist) {
                $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR sec.section_code LIKE ? OR sec.section_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
                for($i=0; $i<10; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            } else {
                $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
                for($i=0; $i<8; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            }
        }
        
        $query .= " ORDER BY s.created_at DESC";

        if (!is_null($page) && !is_null($limit)) {
            $offset = max(0, ($page - 1) * $limit);
            $query .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            $types .= "ii";
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $students = [];
        while ($row = $result->fetch_assoc()) {
            $firstName = $row['first_name'] ?? '';
            $middleName = $row['middle_name'] ?? '';
            $lastName = $row['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName);
            
            $avatar = $row['avatar'] ?? '';
            if (empty($avatar) || trim($avatar) === '') {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=ffd700&color=333&size=40';
            } else {
                if (!filter_var($avatar, FILTER_VALIDATE_URL) && strpos($avatar, 'data:') !== 0) {
                    // Normalize avatar paths to use app/assets/
                    if (strpos($avatar, 'app/assets/img/students/') === false && strpos($avatar, 'assets/img/students/') === false) {
                        if (strpos($avatar, '../app/assets/img/students/') === 0 || strpos($avatar, '../assets/img/students/') === 0) {
                            // Remove ../ prefix and normalize to app/assets/
                            $avatar = 'app/' . ltrim(substr($avatar, 3), '/');
                            if (strpos($avatar, 'app/assets/') === false) {
                                $avatar = str_replace('assets/', 'app/assets/', $avatar);
                            }
                        } else {
                            // Assume it's just a filename, prepend the path
                            $avatar = 'app/assets/img/students/' . basename($avatar);
                        }
                    } elseif (strpos($avatar, 'assets/img/students/') !== false && strpos($avatar, 'app/assets/') === false) {
                        // Normalize old assets/ paths to app/assets/
                        $avatar = str_replace('assets/', 'app/assets/', $avatar);
                    }
                }
            }
            
            $students[] = [
                'id' => $row['id'] ?? 0,
                'studentId' => $row['student_id'] ?? '',
                'firstName' => $firstName,
                'middleName' => $middleName,
                'lastName' => $lastName,
                'email' => $row['email'] ?? '',
                'contact' => $row['contact_number'] ?: 'N/A',
                'address' => $row['address'] ?: '',
                'department' => $row['department_name'] ?? ($row['department'] ?? 'N/A'),
                'department_code' => $row['department'] ?? '',
                'section' => $row['section_code'] ?? 'N/A',
                'section_id' => $row['section_id'] ?? null,
                'yearlevel' => $row['yearlevel'] ?? 'N/A',
                'status' => $row['status'] ?? 'active',
                'avatar' => $avatar,
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y')
            ];
        }
        
        $stmt->close();
        return $students;
    }

    /**
     * Count active students
     */
    public function countActive() {
        $query = "SELECT COUNT(*) as count FROM students WHERE status != 'archived' OR status IS NULL";
        $result = $this->conn->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            return (int)$row['count'];
        }
        return 0;
    }

    public function getCountWithFilters($filter = 'all', $search = '', $department = 'all', $sectionId = 'all') {
        $sectionsExist = $this->tableExists('sections');
        $deptExist = $this->tableExists('departments');

        if ($sectionsExist && $deptExist) {
            $query = "SELECT COUNT(DISTINCT s.id) as total
                      FROM students s
                      LEFT JOIN sections sec ON s.section_id = sec.id
                      LEFT JOIN departments d ON s.department = d.department_code
                      WHERE 1=1";
        } elseif ($sectionsExist) {
            $query = "SELECT COUNT(DISTINCT s.id) as total
                      FROM students s
                      LEFT JOIN sections sec ON s.section_id = sec.id
                      WHERE 1=1";
        } else {
            $query = "SELECT COUNT(*) as total
                      FROM students s
                      WHERE 1=1";
        }

        $params = [];
        $types = "";

        if ($filter === 'active') {
            $query .= " AND s.status = 'active'";
        } elseif ($filter === 'inactive') {
            $query .= " AND s.status = 'inactive'";
        } elseif ($filter === 'graduating') {
            $query .= " AND s.status = 'graduating'";
        } elseif ($filter === 'archived') {
            $query .= " AND s.status = 'archived'";
        } else {
            $query .= " AND s.status != 'archived'";
        }

        // Add department filter
        if ($department !== 'all') {
            $query .= " AND s.department = ?";
            $params[] = $department;
            $types .= "s";
        }

        // Add section filter
        if ($sectionId !== 'all') {
            $query .= " AND s.section_id = ?";
            $params[] = (int)$sectionId;
            $types .= "i";
        }

        if (!empty($search)) {
            $searchTerm = "%$search%";
            if ($sectionsExist && $deptExist) {
                $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR d.department_name LIKE ? OR sec.section_code LIKE ? OR sec.section_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
                for($i=0; $i<11; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            } elseif ($sectionsExist) {
                $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR sec.section_code LIKE ? OR sec.section_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
                for($i=0; $i<10; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            } else {
                $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.middle_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ? OR s.department LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
                for($i=0; $i<8; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            }
        }

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get statistics
     */
    public function getStats() {
        $stats = [];
        $stats['total'] = $this->query("SELECT COUNT(*) as count FROM students")[0]['count'];
        $stats['active'] = $this->query("SELECT COUNT(*) as count FROM students WHERE status = 'active'")[0]['count'];
        $stats['inactive'] = $this->query("SELECT COUNT(*) as count FROM students WHERE status = 'inactive'")[0]['count'];
        $stats['graduating'] = $this->query("SELECT COUNT(*) as count FROM students WHERE status = 'graduating'")[0]['count'];
        $stats['archived'] = $this->query("SELECT COUNT(*) as count FROM students WHERE status = 'archived'")[0]['count'];
        return $stats;
    }

    /**
     * Get student by student_id (the actual student ID string)
     */
    public function getByStudentId($studentId) {
        // Check if table exists
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return null;
        }

        // Check if sections and departments tables exist
        $sectionsExist = $this->tableExists('sections');
        $deptExist = $this->tableExists('departments');
        
        // Build query with JOINs
        if ($sectionsExist && $deptExist) {
            $query = "SELECT s.*, 
                             COALESCE(sec.section_name, 'N/A') as section_name, 
                             COALESCE(sec.section_code, 'N/A') as section_code, 
                             COALESCE(d.department_name, s.department) as department_name
                      FROM students s
                      LEFT JOIN sections sec ON s.section_id = sec.id
                      LEFT JOIN departments d ON s.department = d.department_code
                      WHERE s.student_id = ? AND s.status != 'archived'";
        } elseif ($sectionsExist) {
            $query = "SELECT s.*, 
                             COALESCE(sec.section_name, 'N/A') as section_name, 
                             COALESCE(sec.section_code, 'N/A') as section_code, 
                             s.department as department_name
                      FROM students s
                      LEFT JOIN sections sec ON s.section_id = sec.id
                      WHERE s.student_id = ? AND s.status != 'archived'";
        } else {
            $query = "SELECT s.*, 
                             'N/A' as section_name, 
                             'N/A' as section_code, 
                             s.department as department_name
                      FROM students s
                      WHERE s.student_id = ? AND s.status != 'archived'";
        }

        try {
            $stmt = $this->conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("s", $studentId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    
                    // Format the data similar to getAllWithDetails
                    $firstName = trim($row['first_name'] ?? '');
                    $middleName = trim($row['middle_name'] ?? '');
                    $lastName = trim($row['last_name'] ?? '');
                    
                    $avatar = $row['avatar'] ?? null;
                    if ($avatar && !empty($avatar) && trim($avatar) !== '') {
                        // If avatar is already a full path (contains app/assets or assets), use it as is
                        if (strpos($avatar, 'app/assets/img/students/') !== false || 
                            strpos($avatar, 'assets/img/students/') !== false) {
                            // Already has full path, use as is
                            if (strpos($avatar, 'app/assets/') === false && strpos($avatar, 'assets/') !== false) {
                                // Normalize to app/assets/
                                $avatar = str_replace('assets/', 'app/assets/', $avatar);
                            }
                        } elseif (!filter_var($avatar, FILTER_VALIDATE_URL) && !str_starts_with($avatar, '/')) {
                            // If it's just a filename, prepend the path
                            $avatar = 'app/assets/img/students/' . basename($avatar);
                        }
                    } else {
                        $avatar = 'app/assets/img/default.png';
                    }
                    
                    return [
                        'id' => $row['id'] ?? 0,
                        'student_id' => $row['student_id'] ?? '',
                        'studentId' => $row['student_id'] ?? '',
                        'first_name' => $firstName,
                        'firstName' => $firstName,
                        'middle_name' => $middleName,
                        'middleName' => $middleName,
                        'last_name' => $lastName,
                        'lastName' => $lastName,
                        'email' => $row['email'] ?? '',
                        'contact_number' => $row['contact_number'] ?? null,
                        'contact' => $row['contact_number'] ?? null,
                        'phone' => $row['contact_number'] ?? null,
                        'address' => $row['address'] ?? '',
                        'department' => $row['department_name'] ?? ($row['department'] ?? 'N/A'),
                        'department_name' => $row['department_name'] ?? ($row['department'] ?? 'N/A'),
                        'section' => $row['section_code'] ?? 'N/A',
                        'section_code' => $row['section_code'] ?? 'N/A',
                        'section_name' => $row['section_name'] ?? 'N/A',
                        'section_id' => $row['section_id'] ?? null,
                        'status' => $row['status'] ?? 'active',
                        'avatar' => $avatar,
                        'date_of_birth' => $row['date_of_birth'] ?? null,
                        'dateOfBirth' => $row['date_of_birth'] ?? null,
                        'dob' => $row['date_of_birth'] ?? null,
                        'gender' => $row['gender'] ?? null,
                        'year_level' => $row['year_level'] ?? null,
                        'yearLevel' => $row['year_level'] ?? null,
                        'year' => $row['year_level'] ?? null,
                        'yearlevel' => $row['yearlevel'] ?? null,
                        'created_at' => $row['created_at'] ?? null,
                        'createdAt' => $row['created_at'] ?? null,
                        'enrollment_date' => $row['created_at'] ?? null,
                        'enrollmentDate' => $row['created_at'] ?? null
                    ];
                }
                $stmt->close();
            }
            return null;
        } catch (Exception $e) {
            error_log("StudentModel::getByStudentId error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if student_id exists
     */
    public function studentIdExists($studentId, $excludeId = null) {
        $query = "SELECT id FROM students WHERE student_id = ?";
        if ($excludeId) {
            $query .= " AND id != ?";
            $result = $this->query($query, [$studentId, $excludeId]);
        } else {
            $result = $this->query($query, [$studentId]);
        }
        return count($result) > 0;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT id FROM students WHERE email = ?";
        if ($excludeId) {
            $query .= " AND id != ?";
            $result = $this->query($query, [$email, $excludeId]);
        } else {
            $result = $this->query($query, [$email]);
        }
        return count($result) > 0;
    }

    /**
     * Archive student
     */
    public function archive($id) {
        return $this->update($id, ['status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Restore student
     */
    public function restore($id) {
        return $this->update($id, ['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Permanent delete student and associated user account
     */
    public function delete($id) {
        try {
            // Get student info before deleting to find associated user
            $student = $this->getById($id);
            if ($student) {
                $studentIdCode = $student['student_id'];
                // Delete associated user first
                if (!empty($studentIdCode)) {
                    $this->conn->query("DELETE FROM users WHERE student_id = '$studentIdCode' OR username = '$studentIdCode'");
                }
            }
            // Delete student record
            return parent::delete($id);
        } catch (Exception $e) {
            error_log("StudentModel::delete error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete all students and their associated user accounts
     */
    public function deleteAll() {
        try {
            $this->conn->begin_transaction();
            
            // 1. Delete all users with role 'user' (assuming all students have role 'user')
            // Or more precisely, delete users that have a student_id linked
            $this->conn->query("DELETE FROM users WHERE student_id IS NOT NULL AND role = 'user'");
            
            // 2. Clear students table
            $this->conn->query("TRUNCATE TABLE students");
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("StudentModel::deleteAll error: " . $e->getMessage());
            throw $e;
        }
    }

    public function importAll($data) {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        
        $studentsData = $data['students'] ?? [];
        if (empty($studentsData)) return ['created' => 0, 'updated' => 0, 'skipped' => 0];

        // 1. Pre-fetch Lookups to avoid N+1 queries
        $deptMap = [];
        $res = $this->conn->query("SELECT id, department_code FROM departments");
        while($row = $res->fetch_assoc()) $deptMap[$row['department_code']] = $row['id'];

        $sectionMap = [];
        $res = $this->conn->query("SELECT id, section_code FROM sections");
        while($row = $res->fetch_assoc()) $sectionMap[$row['section_code']] = $row['id'];

        $existingStudents = [];
        $res = $this->conn->query("SELECT id, student_id, email FROM students");
        while($row = $res->fetch_assoc()) $existingStudents[$row['student_id']] = $row;

        $existingUsers = [];
        $res = $this->conn->query("SELECT id, student_id, email FROM users WHERE role = 'user'");
        while($row = $res->fetch_assoc()) {
            if ($row['student_id']) $existingUsers[$row['student_id']] = $row['id'];
            if ($row['email']) $existingUsers[$row['email']] = $row['id'];
        }

        // 2. Prepare Statements
        $updateStmt = $this->conn->prepare("UPDATE students SET first_name=?, middle_name=?, last_name=?, gender=?, department=?, section_id=?, yearlevel=?, year_level=?, email=?, status='active', updated_at=NOW() WHERE id=?");
        $insertStmt = $this->conn->prepare("INSERT INTO students (student_id, first_name, middle_name, last_name, gender, email, department, section_id, yearlevel, year_level, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        $userInsertStmt = $this->conn->prepare("INSERT INTO users (username, email, password, role, full_name, student_id, is_active, created_at) VALUES (?, ?, ?, 'user', ?, ?, 1, NOW())");
        $userUpdateStmt = $this->conn->prepare("UPDATE users SET is_active=1, role='user' WHERE id=?");

        $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
        $processedInBatch = [];
        $conflicts = [];

        // Enable exception mode so duplicate key errors can be caught per-row
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $this->conn->begin_transaction();
        try {
            foreach ($studentsData as $s) {
                $studentId = trim($s['student_id']);
                if (empty($studentId)) { $skipped++; continue; }

                $sectionId = $sectionMap[$s['section_code']] ?? null;
                
                // Extract Year Level (e.g. BSIS-1A -> 1)
                $yearNum = 0;
                if (preg_match('/(\d)/', $s['section_code'], $m)) {
                    $yearNum = (int)$m[1];
                }
                $suffixes = [1=>'st', 2=>'nd', 3=>'rd'];
                $yearLevelStr = $yearNum ? $yearNum . ($suffixes[$yearNum] ?? 'th') . ' Year' : 'N/A';

                $fullName = trim($s['first_name'] . ' ' . $s['last_name']);
                
                if (isset($existingStudents[$studentId])) {
                    // Student exists in DB — check if same person (update) or different (conflict)
                    $id = $existingStudents[$studentId]['id'];
                    $email = $existingStudents[$studentId]['email'] ?: $this->generateEmail($s['first_name'], $s['last_name']);
                    
                    $updateStmt->bind_param("sssssisssi", 
                        $s['first_name'], $s['middle_name'], $s['last_name'],
                        $s['sex'], $s['department_code'], $sectionId,
                        $yearNum, $yearLevelStr, $email, $id
                    );
                    $updateStmt->execute();
                    $updated++;
                } else {
                    // Check if this student_id was already processed in THIS batch (duplicate in file)
                    if (isset($processedInBatch[$studentId])) {
                        $conflicts[] = [
                            'student_id' => $studentId,
                            'name' => $fullName,
                            'existing_name' => $processedInBatch[$studentId],
                            'section' => $s['section_code'] ?? ''
                        ];
                        $skipped++;
                        continue;
                    }

                    $email = $this->generateEmail($s['first_name'], $s['last_name']);
                    try {
                        $insertStmt->bind_param("sssssssiss", 
                            $studentId, $s['first_name'], $s['middle_name'], $s['last_name'],
                            $s['sex'], $email, $s['department_code'], $sectionId,
                            $yearNum, $yearLevelStr
                        );
                        $insertStmt->execute();
                        $created++;
                        $processedInBatch[$studentId] = $fullName;
                    } catch (Exception $dupEx) {
                        // Duplicate key error — skip and log conflict
                        $conflicts[] = [
                            'student_id' => $studentId,
                            'name' => $fullName,
                            'section' => $s['section_code'] ?? '',
                            'error' => 'Duplicate ID in database'
                        ];
                        $skipped++;
                        continue;
                    }
                }

                // Sync User
                $userId = $existingUsers[$studentId] ?? $existingUsers[$email] ?? null;
                if ($userId) {
                    $userUpdateStmt->bind_param("i", $userId);
                    $userUpdateStmt->execute();
                } else {
                    try {
                        $userInsertStmt->bind_param("sssss", $studentId, $email, $defaultPassword, $fullName, $studentId);
                        $userInsertStmt->execute();
                    } catch (Exception $userEx) {
                        // User already exists — skip silently
                    }
                }
            }
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }

        $updateStmt->close();
        $insertStmt->close();
        $userInsertStmt->close();
        $userUpdateStmt->close();

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'conflicts' => $conflicts];
    }

    private function generateEmail($firstName, $lastName) {
        $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName));
        $cleanLast = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $lastName));
        $baseEmail = $cleanFirst . '.' . $cleanLast . '@colegiodenaujan.edu.ph';
        
        // Check for duplicates
        $email = $baseEmail;
        $counter = 1;
        while ($this->emailExists($email)) {
            $email = $cleanFirst . '.' . $cleanLast . $counter . '@colegiodenaujan.edu.ph';
            $counter++;
        }
        return $email;
    }

    /**
     * Sync user account for a student
     */
    public function syncUser($studentId, $email, $fullName) {
        if (empty($email)) return;
        
        $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
        
        // Check if user exists
        $user = $this->query("SELECT id FROM users WHERE student_id = ? OR email = ?", [$studentId, $email]);
        
        if (!empty($user)) {
            // Update existing user to active and role user
            $this->conn->query("UPDATE users SET is_active = 1, role = 'user' WHERE id = " . $user[0]['id']);
        } else {
            // Create new user
            $sql = "INSERT INTO users (username, email, password, role, full_name, student_id, is_active, created_at) 
                    VALUES (?, ?, ?, 'user', ?, ?, 1, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sssss", $studentId, $email, $defaultPassword, $fullName, $studentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function upsertDepartment($code, $name) {
        if (!$this->tableExists('departments')) return;
        
        $existing = $this->query("SELECT id FROM departments WHERE department_code = ?", [$code]);
        if (!empty($existing)) {
            $sql = "UPDATE departments SET department_name = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("si", $name, $existing[0]['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = "INSERT INTO departments (department_code, department_name) VALUES (?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ss", $code, $name);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function upsertSection($code, $name, $deptCode) {
        if (!$this->tableExists('sections')) return;
        
        // Get department ID from code
        $deptId = 0;
        $deptRes = $this->query("SELECT id FROM departments WHERE department_code = ?", [$deptCode]);
        if (!empty($deptRes)) {
            $deptId = $deptRes[0]['id'];
        }

        $existing = $this->query("SELECT id FROM sections WHERE section_code = ?", [$code]);
        if (!empty($existing)) {
            $sql = "UPDATE sections SET section_name = ?, department_id = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sii", $name, $deptId, $existing[0]['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = "INSERT INTO sections (section_code, section_name, department_id) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssi", $code, $name, $deptId);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function getSectionIdByCode($code) {
        if (!$this->tableExists('sections')) return null;
        $res = $this->query("SELECT id FROM sections WHERE section_code = ?", [$code]);
        return !empty($res) ? $res[0]['id'] : null;
    }

    /**
     * Check if table exists
     */
    private function tableExists($tableName) {
        $result = $this->conn->query("SHOW TABLES LIKE '$tableName'");
        return $result && $result->num_rows > 0;
    }
}
