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
  <title>Students | OSAS System</title>
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="<?= View::asset('styles/students.css') ?>">
</head>
<body>
  
<!-- Students.html -->
<main id="Students-page">

  <!-- HEADER -->
  <div class="Students-head-title">
    <div class="Students-left">
      <h1>Students</h1>
      <p class="Students-subtitle">Manage all student records in the institution</p>
      <ul class="Students-breadcrumb">
        <li><a href="#">Dashboard</a></li>
        <li><i class='bx bx-chevron-right'></i></li>
        <li><a class="active" href="#">Students Data</a></li>
      </ul>
    </div>

    <div class="Students-header-actions">
      <div class="Students-button-group">
        <button id="btnImportStudents" class="Students-btn outline small">
          <i class='bx bx-upload'></i>
          <span>Import</span>
        </button>
        <button id="btnExportStudents" class="Students-btn outline small">
          <i class='bx bx-download'></i>
          <span>Export</span>
        </button>
      </div>
    </div>
  </div>

  <!-- STATS CARDS -->
  <div class="Students-stats-overview">
    <div class="Students-stat-card">
      <div class="Students-stat-icon">
        <i class='bx bx-user'></i>
      </div>
      <div class="Students-stat-content">
        <h3 class="Students-stat-title">Total Students</h3>
        <div class="Students-stat-value" id="totalStudents">0</div>
        <div class="Students-stat-change positive">
          <i class='bx bx-up-arrow-alt'></i>
          <span>+25 this month</span>
        </div>
      </div>
    </div>

    <div class="Students-stat-card">
      <div class="Students-stat-icon">
        <i class='bx bx-user-check'></i>
      </div>
      <div class="Students-stat-content">
        <h3 class="Students-stat-title">Active</h3>
        <div class="Students-stat-value" id="activeStudents">0</div>
        <div class="Students-stat-percentage" id="activeStudentsPct">0%</div>
      </div>
    </div>

    <div class="Students-stat-card">
      <div class="Students-stat-icon">
        <i class='bx bx-user-x'></i>
      </div>
      <div class="Students-stat-content">
        <h3 class="Students-stat-title">Inactive</h3>
        <div class="Students-stat-value" id="inactiveStudents">0</div>
        <div class="Students-stat-percentage" id="inactiveStudentsPct">0%</div>
      </div>
    </div>

  
  </div>

  <!-- MAIN CONTENT CARD -->
  <div class="Students-content-card">
    <!-- Table Header -->
    <div class="Students-table-header">
      <div class="Students-header-left">
        <h2 class="Students-table-title">Student List</h2>
        <p class="Students-table-subtitle">All student records and their details</p>
      </div>

      <div class="Students-header-right">
        <div class="Students-search-toggle-row">
          <div class="Students-search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchStudent" placeholder="Search students...">
          </div>

          <!-- View Toggle Buttons -->
          <div class="Students-view-toggle">
            <button class="Students-view-btn" id="viewTable" title="Table View" data-view="table">
              <i class='bx bx-table'></i>
            </button>
            <button class="Students-view-btn" id="viewGrid" title="Grid View" data-view="grid">
              <i class='bx bx-grid-alt'></i>
            </button>
            <button class="Students-view-btn active" id="viewList" title="List View" data-view="list">
              <i class='bx bx-list-ul'></i>
            </button>
          </div>
        </div>

        <div class="Students-filter-group">
          <select id="StudentsFilterSelect" class="Students-filter-select">
            <option value="all">All Status</option>
            <option value="active">Active Only</option>
            <option value="inactive">Inactive</option>
            <option value="graduating">Graduating</option>
            <option value="archived">Archived</option>
          </select>

          <select id="StudentsDepartmentFilter" class="Students-filter-select">
            <option value="all">All Departments</option>
            <!-- Departments will be loaded via JS -->
          </select>

          <select id="StudentsSectionFilter" class="Students-filter-select">
            <option value="all">All Sections</option>
            <!-- Sections will be loaded via JS based on department -->
          </select>

          <button class="Students-filter-btn" title="More filters">
            <i class='bx bx-filter-alt'></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Students Table -->
    <div id="StudentsPrintArea" class="Students-table-container">
      <table class="Students-table">
        <thead>
          <tr>
            <th>Image</th>
            <th class="Students-sortable" data-sort="studentId">
              <div class="Students-table-header-content">
                <span>Student ID</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th class="Students-sortable" data-sort="name">
              <div class="Students-table-header-content">
                <span>Name</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th class="Students-sortable" data-sort="department">
              <div class="Students-table-header-content">
                <span>Department</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th class="Students-sortable" data-sort="section">
              <div class="Students-table-header-content">
                <span>Section</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th class="Students-sortable" data-sort="yearlevel">
              <div class="Students-table-header-content">
                <span>Year Level</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th>Contact No</th>
            <th class="Students-sortable" data-sort="status">
              <div class="Students-table-header-content">
                <span>Status</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody id="StudentsTableBody">
          <!-- JS will populate rows from database -->
        </tbody>
      </table>
    </div>

    <!-- Grid Card View -->
    <div id="studentsGridView" class="Students-grid-container" style="display:none;">
      <div id="StudentsGridBody" class="Students-grid"></div>
    </div>

    <!-- List View -->
    <div id="studentsListView" class="Students-list-container" style="display:none;">
      <div id="StudentsListBody" class="Students-list"></div>
    </div>

    <!-- Table Footer -->
    <div class="Students-table-footer">
      <div class="Students-footer-info">
        Showing <span id="showingStudentsCount">4</span> of <span id="totalStudentsCount">250</span> students
      </div>
      <div class="Students-pagination">
        <button class="Students-pagination-btn" disabled>
          <i class='bx bx-chevron-left'></i>
        </button>
        <button class="Students-pagination-btn active">1</button>
        <button class="Students-pagination-btn">2</button>
        <button class="Students-pagination-btn">3</button>
        <button class="Students-pagination-btn">4</button>
        <button class="Students-pagination-btn">5</button>
        <button class="Students-pagination-btn">
          <i class='bx bx-chevron-right'></i>
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL -->
  <div id="StudentsModal" class="Students-modal">
    <div class="Students-modal-overlay" id="StudentsModalOverlay"></div>
    <div class="Students-modal-container">
      <div class="Students-modal-header">
        <h2 id="StudentsModalTitle">
          <i class='bx bxs-group'></i>
          <span>Student</span>
        </h2>
        <button class="Students-close-btn" id="closeStudentsModal">
          <i class='bx bx-x'></i>
        </button>
      </div>

      <form id="StudentsForm">
        <div class="Students-form-row">
          <div class="Students-form-group">
            <label for="studentId">Student ID</label>
            <input type="text" id="studentId" name="studentId" required placeholder="e.g., 2023-001">
          </div>
          
          <div class="Students-form-group">
            <label for="studentStatus">Status</label>
            <select id="studentStatus" name="studentStatus" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="graduating">Graduating</option>
            </select>
          </div>
        </div>

        <div class="Students-form-group">
          <label for="studentImage">Student Photo</label>
          <div class="Students-image-upload">
            <div class="Students-image-preview" id="imagePreview">
              <div class="Students-preview-placeholder">
                <i class='bx bx-user'></i>
                <span>Upload photo</span>
              </div>
              <img class="Students-preview-img" style="display:none" alt="Preview">
            </div>
            <input type="file" id="studentImage" name="studentImage" accept="image/*" class="Students-file-input">
            <button type="button" class="Students-upload-btn" id="uploadImageBtn">
              <i class='bx bx-upload'></i> Choose Photo
            </button>
          </div>
        </div>

        <div class="Students-form-row">
          <div class="Students-form-group">
            <label for="firstName">First Name</label>
            <input type="text" id="firstName" name="firstName" required placeholder="e.g., John">
          </div>
          
          <div class="Students-form-group">
            <label for="lastName">Last Name</label>
            <input type="text" id="lastName" name="lastName" required placeholder="e.g., Doe">
          </div>
        </div>

        <div class="Students-form-group">
          <label for="middleName">Middle Name (Optional)</label>
          <input type="text" id="middleName" name="middleName" placeholder="e.g., Michael">
        </div>

        <div class="Students-form-row">
          <div class="Students-form-group">
            <label for="studentEmail">Email Address</label>
            <input type="email" id="studentEmail" name="studentEmail" required placeholder="student@example.com">
          </div>
          
          <div class="Students-form-group">
            <label for="studentContact">Contact Number</label>
            <input type="tel" id="studentContact" name="studentContact" required placeholder="+63 912 345 6789">
          </div>
        </div>

        <div class="Students-form-row">
          <div class="Students-form-group">
            <label for="studentDept">Department</label>
            <select id="studentDept" name="studentDept" required>
              <option value="">Select Department</option>
              <!-- Options loaded from database via JavaScript -->
            </select>
          </div>
          
          <div class="Students-form-group">
            <label for="studentSection">Section</label>
            <select id="studentSection" name="studentSection" required>
              <option value="">Select Section</option>
              <!-- Options loaded from database based on selected department -->
            </select>
          </div>
        </div>

        <div class="Students-form-row">
          <div class="Students-form-group">
            <label for="studentYearlevel">Year Level</label>
            <select id="studentYearlevel" name="studentYearlevel" required>
              <option value="">Select Year Level</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
              <option value="5th Year">5th Year</option>
            </select>
          </div>
        </div>

        <div class="Students-form-group">
          <label for="studentAddress">Address</label>
          <textarea id="studentAddress" name="studentAddress" rows="2" placeholder="Complete address..."></textarea>
        </div>

        <div class="Students-form-actions">
          <button type="button" class="Students-btn-outline" id="cancelStudentsModal">Cancel</button>
          <button type="submit" class="Students-btn-primary">Save Student</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Empty State -->
  <div class="Students-empty-state" id="StudentsEmptyState" style="display: none;">
    <div class="Students-empty-icon">
      <i class='bx bx-user'></i>
    </div>
    <h3>No Students Found</h3>
    <p>Get started by importing students data</p>
    <button class="Students-btn-primary" id="btnImportFirstStudents">
      <i class='bx bx-upload'></i> Import Students
    </button>
  </div>

  <!-- Export Modal -->
  <div id="ExportStudentsModal" class="modal">
    <div class="modal-overlay" id="ExportModalOverlay"></div>
    <div class="modal-container" style="max-width: 360px;">
      <div class="modal-header">
        <h2>
          <i class='bx bx-download'></i>
          <span>Export Students</span>
        </h2>
        <button class="close-btn" id="closeExportModal">
          <i class='bx bx-x'></i>
        </button>
      </div>
      <div class="modal-body" style="padding: 16px;">
        <p style="margin-bottom: 14px; color: #666; font-size: 11px;">Select your preferred format to download the student records.</p>
        <div class="export-options" style="display: flex; flex-direction: column; gap: 8px;">
          <button id="exportPDF" class="Students-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file-pdf' style="color: #e74c3c; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as PDF</span>
          </button>
          <button id="exportExcel" class="Students-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file' style="color: #10B981; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as Excel (.xlsx)</span>
          </button>
          <button id="exportWord" class="Students-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file-doc' style="color: #2563EB; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as Word (.docx)</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Student Profile Modal -->
  <div id="StudentProfileModal" class="Students-modal">
    <div class="Students-modal-overlay" id="ProfileModalOverlay"></div>
    <div class="Students-modal-container" style="max-width: 600px;">
      <div class="Students-modal-header">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bxs-user-detail' style="font-size:20px;color:#d4af37;"></i>
        </div>
        <div style="flex:1;">
          <h2 style="margin:0;font-size:1.05rem;font-weight:700;">
            <span>Student Profile</span>
          </h2>
          <p style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">View student information.</p>
        </div>
        <button class="Students-close-btn" id="closeProfileModal">
          <i class='bx bx-x'></i>
        </button>
      </div>
      <div class="Students-modal-body">
        <div class="profile-details-wrapper" style="padding: 20px;">
          <div class="profile-header" style="display: flex; gap: 20px; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
            <div class="profile-avatar-large">
              <img id="profileAvatar" src="" alt="Avatar" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--gold);">
            </div>
            <div class="profile-main-info">
              <h3 id="profileFullName" style="font-size: 1.3rem; color: var(--dark); margin-bottom: 4px; font-weight: 700;"></h3>
              <p id="profileId" style="color: var(--gold); font-weight: 600; font-size: 0.9rem; margin-bottom: 6px;"></p>
              <span id="profileStatusBadge" class="status-badge"></span>
            </div>
          </div>
          
          <div class="profile-info-grid" style="display: grid; grid-template-columns: 1fr; gap: 14px;">
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Department</label>
              <p id="profileDept" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0;"></p>
            </div>
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Section</label>
              <p id="profileSection" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0;"></p>
            </div>
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Year Level</label>
              <p id="profileYear" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0;"></p>
            </div>
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Email</label>
              <p id="profileEmail" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0; word-break: break-all;"></p>
            </div>
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Contact</label>
              <p id="profileContact" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0;"></p>
            </div>
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Enrolled</label>
              <p id="profileDate" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0;"></p>
            </div>
            <div class="info-group" style="display:grid; grid-template-columns: 120px 1fr; align-items:baseline;">
              <label style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: 600;">Address</label>
              <p id="profileAddress" style="font-weight: 500; color: var(--dark); font-size: 0.88rem; margin:0; line-height: 1.5;"></p>
            </div>
          </div>
        </div>
      </div>
      <div class="Students-modal-footer" style="padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end;">
        <button type="button" class="Students-btn outline" id="closeProfileBtn">Close</button>
      </div>
    </div>
  </div>

  <!-- Import Modal -->
  <div id="ImportStudentsModal" class="Students-modal">
    <div class="Students-modal-overlay" id="ImportModalOverlay"></div>
    <div class="Students-modal-container" style="max-width: 500px;">
      <div class="Students-modal-header">
        <h2>
          <i class='bx bx-upload'></i>
          <span>Import Students Data</span>
        </h2>
        <button class="Students-close-btn" id="closeImportModal">
          <i class='bx bx-x'></i>
        </button>
      </div>
      <div class="Students-modal-body" style="padding: 20px;">
        <p style="margin-bottom: 20px; color: #666;">Upload an Excel (.xlsx, .xls) or CSV file containing student records to sync with the database.</p>

        <form id="ImportStudentsForm" enctype="multipart/form-data">
          <div class="Students-form-group">
            <label for="enrollmentList">Select File</label>
            <div class="Students-file-upload-wrapper" style="border: 2px dashed #ddd; padding: 30px; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s ease;" id="dropZone">
              <i class='bx bx-cloud-upload' style="font-size: 48px; color: #aaa; margin-bottom: 10px; display: block;"></i>
              <span style="color: #888; display: block; margin-bottom: 10px;">Drag and drop or click to browse</span>
              <span id="selectedFileName" style="color: var(--gold); font-weight: 600; display: none;"></span>
              <input type="file" id="enrollmentList" name="enrollmentList" accept=".csv, .xlsx, .xls" style="display: none;">
            </div>
          </div>
          
          <div class="Students-form-actions" style="margin-top: 25px;">
            <button type="button" class="Students-btn outline" id="cancelImportBtn">Cancel</button>
            <button type="submit" class="Students-btn primary" id="submitImportBtn" disabled>
              <i class='bx bx-check-circle'></i> Start Import
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modern Alert/Confirm Modal -->
  <div id="ModernAlertModal" class="Students-modal">
    <div class="Students-modal-overlay" id="ModernAlertOverlay"></div>
    <div class="Modern-modal-container">
      <div id="ModernAlertIcon" class="Modern-modal-icon warning">
        <i class='bx bx-help-circle'></i>
      </div>
      <h2 id="ModernAlertTitle" class="Modern-modal-title">Confirm Action</h2>
      <p id="ModernAlertMessage" class="Modern-modal-message">Are you sure you want to proceed?</p>
      <div id="ModernAlertStats" class="result-stats" style="display: none;">
        <div class="stat-item">
          <span class="stat-value" id="statNew">0</span>
          <span class="stat-label">New</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="statUpdated">0</span>
          <span class="stat-label">Updated</span>
        </div>
        <div class="stat-item">
          <span class="stat-value" id="statSkipped">0</span>
          <span class="stat-label">Skipped</span>
        </div>
      </div>
      <div class="Modern-modal-actions" id="ModernAlertActions">
        <button id="ModernAlertCancel" class="Modern-modal-btn cancel">Cancel</button>
        <button id="ModernAlertConfirm" class="Modern-modal-btn confirm">Confirm</button>
      </div>
    </div>
  </div>

</main>

<!-- Load Libraries for Export -->
<script src="<?= View::asset('js/lib/jspdf.umd.min.js') ?>"></script>
<script src="<?= View::asset('js/lib/jspdf.plugin.autotable.min.js') ?>"></script>
<script src="<?= View::asset('js/lib/FileSaver.js') ?>"></script>

</body>
</html>


