<?php
require_once __DIR__ . '/../../core/View.php';
?>
<link rel="stylesheet" href="<?= View::asset('styles/violation.css') ?>">

<main id="Violations-page" class="uv-page">

  <!-- ── PAGE HEADER ── -->
  <div class="uv-header">
    <div class="uv-header__left">
      <div>
        <h1 class="uv-header__title">My Violations</h1>
        <p class="uv-header__desc">View and track your violation records in the institution</p>
        <nav class="uv-breadcrumb">
          <a href="#">Dashboard</a>
          <i class='bx bx-chevron-right'></i>
          <span>My Violations</span>
        </nav>
      </div>
    </div>
    <a href="#" class="uv-dl-btn" id="btnDownloadReport">
      <i class='bx bxs-download'></i> Download Report
    </a>
  </div>

  <!-- ── STAT CARDS ── -->
  <div class="uv-stats">
    <div class="uv-stat uv-stat--total">
      <div class="uv-stat__icon"><i class='bx bxs-calendar-check'></i></div>
      <div class="uv-stat__body">
        <span class="uv-stat__lbl">All Time Total</span>
        <span class="uv-stat__val" id="statTotal">0</span>
      </div>
    </div>
    <div class="uv-stats-type-container" id="uvTypeStatsContainer">
      <p class="uv-stats-loading">Loading violation types...</p>
    </div>
  </div>

  <!-- ── VIOLATION HISTORY CARD ── -->
  <div class="uv-card">

    <!-- Card Header -->
    <div class="uv-card__head">
      <div class="uv-card__title-wrap">
        <span class="uv-card__icon-badge"><i class='bx bx-list-ul'></i></span>
        <div>
          <h2 class="uv-card__title">Violation History</h2>
          <p class="uv-card__sub">Showing <span id="showingViolationsCount">0</span> records</p>
        </div>
      </div>
      <div class="uv-card__controls">
        <div class="uv-search">
          <i class='bx bx-search'></i>
          <input type="text" id="searchViolation" placeholder="Search…">
        </div>
        <select id="timePeriodFilter" class="uv-select" onchange="filterViolations()">
          <option value="current_month">This Month</option>
          <option value="all">All History</option>
        </select>
        <select id="violationFilter" class="uv-select" onchange="filterViolations()">
          <option value="all">All Types</option>
        </select>
        <select id="statusFilter" class="uv-select" onchange="filterViolations()">
          <option value="all">All Status</option>
          <option value="resolved">Resolved / Permitted</option>
          <option value="warning">Warning</option>
          <option value="disciplinary">Disciplinary</option>
        </select>
        <!-- View Toggle -->
        <div class="Violations-view-toggle">
          <button class="Violations-view-btn" data-view="table" title="Table View" onclick="setUvView('table')">
            <i class='bx bx-table'></i>
          </button>
          <button class="Violations-view-btn" data-view="grid" title="Grid View" onclick="setUvView('grid')">
            <i class='bx bx-grid-alt'></i>
          </button>
          <button class="Violations-view-btn active" data-view="list" title="List View" onclick="setUvView('list')">
            <i class='bx bx-list-ul'></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Table View -->
    <div id="uvTableView" style="display:none;">
      <div class="uv-table-wrap">
        <table class="uv-table Violations-table">
          <thead>
            <tr>
              <th>Violation Type</th>
              <th>Offense Level</th>
              <th>Date</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="violationsTableBody">
            <tr><td colspan="5"><div class="uv-loading"><div class="uv-spinner"></div><span>Loading…</span></div></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- List View -->
    <div id="uvListView" style="display:block;">
      <div id="violationsListBody" style="padding: 12px 16px; display:flex; flex-direction:column; gap:10px;">
        <div class="uv-loading"><div class="uv-spinner"></div><span>Loading violations…</span></div>
      </div>
    </div>

    <!-- Grid View -->
    <div id="uvGridView" style="display:none;">
      <div id="violationsGridBody" style="padding: 12px 16px; display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:12px;">
      </div>
    </div>

    <!-- Pagination Footer -->
    <div class="uv-table-footer">
      <div class="uv-pagination-info">
        Showing <span id="uvShowingStart">0</span>–<span id="uvShowingEnd">0</span> of <span id="uvTotalFiltered">0</span> records
      </div>
      <div class="uv-pagination" id="uvPagination">
      </div>
    </div>

  </div><!-- /uv-card -->

  <!-- ── DETAILS MODAL ── -->
  <div id="ViolationDetailsModal" class="Violations-modal" style="display:none;">
    <div class="Violations-modal-overlay" id="modalOverlay" onclick="closeViolationModal()"></div>
    <div class="Violations-modal-container">
      <div class="Violations-modal-header">
        <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,215,0,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class='bx bxs-info-circle' style="font-size:20px;color:#d4af37;"></i>
        </div>
        <div style="flex:1;">
          <h2 style="margin:0;font-size:1.05rem;font-weight:700;display:block;"><span>Violation Details</span></h2>
          <p style="margin:3px 0 0;font-size:0.75rem;color:#6b7280;font-weight:400;">View your violation record details.</p>
        </div>
        <button class="Violations-close-btn" onclick="closeViolationModal()"><i class='bx bx-x'></i></button>
      </div>
      <div class="violation-details-content">
        <div class="case-header">
          <span class="case-id">Case: <span id="detailCaseId">-</span></span>
          <span class="case-status-badge" id="detailStatusBadge">-</span>
        </div>
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
              <span class="student-id">ID: <span id="detailStudentId">-</span></span>
              <span class="student-dept badge" id="detailStudentDept">-</span>
              <span class="student-section">Section: <span id="detailStudentSection">-</span></span>
            </div>
            <div class="student-contact"><i class='bx bx-phone'></i> <span id="detailStudentContact">-</span></div>
          </div>
        </div>
        <div class="violation-details-grid">
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
                <td><span class="detail-value badge" id="detailViolationType">-</span></td>
                <td><span class="detail-value badge warning" id="detailViolationLevel">-</span></td>
                <td><span class="detail-value" id="detailDateTime">-</span></td>
                <td><span class="detail-value" id="detailLocation">-</span></td>
                <td><span class="detail-value" id="detailReportedBy">-</span></td>
                <td><span class="detail-value badge warning" id="detailStatus">-</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="violation-notes-section">
          <h4>Violation Description</h4>
          <div class="notes-content"><p id="detailNotes">-</p></div>
        </div>
        <div class="violation-evidence-section" id="evidenceSection">
          <h4>Evidence / Attachments</h4>
          <div id="detailAttachments" class="attachments-grid">
            <p class="no-attachments">No attachments available.</p>
          </div>
        </div>
        <div class="violation-notes-section" id="resolutionSection" style="display:none;">
          <h4>Resolution</h4>
          <div class="notes-content"><p id="detailResolution">-</p></div>
        </div>
        <div class="violation-history">
          <h4>Violation History</h4>
          <div class="timeline" id="detailTimeline">
            <p style="color:var(--dark-grey);font-size:.82rem;">No history available.</p>
          </div>
        </div>
        <div class="Violations-form-actions">
          <button id="requestSlipBtn" class="Violations-btn warning" onclick="handleStudentSlipRequest()" style="display:none;">
            <i class='bx bx-paper-plane'></i> Request Receipt
          </button>
          <button id="downloadSlipBtn" class="Violations-btn success" onclick="printViolationSlip()" style="display:none;">
            <i class='bx bxs-download'></i> Download Slip
          </button>
          <button class="Violations-btn-outline" onclick="closeViolationModal()">Close</button>
        </div>
      </div>
    </div>
  </div>

</main>
