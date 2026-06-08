<?php
require_once __DIR__ . '/../core/Model.php';

class ReportModel extends Model {
    protected $table = 'reports';
    protected $primaryKey = 'id';
    
    /**
     * Create reports tables if they don't exist
     */
    private function createReportsTables() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS `reports` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `report_id` VARCHAR(50) NOT NULL,
              `student_id` VARCHAR(50) NOT NULL,
              `student_name` VARCHAR(255) NOT NULL,
              `student_contact` VARCHAR(100) NULL,
              `department` VARCHAR(255) NULL,
              `department_code` VARCHAR(50) NULL,
              `section` VARCHAR(100) NULL,
              `section_id` VARCHAR(50) NULL,
              `yearlevel` VARCHAR(20) NULL,
              `uniform_count` INT NOT NULL DEFAULT 0,
              `footwear_count` INT NOT NULL DEFAULT 0,
              `no_id_count` INT NOT NULL DEFAULT 0,
              `total_violations` INT NOT NULL DEFAULT 0,
              `status` VARCHAR(50) NOT NULL DEFAULT 'permitted',
              `last_violation_date` DATE NULL,
              `report_period_start` DATE NULL,
              `report_period_end` DATE NULL,
              `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_reports_report_id` (`report_id`),
              KEY `idx_reports_student_id` (`student_id`),
              KEY `idx_reports_status` (`status`),
              KEY `idx_reports_total_violations` (`total_violations`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            
            "CREATE TABLE IF NOT EXISTS `report_violations` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `report_id` INT NOT NULL,
              `violation_id` INT NOT NULL,
              `violation_type` VARCHAR(100) NOT NULL,
              `violation_level` VARCHAR(100) NULL,
              `violation_date` DATE NULL,
              `violation_time` TIME NULL,
              `status` VARCHAR(50) NULL,
              `notes` TEXT NULL,
              `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_report_violation` (`report_id`, `violation_id`),
              KEY `idx_report_violations_report_id` (`report_id`),
              KEY `idx_report_violations_violation_id` (`violation_id`),
              CONSTRAINT `fk_report_violations_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            
            "CREATE TABLE IF NOT EXISTS `report_recommendations` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `report_id` INT NOT NULL,
              `recommendation` TEXT NOT NULL,
              `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
              `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_report_recommendations_report_id` (`report_id`),
              KEY `idx_report_recommendations_priority` (`priority`),
              CONSTRAINT `fk_report_recommendations_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        ];
        
        foreach ($queries as $sql) {
            if (!$this->conn->query($sql)) {
                error_log("Failed to create table: " . $this->conn->error);
                throw new Exception("Failed to create reports tables: " . $this->conn->error);
            }
        }
    }

    /**
     * Generate or update reports from violations
     * This should be called periodically or when violations are added/updated
     */
    public function generateReportsFromViolations($startDate = null, $endDate = null, $filters = []) {
        // Check if reports table exists, if not create it
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE 'reports'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            $this->createReportsTables();
        }
        
        // Check if violations table exists
        $violationCheck = @$this->conn->query("SHOW TABLES LIKE 'violations'");
        if ($violationCheck === false || $violationCheck->num_rows === 0) {
            // Check if we can create it or just return 0
            // Assuming violations table must exist for this to work
            // But if it doesn't, we can't generate anything
            return ['generated' => 0, 'updated' => 0, 'total' => 0];
        }
        
        // Build query to aggregate violations by student
        $query = "SELECT 
                    s.id as student_db_id,
                    s.student_id,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.contact_number,
                    s.avatar,
                    s.department as student_dept_code,
                    s.section_id,
                    s.yearlevel,
                    COALESCE(d.department_name, s.department) as department_name,
                    COALESCE(sec.section_code, 'N/A') as section_code,
                    COALESCE(sec.section_name, 'N/A') as section_name,
                    COUNT(CASE WHEN vt.name LIKE '%Uniform%' THEN 1 END) as uniform_count,
                    COUNT(CASE WHEN vt.name LIKE '%Footwear%' OR vt.name LIKE '%Shoe%' THEN 1 END) as footwear_count,
                    COUNT(CASE WHEN vt.name LIKE '%ID%' THEN 1 END) as no_id_count,
                    COUNT(v.id) as total_violations,
                    MAX(CASE 
                        WHEN v.status = 'disciplinary' THEN 3
                        WHEN v.status = 'warning' THEN 2
                        WHEN v.status = 'permitted' THEN 1
                        ELSE 0
                    END) as max_status_level,
                    MAX(v.violation_date) as last_violation_date,
                    MIN(v.violation_date) as first_violation_date
                  FROM students s
                  INNER JOIN violations v ON BINARY v.student_id = BINARY s.student_id
                  LEFT JOIN departments d ON s.department = d.department_code
                  LEFT JOIN sections sec ON s.section_id = sec.id
                  LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                  WHERE s.status != 'archived'";
        
        $params = [];
        $types = "";
        
        // Apply date filters if provided
        if ($startDate && $endDate) {
            $query .= " AND v.violation_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        } elseif ($startDate) {
            $query .= " AND v.violation_date >= ?";
            $params[] = $startDate;
            $types .= "s";
        } elseif ($endDate) {
            $query .= " AND v.violation_date <= ?";
            $params[] = $endDate;
            $types .= "s";
        }

        // Apply department filters
        if (!empty($filters['departments'])) {
            $deptPlaceholders = implode(',', array_fill(0, count($filters['departments']), '?'));
            $query .= " AND s.department IN ($deptPlaceholders)";
            foreach ($filters['departments'] as $dept) {
                $params[] = $dept;
                $types .= "s";
            }
        }

        // Apply violation type filters
        if (!empty($filters['violationTypes'])) {
            $violationConditions = [];
            foreach ($filters['violationTypes'] as $type) {
                if (is_numeric($type)) {
                    $violationConditions[] = "v.violation_type_id = " . (int)$type;
                } elseif ($type === 'uniform') {
                    $violationConditions[] = "vt.name LIKE '%Uniform%'";
                } elseif ($type === 'footwear') {
                    $violationConditions[] = "(vt.name LIKE '%Footwear%' OR vt.name LIKE '%Shoe%')";
                } elseif ($type === 'no_id') {
                    $violationConditions[] = "vt.name LIKE '%ID%'";
                }
            }
            if (!empty($violationConditions)) {
                $query .= " AND (" . implode(' OR ', $violationConditions) . ")";
            }
        }
        
        $query .= " GROUP BY s.id, s.student_id, s.first_name, s.middle_name, s.last_name, 
                           s.contact_number, s.avatar, s.department, s.section_id, 
                           d.department_name, sec.section_code, sec.section_name
                    HAVING COUNT(v.id) > 0";
        
        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }
            
            if (!empty($params) && !empty($types)) {
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new Exception('Bind param failed: ' . $stmt->error);
                }
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $generated = 0;
            $updated = 0;
            
            while ($row = $result->fetch_assoc()) {
                $firstName = $row['first_name'] ?? '';
                $middleName = $row['middle_name'] ?? '';
                $lastName = $row['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName);
                
                if (empty($fullName)) {
                    $fullName = 'Student ' . ($row['student_id'] ?? 'Unknown');
                }
                
                // Determine status
                $maxStatusLevel = (int)($row['max_status_level'] ?? 0);
                $status = 'permitted';
                if ($maxStatusLevel >= 3) {
                    $status = 'disciplinary';
                } elseif ($maxStatusLevel >= 2) {
                    $status = 'warning';
                }
                
                // Generate report ID
                $reportId = 'R' . str_pad($row['student_db_id'], 3, '0', STR_PAD_LEFT);
                
                // Check if report exists
                $existing = $this->query("SELECT id FROM reports WHERE report_id = ?", [$reportId]);
                
                $data = [
                    'report_id' => $reportId,
                    'student_id' => $row['student_id'],
                    'student_name' => $fullName,
                    'student_contact' => $row['contact_number'] ?? null,
                    'department' => $row['department_name'] ?? ($row['student_dept_code'] ?? null),
                    'department_code' => $row['student_dept_code'] ?? null,
                    'section' => $row['section_code'] ?? null,
                    'section_id' => $row['section_id'] ?? null,
                    'yearlevel' => $row['yearlevel'] ?? 'N/A',
                    'uniform_count' => (int)($row['uniform_count'] ?? 0),
                    'footwear_count' => (int)($row['footwear_count'] ?? 0),
                    'no_id_count' => (int)($row['no_id_count'] ?? 0),
                    'total_violations' => (int)($row['total_violations'] ?? 0),
                    'status' => $status,
                    'last_violation_date' => $row['last_violation_date'] ?? null,
                    'report_period_start' => $row['first_violation_date'] ?? $startDate,
                    'report_period_end' => $row['last_violation_date'] ?? $endDate
                ];
                
                if (!empty($existing)) {
                    // Update existing report
                    $this->update($existing[0]['id'], $data);
                    $updated++;
                } else {
                    // Create new report
                    $this->create($data);
                    $generated++;
                }
            }
            
            $stmt->close();
            
            // Sync violation history and recommendations
            $this->syncReportViolations($startDate, $endDate);
            $this->syncReportRecommendations();
            
            return [
                'generated' => $generated,
                'updated' => $updated,
                'total' => $generated + $updated
            ];
            
        } catch (Exception $e) {
            if (isset($stmt)) {
                $stmt->close();
            }
            error_log("ReportModel::generateReportsFromViolations error: " . $e->getMessage());
            throw new Exception('Failed to generate reports: ' . $e->getMessage());
        }
    }
    
    /**
     * Get reports from reports table
     */
    public function getStudentReports($filters = []) {
        // Check if reports table exists
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE 'reports'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            // If table doesn't exist, try to create and generate
            try {
                $this->createReportsTables();
                $this->generateReportsFromViolations();
            } catch (Exception $e) {
                // If violations table missing or other error
                return [];
            }
        }
        
        // Also check if table is empty and try to auto-generate
        $countCheck = @$this->conn->query("SELECT COUNT(*) as count FROM reports");
        if ($countCheck) {
            $row = $countCheck->fetch_assoc();
            if ($row['count'] == 0) {
                try {
                    $this->generateReportsFromViolations();
                } catch (Exception $e) {
                    // Ignore error if violations table is empty/missing
                }
            }
        }
        
        $department = $filters['department'] ?? 'all';
        $section = $filters['section'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;
        $search = $filters['search'] ?? '';
        
        $query = "SELECT r.*, s.avatar, s.yearlevel
                  FROM reports r
                  LEFT JOIN students s ON BINARY r.student_id = BINARY s.student_id
                  WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Apply filters
        if ($department !== 'all' && !empty($department)) {
            if (strpos($department, ',') !== false) {
                $depts = explode(',', $department);
                $placeholders = implode(',', array_fill(0, count($depts), '?'));
                $query .= " AND r.department_code IN ($placeholders)";
                foreach ($depts as $dept) {
                    $params[] = trim($dept);
                    $types .= "s";
                }
            } else {
                $query .= " AND r.department_code = ?";
                $params[] = $department;
                $types .= "s";
            }
        }
        
        if ($section !== 'all' && !empty($section)) {
            $query .= " AND (r.section = ? OR r.section_id = ?)";
            $params[] = $section;
            $params[] = $section;
            $types .= "ss";
        }
        
        if ($status !== 'all' && !empty($status)) {
            $query .= " AND r.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($startDate && $endDate) {
            $query .= " AND r.report_period_start >= ? AND r.report_period_end <= ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        } elseif ($startDate) {
            $query .= " AND r.report_period_start >= ?";
            $params[] = $startDate;
            $types .= "s";
        } elseif ($endDate) {
            $query .= " AND r.report_period_end <= ?";
            $params[] = $endDate;
            $types .= "s";
        }
        
        if (!empty($search)) {
            $query .= " AND (r.student_name LIKE ? OR r.student_id LIKE ? OR r.report_id LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }
        
        $query .= " ORDER BY r.total_violations DESC, r.student_name ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }
            
            if (!empty($params) && !empty($types)) {
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new Exception('Bind param failed: ' . $stmt->error);
                }
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $reports = [];
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $avatar = $row['avatar'] ?? '';
                    if (empty($avatar)) {
                        $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($row['student_name']) . '&background=ffd700&color=333&size=80';
                    }
                    
                    $statusLabels = [
                        'permitted' => 'Permitted',
                        'warning' => 'Warning',
                        'disciplinary' => 'Disciplinary Action'
                    ];
                    $statusLabel = $statusLabels[$row['status']] ?? ucfirst($row['status']);
                    
                    // Get violation history
                    $history = $this->getReportViolationHistory($row['id']);
                    
                    // Get recommendations
                    $recommendations = $this->getReportRecommendations($row['id']);
                    
                    $reports[] = [
                        'id' => (int)$row['id'],
                        'reportId' => $row['report_id'],
                        'studentId' => $row['student_id'],
                        'studentName' => $row['student_name'],
                        'studentImage' => $avatar,
                        'studentContact' => $row['student_contact'] ?? 'N/A',
                        'department' => $row['department'] ?? 'N/A',
                        'deptCode' => $row['department_code'] ?? '',
                        'section' => $row['section'] ?? 'N/A',
                        'sectionName' => $row['section'] ?? 'N/A',
                        'yearlevel' => $row['yearlevel'] ?? 'N/A',
                        'uniformCount' => (int)($row['uniform_count'] ?? 0),
                        'footwearCount' => (int)($row['footwear_count'] ?? 0),
                        'noIdCount' => (int)($row['no_id_count'] ?? 0),
                        'totalViolations' => (int)($row['total_violations'] ?? 0),
                        'status' => $row['status'],
                        'statusLabel' => $statusLabel,
                        'lastUpdated' => $row['last_violation_date'] ?? date('Y-m-d'),
                        'periodStart' => $row['report_period_start'] ?? null,
                        'periodEnd' => $row['report_period_end'] ?? null,
                        'history' => $history,
                        'recommendations' => $recommendations
                    ];
                }
            }
            
            $stmt->close();

            $typeCountsMap = $this->getStudentTypeCountsMap($filters);
            foreach ($reports as &$report) {
                $report['typeCounts'] = $typeCountsMap[$report['studentId']] ?? [];
            }
            unset($report);

            return $reports;
            
        } catch (Exception $e) {
            if (isset($stmt)) {
                $stmt->close();
            }
            error_log("ReportModel::getStudentReports error: " . $e->getMessage());
            throw new Exception('Database query error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get violation history for a report
     */
    private function getReportViolationHistory($reportId) {
        $query = "SELECT 
                    rv.violation_type,
                    rv.violation_level,
                    rv.violation_date,
                    rv.violation_time,
                    rv.status,
                    rv.notes,
                    v.location,
                    v.reported_by
                  FROM report_violations rv
                  LEFT JOIN violations v ON rv.violation_id = v.id
                  WHERE rv.report_id = ?
                  ORDER BY rv.violation_date DESC, rv.violation_time DESC LIMIT 10";
        
        try {
            $results = $this->query($query, [$reportId]);
            $history = [];
            
            $violationTypeLabels = [
                'improper_uniform' => 'Improper Uniform',
                'no_id' => 'No ID',
                'improper_footwear' => 'Improper Footwear'
            ];
            
            $violationLevelLabels = [
                'offense1'     => '1st Offense',
                'offense2'     => '2nd Offense',
                'offense3'     => '3rd Offense',
                'offense4'     => '4th Offense',
                'offense5'     => '5th Offense',
                'disciplinary' => 'Disciplinary',
                // Legacy fallbacks
                'permitted1'   => '1st Offense',
                'permitted2'   => '2nd Offense',
                'warning1'     => '3rd Offense',
                'warning2'     => '4th Offense',
                'warning3'     => '5th Offense',
            ];

            $locationLabels = [
                'campus' => 'Campus',
                'canteen' => 'Canteen',
                'classroom' => 'Classroom',
                'library' => 'Library',
                'gym' => 'Gymnasium',
                'others' => 'Others',
                'gate_1' => 'Main Gate 1',
                'gate_2' => 'Gate 2',
                'cafeteria' => 'Cafeteria'
            ];
            
            foreach ($results as $row) {
                $violationType = $violationTypeLabels[$row['violation_type']] ?? ucfirst(str_replace('_', ' ', $row['violation_type']));
                $violationLevel = $violationLevelLabels[$row['violation_level']] ?? ucfirst($row['violation_level'] ?? '');
                
                $date = $row['violation_date'] ?? '';
                $time = $row['violation_time'] ?? '';
                $formattedDate = '';
                $formattedTime = '';
                if ($date) {
                    $dateObj = new DateTime($date);
                    $formattedDate = $dateObj->format('M d, Y');
                }
                if ($time) {
                    $timeObj = new DateTime($time);
                    $formattedTime = $timeObj->format('g:i A');
                }

                $location = $locationLabels[$row['location'] ?? ''] ?? ucfirst(str_replace('_', ' ', $row['location'] ?? ''));
                $status = $row['status'] ?? 'warning';
                $reportedBy = $row['reported_by'] ?? '';
                
                $history[] = [
                    'date' => $formattedDate,
                    'time' => $formattedTime,
                    'title' => $violationLevel . ' - ' . $violationType,
                    'desc' => $row['notes'] ?? 'No additional notes',
                    'location' => $location,
                    'status' => $status,
                    'reportedBy' => $reportedBy
                ];
            }
            
            return $history;
        } catch (Exception $e) {
            error_log("Error getting report violation history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recommendations for a report
     */
    private function getReportRecommendations($reportId) {
        $query = "SELECT recommendation FROM report_recommendations 
                  WHERE report_id = ? 
                  ORDER BY priority DESC, id ASC";
        
        try {
            $results = $this->query($query, [$reportId]);
            return array_column($results, 'recommendation');
        } catch (Exception $e) {
            error_log("Error getting report recommendations: " . $e->getMessage());
            return $this->generateRecommendations(0, 'permitted');
        }
    }
    
    /**
     * Sync violation history to report_violations table
     */
    private function syncReportViolations($startDate = null, $endDate = null) {
        $query = "SELECT r.id as report_id, v.id as violation_id, 
                         vt.name as violation_type, 
                         vl.name as violation_level, 
                         v.violation_date, v.violation_time, 
                         v.status, v.notes
                  FROM reports r
                  INNER JOIN violations v ON BINARY r.student_id = BINARY v.student_id
                  LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                  LEFT JOIN violation_levels vl ON v.violation_level_id = vl.id";
        
        $params = [];
        $types = "";
        
        if ($startDate && $endDate) {
            $query .= " WHERE v.violation_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            if (!empty($params) && !empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Clear existing violations for reports in the date range (if specified)
            // We'll delete and re-insert to ensure data consistency
            if ($startDate && $endDate) {
                $clearQuery = "DELETE rv FROM report_violations rv 
                              INNER JOIN reports r ON rv.report_id = r.id
                              WHERE r.report_period_start >= ? AND r.report_period_end <= ?";
                $clearStmt = $this->conn->prepare($clearQuery);
                $clearStmt->bind_param("ss", $startDate, $endDate);
                $clearStmt->execute();
                $clearStmt->close();
            }
            
            // Insert violations
            while ($row = $result->fetch_assoc()) {
                // Check if violation already exists for this report
                $checkQuery = "SELECT id FROM report_violations 
                              WHERE report_id = ? AND violation_id = ?";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->bind_param("ii", $row['report_id'], $row['violation_id']);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();
                
                if (!$exists) {
                    $insertQuery = "INSERT INTO report_violations 
                                   (report_id, violation_id, violation_type, violation_level, 
                                    violation_date, violation_time, status, notes)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $insertStmt = $this->conn->prepare($insertQuery);
                    $insertStmt->bind_param("iissssss",
                        $row['report_id'],
                        $row['violation_id'],
                        $row['violation_type'],
                        $row['violation_level'],
                        $row['violation_date'],
                        $row['violation_time'],
                        $row['status'],
                        $row['notes']
                    );
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error syncing report violations: " . $e->getMessage());
        }
    }
    
    /**
     * Sync recommendations to report_recommendations table
     */
    private function syncReportRecommendations() {
        $reports = $this->query("SELECT id, total_violations, status FROM reports");
        
        foreach ($reports as $report) {
            $recommendations = $this->generateRecommendations(
                (int)$report['total_violations'],
                $report['status']
            );
            
            // Clear existing recommendations
            $this->conn->query("DELETE FROM report_recommendations WHERE report_id = " . (int)$report['id']);
            
            // Insert new recommendations
            foreach ($recommendations as $index => $rec) {
                $priority = 'medium';
                if ($report['total_violations'] >= 5 || $report['status'] === 'disciplinary') {
                    $priority = 'high';
                } elseif ($report['total_violations'] < 2) {
                    $priority = 'low';
                }
                
                $insertQuery = "INSERT INTO report_recommendations 
                               (report_id, recommendation, priority) 
                               VALUES (?, ?, ?)";
                $insertStmt = $this->conn->prepare($insertQuery);
                $insertStmt->bind_param("iss", $report['id'], $rec, $priority);
                $insertStmt->execute();
                $insertStmt->close();
            }
        }
    }
    
    /**
     * Generate recommendations based on violation count and status
     */
    private function generateRecommendations($totalViolations, $status) {
        $recommendations = [];
        
        if ($totalViolations >= 5 || $status === 'disciplinary') {
            $recommendations[] = 'Schedule counseling session with student';
            $recommendations[] = 'Notify parents about disciplinary status';
            $recommendations[] = 'Monitor student for next 30 days';
        } elseif ($totalViolations >= 3 || $status === 'warning') {
            $recommendations[] = 'Issue written warning';
            $recommendations[] = 'Monitor uniform compliance';
            $recommendations[] = 'Schedule follow-up meeting';
        } else {
            $recommendations[] = 'Remind student about dress code policies';
            $recommendations[] = 'Monitor compliance for 2 weeks';
        }
        
        return $recommendations;
    }
    
    /**
     * Get all violation types for dynamic reports UI
     */
    public function getViolationTypesList() {
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE 'violation_types'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return [];
        }
        return $this->query("SELECT id, name FROM violation_types ORDER BY name ASC") ?: [];
    }

    /**
     * Apply shared violation query filters
     */
    private function applyViolationFilters($filters, &$query, &$params, &$types) {
        $department = $filters['department'] ?? 'all';
        $section = $filters['section'] ?? 'all';
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;
        $violationType = $filters['violationType'] ?? 'all';

        if ($department !== 'all' && !empty($department)) {
            if (strpos($department, ',') !== false) {
                $depts = array_map('trim', explode(',', $department));
                $placeholders = implode(',', array_fill(0, count($depts), '?'));
                $query .= " AND s.department IN ($placeholders)";
                foreach ($depts as $dept) {
                    $params[] = $dept;
                    $types .= "s";
                }
            } else {
                $query .= " AND s.department = ?";
                $params[] = $department;
                $types .= "s";
            }
        }

        if ($section !== 'all' && !empty($section)) {
            $query .= " AND (sec.section_code = ? OR CAST(s.section_id AS CHAR) = ?)";
            $params[] = $section;
            $params[] = $section;
            $types .= "ss";
        }

        if ($violationType !== 'all' && !empty($violationType)) {
            $query .= " AND v.violation_type_id = ?";
            $params[] = (int)$violationType;
            $types .= "i";
        }

        if ($startDate && $endDate) {
            $query .= " AND v.violation_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
            $types .= "ss";
        } elseif ($startDate) {
            $query .= " AND v.violation_date >= ?";
            $params[] = $startDate;
            $types .= "s";
        } elseif ($endDate) {
            $query .= " AND v.violation_date <= ?";
            $params[] = $endDate;
            $types .= "s";
        }
    }

    /**
     * Per-student violation counts keyed by type id
     */
    public function getStudentTypeCountsMap($filters = []) {
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE 'violations'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return [];
        }

        $query = "SELECT v.student_id, v.violation_type_id, COUNT(*) AS cnt
                  FROM violations v
                  INNER JOIN students s ON BINARY v.student_id = BINARY s.student_id
                  LEFT JOIN sections sec ON s.section_id = sec.id
                  WHERE (v.is_archived = 0 OR v.is_archived IS NULL)
                    AND s.status != 'archived'";

        $params = [];
        $types = "";
        $this->applyViolationFilters($filters, $query, $params, $types);
        $query .= " GROUP BY v.student_id, v.violation_type_id";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                return [];
            }
            if (!empty($params) && !empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $map = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $studentId = $row['student_id'];
                    $typeId = (int)$row['violation_type_id'];
                    if (!isset($map[$studentId])) {
                        $map[$studentId] = [];
                    }
                    $map[$studentId][$typeId] = (int)$row['cnt'];
                }
            }
            $stmt->close();
            return $map;
        } catch (Exception $e) {
            error_log("ReportModel::getStudentTypeCountsMap error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Global violation counts grouped by type id
     */
    public function getAggregatedTypeCounts($filters = []) {
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE 'violations'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return [];
        }

        $query = "SELECT v.violation_type_id, COUNT(*) AS cnt
                  FROM violations v
                  INNER JOIN students s ON BINARY v.student_id = BINARY s.student_id
                  LEFT JOIN sections sec ON s.section_id = sec.id
                  WHERE (v.is_archived = 0 OR v.is_archived IS NULL)
                    AND s.status != 'archived'";

        $params = [];
        $types = "";
        $this->applyViolationFilters($filters, $query, $params, $types);
        $query .= " GROUP BY v.violation_type_id";

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                return [];
            }
            if (!empty($params) && !empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $counts = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $counts[(int)$row['violation_type_id']] = (int)$row['cnt'];
                }
            }
            $stmt->close();
            return $counts;
        } catch (Exception $e) {
            error_log("ReportModel::getAggregatedTypeCounts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get statistics for reports
     */
    public function getReportStats($filters = []) {
        $reports = $this->getStudentReports($filters);
        $types = $this->getViolationTypesList();
        $countsMap = $this->getAggregatedTypeCounts($filters);

        $totalViolations = array_sum($countsMap);
        $typeStats = [];
        foreach ($types as $type) {
            $id = (int)$type['id'];
            $count = (int)($countsMap[$id] ?? 0);
            $typeStats[] = [
                'id' => $id,
                'name' => $type['name'],
                'count' => $count,
                'percentage' => $totalViolations > 0 ? (int)round(($count / $totalViolations) * 100) : 0
            ];
        }

        $stats = [
            'totalViolations' => $totalViolations,
            'uniformViolations' => 0,
            'footwearViolations' => 0,
            'noIdViolations' => 0,
            'totalStudents' => count($reports),
            'typeStats' => $typeStats
        ];

        foreach ($typeStats as $typeStat) {
            $nameLower = strtolower($typeStat['name']);
            if (strpos($nameLower, 'uniform') !== false) {
                $stats['uniformViolations'] += $typeStat['count'];
            } elseif (strpos($nameLower, 'footwear') !== false || strpos($nameLower, 'shoe') !== false) {
                $stats['footwearViolations'] += $typeStat['count'];
            } elseif (strpos($nameLower, 'id') !== false) {
                $stats['noIdViolations'] += $typeStat['count'];
            }
        }

        return $stats;
    }
}
