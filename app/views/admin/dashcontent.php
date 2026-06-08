<?php
require_once __DIR__ . '/../../core/View.php';

// Initialize counts with default values
$violatorsCount = 0;
$studentsCount = 0;
$departmentsCount = 0;
$penaltiesCount = 0;

try {
    // Attempt to load models and fetch data for Server-Side Rendering (SSR)
    // This ensures data is visible even before JS loads
    $modelsPath = __DIR__ . '/../../models/';
    
    // Check if we can connect to DB by trying to load one model
    if (file_exists($modelsPath . 'StudentModel.php')) {
        require_once $modelsPath . 'StudentModel.php';
        $studentModel = new StudentModel();
        $studentsCount = $studentModel->countActive();
    }
    
    if (file_exists($modelsPath . 'ViolationModel.php')) {
        require_once $modelsPath . 'ViolationModel.php';
        $violationModel = new ViolationModel();
        $violatorsCount = $violationModel->countViolators();
        $penaltiesCount = $violationModel->countPenalties();
    }
    
    if (file_exists($modelsPath . 'DepartmentModel.php')) {
        require_once $modelsPath . 'DepartmentModel.php';
        $deptModel = new DepartmentModel();
        $departmentsCount = $deptModel->getCountWithFilters('active');
    }
} catch (Throwable $e) {
    // Silently fail for SSR, let JS handle it or show 0
    // This prevents the whole page from crashing if DB is down
    error_log("SSR Error in dashcontent.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link rel="stylesheet" href="<?= View::asset('styles/Dashcontent.css') ?>">
</head>

<body>

  <!-- dashboardContent.html -->
  <main>
    <!-- Header Section -->
    <div class="page-header">
      <div class="header-content">
        <div class="title-section">
          <h1 class="page-title">Dashboard</h1>
          <p class="page-subtitle">Overview of system statistics and recent activities</p>
        </div>
        <div class="breadcrumb-wrapper">
          <div class="breadcrumb">
            <a href="#" class="breadcrumb-item">Dashboard</a>
            <i class='bx bx-chevron-right'></i>
            <span class="breadcrumb-item active">Home</span>
          </div>
        </div>
      </div>
      <div class="header-actions">
        <a href="#" class="btn-download" id="btnExportDashboardPDF">
          <i class='bx bxs-cloud-download'></i>
          <span class="text">Export PDF</span>
        </a>
      </div>
    </div>

    <!-- Announcements Section -->
    <div class="announcements-container">
      <div class="announcements-header">
        <h3><i class='bx bxs-megaphone'></i> Latest Announcements</h3>
        <div class="header-actions">
          <button id="addAnnouncementBtn" class="btn-add" onclick="loadContent('admin_page/Announcements')">
            <i class='bx bx-plus'></i> Add Announcement
          </button>
          <button class="announcement-toggle" onclick="toggleAnnouncements()">
            <i class='bx bx-chevron-down'></i>
          </button>
        </div>
      </div>

      <div class="announcements-content" id="announcementsContent">
        <div style="text-align: center; padding: 40px;">
          <div class="loading-spinner"></div>
          <p>Loading announcements...</p>
        </div>
      </div>
    </div>

    <div class="section-title">
      <h3><i class='bx bx-stats'></i> Statistics Overview</h3>
    </div>

    <ul class="box-info">
      <li>
        <i class='bx bxs-calendar-check'></i>
        <span class="text">
          <h3 id="violators-count"><?= $violatorsCount ?></h3>
          <p>Violators</p>
        </span>
        <div class="box-blobs"><span></span><span></span><span></span></div>
      </li>
      <li>
        <i class='bx bxs-group'></i>
        <span class="text">
          <h3 id="students-count"><?= $studentsCount ?></h3>
          <p>Students</p>
        </span>
        <div class="box-blobs"><span></span><span></span><span></span></div>
      </li>
      <li>
        <i class='bx bxs-building'></i>
        <span class="text">
          <h3 id="departments-count"><?= $departmentsCount ?></h3>
          <p>Departments</p>
        </span>
        <div class="box-blobs"><span></span><span></span><span></span></div>
      </li>
      <li>
        <i class='bx bxs-error-circle penalty-icon'></i>
        <span class="text">
          <h3 id="penalties-count"><?= $penaltiesCount ?></h3>
          <p>Disciplinary</p>
        </span>
        <div class="box-blobs"><span></span><span></span><span></span></div>
      </li>
    </ul>

    <!-- Charts Section -->
    <div class="charts-container">
      <div class="chart-section">
        <div class="chart-card">
          <div class="chart-header">
            <h3>Violation Types Distribution</h3>
            <i class='bx bx-pie-chart-alt-2'></i>
          </div>
          <div class="chart-container">
            <canvas id="violationTypesChart"></canvas>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-header">
            <h3>Violations by Department</h3>
            <i class='bx bx-bar-chart-alt-2'></i>
          </div>
          <div class="chart-container">
            <canvas id="departmentViolationsChart"></canvas>
          </div>
        </div>
      </div>

      <div class="chart-section">
        <div class="chart-card full-width">
          <div class="chart-header">
            <h3>Monthly Violation</h3>
            <i class='bx bx-line-chart'></i>
          </div>
          <div class="chart-container">
            <canvas id="monthlyTrendsChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="table-data">
      <div class="order">
        <div class="head">
          <h3>Recent Violators</h3>
          <i class='bx bx-search'></i>
          <i class='bx bx-filter'></i>
        </div>
        <table>
          <thead>
            <tr>
              <th>Violator</th>
              <th>Date</th>
              <th>Violation</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="recent-violators-body">
            <!-- Populated by JS -->
          </tbody>
        </table>
      </div>

      <div class="violators">
        <div class="head">
          <h3>Top Violators</h3>
          <i class='bx bx-refresh' id="refreshTopViolators" title="Refresh" style="cursor:pointer;"></i>
          <i class='bx bx-filter' id="filterTopViolators" title="View All" style="cursor:pointer;"></i>
        </div>
        <ul class="violator-list" id="top-violators-list">
            <!-- Populated by JS -->
        </ul>
      </div>
  </main>

  <script>
    // Initialize Dashboard Data
    (function() {
         console.log('🚀 Dashcontent script executing...');
         
         let attempts = 0;
         function init() {
             if (typeof DashboardData !== 'undefined') {
                 console.log('✅ DashboardData class found, initializing...');
                 const dashboardData = new DashboardData();
                 window.dashboardDataInstance = dashboardData;
                 dashboardData.loadAllData();
             } else {
                 attempts++;
                 if (attempts < 50) {
                     console.warn('⚠️ DashboardData class not found, retrying in 100ms...');
                     setTimeout(init, 100);
                 } else {
                     console.error('❌ DashboardData failed to load after 5 seconds');
                 }
             }
         }
         
         // Run initialization
         init();
     })();
  </script>
</body>

</html>


