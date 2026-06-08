<?php
require_once __DIR__ . '/../../core/View.php';
?>
<link rel="stylesheet" href="<?= View::asset('styles/announcements.css') ?>">
<main id="announcements-page" class="admin-side">
    <!-- Header Card -->
    <div class="announcements-header-card">
        <div class="announcements-head-title">
            <div class="left">
                <h1>Announcements Management</h1>
                <p class="announcements-subtitle">Manage and publish announcements for students and staff.</p>
                <nav class="announcements-breadcrumb">
                    <a href="#">Dashboard</a>
                    <i class='bx bx-chevron-right'></i>
                    <span>Announcements</span>
                </nav>
            </div>
            <div class="announcement-actions">
                <div class="announcement-btn-group">
                    <button class="btn-export" onclick="exportAnnouncements()">
                        <i class='bx bx-download'></i>
                        <span>Export</span>
                    </button>
                    <button class="btn-refresh" onclick="loadAnnouncements()">
                        <i class='bx bx-refresh'></i>
                        <span>Refresh</span>
                    </button>
                </div>
                <div class="announcement-btn-group">
                    <button class="btn-add-announcement" onclick="openAddAnnouncementModal()">
                        <i class='bx bx-plus'></i>
                        <span>Add Announcement</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcements Table Card with Integrated Filters -->
    <div class="announcements-table-card">
        <div class="table-header">
            <div class="header-left">
                <h3>Announcement List</h3>
                <p class="table-subtitle">All published and archived announcements</p>
            </div>
            <div class="header-right">
                <div class="search-group">
                    <i class='bx bx-search'></i>
                    <input type="text" id="announcementSearch" placeholder="Search announcements..." oninput="filterAnnouncements()">
                </div>
                <div class="filter-controls">
                    <select id="announcementCategoryFilter" class="integrated-select" onchange="setCategoryFilter(this.value)">
                        <option value="all">Category: All</option>
                        <option value="info">Info</option>
                        <option value="urgent">Urgent</option>
                        <option value="warning">Warning</option>
                    </select>
                    <select id="announcementStatusFilter" class="integrated-select" onchange="setFilter(this.value)">
                        <option value="all">Status: All</option>
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="view-toggles">
                    <button class="view-btn" title="Table View" data-view="table"><i class='bx bx-table'></i></button>
                    <button class="view-btn" title="Grid View" data-view="grid"><i class='bx bx-grid-alt'></i></button>
                    <button class="view-btn active" title="List View" data-view="list"><i class='bx bx-list-ul'></i></button>
                </div>
            </div>
        </div>

        <div id="announcementsTableView" class="table-wrapper" style="display: none;">
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

        <div id="announcementsGridView" class="grid-container" style="display: none; padding: 16px;">
            <div id="announcementsGridBody" class="grid-view"></div>
        </div>

        <div id="announcementsListView" class="list-container" style="display: block; padding: 16px;">
            <div id="announcementsListBody" class="list-view"></div>
        </div>

        <!-- Pagination & Footer Stats -->
        <div class="table-footer">
            <div class="footer-stats">
                <div class="stat-item">
                    <span class="stat-label">Total Records:</span>
                    <span class="stat-value" id="totalCount">0</span>
                </div>
                <div class="stat-item info-text" id="announcementsPageInfo">
                    Showing 0-0 of 0 records
                </div>
            </div>
            <div class="pagination" id="announcementsPagination">
                <!-- Buttons generated via JS -->
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="announcementModal" class="announcement-modal">
        <div class="announcement-modal-backdrop" onclick="closeAnnouncementModal()"></div>
        <div class="announcement-modal-panel">
            <header class="announcement-modal-header">
                <div class="header-icon">
                    <i class='bx bxs-megaphone'></i>
                </div>
                <div class="header-text">
                    <h3 id="modalTitle">Add New Announcement</h3>
                    <p id="modalSubtitle">Fill in the details below to publish a new announcement.</p>
                </div>
                <button class="announcement-modal-close" onclick="closeAnnouncementModal()">
                    <i class='bx bx-x'></i>
                </button>
            </header>

            <form id="announcementForm" class="modern-form" onsubmit="event.preventDefault(); saveAnnouncement();">
                <input type="hidden" id="announcementId" value="">

                <div class="form-group">
                    <label for="announcementTitle">Title <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <input type="text" id="announcementTitle" placeholder="e.g. Enrollment for Next Semester" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="announcementType">Announcement Type</label>
                    <div class="input-wrapper">
                        <select id="announcementType">
                            <option value="info">General Information</option>
                            <option value="urgent">Urgent / Critical</option>
                            <option value="warning">Important Warning</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="announcementMessage">Message <span class="required">*</span></label>
                    <div class="textarea-wrapper">
                        <textarea id="announcementMessage" placeholder="Type your announcement message here..." rows="6" required></textarea>
                    </div>
                </div>

                <div class="announcement-modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAnnouncementModal()">Cancel</button>
                    <button type="submit" class="btn-submit">
                        <span class="btn-text">Save Announcement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        if (typeof window.initAnnouncementModule === 'function') {
            window.initAnnouncementModule();
        }
    </script>
</main>
