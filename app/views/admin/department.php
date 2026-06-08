<?php
require_once __DIR__ . '/../../core/View.php';
?>
<?php
require_once __DIR__ . '/../../config/db_connect.php';
/** @var mysqli $conn */
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Departments | OSAS System</title>
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="<?= View::asset('styles/department.css') ?>?v=<?= time() ?>">
</head>

<body>

  <!-- department.html -->
  <main id="department-page">
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">Departments</h1>
          <p class="page-subtitle">Manage all academic departments in the institution</p>
        </div>
        <div class="breadcrumb-wrapper">
          <div class="breadcrumb">
            <a href="#" class="breadcrumb-item">Dashboard</a>
            <i class='bx bx-chevron-right'></i>
            <span class="breadcrumb-item active">Departments</span>
          </div>
        </div>
      </div>

      <div class="header-actions">
        <div class="button-group">
          <button class="action-btn outline small" id="btnImport" title="Import Departments">
            <i class='bx bx-upload'></i>
            <span>Import</span>
          </button>
          <button class="action-btn outline small" id="btnExport" title="Export Departments">
            <i class='bx bx-download'></i>
            <span>Export</span>
          </button>
        </div>
        <button class="action-btn primary" id="btnAddDepartment">
          <i class='bx bx-plus'></i>
          <span>Add Department</span>
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-overview">
      <div class="stat-card">
        <div class="stat-icon">
          <i class='bx bx-buildings'></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-title">Total Departments</h3>
          <div class="stat-value" id="totalDepartments">0</div>
          <div class="stat-change positive">
            <i class='bx bx-up-arrow-alt'></i>
            <span>+2 this month</span>
          </div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">
          <i class='bx bx-user-check'></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-title">Active</h3>
          <div class="stat-value" id="activeDepartments">0</div>
          <div class="stat-percentage" id="activeDepartmentsPct">0%</div>
        </div>
      </div>

    </div>

    <!-- Main Content Card -->
    <div class="content-card">
      <!-- Table Header -->
      <div class="table-header">
        <div class="header-left">
          <h2 class="table-title">Department List</h2>
          <p class="table-subtitle">All academic departments and their details</p>
        </div>

        <div class="header-right">
          <div class="search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchDepartment" placeholder="Search departments...">
          </div>

          <div class="filter-group">
            <select id="departmentFilter" class="filter-select">
              <option value="all">All Departments</option>
              <option value="active">Active Only</option>
            </select>

            <!-- View Toggle -->
            <div class="dept-view-toggle">
              <button class="dept-view-btn" data-view="table" title="Table View">
                <i class='bx bx-table'></i>
              </button>
              <button class="dept-view-btn" data-view="grid" title="Grid View">
                <i class='bx bx-grid-alt'></i>
              </button>
              <button class="dept-view-btn active" data-view="list" title="List View">
                <i class='bx bx-list-ul'></i>
              </button>
            </div>

            <button class="filter-btn" title="More filters">
              <i class='bx bx-filter-alt'></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Department Table -->
      <div class="table-wrapper" id="deptTableView">
        <table class="department-table">
          <thead>
            <tr>
              <th class="sortable" data-sort="name">
                <div class="table-header-content">
                  <span>Department Name</span>
                  <i class='bx bx-sort'></i>
                </div>
              </th>
              <th>Head of Department</th>
              <th>Student Count</th>
              <th class="sortable" data-sort="date">
                <div class="table-header-content">
                  <span>Date Created</span>
                  <i class='bx bx-sort'></i>
                </div>
              </th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="departmentTableBody">
            <!-- JS will populate rows from database -->
          </tbody>
        </table>
      </div>

      <!-- Grid View -->
      <div id="deptGridView" class="dept-grid-container" style="display:none;">
        <div id="deptGridBody" class="dept-grid"></div>
      </div>

      <!-- List View -->
      <div id="deptListView" class="dept-list-container" style="display:none;">
        <div id="deptListBody" class="dept-list"></div>
      </div>

      <!-- Table Footer -->
      <div class="table-footer">
        <div class="footer-info">
          Showing <span id="showingCount">3</span> of <span id="totalCount">12</span> departments
        </div>
        <div class="pagination">
          <!-- JS will populate pagination buttons -->
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div id="departmentModal" class="modal">
      <div class="modal-overlay" id="modalOverlay"></div>
      <div class="modal-container" style="max-width: 450px;">
        <div class="modal-header">
          <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class='bx bxs-building' style="font-size:20px;color:#d4af37;"></i>
          </div>
          <div style="flex:1;">
            <h2 id="modalTitle" style="margin:0;font-size:1.05rem;font-weight:700;display:block;">
              <span>Add New Department</span>
            </h2>
            <p class="dept-modal-subtitle" style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">Create and manage academic departments.</p>
          </div>
          <button class="close-btn" id="closeModal">
            <i class='bx bx-x'></i>
          </button>
        </div>

        <form id="departmentForm">
          <div class="form-group">
            <label for="deptName">Department Name</label>
            <input type="text" id="deptName" name="deptName" required placeholder="e.g., Computer Science">
          </div>

          <div class="form-group">
            <label for="deptCode">Department Code</label>
            <input type="text" id="deptCode" name="deptCode" required placeholder="e.g., CS" maxlength="10">
          </div>

          <div class="form-group">
            <label for="hodName">Head of Department</label>
            <input type="text" id="hodName" name="hodName" placeholder="Dr. Firstname Lastname">
          </div>

          <div class="form-group">
            <label for="deptDescription">Description (Optional)</label>
            <textarea id="deptDescription" name="deptDescription" rows="3" placeholder="Brief description of the department..."></textarea>
          </div>

          <div class="form-group">
            <label for="deptStatus">Status</label>
            <select id="deptStatus" name="deptStatus" required>
              <option value="active">Active</option>
            </select>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-outline" id="cancelModal">Cancel</button>
            <button type="submit" class="btn-primary">Save Department</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Empty State (hidden by default) -->
    <div class="empty-state" id="emptyState" style="display: none;">
      <div class="empty-icon">
        <i class='bx bx-building-house'></i>
      </div>
      <h3>No Departments Found</h3>
      <p>Get started by adding your first department</p>
      <button class="btn-primary" id="btnAddFirstDepartment">
        <i class='bx bx-plus'></i> Add Department
      </button>
    </div>

    <!-- Export Modal -->
    <div id="ExportDepartmentsModal" class="modal">
      <div class="modal-overlay" id="ExportModalOverlay"></div>
      <div class="modal-container" style="max-width: 360px;">
        <div class="modal-header">
          <h2>
            <i class='bx bx-download'></i>
            <span>Export Departments</span>
          </h2>
          <button class="close-btn" id="closeExportModal">
            <i class='bx bx-x'></i>
          </button>
        </div>
        <div class="modal-body" style="padding: 16px;">
          <p style="margin-bottom: 14px; color: #666; font-size: 11px;">Select your preferred format to download the department records.</p>
          <div class="export-options" style="display: flex; flex-direction: column; gap: 8px;">
            <button id="exportPDF" class="action-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
              <i class='bx bxs-file-pdf' style="color: #e74c3c; font-size: 16px;"></i>
              <span style="margin-left: 8px;">Export as PDF</span>
            </button>
            <button id="exportExcel" class="action-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
              <i class='bx bxs-file' style="color: #27ae60; font-size: 16px;"></i>
              <span style="margin-left: 8px;">Export as Excel (.csv)</span>
            </button>
            <button id="exportWord" class="action-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
              <i class='bx bxs-file-doc' style="color: #2980b9; font-size: 16px;"></i>
              <span style="margin-left: 8px;">Export as Word (.docx)</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </main>
  <!-- Load Libraries for Export -->
  <script src="<?= View::asset('js/lib/jspdf.umd.min.js') ?>"></script>
  <script src="<?= View::asset('js/lib/jspdf.plugin.autotable.min.js') ?>"></script>
  <script src="<?= View::asset('js/lib/FileSaver.js') ?>"></script>
  <script src="<?= View::asset('js/lib/pizzip.js') ?>"></script>
  <script src="<?= View::asset('js/lib/docxtemplater.js') ?>"></script>
  <script src="<?= View::asset('js/lib/docx.js') ?>"></script>
</body>

</html>


