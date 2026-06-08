<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/SectionModel.php';

class SectionController extends Controller {
    private $model;

    public function __construct() {
        ob_start();
        header('Content-Type: application/json');
        @session_start();
        $this->model = new SectionModel();
    }

    public function index() {
        $filter = $this->getGet('filter', 'all');
        $search = $this->getGet('search', '');
        $page = intval($this->getGet('page', 1));
        $limit = intval($this->getGet('limit', 10));
        
        try {
            $sections = $this->model->getAllWithFilters($filter, $search, $page, $limit);
            $totalCount = $this->model->getCountWithFilters($filter, $search);

            $this->success('Sections retrieved successfully', [
                'sections' => $sections,
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / max(1, $limit))
            ]);
        } catch (Exception $e) {
            $this->error('Failed to retrieve sections: ' . $e->getMessage());
        }
    }

    public function getByDepartment() {
        $deptCode = $this->getGet('department_code', '');
        
        if (empty($deptCode)) {
            $this->error('Department code is required');
        }

        try {
            $sections = $this->model->getByDepartment($deptCode);
            $this->success('Sections retrieved successfully', $sections);
        } catch (Exception $e) {
            $this->error('Failed to retrieve sections: ' . $e->getMessage());
        }
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $name = $this->sanitize($this->getPost('sectionName', ''));
        $code = $this->sanitize($this->getPost('sectionCode', ''));
        $deptId = intval($this->getPost('departmentId', $this->getPost('sectionDepartment', 0)));
        $academicYear = $this->sanitize($this->getPost('academicYear', ''));
        $status = $this->sanitize($this->getPost('sectionStatus', 'active'));

        if (empty($name) || empty($code) || $deptId === 0) {
            $this->error('Section name, code, and department are required.');
        }

        try {
            $data = [
                'section_name' => $name,
                'section_code' => $code,
                'department_id' => $deptId,
                'academic_year' => $academicYear,
                'status' => !empty($status) ? $status : 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $id = $this->model->create($data);
            $this->success('Section added successfully!', ['id' => $id]);
        } catch (Exception $e) {
            $this->error('Failed to add section: ' . $e->getMessage());
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->error('Invalid request method');
        }

        $id = intval($this->getPost('sectionId', $this->getGet('id', 0)));
        if ($id === 0) {
            $this->error('Invalid section ID');
        }

        $name = $this->sanitize($this->getPost('sectionName', ''));
        $code = $this->sanitize($this->getPost('sectionCode', ''));
        $deptId = intval($this->getPost('departmentId', $this->getPost('sectionDepartment', 0)));
        $academicYear = $this->sanitize($this->getPost('academicYear', ''));
        $status = $this->sanitize($this->getPost('sectionStatus', 'active'));

        if (empty($name) || empty($code) || $deptId === 0) {
            $this->error('Section name, code, and department are required.');
        }

        try {
            $data = [
                'section_name' => $name,
                'section_code' => $code,
                'department_id' => $deptId,
                'academic_year' => $academicYear,
                'status' => !empty($status) ? $status : 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->model->update($id, $data);
            $this->success('Section updated successfully!');
        } catch (Exception $e) {
            $this->error('Failed to update section: ' . $e->getMessage());
        }
    }

    public function delete() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid section ID');
        }

        try {
            $this->model->archive($id);
            $this->success('Section archived successfully!');
        } catch (Exception $e) {
            $this->error('Failed to archive section: ' . $e->getMessage());
        }
    }

    public function destroy() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid section ID');
        }

        try {
            $this->model->delete($id);
            $this->success('Section permanently deleted!');
        } catch (Exception $e) {
            $this->error('Failed to delete section: ' . $e->getMessage());
        }
    }

    public function restore() {
        $id = intval($this->getGet('id', $this->getPost('id', 0)));
        
        if ($id === 0) {
            $this->error('Invalid section ID');
        }

        try {
            $this->model->restore($id);
            $this->success('Section restored successfully!');
        } catch (Exception $e) {
            $this->error('Failed to restore section: ' . $e->getMessage());
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
}
