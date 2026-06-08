/**
 * Dashboard Data Loader
 * Connects dashboard to database APIs for real-time data
 */

// API Base Path Detection
function getAPIBasePath() {
    const p = window.location.pathname.split('/').filter(Boolean);
    const d = ['app','api','includes','assets','public'];
    return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/api/';
}

const API_BASE = getAPIBasePath();
console.log('🌐 Using API Base:', API_BASE);

// Dashboard Data Manager
class DashboardData {
    constructor() {
        this.stats = {
            students: 0,
            departments: 0,
            sections: 0,
            violations: 0,
            violators: 0,
            penalties: 0
        };
        this.violations = [];
        this.students = [];
        this.departments = [];
        this.sections = [];
        this.announcements = [];
        this.dashcontents = [];
        this.topViolators = [];
        // Store chart instances to prevent flickering
        this.charts = {
            violationTypes: null,
            departmentViolations: null,
            monthlyTrends: null
        };
        // Flag to prevent multiple chart updates
        this.chartsUpdating = false;
    }

    /**
     * Load all dashboard data from APIs
     */
    async loadAllData() {
        try {
            console.log('📊 Loading dashboard data from database...');
            console.log('🔗 API Base:', API_BASE);
            
            // Load dashboard stats (includes violations data)
            const [dashboardStatsRes, studentsRes, departmentsRes, sectionsRes, announcementsRes, dashcontentsRes, violationsRes] = await Promise.allSettled([
                fetch(API_BASE + 'dashboard_stats.php'),
                fetch(API_BASE + 'students.php'),
                fetch(API_BASE + 'departments.php'),
                fetch(API_BASE + 'sections.php'),
                fetch(API_BASE + 'announcements.php?action=active&limit=5'),
                fetch(API_BASE + 'dashcontents.php?action=active&audience=admin'),
                fetch(API_BASE + 'violations.php')  // Load all violations for charts
            ]);
            
            // Parse dashboard stats (violations, violators, recent violations, top violators)
            if (dashboardStatsRes.status === 'fulfilled' && dashboardStatsRes.value) {
                try {
                    if (dashboardStatsRes.value.ok) {
                        const statsData = await dashboardStatsRes.value.json();
                        console.log('📊 Dashboard stats response:', statsData);
                        if (statsData.data && statsData.data.stats) {
                            this.stats.violations = statsData.data.stats.violations || 0;
                            this.stats.violators = statsData.data.stats.violators || 0;
                            this.stats.students = statsData.data.stats.students || 0;
                            this.stats.departments = statsData.data.stats.departments || 0;
                            this.stats.sections = statsData.data.stats.sections || 0;
                            this.stats.penalties = statsData.data.stats.penalties || 0;
                            
                            // Process recent violations - map database fields to expected format
                            const recentViolations = statsData.data.recentViolations || [];
                            this.violations = recentViolations.map(v => {
                                const violationType = v.violation_type || 'Other';
                                const violationDate = v.violation_date || v.created_at || '';
                                
                                return {
                                    ...v,
                                    violationType: violationType,
                                    violation_type: violationType,
                                    studentId: v.student_id || '',
                                    student_id: v.student_id || '',
                                    studentName: `${v.first_name || ''} ${v.last_name || ''}`.trim() || 'Unknown',
                                    firstName: v.first_name || '',
                                    lastName: v.last_name || '',
                                    studentDept: v.department || '',
                                    student_dept: v.department || '',
                                    violationDate: violationDate,
                                    violation_date: violationDate,
                                    dateReported: violationDate,
                                    studentImage: v.avatar || '',
                                    avatar: v.avatar || '',
                                    status: v.status || 'pending'
                                };
                            });
                            
                            this.topViolators = statsData.data.topViolators || [];
                            console.log('✅ Dashboard stats loaded:', this.stats);
                            console.log('✅ Violations loaded:', this.violations.length);
                            console.log('✅ Top violators loaded:', this.topViolators.length);
                        } else {
                            console.warn('⚠️ Dashboard stats data structure unexpected:', statsData);
                        }
                    } else {
                        const errorText = await dashboardStatsRes.value.text();
                        console.warn('⚠️ Dashboard stats API error:', dashboardStatsRes.value.status, dashboardStatsRes.value.statusText);
                        console.warn('Error response:', errorText);
                    }
                } catch (e) {
                    console.error('❌ Error parsing dashboard stats:', e);
                    console.error('Error stack:', e.stack);
                }
            } else {
                console.error('❌ Dashboard stats fetch failed:', dashboardStatsRes.reason);
            }

            // Parse students (only if stats didn't already set it)
            if (this.stats.students === 0 && studentsRes.status === 'fulfilled' && studentsRes.value) {
                try {
                    if (studentsRes.value.ok) {
                        const data = await studentsRes.value.json();
                        console.log('📊 Students response:', data);
                        this.students = data.data || data.students || [];
                        this.stats.students = this.students.length;
                        console.log('✅ Students loaded:', this.stats.students);
                    } else {
                        console.warn('⚠️ Students API error:', studentsRes.value.status, studentsRes.value.statusText);
                    }
                } catch (e) {
                    console.error('❌ Error parsing students:', e);
                }
            } else if (studentsRes.status === 'rejected') {
                console.error('❌ Students fetch failed:', studentsRes.reason);
            }

            // Parse departments (only if stats didn't already set it)
            if (this.stats.departments === 0 && departmentsRes.status === 'fulfilled' && departmentsRes.value) {
                try {
                    if (departmentsRes.value.ok) {
                        const data = await departmentsRes.value.json();
                        console.log('📊 Departments response:', data);
                        this.departments = data.data || data.departments || [];
                        this.stats.departments = this.departments.length;
                        console.log('✅ Departments loaded:', this.stats.departments);
                    } else {
                        console.warn('⚠️ Departments API error:', departmentsRes.value.status, departmentsRes.value.statusText);
                    }
                } catch (e) {
                    console.error('❌ Error parsing departments:', e);
                }
            } else if (departmentsRes.status === 'rejected') {
                console.error('❌ Departments fetch failed:', departmentsRes.reason);
            }

            // Parse sections (only if stats didn't already set it)
            if (this.stats.sections === 0 && sectionsRes.status === 'fulfilled' && sectionsRes.value) {
                try {
                    if (sectionsRes.value.ok) {
                        const data = await sectionsRes.value.json();
                        console.log('📊 Sections response:', data);
                        this.sections = data.data || data.sections || [];
                        this.stats.sections = this.sections.length;
                        console.log('✅ Sections loaded:', this.stats.sections);
                    } else {
                        console.warn('⚠️ Sections API error:', sectionsRes.value.status, sectionsRes.value.statusText);
                    }
                } catch (e) {
                    console.error('❌ Error parsing sections:', e);
                }
            } else if (sectionsRes.status === 'rejected') {
                console.error('❌ Sections fetch failed:', sectionsRes.reason);
            }

            // Parse announcements
            if (announcementsRes.status === 'fulfilled' && announcementsRes.value) {
                try {
                    if (announcementsRes.value.ok) {
                        const data = await announcementsRes.value.json();
                        console.log('📊 Announcements response:', data);
                        const annPayload = data.data;
                        this.announcements = Array.isArray(annPayload)
                            ? annPayload
                            : (annPayload && Array.isArray(annPayload.announcements) ? annPayload.announcements : (data.announcements || []));
                        console.log('✅ Announcements loaded:', this.announcements.length);
                    } else {
                        console.warn('⚠️ Announcements API error:', announcementsRes.value.status, announcementsRes.value.statusText);
                    }
                } catch (e) {
                    console.error('❌ Error parsing announcements:', e);
                }
            } else {
                console.error('❌ Announcements fetch failed:', announcementsRes.reason);
            }

            // Parse dashcontents
            if (dashcontentsRes.status === 'fulfilled' && dashcontentsRes.value) {
                try {
                    if (dashcontentsRes.value.ok) {
                        const data = await dashcontentsRes.value.json();
                        console.log('📊 Dashcontents response:', data);
                        this.dashcontents = data.data || [];
                        console.log('✅ Dashcontents loaded:', this.dashcontents.length);
                        console.log('📋 Dashcontents data:', this.dashcontents);
                    } else {
                        const errorText = await dashcontentsRes.value.text().catch(() => 'Unknown error');
                        console.warn('⚠️ Dashcontents API error:', dashcontentsRes.value.status, dashcontentsRes.value.statusText);
                        console.warn('Error response:', errorText);
                        this.dashcontents = [];
                    }
                } catch (e) {
                    console.error('❌ Error parsing dashcontents:', e);
                    console.error('Error stack:', e.stack);
                    this.dashcontents = [];
                }
            } else {
                console.error('❌ Dashcontents fetch failed:', dashcontentsRes.reason);
                this.dashcontents = [];
            }

            // Parse violations for charts (load ALL violations, not just recent ones)
            if (violationsRes.status === 'fulfilled' && violationsRes.value) {
                try {
                    if (violationsRes.value.ok) {
                        const data = await violationsRes.value.json();
                        console.log('📊 Violations API response:', data);
                        // Use violations from API response (for charts)
                        const apiViolations = data.violations || data.data || [];
                        if (apiViolations.length > 0) {
                            // Merge with existing violations, avoiding duplicates
                            const existingIds = new Set(this.violations.map(v => v.id || v.case_id || v.caseId));
                            apiViolations.forEach(v => {
                                const id = v.id || v.case_id || v.caseId;
                                if (!existingIds.has(id)) {
                                    // Map API response format to expected format
                                    this.violations.push({
                                        ...v,
                                        violationType: v.violationType || v.violation_type || v.violationTypeLabel || 'Other',
                                        violation_type: v.violation_type || v.violationType || 'Other',
                                        violationDate: v.violationDate || v.violation_date || v.dateReported || v.date || '',
                                        violation_date: v.violation_date || v.violationDate || '',
                                        studentDept: v.studentDept || v.student_dept || v.department || '',
                                        student_dept: v.student_dept || v.studentDept || '',
                                        dateReported: v.dateReported || v.violationDate || v.violation_date || v.date || ''
                                    });
                                }
                            });
                            console.log('✅ Violations loaded for charts:', apiViolations.length);
                            console.log('📊 Total violations for charts:', this.violations.length);
                        } else {
                            console.warn('⚠️ No violations returned from API');
                        }
                    } else {
                        const errorText = await violationsRes.value.text().catch(() => 'Unknown error');
                        console.warn('⚠️ Violations API error:', violationsRes.value.status, violationsRes.value.statusText);
                        console.warn('Error response:', errorText);
                    }
                } catch (e) {
                    console.error('❌ Error parsing violations:', e);
                    console.error('Error stack:', e.stack);
                }
            } else {
                console.error('❌ Violations fetch failed:', violationsRes.reason);
            }

            console.log('✅ Dashboard data loaded:', this.stats);
            console.log('📊 Final stats:', {
                students: this.stats.students,
                departments: this.stats.departments,
                sections: this.stats.sections,
                violations: this.stats.violations,
                violators: this.stats.violators,
                violationsArrayLength: this.violations.length
            });
            
            // Mark data as loaded
            if (typeof window !== 'undefined') {
                window.dashboardDataLoaded = true;
            }
            
            // Update UI with real data (with retry logic)
            setTimeout(() => {
                console.log('🔄 Updating UI elements...');
                this.updateStats();
                this.updateCharts();
                this.updateRecentViolators();
                this.updateTopViolators();
                this.updateDashcontents(); // Update tips first
                this.updateAnnouncements(); // Then update announcements (which will show guidelines if no announcements)
            }, 300);
            
            // Also retry after a longer delay in case elements aren't ready
            setTimeout(() => {
                const statsBoxes = document.querySelectorAll('.box-info li');
                if (statsBoxes.length === 0) {
                    console.warn('⚠️ Stats boxes not found, retrying updateStats...');
                    this.updateStats();
                } else {
                    // Verify stats were updated
                    const firstStat = statsBoxes[0].querySelector('h3');
                    if (firstStat && firstStat.textContent === '10') {
                        console.warn('⚠️ Stats still showing placeholder values, forcing update...');
                        this.updateStats();
                    }
                }
            }, 1500);

        } catch (error) {
            console.error('❌ Error loading dashboard data:', error);
            console.error('Error stack:', error.stack);
            // Still try to update with whatever data we have
            setTimeout(() => {
                this.updateStats();
            }, 100);
        }
    }

