<?php
require_once __DIR__ . '/../core/Model.php';

class ViolationModel extends Model {
    protected $table = 'violations';
    protected $primaryKey = 'id';

    /**
     * Mark a violation as read
     */
    public function markAsRead($id, $studentId = null) {
        $query = "UPDATE violations SET is_read = 1 WHERE id = ?";
        $params = [$id];
        $types = "i";
        
        if ($studentId) {
            $query .= " AND BINARY student_id = BINARY ?";
            $params[] = $studentId;
            $types .= "s";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Mark all violations as read for a student
     */
    public function markAllAsRead($studentId) {
        if (empty($studentId)) return false;
        
        $query = "UPDATE violations SET is_read = 1 WHERE BINARY student_id = BINARY ? AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $studentId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Get all violations with student info
     */
    public function getAllWithStudentInfo($filter = 'all', $search = '', $studentId = '', $dateFrom = '', $dateTo = '', $isArchived = 0, $specificId = null) {
        // Check if violations table exists
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE 'violations'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            throw new Exception('Violations table does not exist. Please run the database setup SQL file: database/osas.sql');
        }

        // Auto-fix: Check if is_archived column exists, if not add it
        $columnCheck = @$this->conn->query("SHOW COLUMNS FROM violations LIKE 'is_archived'");
        if ($columnCheck === false || $columnCheck->num_rows === 0) {
            @$this->conn->query("ALTER TABLE violations ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
            @$this->conn->query("ALTER TABLE violations ADD INDEX idx_is_archived (is_archived)");
        }

        // Auto-fix: Check if is_read column exists, if not add it
        $readColumnCheck = @$this->conn->query("SHOW COLUMNS FROM violations LIKE 'is_read'");
        if ($readColumnCheck === false || $readColumnCheck->num_rows === 0) {
            @$this->conn->query("ALTER TABLE violations ADD COLUMN is_read TINYINT(1) DEFAULT 0");
            @$this->conn->query("ALTER TABLE violations ADD INDEX idx_is_read (is_read)");
        }

        // Auto-fix: Check status column in violation_types and violation_levels
        $vtStatusCheck = @$this->conn->query("SHOW COLUMNS FROM violation_types LIKE 'status'");
        if ($vtStatusCheck === false || $vtStatusCheck->num_rows === 0) {
            @$this->conn->query("ALTER TABLE violation_types ADD COLUMN status ENUM('active', 'archived') DEFAULT 'active'");
            @$this->conn->query("ALTER TABLE violation_types ADD INDEX idx_vt_status (status)");
        }
        $vlStatusCheck = @$this->conn->query("SHOW COLUMNS FROM violation_levels LIKE 'status'");
        if ($vlStatusCheck === false || $vlStatusCheck->num_rows === 0) {
            @$this->conn->query("ALTER TABLE violation_levels ADD COLUMN status ENUM('active', 'archived') DEFAULT 'active'");
            @$this->conn->query("ALTER TABLE violation_levels ADD INDEX idx_vl_status (status)");
        }

        // Auto-fix: Create violation_statuses table if it doesn't exist
        $this->conn->query("CREATE TABLE IF NOT EXISTS violation_statuses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            status_color VARCHAR(20) DEFAULT '#f59e0b',
            status ENUM('active', 'archived') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Seed default statuses if table is empty
        $statusCountCheck = $this->conn->query("SELECT COUNT(*) as count FROM violation_statuses");
        if ($statusCountCheck && $statusCountCheck->fetch_assoc()['count'] == 0) {
            $defaultStatuses = [
                ['Warning', '#f59e0b'],
                ['Permitted', '#10b981'],
                ['Disciplinary', '#ef4444'],
                ['Resolved', '#3b82f6']
            ];
            $stmt = $this->conn->prepare("INSERT INTO violation_statuses (name, status_color) VALUES (?, ?)");
            foreach ($defaultStatuses as $ds) {
                $stmt->bind_param("ss", $ds[0], $ds[1]);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        // First, check if there are any violations at all (without JOIN)
        $countQuery = "SELECT COUNT(*) as total FROM violations WHERE is_archived = ?";
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->bind_param("i", $isArchived);
        $stmtCount->execute();
        $countResult = $stmtCount->get_result();
        
        $totalCount = 0;
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            $totalCount = (int)$countRow['total'];
        }
        
        error_log("ViolationsModel: Total violations in database (archived=$isArchived): $totalCount");
        
        // If no violations exist, return empty array immediately
        if ($totalCount === 0) {
            error_log("ViolationsModel: No violations found in database, returning empty array");
            return [];
        }
        
        // Use LEFT JOIN so we get violations even if student doesn't exist
        // Fix collation mismatch by using BINARY comparison (case-sensitive but works with any collation)
        // Also join with departments table to get department names
        $query = "SELECT 
                    v.*, 
                    vt.name as violation_type_name,
                    vl.name as violation_level_name,
                    s.student_id as student_id_no,
                    s.first_name, 
                    s.middle_name, 
                    s.last_name, 
                    s.email, 
                    s.contact_number, 
                    s.avatar,
                    s.department as student_dept,
                    s.section_id as student_section,
                    s.yearlevel as student_yearlevel,
                    s.department as student_dept_code,
                    COALESCE(d.department_name, s.department) as department_name,
                    COALESCE(sec.section_name, 'N/A') as section_name,
                    COALESCE(sec.section_code, 'N/A') as section_code
                  FROM violations v
                  LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
                  LEFT JOIN violation_levels vl ON v.violation_level_id = vl.id
                  LEFT JOIN students s ON BINARY v.student_id = BINARY s.student_id
                  LEFT JOIN departments d ON s.department = d.department_code
                  LEFT JOIN sections sec ON s.section_id = sec.id
                  WHERE v.is_archived = ?";
        
        $params = [$isArchived];
        $types = "i";

        if (!empty($studentId)) {
            // If it's a numeric ID, allow filtering by students.id as well.
            // Otherwise, only filter by the student_id string to avoid MySQL coercion issues
            // (e.g. "2024-001" being treated as 2024).
            if (ctype_digit((string)$studentId)) {
                $query .= " AND (BINARY v.student_id = BINARY ? OR BINARY s.student_id = BINARY ? OR s.id = ?)";
                $params[] = $studentId;
                $params[] = $studentId;
                $params[] = (int)$studentId;
                $types .= "ssi";
            } else {
                $query .= " AND (BINARY v.student_id = BINARY ? OR BINARY s.student_id = BINARY ?)";
                $params[] = $studentId;
                $params[] = $studentId;
                $types .= "ss";
            }
        }

        if ($filter === 'resolved') {
            $query .= " AND v.status = 'resolved'";
        } elseif ($filter === 'pending') {
            $query .= " AND v.status IN ('warning', 'permitted')";
        } elseif ($filter === 'disciplinary') {
            $query .= " AND v.status = 'disciplinary'";
        }

        if (!empty($search)) {
            $query .= " AND (v.case_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR v.student_id LIKE ? OR vt.name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ? OR CONCAT_WS(' ', s.first_name, s.last_name) LIKE ?)";
            $searchTerm = "%$search%";
            $searchParams = array_fill(0, 7, $searchTerm);
            $params = array_merge($params, $searchParams);
            $types .= "sssssss";
        }

        if (!empty($dateFrom)) {
            $query .= " AND v.violation_date >= ?";
            $params[] = $dateFrom;
            $types .= "s";
        }

        if (!empty($dateTo)) {
            $query .= " AND v.violation_date <= ?";
            $params[] = $dateTo;
            $types .= "s";
        }

        if (!empty($specificId)) {
            $query .= " AND v.id = ?";
            $params[] = $specificId;
            $types .= "i";
        }

        $query .= " ORDER BY v.created_at DESC";

        try {
            // Debug: Log the query (remove in production)
            error_log("Violations Query: " . $query);
            error_log("Query params: " . print_r($params, true));
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                $error = $this->conn->error;
                error_log("Prepare failed: " . $error);
                error_log("Query was: " . $query);
                throw new Exception('Prepare failed: ' . $error);
            }
            
            if (!empty($params) && !empty($types)) {
                if (!$stmt->bind_param($types, ...$params)) {
                    $error = $stmt->error;
                    error_log("Bind param failed: " . $error);
                    throw new Exception('Bind param failed: ' . $error);
                }
            }
            
            if (!$stmt->execute()) {
                $error = $stmt->error;
                error_log("Execute failed: " . $error);
                throw new Exception('Execute failed: ' . $error);
            }
            
            $result = $stmt->get_result();
            
            // Check if result is valid
            if ($result === false) {
                $error = $this->conn->error;
                error_log("Failed to get result: " . $error);
                throw new Exception('Failed to get result: ' . $error);
            }
            
            // Debug: Log row count
            $rowCount = $result ? $result->num_rows : 0;
            error_log("Violations query returned $rowCount rows");
            
        } catch (Exception $e) {
            if (isset($stmt)) {
                $stmt->close();
            }
            error_log("Violations query error: " . $e->getMessage());
            throw new Exception('Database query error: ' . $e->getMessage());
        }

        $violations = [];
        
        // Check if result is valid and has rows
        if ($result === false) {
            error_log("ViolationsModel: Result is false, query may have failed");
            $stmt->close();
            return [];
        }
        
        if ($result && $result->num_rows > 0) {
            error_log("ViolationsModel: Processing " . $result->num_rows . " rows");
            while ($row = $result->fetch_assoc()) {
                $firstName = $row['first_name'] ?? '';
                $middleName = $row['middle_name'] ?? '';
                $lastName = $row['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName);
                
                // If no student name from JOIN, use student_id as fallback
                if (empty($fullName)) {
                    $fullName = 'Student ' . ($row['student_id'] ?? 'Unknown');
                }

                $avatar = $row['avatar'] ?? '';
                if (empty($avatar)) {
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=ffd700&color=333&size=80';
                }

                $violationTypeLabel = $row['violation_type_name'] ?? 'Unknown Type';
                $violationLevelLabel = $row['violation_level_name'] ?? 'Unknown Level';

                $statusLabels = [
                    'permitted' => 'Permitted',
                    'warning' => 'Warning',
                    'disciplinary' => 'Disciplinary',
                    'resolved' => 'Resolved'
                ];
                $statusLabel = $statusLabels[$row['status']] ?? ucfirst($row['status']);

                $locationLabels = [
                    'campus' => 'Campus',
                    'canteen' => 'Canteen',
                    'classroom' => 'Classroom',
                    'library' => 'Library',
                    'gym' => 'Gymnasium',
                    'others' => 'Others',
                    // Legacy values (existing records before location update)
                    'gate_1' => 'Main Gate 1',
                    'gate_2' => 'Gate 2',
                    'cafeteria' => 'Cafeteria',
                ];
                $locationLabel = $locationLabels[$row['location']] ?? ucfirst(str_replace('_', ' ', $row['location']));

                $violationDateTime = '';
                if ($row['violation_date'] && $row['violation_time']) {
                    $dateTime = new DateTime($row['violation_date'] . ' ' . $row['violation_time']);
                    $violationDateTime = $dateTime->format('M d, Y • h:i A');
                }

                $violations[] = [
                    'id' => (int)$row['id'],
                    'caseId' => $row['case_id'] ?? '',
                    'studentId' => $row['student_id'] ?? '',
                    'studentName' => $fullName,
                    'studentImage' => $avatar,
                    'studentDept' => $row['department_name'] ?? $row['student_dept'] ?? 'N/A',
                    'studentSection' => $row['section_code'] ?? $row['section_name'] ?? 'N/A',
                    'studentYearlevel' => $row['student_yearlevel'] ?? 'N/A',
                    'studentContact' => $row['contact_number'] ?? 'N/A',
                    'violationType' => $row['violation_type_id'] ?? '',
                    'violationTypeLabel' => $violationTypeLabel,
                    'violationLevel' => $row['violation_level_id'] ?? '',
                    'violationLevelLabel' => $violationLevelLabel,
                    'department' => $row['department_name'] ?? $row['student_dept'] ?? 'N/A',
                    'department_code' => $row['student_dept_code'] ?? $row['student_dept'] ?? 'N/A',
                    'section' => $row['section_code'] ?? $row['section_name'] ?? 'N/A',
                    'dateReported' => $row['violation_date'] ?? '',
                    'violationTime' => $row['violation_time'] ?? '',
                    'dateTime' => $violationDateTime,
                    'location' => $row['location'] ?? '',
                    'locationLabel' => $locationLabel,
                    'reportedBy' => $row['reported_by'] ?? '',
                    'status' => $row['status'] ?? 'warning',
                    'statusLabel' => $statusLabel,
                    'notes' => $row['notes'] ?? '',
                    'attachments' => !empty($row['attachments']) ? json_decode($row['attachments'], true) : [],
                    'is_archived' => (int)($row['is_archived'] ?? 0),
                    'created_at' => $row['created_at'] ?? '',
                    'updated_at' => $row['updated_at'] ?? ''
                ];
            }
        }

        if (isset($stmt)) {
            $stmt->close();
        }
        
        // Log final count
        error_log("ViolationsModel: Returning " . count($violations) . " violations");
        
        // If no violations returned but query succeeded, log a warning
        if (count($violations) === 0 && $totalCount > 0) {
            error_log("WARNING: ViolationsModel query returned 0 rows but database has $totalCount violations. Check if JOIN is working or if filters are too restrictive.");
        } elseif (count($violations) === 0 && $totalCount === 0) {
            error_log("INFO: No violations in database (count is 0)");
        }
        
        return $violations;
    }

    /**
     * Check if a new month has started and trigger auto-archive.
     * Uses the database instead of a file so it works correctly on AWS
     * (git pull would overwrite a file-based tracker).
     */
    public function checkAndTriggerAutoArchive() {
        $currentMonth = date('Y-m');

        // Store last reset month in the database (settings table or a dedicated row)
        // We use a simple query to check/update a settings-style record
        try {
            // Try to get last reset from DB
            $result = $this->conn->query(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'last_monthly_reset' LIMIT 1"
            );

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $lastReset = $row['setting_value'];
            } else {
                // First time — create the record
                $this->conn->query(
                    "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('last_monthly_reset', '')"
                );
                $lastReset = '';
            }

            if ($lastReset !== $currentMonth) {
                error_log("📅 New month detected ($currentMonth). Triggering auto-archive...");
                $archived = $this->archivePreviousMonthViolations();
                if ($archived) {
                    $stmt = $this->conn->prepare(
                        "INSERT INTO system_settings (setting_key, setting_value)
                         VALUES ('last_monthly_reset', ?)
                         ON DUPLICATE KEY UPDATE setting_value = ?"
                    );
                    $stmt->bind_param('ss', $currentMonth, $currentMonth);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("⚠️ Auto-archive check failed: " . $e->getMessage());
            // Fall back to file-based tracking so the app doesn't crash
            $lastResetFile = __DIR__ . '/../../storage/last_reset.txt';
            if (!file_exists(dirname($lastResetFile))) {
                @mkdir(dirname($lastResetFile), 0777, true);
            }
            $lastReset = @file_get_contents($lastResetFile) ?: '';
            if ($lastReset !== $currentMonth) {
                $this->archivePreviousMonthViolations();
                @file_put_contents($lastResetFile, $currentMonth);
            }
        }
    }

    /**
     * Archive violations from previous months and reset levels.
     */
    public function archivePreviousMonthViolations() {
        $this->conn->begin_transaction();
        try {
            // Archive all violations from before the current month
            $currentMonthStart = date('Y-m-01 00:00:00');
            $stmt = $this->conn->prepare(
                "UPDATE violations SET is_archived = 1 WHERE created_at < ? AND is_archived = 0"
            );
            $stmt->bind_param("s", $currentMonthStart);
            $stmt->execute();
            $stmt->close();

            // Note: violation levels are derived from the violations table (not stored on students),
            // so archiving old violations effectively resets each student's active level count.
            // No students table update needed.

            $this->conn->commit();
            error_log("✅ Monthly archive complete. Violations before $currentMonthStart archived.");
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("❌ Error during auto-archive: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate case ID
     */
    public function generateCaseId() {
        $year = date('Y');
        $result = $this->query("SELECT COUNT(*) as count FROM violations WHERE YEAR(created_at) = ?", [$year]);
        $count = ($result[0]['count'] ?? 0) + 1;
        return sprintf('VIOL-%d-%03d', $year, $count);
    }

    /**
     * Check for duplicate violation with time tolerance
     */
    public function checkDuplicate($studentId, $violationTypeId, $violationLevelId, $violationDate, $violationTime, $location) {
        $query = "SELECT id FROM violations 
                  WHERE student_id = ? 
                  AND violation_type_id = ? 
                  AND violation_level_id = ?
                  AND violation_date = ? 
                  AND violation_time = ? 
                  AND location = ? 
                  AND deleted_at IS NULL";
        
        $result = $this->query($query, [$studentId, $violationTypeId, $violationLevelId, $violationDate, $violationTime, $location]);
        return !empty($result) ? $result[0]['id'] : false;
    }

    /**
     * Check for duplicate violation submission (exact match or created recently)
     */
    public function checkDuplicateSubmission($studentId, $violationTypeId, $violationLevelId, $violationDate, $violationTime, $location) {
        // 1. Check for exact match (same details)
        // We check for records created recently (last 10 seconds) with same details to catch double-clicks
        // OR simply check if such a violation exists at all. 
        // Given "only one", we assume strict uniqueness for (student, type, date, time).
        
        $query = "SELECT id FROM violations 
                  WHERE student_id = ? 
                  AND violation_type_id = ? 
                  AND violation_level_id = ?
                  AND violation_date = ? 
                  AND violation_time = ? 
                  AND location = ? 
                  AND deleted_at IS NULL";
        
        $result = $this->query($query, [$studentId, $violationTypeId, $violationLevelId, $violationDate, $violationTime, $location]);
        
        if (!empty($result)) {
            return $result[0]['id'];
        }

        // 2. Check for "same violation, different timestamp" if needed (e.g. submitted 1 second apart but with different input time?)
        // If the user inputs the time manually, it will be the same.
        // If the system auto-generates time, it might differ.
        // Assuming user inputs time or system sets it once in frontend.
        
        return false;
    }

    /**
     * Check for duplicate violation within time window (for near-simultaneous submissions)
     */
    public function checkDuplicateInTimeWindow($studentId, $violationTypeId, $violationLevelId, $violationDate, $violationTime, $location, $timeWindowMinutes = 5) {
        // Check exact match first
        $exactMatch = $this->checkDuplicate($studentId, $violationTypeId, $violationLevelId, $violationDate, $violationTime, $location);
        if ($exactMatch) {
            return $exactMatch;
        }

        // Check for violations within time window
        // Use CAST(? AS TIME) to handle various time formats safely
        $query = "SELECT id, violation_time FROM violations 
                  WHERE student_id = ? 
                  AND violation_type_id = ? 
                  AND violation_level_id = ?
                  AND violation_date = ? 
                  AND location = ? 
                  AND deleted_at IS NULL
                  AND ABS(TIMESTAMPDIFF(MINUTE, CAST(? AS TIME), violation_time)) <= ?";
        
        $result = $this->query($query, [$studentId, $violationTypeId, $violationLevelId, $violationDate, $location, $violationTime, $timeWindowMinutes]);
        return !empty($result) ? $result[0]['id'] : false;
    }

    /**
     * Check if case ID already exists
     */
    public function caseIdExists($caseId) {
        $query = "SELECT id FROM violations WHERE case_id = ?";
        $result = $this->query($query, [$caseId]);
        return !empty($result);
    }

    /**
     * Get all violation statuses
     */
    public function getViolationStatuses($includeArchived = false) {
        // Auto-fix: Create violation_statuses table if it doesn't exist
        $this->conn->query("CREATE TABLE IF NOT EXISTS violation_statuses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            status_color VARCHAR(20) DEFAULT '#f59e0b',
            status ENUM('active', 'archived') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Seed default statuses if table is empty
        $statusCountCheck = $this->conn->query("SELECT COUNT(*) as count FROM violation_statuses");
        if ($statusCountCheck && $statusCountCheck->fetch_assoc()['count'] == 0) {
            $defaultStatuses = [
                ['Warning', '#f59e0b'],
                ['Permitted', '#10b981'],
                ['Disciplinary', '#ef4444'],
                ['Resolved', '#3b82f6']
            ];
            $stmt = $this->conn->prepare("INSERT INTO violation_statuses (name, status_color) VALUES (?, ?)");
            foreach ($defaultStatuses as $ds) {
                $stmt->bind_param("ss", $ds[0], $ds[1]);
                $stmt->execute();
            }
            $stmt->close();
        }

        $where = $includeArchived ? "" : " WHERE status = 'active'";
        $query = "SELECT * FROM violation_statuses" . $where . " ORDER BY name ASC";
        $result = $this->conn->query($query);
        $statuses = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $statuses[] = $row;
            }
        }
        return $statuses;
    }

    /**
     * Create a new violation status
     */
    public function createViolationStatus($name, $color = '#f59e0b') {
        $name = trim($name);
        if ($name === '') throw new Exception('Status name is required');
        
        $stmt = $this->conn->prepare("INSERT INTO violation_statuses (name, status_color) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $color);
        if (!$stmt->execute()) {
            if ($this->conn->errno == 1062) throw new Exception('Status name already exists');
            throw new Exception('Failed to create status: ' . $stmt->error);
        }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Update a violation status
     */
    public function updateViolationStatus($id, $name, $color) {
        $name = trim($name);
        if ($name === '') throw new Exception('Status name is required');
        
        $stmt = $this->conn->prepare("UPDATE violation_statuses SET name = ?, status_color = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $color, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Delete/Archive a violation status
     */
    public function deleteViolationStatus($id) {
        // Check if status is in use in violation_levels
        $stmt = $this->conn->prepare("SELECT name FROM violation_statuses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $statusRow = $res->fetch_assoc();
        $stmt->close();

        if ($statusRow) {
            $statusName = $statusRow['name'];
            $stmt = $this->conn->prepare("SELECT COUNT(*) as cnt FROM violation_levels WHERE default_status = ?");
            $stmt->bind_param("s", $statusName);
            $stmt->execute();
            $res = $stmt->get_result();
            $count = $res->fetch_assoc()['cnt'];
            $stmt->close();

            if ($count > 0) {
                // Archive if in use
                $stmt = $this->conn->prepare("UPDATE violation_statuses SET status = 'archived' WHERE id = ?");
                $stmt->bind_param("i", $id);
                $success = $stmt->execute();
                $stmt->close();
                return $success;
            } else {
                // Delete if not in use
                $stmt = $this->conn->prepare("DELETE FROM violation_statuses WHERE id = ?");
                $stmt->bind_param("i", $id);
                $success = $stmt->execute();
                $stmt->close();
                return $success;
            }
        }
        return false;
    }

    /**
     * Restore a violation status
     */
    public function restoreViolationStatus($id) {
        $stmt = $this->conn->prepare("UPDATE violation_statuses SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Get all violation types
     */
    public function getViolationTypes($includeArchived = false) {
        $where = $includeArchived ? "" : " WHERE status = 'active'";
        return $this->query("SELECT * FROM violation_types $where ORDER BY name ASC");
    }

    /**
     * Get violation levels by type
     */
    public function getViolationLevels($typeId, $includeArchived = false) {
        $where = $includeArchived ? "" : " AND status = 'active'";
        $query = "SELECT * FROM violation_levels WHERE violation_type_id = ? $where ORDER BY level_order ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $typeId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $levels = [];
        while ($row = $result->fetch_assoc()) {
            $levels[] = $row;
        }
        $stmt->close();
        return $levels;
    }

    /**
     * Default offense levels for a new violation type
     */
    private function getDefaultLevelTemplates() {
        return [
            ['1st Offense', 'First offense', 1],
            ['2nd Offense', 'Second offense', 2],
            ['3rd Offense', 'Third offense', 3],
            ['4th Offense', 'Fourth offense', 4],
            ['5th Offense', 'Fifth offense — triggers disciplinary action', 5],
        ];
    }

    /**
     * Create a violation type with default levels
     */
    public function createViolationType($name, $description = '') {
        $name = trim($name);
        if ($name === '') {
            throw new Exception('Violation type name is required');
        }

        $existing = $this->query(
            "SELECT id FROM violation_types WHERE LOWER(name) = LOWER(?)",
            [$name]
        );
        if (!empty($existing)) {
            throw new Exception('A violation type with this name already exists');
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO violation_types (name, description, created_at) VALUES (?, ?, NOW())"
        );
        $stmt->bind_param("ss", $name, $description);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Failed to create violation type: ' . $error);
        }
        $typeId = (int)$stmt->insert_id;
        $stmt->close();

        foreach ($this->getDefaultLevelTemplates() as [$levelName, $levelDesc, $order]) {
            $this->createViolationLevel($typeId, $levelName, $levelDesc, $order);
        }

        return $typeId;
    }

    /**
     * Update a violation type
     */
    public function updateViolationType($id, $name, $description = '') {
        $name = trim($name);
        if ($name === '') {
            throw new Exception('Violation type name is required');
        }

        $existing = $this->query(
            "SELECT id FROM violation_types WHERE LOWER(name) = LOWER(?) AND id != ?",
            [$name, $id]
        );
        if (!empty($existing)) {
            throw new Exception('A violation type with this name already exists');
        }

        $stmt = $this->conn->prepare(
            "UPDATE violation_types SET name = ?, description = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param("ssi", $name, $description, $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Delete a violation type
     * If historical records exist, mark as 'archived'.
     * If no records exist, fully delete from database.
     */
    public function deleteViolationType($id) {
        $count = $this->countViolationsByType($id);
        
        if ($count > 0) {
            // ARCHIVE if history exists
            $stmt = $this->conn->prepare("UPDATE violation_types SET status = 'archived' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        } else {
            // HARD DELETE if no history
            $stmt = $this->conn->prepare("DELETE FROM violation_types WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
    }

    /**
     * Create a violation level
     */
    public function createViolationLevel($typeId, $name, $description = '', $levelOrder = null, $defaultStatus = 'warning', $statusColor = '#f59e0b') {
        $name = trim($name);
        if ($name === '') {
            throw new Exception('Level name is required');
        }

        if ($levelOrder === null) {
            $rows = $this->query(
                "SELECT COALESCE(MAX(level_order), 0) + 1 AS next_order FROM violation_levels WHERE violation_type_id = ?",
                [$typeId]
            );
            $levelOrder = (int)($rows[0]['next_order'] ?? 1);
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO violation_levels (violation_type_id, level_order, name, description, default_status, status_color, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("iissss", $typeId, $levelOrder, $name, $description, $defaultStatus, $statusColor);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Failed to create violation level: ' . $error);
        }
        $levelId = (int)$stmt->insert_id;
        $stmt->close();
        return $levelId;
    }

    /**
     * Update a violation level
     */
    public function updateViolationLevel($id, $name, $description = '', $levelOrder = null, $defaultStatus = 'warning', $statusColor = '#f59e0b') {
        $name = trim($name);
        if ($name === '') {
            throw new Exception('Level name is required');
        }

        if ($levelOrder !== null) {
            $stmt = $this->conn->prepare(
                "UPDATE violation_levels SET name = ?, description = ?, level_order = ?, default_status = ?, status_color = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param("ssissi", $name, $description, $levelOrder, $defaultStatus, $statusColor, $id);
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE violation_levels SET name = ?, description = ?, default_status = ?, status_color = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->bind_param("ssssi", $name, $description, $defaultStatus, $statusColor, $id);
        }
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Delete a violation level
     * If historical records exist, mark as 'archived'.
     * If no records exist, fully delete from database.
     */
    public function deleteViolationLevel($id) {
        $count = $this->countViolationsByLevel($id);
        
        if ($count > 0) {
            // ARCHIVE if history exists
            $stmt = $this->conn->prepare("UPDATE violation_levels SET status = 'archived' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        } else {
            // HARD DELETE if no history
            $stmt = $this->conn->prepare("DELETE FROM violation_levels WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
    }

    /**
     * Restore a violation type from archive
     */
    public function restoreViolationType($id) {
        $stmt = $this->conn->prepare("UPDATE violation_types SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Restore a violation level from archive
     */
    public function restoreViolationLevel($id) {
        $stmt = $this->conn->prepare("UPDATE violation_levels SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Count violations using a type
     */
    public function countViolationsByType($typeId) {
        $rows = $this->query(
            "SELECT COUNT(*) AS cnt FROM violations WHERE violation_type_id = ?",
            [$typeId]
        );
        return (int)($rows[0]['cnt'] ?? 0);
    }

    /**
     * Count violations using a level
     */
    public function countViolationsByLevel($levelId) {
        $rows = $this->query(
            "SELECT COUNT(*) AS cnt FROM violations WHERE violation_level_id = ?",
            [$levelId]
        );
        return (int)($rows[0]['cnt'] ?? 0);
    }

    /**
     * Count total violations
     */
    public function countViolations($studentId = null) {
        $query = "SELECT COUNT(*) as count FROM violations v";
        $params = [];
        $types = "";
        
        if ($studentId) {
            $query .= " WHERE BINARY v.student_id = BINARY ?";
            $params[] = $studentId;
            $types = "s";
        }
        
        if (!empty($params)) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['count'];
        } else {
            $result = $this->conn->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                return (int)$row['count'];
            }
        }
        return 0;
    }

    /**
     * Count unique violators
     */
    public function countViolators($studentId = null) {
        if ($studentId) {
            return $this->countViolations($studentId) > 0 ? 1 : 0;
        }
        
        $query = "SELECT COUNT(DISTINCT student_id) as count FROM violations WHERE student_id IS NOT NULL AND student_id != ''";
        $result = $this->conn->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            return (int)$row['count'];
        }
        return 0;
    }

    /**
     * Count penalties (disciplinary actions)
     */
    public function countPenalties($studentId = null) {
        $query = "SELECT COUNT(*) as count FROM violations v WHERE v.status = 'disciplinary'";
        $params = [];
        $types = "";
        
        if ($studentId) {
            $query .= " AND BINARY v.student_id = BINARY ?";
            $params[] = $studentId;
            $types = "s";
        }
        
        if (!empty($params)) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['count'];
        } else {
            $result = $this->conn->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                return (int)$row['count'];
            }
        }
        return 0;
    }

    /**
     * Get recent violations
     */
    public function getRecent($limit = 10, $studentId = null) {
        $query = "
            SELECT v.id,
                   v.case_id,
                   v.student_id,
                   vt.name as violation_type,
                   vl.name as violation_level,
                   v.violation_date,
                   v.violation_time,
                   v.status,
                   v.location,
                   v.reported_by,
                   v.notes,
                   v.created_at,
                   v.updated_at,
                   s.first_name, 
                   s.last_name, 
                   s.avatar,
                   s.department
            FROM violations v
            LEFT JOIN students s ON BINARY v.student_id = BINARY s.student_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.id
            LEFT JOIN violation_levels vl ON v.violation_level_id = vl.id
        ";
        
        $params = [];
        $types = "";
        
        if ($studentId) {
            $query .= " WHERE BINARY v.student_id = BINARY ?";
            $params[] = $studentId;
            $types = "s";
        }
        
        $query .= " ORDER BY v.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            // Normalize avatar
            $firstName = $row['first_name'] ?? '';
            $middleName = ''; // Not in query but needed for normalization
            $lastName = $row['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
            
            $avatar = $row['avatar'] ?? '';
            if (empty($avatar) || trim($avatar) === '') {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=ffd700&color=333&size=40';
            } else {
                if (!filter_var($avatar, FILTER_VALIDATE_URL) && strpos($avatar, 'data:') !== 0) {
                    if (strpos($avatar, 'app/assets/img/students/') === false && strpos($avatar, 'assets/img/students/') === false) {
                         if (strpos($avatar, '../app/assets/img/students/') === 0 || strpos($avatar, '../assets/img/students/') === 0) {
                             $avatar = 'app/' . ltrim(substr($avatar, 3), '/');
                             if (strpos($avatar, 'app/assets/') === false) {
                                 $avatar = str_replace('assets/', 'app/assets/', $avatar);
                             }
                         } else {
                             $avatar = 'app/assets/img/students/' . basename($avatar);
                         }
                    } elseif (strpos($avatar, 'assets/img/students/') !== false && strpos($avatar, 'app/assets/') === false) {
                        $avatar = str_replace('assets/', 'app/assets/', $avatar);
                    }
                }
            }
            $row['avatar'] = $avatar;
            
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }

    /**
     * Get top violators
     */
    public function getTopViolators($limit = 5, $studentId = null) {
        $query = "
            SELECT 
                v.student_id,
                s.first_name,
                s.last_name,
                s.avatar,
                COUNT(*) as violation_count
            FROM violations v
            LEFT JOIN students s ON BINARY v.student_id = BINARY s.student_id
        ";
        
        $params = [];
        $types = "";
        
        if ($studentId) {
            $query .= " WHERE BINARY v.student_id = BINARY ?";
            $params[] = $studentId;
            $types = "s";
        }
        
        $query .= " GROUP BY v.student_id, s.first_name, s.last_name, s.avatar ORDER BY violation_count DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }

    /**
     * Create a slip request
     */
    public function createSlipRequest($violationId, $studentIdCode, $userId) {
        $sql = "INSERT INTO slip_requests (violation_id, student_id_code, requested_by_user_id, status, request_date) 
                VALUES (?, ?, ?, 'pending', NOW())";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("isi", $violationId, $studentIdCode, $userId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    /**
     * Get all slip requests for a specific student
     */
    public function getStudentSlipRequests($studentIdCode) {
        $sql = "SELECT sr.id, sr.violation_id, sr.status, sr.request_date, sr.processed_date 
                FROM slip_requests sr 
                WHERE BINARY TRIM(sr.student_id_code) = BINARY TRIM(?) 
                ORDER BY sr.request_date DESC LIMIT 10";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $studentIdCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
        }
        $stmt->close();
        return $requests;
    }

    /**
     * Get slip request status
     */
    public function getSlipRequestStatus($violationId, $studentIdCode) {
        $sql = "SELECT status FROM slip_requests WHERE violation_id = ? AND BINARY TRIM(student_id_code) = BINARY TRIM(?) ORDER BY request_date DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $violationId, $studentIdCode);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? $row['status'] : null;
    }

    /**
     * Get all slip requests (Admin)
     */
    public function getSlipRequests() {
        $query = "SELECT sr.*, s.first_name, s.last_name, s.student_id, u.full_name as requested_by_name FROM slip_requests sr 
                  LEFT JOIN students s ON BINARY TRIM(sr.student_id_code) = BINARY TRIM(s.student_id)
                  LEFT JOIN users u ON sr.requested_by_user_id = u.id
                  ORDER BY sr.request_date DESC";
        $result = $this->conn->query($query);
        $requests = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
        }
        return $requests;
    }

    /**
     * Update slip request status
     */
    public function updateSlipRequestStatus($requestId, $status) {
        $sql = "UPDATE slip_requests SET status = ?, processed_date = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("si", $status, $requestId);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }

    /**
     * Get approved slip request
     */
    public function getApprovedSlipRequest($violationId, $studentIdCode) {
        $sql = "SELECT id FROM slip_requests WHERE violation_id = ? AND BINARY TRIM(student_id_code) = BINARY TRIM(?) AND status = 'approved'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $violationId, $studentIdCode);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row;
    }
}

