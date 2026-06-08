<?php

require_once __DIR__ . '/../core/Model.php';

class SlipRequestModel extends Model
{
    public function ensureTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS slip_requests (
                id INT NOT NULL AUTO_INCREMENT,
                violation_id INT NOT NULL,
                student_id VARCHAR(50) NOT NULL,
                status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                approved_by INT NULL,
                approved_at DATETIME NULL,
                processed_date DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_violation_student (violation_id, student_id),
                KEY idx_status (status),
                KEY idx_violation (violation_id),
                KEY idx_student (student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->conn->query($sql);

        // Check if processed_date column exists, if not, add it
        $checkColumnSql = "SHOW COLUMNS FROM slip_requests LIKE 'processed_date'";
        $result = $this->conn->query($checkColumnSql);
        if ($result && $result->num_rows == 0) {
            $addColumnSql = "ALTER TABLE slip_requests ADD COLUMN processed_date DATETIME NULL";
            $this->conn->query($addColumnSql);
        }
    }

    public function getByViolationAndStudent($violationId, $studentId)
    {
        $this->ensureTable();
        $rows = $this->query(
            "SELECT * FROM slip_requests WHERE violation_id = ? AND student_id = ? LIMIT 1",
            [(int)$violationId, (string)$studentId]
        );
        return $rows[0] ?? null;
    }

    public function createOrGetRequest($violationId, $studentId)
    {
        $this->ensureTable();

        $existing = $this->getByViolationAndStudent($violationId, $studentId);
        if ($existing) return $existing;

        $stmt = $this->conn->prepare(
            "INSERT INTO slip_requests (violation_id, student_id, status) VALUES (?, ?, 'pending')"
        );
        $vid = (int)$violationId;
        $sid = (string)$studentId;
        $stmt->bind_param("is", $vid, $sid);
        $stmt->execute();
        $stmt->close();

        return $this->getByViolationAndStudent($violationId, $studentId);
    }

    public function approveByViolation($violationId, $adminUserId)
    {
        $this->ensureTable();
        $stmt = $this->conn->prepare(
            "UPDATE slip_requests
             SET status = 'approved', approved_by = ?, approved_at = NOW()
             WHERE violation_id = ? AND status = 'pending'"
        );
        $adminId = (int)$adminUserId;
        $vid = (int)$violationId;
        $stmt->bind_param("ii", $adminId, $vid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function denyByViolation($violationId, $adminUserId)
    {
        $this->ensureTable();
        $stmt = $this->conn->prepare(
            "UPDATE slip_requests
             SET status = 'denied', approved_by = ?, approved_at = NOW()
             WHERE violation_id = ? AND status = 'pending'"
        );
        $adminId = (int)$adminUserId;
        $vid = (int)$violationId;
        $stmt->bind_param("ii", $adminId, $vid);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function isApproved($violationId, $studentId)
    {
        $row = $this->getByViolationAndStudent($violationId, $studentId);
        return $row && $row['status'] === 'approved';
    }
}