    /**
     * Update statistics boxes
     */
    updateStats() {
        console.log('📊 Updating dashboard stats:', this.stats);
        
        // Try updating by ID first (more robust)
        const violatorsCount = document.getElementById('violators-count');
        const studentsCount = document.getElementById('students-count');
        const departmentsCount = document.getElementById('departments-count');
        const penaltiesCount = document.getElementById('penalties-count');
        
        if (violatorsCount) {
            if (window.animateCountUp) animateCountUp(violatorsCount, this.stats.violators || 0);
            else violatorsCount.textContent = this.stats.violators || 0;
        }
        
        if (studentsCount) {
            // Static override: 546 total enrolled students (until registrar fixes duplicate IDs)
            const studentTotal = 546;
            if (window.animateCountUp) animateCountUp(studentsCount, studentTotal);
            else studentsCount.textContent = studentTotal;
        }
        
        if (departmentsCount) {
            if (window.animateCountUp) animateCountUp(departmentsCount, this.stats.departments || 0);
            else departmentsCount.textContent = this.stats.departments || 0;
        }
        
        if (penaltiesCount) {
            if (window.animateCountUp) animateCountUp(penaltiesCount, this.stats.penalties || 0);
            else penaltiesCount.textContent = this.stats.penalties || 0;
        }
        
        // If IDs not found, fallback to selector based (legacy support)
        if (!violatorsCount && !studentsCount) {
            const statsBoxes = document.querySelectorAll('.box-info li');
            console.log(`Found ${statsBoxes.length} stat boxes (fallback mode)`);
            
            if (statsBoxes.length >= 4) {
                // Violators
                const violatorsBox = statsBoxes[0];
                if (violatorsBox) {
                    const h3 = violatorsBox.querySelector('h3');
                    if (h3) {
                        h3.textContent = this.stats.violators || 0;
                    }
                }
    
                // Students
                const studentsBox = statsBoxes[1];
                if (studentsBox) {
                    const h3 = studentsBox.querySelector('h3');
                    if (h3) {
                        h3.textContent = this.stats.students || 0;
                    }
                }
    
                // Departments
                const departmentsBox = statsBoxes[2];
                if (departmentsBox) {
                    const h3 = departmentsBox.querySelector('h3');
                    if (h3) {
                        h3.textContent = this.stats.departments || 0;
                    }
                }
    
                // Penalties
                const penaltiesBox = statsBoxes[3];
                if (penaltiesBox) {
                    const h3 = penaltiesBox.querySelector('h3');
                    if (h3) {
                        h3.textContent = this.stats.penalties || 0;
                    }
                }
            } else {
                 console.warn(`⚠️ Expected 4 stat boxes, found ${statsBoxes.length}. Retrying in 500ms...`);
                 // Only retry if we're actually on the dashboard/dashcontent page
                 const onDashboard = document.getElementById('violators-count') !== null ||
                                     document.querySelector('.box-info') !== null ||
                                     document.getElementById('dashcontent-page') !== null;
                 if (onDashboard) {
                     setTimeout(() => { this.updateStats(); }, 500);
                 }
            }
        }
    }

