<?php
require_once __DIR__ . '/../core/Model.php';

class AnnouncementModel extends Model {
    protected $table = 'announcements';
    protected $primaryKey = 'id';

    /**
     * Build WHERE clause for admin list (filter + search).
     */
    private function buildListWhere($filter = 'all', $search = '', &$params = [], &$types = '') {
        $where = "deleted_at IS NULL";
        if ($filter === 'active') {
            $where .= " AND status = 'active'";
        } elseif ($filter === 'archived') {
            $where .= " AND status = 'archived'";
        }
        if ($search !== '') {
            $where .= " AND (title LIKE ? OR message LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $types .= 'ss';
        }
        return $where;
    }

    /**
     * Count announcements matching filter/search.
     */
    public function countFiltered($filter = 'all', $search = '') {
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return 0;
        }

        $params = [];
        $types = '';
        $where = $this->buildListWhere($filter, $search, $params, $types);
        $query = "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE {$where}";

        try {
            if (!empty($params)) {
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    return 0;
                }
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return (int) ($row['cnt'] ?? 0);
            }
            $result = $this->conn->query($query);
            if ($result === false) {
                return 0;
            }
            $row = $result->fetch_assoc();
            return (int) ($row['cnt'] ?? 0);
        } catch (Throwable $e) {
            error_log('AnnouncementModel::countFiltered: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Paginated list for admin table.
     */
    public function getPaginated($filter = 'all', $search = '', $page = 1, $limit = 10) {
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return [];
        }

        $page = max(1, (int) $page);
        $limit = max(1, min(100, (int) $limit));
        $offset = ($page - 1) * $limit;

        $params = [];
        $types = '';
        $where = $this->buildListWhere($filter, $search, $params, $types);
        $query = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log('AnnouncementModel::getPaginated prepare: ' . $this->conn->error);
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            return $data;
        } catch (Throwable $e) {
            error_log('AnnouncementModel::getPaginated: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all announcements with filters (no pagination — legacy / exports).
     */
    public function getFiltered($filter = 'all', $search = '') {
        return $this->getPaginated($filter, $search, 1, 10000);
    }

    /**
     * Get one announcement by id (admin edit).
     */
    public function getById($id) {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            return null;
        }
        $query = "SELECT * FROM {$this->table} WHERE id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Get active announcements (for display)
     */
    public function getActive($limit = null) {
        // Check if table exists
        $tableCheck = @$this->conn->query("SHOW TABLES LIKE '{$this->table}'");
        if ($tableCheck === false || $tableCheck->num_rows === 0) {
            // Table doesn't exist, return empty array
            return [];
        }

        // Sort primarily by date to ensure the LATEST is always at the top
        $query = "SELECT * FROM {$this->table} 
                  WHERE status = 'active' AND deleted_at IS NULL 
                  ORDER BY created_at DESC, 
                    CASE type 
                        WHEN 'urgent' THEN 1 
                        WHEN 'warning' THEN 2 
                        ELSE 3 
                    END";
        
        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }

        try {
            $result = $this->conn->query($query);
            $data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
            return $data;
        } catch (Exception $e) {
            error_log("AnnouncementModel::getActive error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Archive announcement
     */
    public function archive($id) {
        $query = "UPDATE {$this->table} SET status = 'archived', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    /**
     * Restore archived announcement
     */
    public function restore($id) {
        $query = "UPDATE {$this->table} SET status = 'active', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    /**
     * Soft delete announcement
     */
    public function softDelete($id) {
        $query = "UPDATE {$this->table} SET deleted_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}

