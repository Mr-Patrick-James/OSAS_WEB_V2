<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/DepartmentModel.php';

class DepartmentController extends Controller {
    private $model;

    public function __construct() {
        ob_start();
        header('Content-Type: application/json');
        @session_start();
        $this->model = new DepartmentModel();
    }

    public function index() {
        $filter = $this->getGet('filter', 'all');
        $search = $this->getGet('search', '');
        $page = intval($this->getGet('page', 1));
        $limit = intval($this->getGet('limit', 10));
        
        try {
            $departments = $this->model->getAllWithFilters($filter, $search, $page, $limit);
            $totalCount = $this->model->getCountWithFilters($filter, $search);
            
            $this->success('Departments retrieved successfully', [
                'departments' => $departments,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ]);
        } catch (Exception $e) {
            $this->error('Failed to retrieve departments: ' . $e->getMessage());
        }
    }

    public function dropdown() {
        try {
            $departments = $this->model->getForDropdown();
            $this->success('Departments retrieved successfully', $departments);
        } catch (Exception $e) {
            $this->error('Failed to retrieve departments: ' . $e->getMessage());
        }
    }

    public function stats() {
        try {
            $stats = $this->model->getStats();
            $this->success('Statistics retrieved successfully', $stats);
        } catch (Exception $e) {
            $this->error('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $name = $this->sanitize($this->getPost('deptName', ''));
        $code = $this->sanitize($this->getPost('deptCode', ''));
        $hod = $this->sanitize($this->getPost('hodName', ''));
        $description = $this->sanitize($this->getPost('deptDescription', ''));

        if (empty($name) || empty($code)) {
            $this->error('Department name and code are required.');
        }

        try {
            $data = [
                'department_name' => $name,
                'department_code' => $code,
                'head_of_department' => $hod,
                'description' => $description,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->model->create($data);
            $this->success('Department added successfully!', ['id' => $id]);
        } catch (Exception $e) {
            $this->error('Failed to add department: ' . $e->getMessage());
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $id = intval($this->getPost('deptId', $this->getGet('id', 0)));
        if ($id === 0) {
            $this->error('Invalid department ID');
        }

        $name = $this->sanitize($this->getPost('deptName', ''));
        $code = $this->sanitize($this->getPost('deptCode', ''));
        $hod = $this->sanitize($this->getPost('hodName', ''));
        $description = $this->sanitize($this->getPost('deptDescription', ''));
        $status = $this->sanitize($this->getPost('deptStatus', 'active'));

        if (empty($name) || empty($code)) {
            $this->error('Department name and code are required.');
        }

        try {
            $data = [
                'department_name' => $name,
                'department_code' => $code,
                'head_of_department' => $hod,
                'description' => $description,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->model->update($id, $data);
            $this->success('Department updated successfully!');
        } catch (Exception $e) {
            $this->error('Failed to update department: ' . $e->getMessage());
        }
    }

    public function delete() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid department ID');
        }

        try {
            // Check if department is already archived
            $dept = $this->model->getById($id);
            if ($dept && $dept['status'] === 'archived') {
                // If archived, perform permanent delete
                $this->model->delete($id);
                $this->success('Department permanently deleted!');
            } else {
                // Otherwise just archive it
                $this->model->archive($id);
                $this->success('Department archived successfully!');
            }
        } catch (Exception $e) {
            $this->error('Operation failed: ' . $e->getMessage());
        }
    }

    public function restore() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid department ID');
        }

        try {
            $this->model->restore($id);
            $this->success('Department restored successfully!');
        } catch (Exception $e) {
            $this->error('Failed to restore department: ' . $e->getMessage());
        }
    }
}
