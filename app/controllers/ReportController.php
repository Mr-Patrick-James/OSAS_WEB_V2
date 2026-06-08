<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/ReportModel.php';

class ReportController extends Controller {
    private $model;

    public function __construct() {
        header('Content-Type: application/json');
        @session_start();
        $this->model = new ReportModel();
    }

    public function index() {
        try {
            // Check if user wants to generate/refresh reports
            $generate = $this->getGet('generate', false);
            if ($generate === 'true' || $generate === '1') {
                $startDate = $this->getGet('startDate', null);
                $endDate = $this->getGet('endDate', null);
                $departments = $this->getGet('departments', null);
                $violationTypes = $this->getGet('violationTypes', null);
                
                $filters = [];
                // Clean up departments input
                $deptArray = $departments ? explode(',', $departments) : [];
                $deptArray = array_filter($deptArray, function($v) { return trim($v) !== ''; });
                
                if (!empty($deptArray)) {
                    $filters['departments'] = $deptArray;
                    // Reconstruct string for getStudentReports consistency
                    $departments = implode(',', $deptArray);
                } else {
                    $departments = 'all'; // Treat empty as all
                }

                if ($violationTypes) $filters['violationTypes'] = explode(',', $violationTypes);
                
                $result = $this->model->generateReportsFromViolations($startDate, $endDate, $filters);
                
                $downloadUrl = null;
                $format = $this->getGet('reportFormat', 'json'); // Check reportFormat from form
                
                // If format is CSV or Excel, provide download link
                if ($format === 'csv' || $format === 'excel' || $format === 'xlsx') {
                    $exportParams = $_GET;
                    $exportParams['export'] = 'true';
                    $exportParams['format'] = $format;
                    unset($exportParams['generate']);
                    // Use plural 'departments' consistently
                    if (isset($exportParams['department'])) {
                        $exportParams['departments'] = $exportParams['department'];
                    }
                    $downloadUrl = 'reports.php?' . http_build_query($exportParams);
                }
                
                // If format is PDF, DOCX or Excel, fetch data for client-side generation
                $reportsData = null;
                if ($format === 'pdf' || $format === 'docx' || $format === 'excel') {
                    $reportFilters = [
                        'department' => $departments ?? 'all',
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'status' => 'all',
                        'section' => 'all'
                    ];
                    $reportsData = $this->model->getStudentReports($reportFilters);
                }
                
                $response = [
                    'status' => 'success',
                    'message' => "Report generation complete. Found {$result['total']} students matching criteria (Generated: {$result['generated']}, Updated: {$result['updated']}).",
                    'generated' => $result['generated'],
                    'updated' => $result['updated'],
                    'total' => $result['total'],
                    'downloadUrl' => $downloadUrl,
                    'reports' => $reportsData
                ];
                
                $this->json($response);
                return;
            }
            
            // Check for export request
            $export = $this->getGet('export', false);
            if ($export === 'true') {
                $format = $this->getGet('format', 'csv');
                
                // Get filters
                $filters = [
                    'department' => $this->getGet('departments', 'all'), // Use departments (plural) from form if available, or singular
                    'section' => $this->getGet('section', 'all'),
                    'status' => $this->getGet('status', 'all'),
                    'startDate' => $this->getGet('startDate', null),
                    'endDate' => $this->getGet('endDate', null),
                    'search' => $this->getGet('search', ''),
                    'timePeriod' => $this->getGet('timePeriod', null)
                ];
                
                // Handle singular/plural mismatch for department
                if ($filters['department'] === 'all' && $this->getGet('department', 'all') !== 'all') {
                    $filters['department'] = $this->getGet('department');
                }
                
                // Handle time period filters
                if ($filters['timePeriod'] && !$filters['startDate'] && !$filters['endDate']) {
                    $dateRange = $this->getDateRange($filters['timePeriod']);
                    if ($dateRange) {
                        $filters['startDate'] = $dateRange['start'];
                        $filters['endDate'] = $dateRange['end'];
                    }
                }
                
                $reports = $this->model->getStudentReports($filters);
                
                if ($format === 'csv' || $format === 'excel' || $format === 'xlsx') {
                    $this->exportToCsv($reports);
                    return;
                }
            }
            
            // Get filters from query parameters
            $filters = [
                'department' => $this->getGet('department', 'all'),
                'section' => $this->getGet('section', 'all'),
                'status' => $this->getGet('status', 'all'),
                'violationType' => $this->getGet('violationType', 'all'),
                'startDate' => $this->getGet('startDate', null),
                'endDate' => $this->getGet('endDate', null),
                'search' => $this->getGet('search', ''),
                'timePeriod' => $this->getGet('timePeriod', null)
            ];
            
            // Handle time period filters
            if ($filters['timePeriod'] && !$filters['startDate'] && !$filters['endDate']) {
                $dateRange = $this->getDateRange($filters['timePeriod']);
                if ($dateRange) {
                    $filters['startDate'] = $dateRange['start'];
                    $filters['endDate'] = $dateRange['end'];
                }
            }
            
            // Get reports
            $reports = $this->model->getStudentReports($filters);
            
            // Get statistics
            $stats = $this->model->getReportStats($filters);
            $violationTypes = $this->model->getViolationTypesList();
            
            error_log("ReportController: Retrieved " . count($reports) . " reports");
            error_log("ReportController: Stats: " . print_r($stats, true));
            
            $response = [
                'status' => 'success',
                'message' => count($reports) > 0 ? 'Reports retrieved successfully' : 'No reports found. Click "Generate Report" to create reports from violations.',
                'reports' => $reports,
                'data' => $reports,
                'stats' => $stats,
                'violationTypes' => $violationTypes,
                'count' => count($reports),
                'filters_applied' => $filters
            ];
            
            $this->json($response);
        } catch (Exception $e) {
            error_log("ReportController Error: " . $e->getMessage());
            $this->error('Failed to retrieve reports: ' . $e->getMessage());
        }
    }
    
    /**
     * Get date range based on time period
     */
    private function getDateRange($timePeriod) {
        $today = new DateTime();
        
        switch ($timePeriod) {
            case 'today':
                return [
                    'start' => $today->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];
            case 'this_week':
                $start = clone $today;
                $start->modify('monday this week');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];
            case 'this_month':
                $start = clone $today;
                $start->modify('first day of this month');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];
            case 'this_year':
                $start = clone $today;
                $start->modify('first day of January this year');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];
            case 'last_7_days':
                $start = clone $today;
                $start->modify('-7 days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];
            case 'last_30_days':
                $start = clone $today;
                $start->modify('-30 days');
                return [
                    'start' => $start->format('Y-m-d'),
                    'end' => $today->format('Y-m-d')
                ];
            default:
                return null;
        }
    }
    
    /**
     * Export reports to CSV
     */
    private function exportToCsv($reports) {
        // Clear any previous output
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reports_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'Report ID', 'Student ID', 'Name', 'Department', 'Section', 'Year Level',
            'Uniform Violations', 'Footwear Violations', 'No ID Violations',
            'Total Violations', 'Status', 'Last Violation Date'
        ]);
        
        foreach ($reports as $report) {
            fputcsv($output, [
                $report['reportId'],
                $report['studentId'],
                $report['studentName'],
                $report['department'],
                $report['section'] ?? '',
                $report['yearlevel'],
                $report['uniformCount'],
                $report['footwearCount'],
                $report['noIdCount'],
                $report['totalViolations'],
                $report['status'],
                $report['lastUpdated']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