    /**
     * Update charts with real data
     */
    updateCharts() {
        // Prevent multiple simultaneous updates
        if (this.chartsUpdating) {
            console.log('⏸️ Charts already updating, skipping...');
            return;
        }

        console.log('📊 Updating charts with', this.violations.length, 'violations');
        
        // Check if canvas elements exist
        const violationTypesCanvas = document.getElementById('violationTypesChart');
        const departmentCanvas = document.getElementById('departmentViolationsChart');
        const monthlyCanvas = document.getElementById('monthlyTrendsChart');
        
        if (!violationTypesCanvas && !departmentCanvas && !monthlyCanvas) {
            // Only retry if on dashboard page
            const onDashboard = document.getElementById('violators-count') !== null ||
                                document.querySelector('.box-info') !== null;
            if (onDashboard) {
                console.warn('⚠️ Chart canvases not found, retrying in 500ms...');
                setTimeout(() => this.updateCharts(), 500);
            }
            return;
        }
        
        this.chartsUpdating = true;
        
        // Wait a bit to ensure canvas elements are ready
        setTimeout(() => {
            try {
                // Process violation data for charts
                const violationTypes = this.processViolationTypes();
                const departmentViolations = this.processDepartmentViolations();
                const monthlyTrends = this.processMonthlyTrends();

                // Update Violation Types Chart
                if (violationTypesCanvas) {
                    this.updateViolationTypesChart(violationTypes);
                }

                // Update Department Violations Chart
                if (departmentCanvas) {
                    this.updateDepartmentViolationsChart(departmentViolations);
                }

                // Update Monthly Trends Chart
                if (monthlyCanvas) {
                    this.updateMonthlyTrendsChart(monthlyTrends);
                }
                
                console.log('✅ Charts updated successfully');
            } catch (error) {
                console.error('❌ Error updating charts:', error);
            } finally {
                this.chartsUpdating = false;
            }
        }, 300);
    }

