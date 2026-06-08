// reports.js - Complete working version
function initReportsModule() {
    console.log('🛠 Reports module initializing...');
    
    try {
        // Elements
        const tableBody = document.getElementById('ReportsTableBody');
        const btnGenerateReport = document.getElementById('btnGenerateReports');
        const btnGenerateFirst = document.getElementById('btnGenerateFirstReport');
        const btnExportReports = document.getElementById('btnExportReports');
        const exportModal = document.getElementById('ExportReportsModal');
        const closeExportBtn = document.getElementById('closeExportModal');
        const exportModalOverlay = document.getElementById('ExportModalOverlay');
        const exportPDFBtn = document.getElementById('exportPDF');
        const exportExcelBtn = document.getElementById('exportExcel');
        const exportWordBtn = document.getElementById('exportWord');
        const btnRefreshReports = document.getElementById('btnRefreshReports');
        const generateModal = document.getElementById('ReportsGenerateModal');
        const detailsModal = document.getElementById('ReportDetailsModal');
        const closeGenerateBtn = document.getElementById('closeReportsModal');
        const closeDetailsBtn = document.getElementById('closeDetailsModal');
        const cancelGenerateBtn = document.getElementById('cancelReportsModal');
        const generateOverlay = document.getElementById('ReportsModalOverlay');
        const detailsOverlay = document.getElementById('DetailsModalOverlay');
        const generateForm = document.getElementById('ReportsGenerateForm');
        const searchInput = document.getElementById('searchReport');
        const deptFilter = document.getElementById('ReportsDepartmentFilter');
        const sectionFilter = document.getElementById('ReportsSectionFilter');
        const statusFilter = document.getElementById('ReportsStatusFilter');
        const violationTypeFilter = document.getElementById('ReportsViolationTypeFilter');
        const timeFilter = document.getElementById('ReportsTimeFilter');
        const sortByFilter = document.getElementById('ReportsSortBy');
        const applyFiltersBtn = document.getElementById('applyFilters');
        const clearFiltersBtn = document.getElementById('clearFilters');
        const resetFiltersBtn = document.getElementById('resetFilters');
        const dateRangeGroup = document.getElementById('dateRangeGroup');
        const viewButtons = document.querySelectorAll('.Reports-view-btn');
        const paginationContainer = document.querySelector('.Reports-pagination');

        // Debug logging
        console.log('🔍 Generate button found:', btnGenerateReport);
        console.log('🔍 Generate modal found:', generateModal);

        if (!btnGenerateReport) {
            console.error('❌ #btnGenerateReports NOT FOUND!');
            return;
        }

        if (!generateModal) {
            console.error('❌ #ReportsGenerateModal NOT FOUND!');
            return;
        }

        // ========== API CONFIG ==========
        
        function getAPIBasePath() {
            const p = window.location.pathname.split('/').filter(Boolean);
            const d = ['app','api','includes','assets','public'];
            return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/api/';
        }
        
        const API_BASE = getAPIBasePath();
        console.log('🔗 Reports API Base Path:', API_BASE);
        
        // ========== DATA ==========
        
        // Reports data loaded from API
        let reports = [];
        let allReports = []; // Store all reports for client-side filtering
        let reportViolationTypes = [];

        let currentPage = 1;
        let itemsPerPage = 10;
        let totalRecords = 0;
        let totalPages = 1;
        let viewMode = localStorage.getItem('reportsViewMode') || 'list'; // 'table', 'grid', 'list'

        // ========== HELPER FUNCTIONS ==========
        
        function getTypeIcon(name, solid = false) {
            const n = (name || '').toLowerCase();
            const prefix = solid ? 'bxs-' : 'bx-';
            if (n.includes('uniform')) return prefix + 't-shirt';
            if (n.includes('footwear') || n.includes('shoe')) return prefix + 'walk';
            if (n.includes('id')) return prefix + 'id-card';
            if (n.includes('misconduct') || n.includes('behavior')) return prefix + 'error';
            return prefix + 'error-circle';
        }

        function getReportTypeCount(report, typeId) {
            const id = String(typeId);
            if (report.typeCounts) {
                if (report.typeCounts[typeId] !== undefined) return parseInt(report.typeCounts[typeId], 10) || 0;
                if (report.typeCounts[id] !== undefined) return parseInt(report.typeCounts[id], 10) || 0;
            }
            const type = reportViolationTypes.find(t => String(t.id) === id);
            if (!type) return 0;
            const name = (type.name || '').toLowerCase();
            if (name.includes('uniform')) return parseInt(report.uniformCount, 10) || 0;
            if (name.includes('footwear') || name.includes('shoe')) return parseInt(report.footwearCount, 10) || 0;
            if (name.includes('id')) return parseInt(report.noIdCount, 10) || 0;
            return 0;
        }

        function getReportsTableColspan() {
            return 6 + reportViolationTypes.length;
        }

        function renderTypeCountCells(report) {
            return reportViolationTypes.map(type => {
                const count = getReportTypeCount(report, type.id);
                const countClass = getCountBadgeClass(count);
                return `<td class="violation-count" data-label="${type.name}">
                    <div class="count-badge ${countClass}">${count}/5</div>
                </td>`;
            }).join('');
        }

        function renderTypeStatCards(typeStats) {
            const container = document.getElementById('ReportsTypeStatsContainer');
            if (!container) return;

            const stats = typeStats || [];
            if (!stats.length) {
                container.innerHTML = '<p class="Reports-stats-loading">No violation types found</p>';
                return;
            }

            container.innerHTML = stats.map(typeStat => {
                const icon = getTypeIcon(typeStat.name, true);
                const label = typeStat.name.length > 24 ? `${typeStat.name.slice(0, 22)}…` : typeStat.name;
                return `
                <div class="Reports-stat-card" title="${typeStat.name}">
                    <div class="Reports-stat-icon"><i class='bx ${icon}'></i></div>
                    <div class="Reports-stat-content">
                        <h3 class="Reports-stat-title">${label}</h3>
                        <div class="Reports-stat-value">${typeStat.count}</div>
                        <div class="Reports-stat-percentage">${typeStat.percentage}%</div>
                    </div>
                </div>`;
            }).join('');
        }

        function populateViolationTypeFilter(types) {
            if (!violationTypeFilter) return;
            const current = violationTypeFilter.value || 'all';
            violationTypeFilter.innerHTML = '<option value="all">All Types</option>';
            (types || []).forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                violationTypeFilter.appendChild(option);
            });
            if ([...violationTypeFilter.options].some(opt => opt.value === current)) {
                violationTypeFilter.value = current;
            }
        }

        function updateSortByOptions() {
            if (!sortByFilter) return;
            const current = sortByFilter.value;
            const baseOptions = [
                ['total_desc', 'Total Violations (High to Low)'],
                ['total_asc', 'Total Violations (Low to High)'],
                ['name_asc', 'Name (A to Z)'],
                ['name_desc', 'Name (Z to A)'],
                ['dept_asc', 'Department (A to Z)'],
                ['section_asc', 'Section (A to Z)']
            ];
            sortByFilter.innerHTML = '';
            baseOptions.forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                sortByFilter.appendChild(option);
            });
            reportViolationTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = `type_${type.id}_desc`;
                option.textContent = `${type.name} (High to Low)`;
                sortByFilter.appendChild(option);
            });
            if ([...sortByFilter.options].some(opt => opt.value === current)) {
                sortByFilter.value = current;
            }
        }

        function renderReportsTableHeader() {
            const row = document.getElementById('ReportsTableHeaderRow');
            if (!row) return;

            const typeHeaders = reportViolationTypes.map(type => `
                <th class="Reports-sortable" data-sort="type_${type.id}">
                    <div class="Reports-table-header-content">
                        <span>${type.name}</span>
                        <i class='bx bx-sort'></i>
                    </div>
                </th>
            `).join('');

            row.innerHTML = `
                <th>
                    <div class="Reports-table-header-content"><span>Student Info</span></div>
                </th>
                <th class="Reports-sortable" data-sort="department">
                    <div class="Reports-table-header-content"><span>Department</span><i class='bx bx-sort'></i></div>
                </th>
                <th class="Reports-sortable" data-sort="section">
                    <div class="Reports-table-header-content"><span>Section</span><i class='bx bx-sort'></i></div>
                </th>
                <th>
                    <div class="Reports-table-header-content"><span>Year Level</span></div>
                </th>
                ${typeHeaders}
                <th class="Reports-sortable" data-sort="total">
                    <div class="Reports-table-header-content"><span>Total Violations</span><i class='bx bx-sort'></i></div>
                </th>
                <th>
                    <div class="Reports-table-header-content"><span>Actions</span></div>
                </th>
            `;
        }

        function setReportViolationTypes(types) {
            reportViolationTypes = Array.isArray(types) ? types : [];
            renderReportsTableHeader();
            populateViolationTypeFilter(reportViolationTypes);
            updateSortByOptions();
        }

        function getDepartmentClass(deptCode) {
            const classes = {
                'BSIS': 'bsis',
                'WFT': 'wft',
                'BTVTED': 'btvted',
                'CHS': 'chs'
            };
            return classes[deptCode] || 'default';
        }

        function getStatusClass(status) {
            const classes = {
                'permitted': 'permitted',
                'warning': 'warning',
                'disciplinary': 'disciplinary'
            };
            return classes[status] || 'default';
        }

        function getCountBadgeClass(count) {
            if (count >= 3) return 'high';
            if (count >= 2) return 'medium';
            if (count >= 1) return 'low';
            return 'none';
        }

        function getReportPeriodLabel(report) {
            if (report.periodStart) {
                const d = new Date(report.periodStart);
                return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }
            if (report.lastUpdated) {
                const d = new Date(report.lastUpdated);
                return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            }
            return 'N/A';
        }

        function getStatusBreakdown(report) {
            if (!reportViolationTypes.length) {
                return `<span class="Reports-status-badge ${getStatusClass(report.status)}" style="font-size:9px;">Total: ${report.totalViolations}</span>`;
            }

            const offenseLabels = ['', '1st', '2nd', '3rd', '4th', '5th'];
            const parts = [];

            reportViolationTypes.forEach(type => {
                const count = getReportTypeCount(report, type.id);
                if (count <= 0) return;

                const level = Math.min(count, 5);
                let badgeClass;
                if (level <= 2) badgeClass = 'permitted';
                else if (level <= 4) badgeClass = 'warning';
                else badgeClass = 'disciplinary';

                const shortName = type.name.length > 14 ? `${type.name.slice(0, 12)}…` : type.name;
                const offenseLabel = level >= 5 ? 'DISCIPLINARY' : `${offenseLabels[level]} OFFENSE`;
                parts.push(`<span style="font-size:9px;color:var(--dark-grey);">${shortName}:</span> <strong class="Reports-status-badge ${badgeClass}" style="font-size:9px;padding:1px 6px;">${level}/5 ${offenseLabel}</strong>`);
            });

            if (parts.length === 0) {
                return `<span class="Reports-status-badge ${getStatusClass(report.status)}" style="font-size:9px;">Total: ${report.totalViolations}</span>`;
            }
            return parts.join(' ');
        }

        function getCurrentAdminName() {
            const sessionStr = localStorage.getItem('userSession');
            if (!sessionStr) return 'Admin';
            try {
                const session = JSON.parse(sessionStr);
                return session.full_name || session.name || session.username || 'Admin';
            } catch (e) {
                return 'Admin';
            }
        }

        function calculateStats(statsData = null) {
            let totalViolations, totalStudents, typeStats;

            if (statsData) {
                totalViolations = statsData.totalViolations || 0;
                totalStudents = statsData.totalStudents || reports.length;
                typeStats = statsData.typeStats || [];
            } else {
                totalViolations = reports.reduce((sum, report) => sum + report.totalViolations, 0);
                totalStudents = reports.length;
                typeStats = reportViolationTypes.map(type => {
                    const count = reports.reduce((sum, report) => sum + getReportTypeCount(report, type.id), 0);
                    return {
                        id: type.id,
                        name: type.name,
                        count,
                        percentage: totalViolations > 0 ? Math.round((count / totalViolations) * 100) : 0
                    };
                });
            }

            const totalViolationsEl = document.getElementById('totalViolationsCount');
            if (totalViolationsEl) totalViolationsEl.textContent = totalViolations;
            renderTypeStatCards(typeStats);
            const totalStudentsEl = document.getElementById('totalStudentsCount');
            const totalViolationsFooterEl = document.getElementById('totalViolationsFooter');
            const avgViolationsEl = document.getElementById('avgViolations');
            const totalReportsCountEl = document.getElementById('totalReportsCount');
            
            if (totalStudentsEl) totalStudentsEl.textContent = totalStudents;
            if (totalViolationsFooterEl) totalViolationsFooterEl.textContent = totalViolations;
            if (avgViolationsEl) avgViolationsEl.textContent = totalStudents > 0 ? (totalViolations / totalStudents).toFixed(1) : '0';
            if (totalReportsCountEl) totalReportsCountEl.textContent = totalStudents;
        }
        
        // ========== CHART FUNCTIONS ==========
        // Charts removed as per request
        
        function initCharts() {
            // Functionality removed
        }

        function updateCharts(data) {
            // Functionality removed
        }

        // ========== API FUNCTIONS ==========
        
        async function loadReports(showLoading = true) {
            try {
                if (showLoading) {
                    if (tableBody) {
                        tableBody.innerHTML = `<tr><td colspan="${getReportsTableColspan()}" style="text-align: center; padding: 20px;">Loading reports...</td></tr>`;
                    }
                }
                
                console.log('🔄 Loading reports from API...');
                
                // Build query parameters
                const params = new URLSearchParams();
                const deptValue = deptFilter ? deptFilter.value : 'all';
                const sectionValue = sectionFilter ? sectionFilter.value : 'all';
                const statusValue = statusFilter ? statusFilter.value : 'all';
                const violationTypeValue = violationTypeFilter ? violationTypeFilter.value : 'all';
                const timeValue = timeFilter ? timeFilter.value : 'all';
                const searchValue = searchInput ? searchInput.value.trim() : '';
                
                if (deptValue !== 'all') params.append('department', deptValue);
                if (sectionValue !== 'all') params.append('section', sectionValue);
                if (statusValue !== 'all') params.append('status', statusValue);
                if (violationTypeValue !== 'all') params.append('violationType', violationTypeValue);
                if (searchValue) params.append('search', searchValue);
                
                // Handle time period
                if (timeValue && timeValue !== 'all' && timeValue !== 'custom') {
                    params.append('timePeriod', timeValue);
                } else if (timeValue === 'custom') {
                    const startDate = document.getElementById('ReportsStart')?.value;
                    const endDate = document.getElementById('ReportsEnd')?.value;
                    if (startDate) params.append('startDate', startDate);
                    if (endDate) params.append('endDate', endDate);
                }
                
                const queryString = params.toString();
                const url = API_BASE + 'reports.php' + (queryString ? '?' + queryString : '');
                
                console.log('📡 Fetching from:', url);
                
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                console.log('📊 API Response:', data);
                
                if (data.status === 'error') {
                    console.error('❌ API Error:', data.message);
                    throw new Error(data.message || 'API returned error status');
                }
                
                // Store all reports
                allReports = data.reports || data.data || [];
                reports = [...allReports];

                if (data.violationTypes && data.violationTypes.length) {
                    setReportViolationTypes(data.violationTypes);
                }
                
                console.log(`✅ Loaded ${reports.length} reports from database`);
                
                if (reports.length === 0) {
                    if (tableBody) {
                        tableBody.innerHTML = `<tr><td colspan="${getReportsTableColspan()}">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:48px 24px;text-align:center;">
                                <div style="width:56px;height:56px;border-radius:50%;background:rgba(212,175,55,0.08);border:1.5px solid rgba(212,175,55,0.2);display:flex;align-items:center;justify-content:center;">
                                    <i class='bx bx-bar-chart-alt-2' style="font-size:24px;color:#D4AF37;"></i>
                                </div>
                                <div>
                                    <div style="font-size:14px;font-weight:700;color:var(--dark);margin-bottom:4px;">No Reports Found</div>
                                    <div style="font-size:12px;color:var(--dark-grey);line-height:1.6;">No violations match the current filters.<br>Try adjusting your search criteria.</div>
                                </div>
                            </div>
                        </td></tr>`;
                    }
                }
                
                // Update stats if provided
                if (data.stats) {
                    calculateStats(data.stats);
                } else {
                    calculateStats();
                }
                
                // Apply client-side filtering and sorting
            updateCharts(reports); // Initial chart update with all data
            renderReports();
        } catch (error) {
                console.error('❌ Error loading reports:', error);
                console.error('Error details:', error.stack);
                if (tableBody) {
                    tableBody.innerHTML = `<tr><td colspan="${getReportsTableColspan()}" style="text-align: center; padding: 20px; color: #e74c3c;">
                        <div style="margin-bottom: 10px;">❌ Error loading reports: ${error.message}</div>
                        <div style="font-size: 0.9em; color: #666;">Check browser console for details</div>
                    </td></tr>`;
                }
                calculateStats({
                    totalViolations: 0,
                    totalStudents: 0,
                    typeStats: reportViolationTypes.map(type => ({
                        id: type.id,
                        name: type.name,
                        count: 0,
                        percentage: 0
                    }))
                });
            }
        }

        // ========== RENDER FUNCTIONS ==========
        
        function renderReports() {
            if (!tableBody) return;
            
            // Client-side filtering for search and sort (department, section, status are handled by API)
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const sortValue = sortByFilter ? sortByFilter.value : 'total_desc';
            
            let filteredReports = reports;
            
            // Apply search filter (client-side)
            if (searchTerm) {
                filteredReports = filteredReports.filter(report => {
                    return report.studentName.toLowerCase().includes(searchTerm) || 
                           report.reportId.toLowerCase().includes(searchTerm) ||
                           report.studentId.toLowerCase().includes(searchTerm);
                });
            }

            // Sort reports
            filteredReports.sort((a, b) => {
                if (sortValue.startsWith('type_') && sortValue.endsWith('_desc')) {
                    const typeId = sortValue.replace('type_', '').replace('_desc', '');
                    return getReportTypeCount(b, typeId) - getReportTypeCount(a, typeId);
                }
                switch(sortValue) {
                    case 'total_desc':
                        return b.totalViolations - a.totalViolations;
                    case 'total_asc':
                        return a.totalViolations - b.totalViolations;
                    case 'name_asc':
                        return a.studentName.localeCompare(b.studentName);
                    case 'name_desc':
                        return b.studentName.localeCompare(a.studentName);
                    case 'dept_asc':
                        return a.department.localeCompare(b.department);
                    case 'section_asc':
                        return a.section.localeCompare(b.section);
                    default:
                        return b.id - a.id;
                }
            });

            // Update charts with filtered data
            updateCharts(filteredReports);

            totalRecords = filteredReports.length;
            totalPages = Math.ceil(totalRecords / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageItems = filteredReports.slice(start, end);

            // Show/hide empty state
            const emptyState = document.getElementById('ReportsEmptyState');
            if (emptyState) {
                emptyState.style.display = (filteredReports.length === 0 && reports.length === 0) ? 'flex' : 'none';
            }

            // Show/hide view containers
            const tableViewEl = document.getElementById('reportsTableView');
            const gridViewEl  = document.getElementById('reportsGridView');
            const listViewEl  = document.getElementById('reportsListView');
            if (tableViewEl) tableViewEl.style.display = viewMode === 'table' ? '' : 'none';
            if (gridViewEl)  gridViewEl.style.display  = viewMode === 'grid'  ? '' : 'none';
            if (listViewEl)  listViewEl.style.display  = viewMode === 'list'  ? '' : 'none';

            // ── Helper ──────────────────────────────────────────────────────
            function actionBtns(id) {
                return `<button class="Reports-action-btn view" data-id="${id}" title="View Details"><i class='bx bx-show'></i></button>
                        <button class="Reports-action-btn export" data-id="${id}" title="Export Report"><i class='bx bx-download'></i></button>`;
            }

            // ── TABLE VIEW ──────────────────────────────────────────────────
            if (filteredReports.length === 0 && reports.length > 0) {
                tableBody.innerHTML = `<tr><td colspan="${getReportsTableColspan()}" style="text-align:center;padding:20px;color:#666;">No reports match the current filters.</td></tr>`;
            } else {
                tableBody.innerHTML = pageItems.map(report => {
                    const deptClass    = getDepartmentClass(report.deptCode);
                    const totalClass   = getCountBadgeClass(report.totalViolations);
                    return `
                    <tr data-id="${report.id}">
                        <td class="report-student-info" data-label="Student">
                            <div class="student-info-wrapper">
                                <div class="student-avatar">
                                    <img src="${report.studentImage}" alt="${report.studentName}"
                                         onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(report.studentName)}&background=ffd700&color=333&size=32'">
                                </div>
                                <div class="student-details">
                                    <strong>${report.studentName}</strong>
                                    <small>${report.studentId} • ${report.section || 'N/A'}</small>
                                </div>
                            </div>
                        </td>
                        <td class="report-dept" data-label="Department">
                            <span class="dept-badge ${deptClass}">${report.department}</span>
                        </td>
                        <td class="report-section" data-label="Section">${report.section}</td>
                        <td class="report-yearlevel" data-label="Year Level">
                            <span class="yearlevel-badge">${report.yearlevel || 'N/A'}</span>
                        </td>
                        ${renderTypeCountCells(report)}
                        <td class="total-violations" data-label="Total">
                            <div class="total-badge ${totalClass}">${report.totalViolations}</div>
                        </td>
                        <td data-label="Actions">
                            <div class="Reports-action-buttons">${actionBtns(report.id)}</div>
                        </td>
                    </tr>`;
                }).join('');
            }

            // ── GRID / CARD VIEW ────────────────────────────────────────────
            const gridBody = document.getElementById('ReportsGridBody');
            if (gridBody) {
                if (pageItems.length === 0) {
                    gridBody.innerHTML = `<p style="text-align:center;color:#999;padding:40px;grid-column:1/-1;">No reports found</p>`;
                } else {
                    gridBody.innerHTML = pageItems.map(report => {
                        const deptClass    = getDepartmentClass(report.deptCode);
                        const totalClass   = getCountBadgeClass(report.totalViolations);
                        const typeCountItems = reportViolationTypes.map(type => {
                            const count = getReportTypeCount(report, type.id);
                            const countClass = getCountBadgeClass(count);
                            const shortName = type.name.length > 12 ? `${type.name.slice(0, 10)}…` : type.name;
                            return `
                                    <div class="report-card-count-item">
                                        <span class="report-card-count-label">${shortName}</span>
                                        <span class="report-card-count-value ${countClass}">${count}/5</span>
                                    </div>`;
                        }).join('');
                        return `
                        <div class="report-card" data-id="${report.id}">
                            <div class="report-card-top ${report.status}"></div>
                            <div class="report-card-body">
                                <div class="report-card-student">
                                    <img src="${report.studentImage}" alt="${report.studentName}" class="report-card-avatar"
                                         onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(report.studentName)}&background=ffd700&color=333&size=44'">
                                    <div class="report-card-student-info">
                                        <p class="report-card-name">${report.studentName}</p>
                                        <p class="report-card-id">${report.studentId}</p>
                                    </div>
                                </div>
                                <div style="display:flex;gap:5px;flex-wrap:wrap;margin:8px 0;">
                                    <span class="dept-badge ${deptClass}" style="font-size:9px;padding:2px 6px;">${report.department}</span>
                                    <span class="yearlevel-badge" style="font-size:9px;padding:2px 6px;min-width:auto;">Year ${report.yearlevel || 'N/A'}</span>
                                </div>
                                <div class="report-card-divider"></div>
                                <div class="report-card-counts">
                                    ${typeCountItems}
                                    <div class="report-card-count-item">
                                        <span class="report-card-count-label">Total</span>
                                        <span class="report-card-count-value ${totalClass}">${report.totalViolations}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="report-card-footer">
                                <span style="font-size:9px;color:var(--dark-grey);font-weight:600;">${getReportPeriodLabel(report)}</span>
                                <div class="report-card-actions">${actionBtns(report.id)}</div>
                            </div>
                        </div>`;
                    }).join('');
                }
            }

            // ── LIST VIEW ───────────────────────────────────────────────────
            const listBody = document.getElementById('ReportsListBody');
            if (listBody) {
                if (pageItems.length === 0) {
                    listBody.innerHTML = `<p style="text-align:center;color:#999;padding:40px;">No reports found</p>`;
                } else {
                    listBody.innerHTML = pageItems.map(report => {
                        const deptClass    = getDepartmentClass(report.deptCode);
                        const statusBreakdown = getStatusBreakdown(report);
                        const periodLabel = getReportPeriodLabel(report);
                        return `
                        <div class="report-list-item ${report.status}" data-id="${report.id}">
                            <div class="report-list-top">
                                <img src="${report.studentImage}" alt="${report.studentName}" class="report-list-avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(report.studentName)}&background=ffd700&color=333&size=36'">
                                <div class="report-list-name-block">
                                    <span class="report-list-name">${report.studentName}</span>
                                    <span class="report-list-id">${report.studentId}</span>
                                </div>
                                <div class="report-list-actions">${actionBtns(report.id)}</div>
                            </div>
                            <div class="report-list-badges">
                                <span class="dept-badge ${deptClass}" style="font-size:9px;padding:2px 7px;">${report.department}</span>
                                <span class="yearlevel-badge" style="font-size:9px;padding:2px 7px;min-width:auto;">${report.yearlevel || 'N/A'}</span>
                                <span style="font-size:9px;padding:2px 7px;background:rgba(100,116,139,.1);color:#64748b;border-radius:4px;font-weight:600;"><i class='bx bx-calendar' style="vertical-align:middle;font-size:10px;"></i> ${periodLabel}</span>
                                ${statusBreakdown}
                            </div>
                        </div>`;
                    }).join('');
                }
            }

            calculateStats();
            const showingEl = document.getElementById('showingReportsCount');
            const totalEl = document.getElementById('totalReportsCount');
            if (showingEl) showingEl.textContent = pageItems.length;
            if (totalEl) totalEl.textContent = totalRecords;

            renderReportsPagination();
        }

        function renderReportsPagination() {
            if (!paginationContainer) return;
            paginationContainer.innerHTML = '';

            const makeBtn = (label, opts = {}) => {
                const btn = document.createElement('button');
                btn.className = 'Reports-pagination-btn' + (opts.active ? ' active' : '');
                btn.textContent = label;
                if (opts.disabled) btn.disabled = true;
                if (opts.page) btn.dataset.page = String(opts.page);
                if (opts.action) btn.dataset.action = opts.action;
                return btn;
            };

            paginationContainer.appendChild(makeBtn('‹', { disabled: currentPage === 1, action: 'prev' }));

            const maxButtons = 7;
            let startPage = Math.max(1, currentPage - 3);
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            if (endPage - startPage + 1 < maxButtons) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            for (let p = startPage; p <= endPage; p++) {
                paginationContainer.appendChild(makeBtn(String(p), { active: p === currentPage, page: p }));
            }

            paginationContainer.appendChild(makeBtn('›', { disabled: currentPage === totalPages, action: 'next' }));
        }

        function handlePaginationClick(e) {
            const target = e.target.closest('button');
            if (!target) return;
            const action = target.dataset.action;
            const pageAttr = target.dataset.page;
            if (action === 'prev') {
                if (currentPage > 1) currentPage--;
            } else if (action === 'next') {
                if (currentPage < totalPages) currentPage++;
            } else if (pageAttr) {
                const pageNum = parseInt(pageAttr, 10);
                if (!isNaN(pageNum)) currentPage = pageNum;
            } else {
                return;
            }
            renderReports();
        }

        // ========== MODAL FUNCTIONS ==========
        
        function openGenerateModal() {
            console.log('🎯 Opening generate report modal...');
            generateModal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Ensure departments and violation types are loaded in the modal
            const generateDeptCheckboxes = document.getElementById('generateDeptCheckboxes');
            if (generateDeptCheckboxes && (generateDeptCheckboxes.innerHTML.trim() === '' || generateDeptCheckboxes.innerHTML.includes('Loading departments'))) {
                loadDepartments();
            }

            const generateViolationTypeCheckboxes = document.getElementById('generateViolationTypeCheckboxes');
            if (generateViolationTypeCheckboxes && (generateViolationTypeCheckboxes.innerHTML.trim() === '' || generateViolationTypeCheckboxes.innerHTML.includes('Loading violation types'))) {
                loadViolationTypes();
            }
            
            // Set default dates
            const today = new Date();
            const startDate = document.getElementById('startDate');
            const endDate = document.getElementById('endDate');
            
            if (startDate) {
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                startDate.value = firstDay.toISOString().split('T')[0];
            }
            
            if (endDate) {
                endDate.value = today.toISOString().split('T')[0];
            }
        }

        function openDetailsModal(reportId) {
            if (!detailsModal) return;
            
            const report = reports.find(r => r.id === reportId);
            if (!report) {
                console.error('Report not found:', reportId);
                return;
            }
            
            // Populate report header
            const reportHeader = detailsModal.querySelector('.report-header h3');
            if (reportHeader) {
                reportHeader.textContent = `Student Violation Analysis Report - ${report.studentName}`;
            }
            
            const reportIdEl = detailsModal.querySelector('.report-id');
            if (reportIdEl) {
                reportIdEl.textContent = `Report ID: ${report.reportId}`;
            }
            
            const reportDateEl = detailsModal.querySelector('.report-date');
            if (reportDateEl) {
                reportDateEl.textContent = `Generated: ${new Date().toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}`;
            }
            
            const reportAdminName = detailsModal.querySelector('#reportAdminName');
            if (reportAdminName) {
                reportAdminName.textContent = getCurrentAdminName();
            }

            // Populate student info
            const studentInfoGrid = detailsModal.querySelector('.student-info-grid');
            if (studentInfoGrid) {
                studentInfoGrid.innerHTML = `
                    <div class="info-item">
                        <span class="info-label">Student Name:</span>
                        <span class="info-value">${report.studentName}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Student ID:</span>
                        <span class="info-value">${report.studentId}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Department:</span>
                        <span class="info-value">${report.department}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Section:</span>
                        <span class="info-value">${report.section}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Year Level:</span>
                        <span class="info-value">${report.yearlevel || 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact No:</span>
                        <span class="info-value">${report.studentContact}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Report Period:</span>
                        <span class="info-value">${report.lastUpdated ? new Date(report.lastUpdated).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Reported by:</span>
                        <span class="info-value">${getCurrentAdminName()}</span>
                    </div>
                `;
            }
            
            // Populate violation statistics
            const statsGrid = detailsModal.querySelector('.stats-grid');
            if (statsGrid) {
                let statsHtml = '';
                
                // Dynamically add cards for each violation type
                if (reportViolationTypes && reportViolationTypes.length > 0) {
                    reportViolationTypes.forEach(type => {
                        const count = getReportTypeCount(report, type.id);
                        // Only show if count > 0 or it's one of the main types the user expects
                        const isMainType = ['uniform', 'footwear', 'shoe', 'id'].some(kw => type.name.toLowerCase().includes(kw));
                        
                        if (count > 0 || isMainType) {
                            statsHtml += `
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class='bx ${getTypeIcon(type.name, true)}'></i>
                                    </div>
                                    <div class="stat-content">
                                        <span class="stat-title">${type.name}</span>
                                        <span class="stat-value">${count}/5</span>
                                    </div>
                                </div>
                            `;
                        }
                    });
                } else {
                    // Fallback to basic types if global list is unavailable
                    statsHtml += `
                        <div class="stat-card">
                            <div class="stat-icon"><i class='bx bxs-t-shirt'></i></div>
                            <div class="stat-content">
                                <span class="stat-title">Uniform Violations</span>
                                <span class="stat-value">${report.uniformCount}/5</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class='bx bxs-walk'></i></div>
                            <div class="stat-content">
                                <span class="stat-title">Footwear Violations</span>
                                <span class="stat-value">${report.footwearCount}/5</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class='bx bxs-id-card'></i></div>
                            <div class="stat-content">
                                <span class="stat-title">No ID Violations</span>
                                <span class="stat-value">${report.noIdCount}/5</span>
                            </div>
                        </div>
                    `;
                }

                // Add Total Violations card at the end
                statsHtml += `
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class='bx bxs-bar-chart-alt-2'></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-title">Total Violations</span>
                            <span class="stat-value">${report.totalViolations}</span>
                        </div>
                    </div>
                `;
                
                statsGrid.innerHTML = statsHtml;
            }
            
            // Populate timeline
            const timelineEl = detailsModal.querySelector('.timeline');
            if (timelineEl) {
                if (report.history && report.history.length > 0) {
                    timelineEl.innerHTML = report.history.map(item => {
                        const dateTime = item.time ? `${item.date} • ${item.time}` : item.date;
                        const locationHtml = item.location ? `<span class="timeline-location">Reported at ${item.location}</span>` : '';
                        const reportedByHtml = item.reportedBy ? `<span class="timeline-location">By: ${item.reportedBy}</span>` : '';
                        return `
                        <div class="timeline-item">
                            <div class="timeline-date">${dateTime}</div>
                            <div class="timeline-content">
                                <span class="timeline-title">${item.title}</span>
                                ${locationHtml}
                                ${reportedByHtml}
                            </div>
                        </div>
                    `;
                    }).join('');
                } else {
                    timelineEl.innerHTML = '<div class="timeline-item"><div class="timeline-content">No violation history available</div></div>';
                }
            }
            
            // Populate recommendations
            const recommendationsEl = detailsModal.querySelector('.recommendations-list');
            if (recommendationsEl) {
                if (report.recommendations && report.recommendations.length > 0) {
                    recommendationsEl.innerHTML = report.recommendations.map(rec => `
                        <div class="recommendation-item">
                            <i class='bx bx-check-circle'></i>
                            <span>${rec}</span>
                        </div>
                    `).join('');
                } else {
                    recommendationsEl.innerHTML = '<div class="recommendation-item"><span>No recommendations at this time</span></div>';
                }
            }
            
            detailsModal.dataset.viewingId = reportId;
            detailsModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeGenerateModal() {
            console.log('Closing generate modal');
            generateModal.classList.remove('active');
            document.body.style.overflow = 'auto';
            
            // Reset form if exists
            const form = document.getElementById('ReportsGenerateForm');
            if (form) form.reset();
        }

        function closeDetailsModal() {
            if (!detailsModal) return;
            detailsModal.classList.remove('active');
            document.body.style.overflow = 'auto';
            delete detailsModal.dataset.viewingId;
        }

        // ========== EVENT HANDLERS ==========
        
        function handleTableClick(e) {
            const viewBtn = e.target.closest('.Reports-action-btn.view');
            const exportBtn = e.target.closest('.Reports-action-btn.export');

            if (viewBtn) {
                const id = parseInt(viewBtn.dataset.id);
                openDetailsModal(id);
            }

            if (exportBtn) {
                const id = parseInt(exportBtn.dataset.id);
                const report = reports.find(r => r.id === id);
                if (report) {
                    downloadSingleReport(report);
                }
            }
        }

        function handleStudentSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            renderReports();
        }

        // ========== EVENT LISTENERS ==========
        
        // 1. OPEN GENERATE MODAL
        if (btnGenerateReport) {
            btnGenerateReport.addEventListener('click', openGenerateModal);
            console.log('✅ Added click event to btnGenerateReports');
        }

        // 2. OPEN GENERATE MODAL (FIRST REPORT)
        if (btnGenerateFirst) {
            btnGenerateFirst.addEventListener('click', openGenerateModal);
            console.log('✅ Added click event to btnGenerateFirstReport');
        }

        // 3. EXPORT REPORTS
        if (btnExportReports) {
            btnExportReports.addEventListener('click', function() {
                if (exportModal) {
                    exportModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            });
        }

        if (closeExportBtn) {
            closeExportBtn.addEventListener('click', () => {
                if (exportModal) {
                    exportModal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });
        }

        if (exportModalOverlay) {
            exportModalOverlay.addEventListener('click', () => {
                if (exportModal) {
                    exportModal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });
        }

        // Export format buttons
        if (exportPDFBtn) {
            exportPDFBtn.addEventListener('click', async () => {
                if (reports.length === 0) {
                    alert('No reports to export.');
                    return;
                }
                await downloadPDF(reports, 'Reports_Export');
                if (exportModal) exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }

        if (exportExcelBtn) {
            exportExcelBtn.addEventListener('click', () => {
                if (reports.length === 0) {
                    alert('No reports to export.');
                    return;
                }
                downloadAllReports();
                if (exportModal) exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }

        if (exportWordBtn) {
            exportWordBtn.addEventListener('click', async () => {
                if (reports.length === 0) {
                    alert('No reports to export.');
                    return;
                }
                await downloadDOCX(reports, 'Reports_Export');
                if (exportModal) exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        }

        // 4. REFRESH REPORTS
        if (btnRefreshReports) {
            btnRefreshReports.addEventListener('click', function() {
                loadReports(true);
            });
        }

        // 6. CLOSE MODAL BUTTONS
        if (closeGenerateBtn) {
            closeGenerateBtn.addEventListener('click', closeGenerateModal);
            console.log('✅ Added click event to closeReportsModal');
        }

        if (cancelGenerateBtn) {
            cancelGenerateBtn.addEventListener('click', closeGenerateModal);
            console.log('✅ Added click event to cancelReportsModal');
        }

        if (generateOverlay) {
            generateOverlay.addEventListener('click', closeGenerateModal);
            console.log('✅ Added click event to ReportsModalOverlay');
        }

        if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', closeDetailsModal);
        if (detailsOverlay) detailsOverlay.addEventListener('click', closeDetailsModal);

        // 7. ESCAPE KEY TO CLOSE MODAL
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (generateModal && generateModal.classList.contains('active')) {
                    closeGenerateModal();
                }
                if (detailsModal && detailsModal.classList.contains('active')) {
                    closeDetailsModal();
                }
            }
        });

        // 8. TABLE EVENT LISTENERS
        if (tableBody) {
            tableBody.addEventListener('click', handleTableClick);
        }

        if (paginationContainer) {
            paginationContainer.addEventListener('click', handlePaginationClick);
        }

        // 9. SEARCH FUNCTIONALITY
        if (searchInput) {
            searchInput.addEventListener('input', () => { currentPage = 1; handleStudentSearch(); });
        }

        // 10. FILTER FUNCTIONALITY
        if (deptFilter) {
            deptFilter.addEventListener('change', function() {
                currentPage = 1;
                loadReports(true);
            });
        }

        if (sectionFilter) {
            sectionFilter.addEventListener('change', function() {
                currentPage = 1;
                loadReports(true);
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', function() {
                currentPage = 1;
                loadReports(true);
            });
        }

        if (violationTypeFilter) {
            violationTypeFilter.addEventListener('change', function() {
                currentPage = 1;
                loadReports(true);
            });
        }

        if (timeFilter) {
            timeFilter.addEventListener('change', function() {
                if (this.value === 'custom') {
                    if (dateRangeGroup) dateRangeGroup.style.display = 'block';
                } else {
                    if (dateRangeGroup) dateRangeGroup.style.display = 'none';
                    currentPage = 1;
                    loadReports(true);
                }
            });
        }

        if (sortByFilter) {
            sortByFilter.addEventListener('change', () => { currentPage = 1; renderReports(); });
        }

        // 11. APPLY FILTERS BUTTON
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', function() {
                currentPage = 1;
                loadReports(true);
            });
        }

        // 12. CLEAR FILTERS BUTTON
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                if (deptFilter) deptFilter.value = 'all';
                if (sectionFilter) sectionFilter.value = 'all';
                if (statusFilter) statusFilter.value = 'all';
                if (violationTypeFilter) violationTypeFilter.value = 'all';
                if (timeFilter) timeFilter.value = 'today';
                if (sortByFilter) sortByFilter.value = 'total_desc';
                if (dateRangeGroup) dateRangeGroup.style.display = 'none';
                if (searchInput) searchInput.value = '';
                currentPage = 1;
                loadReports(true);
            });
        }

        // 13. RESET FILTERS BUTTON
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function() {
                if (deptFilter) deptFilter.value = 'all';
                if (sectionFilter) sectionFilter.value = 'all';
                if (statusFilter) statusFilter.value = 'all';
                if (violationTypeFilter) violationTypeFilter.value = 'all';
                currentPage = 1;
                loadReports(true);
            });
        }

        // 14. FORM SUBMISSION
        if (generateForm) {
            generateForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const reportName = document.getElementById('reportName').value.trim();
                const reportType = document.getElementById('reportType').value;
                const reportFormat = document.getElementById('reportFormat').value;
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                
                if (!reportName || !reportType || !reportFormat || !startDate || !endDate) {
                    alert('Please fill in all required fields.');
                    return;
                }

                try {
                    // Generate reports from violations
                    const params = new URLSearchParams();
                    params.append('generate', 'true');
                    if (startDate) params.append('startDate', startDate);
                    if (endDate) params.append('endDate', endDate);
                    
                    if (reportFormat) params.append('reportFormat', reportFormat);
                    if (reportType) params.append('reportType', reportType);

                    // Add filters
                    const departments = Array.from(document.querySelectorAll('input[name="departments"]:checked'))
                        .map(cb => cb.value).join(',');
                    if (departments) params.append('departments', departments);

                    const violationTypes = Array.from(document.querySelectorAll('input[name="violationTypes"]:checked'))
                        .map(cb => cb.value).join(',');
                    if (violationTypes) params.append('violationTypes', violationTypes);

                    const response = await fetch(API_BASE + 'reports.php?' + params.toString());
                    const data = await response.json();
                    
                if (data.status === 'success') {
                    // Use message from server
                    showSuccess(data.message || `Reports generated successfully!`);
                    
                    // Check for client-side download (PDF)
                    if (reportFormat === 'pdf') {
                        if (data.reports && data.reports.length > 0) {
                            // Get the checkbox directly from the document to be absolutely sure
                            const includeChartsCheckbox = document.getElementById('includeCharts');
                            const isChecked = includeChartsCheckbox ? includeChartsCheckbox.checked : false;
                            
                            console.log('📊 Exporting report...', {
                                format: reportFormat,
                                totalReports: data.reports.length,
                                isChecked: isChecked
                            });
                            
                            await downloadPDF(data.reports, reportName || 'Violation Report', isChecked);
                        } else {
                            showError('No reports found to export for the selected criteria.');
                        }
                    }
                    
                    closeGenerateModal();
                    // Reload reports
                    loadReports(true);
                } else {
                    showError('Error generating reports: ' + (data.message || 'Unknown error'));
                }
                } catch (error) {
                    console.error('Error generating reports:', error);
                    alert('Error generating reports: ' + error.message);
                }
            });
        }

        // 15. VIEW OPTIONS (Table/Grid/Card view)
        if (viewButtons.length > 0) {
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    viewMode = this.dataset.view;
                    localStorage.setItem('reportsViewMode', viewMode);
                    viewButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    renderReports();
                });
            });
            // Set initial active state from saved preference
            viewButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === viewMode);
            });
        }

        // Delegate clicks on grid and list views to the existing table click handler
        const reportsGridView = document.getElementById('reportsGridView');
        const reportsListView = document.getElementById('reportsListView');
        if (reportsGridView) reportsGridView.addEventListener('click', handleTableClick);
        if (reportsListView) reportsListView.addEventListener('click', handleTableClick);

        // 16. DETAILS MODAL ACTION BUTTONS
        const detailExportBtn = document.getElementById('detailExportBtn');
        const detailPrintBtn = document.getElementById('detailPrintBtn');
        const detailEditBtn = document.getElementById('detailEditBtn');
        const detailShareBtn = document.getElementById('detailShareBtn');
        const detailDownloadBtn = document.getElementById('detailDownloadBtn');

        if (detailExportBtn) {
            detailExportBtn.addEventListener('click', function() {
                const reportId = detailsModal.dataset.viewingId;
                const report = reports.find(r => r.id === parseInt(reportId));
                if (report) {
                    downloadSingleReport(report);
                }
            });
        }

        if (detailPrintBtn) {
            detailPrintBtn.addEventListener('click', function() {
                const reportId = detailsModal.dataset.viewingId;
                const report = reports.find(r => r.id === parseInt(reportId));
                if (report) {
                    printReport(report);
                }
            });
        }

        if (detailEditBtn) {
            detailEditBtn.addEventListener('click', function() {
                alert('Edit report feature would open here');
            });
        }

        if (detailShareBtn) {
            detailShareBtn.addEventListener('click', function() {
                alert('Share report feature would open here');
            });
        }

        if (detailDownloadBtn) {
            detailDownloadBtn.addEventListener('click', function() {
                const reportId = detailsModal.dataset.viewingId;
                const report = reports.find(r => r.id === parseInt(reportId));
                if (report) {
                    downloadSingleReport(report);
                }
            });
        }

        // ========== UTILITY FUNCTIONS ==========
        
        function csvEscape(value) {
            if (value === null || value === undefined) return '';
            const str = String(value);
            if (/[",\n]/.test(str)) {
                return '"' + str.replace(/"/g, '""') + '"';
            }
            return str;
        }

        async function loadImage(url) {
            return new Promise((resolve) => {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    resolve(canvas.toDataURL('image/png'));
                };
                img.onerror = () => {
                    console.warn('Could not load image:', url);
                    resolve(null);
                }
                img.src = url;
            });
        }

        async function createChartImage(type, data, options) {
            return new Promise((resolve) => {
                const wrapper = document.createElement('div');
                wrapper.style.position = 'absolute';
                wrapper.style.left = '-9999px';
                wrapper.style.top = '-9999px';
                wrapper.style.width = '600px';
                wrapper.style.height = '400px';
                document.body.appendChild(wrapper);

                const canvas = document.createElement('canvas');
                wrapper.appendChild(canvas);

                const chart = new Chart(canvas, {
                    type: type,
                    data: data,
                    options: {
                        ...options,
                        animation: false,
                        responsive: false,
                        maintainAspectRatio: false,
                        devicePixelRatio: 2
                    }
                });

                // Small delay to ensure render
                setTimeout(() => {
                    const imgData = canvas.toDataURL('image/png');
                    chart.destroy();
                    document.body.removeChild(wrapper);
                    resolve(imgData);
                }, 100);
            });
        }

        async function printReport(report) {
            if (!report) return;
            
            showNotification('Preparing PDF report...', 'info');

            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                const now = new Date();

                // --- Header Section ---
                const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
                const headerData = await loadImage(headerPath);

                if (headerData) {
                    doc.addImage(headerData, 'PNG', 38, 5, 140, 25);
                }

                // Report Title
                doc.setFontSize(14);
                doc.setTextColor(41, 128, 185);
                doc.setFont("helvetica", "bold");
                doc.text("STUDENT VIOLATION ANALYSIS", 105, 40, { align: 'center' });

                doc.setFontSize(9);
                doc.setTextColor(100, 100, 100);
                doc.setFont("helvetica", "normal");
                doc.text(`Report ID: ${report.reportId}`, 105, 46, { align: 'center' });
                doc.text(`Generated on: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`, 105, 51, { align: 'center' });

                // Divider
                doc.setDrawColor(220, 220, 220);
                doc.line(14, 56, 196, 56);

                // Student Info
                doc.setFontSize(11);
                doc.setTextColor(44, 62, 80);
                doc.setFont("helvetica", "bold");
                doc.text("Student Profile", 14, 65);

                doc.setFontSize(10);
                doc.setFont("helvetica", "normal");
                let y = 72;
                doc.text(`Name: ${report.studentName}`, 14, y);
                doc.text(`Student ID: ${report.studentId}`, 110, y);
                y += 7;
                doc.text(`Department: ${report.department}`, 14, y);
                doc.text(`Section: ${report.section}`, 110, y);
                y += 7;
                doc.text(`Year Level: ${report.yearlevel || 'N/A'}`, 14, y);
                doc.text(`Contact: ${report.studentContact}`, 110, y);

                // Stats Table
                y += 10;
                doc.setFont("helvetica", "bold");
                doc.text("Violation Summary", 14, y);
                y += 5;

                const statsTable = [
                    ["Violation Type", "Count"],
                    ["Improper Uniform", report.uniformCount.toString()],
                    ["Improper Footwear", report.footwearCount.toString()],
                    ["No ID", report.noIdCount.toString()],
                    ["Total Violations", report.totalViolations.toString()]
                ];

                doc.autoTable({
                    body: statsTable.slice(1),
                    head: [statsTable[0]],
                    startY: y,
                    theme: 'striped',
                    headStyles: { fillColor: [41, 128, 185], textColor: 255 },
                    styles: { fontSize: 10 }
                });

                y = doc.lastAutoTable.finalY + 15;

                // History
                if (report.history && report.history.length > 0) {
                    doc.setFont("helvetica", "bold");
                    doc.text("Violation History", 14, y);
                    y += 5;

                    const historyRows = report.history.map(h => [h.date, h.title, h.desc]);
                    doc.autoTable({
                        head: [["Date", "Violation", "Description"]],
                        body: historyRows,
                        startY: y,
                        theme: 'grid',
                        headStyles: { fillColor: [245, 245, 245], textColor: 44 },
                        styles: { fontSize: 9 }
                    });
                    y = doc.lastAutoTable.finalY + 15;
                }

                // Footer
                if (y > 250) {
                    doc.addPage();
                    y = 20;
                }
                
                doc.setFontSize(10);
                doc.setFont("helvetica", "bold");
                doc.text("Recommendations:", 14, y);
                y += 7;
                doc.setFont("helvetica", "normal");
                const recommendations = report.recommendations || ["No recommendations at this time."];
                recommendations.forEach(rec => {
                    doc.text(`• ${rec}`, 14, y);
                    y += 6;
                });

                // Signatures
                y += 20;
                doc.line(130, y, 190, y);
                doc.setFontSize(9);
                doc.text("OSAS Representative", 160, y + 5, { align: 'center' });

                doc.save(`AnalysisReport_${report.studentId}.pdf`);
                showNotification('Analysis report generated as PDF!', 'success');
            } catch (error) {
                console.error('Error generating analysis PDF:', error);
                showNotification('Failed to generate PDF report.', 'error');
            }
        }

        async function downloadPDF(reportsData, filenamePrefix, isChecked = false) {
            if (!window.jspdf) {
                showError('PDF library not loaded. Please refresh the page.');
                return;
            }
            
            console.log('📄 PDF Request:', { isChecked });
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const now = new Date();
            
            // --- Header Design ---
            const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
            const headerData = await loadImage(headerPath);

            if (headerData) {
                // Add header image (Smaller: 180mm width, 25mm height, centered)
                // Center it: (210 - 180) / 2 = 15mm from left. 
                // But let's use the 140mm version for consistency with others
                // (210 - 140) / 2 = 35mm. Shifting slightly right (38mm) like student export.
                doc.addImage(headerData, 'PNG', 38, 5, 140, 25);
            } else {
                // Fallback Layout
                doc.setFontSize(22);
                doc.setTextColor(44, 62, 80);
                doc.setFont("helvetica", "bold");
                doc.text("E-OSAS SYSTEM", 14, 20);
                
                doc.setFontSize(10);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(127, 140, 141);
                doc.text("Office of Student Affairs and Services", 14, 28);
            }

            // Report Title & Date (Positioned below the header image)
            doc.setFontSize(12); // Reduced from 14
            doc.setTextColor(41, 128, 185); 
            doc.setFont("helvetica", "bold");
            doc.text("VIOLATION ANALYSIS REPORT", 105, 38, { align: 'center' });

            doc.setFontSize(8); // Reduced from 9
            doc.setTextColor(100, 100, 100);
            doc.setFont("helvetica", "normal");
            doc.text(`Generated on: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`, 105, 43, { align: 'center' });
            doc.text(`Reported by: ${getCurrentAdminName()}`, 105, 47, { align: 'center' });

            // Divider Line
            doc.setDrawColor(220, 220, 220);
            doc.setLineWidth(0.5);
            doc.line(14, 52, 196, 52);
            
            // Summary Stats
            doc.setFontSize(10);
            doc.setTextColor(60, 60, 60);
            doc.text(`Total Records: ${reportsData.length}`, 14, 62);
            
            let startY = 67;

            // Table
            const tableColumn = ["Student ID", "Name", "Dept", "Section", "Period", "Uniform", "Footwear", "No ID", "Total"];
            const tableRows = [];

            reportsData.forEach(report => {
                const reportData = [
                    report.studentId,
                    report.studentName,
                    report.department,
                    report.section,
                    getReportPeriodLabel(report),
                    report.uniformCount + '/5',
                    report.footwearCount + '/5',
                    report.noIdCount + '/5',
                    report.totalViolations
                ];
                tableRows.push(reportData);
            });

            doc.autoTable({
                head: [tableColumn],
                body: tableRows,
                startY: startY,
                theme: 'grid',
                styles: { fontSize: 8, cellPadding: 3, valign: 'middle' },
                headStyles: { 
                    fillColor: [245, 245, 245], 
                    textColor: [44, 62, 80], 
                    fontStyle: 'bold',
                    lineWidth: 0.1,
                    lineColor: [200, 200, 200]
                },
                columnStyles: {
                    0: { cellWidth: 25 }, // Student ID
                    1: { cellWidth: 'auto' }, // Name
                    7: { halign: 'center' }, // Total
                    8: { halign: 'center' }  // Status
                },
                alternateRowStyles: { fillColor: [255, 255, 255] },
                margin: { top: 60 }
            });

            // Charts Section (Bottom)
            if (isChecked === true) {
                try {
                    console.log('📈 CHARTS ARE ENABLED - Rendering...');
                    let finalY = doc.lastAutoTable.finalY + 20;
                    
                    // Check if we need a new page for charts
                    if (finalY + 100 > 280) {
                        doc.addPage();
                        finalY = 20;
                    }

                    // Section Title
                    doc.setFontSize(16);
                    doc.setTextColor(41, 128, 185);
                    doc.text("Visual Analysis", 105, finalY, { align: 'center' });
                    doc.setDrawColor(200, 200, 200);
                    doc.line(70, finalY + 2, 140, finalY + 2); // Underline
                    
                    finalY += 15;

                    // Department Data
                    const deptCounts = {};
                    reportsData.forEach(r => {
                        const dept = r.department || 'Unknown';
                        deptCounts[dept] = (deptCounts[dept] || 0) + 1;
                    });

                    const deptChartData = {
                        labels: Object.keys(deptCounts),
                        datasets: [{
                            data: Object.values(deptCounts),
                            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#76A346']
                        }]
                    };
                    
                    // Type Data
                    const typeCounts = { uniform: 0, footwear: 0, noId: 0 };
                    reportsData.forEach(r => {
                        typeCounts.uniform += parseInt(r.uniformCount) || 0;
                        typeCounts.footwear += parseInt(r.footwearCount) || 0;
                        typeCounts.noId += parseInt(r.noIdCount) || 0;
                    });

                    const typeChartData = {
                        labels: ['Uniform', 'Footwear', 'No ID'],
                        datasets: [{
                            label: 'Violations',
                            data: [typeCounts.uniform, typeCounts.footwear, typeCounts.noId],
                            backgroundColor: ['#4e73df', '#f6c23e', '#e74a3b']
                        }]
                    };

                    // Generate Images
                    const deptImg = await createChartImage('doughnut', deptChartData, { 
                        plugins: { 
                            legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } },
                            title: { display: true, text: 'By Department' }
                        } 
                    });
                    const typeImg = await createChartImage('bar', typeChartData, { 
                        plugins: { 
                            legend: { display: false },
                            title: { display: true, text: 'By Violation Type' }
                        } 
                    });

                    // Add to PDF (Side by Side)
                    // Page width is 210mm. Margins 14mm. Usable ~180mm.
                    // Each chart 85mm wide.
                    
                    doc.addImage(deptImg, 'PNG', 14, finalY, 85, 60);
                    doc.addImage(typeImg, 'PNG', 110, finalY, 85, 60);
                    
                    // Add captions/titles manually if needed, but chart title plugin handles it cleaner inside the image
                    
                } catch (e) {
                    console.error('Error adding charts to PDF:', e);
                }
            }

            doc.save(`${filenamePrefix}_${now.toISOString().slice(0, 10)}.pdf`);
        }

        async function downloadDOCX(reportsData, filenamePrefix, isChecked = false) {
            if (!window.docx) {
                showError('DOCX library not loaded. Please refresh the page.');
                return;
            }
            
            console.log('📝 DOCX Request:', { isChecked });
            const { Document, Packer, Paragraph, Table, TableCell, TableRow, WidthType, HeadingLevel, TextRun, AlignmentType, ImageRun, VerticalAlign, BorderStyle } = window.docx;
            const now = new Date();
            
            const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
            const headerData = await loadImage(headerPath);

            // Children for the document
            const children = [];

            // Add Header Image if available
            if (headerData) {
                children.push(new Paragraph({
                    children: [
                        new ImageRun({
                            data: headerData,
                            transformation: {
                                width: 400,
                                height: 80,
                            },
                        }),
                    ],
                    alignment: AlignmentType.CENTER,
                }));
            }

            children.push(
                new Paragraph({
                    text: "VIOLATION ANALYSIS REPORT",
                    heading: HeadingLevel.HEADING_1,
                    alignment: AlignmentType.CENTER,
                    spacing: { before: 200, after: 200 }
                }),
                new Paragraph({
                    children: [
                        new TextRun({
                            text: "Office of Student Affairs and Services",
                            italics: true,
                            color: "666666",
                            size: 18,
                        })
                    ],
                    alignment: AlignmentType.CENTER,
                    spacing: { after: 100 }
                }),
                new Paragraph({
                    children: [
                        new TextRun({
                            text: `Generated: ${now.toLocaleString()}`,
                            italics: true,
                            color: "666666",
                            size: 18,
                        })
                    ],
                    alignment: AlignmentType.CENTER,
                    spacing: { after: 100 }
                }),
                new Paragraph({
                    children: [
                        new TextRun({
                            text: `Reported by: ${getCurrentAdminName()}`,
                            italics: true,
                            color: "666666",
                            size: 18,
                        })
                    ],
                    alignment: AlignmentType.CENTER,
                    spacing: { after: 100 }
                }),
                new Paragraph({
                    children: [
                        new TextRun({
                            text: `Total Students: ${reportsData.length}`,
                            bold: true,
                            size: 20,
                        })
                    ],
                    spacing: { after: 400 }
                })
            );

            // Add Charts if requested
            if (isChecked === true) {
                try {
                    console.log('📈 CHARTS ARE ENABLED (DOCX) - Rendering...');
                    // Create charts data (similar to PDF logic)
                    const deptCounts = {};
                    reportsData.forEach(r => {
                        const dept = r.department || 'Unknown';
                        deptCounts[dept] = (deptCounts[dept] || 0) + 1;
                    });
                    const deptChartData = {
                        labels: Object.keys(deptCounts),
                        datasets: [{
                            data: Object.values(deptCounts),
                            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#E7E9ED', '#76A346']
                        }]
                    };

                    const typeCounts = { uniform: 0, footwear: 0, noId: 0 };
                    reportsData.forEach(r => {
                        typeCounts.uniform += parseInt(r.uniformCount) || 0;
                        typeCounts.footwear += parseInt(r.footwearCount) || 0;
                        typeCounts.noId += parseInt(r.noIdCount) || 0;
                    });
                    const typeChartData = {
                        labels: ['Uniform', 'Footwear', 'No ID'],
                        datasets: [{
                            label: 'Violations',
                            data: [typeCounts.uniform, typeCounts.footwear, typeCounts.noId],
                            backgroundColor: ['#4e73df', '#f6c23e', '#e74a3b']
                        }]
                    };

                    const deptImg = await createChartImage('doughnut', deptChartData, { 
                        plugins: { title: { display: true, text: 'Department Distribution' } } 
                    });
                    const typeImg = await createChartImage('bar', typeChartData, { 
                        plugins: { title: { display: true, text: 'Violation Types' } } 
                    });

                    // Helper to convert base64 to Uint8Array for docx
                    const b64toBlob = (b64Data) => {
                        const byteString = atob(b64Data.split(',')[1]);
                        const ab = new ArrayBuffer(byteString.length);
                        const ia = new Uint8Array(ab);
                        for (let i = 0; i < byteString.length; i++) {
                            ia[i] = byteString.charCodeAt(i);
                        }
                        return ab;
                    };

                    children.push(new Paragraph({
                        text: "Visual Analysis",
                        heading: HeadingLevel.HEADING_2,
                        spacing: { before: 400, after: 200 }
                    }));

                    children.push(new Paragraph({
                        children: [
                            new ImageRun({
                                data: b64toBlob(deptImg),
                                transformation: { width: 300, height: 200 }
                            }),
                            new TextRun({ text: "    " }), // spacing
                            new ImageRun({
                                data: b64toBlob(typeImg),
                                transformation: { width: 300, height: 200 }
                            })
                        ],
                        alignment: "center"
                    }));
                } catch (e) {
                    console.error('Error adding charts to DOCX:', e);
                }
            }
            
            // Table Header with modern styling
            const tableHeader = new TableRow({
                children: [
                    "Student ID", "Name", "Dept", "Section", "Period", "Uniform", "Footwear", "No ID", "Total"
                ].map(text => new TableCell({
                    children: [new Paragraph({ 
                        children: [new TextRun({ text, bold: true, size: 18, color: "FFFFFF" })],
                        alignment: AlignmentType.CENTER
                    })],
                    shading: { fill: "2C3E50", val: "clear", color: "auto" },
                    verticalAlign: VerticalAlign.CENTER,
                    width: { size: 100 / 9, type: WidthType.PERCENTAGE },
                    margins: { top: 80, bottom: 80, left: 80, right: 80 }
                })),
                tableHeader: true,
                height: { value: 600, rule: "atLeast" }
            });
            
            // Table Rows with modern styling
            const tableRows = reportsData.map((report, index) => {
                const isEven = index % 2 === 0;
                const rowColor = isEven ? "FFFFFF" : "F8F9FA";
                
                return new TableRow({
                    children: [
                        report.studentId,
                        report.studentName,
                        report.department,
                        report.section,
                        getReportPeriodLabel(report),
                        report.uniformCount + '/5',
                        report.footwearCount + '/5',
                        report.noIdCount + '/5',
                        String(report.totalViolations)
                    ].map(text => new TableCell({
                        children: [new Paragraph({ 
                            children: [new TextRun({ text: text || "", size: 18 })],
                            alignment: AlignmentType.LEFT
                        })],
                        shading: { fill: rowColor, val: "clear", color: "auto" },
                        verticalAlign: VerticalAlign.CENTER,
                        width: { size: 100 / 9, type: WidthType.PERCENTAGE },
                        margins: { top: 60, bottom: 60, left: 80, right: 80 }
                    })),
                    height: { value: 400, rule: "atLeast" }
                });
            });

            children.push(new Paragraph({
                text: "Summary Data",
                heading: HeadingLevel.HEADING_2,
                spacing: { before: 400, after: 200 }
            }));

            children.push(new Table({
                rows: [tableHeader, ...tableRows],
                width: { size: 100, type: WidthType.PERCENTAGE },
                borders: {
                    top: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                    bottom: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                    left: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                    right: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                    insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: "E0E0E0" },
                    insideVertical: { style: BorderStyle.SINGLE, size: 1, color: "E0E0E0" }
                }
            }));

            const doc = new Document({
                sections: [{
                    properties: {
                        page: {
                            margin: {
                                top: 720,
                                right: 720,
                                bottom: 720,
                                left: 720
                            }
                        }
                    },
                    children: children
                }]
            });

            Packer.toBlob(doc).then(blob => {
                if (typeof saveAs === 'function') {
                    saveAs(blob, `${filenamePrefix}_${now.toISOString().slice(0, 10)}.docx`);
                } else {
                    console.error('FileSaver.js not loaded');
                    showError('Error: FileSaver.js not loaded');
                }
            });
        }

        async function downloadSingleReport(report) {
            const reportsToExport = [report];
            const now = new Date();
            const filename = 'report_' + (report.reportId || report.id || 'student') + '.xls';
            await generateExcel(reportsToExport, filename);
        }

        async function downloadAllReports() {
            if (!Array.isArray(reports) || reports.length === 0) return;
            const now = new Date();
            const filename = 'reports_export_' + now.toISOString().slice(0, 10) + '.xls';
            await generateExcel(reports, filename);
        }

        async function generateExcel(reportsData, fileName) {
            try {
                const now = new Date();
                const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
                const headerData = await loadImage(headerPath);

                let html = `
                    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                    <head>
                        <meta charset="UTF-8">
                        <style>
                            .title { font-size: 14pt; font-weight: bold; color: #2980b9; text-align: center; }
                            .subtitle { font-size: 10pt; color: #7f8c8d; text-align: center; }
                            .stats { font-size: 9pt; color: #333; text-align: center; }
                            .data-table th { background-color: #f2f2f2; font-weight: bold; border: 0.5pt solid #000; text-align: center; }
                            .data-table td { border: 0.5pt solid #000; padding: 5px; }
                        </style>
                    </head>
                    <body>
                        <table width="1060" style="width: 1060px; border-collapse: collapse;">
                            ${headerData ? `
                            <tr height="100" style="height: 100px;">
                                <td colspan="9" width="1060" align="center" valign="middle" style="width: 1060px; text-align: center; vertical-align: middle;">
                                    <center>
                                        <div align="center" style="text-align: center;">
                                            <p align="center" style="text-align: center; margin: 0; padding: 0;">
                                                <img src="${headerData}" width="400" height="80" border="0" style="display: inline-block;">
                                            </p>
                                        </div>
                                    </center>
                                </td>
                            </tr>` : ''}
                            <tr><td colspan="10" class="title" align="center" style="text-align: center;">VIOLATION ANALYSIS REPORT</td></tr>
                            <tr><td colspan="10" class="subtitle" align="center" style="text-align: center;">Office of Student Affairs and Services</td></tr>
                            <tr><td colspan="10" class="stats" align="center" style="text-align: center;">Generated on: ${now.toLocaleString()}</td></tr>
                            <tr><td colspan="10" class="stats" align="center" style="text-align: center;">Reported by: ${getCurrentAdminName()}</td></tr>
                            <tr><td colspan="10" class="stats" align="center" style="text-align: center;">Total Records: ${reportsData.length}</td></tr>
                            <tr><td colspan="10" style="height: 20px;"></td></tr>
                            <tr class="data-table">
                                <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Report ID</th>
                                <th width="120" style="width: 120px; background-color: #e0e0e0; border: 0.5pt solid #000;">Student ID</th>
                                <th width="200" style="width: 200px; background-color: #e0e0e0; border: 0.5pt solid #000;">Student Name</th>
                                <th width="200" style="width: 200px; background-color: #e0e0e0; border: 0.5pt solid #000;">Department</th>
                                <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Section</th>
                                <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Period</th>
                                <th width="80" style="width: 80px; background-color: #e0e0e0; border: 0.5pt solid #000;">Uniform</th>
                                <th width="80" style="width: 80px; background-color: #e0e0e0; border: 0.5pt solid #000;">Footwear</th>
                                <th width="80" style="width: 80px; background-color: #e0e0e0; border: 0.5pt solid #000;">No ID</th>
                                <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Total</th>
                            </tr>
                `;

                reportsData.forEach(report => {
                    html += `
                        <tr>
                            <td>${report.reportId || ''}</td>
                            <td>${report.studentId || ''}</td>
                            <td>${report.studentName || ''}</td>
                            <td>${report.department || ''}</td>
                            <td>${report.section || ''}</td>
                            <td style="mso-number-format:'\@';">${getReportPeriodLabel(report)}</td>
                            <td align="center" style="mso-number-format:'\@';">${(report.uniformCount || 0) + '/5'}</td>
                            <td align="center" style="mso-number-format:'\@';">${(report.footwearCount || 0) + '/5'}</td>
                            <td align="center" style="mso-number-format:'\@';">${(report.noIdCount || 0) + '/5'}</td>
                            <td align="center"><b>${report.totalViolations || 0}</b></td>
                        </tr>
                    `;
                });

                html += `
                        </table>
                    </body>
                    </html>
                `;

                const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
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
            } catch (error) {
                console.error('Excel export error:', error);
                showError('Failed to generate Excel document.');
            }
        }

        // ========== LOAD DEPARTMENTS AND SECTIONS ==========
        
        async function loadDepartments() {
            try {
                const response = await fetch(API_BASE + 'departments.php');
                const data = await response.json();
                
                if (data.status === 'success') {
                    const depts = data.data;
                    
                    // 1. Populate the filter dropdown (deptFilter)
                    if (deptFilter) {
                        const allOption = deptFilter.querySelector('option[value="all"]');
                        deptFilter.innerHTML = '';
                        if (allOption) {
                            deptFilter.appendChild(allOption);
                        }
                        
                        depts.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.department_code || dept.code || dept.id;
                            option.textContent = dept.department_name || dept.name || dept.department_code || dept.code;
                            deptFilter.appendChild(option);
                        });
                        console.log(`✅ Loaded ${depts.length} departments to dropdown`);
                    }

                    // 2. Populate the generation modal checkboxes (generateDeptCheckboxes)
                    const generateDeptCheckboxes = document.getElementById('generateDeptCheckboxes');
                    if (generateDeptCheckboxes) {
                        generateDeptCheckboxes.innerHTML = '';
                        depts.forEach(dept => {
                            const deptCode = dept.department_code || dept.code || dept.id;
                            const deptName = dept.department_name || dept.name || dept.department_code || dept.code;
                            
                            const label = document.createElement('label');
                            label.className = 'checkbox-label';
                            label.innerHTML = `
                                <input type="checkbox" name="departments" value="${deptCode}" checked>
                                <span>${deptName}</span>
                            `;
                            generateDeptCheckboxes.appendChild(label);
                        });
                        console.log(`✅ Loaded ${depts.length} departments to checkboxes`);
                    }
                }
            } catch (error) {
                console.error('❌ Error loading departments:', error);
            }
        }
        
        async function loadViolationTypes() {
            try {
                const response = await fetch(API_BASE + 'violations.php?action=types');
                const data = await response.json();
                
                const generateViolationTypeCheckboxes = document.getElementById('generateViolationTypeCheckboxes');
                if (data.status === 'success' && data.data) {
                    const types = data.data.filter(type => {
                        const name = (type.type_name || type.name || '').toLowerCase();
                        return !name.includes('misconduct');
                    });

                    setReportViolationTypes(types.map(type => ({
                        id: type.id,
                        name: type.type_name || type.name
                    })));

                    if (generateViolationTypeCheckboxes) {
                        generateViolationTypeCheckboxes.innerHTML = '';
                        types.forEach(type => {
                            const label = document.createElement('label');
                            label.className = 'checkbox-label';
                            label.innerHTML = `
                                <input type="checkbox" name="violationTypes" value="${type.id}" checked>
                                <span>${type.type_name || type.name}</span>
                            `;
                            generateViolationTypeCheckboxes.appendChild(label);
                        });
                    }
                    console.log(`✅ Loaded ${types.length} violation types from API`);
                } else {
                    throw new Error('Invalid API response');
                }
            } catch (error) {
                console.warn('⚠️ Error loading violation types, using defaults:', error);
                const fallbackTypes = [
                    { id: 'uniform', name: 'Improper Uniform' },
                    { id: 'footwear', name: 'Improper Footwear' },
                    { id: 'no_id', name: 'No ID' }
                ];
                setReportViolationTypes(fallbackTypes);
                const container = document.getElementById('generateViolationTypeCheckboxes');
                if (container) {
                    container.innerHTML = fallbackTypes.map(type => `
                        <label class="checkbox-label">
                            <input type="checkbox" name="violationTypes" value="${type.id}" checked>
                            <span>${type.name}</span>
                        </label>
                    `).join('');
                }
            }
        }

        async function loadSections() {
            try {
                const response = await fetch(API_BASE + 'sections.php');
                const data = await response.json();
                
                if (data.status === 'success' && sectionFilter) {
                    // Clear existing options except "All"
                    const allOption = sectionFilter.querySelector('option[value="all"]');
                    sectionFilter.innerHTML = '';
                    if (allOption) {
                        sectionFilter.appendChild(allOption);
                    }
                    
                    // Add sections
                    data.data.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.section_code || section.code || section.id;
                        option.textContent = section.section_code || section.code || section.section_name || section.name;
                        sectionFilter.appendChild(option);
                    });
                    console.log(`✅ Loaded ${data.data.length} sections`);
                }
            } catch (error) {
                console.error('❌ Error loading sections:', error);
            }
        }
        
        // ========== INITIAL LOAD ==========
        // Ensure we are on the reports page before proceeding
        if (!document.getElementById('Reports-page')) {
            console.log('Not on Reports page, skipping initialization');
            return;
        }

        initCharts();
        loadDepartments();
        loadSections();
        loadViolationTypes().then(() => loadReports(true));
        console.log('✅ Reports module initialized successfully!');
        
    } catch (error) {
        console.error('❌ Error initializing reports module:', error);
    }
}

// Make function globally available
window.initReportsModule = initReportsModule;

// Auto-initialize if loaded directly (for testing)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReportsModule);
} else {
    // Give a small delay for dynamic loading
    setTimeout(initReportsModule, 500);
}
