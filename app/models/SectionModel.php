<?php
require_once __DIR__ . '/../core/Model.php';

class SectionModel extends Model {
    protected $table = 'sections';
    protected $primaryKey = 'id';

    /**
     * Get sections by department
     */
    public function getByDepartment($departmentCode) {
        $query = "SELECT s.*, d.department_name, d.department_code 
                  FROM sections s
                  LEFT JOIN departments d ON s.department_id = d.id
                  WHERE d.department_code = ? AND s.status = 'active'
                  ORDER BY s.section_code ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $departmentCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = [
                'id' => (int)$row['id'],
                'section_code' => $row['section_code'] ?? '',
                'section_name' => $row['section_name'] ?? ''
            ];
        }
        
        $stmt->close();
        return $sections;
    }

    /**
     * Count active sections
     */
    public function countActive() {
        $query = "SELECT COUNT(*) as count FROM sections WHERE status = 'active' OR status IS NULL";
        $result = $this->conn->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            return (int)$row['count'];
        }
        return 0;
    }


    /**
     * Get all sections with filters
     */
    public function getAllWithFilters($filter = 'all', $search = '', $page = null, $limit = null) {
        $query = "SELECT s.*, 
                         d.department_name, 
                         d.department_code,
                         COUNT(DISTINCT st.id) as student_count
                  FROM sections s
                  LEFT JOIN departments d ON s.department_id = d.id
                  LEFT JOIN students st ON s.id = st.section_id AND st.status != 'archived'
                  WHERE 1=1";
        $params = [];
        $types = "";

        if ($filter === 'active') {
            $query .= " AND s.status = 'active'";
        } elseif ($filter === 'archived') {
            $query .= " AND s.status = 'archived'";
        }

        if (!empty($search)) {
            $query .= " AND (s.section_name LIKE ? OR s.section_code LIKE ? OR d.department_name LIKE ?)";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
            $types = "sss";
        }

        $query .= " GROUP BY s.id ORDER BY s.section_code ASC";

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

        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = [
                'id' => (int)$row['id'],
                'section_id' => (int)$row['id'],
                'name' => $row['section_name'] ?? '',
                'code' => $row['section_code'] ?? '',
                'department' => $row['department_name'] ?? 'N/A',
                'department_id' => (int)($row['department_id'] ?? 0),
                'academic_year' => $row['academic_year'] ?? '',
                'student_count' => (int)($row['student_count'] ?? 0),
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'status' => $row['status'] ?? 'active'
            ];
        }

        $stmt->close();
        return $sections;
    }

    public function getCountWithFilters($filter = 'all', $search = '') {
        $query = "SELECT COUNT(s.id) as total
                  FROM sections s
                  LEFT JOIN departments d ON s.department_id = d.id
                  WHERE 1=1";
        $params = [];
        $types = "";

        if ($filter === 'active') {
            $query .= " AND s.status = 'active'";
        } elseif ($filter === 'archived') {
            $query .= " AND s.status = 'archived'";
        }

        if (!empty($search)) {
            $query .= " AND (s.section_name LIKE ? OR s.section_code LIKE ? OR d.department_name LIKE ?)";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
            $types = "sss";
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
     * Check if section code exists among active sections
     */
    public function codeExists($code, $excludeId = null) {
        // Only check for sections that are NOT archived
        $query = "SELECT id FROM sections WHERE section_code = ? AND status != 'archived'";
        if ($excludeId) {
            $query .= " AND id != ?";
            $result = $this->query($query, [$code, $excludeId]);
        } else {
            $result = $this->query($query, [$code]);
        }
        return count($result) > 0;
    }

    /**
     * Archive section
     */
    public function archive($id) {
        return $this->update($id, ['status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Restore section
     */
    public function restore($id) {
        return $this->update($id, ['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Permanently delete section
     */
    public function delete($id) {
        $query = "DELETE FROM sections WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Get statistics
     */
    public function getStats() {
        $stats = [];
        
        // Total sections
        $totalResult = $this->query("SELECT COUNT(*) as count FROM sections");
        $stats['total'] = (int)($totalResult[0]['count'] ?? 0);
        
        // Active sections
        $activeResult = $this->query("SELECT COUNT(*) as count FROM sections WHERE status = 'active'");
        $stats['active'] = (int)($activeResult[0]['count'] ?? 0);
        
        // Archived sections
        $archivedResult = $this->query("SELECT COUNT(*) as count FROM sections WHERE status = 'archived'");
        $stats['archived'] = (int)($archivedResult[0]['count'] ?? 0);
        
        // Sections with students
        $withStudentsResult = $this->query("
            SELECT COUNT(DISTINCT s.id) as count 
            FROM sections s
            INNER JOIN students st ON s.id = st.section_id 
            WHERE st.status != 'archived' AND s.status = 'active'
        ");
        $stats['with_students'] = (int)($withStudentsResult[0]['count'] ?? 0);
        
        // Total students in sections
        $totalStudentsResult = $this->query("
            SELECT COUNT(*) as count 
            FROM students 
            WHERE section_id IS NOT NULL AND status != 'archived'
        ");
        $stats['total_students'] = (int)($totalStudentsResult[0]['count'] ?? 0);
        
        return $stats;
    }
}

