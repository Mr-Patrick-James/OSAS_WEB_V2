<?php
require_once __DIR__ . '/../../core/View.php';
?>
<?php
require_once __DIR__ . '/../../config/db_connect.php';
/** @var mysqli $conn */

// Fetch departments for dropdown
$deptQuery = "SELECT id, department_name, department_code FROM departments WHERE status = 'active' ORDER BY department_name ASC";
$deptResult = $conn->query($deptQuery);
$departments = [];
if ($deptResult && $deptResult->num_rows > 0) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sections | OSAS System</title>
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="<?= View::asset('styles/section.css') ?>">
</head>

<body>

  <!-- section.html -->
  <main id="sections-page">
    <!-- Header Section -->
    <div class="sections-head-title">
      <div class="sections-left">
        <h1>Sections</h1>
        <p class="sections-subtitle">Manage all academic sections in the institution</p>
        <ul class="sections-breadcrumb">
          <li><a href="#">Dashboard</a></li>
          <li><i class='bx bx-chevron-right'></i></li>
          <li><a class="active" href="#">Sections</a></li>
        </ul>
      </div>

      <div class="sections-header-actions">
        <div class="sections-button-group">
          <button id="btnExportSections" class="sections-btn outline small">
            <i class='bx bx-download'></i>
            <span>Export</span>
          </button>
        </div>
        <button id="btnAddSection" class="sections-btn primary">
          <i class='bx bx-plus'></i> Add Section
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="sections-stats-overview">
      <div class="sections-stat-card">
        <div class="sections-stat-icon">
          <i class='bx bx-layer'></i>
        </div>
        <div class="sections-stat-content">
          <h3 class="sections-stat-title">Total Sections</h3>
          <div class="sections-stat-value" id="totalSections">0</div>
          <div class="sections-stat-change positive">
            <i class='bx bx-up-arrow-alt'></i>
            <span>+5 this month</span>
          </div>
        </div>
      </div>

      <div class="sections-stat-card">
        <div class="sections-stat-icon">
          <i class='bx bx-check-circle'></i>
        </div>
        <div class="sections-stat-content">
          <h3 class="sections-stat-title">Active</h3>
          <div class="sections-stat-value" id="activeSections">0</div>
          <div class="sections-stat-percentage" id="activeSectionsPct">0%</div>
        </div>
      </div>

    </div>

    <!-- Main Content Card -->
    <div class="sections-content-card">
      <!-- Table Header -->
      <div class="sections-table-header">
        <div class="sections-header-left">
          <h2 class="sections-table-title">Section List</h2>
          <p class="sections-table-subtitle">All academic sections and their details</p>
        </div>

        <div class="sections-header-right">
          <div class="sections-search-toggle-row">
            <div class="sections-search-box">
              <i class='bx bx-search'></i>
              <input type="text" id="searchSection" placeholder="Search sections...">
            </div>

            <!-- View Toggle -->
            <div class="sect-view-toggle">
              <button class="sect-view-btn" data-view="table" title="Table View">
                <i class='bx bx-table'></i>
              </button>
              <button class="sect-view-btn" data-view="grid" title="Grid View">
                <i class='bx bx-grid-alt'></i>
              </button>
              <button class="sect-view-btn active" data-view="list" title="List View">
                <i class='bx bx-list-ul'></i>
              </button>
            </div>
          </div>

          <div class="sections-filter-group">
            <select id="sectionFilterSelect" class="sections-filter-select">
              <option value="all">All Sections</option>
              <option value="active">Active Only</option>
            </select>

            <button class="sections-filter-btn" title="More filters">
              <i class='bx bx-filter-alt'></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Section Table -->
      <div id="sectionsPrintArea" class="sections-table-container" id="sectTableView">
        <table class="sections-table">
          <thead>
            <tr>
              <th class="sections-sortable" data-sort="name">
                <div class="sections-table-header-content">
                  <span>Section Name</span>
                  <i class='bx bx-sort'></i>
                </div>
              </th>
              <th>Associated Department</th>
              <th>Student Count</th>
              <th class="sections-sortable" data-sort="date">
                <div class="sections-table-header-content">
                  <span>Date Created</span>
                  <i class='bx bx-sort'></i>
                </div>
              </th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="sectionsTableBody">
            <!-- JS will populate rows from database -->
          </tbody>
        </table>
      </div>

      <!-- Grid View -->
      <div id="sectGridView" class="sect-grid-container" style="display:none;">
        <div id="sectGridBody" class="sect-grid"></div>
      </div>

      <!-- List View -->
      <div id="sectListView" class="sect-list-container" style="display:none;">
        <div id="sectListBody" class="sect-list"></div>
      </div>

      <!-- Table Footer -->
      <div class="sections-table-footer">
        <div class="sections-footer-info">
          Showing <span id="showingSectionsCount">3</span> of <span id="totalSectionsCount">15</span> sections
        </div>
        <div class="sections-pagination">
          <button class="sections-pagination-btn" disabled>
            <i class='bx bx-chevron-left'></i>
          </button>
          <button class="sections-pagination-btn active">1</button>
          <button class="sections-pagination-btn">2</button>
          <button class="sections-pagination-btn">3</button>
          <button class="sections-pagination-btn">
            <i class='bx bx-chevron-right'></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div id="sectionsModal" class="sections-modal">
      <div class="sections-modal-overlay" id="sectionsModalOverlay"></div>
      <div class="sections-modal-container">
        <div class="sections-modal-header">
          <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class='bx bxs-layer' style="font-size:20px;color:#d4af37;"></i>
          </div>
          <div style="flex:1;">
            <h2 id="sectionsModalTitle" style="margin:0;font-size:1.05rem;font-weight:700;display:block;">
              <span>Add New Section</span>
            </h2>
            <p class="sections-modal-subtitle" style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">Create and manage class sections.</p>
          </div>
          <button class="sections-close-btn" id="closeSectionsModal">
            <i class='bx bx-x'></i>
          </button>
        </div>

        <form id="sectionsForm">
          <div class="sections-form-group">
            <label for="sectionName">Section Name</label>
            <input type="text" id="sectionName" name="sectionName" required placeholder="e.g., Section A">
          </div>

          <div class="sections-form-group">
            <label for="sectionCode">Section Code</label>
            <input type="text" id="sectionCode" name="sectionCode" required placeholder="e.g., SEC-A" maxlength="10">
          </div>

          <div class="sections-form-group">
            <label for="sectionDepartment">Department</label>
            <select id="sectionDepartment" name="sectionDepartment" required>
              <option value="">Select Department</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept['id']); ?>">
                  <?php echo htmlspecialchars($dept['department_name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="sections-form-group">
            <label for="academicYear">Academic Year</label>
            <input type="text" id="academicYear" name="academicYear" required placeholder="e.g., 2023-2024">
          </div>

          <div class="sections-form-group">
            <label for="sectionStatus">Status</label>
            <select id="sectionStatus" name="sectionStatus" required>
              <option value="active">Active</option>
            </select>
          </div>

          <div class="sections-form-actions">
            <button type="button" class="sections-btn-outline" id="cancelSectionsModal">Cancel</button>
            <button type="submit" class="sections-btn-primary">Save Section</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Export Modal -->
    <div id="ExportSectionsModal" class="sections-modal">
      <div class="sections-modal-overlay" id="ExportModalOverlay"></div>
      <div class="sections-modal-container" style="max-width: 360px;">
        <div class="sections-modal-header">
          <h2>
            <i class='bx bx-download'></i>
            <span>Export Sections</span>
          </h2>
          <button class="sections-close-btn" id="closeExportModal">
            <i class='bx bx-x'></i>
          </button>
        </div>
        <div class="sections-modal-body" style="padding: 16px;">
          <p style="margin-bottom: 14px; color: #666; font-size: 11px;">Select your preferred format to download the section records.</p>
          <div class="export-options" style="display: flex; flex-direction: column; gap: 8px;">
            <button id="exportPDF" class="sections-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
              <i class='bx bxs-file-pdf' style="color: #e74c3c; font-size: 16px;"></i>
              <span style="margin-left: 8px;">Export as PDF</span>
            </button>
            <button id="exportExcel" class="sections-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
              <i class='bx bxs-file' style="color: #27ae60; font-size: 16px;"></i>
              <span style="margin-left: 8px;">Export as Excel (.csv)</span>
            </button>
            <button id="exportWord" class="sections-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
              <i class='bx bxs-file-doc' style="color: #2980b9; font-size: 16px;"></i>
              <span style="margin-left: 8px;">Export as Word (.docx)</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State (hidden by default) -->
    <div class="sections-empty-state" id="sectionsEmptyState" style="display: none;">
      <div class="sections-empty-icon">
        <i class='bx bx-layer'></i>
      </div>
      <h3>No Sections Found</h3>
      <p>Get started by adding your first section</p>
      <button class="sections-btn-primary" id="btnAddFirstSection">
        <i class='bx bx-plus'></i> Add Section
      </button>
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


