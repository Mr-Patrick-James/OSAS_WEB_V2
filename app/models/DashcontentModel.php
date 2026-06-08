<?php
require_once __DIR__ . '/../core/Model.php';

class DashcontentModel extends Model {
    protected $table = 'dashcontents';
    protected $primaryKey = 'id';

    /**
     * Get all dashcontents with filters
     */
    public function getFiltered($contentType = null, $targetAudience = null, $status = 'active') {
        // Check if table exists
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            // Table doesn't exist, return empty array
            return [];
        }

        $query = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        $types = "";

        // Filter by status
        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
            $types .= "s";
        }

        // Filter by content type
        if ($contentType) {
            $query .= " AND content_type = ?";
            $params[] = $contentType;
            $types .= "s";
        }

        // Filter by target audience
        if ($targetAudience) {
            $query .= " AND (target_audience = ? OR target_audience = 'both')";
            $params[] = $targetAudience;
            $types .= "s";
        }

        $query .= " ORDER BY display_order ASC, created_at DESC";

        try {
            if (!empty($params)) {
                $stmt = $this->conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                    $stmt->close();
                    return $data;
                } else {
                    error_log("DashcontentModel::getFiltered - Failed to prepare statement: " . $this->conn->error);
                    return [];
                }
            } else {
                $result = $this->conn->query($query);
                if ($result === false) {
                    error_log("DashcontentModel::getFiltered - Query failed: " . $this->conn->error);
                    return [];
                }
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                return $data;
            }
        } catch (Throwable $e) {
            error_log("DashcontentModel::getFiltered error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Get active dashcontents for display
     */
    public function getActive($contentType = null, $targetAudience = null, $limit = null) {
        // Check if table exists
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            // Table doesn't exist, return empty array
            return [];
        }

        $query = "SELECT * FROM {$this->table} 
                  WHERE status = 'active'";
        
        $params = [];
        $types = "";

        // Filter by content type
        if ($contentType) {
            $query .= " AND content_type = ?";
            $params[] = $contentType;
            $types .= "s";
        }

        // Filter by target audience
        if ($targetAudience) {
            $query .= " AND (target_audience = ? OR target_audience = 'both')";
            $params[] = $targetAudience;
            $types .= "s";
        }

        $query .= " ORDER BY display_order ASC, created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }

        try {
            if (!empty($params)) {
                $stmt = $this->conn->prepare($query);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                    $stmt->close();
                    return $data;
                } else {
                    error_log("DashcontentModel::getActive - Failed to prepare statement: " . $this->conn->error);
                    return [];
                }
            } else {
                $result = $this->conn->query($query);
                $data = [];
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
                return $data;
            }
        } catch (Exception $e) {
            error_log("DashcontentModel::getActive error: " . $e->getMessage());
            return [];
        }
    }
}

