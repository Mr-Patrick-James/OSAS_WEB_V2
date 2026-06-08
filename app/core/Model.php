<?php
/**
 * Base Model Class
 * Handles database operations
 */
class Model {
    protected $conn;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct() {
        // Get the absolute path to db_connect.php
        $dbConfigPath = realpath(__DIR__ . '/../../config/db_connect.php');
        
        if (!$dbConfigPath || !file_exists($dbConfigPath)) {
            throw new Exception('Database configuration file not found. Tried: ' . __DIR__ . '/../../config/db_connect.php');
        }
        
        // Include the config file - this will set $conn variable
        include $dbConfigPath;
        
        // Check if $conn was created
        if (!isset($conn)) {
            throw new Exception('Database connection variable $conn not found after including db_connect.php');
        }
        
        // Assign to instance variable
        $this->conn = $conn;
        
        // Check if connection is valid
        if (!$this->conn) {
            throw new Exception('Database connection object is null');
        }
        
        // Check for connection errors
        if ($this->conn->connect_error) {
            throw new Exception('Database connection failed: ' . $this->conn->connect_error);
        }
        
        // Set charset (if not already set in db_connect.php)
        if (!$this->conn->set_charset("utf8mb4")) {
            error_log("Warning: Failed to set charset to utf8mb4: " . $this->conn->error);
        }
    }

    /**
     * Get all records
     */
    public function getAll($conditions = [], $orderBy = null, $limit = null) {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        $types = "";

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $field => $value) {
                $where[] = "$field = ?";
                $params[] = $value;
                $types .= is_int($value) ? "i" : "s";
            }
            $query .= " WHERE " . implode(" AND ", $where);
        }

        if ($orderBy) {
            $query .= " ORDER BY $orderBy";
        }

        if ($limit) {
            $query .= " LIMIT $limit";
        }

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
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
     * Get single record by ID
     */
    public function getById($id) {
        $query = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * Create new record
     */
    public function create($data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $values = array_values($data);
        
        $types = "";
        foreach ($values as $value) {
            $types .= is_int($value) ? "i" : (is_float($value) ? "d" : "s");
        }

        $query = "INSERT INTO {$this->table} (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $id = $this->conn->insert_id;
            $stmt->close();
            return $id;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to create record: " . $error);
        }
    }

    /**
     * Update record
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        
        $types = "";
        foreach ($values as $value) {
            $types .= is_int($value) ? "i" : (is_float($value) ? "d" : "s");
        }
        $types .= "i"; // for the ID parameter
        
        $values[] = $id;
        
        $query = "UPDATE {$this->table} SET " . implode(", ", $fields) . " WHERE {$this->primaryKey} = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to update record: " . $error);
        }
    }

    /**
     * Delete record
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to delete record: " . $error);
        }
    }

    /**
     * Execute custom query
     */
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            $error = $this->conn->error;
            error_log("Model::query() prepare failed: " . $error);
            error_log("SQL: " . $sql);
            throw new Exception("Database query preparation failed: " . $error);
        }
        
        if (!empty($params)) {
            $types = "";
            foreach ($params as $param) {
                $types .= is_int($param) ? "i" : (is_float($param) ? "d" : "s");
            }
            if (!$stmt->bind_param($types, ...$params)) {
                $error = $stmt->error;
                $stmt->close();
                error_log("Model::query() bind_param failed: " . $error);
                throw new Exception("Database query parameter binding failed: " . $error);
            }
        }
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            error_log("Model::query() execute failed: " . $error);
            throw new Exception("Database query execution failed: " . $error);
        }
        
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }

    /**
     * Get connection
     */
    public function getConnection() {
        return $this->conn;
    }
}

