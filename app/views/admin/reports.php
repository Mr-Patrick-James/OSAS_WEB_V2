<?php
require_once __DIR__ . '/../../core/View.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | OSAS System</title>
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="<?= View::asset('styles/report.css') ?>">
</head>
<body>

<!-- Reports.html -->
<main id="Reports-page">
  <!-- Theme Toggle Button -->
 

  <!-- HEADER -->
  <div class="Reports-head-title">
    <div class="Reports-left">
      <h1>Reports</h1>
      <p class="Reports-subtitle">Statistics and insights on student violations</p>
      <ul class="Reports-breadcrumb">
        <li><a href="#">Dashboard</a></li>
        <li><i class='bx bx-chevron-right'></i></li>
        <li><a class="active" href="#">Reports Data</a></li>
      </ul>
    </div>

    <div class="Reports-header-actions">
      <div class="Reports-button-group">
        <button id="btnExportReports" class="Reports-btn outline small" style="padding:6px 12px;font-size:11px;border-radius:6px;height:auto;line-height:1.4;">
          <i class='bx bx-download' style="font-size:14px;"></i>
          <span>Export</span>
        </button>
        <button id="btnRefreshReports" class="Reports-btn outline small" style="padding:6px 12px;font-size:11px;border-radius:6px;height:auto;line-height:1.4;">
          <i class='bx bx-refresh' style="font-size:14px;"></i>
          <span>Refresh</span>
        </button>
      </div>
      <div class="Reports-button-group">
        <button id="btnGenerateReports" class="Reports-btn primary" style="padding:6px 12px;font-size:11px;border-radius:6px;height:auto;line-height:1.4;">
          <i class='bx bx-plus' style="font-size:14px;"></i> Generate Report
        </button>
      </div>
    </div>
  </div>

  <!-- STATS CARDS -->
  <div class="Reports-stats-overview">
    <div class="Reports-stat-card Reports-stat-card--total">
      <div class="Reports-stat-icon">
        <i class='bx bx-bar-chart-alt'></i>
      </div>
      <div class="Reports-stat-content">
        <h3 class="Reports-stat-title">Total Violations</h3>
        <div class="Reports-stat-value" id="totalViolationsCount">0</div>
        <div class="Reports-stat-percentage" style="visibility: hidden;">0%</div>
      </div>
    </div>

    <div id="ReportsTypeStatsContainer" class="Reports-stats-type-container">
      <p class="Reports-stats-loading">Loading violation types...</p>
    </div>
  </div>

  <!-- FILTERS CARD -->
  <div class="Reports-filter-card">
    <div class="filter-header">
      <h3><i class='bx bx-filter-alt'></i> Report Filters</h3>
      <button id="clearFilters" class="Reports-btn outline small">
        <i class='bx bx-x'></i> Clear All
      </button>
    </div>

    <div class="filter-grid">
      <div class="filter-group">
        <label for="ReportsDepartmentFilter">Department</label>
        <select id="ReportsDepartmentFilter" class="Reports-filter-select">
          <option value="all">All Departments</option>
          <option value="BSIS">BS Information System</option>
          <option value="BSBA">BS Business Administration</option>
          <option value="BEED">Bachelor of Elementary Education</option>
          <option value="BSIT">BS Information Technology</option>
          <option value="BSIS-1">BSIT (BSIS-1)</option>
          <option value="BSCS">BS Computer Science</option>
          <option value="WFT">Welding & Fabrication Tech</option>
          <option value="BTVTED">BTVTED</option>
          <option value="CHS">Computer Hardware Servicing</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="ReportsSectionFilter">Section</label>
        <select id="ReportsSectionFilter" class="Reports-filter-select">
          <option value="all">All Sections</option>
          <option value="BSIS-1">BSIS-1</option>
          <option value="BSIS-2">BSIS-2</option>
          <option value="WFT-1">WFT-1</option>
          <option value="WFT-2">WFT-2</option>
          <option value="BTVTED-3">BTVTED-3</option>
          <option value="CHS-1">CHS-1</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="ReportsStatusFilter">Status</label>
        <select id="ReportsStatusFilter" class="Reports-filter-select">
          <option value="all">All Status</option>
          <option value="permitted">Permitted</option>
          <option value="warning">Warning</option>
          <option value="disciplinary">Disciplinary</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="ReportsTimeFilter">Time Period</label>
        <select id="ReportsTimeFilter" class="Reports-filter-select">
          <option value="all">All Time</option>
          <option value="today">Today</option>
          <option value="this_week">This Week</option>
          <option value="this_month">This Month</option>
          <option value="this_year">This Year</option>
          <option value="last_7_days">Last 7 Days</option>
          <option value="last_30_days">Last 30 Days</option>
          <option value="custom">Custom Range</option>
        </select>
      </div>

      <div class="filter-group date-range-group" id="dateRangeGroup" style="display: none;">
        <label>Date Range</label>
        <div class="date-range-inputs">
          <div class="date-input">
            <i class='bx bx-calendar'></i>
            <input type="date" id="ReportsStart" name="ReportsStart">
          </div>
          <span class="date-separator">to</span>
          <div class="date-input">
            <i class='bx bx-calendar'></i>
            <input type="date" id="ReportsEnd" name="ReportsEnd">
          </div>
        </div>
      </div>

      <div class="filter-group">
        <label for="ReportsViolationTypeFilter">Violation Type</label>
        <select id="ReportsViolationTypeFilter" class="Reports-filter-select">
          <option value="all">All Types</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="ReportsSortBy">Sort By</label>
        <select id="ReportsSortBy" class="Reports-filter-select">
          <option value="total_desc">Total Violations (High to Low)</option>
          <option value="total_asc">Total Violations (Low to High)</option>
          <option value="name_asc">Name (A to Z)</option>
          <option value="name_desc">Name (Z to A)</option>
          <option value="dept_asc">Department (A to Z)</option>
          <option value="section_asc">Section (A to Z)</option>
        </select>
      </div>
    </div>

    <div class="filter-actions">
      <button id="applyFilters" class="Reports-btn primary small">
        <i class='bx bx-check'></i> Apply Filters
      </button>
      <button id="resetFilters" class="Reports-btn outline small">
        <i class='bx bx-reset'></i> Reset
      </button>
    </div>
  </div>

  <!-- MAIN CONTENT CARD -->
  <div class="Reports-content-card">
    <!-- Table Header -->
    <div class="Reports-table-header">
      <div class="Reports-header-left">
        <h2 class="Reports-table-title">Detailed Report</h2>
        <p class="Reports-table-subtitle">Student violation statistics and analysis</p>
      </div>

      <div class="Reports-header-right">
        <div class="Reports-search-box">
          <i class='bx bx-search'></i>
          <input type="text" id="searchReport" placeholder="Search reports...">
        </div>

        <div class="Reports-view-options">
          <button class="Reports-view-btn" data-view="table" title="Table View">
            <i class='bx bx-table'></i>
          </button>
          <button class="Reports-view-btn" data-view="grid" title="Grid View">
            <i class='bx bx-grid-alt'></i>
          </button>
          <button class="Reports-view-btn active" data-view="list" title="List View">
            <i class='bx bx-list-ul'></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Reports Table -->
    <div class="Reports-table-container" id="reportsTableView">
      <table class="Reports-table">
        <thead>
          <tr id="ReportsTableHeaderRow">
            <th colspan="9" style="text-align:center;padding:16px;color:#666;">Loading columns...</th>
          </tr>
        </thead>

        <tbody id="ReportsTableBody">
          <tr>
            <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
              <div style="font-size: 1.1em;">Loading reports...</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Grid / Card View -->
    <div id="reportsGridView" class="Reports-grid-container" style="display:none;">
      <div id="ReportsGridBody" class="Reports-grid"></div>
    </div>

    <!-- List View -->
    <div id="reportsListView" class="Reports-list-container" style="display:none;">
      <div id="ReportsListBody" class="Reports-list"></div>
    </div>

    <!-- Table Footer -->
    <div class="Reports-table-footer">
      <div class="Reports-footer-info">
        <div class="summary-stats">
          <span class="stat-item">
            <strong>Total Students:</strong> <span id="totalStudentsCount">0</span>
          </span>
          <span class="stat-item">
            <strong>Total Violations:</strong> <span id="totalViolationsFooter">0</span>
          </span>
          <span class="stat-item">
            <strong>Avg per Student:</strong> <span id="avgViolations">0</span>
          </span>
        </div>
        <div class="pagination-info">
          Showing <span id="showingReportsCount">0</span> of <span id="totalReportsCount">0</span> records
        </div>
      </div>
      <div class="Reports-pagination">
        <button class="Reports-pagination-btn" disabled>
          <i class='bx bx-chevron-left'></i>
        </button>
        <button class="Reports-pagination-btn active">1</button>
        <button class="Reports-pagination-btn">2</button>
        <button class="Reports-pagination-btn">3</button>
        <button class="Reports-pagination-btn">4</button>
        <button class="Reports-pagination-btn">5</button>
        <button class="Reports-pagination-btn">
          <i class='bx bx-chevron-right'></i>
        </button>
      </div>
    </div>
  </div>

  <!-- GENERATE REPORT MODAL -->
  <div id="ReportsGenerateModal" class="Reports-modal">
    <div class="Reports-modal-overlay" id="ReportsModalOverlay"></div>
    <div class="Reports-modal-container">
      <div class="Reports-modal-header">
        <div class="header-icon" style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bxs-report' style="font-size:20px;color:#d4af37;"></i>
        </div>
        <div class="header-text" style="flex:1;">
          <h2 id="ReportsModalTitle" style="margin:0;font-size:1.1rem;">Generate Custom Report</h2>
          <p style="margin:4px 0 0;font-size:0.78rem;color:#6b7280;">Configure and generate a violation analysis report.</p>
        </div>
        <button class="Reports-close-btn" id="closeReportsModal">
          <i class='bx bx-x'></i>
        </button>
      </div>

      <form id="ReportsGenerateForm">
        <div class="Reports-form-group">
          <label for="reportName">Report Name</label>
          <input type="text" id="reportName" name="reportName" required placeholder="e.g., Monthly Violation Report - March 2024">
        </div>

        <div class="Reports-form-row">
          <div class="Reports-form-group">
            <label for="reportType">Report Type</label>
            <select id="reportType" name="reportType" required>
              <option value="">Select report type</option>
              <option value="summary">Summary Report</option>
              <option value="detailed">Detailed Report</option>
              <option value="department">Department-wise Report</option>
              <option value="violation_type">Violation Type Report</option>
              <option value="time_series">Time Series Analysis</option>
            </select>
          </div>
          
          <div class="Reports-form-group">
            <label for="reportFormat">Format</label>
            <select id="reportFormat" name="reportFormat" required>
              <option value="pdf">PDF Document (Non-editable)</option>
            </select>
          </div>
        </div>

        <div class="Reports-form-row">
          <div class="Reports-form-group">
            <label for="startDate">Start Date</label>
            <div class="date-input-wrapper">
              <i class='bx bx-calendar'></i>
              <input type="date" id="startDate" name="startDate" required>
            </div>
          </div>
          
          <div class="Reports-form-group">
            <label for="endDate">End Date</label>
            <div class="date-input-wrapper">
              <i class='bx bx-calendar'></i>
              <input type="date" id="endDate" name="endDate" required>
            </div>
          </div>
        </div>

        <div class="Reports-form-group">
          <label>Include Departments</label>
          <div class="checkbox-group" id="generateDeptCheckboxes">
            <!-- Departments will be loaded via JS from the database -->
            <p class="loading-text">Loading departments...</p>
          </div>
        </div>

        <div class="Reports-form-group">
          <label>Include Violation Types</label>
          <div class="checkbox-group" id="generateViolationTypeCheckboxes">
            <!-- Violation types will be loaded via JS from the database -->
            <p class="loading-text">Loading violation types...</p>
          </div>
        </div>

        <div class="Reports-form-group">
          <label for="includeCharts">Include Charts & Graphs</label>
          <div class="toggle-switch">
            <input type="checkbox" id="includeCharts" name="includeCharts">
            <label for="includeCharts" class="toggle-label">
              <span class="toggle-handle"></span>
            </label>
          </div>
        </div>

        <div class="Reports-form-group">
          <label for="reportNotes">Additional Notes (Optional)</label>
          <textarea id="reportNotes" name="reportNotes" rows="3" placeholder="Add any additional instructions or notes for the report..."></textarea>
        </div>

        <div class="Reports-form-actions">
          <button type="button" class="Reports-btn-outline" id="cancelReportsModal">Cancel</button>
          <button type="submit" class="Reports-btn-primary">
            <i class='bx bx-file'></i> Generate Report
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- REPORT DETAILS MODAL -->
  <div id="ReportDetailsModal" class="Reports-modal">
    <div class="Reports-modal-overlay" id="DetailsModalOverlay"></div>
    <div class="Reports-modal-container wide">
      <div class="Reports-modal-header">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bxs-file-blank' style="font-size:20px;color:#d4af37;"></i>
        </div>
        <div style="flex:1;">
          <h2 style="margin:0;font-size:1.05rem;font-weight:700;display:block;">
            <span>Report Details</span>
          </h2>
          <p style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">View student violation analysis and history.</p>
        </div>
        <div class="modal-actions">
          <button class="Reports-action-btn export">
            <i class='bx bx-download'></i> Export
          </button>
          <button class="Reports-close-btn" id="closeDetailsModal">
            <i class='bx bx-x'></i>
          </button>
        </div>
      </div>

      <div class="report-details-content">
        <!-- Report Header -->
        <div class="report-header">
          <h3>Student Violation Analysis Report</h3>
          <div class="report-meta">
            <span class="report-id">Report ID: R001</span>
            <span class="report-date">Generated: March 15, 2024 • 14:30 PM</span>
            <span class="report-status active">Active</span>
          </div>
        </div>

        <!-- Student Info Section -->
        <div class="report-student-section">
          <h4>Student Information</h4>
          <div class="student-info-grid">
            <div class="info-item">
              <span class="info-label">Student Name:</span>
              <span class="info-value">John Doe</span>
            </div>
            <div class="info-item">
              <span class="info-label">Student ID:</span>
              <span class="info-value">2024-001</span>
            </div>
            <div class="info-item">
              <span class="info-label">Department:</span>
              <span class="info-value">BS Information System</span>
            </div>
            <div class="info-item">
              <span class="info-label">Section:</span>
              <span class="info-value">BSIS-1</span>
            </div>
            <div class="info-item">
              <span class="info-label">Contact No:</span>
              <span class="info-value">09171234567</span>
            </div>
            <div class="info-item">
              <span class="info-label">Report Period:</span>
              <span class="info-value">Jan 1, 2024 - Mar 15, 2024</span>
            </div>
          </div>
        </div>

        <!-- Violation Statistics -->
        <div class="violation-statistics">
          <h4>Violation Statistics</h4>
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-icon">
                <i class='bx bx-t-shirt'></i>
              </div>
              <div class="stat-content">
                <span class="stat-title">Uniform Violations</span>
                <span class="stat-value">3</span>
                <span class="stat-trend up">+1 this month</span>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">
                <i class='bx bx-walk'></i>
              </div>
              <div class="stat-content">
                <span class="stat-title">Footwear Violations</span>
                <span class="stat-value">2</span>
                <span class="stat-trend neutral">No change</span>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">
                <i class='bx bx-id-card'></i>
              </div>
              <div class="stat-content">
                <span class="stat-title">No ID Violations</span>
                <span class="stat-value">1</span>
                <span class="stat-trend down">-1 this month</span>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">
                <i class='bx bx-bar-chart-alt'></i>
              </div>
              <div class="stat-content">
                <span class="stat-title">Total Violations</span>
                <span class="stat-value">6</span>
                <span class="stat-trend up">+2 this month</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Violation History -->
        <div class="violation-history">
          <h4>Violation Timeline</h4>
          <div class="timeline">
            <div class="timeline-item">
              <div class="timeline-date">Mar 15, 2024</div>
              <div class="timeline-content">
                <span class="timeline-title">Improper Uniform - 5th Offense</span>
                <span class="timeline-desc">Fifth offense for improper uniform</span>
              </div>
            </div>
            <div class="timeline-item">
              <div class="timeline-date">Mar 1, 2024</div>
              <div class="timeline-content">
                <span class="timeline-title">Improper Footwear - 4th Offense</span>
                <span class="timeline-desc">Fourth offense for improper footwear</span>
              </div>
            </div>
            <div class="timeline-item">
              <div class="timeline-date">Feb 15, 2024</div>
              <div class="timeline-content">
                <span class="timeline-title">No ID - 3rd Offense</span>
                <span class="timeline-desc">Third offense for not wearing ID</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recommendations -->
        <div class="report-recommendations">
          <h4>Recommendations</h4>
          <div class="recommendations-list">
            <div class="recommendation-item">
              <i class='bx bx-check-circle'></i>
              <span>Schedule counseling session with student</span>
            </div>
            <div class="recommendation-item">
              <i class='bx bx-check-circle'></i>
              <span>Notify parent or guardian of multiple offenses</span>
            </div>
          </div>
        </div>

        <!-- Report Footer -->
        <div class="report-footer">
          <div class="footer-left">
            <p class="footer-note">* This report is generated automatically by the E-OSAS System.</p>
          </div>
          <div class="footer-right">
            <div class="signature-line"></div>
            <p class="signature-name" id="reportAdminName">Administrator</p>
            <p class="signature-title">OSAS Administrator</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Empty State -->
  <div class="Reports-empty-state" id="ReportsEmptyState" style="display: none;">
    <div class="reports-empty-inner">
      <div class="reports-empty-icon">
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="32" cy="32" r="32" fill="rgba(212,175,55,0.08)"/>
          <rect x="18" y="38" width="6" height="10" rx="2" fill="#D4AF37" opacity="0.4"/>
          <rect x="27" y="28" width="6" height="20" rx="2" fill="#D4AF37" opacity="0.6"/>
          <rect x="36" y="20" width="6" height="28" rx="2" fill="#D4AF37" opacity="0.9"/>
          <path d="M16 44 Q24 36 32 30 Q40 24 48 18" stroke="#D4AF37" stroke-width="1.5" stroke-dasharray="3 2" stroke-linecap="round"/>
        </svg>
      </div>
      <div class="reports-empty-text">
        <h3>No Reports Generated</h3>
        <p>No violation data matches the current filters.<br>Try adjusting your criteria or generate a new report.</p>
      </div>
      <button class="reports-empty-btn" id="btnGenerateFirstReport">
        <i class='bx bx-bar-chart-alt-2'></i>
        <span>Generate Report</span>
      </button>
    </div>
  </div>

  <!-- Export Modal -->
  <div id="ExportReportsModal" class="Reports-modal">
    <div class="Reports-modal-overlay" id="ExportModalOverlay"></div>
    <div class="Reports-modal-container" style="max-width: 360px;">
      <div class="Reports-modal-header">
        <h2>
          <i class='bx bx-download'></i>
          <span>Export Reports Data</span>
        </h2>
        <button class="Reports-close-btn" id="closeExportModal">
          <i class='bx bx-x'></i>
        </button>
      </div>
      <div class="Reports-modal-body" style="padding: 16px;">
        <p style="margin-bottom: 14px; color: #666; font-size: 11px;">Select your preferred format to download the report records.</p>
        <div class="export-options" style="display: flex; flex-direction: column; gap: 8px;">
          <button id="exportPDF" class="Reports-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file-pdf' style="color: #e74c3c; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as PDF</span>
          </button>
          <button id="exportExcel" class="Reports-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file' style="color: #27ae60; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as Excel (.csv)</span>
          </button>
          <button id="exportWord" class="Reports-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file-doc' style="color: #2980b9; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as Word (.docx)</span>
          </button>
        </div>
      </div>
    </div>
  </div>

</main>

<script src="<?= View::asset('js/lib/jspdf.umd.min.js') ?>"></script>
<script src="<?= View::asset('js/lib/jspdf.plugin.autotable.min.js') ?>"></script>
<script src="<?= View::asset('js/lib/FileSaver.js') ?>"></script>
<script src="<?= View::asset('js/lib/pizzip.js') ?>"></script>
<script src="<?= View::asset('js/lib/docxtemplater.js') ?>"></script>
<script src="<?= View::asset('js/lib/docx.js') ?>"></script>
<script src="<?= View::asset('js/reports.js') ?>"></script>
</body>
</html>


