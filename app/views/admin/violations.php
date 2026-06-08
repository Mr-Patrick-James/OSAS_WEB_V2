<?php
require_once __DIR__ . '/../../core/View.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Violations | OSAS System</title>
  <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="<?= View::asset('styles/violation.css') ?>">
</head>
<body>
  
<!-- Violations.html -->
<main id="Violations-page">
  <!-- HEADER -->
  <div class="Violations-head-title">
    <div class="Violations-left">
      <h1>Violations</h1>
      <p class="Violations-subtitle">Manage and track student violations in the institution</p>
      <ul class="Violations-breadcrumb">
        <li><a href="#">Dashboard</a></li>
        <li><i class='bx bx-chevron-right'></i></li>
        <li><a class="active" href="#">Violations Data</a></li>
      </ul>
    </div>

    <div class="Violations-header-actions">
      <div class="Violations-button-group">
        <button id="btnMonthlyReset" class="Violations-btn outline small warning" title="Archive old violations and reset student levels">
          <i class='bx bx-reset'></i>
          <span>Monthly Reset</span>
        </button>
        <!-- Import button removed -->
        <button id="btnExportViolations" class="Violations-btn outline small">
          <i class='bx bx-download'></i>
          <span>Export</span>
        </button>
      </div>
      <div class="Violations-button-group">
        <button id="btnAddViolations" class="Violations-btn primary">
          <i class='bx bx-plus'></i> Record Violation
        </button>
      </div>
    </div>
  </div>

  <!-- STATS CARDS -->
  <div class="Violations-stats-overview">
    <div class="Violations-stat-card">
      <div class="Violations-stat-icon">
        <i class='bx bx-error-circle'></i>
      </div>
      <div class="Violations-stat-content">
        <h3 class="Violations-stat-title">Total Violations</h3>
        <div class="Violations-stat-value" id="totalViolations">0</div>
        <div class="Violations-stat-change negative">
          <i class='bx bx-up-arrow-alt'></i>
          <span id="totalViolationsWeek">+0 this week</span>
        </div>
      </div>
    </div>

    <div class="Violations-stat-card">
      <div class="Violations-stat-icon">
        <i class='bx bx-check-circle'></i>
      </div>
      <div class="Violations-stat-content">
        <h3 class="Violations-stat-title">Permitted</h3>
        <div class="Violations-stat-value" id="resolvedViolations">0</div>
        <div class="Violations-stat-percentage" id="resolvedViolationsPct">0%</div>
      </div>
    </div>

    <div class="Violations-stat-card">
      <div class="Violations-stat-icon">
        <i class='bx bx-user-voice'></i>
      </div>
          <div class="Violations-stat-content">
        <h3 class="Violations-stat-title">Warning</h3>
        <div class="Violations-stat-value" id="pendingViolations">0</div>
        <div class="Violations-stat-percentage" id="pendingViolationsPct">0%</div>
      </div>
    </div>

    <div class="Violations-stat-card">
      <div class="Violations-stat-icon">
        <i class='bx bx-time-five'></i>
      </div>
      <div class="Violations-stat-content">
        <h3 class="Violations-stat-title">Disciplinary</h3>
        <div class="Violations-stat-value" id="disciplinaryViolations">0</div>
        <div class="Violations-stat-percentage" id="disciplinaryViolationsPct">0%</div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT CARD -->
  <div class="Violations-content-card">
    <!-- Table Header -->
    <div class="Violations-table-header">
      <div class="Violations-header-left">
        <h2 class="Violations-table-title" id="violationsTableTitle">Violations List</h2>
        <div class="Violations-tabs">
          <button class="Violations-tab-btn active" data-view="current">Current Month</button>
          <button class="Violations-tab-btn" data-view="archive">Archive</button>
          <button class="Violations-tab-btn" data-view="requests">Slip Requests</button>
        </div>
      </div>

      <div class="Violations-header-right">
        <!-- Current Month Filters -->
        <div id="currentFilters" class="Violations-filter-group">
          <div class="Violations-search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchViolation" placeholder="Search violations...">
          </div>

          <div class="Violations-date-filter">
            <input type="date" id="ViolationDateFrom" class="Violations-filter-date" title="From Date">
            <span>to</span>
            <input type="date" id="ViolationDateTo" class="Violations-filter-date" title="To Date">
          </div>

          <select id="ViolationsFilter" class="Violations-filter-select">
            <option value="all">All Departments</option>
            <!-- Departments will be loaded via JS -->
          </select>

          <select id="ViolationsStatusFilter" class="Violations-filter-select">
            <option value="all">All Status</option>
            <option value="permitted">Resolved / Permitted</option>
            <option value="warning">Warning</option>
            <option value="disciplinary">Disciplinary</option>
            <option value="resolved">Resolved</option>
          </select>
        </div>

        <!-- Archive Filters (Initially Hidden) -->
        <div id="archiveFilters" class="Violations-filter-group" style="display: none;">
          <div class="Violations-search-box">
            <i class='bx bx-search'></i>
            <input type="text" id="searchViolationArchive" placeholder="Search archive...">
          </div>

          <div class="Violations-date-filter">
            <input type="date" id="ArchiveDateFrom" class="Violations-filter-date" title="From Date">
            <span>to</span>
            <input type="date" id="ArchiveDateTo" class="Violations-filter-date" title="To Date">
          </div>

          <select id="ArchiveDeptFilter" class="Violations-filter-select">
            <option value="all">All Departments</option>
            <!-- Departments will be loaded via JS -->
          </select>

          <select id="ArchiveYearFilter" class="Violations-filter-select" style="min-width:90px;">
            <option value="all">All Years</option>
            <?php
            $currentYear = (int)date('Y');
            for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
                $selected = ($y == $currentYear) ? 'selected' : '';
                echo "<option value='$y' $selected>$y</option>";
            }
            ?>
          </select>
          
          <select id="ArchiveMonthFilter" class="Violations-filter-select" style="min-width:90px;">
            <option value="all">All Months</option>
            <?php
            for ($i = 1; $i <= 12; $i++) {
                $month = date('M', mktime(0, 0, 0, $i, 1));
                $selected = ($i == date('n')) ? 'selected' : '';
                echo "<option value='$i' $selected>$month</option>";
            }
            ?>
          </select>
        </div>

        <button class="Violations-filter-btn" title="More filters">
          <i class='bx bx-filter-alt'></i>
        </button>

        <!-- Display Mode Toggle: Latest per student vs All records -->
        <div class="Violations-view-toggle" id="displayModeToggle">
          <button class="Violations-display-btn active" data-display="latest" title="Latest per student">
            <i class='bx bx-user'></i>
          </button>
          <button class="Violations-display-btn" data-display="all" title="All violations (full history)">
            <i class='bx bx-history'></i>
          </button>
        </div>

        <!-- View Toggle -->
        <div class="Violations-view-toggle">
          <button class="Violations-view-btn" data-view="table" title="Table View">
            <i class='bx bx-table'></i>
          </button>
          <button class="Violations-view-btn" data-view="grid" title="Grid View">
            <i class='bx bx-grid-alt'></i>
          </button>
          <button class="Violations-view-btn active" data-view="list" title="List View">
            <i class='bx bx-list-ul'></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Violations Table -->
    <div class="Violations-table-container" id="violationsTableView">
      <table class="Violations-table">
        <thead>
          <tr>
            <th>Student</th>
            <th class="Violations-sortable" data-sort="studentId">
              <div class="Violations-table-header-content">
                <span>Student ID</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th class="Violations-sortable" data-sort="name">
              <div class="Violations-table-header-content">
                <span>Violation Type</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th>Level</th>
            <th class="Violations-sortable" data-sort="department">
              <div class="Violations-table-header-content">
                <span>Department</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th>Section</th>
            <th>Year Level</th>
            <th class="Violations-sortable" data-sort="date">
              <div class="Violations-table-header-content">
                <span>Date Reported</span>
                <i class='bx bx-sort'></i>
              </div>
            </th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody id="ViolationsTableBody">
          <!-- Data will be loaded dynamically -->
        </tbody>
      </table>
    </div>

    <!-- Grid / Card View -->
    <div id="violationsGridView" class="Violations-grid-container" style="display:none;">
      <div id="ViolationsGridBody" class="Violations-grid"></div>
    </div>

    <!-- List View -->
    <div id="violationsListView" class="Violations-list-container" style="display:none;">
      <div id="ViolationsListBody" class="Violations-list"></div>
    </div>

    <!-- Table Footer -->
    <div class="Violations-table-footer">
      <div class="Violations-footer-info">
        Showing <span id="showingViolationsCount">4</span> of <span id="totalViolationsCount">48</span> violations
      </div>
      <div class="Violations-pagination">
        <button class="Violations-pagination-btn" disabled>
          <i class='bx bx-chevron-left'></i>
        </button>
        <button class="Violations-pagination-btn active">1</button>
        <button class="Violations-pagination-btn">2</button>
        <button class="Violations-pagination-btn">3</button>
        <button class="Violations-pagination-btn">4</button>
        <button class="Violations-pagination-btn">5</button>
        <button class="Violations-pagination-btn">
          <i class='bx bx-chevron-right'></i>
        </button>
      </div>
    </div>

    <!-- Slip Requests (Initially Hidden) -->
    <div id="slipRequestsContainer" style="display: none;">
      <div class="Violations-table-container" id="slipRequestsTableView">
        <table class="Violations-table" id="slipRequestsTable">
          <thead>
            <tr>
              <th>Student</th>
              <th>Student ID</th>
              <th>Request Date</th>
              <th>Requested By</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="slipRequestsTableBody">
          </tbody>
        </table>
      </div>
      <div class="Violations-grid-container" id="slipRequestsGridView" style="display:none;">
        <div class="Violations-grid" id="slipRequestsGridBody"></div>
      </div>
      <div id="slipRequestsListView" style="display:none;"></div>
    </div>
  </div>

  <!-- VIOLATION RECORDING MODAL -->
  <div id="ViolationRecordModal" class="Violations-modal">
    <div class="Violations-modal-overlay" id="ViolationModalOverlay"></div>
    <div class="Violations-modal-container">
      <div class="Violations-modal-header">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bxs-notepad' style="font-size:28px;color:#d4af37;"></i>
        </div>
        <div style="flex:1;">
          <h2 id="violationModalTitle" style="margin:0;font-size:1.05rem;font-weight:700;color:#1a1a1a;display:block;">
            <span>Record New Violation</span>
          </h2>
          <p style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">Search for a student and record their violation details.</p>
        </div>
        <button class="Violations-close-btn" id="closeRecordModal">
          <i class='bx bx-x'></i>
        </button>
        <div class="form-progress" id="violationFormProgress"></div>
      </div>

      <form id="ViolationRecordForm" enctype="multipart/form-data">
        <!-- Student Search Section -->
        <div class="Violations-form-group">
            <label for="studentSearch">Search Student</label>
          <div class="student-search-wrapper">
            <input type="text" id="studentSearch" placeholder="Search by Student ID or Name...">
            <button type="button" id="searchStudentBtn" class="Violations-search-btn">
              <i class='bx bx-search-alt'></i> Search
            </button>
            <button type="button" class="Violations-refresh-btn" id="refreshStudentsBtn" title="Refresh student data">
              <i class='bx bx-refresh'></i>
            </button>
          </div>
        </div>

        <!-- Student Info Card -->
        <div class="violation-student-info-card selected">
          <div class="violation-student-image">
            <img id="modalStudentImage" 
                 src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='40' r='20' fill='%23ccc'/%3E%3Ccircle cx='50' cy='100' r='40' fill='%23ccc'/%3E%3C/svg%3E" 
                 alt="Student Image"
                 onerror="this.src='https://ui-avatars.com/api/?name=Student&background=ffd700&color=333&size=80'">
          </div>
          <div class="violation-student-details">
            <div class="violation-detail-row">
              <span class="violation-detail-label">Student ID:</span>
              <span id="modalStudentId" class="violation-detail-value">2023-001</span>
            </div>
            <div class="violation-detail-row">
              <span class="violation-detail-label">Name:</span>
              <span id="modalStudentName" class="violation-detail-value">John Michael Doe</span>
            </div>
            <div class="violation-detail-row">
              <span class="violation-detail-label">Department:</span>
              <span id="modalStudentDept" class="violation-detail-value">BS Information Technology</span>
            </div>
            <div class="violation-detail-row">
              <span class="violation-detail-label">Section:</span>
              <span id="modalStudentSection" class="violation-detail-value">BSIT-3A</span>
            </div>
            <div class="violation-detail-row">
              <span class="violation-detail-label">Year Level:</span>
              <span id="modalStudentYearlevel" class="violation-detail-value">3rd Year</span>
            </div>
            <div class="violation-detail-row">
              <span class="violation-detail-label">Contact:</span>
              <span id="modalStudentContact" class="violation-detail-value">+63 912 345 6789</span>
            </div>
          </div>
        </div>

        <!-- Violation Type Selection -->
        <div class="violation-type-section">
          <h3>Violation Type</h3>
          <div class="violation-types" id="violationTypesContainer">
            <!-- Loaded dynamically via JS -->
            <p style="text-align: center; color: #666; width: 100%;">Loading violation types...</p>
          </div>
        </div>

        <!-- Violation Level Selection -->
        <div class="violation-level-section">
          <h3>Violation Level</h3>
          <div class="violation-level-buttons" id="violationLevelsContainer">
            <!-- Loaded dynamically via JS -->
            <p style="text-align: center; color: #666; width: 100%;">Select a violation type first</p>
          </div>
        </div>

        <!-- Additional Details -->
        <div class="violation-details-section">
          <div class="Violations-form-row">
            <div class="Violations-form-group">
              <label for="violationDate">Date of Violation</label>
              <input type="date" id="violationDate" name="violationDate">
            </div>

            <div class="Violations-form-group">
              <label for="violationTime">Time of Violation</label>
              <input type="time" id="violationTime" name="violationTime">
            </div>
          </div>

          <div class="Violations-form-group">
            <label for="violationLocation">Location</label>
            <select id="violationLocation" name="violationLocation">
              <option value="campus" selected>Campus</option>
              <option value="canteen">Canteen</option>
              <option value="classroom">Classroom</option>
              <option value="library">Library</option>
              <option value="gym">Gymnasium</option>
              <option value="others">Others</option>
            </select>
          </div>

          <div class="Violations-form-group">
            <label for="reportedBy">Reported By</label>
            <input type="text" id="reportedBy" name="reportedBy" placeholder="Admin Full Name" maxlength="100" value="<?= htmlspecialchars(($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly style="background-color: #f8f9fa; cursor: not-allowed; border: 1px solid #ddd;">
          </div>

          <div class="Violations-form-group" style="position: relative;">
            <label for="violationNotes">Additional Notes</label>
            <textarea id="violationNotes" name="violationNotes" rows="3" placeholder="Enter detailed description of the violation..." maxlength="500"></textarea>
          </div>
        </div>

        <!-- Attachments -->
        <div class="violation-attachments">
          <div class="evidence-upload-header">
            <div class="evidence-upload-title">
              <i class='bx bx-camera'></i>
              <span>Evidence / Attachments <em>(Optional)</em></span>
            </div>
            <span class="evidence-count" id="evidenceCount">0 files</span>
          </div>

          <div class="evidence-dropzone" id="evidenceDropzone">
            <input type="file" id="violationAttachment" name="violationAttachment"
                   accept="image/*,.pdf,.doc,.docx" multiple style="display:none">
            <div class="evidence-dropzone-inner" id="evidenceDropzoneInner">
              <i class='bx bx-cloud-upload'></i>
              <p>Drag & drop files here or <button type="button" class="evidence-browse-btn" id="evidenceBrowseBtn">Browse</button></p>
              <span>Supports: JPG, PNG, GIF, WEBP, PDF, DOC — Max 5MB each</span>
            </div>
          </div>

          <!-- Attachment Previews Grid -->
          <div id="attachmentPreviews" class="evidence-previews-grid"></div>
        </div>

        <!-- Action Buttons -->
        <div class="Violations-form-actions">
          <input type="hidden" id="violationStatus" name="status" value="warning">
          <button type="button" class="Violations-btn-outline" id="cancelRecordModal">Cancel</button>
          <button type="button" class="Violations-btn-outline entrance-btn" id="modalEntranceBtn" style="display: none;">
            <i class='bx bx-receipt'></i> Entrance Slip
          </button>
          <button type="submit" class="Violations-btn-primary">Record Violation</button>
        </div>
      </form>
    </div>
  </div>

  <!-- VIOLATION DETAILS MODAL -->
  <div id="ViolationDetailsModal" class="Violations-modal">
    <div class="Violations-modal-overlay" id="DetailsModalOverlay"></div>
    <div class="Violations-modal-container">
      <div class="Violations-modal-header">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bxs-info-circle' style="font-size:20px;color:#d4af37;"></i>
        </div>
        <div style="flex:1;">
          <h2 style="margin:0;font-size:1.05rem;font-weight:700;display:block;">
            <span>Violation Details</span>
          </h2>
          <p style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">View violation record and student history.</p>
        </div>
        <button class="Violations-close-btn" id="closeDetailsModal">
          <i class='bx bx-x'></i>
        </button>
      </div>

      <div class="violation-details-content">
        <!-- Case Header -->
        <div class="case-header">
          <span class="case-id">Case: <span id="detailCaseId">VIOL-2024-001</span></span>
          <span class="case-status-badge warning" id="detailStatusBadge">Warning</span>
        </div>

        <!-- Student Info -->
        <div class="violation-student-info-card detailed">
          <div class="violation-student-image">
            <img id="detailStudentImage" 
                 src="https://ui-avatars.com/api/?name=Student&background=ffd700&color=333&size=80" 
                 alt="Student"
                 onerror="this.src='https://ui-avatars.com/api/?name=Student&background=ffd700&color=333&size=80'">
          </div>
          <div class="violation-student-details">
            <h3 id="detailStudentName">Student Name</h3>
            <div class="student-meta">
              <span class="student-id">ID: <span id="detailStudentId">2023-001</span></span>
              <span class="student-dept badge bsis" id="detailStudentDept">BSIS</span>
              <span class="student-section">Section: <span id="detailStudentSection">N/A</span></span>
            </div>
            <div class="student-contact">
              <i class='bx bx-phone'></i> <span id="detailStudentContact">N/A</span>
            </div>
          </div>
        </div>

        <!-- Violation Details -->
        <div class="violation-details-grid">
          <div class="vd-table-scroll">
            <table class="vd-table">
              <thead>
                <tr>
                  <th>Violation Type</th>
                  <th>Level</th>
                  <th>Date &amp; Time</th>
                  <th>Location</th>
                  <th>Reported By</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><span id="detailViolationType">-</span></td>
                  <td><span id="detailViolationLevel">-</span></td>
                  <td><span id="detailDateTime">-</span></td>
                  <td><span id="detailLocation">-</span></td>
                  <td><span id="detailReportedBy">-</span></td>
                  <td><span id="detailStatus">-</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes Section -->
        <div class="violation-notes-section">
          <h4>Violation Description</h4>
          <div class="notes-content">
            <p id="detailNotes">No notes available.</p>
          </div>
        </div>

        <!-- Evidence Section REMOVED — click the Evidence badge in Violation History to view -->

        <!-- Evidence Popup (shown when clicking Evidence badge in history) -->
        <!-- REMOVED — Evidence badge now opens lightbox directly -->

        <!-- Image Lightbox -->
        <div id="evidenceLightbox" class="evidence-lightbox" style="display:none">
          <div class="evidence-lightbox-overlay" onclick="closeLightbox()"></div>
          <div class="evidence-lightbox-content">
            <div class="evidence-lightbox-topbar">
              <span class="evidence-lightbox-label" id="lightboxLabel">Evidence</span>
              <button class="evidence-lightbox-close" onclick="closeLightbox()"><i class='bx bx-x'></i></button>
            </div>
            <div class="evidence-lightbox-imgwrap">
              <button class="evidence-lightbox-prev" id="lightboxPrev" onclick="lightboxNav(-1)"><i class='bx bx-chevron-left'></i></button>
              <img id="lightboxImg" src="" alt="Evidence">
              <button class="evidence-lightbox-next" id="lightboxNext" onclick="lightboxNav(1)"><i class='bx bx-chevron-right'></i></button>
            </div>
            <div class="evidence-lightbox-caption">
              <span class="evidence-lightbox-counter" id="lightboxCaption">1 / 1</span>
            </div>
          </div>
        </div>

        <!-- History Timeline -->
        <div class="violation-history">
          <h4>Violation History</h4>
          <div class="timeline" id="detailTimeline">
            <!-- Populated dynamically -->
            <p style="color: #6c757d; font-size: 14px;">No history available.</p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="violation-details-actions">
          <button class="Violations-action-btn view" id="detailRecordNewBtn" title="Record New Violation" style="background: #10b981; color: white;">
            <i class='bx bx-plus'></i> Record New
          </button>
          <button class="Violations-action-btn resolve" id="detailResolveBtn" title="Mark Resolved">
            <i class='bx bx-check'></i> Mark Resolved
          </button>
          <button class="Violations-action-btn print" id="detailPrintSlipBtn" title="Print Entrance Slip" style="background: #f59e0b; color: white;">
            <i class='bx bx-file'></i> Print Slip
          </button>
          <button class="Violations-action-btn resolve" id="detailApproveSlipBtn" title="Approve Slip Download">
            <i class='bx bx-check-shield'></i> Approve Slip
          </button>
          <button class="Violations-action-btn outline" id="detailDenySlipBtn" title="Deny Slip Download">
            <i class='bx bx-block'></i> Deny Slip
          </button>
          <button class="Violations-action-btn print" id="detailPrintBtn" title="Print">
            <i class='bx bx-printer'></i> Print Report
          </button>
        </div>
        <div id="detailSlipStatus" style="margin-top: 10px; font-size: 12px; color: #64748b;"></div>
      </div>
    </div>
  </div>

  <!-- MANAGE VIOLATION TYPES & LEVELS MODAL -->
  <div id="ViolationTypesManageModal" class="Violations-modal">
    <div class="Violations-modal-overlay" id="ViolationTypesManageOverlay"></div>
    <div class="Violations-modal-container vt-manage-modal">
      <div class="Violations-modal-header">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bx-list-plus' style="font-size:28px;color:#d4af37;"></i>
        </div>
        <div style="flex:1;">
          <h2 style="margin:0;font-size:1.05rem;font-weight:700;color:#1a1a1a;display:block;">Manage Violation Types &amp; Levels</h2>
          <p style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">Add, edit, or remove violation types and their offense levels.</p>
        </div>
        <button class="Violations-close-btn" id="closeViolationTypesManageModal" type="button">
          <i class='bx bx-x'></i>
        </button>
      </div>

      <div class="vt-manage-body">
        <div class="vt-manage-column">
          <div class="vt-manage-column-header">
            <h3 id="vtManageLeftTitle">Violation Types</h3>
            <div style="display: flex; gap: 6px; align-items: center;">
              <span class="vt-manage-count" id="vtManageTypeCount" style="margin: 0;">0</span>
              <button type="button" class="vt-toggle-view-btn" id="vtToggleStatusesBtn" title="Manage global statuses">
                <i class='bx bx-cog'></i>
              </button>
            </div>
          </div>
          <div id="vtManageTypesContainer">
            <div class="vt-manage-list" id="vtManageTypesList">
              <p class="vt-manage-empty">Loading types...</p>
            </div>
            <div class="vt-manage-add-form">
              <input type="text" id="vtNewTypeName" placeholder="New violation type name..." maxlength="255">
              <button type="button" class="Violations-btn-primary vt-manage-add-btn" id="vtAddTypeBtn">
                <i class='bx bx-plus'></i> Add Type
              </button>
            </div>
          </div>
          <div id="vtManageStatusesContainer" style="display: none;">
            <div class="vt-manage-list" id="vtManageStatusesList">
              <p class="vt-manage-empty">Loading statuses...</p>
            </div>
            <div class="vt-manage-add-form" style="flex-direction: column; gap: 10px;">
              <input type="text" id="vtNewStatusName" placeholder="New status name (e.g. Expulsion)..." maxlength="100">
              <div class="vt-color-presets" id="vtNewStatusColorPresets" style="display: flex; gap: 6px; justify-content: center; padding: 5px; background: #f8f9fa; border-radius: 6px;">
                <!-- Colors will be added by JS -->
              </div>
              <button type="button" class="Violations-btn-primary vt-manage-add-btn" id="vtAddStatusBtn">
                <i class='bx bx-plus'></i> Add Status
              </button>
            </div>
          </div>
        </div>

        <div class="vt-manage-column">
          <div class="vt-manage-column-header">
            <h3 id="vtManageLevelsTitle">Offense Levels</h3>
            <div style="display: flex; gap: 8px; align-items: center;">
              <span class="vt-manage-count" id="vtManageLevelCount">0</span>
              <button type="button" class="vt-save-all-btn" id="vtSaveAllLevelsBtn" style="display: none;">
                Save All
              </button>
            </div>
          </div>
          <div class="vt-manage-list" id="vtManageLevelsList">
            <p class="vt-manage-empty">Select a violation type to view its levels</p>
          </div>
          <div class="vt-manage-add-form" id="vtAddLevelForm" style="display:none; flex-direction: column; gap: 10px;">
            <input type="text" id="vtNewLevelName" placeholder="Level name (e.g. 6th Offense)" maxlength="255">
            <select id="vtNewLevelStatus" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd; font-size: 13px;">
              <!-- Will be populated by JS -->
            </select>
            <button type="button" class="Violations-btn-primary vt-manage-add-btn" id="vtAddLevelBtn">
              <i class='bx bx-plus'></i> Add Level
            </button>
          </div>
        </div>
      </div>

      <div class="Violations-form-actions vt-manage-actions">
        <button type="button" class="Violations-btn-outline" id="cancelViolationTypesManageModal">Close</button>
      </div>
    </div>
  </div>

  <!-- Empty State -->
  <div class="Violations-empty-state" id="ViolationsEmptyState" style="display: none;">
    <div class="empty-state-inner">
      <div class="empty-state-icon">
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="32" cy="32" r="32" fill="rgba(212,175,55,0.08)"/>
          <path d="M20 44L26 38M44 20L38 26M26 20L32 26L38 20M32 38V44M20 32H26M38 32H44" stroke="#D4AF37" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="32" cy="32" r="8" stroke="#D4AF37" stroke-width="2" stroke-dasharray="4 3"/>
        </svg>
      </div>
      <div class="empty-state-text">
        <h3>No Violations Recorded</h3>
        <p>There are no violation records for this period.<br>Start by recording the first violation.</p>
      </div>
      <button class="empty-state-btn" id="btnRecordFirstViolation">
        <i class='bx bx-plus'></i>
        <span>Record First Violation</span>
      </button>
    </div>
  </div>

  <!-- STUDENT DETAILS PANEL (shown when searching by student ID) -->
  <div class="student-details-panel" id="studentDetailsPanel" style="display: none;">
    <div class="student-details-header">
      <h2>Student Violation Details</h2>
      <button class="student-details-close" id="closeStudentDetails">
        <i class='bx bx-x'></i>
      </button>
    </div>

    <div class="student-profile-section">
      <div class="student-profile-card" id="studentProfileCard">
        <!-- Student info will be populated dynamically -->
      </div>

      <div class="student-stats-grid" id="studentStatsGrid">
        <!-- Statistics will be populated dynamically -->
      </div>
    </div>

    <div class="student-violations-section">
      <h3>Violation History</h3>
      <div class="student-violations-timeline" id="studentViolationsTimeline">
        <!-- Violation timeline will be populated dynamically -->
      </div>
    </div>
  </div>

  <!-- Export Modal -->
  <div id="ExportViolationsModal" class="Violations-modal">
    <div class="Violations-modal-overlay" id="ExportModalOverlay"></div>
    <div class="Violations-modal-container" style="max-width: 360px;">
      <div class="Violations-modal-header">
        <h2>
          <i class='bx bx-download'></i>
          <span>Export Violations Data</span>
        </h2>
        <button class="Violations-close-btn" id="closeExportModal">
          <i class='bx bx-x'></i>
        </button>
      </div>
      <div class="Violations-modal-body" style="padding: 16px;">
        <p style="margin-bottom: 14px; color: #666; font-size: 11px;">Select your preferred format to download the violation records.</p>
        <div class="export-options" style="display: flex; flex-direction: column; gap: 8px;">
          <button id="exportPDF" class="Violations-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file-pdf' style="color: #e74c3c; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as PDF</span>
          </button>
          <button id="exportExcel" class="Violations-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
            <i class='bx bxs-file' style="color: #27ae60; font-size: 16px;"></i>
            <span style="margin-left: 8px;">Export as Excel (.csv)</span>
          </button>
          <button id="exportWord" class="Violations-btn outline" style="justify-content: flex-start; width: 100%; padding: 8px 12px; font-size: 11px;">
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

