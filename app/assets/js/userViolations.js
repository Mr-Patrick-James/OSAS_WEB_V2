/*********************************************************
 * CONFIG
 *********************************************************/
const API_BASE = (function(){ const p=window.location.pathname.split('/').filter(Boolean); const d=['app','api','includes','assets','public']; return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/api/'; })();

let studentId = null;
let userViolations = [];
let allUserViolations = []; // includes archived
let userViolationTypes = [];
let currentViolationId = null;
let uvViewMode = 'list'; // default view

// Pagination state
let uvCurrentPage = 1;
let uvItemsPerPage = 10;
let uvTotalPages = 1;

/*********************************************************
 * VIEW TOGGLE
 *********************************************************/
function setUvView(mode) {
    uvViewMode = mode;
    const tableView = document.getElementById('uvTableView');
    const listView  = document.getElementById('uvListView');
    const gridView  = document.getElementById('uvGridView');
    if (tableView) tableView.style.display = mode === 'table' ? 'block' : 'none';
    if (listView)  listView.style.display  = mode === 'list'  ? 'block' : 'none';
    if (gridView)  gridView.style.display  = mode === 'grid'  ? 'block' : 'none';

    // Update active toggle button
    document.querySelectorAll('.Violations-view-btn[data-view]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === mode);
    });

    renderViolationTable();
}

/*********************************************************
 * INIT
 *********************************************************/
document.addEventListener('DOMContentLoaded', initUserViolations);

// Also listen for dynamic page loads
if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('pageContentLoaded', initUserViolations);
}

async function initUserViolations() {
    const tbody = document.getElementById('violationsTableBody');
    
    // Only initialize if the violations table exists on the page
    if (!tbody) {
        console.log('Violations table not found on this page, skipping initialization');
        return;
    }

    // Attach search listener
    const searchInput = document.getElementById('searchViolation');
    if (searchInput) {
        searchInput.addEventListener('input', filterViolations);
    }

    studentId = getStudentId();
    console.log('Student ID:', studentId);

    if (!studentId) {
        tbody.innerHTML = errorRow('Student ID not found. Please login again.');
        return;
    }

    // Attach download listener
    const btnDownload = document.getElementById('btnDownloadReport');
    if (btnDownload) {
        btnDownload.addEventListener('click', function(e) {
            e.preventDefault();
            downloadViolationsReport();
        });
    }

    await loadUserViolationTypes();
    await loadUserViolations();
}

function getUserTypeIcon(name) {
    const n = (name || '').toLowerCase();
    if (n.includes('uniform')) return 'bxs-t-shirt';
    if (n.includes('footwear') || n.includes('shoe')) return 'bx-walk';
    if (n.includes('id')) return 'bxs-id-card';
    if (n.includes('misconduct') || n.includes('behavior')) return 'bx-error';
    return 'bx-error-circle';
}

async function loadUserViolationTypes() {
    try {
        const response = await fetch(`${API_BASE}violations.php?action=types`);
        const data = await response.json();
        if (data.status === 'success' && Array.isArray(data.data)) {
            userViolationTypes = data.data.map(type => ({
                id: type.id,
                name: type.type_name || type.name
            }));
        } else {
            throw new Error('Invalid types response');
        }
    } catch (error) {
        console.warn('Could not load violation types, using defaults:', error);
        userViolationTypes = [
            { id: 'uniform', name: 'Improper Uniform' },
            { id: 'footwear', name: 'Improper Footwear' },
            { id: 'no_id', name: 'No ID' }
        ];
    }

    populateUserViolationFilter();
    renderUserTypeStatCards();
}

function populateUserViolationFilter() {
    const filter = document.getElementById('violationFilter');
    if (!filter) return;

    const current = filter.value || 'all';
    filter.innerHTML = '<option value="all">All Types</option>';
    userViolationTypes.forEach(type => {
        const option = document.createElement('option');
        option.value = type.id;
        option.textContent = type.name;
        filter.appendChild(option);
    });
    if ([...filter.options].some(opt => opt.value === current)) {
        filter.value = current;
    }
}

function getUserViolationTypeId(violation) {
    return String(violation.violationType || violation.violation_type_id || '');
}

function countUserViolationsByType(type, violations) {
    const typeId = String(type.id);
    const typeName = (type.name || '').toLowerCase();

    return violations.filter(v => {
        const vTypeId = getUserViolationTypeId(v);
        if (vTypeId && vTypeId === typeId) return true;

        const label = String(v.violationTypeLabel || v.violation_type_name || '').toLowerCase();
        if (!label) return false;
        if (typeName.includes('uniform')) return label.includes('uniform');
        if (typeName.includes('footwear') || typeName.includes('shoe')) return label.includes('footwear') || label.includes('shoe');
        if (typeName.includes('id')) return label.includes('id') || label.includes('no id');
        return label === typeName || label.includes(typeName);
    }).length;
}

