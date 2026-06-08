/**
 * User Announcements Module
 * Modular and defensive implementation to avoid conflicts and double-loading.
 */
(function() {
    'use strict';

    // Prevent double initialization
    if (window.announcementsInstance) {
        console.log('ℹ️ Announcements module already initialized');
        if (typeof window.announcementsInstance.init === 'function') {
            window.announcementsInstance.init();
        }
        return;
    }

    // State management
    const state = {
        announcements: [],
        readIds: JSON.parse(localStorage.getItem('readAnnouncements') || '[]'),
        pagination: { current: 1, total: 1, limit: 10, items: 0 },
        filters: { category: 'all', search: '', status: 'all' },
        viewMode: localStorage.getItem('annViewMode') || 'list'
    };

    // Helper to get API path safely
    const getApiPath = () => {
        if (window.USER_API_BASE) return window.USER_API_BASE;
        const p = window.location.pathname.split('/').filter(Boolean);
        const d = ['app', 'api', 'includes', 'assets', 'public'];
        const base = (p.length === 0 || d.includes(p[0])) ? '' : '/' + p[0];
        return base + '/api/';
    };

    const API_URL = getApiPath();

    /**
     * Data Fetching
     */
    async function loadAnnouncements(page = 1) {
        const tbody = document.getElementById('announcementsTableBody');
        if (!tbody) return;

        // Loading state
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 60px;">
                    <div class="loading-spinner"></div>
                    <p style="color: #64748b; margin-top: 10px;">Fetching latest announcements...</p>
                </td>
            </tr>
        `;

        try {
            const query = new URLSearchParams({
                action: 'active',
                page: page,
                limit: state.pagination.limit,
                category: state.filters.category,
                search: state.filters.search
            });

            const response = await fetch(`${API_URL}announcements.php?${query.toString()}`);
            if (!response.ok) throw new Error(`Server returned ${response.status}`);
            
            const data = await response.json();
            if (data.status === 'error') throw new Error(data.message);

            const result = data.data || {};
            state.announcements = result.announcements || [];
            state.pagination = {
                current: parseInt(result.page) || 1,
                total: parseInt(result.pages) || 1,
                limit: parseInt(result.limit) || 10,
                items: parseInt(result.total) || 0
            };

            render();
        } catch (error) {
            console.error('❌ Announcement Error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 60px; color: #ef4444;">
                        <i class='bx bx-error-circle' style="font-size: 40px;"></i>
                        <p style="margin: 10px 0;">Failed to load announcements: ${error.message}</p>
                        <button onclick="refreshAnnouncements()" style="padding: 8px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Rendering
     */
    function render() {
        const tbody = document.getElementById('announcementsTableBody');
        const gridView = document.getElementById('annGridView');
        const listView = document.getElementById('annListView');
        const tableView = document.getElementById('annTableView');
        const info = document.getElementById('announcementsPageInfo');
        const btns = document.getElementById('announcementsPageBtns');
        if (!tbody) return;

        // Show/hide views based on viewMode
        if (tableView) tableView.style.display = state.viewMode === 'table' ? '' : 'none';
        if (gridView) gridView.style.display = state.viewMode === 'grid' ? '' : 'none';
        if (listView) listView.style.display = state.viewMode === 'list' ? '' : 'none';

        // Render Table
        if (state.announcements.length === 0) {
            const emptyHtml = `
                <div style="text-align: center; padding: 80px 20px;">
                    <i class='bx bx-news' style="font-size: 48px; color: #cbd5e1;"></i>
                    <p style="color: #64748b; font-weight: 500; margin-top: 10px;">No announcements found</p>
                </div>
            `;
            tbody.innerHTML = `<tr><td colspan="5">${emptyHtml}</td></tr>`;
            if (gridView) gridView.innerHTML = emptyHtml;
            if (listView) listView.innerHTML = emptyHtml;
        } else {
            // Table view
            tbody.innerHTML = state.announcements.map(item => {
                const isRead = state.readIds.includes(item.id) || parseInt(item.is_read) === 1;
                const type = (item.type || item.category || 'info').toLowerCase();
                const date = formatTimeAgo(item.created_at);

                return `
                    <tr data-id="${item.id}" class="${isRead ? 'read' : 'unread'}">
                        <td><span class="title-text">${escapeHtml(item.title)}</span></td>
                        <td><span class="announcement-type ${type}">${type}</span></td>
                        <td><span class="status-badge ${isRead ? 'read' : 'unread'}">${isRead ? 'Read' : 'Unread'}</span></td>
                        <td><span class="date-text">${date}</span></td>
                        <td style="text-align: right;">
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewAnnouncement(${item.id})" title="View"><i class='bx bx-show'></i></button>
                                ${!isRead ? `<button class="action-btn mark-read" onclick="markAsRead(${item.id}, this)" title="Mark Read"><i class='bx bx-check'></i></button>` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            // Grid view
            if (gridView) {
                gridView.innerHTML = `<div class="ann-grid">${state.announcements.map(item => {
                    const isRead = state.readIds.includes(item.id) || parseInt(item.is_read) === 1;
                    const type = (item.type || item.category || 'info').toLowerCase();
                    const date = formatTimeAgo(item.created_at);
                    return `
                        <div class="ann-card ${isRead ? 'read' : 'unread'}" data-id="${item.id}">
                            <div class="ann-card-top ${type}"></div>
                            <div class="ann-card-body">
                                <div class="ann-card-header">
                                    <span class="announcement-type ${type}">${type}</span>
                                    <span class="status-badge ${isRead ? 'read' : 'unread'}">${isRead ? 'Read' : 'Unread'}</span>
                                </div>
                                <h4 class="ann-card-title">${escapeHtml(item.title)}</h4>
                                <p class="ann-card-preview">${escapeHtml((item.message || item.content || '').substring(0, 80))}${(item.message || item.content || '').length > 80 ? '...' : ''}</p>
                            </div>
                            <div class="ann-card-footer">
                                <span class="ann-card-date"><i class='bx bx-calendar'></i> ${date}</span>
                                <div class="action-buttons">
                                    <button class="action-btn view" onclick="viewAnnouncement(${item.id})" title="View"><i class='bx bx-show'></i></button>
                                    ${!isRead ? `<button class="action-btn mark-read" onclick="markAsRead(${item.id}, this)" title="Mark Read"><i class='bx bx-check'></i></button>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}</div>`;
            }

            // List view
            if (listView) {
                listView.innerHTML = state.announcements.map(item => {
                    const isRead = state.readIds.includes(item.id) || parseInt(item.is_read) === 1;
                    const type = (item.type || item.category || 'info').toLowerCase();
                    const date = formatTimeAgo(item.created_at);
                    return `
                        <div class="ann-list-item ${isRead ? 'read' : 'unread'}" data-id="${item.id}">
                            <div class="ann-list-left">
                                <div class="ann-list-icon ${type}"><i class='bx bx-bell'></i></div>
                                <div class="ann-list-info">
                                    <span class="ann-list-title">${escapeHtml(item.title)}</span>
                                    <span class="ann-list-meta">
                                        <span class="announcement-type ${type}">${type}</span>
                                        <span class="status-badge ${isRead ? 'read' : 'unread'}">${isRead ? 'Read' : 'Unread'}</span>
                                        <span class="ann-list-date"><i class='bx bx-calendar'></i> ${date}</span>
                                    </span>
                                </div>
                            </div>
                            <div class="ann-list-actions">
                                <button class="action-btn view" onclick="viewAnnouncement(${item.id})" title="View"><i class='bx bx-show'></i></button>
                                ${!isRead ? `<button class="action-btn mark-read" onclick="markAsRead(${item.id}, this)" title="Mark Read"><i class='bx bx-check'></i></button>` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // Render Pagination
        if (info && btns) {
            const { current, total, limit, items } = state.pagination;
            const start = items > 0 ? (current - 1) * limit + 1 : 0;
            const end = Math.min(current * limit, items);
            info.textContent = `Showing ${start}-${end} of ${items} announcements`;

            let html = `<button class="announcement-page-btn" ${current === 1 ? 'disabled' : ''} onclick="loadAnnouncements(${current - 1})"><i class='bx bx-chevron-left'></i></button>`;
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
                    html += `<button class="announcement-page-btn ${i === current ? 'active' : ''}" onclick="loadAnnouncements(${i})">${i}</button>`;
                } else if (i === current - 3 || i === current + 3) {
                    html += `<span class="announcements-page-ellipsis">...</span>`;
                }
            }
            html += `<button class="announcement-page-btn" ${current === total || total === 0 ? 'disabled' : ''} onclick="loadAnnouncements(${current + 1})"><i class='bx bx-chevron-right'></i></button>`;
            btns.innerHTML = html;
        }

        applyClientFilters();
    }

    function applyClientFilters() {
        const status = state.filters.status;
        if (status === 'all') return;
        document.querySelectorAll('#announcementsTableBody tr').forEach(row => {
            const isRead = row.classList.contains('read');
            row.style.display = (status === 'read' && isRead) || (status === 'unread' && !isRead) ? '' : 'none';
        });
    }

    /**
     * Actions
     */
    function markAsRead(id, btn) {
        if (!state.readIds.includes(id)) {
            state.readIds.push(id);
            localStorage.setItem('readAnnouncements', JSON.stringify(state.readIds));
        }

        // Update all views (table row, list item, grid card)
        document.querySelectorAll(`[data-id="${id}"]`).forEach(el => {
            el.classList.remove('unread');
            el.classList.add('read');
            const badge = el.querySelector('.status-badge');
            if (badge) { badge.className = 'status-badge read'; badge.textContent = 'Read'; }
            const markBtn = el.querySelector('.action-btn.mark-read');
            if (markBtn) markBtn.remove();
        });
    }

    function viewAnnouncement(id) {
        const item = state.announcements.find(a => a.id == id);
        if (!item) return;
        if (typeof window.showModernAlert === 'function') {
            window.showModernAlert({ title: item.title, message: item.message || item.content || 'No content', icon: 'info' });
        } else {
            alert(item.title + '\n\n' + (item.message || item.content));
        }
        markAsRead(id);
    }

    /**
     * Initialization
     */
    function init() {
        const container = document.getElementById('announcementsTableBody');
        if (!container) return;

        // Setup view toggle
        const viewBtns = document.querySelectorAll('.ann-view-btn');
        viewBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === state.viewMode);
            btn.addEventListener('click', () => {
                state.viewMode = btn.dataset.view;
                localStorage.setItem('annViewMode', state.viewMode);
                viewBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                render();
            });
        });

        // Setup filters
        const category = document.getElementById('categoryFilter');
        const status = document.getElementById('statusFilter');
        const search = document.getElementById('searchInput');

        if (category) category.onchange = (e) => { state.filters.category = e.target.value; loadAnnouncements(1); };
        if (status) status.onchange = (e) => { state.filters.status = e.target.value; render(); };
        if (search) {
            let timer;
            search.onkeyup = (e) => {
                clearTimeout(timer);
                timer = setTimeout(() => { state.filters.search = e.target.value.trim(); loadAnnouncements(1); }, 300);
            };
        }

        loadAnnouncements(1);
    }

    // Helpers
    function formatTimeAgo(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr.replace(' ', 'T'));
        const diff = Math.floor((new Date() - date) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return date.toLocaleDateString();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Export functions to window safely
    window.markAllAsRead = () => {
        document.querySelectorAll('[data-id].unread').forEach(el => {
            const id = parseInt(el.dataset.id);
            if (id && !state.readIds.includes(id)) {
                state.readIds.push(id);
            }
            el.classList.remove('unread');
            el.classList.add('read');
            const badge = el.querySelector('.status-badge');
            if (badge) { badge.className = 'status-badge read'; badge.textContent = 'Read'; }
            const markBtn = el.querySelector('.action-btn.mark-read');
            if (markBtn) markBtn.remove();
        });
        localStorage.setItem('readAnnouncements', JSON.stringify(state.readIds));
    };
    window.refreshAnnouncements = () => loadAnnouncements(state.pagination.current);
    window.viewAnnouncement = viewAnnouncement;
    window.markAsRead = markAsRead;
    window.loadAnnouncements = loadAnnouncements;
    window.initAnnouncementsModule = init;

    // Instance for prevent double-load
    window.announcementsInstance = { init };

    // Auto-init
    if (document.readyState === 'complete') init();
    else window.addEventListener('load', init);
    window.addEventListener('pageContentLoaded', init);

})();
