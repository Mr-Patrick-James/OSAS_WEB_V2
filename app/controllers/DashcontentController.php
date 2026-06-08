<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/DashcontentModel.php';

class DashcontentController extends Controller {
    private $model;

    public function __construct() {
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        
        @session_start();
        
        try {
            $this->model = new DashcontentModel();
        } catch (Throwable $e) {
            error_log("DashcontentController constructor error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->model = null;
        }
    }

    public function index() {
        try {
            $contentType = $this->getGet('type', null);
            $targetAudience = $this->getGet('audience', null);
            $status = $this->getGet('status', 'active');
            
            if (!$this->model) {
                $this->error('Model not initialized. Check database connection.', '', 500);
                return;
            }
            
            $dashcontents = $this->model->getFiltered($contentType, $targetAudience, $status);
            $this->success('Dashcontents retrieved successfully', $dashcontents);
        } catch (Throwable $e) {
            error_log("DashcontentController::index error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->error('Failed to retrieve dashcontents: ' . $e->getMessage(), '', 500);
        }
    }

    public function getActive() {
        try {
            $contentType = $this->getGet('type', null);
            $targetAudience = $this->getGet('audience', null);
            $limit = $this->getGet('limit', null);
            
            if (!$this->model) {
                $this->error('Model not initialized. Check database connection.', '', 500);
                return;
            }
            
            $dashcontents = $this->model->getActive($contentType, $targetAudience, $limit);
            $this->success('Active dashcontents retrieved successfully', $dashcontents);
        } catch (Throwable $e) {
            error_log("DashcontentController::getActive error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->error('Failed to retrieve active dashcontents: ' . $e->getMessage(), '', 500);
        }
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $contentType = $this->sanitize($this->getPost('content_type', 'tip'));
        $title = $this->sanitize($this->getPost('title', ''));
        $content = $this->sanitize($this->getPost('content', ''));
        $icon = $this->sanitize($this->getPost('icon', ''));
        $displayOrder = intval($this->getPost('display_order', 0));
        $targetAudience = $this->sanitize($this->getPost('target_audience', 'both'));
        $status = $this->sanitize($this->getPost('status', 'active'));

        if (empty($title) || empty($content)) {
            $this->error('Title and content are required.');
        }

        if (!in_array($contentType, ['tip', 'guideline', 'statistic', 'announcement', 'widget'])) {
            $contentType = 'tip';
        }

        if (!in_array($targetAudience, ['admin', 'user', 'both'])) {
            $targetAudience = 'both';
        }

        if (!in_array($status, ['active', 'inactive'])) {
            $status = 'active';
        }

        try {
            $data = [
                'content_type' => $contentType,
                'title' => $title,
                'content' => $content,
                'icon' => $icon,
                'display_order' => $displayOrder,
                'target_audience' => $targetAudience,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->model->create($data);
            $this->success('Dashcontent created successfully!', ['id' => $id]);
        } catch (Exception $e) {
            $this->error('Failed to create dashcontent: ' . $e->getMessage());
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $id = intval($this->getPost('id', $this->getGet('id', 0)));
        if ($id === 0) {
            $this->error('Invalid dashcontent ID');
        }

        $contentType = $this->sanitize($this->getPost('content_type', 'tip'));
        $title = $this->sanitize($this->getPost('title', ''));
        $content = $this->sanitize($this->getPost('content', ''));
        $icon = $this->sanitize($this->getPost('icon', ''));
        $displayOrder = intval($this->getPost('display_order', 0));
        $targetAudience = $this->sanitize($this->getPost('target_audience', 'both'));
        $status = $this->sanitize($this->getPost('status', 'active'));

        if (empty($title) || empty($content)) {
            $this->error('Title and content are required.');
        }

        try {
            $data = [
                'content_type' => $contentType,
                'title' => $title,
                'content' => $content,
                'icon' => $icon,
                'display_order' => $displayOrder,
                'target_audience' => $targetAudience,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->model->update($id, $data);
            $this->success('Dashcontent updated successfully!');
        } catch (Exception $e) {
            $this->error('Failed to update dashcontent: ' . $e->getMessage());
        }
    }

    public function delete() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid dashcontent ID');
        }

        try {
            $this->model->delete($id);
            $this->success('Dashcontent deleted successfully!');
        } catch (Exception $e) {
            $this->error('Failed to delete dashcontent: ' . $e->getMessage());
        }
    }
}

