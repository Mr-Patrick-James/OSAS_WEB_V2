<?php
require_once __DIR__ . '/../../core/View.php';
?>
<!-- ============================================================
     STUDENT DASHBOARD — REDESIGNED UI
     ============================================================ -->
<script>console.log('✅ USER dashcontent.php loaded (redesigned)');</script>

<main class="student-dash">

  <!-- ── WELCOME HEADER ── -->
  <div class="sd-hero">
    <div class="sd-hero__content">
      <div class="sd-hero__left">
        <div class="sd-hero__greeting" id="sdGreeting">Good morning</div>
        <h1 class="sd-hero__name" id="sdStudentName">Student</h1>
        <p class="sd-hero__sub">Here's your compliance overview for today</p>
        <div class="sd-hero__chips">
          <span class="sd-chip sd-chip--date" id="sdTodayDate"></span>
          <span class="sd-chip sd-chip--status" id="sdStatusChip">
            <span class="sd-chip__dot"></span> Loading&hellip;
          </span>
        </div>
      </div>
      <div class="sd-hero__right">
        <div class="sd-hero__ring-wrap">
          <svg class="sd-ring" viewBox="0 0 120 120">
            <circle class="sd-ring__track" cx="60" cy="60" r="50"/>
            <circle class="sd-ring__fill" id="sdRingFill" cx="60" cy="60" r="50"
                    stroke-dasharray="314" stroke-dashoffset="314"/>
          </svg>
          <div class="sd-ring__label">
            <span class="sd-ring__pct" id="sdRingPct">—</span>
            <span class="sd-ring__txt">Compliance</span>
          </div>
        </div>
        <a href="#" class="sd-hero__dl-btn" id="btnDashDownloadReport">
          <i class='bx bxs-download'></i> Download Report
        </a>
      </div>
    </div>
  </div>

  <!-- ── STAT CARDS ── -->
  <div class="sd-stats">
    <div class="sd-stat sd-stat--blue">
      <div class="sd-stat__icon"><i class='bx bxs-folder-open'></i></div>
      <div class="sd-stat__body">
        <span class="sd-stat__val" id="statTotalViolations">—</span>
        <span class="sd-stat__lbl">Total Violations</span>
      </div>
      <div class="sd-stat__blobs"><span></span><span></span><span></span></div>
    </div>
    <div class="sd-stat sd-stat--green">
      <div class="sd-stat__icon"><i class='bx bxs-check-shield'></i></div>
      <div class="sd-stat__body">
        <span class="sd-stat__val" id="statResolvedViolations">—</span>
        <span class="sd-stat__lbl">PERMITTED</span>
      </div>
      <div class="sd-stat__blobs"><span></span><span></span><span></span></div>
    </div>
    <div class="sd-stat sd-stat--amber">
      <div class="sd-stat__icon"><i class='bx bxs-error'></i></div>
      <div class="sd-stat__body">
        <span class="sd-stat__val" id="statActiveViolations">—</span>
        <span class="sd-stat__lbl">Disciplinary</span>
      </div>
      <div class="sd-stat__blobs"><span></span><span></span><span></span></div>
    </div>
    <div class="sd-stat sd-stat--gold">
      <div class="sd-stat__icon"><i class='bx bxs-time-five'></i></div>
      <div class="sd-stat__body">
        <span class="sd-stat__val" id="statDaysClean">—</span>
        <span class="sd-stat__lbl">Days Clean</span>
      </div>
      <div class="sd-stat__blobs"><span></span><span></span><span></span></div>
    </div>
  </div>

  <!-- ── MAIN GRID ── -->
  <div class="sd-grid">

    <!-- LEFT COLUMN -->
    <div class="sd-col sd-col--left">

      <!-- Violation Summary -->
      <div class="sd-card">
        <div class="sd-card__head">
          <div class="sd-card__title-wrap">
            <span class="sd-card__icon-badge"><i class='bx bxs-shield-x'></i></span>
            <h3 class="sd-card__title">My Violations</h3>
          </div>
          <div class="sd-card__actions">
            <button class="sd-icon-btn" title="Refresh"
              onclick="if(window.userDashboardData)window.userDashboardData.loadAllData()">
              <i class='bx bx-refresh'></i>
            </button>
          </div>
        </div>
        <div class="sd-violation-summary" id="violationSummary">
          <div class="sd-loading"><div class="sd-spinner"></div><span>Loading&hellip;</span></div>
        </div>
      </div>

      <!-- Recent Violations -->
      <div class="sd-card sd-card--violations">
        <div class="sd-card__head">
          <div class="sd-card__title-wrap">
            <span class="sd-card__icon-badge sd-card__icon-badge--gold"><i class='bx bx-list-ul'></i></span>
            <h3 class="sd-card__title">Recent Violations</h3>
          </div>
          <div class="sd-card__actions">
            <button class="sd-icon-btn" title="Filter"><i class='bx bx-filter-alt'></i></button>
          </div>
        </div>
        <div class="sd-violations-list" id="recentViolationsList">
          <div class="sd-loading"><div class="sd-spinner"></div><span>Loading&hellip;</span></div>
        </div>
      </div>

    </div><!-- /LEFT -->

    <!-- RIGHT COLUMN -->
    <div class="sd-col sd-col--right">

      <!-- Announcements -->
      <div class="sd-card sd-card--announce">
        <div class="sd-card__head">
          <div class="sd-card__title-wrap">
            <span class="sd-card__icon-badge sd-card__icon-badge--gold"><i class='bx bxs-megaphone'></i></span>
            <h3 class="sd-card__title">Announcements</h3>
          </div>
          <button class="sd-icon-btn sd-announce-toggle" onclick="toggleAnnouncements()" title="Toggle">
            <i class='bx bx-chevron-down'></i>
          </button>
        </div>
        <div class="sd-announce-body" id="announcementsContent">
          <div class="sd-loading"><div class="sd-spinner"></div><span>Loading&hellip;</span></div>
        </div>
      </div>

      <!-- Tips -->
      <div class="sd-card sd-card--tips">
        <div class="sd-card__head">
          <div class="sd-card__title-wrap">
            <span class="sd-card__icon-badge sd-card__icon-badge--gold"><i class='bx bxs-bulb'></i></span>
            <h3 class="sd-card__title">Tips to Stay Compliant</h3>
          </div>
        </div>
        <div class="sd-tips">
          <div class="sd-tip">
            <div class="sd-tip__icon"><i class='bx bxs-t-shirt'></i></div>
            <div class="sd-tip__body">
              <h4>Proper Uniform</h4>
              <p>Wear the complete school uniform — clean, properly fitted, and in good condition every day.</p>
            </div>
          </div>
          <div class="sd-tip">
            <div class="sd-tip__icon"><i class='fas fa-shoe-prints'></i></div>
            <div class="sd-tip__body">
              <h4>Appropriate Footwear</h4>
              <p>School-approved shoes only. Keep them clean and in good repair.</p>
            </div>
          </div>
          <div class="sd-tip">
            <div class="sd-tip__icon"><i class='bx bxs-id-card'></i></div>
            <div class="sd-tip__body">
              <h4>Always Carry Your ID</h4>
              <p>Display your school ID whenever required. No ID = instant violation.</p>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /RIGHT -->

  </div><!-- /GRID -->