    /**
     * Process violation types from data
     */
    processViolationTypes() {
        const types = {};
        
        if (!this.violations || this.violations.length === 0) {
            console.warn('⚠️ No violations data to process for chart');
            return {
                labels: ['No Data'],
                data: [1]
            };
        }
        
        this.violations.forEach(violation => {
            const label = violation.violationTypeLabel || violation.violation_type_name || violation.violationType || 'Other';
            types[label] = (types[label] || 0) + 1;
        });

        // Get top 5 types
        const sorted = Object.entries(types)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 5);

        if (sorted.length === 0) {
            return {
                labels: ['No Data'],
                data: [1]
            };
        }

        return {
            labels: sorted.map(([label]) => label),
            data: sorted.map(([, count]) => count)
        };
    }

    /**
     * Process department violations from data
     */
    processDepartmentViolations() {
        const deptViolations = {};
        
        if (!this.violations || this.violations.length === 0) {
            console.warn('⚠️ No violations data to process for department chart');
            return {
                labels: ['No Data'],
                data: [0]
            };
        }
        
        this.violations.forEach(violation => {
            const dept = violation.studentDept || violation.student_dept || violation.department || 'Unknown';
            deptViolations[dept] = (deptViolations[dept] || 0) + 1;
        });

        // Get top 6 departments
        const sorted = Object.entries(deptViolations)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 6);

        if (sorted.length === 0) {
            return {
                labels: ['No Data'],
                data: [0]
            };
        }

        return {
            labels: sorted.map(([label]) => label),
            data: sorted.map(([, count]) => count)
        };
    }

    /**
     * Process monthly trends from data
     */
    processMonthlyTrends() {
        const monthlyData = {
            'Jan': 0, 'Feb': 0, 'Mar': 0, 'Apr': 0, 'May': 0, 'Jun': 0,
            'Jul': 0, 'Aug': 0, 'Sep': 0, 'Oct': 0, 'Nov': 0, 'Dec': 0
        };

        this.violations.forEach(violation => {
            const dateStr = violation.violationDate || violation.violation_date || violation.dateReported || violation.created_at || violation.date || '';
            if (dateStr) {
                try {
                    // Handle different date formats
                    let date;
                    if (dateStr.includes('T')) {
                        date = new Date(dateStr);
                    } else if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        // YYYY-MM-DD format
                        date = new Date(dateStr + 'T00:00:00');
                    } else {
                        date = new Date(dateStr);
                    }
                    
                    if (!isNaN(date.getTime())) {
                        const month = date.toLocaleString('en-US', { month: 'short' });
                        if (monthlyData.hasOwnProperty(month)) {
                            monthlyData[month]++;
                        }
                    }
                } catch (e) {
                    // Invalid date, skip
                    console.warn('Invalid date format:', dateStr, e);
                }
            }
        });

        return {
            labels: Object.keys(monthlyData),
            data: Object.values(monthlyData)
        };
    }

    /**
     * Update Violation Types Pie Chart
     */
    updateViolationTypesChart(data) {
        const ctx = document.getElementById('violationTypesChart');
        if (!ctx || typeof Chart === 'undefined') {
            console.warn('⚠️ Chart canvas or Chart.js not available');
            return;
        }

        // Destroy existing chart instance if it exists
        if (this.charts.violationTypes) {
            this.charts.violationTypes.destroy();
            this.charts.violationTypes = null;
        }

        const isDark = document.body.classList.contains('dark');
        const textColor = isDark ? '#ffffff' : '#333333';

        // Only create chart if we have real data (not just "No Data" placeholder)
        if (data.labels.length === 0 || 
            data.data.length === 0 || 
            (data.labels.length === 1 && data.labels[0] === 'No Data') ||
            data.data.every(d => d === 0)) {
            console.warn('⚠️ No violation type data to display');
            // Show placeholder message instead of chart
            const chartContainer = ctx.closest('.chart-container');
            if (chartContainer) {
                chartContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">No violation data available</div>';
            }
            return;
        }

        this.charts.violationTypes = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels.length > 0 ? data.labels : ['No Data'],
                datasets: [{
                    data: data.data.length > 0 ? data.data : [1],
                    backgroundColor: [
                        '#FFD700',
                        '#FFCE26',
                        '#FD7238',
                        '#1bb44eff',
                        '#6c757d'
                    ],
                    borderWidth: 2,
                    borderColor: isDark ? '#2d3748' : '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: { size: 12 },
                            color: textColor
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Update Department Violations Bar Chart
     */
    updateDepartmentViolationsChart(data) {
        const ctx = document.getElementById('departmentViolationsChart');
        if (!ctx || typeof Chart === 'undefined') {
            console.warn('⚠️ Department chart canvas or Chart.js not available');
            return;
        }

        // Destroy existing chart instance if it exists
        if (this.charts.departmentViolations) {
            this.charts.departmentViolations.destroy();
            this.charts.departmentViolations = null;
        }

        const isDark = document.body.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const textColor = isDark ? '#ffffff' : '#333333';
        const bgColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';

        // Only create chart if we have real data (not just "No Data" placeholder)
        if (data.labels.length === 0 || 
            data.data.length === 0 || 
            (data.labels.length === 1 && data.labels[0] === 'No Data') ||
            data.data.every(d => d === 0)) {
            console.warn('⚠️ No department violation data to display');
            // Show placeholder message instead of chart
            const chartContainer = ctx.closest('.chart-container');
            if (chartContainer) {
                chartContainer.innerHTML = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">No violation data available</div>';
            }
            return;
        }

        this.charts.departmentViolations = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels.length > 0 ? data.labels : ['No Data'],
                datasets: [{
                    label: 'Violations',
                    data: data.data.length > 0 ? data.data : [0],
                    backgroundColor: [
                        '#FFD700',
                        '#FFCE26',
                        '#FD7238',
                        '#3fbe18ff',
                        '#DB504A',
                        '#6c757d'
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor },
                        background: { color: bgColor }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }

    /**
     * Update Monthly Trends Line Chart
     */
    updateMonthlyTrendsChart(data) {
        const ctx = document.getElementById('monthlyTrendsChart');
        if (!ctx || typeof Chart === 'undefined') {
            console.warn('⚠️ Monthly trends chart canvas or Chart.js not available');
            return;
        }

        // Destroy existing chart instance if it exists
        if (this.charts.monthlyTrends) {
            this.charts.monthlyTrends.destroy();
            this.charts.monthlyTrends = null;
        }

        const isDark = document.body.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const textColor = isDark ? '#ffffff' : '#333333';
        const bgColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';

        this.charts.monthlyTrends = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Violations',
                    data: data.data,
                    borderColor: '#FFD700',
                    backgroundColor: isDark ? 'rgba(255, 215, 0, 0.2)' : 'rgba(255, 215, 0, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointBackgroundColor: '#FFD700',
                    pointBorderColor: isDark ? '#2d3748' : '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { color: textColor },
                        background: { color: bgColor }
                    },
                    x: {
                        grid: { color: gridColor },
                        ticks: { color: textColor }
                    }
                }
            }
        });
    }

    /**
     * Update Recent Violators Table
     */
    updateRecentViolators() {
        const tbody = document.getElementById('recent-violators-body');
        if (!tbody) return;

        // Get recent violations (last 5)
        const recentViolations = this.violations
            .sort((a, b) => {
                const dateA = new Date(a.violationDate || a.violation_date || a.dateReported || 0);
                const dateB = new Date(b.violationDate || b.violation_date || b.dateReported || 0);
                return dateB - dateA;
            })
            .slice(0, 5);

        tbody.innerHTML = '';

        if (recentViolations.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No violations found</td></tr>';
            return;
        }

        recentViolations.forEach(violation => {
            const studentName = violation.studentName || 
                              `${violation.firstName || ''} ${violation.lastName || ''}`.trim() || 
                              'Unknown Student';
            const date = violation.violationDate || violation.violation_date || violation.dateReported || 'N/A';
            const violationType = violation.violation_type || violation.violationType || violation.notes || violation.remarks || 'N/A';
            const status = (violation.status || 'pending').toLowerCase();
            const avatar = violation.studentImage || violation.avatar || '../app/assets/img/default.png';

            const statusClass = status === 'permitted' ? 'permitted' :
                               (status === 'completed' || status === 'resolved') ? 'completed' :
                               status === 'warning' ? 'process' : 'pending';
            const statusText = status === 'completed' || status === 'resolved' ? 'Resolved' :
                              status === 'warning' ? 'Warning' :
                              status === 'permitted' ? 'Permitted' :
                              status === 'disciplinary' ? 'Disciplinary Action' : 'Pending';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td data-label="Student">
                    <img src="${avatar}" alt="Student Image" onerror="this.src='../app/assets/img/default.png'">
                    <p>${this.escapeHtml(studentName)}</p>
                </td>
                <td data-label="Date">${this.formatDate(date)}</td>
                <td data-label="Violation">${this.escapeHtml(violationType)}</td>
                <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
            `;
            tbody.appendChild(row);
        });
    }

    /**
     * Update Top Violators List
     */
    updateTopViolators() {
        const violatorList = document.querySelector('.violators .violator-list');
        if (!violatorList) {
            console.warn('⚠️ Top violators list not found');
            return;
        }

        let topViolators = [];

        // Use topViolators from stats API if available
        if (this.topViolators && this.topViolators.length > 0) {
            topViolators = this.topViolators.map(v => ({
                name: `${v.first_name || ''} ${v.last_name || ''}`.trim() || 'Unknown Student',
                studentId: v.student_id || '',
                count: v.violation_count || 0
            }));
        } else {
            // Fallback: Count violations per student from violations array
            const studentViolations = {};
            this.violations.forEach(violation => {
                const studentId = violation.studentId || violation.student_id;
                if (studentId) {
                    if (!studentViolations[studentId]) {
                        studentViolations[studentId] = {
                            id: studentId,
                            name: violation.studentName || 
                                  `${violation.firstName || ''} ${violation.lastName || ''}`.trim() || 
                                  'Unknown Student',
                            count: 0
                        };
                    }
                    studentViolations[studentId].count++;
                }
            });

            // Sort by violation count and get top 5
            topViolators = Object.values(studentViolations)
                .sort((a, b) => b.count - a.count)
                .slice(0, 5);
        }

        violatorList.innerHTML = '';

        if (topViolators.length === 0) {
            violatorList.innerHTML = '<li style="padding: 20px; text-align: center;">No violators found</li>';
            return;
        }

        topViolators.forEach((violator, index) => {
            const count = violator.count || violator.violation_count || 0;
            const priority = count >= 10 ? 'high-priority' :
                            count >= 5 ? 'medium-priority' : 'low-priority';

            const li = document.createElement('li');
            li.className = priority;
            li.style.cursor = 'pointer';
            li.innerHTML = `
                <div class="violator-info">
                    <span class="rank">${index + 1}</span>
                    <span class="name">${this.escapeHtml(violator.name)}</span>
                    <span class="violations">${count} violation${count !== 1 ? 's' : ''}</span>
                </div>
                <i class='bx bx-chevron-right'></i>
            `;
            li.addEventListener('click', () => {
                if (typeof loadContent === 'function') {
                    loadContent('admin_page/Violations');
                    setTimeout(() => {
                        const searchInput = document.getElementById('searchViolation');
                        if (searchInput) {
                            // Use student ID if available, otherwise use full name
                            searchInput.value = violator.studentId || violator.name;
                            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }, 500);
                }
            });
            violatorList.appendChild(li);
        });
        
        console.log('✅ Top violators updated:', topViolators.length);

        // Setup click handlers for Top Violators buttons
        const refreshBtn = document.getElementById('refreshTopViolators');
        const filterBtn = document.getElementById('filterTopViolators');
        
        if (refreshBtn && !refreshBtn.dataset.bound) {
            refreshBtn.dataset.bound = 'true';
            refreshBtn.addEventListener('click', () => {
                this.loadAllData();
            });
        }
        
        if (filterBtn && !filterBtn.dataset.bound) {
            filterBtn.dataset.bound = 'true';
            filterBtn.addEventListener('click', () => {
                // Navigate to violations page
                if (typeof loadContent === 'function') {
                    loadContent('admin_page/Violations');
                }
            });
        }
    }

    /**
     * Update announcements on dashboard
     */
    updateAnnouncements() {
        const announcementsContent = document.getElementById('announcementsContent');
        if (!announcementsContent) {
            console.warn('⚠️ announcementsContent element not found');
            return;
        }

        console.log('🔄 Updating announcements...', {
            announcementsCount: this.announcements.length,
            dashcontentsCount: this.dashcontents.length
        });

        // Clear content first
        announcementsContent.innerHTML = '';

        // If no announcements, check for guidelines from dashcontents
        if (this.announcements.length === 0) {
            const guidelines = (this.dashcontents || []).filter(dc => 
                dc.content_type === 'guideline' && 
                (dc.target_audience === 'admin' || dc.target_audience === 'both') &&
                dc.status === 'active'
            );
            
            console.log('📋 Found guidelines:', guidelines.length);
            
            if (guidelines.length > 0) {
                // Show guidelines as announcements
                guidelines.forEach(guideline => {
                    const item = document.createElement('div');
                    item.className = 'announcement-item';
                    item.innerHTML = `
                        <div class="announcement-icon">
                            <i class='bx ${guideline.icon || 'bxs-info-circle'}'></i>
                        </div>
                        <div class="announcement-details">
                            <h4>${this.escapeHtml(guideline.title || '')}</h4>
                            <p>${this.escapeHtml(guideline.content || '')}</p>
                        </div>
                        <div class="announcement-actions">
                            <button class="btn-read-more" onclick="void(0)">Read More</button>
                        </div>
                    `;
                    announcementsContent.appendChild(item);
                });
                console.log('✅ Guidelines displayed as announcements');
                return;
            }
            
            // If no announcements and no guidelines, show empty state
            announcementsContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--dark-grey);">
                    <i class='bx bx-info-circle' style="font-size: 48px; margin-bottom: 10px;"></i>
                    <p>No announcements available</p>
                </div>
            `;
            console.log('⚠️ No announcements or guidelines to display');
            return;
        }

        // Display actual announcements - Sort newest first and limit to 5
        const latestAnnouncements = [...this.announcements]
            .sort((a, b) => new Date(b.created_at || b.createdAt) - new Date(a.created_at || a.createdAt))
            .slice(0, 5);
        
        latestAnnouncements.forEach(announcement => {
            const typeClass = announcement.type || 'info';
            const iconClass = typeClass === 'urgent' ? 'bxs-error-circle' : 
                             typeClass === 'warning' ? 'bxs-error' : 'bxs-info-circle';
            const timeAgo = this.getTimeAgo(announcement.created_at || announcement.createdAt);

            const item = document.createElement('div');
            item.className = `announcement-item ${typeClass}`;
            item.innerHTML = `
                <div class="announcement-icon">
                    <i class='bx ${iconClass}'></i>
                </div>
                <div class="announcement-details">
                    <h4>${this.escapeHtml(announcement.title || 'Untitled')}</h4>
                    <p>${this.escapeHtml(announcement.message || '')}</p>
                    <span class="announcement-time">${timeAgo}</span>
                </div>
                <div class="announcement-actions">
                    <button class="btn-read-more" onclick="openAnnouncement(${announcement.id})">Read More</button>
                </div>
            `;
            announcementsContent.appendChild(item);
        });
    }

    /**
     * Update dashcontents display
     */
    updateDashcontents() {
        // Update tips and guidelines in admin dashboard
        const tipsContainer = document.querySelector('.tips-container .tips-content');
        if (tipsContainer) {
            const tips = this.dashcontents.filter(dc => 
                dc.content_type === 'tip' && 
                (dc.target_audience === 'admin' || dc.target_audience === 'both') &&
                dc.status === 'active'
            );
            if (tips.length > 0) {
                tipsContainer.innerHTML = tips.map(tip => `
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class='bx ${tip.icon || 'bxs-info-circle'}'></i>
                        </div>
                        <div class="tip-details">
                            <h4>${this.escapeHtml(tip.title || '')}</h4>
                            <p>${this.escapeHtml(tip.content || '')}</p>
                        </div>
                    </div>
                `).join('');
            }
        }

        // Guidelines are now handled in updateAnnouncements() method
        // This method only handles tips display
    }

    /**
     * Get time ago string
     */
    getTimeAgo(dateString) {
        if (!dateString) return 'Unknown time';
        try {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
            
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric',
                year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
            });
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        if (!dateString || dateString === 'N/A') return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit' 
            });
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Get Current Admin Name from session
     */
    getCurrentAdminName() {
        const sessionStr = localStorage.getItem('userSession');
        if (!sessionStr) return 'Admin';
        try {
            const session = JSON.parse(sessionStr);
            return session.full_name || session.name || session.username || 'Admin';
        } catch (e) {
            return 'Admin';
        }
    }

    /**
     * Download Dashboard PDF Report
     */
    async downloadDashboardPDF() {
        console.log('📄 Preparing Dashboard PDF...');
        
        if (!window.jspdf) {
            if (typeof showNotification === 'function') {
                showNotification('PDF library not loaded. Please refresh the page.', 'error');
            } else {
                alert('PDF library not loaded. Please refresh the page.');
            }
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const now = new Date();

        // --- Helper: Load Image ---
        const loadImage = (url) => {
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
                img.onerror = () => resolve(null);
                img.src = url;
            });
        };

        // --- Header Section ---
        const headerPath = '/OSAS_WEB/app/assets/headers/header.png';
        const headerData = await loadImage(headerPath);

        if (headerData) {
            // Reduced width to 140mm (from 180mm) to fix stretching, height to 25mm
            // Shift slightly right (38mm) to align visually with centered title
            doc.addImage(headerData, 'PNG', 38, 5, 140, 25);
        } else {
            // Fallback header if image fails to load
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
        doc.setFontSize(12);
        doc.setTextColor(41, 128, 185); 
        doc.setFont("helvetica", "bold");
        doc.text("DASHBOARD SUMMARY REPORT", 105, 38, { align: 'center' });

        doc.setFontSize(8);
        doc.setTextColor(100, 100, 100);
        doc.setFont("helvetica", "normal");
        doc.text(`Generated on: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`, 105, 43, { align: 'center' });
        doc.text(`Exported by: ${this.getCurrentAdminName()}`, 105, 47, { align: 'center' });

        // Divider Line
        doc.setDrawColor(220, 220, 220);
        doc.setLineWidth(0.5);
        doc.line(14, 52, 196, 52);

        // --- Statistics Overview ---
        doc.setFontSize(12);
        doc.setTextColor(44, 62, 80);
        doc.setFont("helvetica", "bold");
        doc.text("Statistics Overview", 14, 62);

        const statsData = [
            ["Violators", String(this.stats.violators || 0)],
            ["Students", String(this.stats.students || 0)],
            ["Departments", String(this.stats.departments || 0)],
            ["Disciplinary", String(this.stats.penalties || 0)]
        ];

        doc.autoTable({
            body: statsData,
            startY: 67,
            theme: 'grid',
            styles: { fontSize: 10, cellPadding: 5 },
            columnStyles: {
                0: { fontStyle: 'bold', fillColor: [245, 245, 245], width: 40 },
                1: { halign: 'center' }
            },
            margin: { left: 14, right: 14 }
        });

        // --- Charts Section ---
        let currentY = doc.lastAutoTable.finalY + 15;
        doc.setFontSize(12);
        doc.text("Visual Analytics", 14, currentY);
        currentY += 5;

        const captureCanvas = (id) => {
            const canvas = document.getElementById(id);
            return canvas ? canvas.toDataURL('image/png') : null;
        };

        const typeChartData = captureCanvas('violationTypesChart');
        const deptChartData = captureCanvas('departmentViolationsChart');

        if (typeChartData) {
            doc.addImage(typeChartData, 'PNG', 14, currentY, 85, 50);
        }
        if (deptChartData) {
            doc.addImage(deptChartData, 'PNG', 110, currentY, 85, 50);
        }

        currentY += 60;

        // --- Recent Violators Table ---
        doc.setFontSize(12);
        doc.text("Recent Violation Activities", 14, currentY);
        
        const recentRows = this.violations.slice(0, 10).map(v => [
            v.studentName || `${v.firstName || ''} ${v.lastName || ''}`.trim() || 'Unknown',
            this.formatDate(v.violationDate || v.violation_date || v.dateReported || ''),
            v.violationType || v.violation_type || 'Other',
            v.status || 'Pending'
        ]);

        doc.autoTable({
            head: [["Student Name", "Date", "Violation", "Status"]],
            body: recentRows,
            startY: currentY + 5,
            theme: 'striped',
            styles: { fontSize: 8, cellPadding: 3 },
            headStyles: { fillColor: [44, 62, 80], textColor: [255, 255, 255] }
        });

        doc.save(`Dashboard_Summary_${now.toISOString().slice(0, 10)}.pdf`);
        console.log('✅ Dashboard PDF Generated');
    }
}