function renderUserTypeStatCards() {
    const container = document.getElementById('uvTypeStatsContainer');
    if (!container) return;

    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    const thisMonthViolations = userViolations.filter(v => {
        const dateStr = v.created_at || v.violation_date || v.date || '';
        const d = new Date(String(dateStr).replace(/\//g, '-'));
        return d.getMonth() === currentMonth && d.getFullYear() === currentYear;
    });

    if (!userViolationTypes.length) {
        container.innerHTML = '<p class="uv-stats-loading">No violation types found</p>';
        return;
    }

    container.innerHTML = userViolationTypes.map(type => {
        const count = countUserViolationsByType(type, thisMonthViolations);
        const icon = getUserTypeIcon(type.name);
        const label = type.name.length > 22 ? `${type.name.slice(0, 20)}…` : type.name;
        return `
        <div class="uv-stat" title="${type.name}">
            <div class="uv-stat__icon"><i class='bx ${icon}'></i></div>
            <div class="uv-stat__body">
                <span class="uv-stat__lbl">${label} <small>(this month)</small></span>
                <span class="uv-stat__val">${count}</span>
            </div>
        </div>`;
    }).join('');
}

/*********************************************************
 * HELPERS
 *********************************************************/
function getStudentId() {
    if (window.STUDENT_ID) return window.STUDENT_ID;
    const mainContent = document.getElementById('main-content');
    if (mainContent && mainContent.dataset.studentId) return mainContent.dataset.studentId;
    
    const cookies = Object.fromEntries(
        document.cookie.split(';').map(c => c.trim().split('=')).map(([k,v]) => [k, decodeURIComponent(v)])
    );
    return cookies.student_id_code || cookies.student_id;
}

function errorRow(message) {
    return `
        <tr>
            <td colspan="6" style="text-align:center; padding:40px; color:#ef4444;">
                ${message}
            </td>
        </tr>
    `;
}

/*********************************************************
 * LOAD VIOLATIONS
 *********************************************************/
async function loadUserViolations() {
    const tbody = document.getElementById('violationsTableBody');

    try {
        // Fetch active (current month) violations
        const apiUrl = `${API_BASE}violations.php`;
        console.log('📡 Fetching violations from:', apiUrl);
        
        const [resActive, resArchived] = await Promise.all([
            fetch(apiUrl),
            fetch(`${apiUrl}?is_archived=1`)
        ]);

        if (!resActive.ok) throw new Error(`HTTP error! status: ${resActive.status}`);
        
        const jsonActive = await resActive.json();
        if (jsonActive.status !== 'success') throw new Error(jsonActive.message || 'Failed to load violations');

        userViolations = jsonActive.data || [];
        
        // Merge archived violations
        let archivedViolations = [];
        if (resArchived.ok) {
            const jsonArchived = await resArchived.json();
            if (jsonArchived.status === 'success') {
                archivedViolations = (jsonArchived.data || []).map(v => ({ ...v, _isArchived: true }));
            }
        }

        // All violations = active + archived
        allUserViolations = [...userViolations, ...archivedViolations];
        console.log('✅ Loaded', userViolations.length, 'active +', archivedViolations.length, 'archived violations');

        updateViolationStats();
        renderViolationTable();

    } catch (err) {
        console.error('❌ Error loading violations:', err);
        tbody.innerHTML = errorRow(err.message);
    }
}

/*********************************************************
 * STATS
 *********************************************************/
function updateViolationStats() {
    const allTimeTotal = allUserViolations.length;
    const statTotal = document.getElementById('statTotal');
    if (statTotal) statTotal.textContent = allTimeTotal;
    renderUserTypeStatCards();
}

/*********************************************************
 * TABLE & FILTER
 *********************************************************/
function renderViolationTable() {
    const tbody = document.getElementById('violationsTableBody');
    const listBody = document.getElementById('violationsListBody');
    const showingCount = document.getElementById('showingViolationsCount');
    
    // Determine source based on time period filter
    const timePeriod = document.getElementById('timePeriodFilter')?.value || 'current_month';
    let sourceViolations;
    
    if (timePeriod === 'all') {
        sourceViolations = allUserViolations.length > 0 ? allUserViolations : (window.allUserViolations || []);
    } else {
        // current_month — only active (non-archived) violations
        sourceViolations = userViolations.length > 0 ? userViolations : (window.userViolations || []);
    }

    // Apply filters
    const searchTerm = (document.getElementById('searchViolation')?.value || '').toLowerCase();
    const typeFilter = document.getElementById('violationFilter')?.value || 'all';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';

    const filtered = sourceViolations.filter(v => {
        const searchStr = `${v.caseId || v.case_id || v.id} ${v.violationTypeLabel || v.violation_type_name || ''} ${v.studentName || ''} ${v.created_at || v.dateReported || ''}`.toLowerCase();
        const matchesSearch = !searchTerm || searchStr.includes(searchTerm);

        const rawType = String(v.violationTypeLabel || v.violation_type_name || v.violation_type || '').toLowerCase();
        let matchesType = typeFilter === 'all';
        if (!matchesType) {
            const selectedType = userViolationTypes.find(t => String(t.id) === String(typeFilter));
            if (selectedType) {
                matchesType = countUserViolationsByType(selectedType, [v]) > 0;
            } else {
                const filterKey = String(typeFilter).toLowerCase();
                if (filterKey === 'improper_uniform') {
                    matchesType = rawType.includes('uniform');
                } else if (filterKey === 'improper_footwear') {
                    matchesType = rawType.includes('footwear') || rawType.includes('shoe');
                } else if (filterKey === 'no_id') {
                    matchesType = rawType.includes('id') || rawType.includes('no_id') || rawType.includes('no id');
                } else {
                    matchesType = rawType.includes(filterKey.replace('improper_', '').replace('_', ' '));
                }
            }
        }

        const status = (v.status || 'pending').toLowerCase();
        const matchesStatus = statusFilter === 'all' || 
                             (statusFilter === 'resolved' && (status === 'resolved' || status === 'permitted')) ||
                             (statusFilter === 'pending' && (status === 'pending' || status === 'warning')) ||
                             (statusFilter === 'warning' && status === 'warning') ||
                             status === statusFilter;

        return matchesSearch && matchesType && matchesStatus;
    });

    if (showingCount) showingCount.textContent = filtered.length;

    if (filtered.length === 0) {
        if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:40px;color:#666;">No violations found matching your filters</td></tr>`;
        if (listBody) listBody.innerHTML = `<div style="text-align:center; padding:40px; color: #666;">No violations found matching your filters</div>`;
        const gridBody = document.getElementById('violationsGridBody');
        if (gridBody) gridBody.innerHTML = `<div style="text-align:center; padding:40px; color: #666;">No violations found matching your filters</div>`;
        renderUvPagination(0);
        return;
    }

    // ── PAGINATION ──
    uvTotalPages = Math.ceil(filtered.length / uvItemsPerPage) || 1;
    if (uvCurrentPage > uvTotalPages) uvCurrentPage = uvTotalPages;
    const startIdx = (uvCurrentPage - 1) * uvItemsPerPage;
    const endIdx = startIdx + uvItemsPerPage;
    const paginatedItems = filtered.slice(startIdx, endIdx);

    // Update pagination info
    const uvShowingStart = document.getElementById('uvShowingStart');
    const uvShowingEnd = document.getElementById('uvShowingEnd');
    const uvTotalFiltered = document.getElementById('uvTotalFiltered');
    if (uvShowingStart) uvShowingStart.textContent = filtered.length > 0 ? startIdx + 1 : 0;
    if (uvShowingEnd) uvShowingEnd.textContent = Math.min(endIdx, filtered.length);
    if (uvTotalFiltered) uvTotalFiltered.textContent = filtered.length;

    renderUvPagination(filtered.length);

    // ── TABLE VIEW ──
    if (tbody) {
        tbody.innerHTML = paginatedItems.map(v => {
        const status = (v.status || 'pending').toLowerCase();
        
        const level = v.violation_level_name || v.violationLevelLabel || v.level || v.offense_level || '1';
        const levelVal = String(level).toLowerCase();
        const isDisciplinary = levelVal.includes('warning 3') || levelVal.includes('3rd') || levelVal.includes('5th offense') || levelVal.includes('disciplinary');
       
        let statusClass = 'warning';
        let statusText = 'Pending';

        if (status === 'resolved') {
            statusClass = 'resolved';
            statusText = 'Resolved';
        } else if (status === 'permitted') {
            statusClass = 'permitted';
            statusText = 'Permitted';
        } else if (isDisciplinary || status === 'disciplinary') {
            statusClass = 'disciplinary';
            statusText = 'Disciplinary';
        } else if (status === 'warning') {
            statusClass = 'warning';
            statusText = 'Warning';
        }

        const violationType = v.violation_type_name || v.violationTypeLabel || v.violation_type || 'Unknown';
        const violationTypeFormatted = formatViolationType(String(violationType));

        return `
            <tr class="violation-row">
                <td data-label="Violation Type">${escapeHtml(violationTypeFormatted)}</td>
                <td data-label="Offense Level"><span class="violation-level-badge ${getViolationLevelClass(level)}">${level}</span></td>
                <td data-label="Date">${formatDate(v.created_at || v.violation_date || v.date)}</td>
                <td data-label="Status"><span class="Violations-status-badge ${statusClass}">${statusText}</span></td>
                <td data-label="Actions">
                    <div class="action-buttons">
                        <button class="Violations-action-btn view" onclick="viewViolationDetails(${v.id})" title="View Details">
                            <i class='bx bx-show'></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');
    }

    // ── LIST VIEW ──
    if (listBody) {
        listBody.innerHTML = paginatedItems.map(v => {
            let status = (v.status || 'warning').toLowerCase();
            let statusText = v.statusLabel || 'Warning';

            // Use level's default status if current status is generic 'warning'
            if (status === 'warning' && v.levelDefaultStatus && v.levelDefaultStatus !== 'warning') {
                status = v.levelDefaultStatus;
                const labels = { 'permitted': 'Permitted', 'warning': 'Warning', 'disciplinary': 'Disciplinary', 'resolved': 'Resolved' };
                statusText = labels[status] || status.charAt(0).toUpperCase() + status.slice(1);
            }

            const statusClass = status;
            const level = v.violation_level_name || v.violationLevelLabel || v.level || v.offense_level || '-';
            const violationType = v.violation_type_name || v.violationTypeLabel || v.violation_type || 'Unknown';
            const violationTypeFormatted = formatViolationType(String(violationType));
            const typeClass  = getViolationTypeClass(violationType);
            const levelClass = getViolationLevelClass(level);
            const studentName = v.student_name || v.studentName || 'Student';
            const studentIdCode = v.student_id_code || v.studentId || v.student_id || '';
            const section = v.section || v.sectionLabel || 'N/A';
            const imgSrc = getImageUrl(v.student_image || v.studentImage || '', studentName);

            return `
            <div class="violation-list-item ${statusClass}" data-id="${v.id}">
                <div class="violation-list-top">
                    <img src="${imgSrc}" alt="${escapeHtml(studentName)}" class="violation-list-avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(studentName)}&background=ffd700&color=333&size=36'">
                    <div class="violation-list-name-block">
                        <span class="violation-list-name">${escapeHtml(studentName)}</span>
                        <span class="violation-list-id">${escapeHtml(studentIdCode)}</span>
                    </div>
                    <div class="violation-list-actions">
                        <div class="action-buttons">
                            <button class="Violations-action-btn view" onclick="viewViolationDetails(${v.id})" title="View Details">
                                <i class='bx bx-show'></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="violation-list-badges">
                    <span class="violation-type-badge ${typeClass}" style="font-size:9px;padding:2px 7px;">${escapeHtml(violationTypeFormatted)}</span>
                    <span class="violation-level-badge ${levelClass}" style="font-size:9px;padding:2px 7px; ${v.levelStatusColor ? `background-color: ${v.levelStatusColor}; color: white; border: none;` : ''}">${escapeHtml(level)}</span>
                    <span class="dept-badge" style="font-size:9px;padding:2px 7px;">${escapeHtml(section)}</span>
                    <span class="Violations-status-badge ${statusClass}" style="font-size:9px;">${statusText}</span>
                    <span style="font-size:9px;color:var(--text-3);margin-left:2px;">
                        <i class='bx bx-calendar' style="vertical-align:middle;"></i> ${formatDate(v.created_at || v.violation_date || v.date)}
                    </span>
                </div>
            </div>`;
        }).join('');
    }

    // ── GRID VIEW ──
    const gridBody = document.getElementById('violationsGridBody');
    if (gridBody) {
        gridBody.innerHTML = paginatedItems.map(v => {
            let status = (v.status || 'warning').toLowerCase();
            let statusText = v.statusLabel || 'Warning';

            // Use level's default status if current status is generic 'warning'
            if (status === 'warning' && v.levelDefaultStatus && v.levelDefaultStatus !== 'warning') {
                status = v.levelDefaultStatus;
                const labels = { 'permitted': 'Permitted', 'warning': 'Warning', 'disciplinary': 'Disciplinary', 'resolved': 'Resolved' };
                statusText = labels[status] || status.charAt(0).toUpperCase() + status.slice(1);
            }

            const statusClass = status;
            const level = v.violation_level_name || v.violationLevelLabel || v.level || v.offense_level || '-';
            const violationType = v.violation_type_name || v.violationTypeLabel || v.violation_type || 'Unknown';
            const violationTypeFormatted = formatViolationType(String(violationType));
            const typeClass  = getViolationTypeClass(violationType);
            const levelClass = getViolationLevelClass(level);

            return `
            <div class="violation-card ${statusClass}" data-id="${v.id}" style="background:var(--surface,#fff);border:1px solid var(--border,#e2e8f0);border-radius:12px;padding:14px;cursor:pointer;" onclick="viewViolationDetails(${v.id})">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <span class="violation-type-badge ${typeClass}" style="font-size:10px;padding:3px 8px;">${escapeHtml(violationTypeFormatted)}</span>
                    <span class="Violations-status-badge ${statusClass}" style="font-size:9px;">${statusText}</span>
                </div>
                <div style="margin-bottom:8px;">
                    <span class="violation-level-badge ${levelClass}" style="font-size:10px;padding:3px 8px; ${v.levelStatusColor ? `background-color: ${v.levelStatusColor}; color: white; border: none;` : ''}">${escapeHtml(level)}</span>
                </div>
                <div style="font-size:11px;color:var(--text-3,#64748b);display:flex;align-items:center;gap:4px;">
                    <i class='bx bx-calendar' style="font-size:12px;"></i>
                    ${formatDate(v.created_at || v.violation_date || v.date)}
                </div>
            </div>`;
        }).join('');
    }
}

function filterViolations() {
    uvCurrentPage = 1;
    renderViolationTable();
}

/*********************************************************
 * PAGINATION
 *********************************************************/
function renderUvPagination(totalItems) {
    const container = document.getElementById('uvPagination');
    if (!container) return;
    container.innerHTML = '';

    uvTotalPages = Math.ceil(totalItems / uvItemsPerPage) || 1;

    const makeBtn = (label, opts = {}) => {
        const btn = document.createElement('button');
        btn.className = 'uv-pagination-btn' + (opts.active ? ' active' : '');
        btn.innerHTML = label;
        if (opts.disabled) btn.disabled = true;
        if (opts.page) btn.dataset.page = opts.page;
        if (opts.action) btn.dataset.action = opts.action;
        return btn;
    };

    // Prev button
    container.appendChild(makeBtn('<i class="bx bx-chevron-left"></i>', { disabled: uvCurrentPage === 1, action: 'prev' }));

    // Page numbers
    const maxButtons = 7;
    let startPage = Math.max(1, uvCurrentPage - 3);
    let endPage = Math.min(uvTotalPages, startPage + maxButtons - 1);
    if (endPage - startPage + 1 < maxButtons) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }

    for (let p = startPage; p <= endPage; p++) {
        container.appendChild(makeBtn(String(p), { active: p === uvCurrentPage, page: p }));
    }

    // Next button
    container.appendChild(makeBtn('<i class="bx bx-chevron-right"></i>', { disabled: uvCurrentPage === uvTotalPages, action: 'next' }));

    // Event delegation
    container.onclick = function(e) {
        const target = e.target.closest('button');
        if (!target || target.disabled) return;
        const action = target.dataset.action;
        const pageAttr = target.dataset.page;
        if (action === 'prev') {
            if (uvCurrentPage > 1) uvCurrentPage--;
        } else if (action === 'next') {
            if (uvCurrentPage < uvTotalPages) uvCurrentPage++;
        } else if (pageAttr) {
            const pageNum = parseInt(pageAttr, 10);
            if (!isNaN(pageNum)) uvCurrentPage = pageNum;
        } else {
            return;
        }
        renderViolationTable();
    };
}

/*********************************************************
 * MODAL
 *********************************************************/
function getImageUrl(imagePath, fallbackName = 'Student') {
    if (!imagePath || imagePath.trim() === '') {
        return `https://ui-avatars.com/api/?name=${encodeURIComponent(fallbackName)}&background=ffd700&color=333&size=80`;
    }
    if (imagePath.startsWith('http') || imagePath.startsWith('data:')) return imagePath;
    
    // Adjust based on your path structure
    // Assuming relative to project root if not absolute
    // Note: API_BASE is /OSAS_WEB/api/, so we want /OSAS_WEB/
    const projectBase = API_BASE.replace('api/', '');

    if (imagePath.startsWith('assets/')) return projectBase + 'app/' + imagePath;
    if (imagePath.startsWith('app/assets/')) return projectBase + imagePath;
    
    return projectBase + 'app/assets/img/students/' + imagePath;
}

function getViolationTypeClass(typeLabel) {
    if (!typeLabel || typeof typeLabel !== 'string') return 'default';
    const lower = typeLabel.toLowerCase();
    if (lower.includes('uniform')) return 'uniform';
    if (lower.includes('footwear') || lower.includes('shoe')) return 'footwear';
    if (lower.includes('id')) return 'id';
    if (lower.includes('misconduct') || lower.includes('behavior')) return 'behavior';
    return 'default';
}

function getViolationLevelClass(level) {
    if (level === null || level === undefined) return 'default';
    const lowerLevel = String(level).toLowerCase();
    // 1st & 2nd Offense = green (permitted), 3rd & 4th = orange (warning), 5th/Disciplinary = red
    if (lowerLevel.includes('1st offense') || lowerLevel.includes('2nd offense') ||
        lowerLevel.startsWith('permitted')) return 'permitted';
    if (lowerLevel.includes('3rd offense') || lowerLevel.includes('4th offense') ||
        lowerLevel.startsWith('warning 1') || lowerLevel.startsWith('warning 2')) return 'warning';
    if (lowerLevel.includes('5th offense') || lowerLevel.startsWith('warning 3') ||
        lowerLevel === 'disciplinary' || lowerLevel.includes('disciplinary')) return 'disciplinary';
    return 'default';
}

function getDepartmentClass(dept) {
    const classes = {
        'BSIS': 'bsis',
        'WFT': 'wft',
        'BTVTED': 'btvted',
        'CHS': 'chs'
    };
    return classes[dept] || 'default';
}

function getStatusClass(status) {
    status = (status || '').toLowerCase();
    if (status === 'resolved') return 'resolved';
    if (status === 'permitted') return 'permitted';
    if (status === 'disciplinary') return 'disciplinary';
    if (status === 'warning') return 'warning';
    return 'warning';
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    try {
        const [hours, minutes] = timeStr.split(':');
        const h = parseInt(hours);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        return `${h12}:${minutes} ${ampm}`;
    } catch (e) {
        return timeStr;
    }
}

function viewViolationDetails(id) {
    const v = allUserViolations.find(x => x.id == id) || userViolations.find(x => x.id == id) 
           || (window.allUserViolations || []).find(x => x.id == id) 
           || (window.userViolations || []).find(x => x.id == id);
    if (!v) return;
    currentViolationId = id;

    // Helper functions for safe element access
    const setElementText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text || 'N/A';
    };
    const setElementSrc = (id, src) => {
        const el = document.getElementById(id);
        if (el) el.src = src;
    };
    const setElementClass = (id, className) => {
        const el = document.getElementById(id);
        if (el) el.className = className;
    };

    // --- Case Header ---
    let displayStatus = (v.status || '').toLowerCase();
    let displayStatusLabel = v.statusLabel || (displayStatus ? displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1) : 'Unknown');

    const levelLabel = (v.violation_level_name || v.violationLevelLabel || v.level || v.offense_level || '').toLowerCase();
    const isDisciplinary = levelLabel.includes('warning 3') || levelLabel.includes('3rd') || levelLabel.includes('5th offense') || levelLabel.includes('disciplinary');

    if (displayStatus === 'resolved') {
        displayStatusLabel = 'Resolved';
        // keep displayStatus = 'resolved'
    } else if (displayStatus === 'permitted') {
        displayStatusLabel = 'Permitted';
        displayStatus = 'permitted';
    } else if (isDisciplinary || displayStatus === 'disciplinary') {
        displayStatus = 'disciplinary';
        displayStatusLabel = 'Disciplinary';
    }

    setElementText('detailCaseId', v.case_id || '#' + v.id);
    setElementText('detailStatusBadge', displayStatusLabel);
    setElementClass('detailStatusBadge', `case-status-badge ${getStatusClass(displayStatus)}`);

    // --- Student Info ---
    const studentName = v.student_name || v.studentName || 'Student';
    const studentImg = v.student_image || v.studentImage || '';
    
    const studentImageUrl = getImageUrl(studentImg, studentName);
    setElementSrc('detailStudentImage', studentImageUrl);
    setElementText('detailStudentName', studentName);
    setElementText('detailStudentId', v.student_id || studentId);
    setElementText('detailStudentDept', v.department || v.department_name || 'N/A');
    setElementClass('detailStudentDept', `student-dept badge ${getDepartmentClass(v.department || v.department_name)}`);
    setElementText('detailStudentSection', v.section || 'N/A');
    setElementText('detailStudentContact', v.student_contact || v.studentContact || 'N/A');

    // Update Slip Status UI (Request/Download buttons)
    updateSlipStatusUI(id);

    // --- Violation Details Grid (Match Admin style) ---
    // Use all violations (including archived) for complete history
    let studentViolations = [...(allUserViolations || userViolations)];
    
    // Sort violations by date descending
    studentViolations.sort((a, b) => {
         const dateA = new Date((a.created_at || a.violation_date || a.date) + ' ' + (a.violation_time || '00:00'));
         const dateB = new Date((b.created_at || b.violation_date || b.date) + ' ' + (b.violation_time || '00:00'));
         return dateB - dateA;
    });

    // Keep only the latest record per violation type
    const latestByType = new Map();
    studentViolations.forEach(sv => {
        const type = sv.violation_type_name || sv.violationTypeLabel || sv.violation_type || 'Unknown';
        if (!latestByType.has(type)) {
            latestByType.set(type, sv);
        }
    });
    
    const displayList = Array.from(latestByType.values());
    displayList.sort((a, b) => {
         const dateA = new Date((a.created_at || a.violation_date || a.date) + ' ' + (a.violation_time || '00:00'));
         const dateB = new Date((b.created_at || b.violation_date || b.date) + ' ' + (b.violation_time || '00:00'));
         return dateB - dateA;
    });

    const renderList = (containerId, items, renderer) => {
        const container = document.getElementById(containerId);
        if (container) {
             container.className = 'detail-value-container';
             container.innerHTML = items.map(item => {
                 const content = renderer(item);
                 return `<div style="margin-bottom: 4px; min-height: 24px; line-height: 24px;">${content}</div>`;
             }).join('');
        }
    };

    // Types
    renderList('detailViolationType', displayList, sv => {
        const type = sv.violation_type_name || sv.violationTypeLabel || sv.violation_type || 'Unknown';
        return `<span class="badge ${getViolationTypeClass(type)}">${formatViolationType(type)}</span>`;
    });

    // Levels
    renderList('detailViolationLevel', displayList, sv => {
        const level = sv.violation_level_name || sv.violationLevelLabel || sv.level || sv.offense_level || '-';
        const badgeClass = level !== '-' ? `badge ${getViolationLevelClass(level)}` : '';
        return `<span class="${badgeClass}">${level}</span>`;
    });

    // Dates
    renderList('detailDateTime', displayList, sv => {
        const dateStr = formatDate(sv.created_at || sv.violation_date || sv.date);
        const timeStr = formatTime(sv.violation_time || '');
        return `${dateStr} ${timeStr ? '• ' + timeStr : ''}`;
    });

    // Locations
    renderList('detailLocation', displayList, sv => sv.locationLabel || sv.location || '-');

    // Reported By
    renderList('detailReportedBy', displayList, sv => sv.reported_by || sv.reportedBy || '-');
    
    // Statuses
    renderList('detailStatus', displayList, sv => {
        let itemStatus = (sv.status || '').toLowerCase();
        let itemStatusLabel = sv.statusLabel || (itemStatus ? itemStatus.charAt(0).toUpperCase() + itemStatus.slice(1) : 'Unknown');
        
        const svLevelLabel = (sv.violation_level_name || sv.violationLevelLabel || sv.level || sv.offense_level || '').toLowerCase();
        const svIsDisciplinary = svLevelLabel.includes('warning 3') || svLevelLabel.includes('3rd') || svLevelLabel.includes('5th offense') || svLevelLabel.includes('disciplinary');
        
        if (itemStatus === 'resolved') {
            itemStatusLabel = 'Resolved';
        } else if (itemStatus === 'permitted') {
            itemStatusLabel = 'Permitted';
        } else if (svIsDisciplinary || itemStatus === 'disciplinary') {
            itemStatus = 'disciplinary';
            itemStatusLabel = 'Disciplinary';
        }
        
        return `<span class="badge ${getStatusClass(itemStatus)}">${itemStatusLabel}</span>`;
    });

    // --- Description / Notes ---
    setElementText('detailNotes', v.notes || v.description || 'No description provided.');

    // --- Evidence / Attachments (Match Admin) ---
    const attachmentsContainer = document.getElementById('detailAttachments');
    const evidenceSection = document.getElementById('evidenceSection');
    if (attachmentsContainer) {
        if (v.attachments && v.attachments.length > 0) {
            attachmentsContainer.innerHTML = v.attachments.map(filePath => {
                const fullUrl = getImageUrl(filePath);
                const fileName = filePath.split('/').pop();
                const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(fileName);
                
                return `<a href="${fullUrl}" target="_blank" class="attachment-item">
                    <i class='bx ${isImage ? 'bx-image' : 'bx-file'}'></i>
                    <span>${fileName}</span>
                </a>`;
            }).join('');
            if (evidenceSection) evidenceSection.style.display = 'block';
        } else {
            attachmentsContainer.innerHTML = '';
            if (evidenceSection) evidenceSection.style.display = 'none';
        }
    }

    // --- Resolution (if exists) ---
    const resSection = document.getElementById('resolutionSection');
    const resText = document.getElementById('detailResolution');
    if (v.resolution || v.resolution_notes) {
        resSection.style.display = 'block';
        resText.textContent = v.resolution || v.resolution_notes;
    } else {
        resSection.style.display = 'none';
    }

    // --- History Timeline (Match Admin Deduplication) ---
    const timelineEl = document.getElementById('detailTimeline');
    if (timelineEl) {
        let studentHistory = [...userViolations];
        
        // Deduplicate history for timeline
        const seenHistory = new Set();
        studentHistory = studentHistory.filter(h => {
            const hType = h.violation_type_name || h.violationTypeLabel || h.violation_type || 'Type';
            const hLevel = h.violation_level_name || h.violationLevelLabel || h.level || h.offense_level || 'Level';
            const hDate = h.created_at || h.violation_date || h.date;
            const hTime = h.violation_time || '00:00';
            const hLoc = h.locationLabel || h.location || 'N/A';
            const hBy = h.reported_by || h.reportedBy || 'N/A';
            
            const key = `${hType}|${hLevel}|${hDate}|${hTime}|${hLoc}|${hBy}`;
            if (seenHistory.has(key)) return false;
            seenHistory.add(key);
            return true;
        });
        
        // Sort by date descending
        studentHistory.sort((a, b) => {
             const dateA = new Date((a.created_at || a.violation_date || a.date) + ' ' + (a.violation_time || '00:00'));
             const dateB = new Date((b.created_at || b.violation_date || b.date) + ' ' + (b.violation_time || '00:00'));
             return dateB - dateA;
        });

        if (studentHistory.length > 0) {
            timelineEl.innerHTML = studentHistory.map(h => {
                const vType = v.violation_type_name || v.violationTypeLabel || v.violation_type || 'Type';
                const vLevel = v.violation_level_name || v.violationLevelLabel || v.level || v.offense_level || 'Level';
                const vDate = v.created_at || v.violation_date || v.date;
                const vTime = v.violation_time || '00:00';
                const vLoc = v.locationLabel || v.location || 'N/A';
                const vBy = v.reported_by || v.reportedBy || 'N/A';
                
                const hType = h.violation_type_name || h.violationTypeLabel || h.violation_type || 'Type';
                const hLevel = h.violation_level_name || h.violationLevelLabel || h.level || h.offense_level || 'Level';
                const hDate = h.created_at || h.violation_date || h.date;
                const hTime = h.violation_time || '00:00';
                const hLoc = h.locationLabel || h.location || 'N/A';
                const hBy = h.reported_by || h.reportedBy || 'N/A';

                const viewingKey = `${vType}|${vLevel}|${vDate}|${vTime}|${vLoc}|${vBy}`;
                const currentKey = `${hType}|${hLevel}|${hDate}|${hTime}|${hLoc}|${hBy}`;
                
                const isCurrent = viewingKey === currentKey;
                const activeClass = isCurrent ? 'current-viewing' : '';
                const hDateStr = formatDate(hDate);
                const hTimeStr = formatTime(hTime);
                
                let itemStatus = (h.status || '').toLowerCase();
                const hlLabel = hLevel.toLowerCase();
                const hIsDisciplinary = hlLabel.includes('warning 3') || hlLabel.includes('3rd') || hlLabel.includes('5th offense') || hlLabel.includes('disciplinary');
                
                let statusHtml = '';
                if (itemStatus === 'resolved' || itemStatus === 'permitted') {
                    const label = hIsDisciplinary ? 'Resolved' : 'Permitted';
                    statusHtml = `<span style="color: green; font-weight: bold;">(${label})</span>`;
                } else if (hIsDisciplinary || itemStatus === 'disciplinary') {
                    statusHtml = '<span style="color: #e74c3c; font-weight: bold;">(Disciplinary)</span>';
                }

                return `
                <div class="timeline-item ${activeClass}">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <span class="timeline-date">${hDateStr} ${hTimeStr ? '• ' + hTimeStr : ''}</span>
                        <span class="timeline-title">
                            ${hLevel} - ${formatViolationType(hType)}
                            ${isCurrent ? '<span style="font-size: 10px; background: #eee; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">Current</span>' : ''}
                        </span>
                        <span class="timeline-desc">
                            Reported at ${hLoc} 
                            ${statusHtml}
                        </span>
                    </div>
                </div>
            `}).join('');
        } else {
            timelineEl.innerHTML = '<p style="color: #6c757d; font-size: 14px; text-align: center; padding: 10px;">No history available.</p>';
        }
    }

    // Show Modal
    const modal = document.getElementById('ViolationDetailsModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeViolationModal() {
    const modal = document.getElementById('ViolationDetailsModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('ViolationDetailsModal');
    if (modal && modal.style.display === 'flex') {
        if (e.target === modal || e.target.classList.contains('Violations-modal-overlay')) {
            closeViolationModal();
        }
    }
});

// Global state for download context
window.downloadContext = 'violations'; // 'violations' or 'dashboard'

function downloadViolationsReport() {
    if (!userViolations || userViolations.length === 0) {
        alert('No violations to download.');
        return;
    }
    window.downloadContext = 'violations';
    openDownloadModal();
}

function openDownloadModal() {
    const modal = document.getElementById('DownloadFormatModal');
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
}

function closeDownloadModal() {
    const modal = document.getElementById('DownloadFormatModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }
}

function confirmDownload(format) {
    closeDownloadModal();
    
    // Give modal time to close
    setTimeout(() => {
        if (window.downloadContext === 'violations') {
            if (format === 'csv') downloadCSV(userViolations, 'my_violations');
            else if (format === 'pdf') downloadPDF(userViolations, 'My Violation Report', 'my_violations');
            else if (format === 'docx') downloadDOCX(userViolations, 'My Violation Report', 'my_violations');
        } else if (window.downloadContext === 'dashboard') {
            if (window.downloadDashboardReport) {
                window.downloadDashboardReport(format);
            }
        }
    }, 300);
}

// Export functions to global scope for the modal
window.openDownloadModal = openDownloadModal;
window.closeDownloadModal = closeDownloadModal;
window.confirmDownload = confirmDownload;

function downloadCSV(data, filenamePrefix) {
    const lines = [];
    const now = new Date();
    
    // Get student name from cookies
    const cookies = Object.fromEntries(
        document.cookie.split(';').map(c => c.trim().split('=')).map(([k,v]) => [k, decodeURIComponent(v)])
    );
    const studentName = cookies.full_name || cookies.username || 'Student';
    const studentIdCode = cookies.student_id_code || cookies.student_id || 'N/A';
    
    // Header Info
    lines.push('My Violation Report');
    lines.push('Generated by,' + csvEscape(studentName));
    lines.push('Student ID,' + csvEscape(studentIdCode));
    lines.push('Generated on,' + csvEscape(now.toLocaleString()));
    lines.push('');
    
    // Column Headers
    lines.push(['Case ID', 'Violation Type', 'Level', 'Status', 'Date Reported'].map(csvEscape).join(','));

    // Data Rows
    data.forEach(v => {
        const type = formatViolationType(v.violationTypeLabel || v.violation_type_name || v.violation_type || v.type || 'Unknown');
        const date = formatDate(v.created_at || v.violation_date || v.date);
        const level = v.violationLevelLabel || v.violation_level_name || v.violation_level || v.level || 'Minor';
        const status = v.status || 'Unknown';
        
        lines.push([
            v.case_id || v.id,
            type,
            level,
            status,
            date
        ].map(csvEscape).join(','));
    });

    const csvContent = lines.join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const fileName = filenamePrefix + '_' + now.toISOString().slice(0, 10) + '.csv';
    
    if (typeof saveAs === 'function') {
        saveAs(blob, fileName);
    } else {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
}

async function downloadPDF(data, title, filenamePrefix) {
    if (!window.jspdf) {
        alert('PDF library not loaded. Please refresh the page.');
        return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const now = new Date();

    // Get student name from cookies
    const cookies = Object.fromEntries(
        document.cookie.split(';').map(c => c.trim().split('=')).map(([k,v]) => [k, decodeURIComponent(v)])
    );
    const studentName = cookies.full_name || cookies.username || 'Student';
    const studentIdCode = cookies.student_id_code || cookies.student_id || 'N/A';

    // Calculate statistics for charts
    const stats = calculateViolationStats(data);

    // Add header image
    try {
        const headerImg = new Image();
        headerImg.src = '../app/assets/headers/header.png';
        await new Promise((resolve, reject) => {
            headerImg.onload = resolve;
            headerImg.onerror = reject;
            setTimeout(reject, 3000);
        });
        
        // Add header image at top (centered)
        const imgWidth = 180;
        const imgHeight = (headerImg.height / headerImg.width) * imgWidth;
        const xPos = (doc.internal.pageSize.width - imgWidth) / 2;
        doc.addImage(headerImg, 'PNG', xPos, 10, imgWidth, imgHeight);
        
        // Title below header
        let currentY = 10 + imgHeight + 10;
        doc.setFontSize(18);
        doc.setTextColor(0);
        doc.text(title, 14, currentY);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(`Generated by: ${studentName} (${studentIdCode})`, 14, currentY + 7);
        doc.text(`Generated on: ${now.toLocaleString()}`, 14, currentY + 12);

        currentY += 22;

        // Add statistics summary boxes
        doc.setFontSize(11);
        doc.setTextColor(0);
        doc.setFillColor(212, 175, 55);
        doc.rect(14, currentY, 60, 20, 'F');
        doc.setTextColor(255);
        doc.text('Total Violations', 44, currentY + 8, { align: 'center' });
        doc.setFontSize(16);
        doc.text(String(data.length), 44, currentY + 15, { align: 'center' });

        doc.setFillColor(239, 68, 68);
        doc.rect(80, currentY, 60, 20, 'F');
        doc.setFontSize(11);
        doc.text('Active', 110, currentY + 8, { align: 'center' });
        doc.setFontSize(16);
        doc.text(String(stats.active), 110, currentY + 15, { align: 'center' });

        doc.setFillColor(34, 197, 94);
        doc.rect(146, currentY, 50, 20, 'F');
        doc.setFontSize(11);
        doc.text('Resolved', 171, currentY + 8, { align: 'center' });
        doc.setFontSize(16);
        doc.text(String(stats.resolved), 171, currentY + 15, { align: 'center' });

        currentY += 28;

        // Generate charts as images
        const chartImages = await generateChartsForPDF(data, stats);
        
        if (chartImages.byType) {
            doc.setFontSize(12);
            doc.setTextColor(0);
            doc.text('Violations by Type', 14, currentY);
            currentY += 5;
            doc.addImage(chartImages.byType, 'PNG', 14, currentY, 90, 60);
        }

        if (chartImages.byStatus) {
            doc.text('Violations by Status', 110, currentY - 5);
            doc.addImage(chartImages.byStatus, 'PNG', 110, currentY, 90, 60);
        }

        currentY += 68;

        // Table Data
        const tableBody = data.map(v => [
            v.case_id || v.id,
            formatViolationType(v.violationTypeLabel || v.violation_type_name || v.violation_type || v.type || 'Unknown'),
            v.violationLevelLabel || v.violation_level_name || v.violation_level || v.level || 'Minor',
            v.status || 'Unknown',
            formatDate(v.created_at || v.violation_date || v.date)
        ]);

        doc.autoTable({
            head: [['Case ID', 'Violation Type', 'Level', 'Status', 'Date Reported']],
            body: tableBody,
            startY: currentY,
            theme: 'grid',
            styles: { fontSize: 9, cellPadding: 2 },
            headStyles: { fillColor: [212, 175, 55], textColor: 255, fontStyle: 'bold' }
        });
    } catch (error) {
        console.warn('Could not load header image, generating PDF without it:', error);
        
        // Fallback without header image
        let currentY = 22;
        doc.setFontSize(18);
        doc.text(title, 14, currentY);
        
        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(`Generated by: ${studentName} (${studentIdCode})`, 14, currentY + 8);
        doc.text(`Generated on: ${now.toLocaleString()}`, 14, currentY + 14);

        currentY += 24;

        // Add statistics summary boxes
        doc.setFontSize(11);
        doc.setTextColor(0);
        doc.setFillColor(212, 175, 55);
        doc.rect(14, currentY, 60, 20, 'F');
        doc.setTextColor(255);
        doc.text('Total Violations', 44, currentY + 8, { align: 'center' });
        doc.setFontSize(16);
        doc.text(String(data.length), 44, currentY + 15, { align: 'center' });

        doc.setFillColor(239, 68, 68);
        doc.rect(80, currentY, 60, 20, 'F');
        doc.setFontSize(11);
        doc.text('Active', 110, currentY + 8, { align: 'center' });
        doc.setFontSize(16);
        doc.text(String(stats.active), 110, currentY + 15, { align: 'center' });

        doc.setFillColor(34, 197, 94);
        doc.rect(146, currentY, 50, 20, 'F');
        doc.setFontSize(11);
        doc.text('Resolved', 171, currentY + 8, { align: 'center' });
        doc.setFontSize(16);
        doc.text(String(stats.resolved), 171, currentY + 15, { align: 'center' });

        currentY += 28;

        // Generate charts
        const chartImages = await generateChartsForPDF(data, stats);
        
        if (chartImages.byType) {
            doc.setFontSize(12);
            doc.setTextColor(0);
            doc.text('Violations by Type', 14, currentY);
            currentY += 5;
            doc.addImage(chartImages.byType, 'PNG', 14, currentY, 90, 60);
        }

        if (chartImages.byStatus) {
            doc.text('Violations by Status', 110, currentY - 5);
            doc.addImage(chartImages.byStatus, 'PNG', 110, currentY, 90, 60);
        }

        currentY += 68;

        const tableBody = data.map(v => [
            v.case_id || v.id,
            formatViolationType(v.violationTypeLabel || v.violation_type_name || v.violation_type || v.type || 'Unknown'),
            v.violationLevelLabel || v.violation_level_name || v.violation_level || v.level || 'Minor',
            v.status || 'Unknown',
            formatDate(v.created_at || v.violation_date || v.date)
        ]);

        doc.autoTable({
            head: [['Case ID', 'Violation Type', 'Level', 'Status', 'Date Reported']],
            body: tableBody,
            startY: currentY,
            theme: 'grid',
            styles: { fontSize: 9, cellPadding: 2 },
            headStyles: { fillColor: [212, 175, 55], textColor: 255, fontStyle: 'bold' }
        });
    }

    doc.save(`${filenamePrefix}_${now.toISOString().slice(0, 10)}.pdf`);
}

// Helper function to calculate violation statistics
function calculateViolationStats(data) {
    const stats = {
        total: data.length,
        active: 0,
        resolved: 0,
        byType: {},
        byStatus: {}
    };

    data.forEach(v => {
        const status = (v.status || 'pending').toLowerCase();
        const type = formatViolationType(v.violationTypeLabel || v.violation_type_name || v.violation_type || v.type || 'Unknown');
        
        // Count by status
        if (status === 'resolved' || status === 'permitted') {
            stats.resolved++;
        } else {
            stats.active++;
        }

        // Count by type
        stats.byType[type] = (stats.byType[type] || 0) + 1;
        
        // Count by status detail
        stats.byStatus[status] = (stats.byStatus[status] || 0) + 1;
    });

    return stats;
}

// Helper function to generate charts as images for PDF
async function generateChartsForPDF(data, stats) {
    const chartImages = {};

    try {
        // Create temporary canvas for charts
        const canvas = document.createElement('canvas');
        canvas.width = 400;
        canvas.height = 300;
        const ctx = canvas.getContext('2d');

        // Chart 1: Violations by Type (Bar Chart)
        if (window.Chart && Object.keys(stats.byType).length > 0) {
            const typeLabels = Object.keys(stats.byType);
            const typeData = Object.values(stats.byType);
            
            const typeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: typeLabels,
                    datasets: [{
                        label: 'Count',
                        data: typeData,
                        backgroundColor: ['#D4AF37', '#EF4444', '#3B82F6', '#10B981', '#F59E0B'],
                        borderColor: ['#B8860B', '#DC2626', '#2563EB', '#059669', '#D97706'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });

            await new Promise(resolve => setTimeout(resolve, 500));
            chartImages.byType = canvas.toDataURL('image/png');
            typeChart.destroy();
        }

        // Chart 2: Violations by Status (Pie Chart)
        if (window.Chart && Object.keys(stats.byStatus).length > 0) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            const statusLabels = Object.keys(stats.byStatus).map(s => s.charAt(0).toUpperCase() + s.slice(1));
            const statusData = Object.values(stats.byStatus);
            
            const statusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: ['#EF4444', '#F59E0B', '#10B981', '#3B82F6'],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: false,
                    plugins: {
                        legend: { 
                            display: true,
                            position: 'bottom',
                            labels: { boxWidth: 12, padding: 8, font: { size: 10 } }
                        }
                    }
                }
            });

            await new Promise(resolve => setTimeout(resolve, 500));
            chartImages.byStatus = canvas.toDataURL('image/png');
            statusChart.destroy();
        }

        canvas.remove();
    } catch (error) {
        console.warn('Could not generate charts:', error);
    }

    return chartImages;
}

async function downloadDOCX(data, title, filenamePrefix) {
    if (!window.docx) {
        alert('DOCX library not loaded. Please refresh the page.');
        return;
    }

    const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType, HeadingLevel, ImageRun, AlignmentType } = window.docx;
    const now = new Date();

    // Get student name from cookies
    const cookies = Object.fromEntries(
        document.cookie.split(';').map(c => c.trim().split('=')).map(([k,v]) => [k, decodeURIComponent(v)])
    );
    const studentName = cookies.full_name || cookies.username || 'Student';
    const studentIdCode = cookies.student_id_code || cookies.student_id || 'N/A';

    // Load header image
    let headerImage = null;
    try {
        const response = await fetch('../app/assets/headers/header.png');
        const blob = await response.blob();
        const arrayBuffer = await blob.arrayBuffer();
        
        headerImage = new Paragraph({
            children: [
                new ImageRun({
                    data: arrayBuffer,
                    transformation: {
                        width: 600,
                        height: 100,
                    },
                }),
            ],
            alignment: AlignmentType.CENTER,
            spacing: { after: 400 },
        });
    } catch (error) {
        console.warn('Could not load header image for DOCX:', error);
    }

    // Table Header
    const tableHeader = new TableRow({
        children: [
            new TableCell({ children: [new Paragraph({ text: "Case ID", bold: true })] }),
            new TableCell({ children: [new Paragraph({ text: "Violation Type", bold: true })] }),
            new TableCell({ children: [new Paragraph({ text: "Level", bold: true })] }),
            new TableCell({ children: [new Paragraph({ text: "Status", bold: true })] }),
            new TableCell({ children: [new Paragraph({ text: "Date Reported", bold: true })] }),
        ],
    });

    // Table Rows
    const tableRows = data.map(v => {
        return new TableRow({
            children: [
                new TableCell({ children: [new Paragraph(String(v.case_id || v.id))] }),
                new TableCell({ children: [new Paragraph(formatViolationType(v.violationTypeLabel || v.violation_type_name || v.violation_type || v.type || 'Unknown'))] }),
                new TableCell({ children: [new Paragraph(v.violationLevelLabel || v.violation_level_name || v.violation_level || v.level || 'Minor')] }),
                new TableCell({ children: [new Paragraph(v.status || 'Unknown')] }),
                new TableCell({ children: [new Paragraph(formatDate(v.created_at || v.violation_date || v.date))] }),
            ],
        });
    });

    const children = [];
    
    // Add header image if loaded
    if (headerImage) {
        children.push(headerImage);
    }
    
    // Add title and metadata
    children.push(
        new Paragraph({
            text: title,
            heading: HeadingLevel.HEADING_1,
            spacing: { after: 200 },
        }),
        new Paragraph({
            children: [
                new TextRun({
                    text: `Generated by: ${studentName} (${studentIdCode})`,
                    italics: true,
                    color: "666666",
                }),
            ],
            spacing: { after: 100 },
        }),
        new Paragraph({
            children: [
                new TextRun({
                    text: `Generated on: ${now.toLocaleString()}`,
                    italics: true,
                    color: "666666",
                }),
            ],
            spacing: { after: 400 },
        }),
        new Table({
            rows: [tableHeader, ...tableRows],
            width: {
                size: 100,
                type: WidthType.PERCENTAGE,
            },
        })
    );

    const doc = new Document({
        sections: [{
            properties: {},
            children: children,
        }],
    });

    Packer.toBlob(doc).then(blob => {
        saveAs(blob, `${filenamePrefix}_${now.toISOString().slice(0, 10)}.docx`);
    });
}

function csvEscape(value) {
    if (value === null || value === undefined) return '';
    const str = String(value);
    if (/[",\n]/.test(str)) {
        return '"' + str.replace(/"/g, '""') + '"';
    }
    return str;
}

function printViolationSlip() {
    if (!currentViolationId) {
        alert('No violation selected');
        return;
    }
    const url = `${API_BASE}violations.php?action=generate_slip&violation_id=${currentViolationId}`;
    window.open(url, '_blank');
}

/*********************************************************
 * UTILS
 *********************************************************/
async function updateSlipStatusUI(violationId) {
    const requestBtn = document.getElementById('requestSlipBtn');
    const downloadBtn = document.getElementById('downloadSlipBtn');
    
    if (!requestBtn || !downloadBtn) return;

    // Default: hide both
    requestBtn.style.display = 'none';
    downloadBtn.style.display = 'none';

    try {
        const response = await fetch(`${API_BASE}violations.php?action=slip_status&violation_id=${violationId}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            const status = result.data.request_status;
            
            if (!status) {
                // No request yet
                requestBtn.style.display = 'inline-flex';
                requestBtn.innerHTML = "<i class='bx bx-paper-plane'></i> Request Receipt";
                requestBtn.disabled = false;
            } else if (status === 'pending') {
                // Request sent, waiting for admin
                requestBtn.style.display = 'inline-flex';
                requestBtn.innerHTML = "<i class='bx bx-time'></i> Pending Approval";
                requestBtn.disabled = true;
            } else if (status === 'approved') {
                // Approved, can download
                downloadBtn.style.display = 'inline-flex';
            } else if (status === 'denied') {
                // Denied, can request again
                requestBtn.style.display = 'inline-flex';
                requestBtn.innerHTML = "<i class='bx bx-redo'></i> Request Again (Denied)";
                requestBtn.disabled = false;
            }
        }
    } catch (error) {
        console.error('Error checking slip status:', error);
    }
}

async function handleStudentSlipRequest() {
    if (!currentViolationId) return;
    
    const requestBtn = document.getElementById('requestSlipBtn');
    if (requestBtn) {
        requestBtn.disabled = true;
        requestBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Sending...";
    }

    try {
        const response = await fetch(`${API_BASE}violations.php?action=request_slip&violation_id=${currentViolationId}`, {
            method: 'POST'
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            showNotification(result.message, 'success');
            updateSlipStatusUI(currentViolationId);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        showNotification('Request failed: ' + error.message, 'error');
        if (requestBtn) {
            requestBtn.disabled = false;
            requestBtn.innerHTML = "<i class='bx bx-paper-plane'></i> Request Receipt";
        }
    }
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatViolationType(type) {
    if (!type) return 'Unknown';
    type = String(type);
    
    const typeMap = {
        'improper_uniform': 'Improper Uniform',
        'improper_footwear': 'Improper Footwear',
        'no_id': 'No ID Card',
        'misconduct': 'Misconduct'
    };
    
    if (typeMap[type.toLowerCase()]) return typeMap[type.toLowerCase()];
    
    const lowerType = type.toLowerCase();
    for (const [key, value] of Object.entries(typeMap)) {
        if (lowerType.includes(key.replace('_', ' ')) || lowerType === key) {
            return value;
        }
    }
    
    return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

/*********************************************************
 * EXPORTS
 *********************************************************/
window.initUserViolations = initUserViolations;
window.filterViolations = filterViolations;
window.viewViolationDetails = viewViolationDetails;
window.closeViolationModal = closeViolationModal;
window.printViolationSlip = printViolationSlip;
window.downloadCSV = downloadCSV;
window.downloadPDF = downloadPDF;
window.downloadDOCX = downloadDOCX;
window.updateViolationStats = updateViolationStats;

/*********************************************************
 * DRAG SCROLL — violation-details-grid table
 *********************************************************/
(function initDragScroll() {
    function attachDragScroll(el) {
        if (!el) return;
        let isDown = false, startX, scrollLeft;

        el.addEventListener('mousedown', e => {
            isDown = true;
            el.classList.add('dragging');
            startX = e.pageX - el.offsetLeft;
            scrollLeft = el.scrollLeft;
        });
        el.addEventListener('mouseleave', () => { isDown = false; el.classList.remove('dragging'); });
        el.addEventListener('mouseup',    () => { isDown = false; el.classList.remove('dragging'); });
        el.addEventListener('mousemove',  e => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - el.offsetLeft;
            el.scrollLeft = scrollLeft - (x - startX) * 1.5;
        });

        // Touch support
        let touchStartX, touchScrollLeft;
        el.addEventListener('touchstart', e => {
            touchStartX = e.touches[0].pageX - el.offsetLeft;
            touchScrollLeft = el.scrollLeft;
        }, { passive: true });
        el.addEventListener('touchmove', e => {
            const x = e.touches[0].pageX - el.offsetLeft;
            el.scrollLeft = touchScrollLeft - (x - touchStartX);
        }, { passive: true });
    }

    // Attach when modal opens (grid may not exist yet on DOMContentLoaded)
    const observer = new MutationObserver(() => {
        const grid = document.querySelector('.violation-details-grid');
        if (grid && !grid.dataset.dragInit) {
            grid.dataset.dragInit = '1';
            attachDragScroll(grid);
        }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    // Also try immediately
    document.addEventListener('DOMContentLoaded', () => {
        const grid = document.querySelector('.violation-details-grid');
        if (grid) { grid.dataset.dragInit = '1'; attachDragScroll(grid); }
    });
})();
