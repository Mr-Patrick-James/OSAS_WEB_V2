<?php
require_once __DIR__ . '/../../core/View.php';
// Start session if not started
@session_start();
?>
<link rel="stylesheet" href="<?= View::asset('styles/user_announcements.css') ?>">
<!-- Announcements Page -->
<div id="announcements-page" class="user-side">
    <!-- Header Card -->
    <div class="announcements-header-card">
        <div class="announcements-head-title">
            <div class="left">
            <h1>Announcements</h1>
            <p class="announcements-subtitle">Stay updated with the latest news and information</p>
            <nav class="announcements-breadcrumb">
                <a href="#">Dashboard</a>
                <i class='bx bx-chevron-right'></i>
                <span>Announcements</span>
            </nav>
        </div>
            <div class="announcement-actions">
                <button class="btn-mark-all-read" onclick="markAllAsRead()">
                    <i class='bx bxs-check-circle'></i>
                    <span>Mark All Read</span>
                </button>
                <button class="btn-refresh" onclick="refreshAnnouncements()">
                    <i class='bx bx-refresh'></i>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
    </div>
  
    <!-- Filter and Search -->
    <div class="announcement-filters">
        <div class="filter-group">
            <label>Category:</label>
            <select id="categoryFilter">
                <option value="all">All Categories</option>
                <option value="urgent">Urgent</option>
                <option value="warning">Warning</option>
                <option value="info">General</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Status:</label>
            <select id="statusFilter">
                <option value="all">All Status</option>
                <option value="unread">Unread</option>
                <option value="read">Read</option>
            </select>
        </div>
        <div class="search-toggle-group">
            <div class="search-group">
                <i class='bx bx-search'></i>
                <input type="text" id="searchInput" placeholder="Search announcements...">
            </div>
            <div class="announcements-view-toggle">
                <button class="ann-view-btn" data-view="table" title="Table View"><i class='bx bx-table'></i></button>
                <button class="ann-view-btn" data-view="grid" title="Grid View"><i class='bx bx-grid-alt'></i></button>
                <button class="ann-view-btn active" data-view="list" title="List View"><i class='bx bx-list-ul'></i></button>
            </div>
        </div>
    </div>
  
    <!-- Announcements List -->
    <div class="announcements-table-card">
        <div class="table-header">
            <h3>Recent Announcements</h3>
        </div>
        <div class="table-wrapper" id="annTableView">
            <table id="announcementsTable">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="announcementsTableBody">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 60px;">
                            <div class="loading-spinner"></div>
                            <p>Loading announcements...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="announcements-grid-view" id="annGridView" style="display:none;"></div>
        <div class="announcements-list-view" id="annListView" style="display:none;"></div>
        <!-- Pagination -->
        <div class="announcements-pagination" id="announcementsPagination">
            <div class="announcements-page-info" id="announcementsPageInfo">
                Showing 0-0 of 0 announcements
            </div>
            <div class="announcements-page-btns" id="announcementsPageBtns">
                <!-- Buttons generated via JS -->
            </div>
        </div>
    </div>
</div>

<!-- Load script only if not already present -->
<script>
    if (!window.announcementsModuleLoaded) {
        const script = document.createElement('script');
        script.src = "<?= View::asset('js/userAnnouncements.js') ?>";
        document.head.appendChild(script);
        window.announcementsModuleLoaded = true;
    } else if (typeof window.initAnnouncementsModule === 'function') {
        window.initAnnouncementsModule();
    }
</script>
