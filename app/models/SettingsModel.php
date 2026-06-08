<?php
require_once __DIR__ . '/../core/Model.php';

class SettingsModel extends Model {
    protected $table = 'settings';
    protected $primaryKey = 'id';
    
    /**
     * Get all settings
     */
    public function getAllSettings($category = null) {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        $types = "";
        
        if ($category) {
            $query .= " WHERE category = ?";
            $params[] = $category;
            $types .= "s";
        }
        
        $query .= " ORDER BY category, setting_key ASC";
        
        try {
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->conn->error);
            }
            
            if (!empty($params) && !empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $value = $this->parseSettingValue($row['setting_value'], $row['setting_type']);
                $settings[$row['setting_key']] = [
                    'id' => (int)$row['id'],
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'is_public' => (bool)$row['is_public'],
                    'updated_at' => $row['updated_at']
                ];
            }
            
            $stmt->close();
            return $settings;
        } catch (Exception $e) {
            error_log("SettingsModel::getAllSettings error: " . $e->getMessage());
            throw new Exception('Failed to retrieve settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Get settings by category
     */
    public function getSettingsByCategory($category) {
        return $this->getAllSettings($category);
    }
    
    /**
     * Get a single setting by key
     */
    public function getSetting($key) {
        $query = "SELECT * FROM {$this->table} WHERE setting_key = ? LIMIT 1";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $value = $this->parseSettingValue($row['setting_value'], $row['setting_type']);
                return [
                    'id' => (int)$row['id'],
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'is_public' => (bool)$row['is_public']
                ];
            }
            
            $stmt->close();
            return null;
        } catch (Exception $e) {
            error_log("SettingsModel::getSetting error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update a setting
     */
    public function updateSetting($key, $value) {
        // Get current setting to determine type
        $setting = $this->getSetting($key);
        if (!$setting) {
            throw new Exception("Setting with key '{$key}' not found");
        }
        
        // Convert value to string based on type
        $stringValue = $this->convertValueToString($value, $setting['type']);
        
        $query = "UPDATE {$this->table} SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ss", $stringValue, $key);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("SettingsModel::updateSetting error: " . $e->getMessage());
            throw new Exception('Failed to update setting: ' . $e->getMessage());
        }
    }
    
    /**
     * Update multiple settings
     */
    public function updateSettings($settings) {
        $updated = 0;
        $errors = [];
        
        foreach ($settings as $key => $value) {
            try {
                $this->updateSetting($key, $value);
                $updated++;
            } catch (Exception $e) {
                $errors[] = "Failed to update {$key}: " . $e->getMessage();
            }
        }
        
        return [
            'updated' => $updated,
            'errors' => $errors
        ];
    }
    
    /**
     * Create a new setting
     */
    public function createSetting($key, $value, $type = 'string', $category = 'general', $description = null, $isPublic = false) {
        $stringValue = $this->convertValueToString($value, $type);
        
        $query = "INSERT INTO {$this->table} (setting_key, setting_value, setting_type, category, description, is_public) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->conn->prepare($query);
            $isPublicInt = $isPublic ? 1 : 0;
            $stmt->bind_param("sssssi", $key, $stringValue, $type, $category, $description, $isPublicInt);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute failed: ' . $stmt->error);
            }
            
            $id = $this->conn->insert_id;
            $stmt->close();
            return $id;
        } catch (Exception $e) {
            error_log("SettingsModel::createSetting error: " . $e->getMessage());
            throw new Exception('Failed to create setting: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse setting value based on type
     */
    private function parseSettingValue($value, $type) {
        switch ($type) {
            case 'integer':
                return (int)$value;
            case 'boolean':
                return (bool)$value || $value === '1' || $value === 'true';
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }
    
    /**
     * Convert value to string for storage
     */
    private function convertValueToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return ($value === true || $value === '1' || $value === 'true' || $value === 1) ? '1' : '0';
            case 'integer':
                return (string)(int)$value;
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }
    
    /**
     * Get settings grouped by category
     */
    public function getSettingsGrouped() {
        $allSettings = $this->getAllSettings();
        $grouped = [];
        
        foreach ($allSettings as $setting) {
            $category = $setting['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$setting['key']] = $setting;
        }
        
        return $grouped;
    }
    
    /**
     * Get public settings (for frontend use)
     */
    public function getPublicSettings() {
        $query = "SELECT setting_key, setting_value, setting_type FROM {$this->table} WHERE is_public = 1";
        
        try {
            $result = $this->conn->query($query);
            $settings = [];
            
            while ($row = $result->fetch_assoc()) {
                $value = $this->parseSettingValue($row['setting_value'], $row['setting_type']);
                $settings[$row['setting_key']] = $value;
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("SettingsModel::getPublicSettings error: " . $e->getMessage());
            return [];
        }
    }
}