</main>

<!-- ── VIOLATION DETAILS MODAL ── -->
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
      <div class="violation-notes-section" id="resolutionSection" style="display:none;">
        <h4>Resolution</h4>
        <div class="notes-content"><p id="detailResolution">-</p></div>
      </div>
      <div class="violation-history">
        <h4>Violation History</h4>
        <div class="timeline" id="detailTimeline">
          <p style="color:var(--text-3);font-size:.82rem;">No history available.</p>
        </div>
      </div>
      <div class="Violations-form-actions">
        <button class="Violations-btn primary" onclick="printViolationSlip()">
          <i class='bx bxs-printer'></i> Print Slip
        </button>
        <button class="Violations-btn-outline" onclick="closeViolationModal()">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// ── Hero greeting & date ──
(function () {
  var h = new Date().getHours();
  var greet = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  var el = document.getElementById('sdGreeting');
  if (el) el.textContent = greet;

  var dateEl = document.getElementById('sdTodayDate');
  if (dateEl) {
    dateEl.textContent = new Date().toLocaleDateString('en-US', {
      weekday: 'long', month: 'long', day: 'numeric'
    });
  }

  // Pull student name from sidebar if available
  var nameEl = document.getElementById('sdStudentName');
  if (nameEl) {
    var sidebarName = document.getElementById('sidebarUsername');
    var navName = document.querySelector('.user-name');
    var src = (sidebarName && sidebarName.textContent.trim() !== 'User')
      ? sidebarName.textContent.trim()
      : (navName && navName.textContent.trim() !== 'User')
        ? navName.textContent.trim()
        : null;
    if (src) nameEl.textContent = src;
  }
})();

// ── Compliance ring (called by userDashboardData after load) ──
window.updateComplianceRing = function (total, resolved) {
  var pct = total > 0 ? Math.round((resolved / total) * 100) : 100;
  var fill = document.getElementById('sdRingFill');
  var pctEl = document.getElementById('sdRingPct');
  var chip  = document.getElementById('sdStatusChip');
  if (fill) {
    var circ = 2 * Math.PI * 50;
    fill.style.strokeDashoffset = circ - (circ * pct / 100);
  }
  if (pctEl) pctEl.textContent = pct + '%';
  if (chip) {
    if (pct >= 80) {
      chip.className = 'sd-chip sd-chip--good';
      chip.innerHTML = '<span class="sd-chip__dot"></span> Good Standing';
    } else if (pct >= 50) {
      chip.className = 'sd-chip sd-chip--warn';
      chip.innerHTML = '<span class="sd-chip__dot"></span> Needs Attention';
    } else {
      chip.className = 'sd-chip sd-chip--bad';
      chip.innerHTML = '<span class="sd-chip__dot"></span> At Risk';
    }
  }
};

// ── Announcements toggle ──
window.toggleAnnouncements = function () {
  var body = document.getElementById('announcementsContent');
  var btn  = document.querySelector('.sd-announce-toggle i');
  if (!body) return;
  var collapsed = body.classList.toggle('sd-announce-body--collapsed');
  if (btn) btn.className = collapsed ? 'bx bx-chevron-up' : 'bx bx-chevron-down';
};
</script>
