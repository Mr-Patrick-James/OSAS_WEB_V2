<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/SettingsModel.php';

class SettingsController extends Controller {
    private $model;

    public function __construct() {
        header('Content-Type: application/json');
        @session_start();
        $this->model = new SettingsModel();
    }

    public function index() {
        try {
            $category = $this->getGet('category', null);
            
            if ($category) {
                $settings = $this->model->getSettingsByCategory($category);
            } else {
                $settings = $this->model->getAllSettings();
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Settings retrieved successfully',
                'settings' => $settings,
                'data' => $settings,
                'count' => count($settings)
            ];
            
            $this->json($response);
        } catch (Exception $e) {
            error_log("SettingsController Error: " . $e->getMessage());
            $this->error('Failed to retrieve settings: ' . $e->getMessage());
        }
    }
    
    public function getGrouped() {
        try {
            $settings = $this->model->getSettingsGrouped();
            
            $response = [
                'status' => 'success',
                'message' => 'Settings retrieved successfully',
                'settings' => $settings,
                'data' => $settings
            ];
            
            $this->json($response);
        } catch (Exception $e) {
            error_log("SettingsController Error: " . $e->getMessage());
            $this->error('Failed to retrieve settings: ' . $e->getMessage());
        }
    }
    
    public function getPublic() {
        try {
            $settings = $this->model->getPublicSettings();
            
            $response = [
                'status' => 'success',
                'message' => 'Public settings retrieved successfully',
                'settings' => $settings,
                'data' => $settings
            ];
            
            $this->json($response);
        } catch (Exception $e) {
            error_log("SettingsController Error: " . $e->getMessage());
            $this->error('Failed to retrieve public settings: ' . $e->getMessage());
        }
    }
    
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->error('Invalid request method');
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            // Single setting update
            if (isset($input['key']) && isset($input['value'])) {
                $this->model->updateSetting($input['key'], $input['value']);
                
                $response = [
                    'status' => 'success',
                    'message' => 'Setting updated successfully',
                    'key' => $input['key'],
                    'value' => $input['value']
                ];
                
                $this->json($response);
                return;
            }
            
            // Bulk update
            if (isset($input['settings']) && is_array($input['settings'])) {
                $result = $this->model->updateSettings($input['settings']);
                
                $response = [
                    'status' => 'success',
                    'message' => "Updated {$result['updated']} setting(s)",
                    'updated' => $result['updated'],
                    'errors' => $result['errors']
                ];
                
                $this->json($response);
                return;
            }
            
            $this->error('Invalid request data');
        } catch (Exception $e) {
            error_log("SettingsController Error: " . $e->getMessage());
            $this->error('Failed to update settings: ' . $e->getMessage());
        }
    }
    
    public function get() {
        try {
            $key = $this->getGet('key', '');
            
            if (empty($key)) {
                $this->error('Setting key is required');
            }
            
            $setting = $this->model->getSetting($key);
            
            if (!$setting) {
                $this->error('Setting not found');
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Setting retrieved successfully',
                'setting' => $setting,
                'data' => $setting
            ];
            
            $this->json($response);
        } catch (Exception $e) {
            error_log("SettingsController Error: " . $e->getMessage());
            $this->error('Failed to retrieve setting: ' . $e->getMessage());
        }
    }
}