// Initialize dashboard data when page loads
let dashboardDataInstance = null;

function initDashboardData() {
    // Prevent multiple initialization attempts (but allow reset)
    if (window.initDashboardDataAttempted) {
        console.log('⏸️ Dashboard data initialization already attempted');
        // But still try to load if data isn't loaded yet
        if (dashboardDataInstance && (!window.dashboardDataLoaded || dashboardDataInstance.stats.students === 0)) {
            console.log('🔄 Data not loaded yet, forcing load...');
            dashboardDataInstance.loadAllData().catch(error => {
                console.error('❌ Error loading dashboard data:', error);
            });
        }
        return;
    }
    
    console.log('🔄 Initializing dashboard data...');
    window.initDashboardDataAttempted = true;
    
    if (!dashboardDataInstance) {
        dashboardDataInstance = new DashboardData();
        window.dashboardDataInstance = dashboardDataInstance; // Make it globally accessible
    }
    
    // Wait for dashboard content to load (check multiple times)
    let attempts = 0;
    const maxAttempts = 50; // 5 seconds max wait
    
    const checkInterval = setInterval(() => {
        attempts++;
        const dashcontent = document.querySelector('.box-info');
        const mainContent = document.querySelector('#main-content');
        
        if (dashcontent && mainContent && mainContent.innerHTML.trim() !== '') {
            clearInterval(checkInterval);
            console.log('✅ Dashboard content found, loading data...');
            dashboardDataInstance.loadAllData().catch(error => {
                console.error('❌ Error loading dashboard data:', error);
                window.initDashboardDataAttempted = false; // Allow retry on error
            });
        } else if (attempts >= maxAttempts) {
            clearInterval(checkInterval);
            console.warn('⚠️ Dashboard content not found after max attempts, trying anyway...');
            // Try to load anyway - might work if content is there but selector is different
            dashboardDataInstance.loadAllData().catch(error => {
                console.error('❌ Error loading dashboard data:', error);
                window.initDashboardDataAttempted = false; // Allow retry on error
            });
        }
    }, 100);
    
    // Also try after a longer delay as fallback
    setTimeout(() => {
        const dashcontent = document.querySelector('.box-info');
        if (dashboardDataInstance && dashcontent && !dashboardDataInstance.stats.students) {
            console.log('🔄 Fallback: Loading dashboard data...');
            dashboardDataInstance.loadAllData().catch(error => {
                console.error('❌ Error loading dashboard data:', error);
            });
        }
    }, 1000);
}

// Export for global use
window.DashboardData = DashboardData;
window.initDashboardData = initDashboardData;

// Watch for content changes (for dynamically loaded content)
let dataLoadDebounce = null;

function watchForDashboardContent() {
    const observer = new MutationObserver((mutations) => {
        const dashcontent = document.querySelector('.box-info');
        if (dashcontent && dashboardDataInstance) {
            // Check if this is a new content load (content changed significantly)
            const hasContent = dashcontent.querySelectorAll('li').length > 0;
            const isDataLoaded = window.dashboardDataLoaded || 
                                (dashboardDataInstance.stats.students > 0 || dashboardDataInstance.stats.violations > 0);
            
            if (hasContent && !isDataLoaded) {
                // Debounce to prevent multiple rapid loads
                if (dataLoadDebounce) {
                    clearTimeout(dataLoadDebounce);
                }
                
                dataLoadDebounce = setTimeout(() => {
                    // Double-check data isn't loaded
                    if (dashboardDataInstance.stats.students > 0 || dashboardDataInstance.stats.violations > 0) {
                        window.dashboardDataLoaded = true;
                        return;
                    }
                    
                    // Content was added, try to load data
                    console.log('🔍 Dashboard content detected, loading data...');
                    dashboardDataInstance.loadAllData().then(() => {
                        if (typeof window !== 'undefined') {
                            window.dashboardDataLoaded = true;
                        }
                    }).catch(error => {
                        console.error('❌ Error loading dashboard data:', error);
                    });
                }, 500);
            }
        }
    });

    // Observe the main content container
    const mainContent = document.querySelector('#main-content');
    if (mainContent) {
        observer.observe(mainContent, {
            childList: true,
            subtree: true
        });

        // Add event delegation for the dashboard export button
        mainContent.addEventListener('click', (e) => {
            const exportBtn = e.target.closest('#btnExportDashboardPDF');
            if (exportBtn) {
                e.preventDefault();
                console.log('🚀 Dashboard Export Button Clicked');
                if (window.dashboardDataInstance) {
                    window.dashboardDataInstance.downloadDashboardPDF();
                } else if (dashboardDataInstance) {
                    dashboardDataInstance.downloadDashboardPDF();
                } else {
                    console.error('❌ Dashboard data instance not found');
                }
            }
        });
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initDashboardData();
        watchForDashboardContent();
    });
} else {
    initDashboardData();
    watchForDashboardContent();
}

