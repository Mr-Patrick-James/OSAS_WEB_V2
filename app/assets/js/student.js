// student.js - Complete working version with API integration
function initStudentsModule() {
    console.log('🛠 Students module initializing...');
    
    try {
        // Elements
        const tableBody = document.getElementById('StudentsTableBody');
        const btnImportFirstStudents = document.getElementById('btnImportFirstStudents');
        const modal = document.getElementById('StudentsModal');
        const modalOverlay = document.getElementById('StudentsModalOverlay');
        const closeBtn = document.getElementById('closeStudentsModal');
        const cancelBtn = document.getElementById('cancelStudentsModal');
        const studentsForm = document.getElementById('StudentsForm');
        const searchInput = document.getElementById('searchStudent');
        const filterSelect = document.getElementById('StudentsFilterSelect');
        const deptFilterSelect = document.getElementById('StudentsDepartmentFilter');
        const sectionFilterSelect = document.getElementById('StudentsSectionFilter');
        const exportBtn = document.getElementById('btnExportStudents');
        const importBtn = document.getElementById('btnImportStudents');
        const exportModal = document.getElementById('ExportStudentsModal');
        const closeExportBtn = document.getElementById('closeExportModal');
        const exportModalOverlay = document.getElementById('ExportModalOverlay');
        const exportPDFBtn = document.getElementById('exportPDF');
        const exportExcelBtn = document.getElementById('exportExcel');
        const exportWordBtn = document.getElementById('exportWord');
        const studentDeptSelect = document.getElementById('studentDept');
        const studentSectionSelect = document.getElementById('studentSection');

        // Modern Alert Elements
        const modernAlertModal = document.getElementById('ModernAlertModal');
        const modernAlertIcon = document.getElementById('ModernAlertIcon');
        const modernAlertTitle = document.getElementById('ModernAlertTitle');
        const modernAlertMessage = document.getElementById('ModernAlertMessage');
        const modernAlertStats = document.getElementById('ModernAlertStats');
        const modernAlertActions = document.getElementById('ModernAlertActions');
        const modernAlertCancel = document.getElementById('ModernAlertCancel');
        const modernAlertConfirm = document.getElementById('ModernAlertConfirm');

        // Check for essential elements
        if (!tableBody) {
            console.error('❗ #StudentsTableBody not found');
            return;
        }

        if (!modal) {
            console.warn('⚠️ #StudentsModal not found');
        }

        // Use window-level cache so data persists when switching pages and back
        // This prevents re-fetching every time the students page is visited
        if (!window._studentsCache) window._studentsCache = { students: [], allStudents: [], stats: null, loaded: false };
        const _cache = window._studentsCache;

        // Students data (will be loaded from database)
        let students    = _cache.students;
        let allStudents = _cache.allStudents; // Store all students for stats
        let currentView = 'active'; // 'active' or 'archived'
        let viewMode    = localStorage.getItem('studentsViewMode') || 'list'; // 'table', 'grid', 'list'
        let editingStudentId = null;
        let currentPage      = 1;
        let itemsPerPage     = 10;
        let totalRecords     = 0;
        let totalPages       = 0;

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

        // ========== DYNAMIC API PATH DETECTION ==========
        function getAPIBasePath() {
            const p = window.location.pathname.split('/').filter(Boolean);
            const d = ['app','api','includes','assets','public'];
            return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/api/';
        }
        
        const API_BASE = getAPIBasePath();
        console.log('🔗 API Base Path:', API_BASE);
        
        const apiBase = API_BASE+'students.php';
        const departmentsApiBase = API_BASE + 'departments.php';
        const sectionsApiBase = API_BASE + 'sections.php';
        
        console.log('📡 Students API:', apiBase);
        console.log('📡 Departments API:', departmentsApiBase);
        console.log('📡 Sections API:', sectionsApiBase);

        // --- Validation Functions ---
        function validateStudentForm() {
            const requiredFields = [
                { id: 'studentId', name: 'Student ID' },
                { id: 'firstName', name: 'First Name' },
                { id: 'lastName', name: 'Last Name' },
                { id: 'studentEmail', name: 'Email' },
                { id: 'studentDept', name: 'Department' },
                { id: 'studentSection', name: 'Section' },
                { id: 'studentYearlevel', name: 'Year Level' }
            ];

            let isValid = true;
            let firstInvalidField = null;

            requiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                if (element) {
                    if (!element.value.trim()) {
                        element.classList.add('is-invalid');
                        isValid = false;
                        if (!firstInvalidField) firstInvalidField = element;
                        
                        // Add listener to remove invalid class on input
                        element.addEventListener('input', function removeInvalid() {
                            element.classList.remove('is-invalid');
                            element.removeEventListener('input', removeInvalid);
                        });
                    } else {
                        element.classList.remove('is-invalid');
                    }
                }
            });

            if (!isValid) {
                showError('Please fill out all required fields.');
                if (firstInvalidField) firstInvalidField.focus();
            }

            return isValid;
        }

        // --- Modern Alert Function ---
        function showModernAlert(options) {
            // Prefer global function from dashboard.js if available
            if (window.showModernAlert && typeof window.showModernAlert === 'function') {
                return window.showModernAlert(options);
            }

            // Fallback to local implementation
            return new Promise((resolve) => {
                if (!modernAlertModal) return resolve(false);

                // Reset
                modernAlertTitle.textContent = options.title;
                modernAlertMessage.textContent = options.message;
                modernAlertIcon.className = `Modern-modal-icon ${options.icon || 'warning'}`;
                
                // Set Icon
                let iconHtml = "<i class='bx bx-help-circle'></i>";
                if (options.icon === 'success') iconHtml = "<i class='bx bx-check-circle'></i>";
                if (options.icon === 'error') iconHtml = "<i class='bx bx-x-circle'></i>";
                if (options.icon === 'loading') iconHtml = "<i class='bx bx-loader-alt bx-spin'></i>";
                modernAlertIcon.innerHTML = iconHtml;

                // Stats
                if (options.stats) {
                    modernAlertStats.style.display = 'grid';
                    document.getElementById('statNew').textContent = options.stats.created || 0;
                    document.getElementById('statUpdated').textContent = options.stats.updated || 0;
                    document.getElementById('statSkipped').textContent = options.stats.skipped || 0;
                } else {
                    modernAlertStats.style.display = 'none';
                }

                // Buttons
                modernAlertCancel.style.display = options.showCancel !== false ? 'block' : 'none';
                modernAlertCancel.textContent = options.cancelText || 'Cancel';
                modernAlertConfirm.textContent = options.confirmText || 'Confirm';
                modernAlertConfirm.style.display = 'block';

                // Show
                modernAlertModal.classList.add('active');
                document.body.style.overflow = 'hidden';

                // Handlers
                const onConfirm = () => {
                    cleanup();
                    resolve(true);
                };
                const onCancel = () => {
                    cleanup();
                    resolve(false);
                };
                const cleanup = () => {
                    modernAlertConfirm.removeEventListener('click', onConfirm);
                    modernAlertCancel.removeEventListener('click', onCancel);
                    modernAlertModal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                };

                modernAlertConfirm.addEventListener('click', onConfirm);
                modernAlertCancel.addEventListener('click', onCancel);
            });
        }

        // Pagination renderer
        function renderPagination() {
            const paginationContainer = document.querySelector('.Students-pagination');
            if (!paginationContainer) return;

            let html = '';
            html += `<button class="Students-pagination-btn ${currentPage === 1 ? 'disabled' : ''}" ${currentPage === 1 ? 'disabled' : ''} onclick="window.changeStudentsPage(${currentPage - 1})"><i class='bx bx-chevron-left'></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    html += `<button class="Students-pagination-btn ${i === currentPage ? 'active' : ''}" onclick="window.changeStudentsPage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    html += `<span class="Students-pagination-ellipsis">...</span>`;
                }
            }

            html += `<button class="Students-pagination-btn ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} onclick="window.changeStudentsPage(${currentPage + 1})"><i class='bx bx-chevron-right'></i></button>`;
            paginationContainer.innerHTML = html;
        }

        window.changeStudentsPage = function(page) {
            if (page < 1 || page > totalPages || page === currentPage) return;
            currentPage = page;
            fetchStudents();
        };

        // --- API Functions ---
        async function fetchStudents() {
            try {
                const filter     = filterSelect     ? filterSelect.value     : 'all';
                const search     = searchInput      ? searchInput.value      : '';
                const department = deptFilterSelect ? deptFilterSelect.value : 'all';
                const section    = sectionFilterSelect ? sectionFilterSelect.value : 'all';
                
                let url = `${apiBase}?action=get&filter=${filter}&page=${currentPage}&limit=${itemsPerPage}&department=${encodeURIComponent(department)}&section=${encodeURIComponent(section)}`;
                if (search) {
                    url += `&search=${encodeURIComponent(search)}`;
                }

                // Cache-hit fast render: if we have cached data, show it immediately
                // while the fresh fetch runs in the background
                if (_cache.loaded && _cache.students.length > 0) {
                    students    = _cache.students;
                    allStudents = _cache.allStudents;
                    renderStudents();
                    renderPagination();
                    if (_cache.stats) applyStats(_cache.stats);
                }

                // ── OFFLINE: serve from SW API cache with client-side filtering ──
                if (!navigator.onLine) {
                    console.log('📡 OFFLINE: Loading students from cache...');
                    await _serveStudentsOffline(filter, search, department, section);
                    return;
                }
                
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response was:', text);
                    throw new Error('Invalid JSON response from server. The students table may not exist. Please run the database setup SQL files.');
                }
                
                if (result.status === 'success') {
                    const payload = result.data;
                    if (Array.isArray(payload)) {
                        // Legacy array response - client-side pagination
                        allStudents = payload;
                        const viewFiltered = currentView === 'archived' 
                            ? allStudents.filter(s => s.status && s.status.toLowerCase() === 'archived') 
                            : allStudents.filter(s => s.status && s.status.toLowerCase() !== 'archived');
                        totalRecords = viewFiltered.length;
                        totalPages   = Math.ceil(totalRecords / itemsPerPage);
                        const start  = (currentPage - 1) * itemsPerPage;
                        const end    = start + itemsPerPage;
                        students     = viewFiltered.slice(start, end);
                    } else if (payload && Array.isArray(payload.students)) {
                        // New paginated response
                        allStudents  = payload.students;
                        totalRecords = typeof payload.total       === 'number' ? payload.total       : allStudents.length;
                        totalPages   = typeof payload.total_pages === 'number' ? payload.total_pages : Math.ceil(totalRecords / itemsPerPage);
                        currentPage  = typeof payload.page        === 'number' ? payload.page        : currentPage;
                        
                        const viewFiltered = currentView === 'archived' 
                            ? allStudents.filter(s => s.status && s.status.toLowerCase() === 'archived') 
                            : allStudents.filter(s => s.status && s.status.toLowerCase() !== 'archived');
                        
                        students = viewFiltered;
                    } else {
                        console.error('Unexpected API data shape:', payload);
                        showError('Unexpected response from server while loading students.');
                        return;
                    }

                    // Sync fresh data back to window cache
                    _cache.students    = students;
                    _cache.allStudents = allStudents;
                    _cache.loaded      = true;

                    renderStudents();
                    renderPagination();
                    await loadStats();
                } else {
                    console.error('Error fetching students:', result.message);
                    showError(result.message || 'Failed to load students');
                }
            } catch (error) {
                console.error('Error fetching students:', error);
                // If we went offline mid-request, fall back to cache silently
                if (!navigator.onLine) {
                    const filter     = filterSelect     ? filterSelect.value     : 'all';
                    const search     = searchInput      ? searchInput.value      : '';
                    const department = deptFilterSelect ? deptFilterSelect.value : 'all';
                    const section    = sectionFilterSelect ? sectionFilterSelect.value : 'all';
                    await _serveStudentsOffline(filter, search, department, section);
                    return;
                }
                console.error('Full error details:', error.message, error.stack);
                showError('Error loading students: ' + error.message + '. Please check if the students table exists in the database.');
            }
        }

        /**
         * Serve students from the SW API cache when offline.
         * Reads the full cached dataset and applies client-side filtering + pagination.
         */
        async function _serveStudentsOffline(filter, search, department, section) {
            try {
                let list = [];

                // Try the Cache API first (populated by SW / warmAPICache)
                // Scan all caches whose name starts with 'osas-api-' to avoid
                // hard-coding the BUILD_DATE cache name.
                if ('caches' in window) {
                    const cacheNames = await caches.keys();
                    const apiCacheName = cacheNames.find(n => n.startsWith('osas-api-'));
                    if (apiCacheName) {
                        const cache = await caches.open(apiCacheName);
                        // Try the canonical key first, then scan all students entries
                        const canonicalKey = `${apiBase}?action=get&filter=active&page=1&limit=1000`;
                        let cached = await cache.match(new Request(canonicalKey));
                        if (!cached) {
                            const keys = await cache.keys();
                            for (const k of keys) {
                                if (k.url.includes('students.php')) { cached = await cache.match(k); break; }
                            }
                        }
                        if (cached) {
                            const data = await cached.json();
                            list = data.data?.students || data.students || data.data || [];
                            if (!Array.isArray(list)) list = [];
                            console.log(`✅ [offline] Loaded ${list.length} students from SW cache`);
                        }
                    }
                }

                // Fallback: use window cache if SW cache is empty
                if (list.length === 0 && _cache.allStudents && _cache.allStudents.length > 0) {
                    list = _cache.allStudents;
                    console.log(`✅ [offline] Loaded ${list.length} students from window cache`);
                }

                if (list.length === 0) {
                    console.warn('⚠️ [offline] No cached students found');
                    renderStudents(); // renders empty state
                    renderPagination();
                    return;
                }

                // Client-side filtering
                let filtered = list;

                // Filter by status/view
                const effectiveFilter = filter && filter !== 'all' ? filter : (currentView === 'archived' ? 'archived' : 'active');
                if (effectiveFilter === 'active') {
                    filtered = filtered.filter(s => !s.status || s.status.toLowerCase() === 'active');
                } else if (effectiveFilter === 'archived') {
                    filtered = filtered.filter(s => s.status && s.status.toLowerCase() === 'archived');
                } else if (effectiveFilter === 'graduating') {
                    filtered = filtered.filter(s => s.status && s.status.toLowerCase() === 'graduating');
                } else if (effectiveFilter === 'inactive') {
                    filtered = filtered.filter(s => s.status && s.status.toLowerCase() === 'inactive');
                }

                if (search) {
                    const s = search.toLowerCase();
                    filtered = filtered.filter(st => JSON.stringify(st).toLowerCase().includes(s));
                }
                if (department && department !== 'all') {
                    filtered = filtered.filter(st => (st.department || st.department_name || '').toLowerCase() === department.toLowerCase());
                }
                if (section && section !== 'all') {
                    filtered = filtered.filter(st => (st.section_id || st.section || st.section_code || '').toString() === section.toString());
                }

                // Pagination
                totalRecords = filtered.length;
                totalPages   = Math.ceil(totalRecords / itemsPerPage) || 1;
                if (currentPage > totalPages) currentPage = 1;
                const start  = (currentPage - 1) * itemsPerPage;
                students     = filtered.slice(start, start + itemsPerPage);
                allStudents  = filtered;

                // Update window cache so pagination changes work offline
                _cache.students    = students;
                _cache.allStudents = list; // keep full list for re-filtering
                _cache.loaded      = true;

                // Derive stats from full cached list
                if (!_cache.stats) {
                    const total      = list.length;
                    const active     = list.filter(s => !s.status || s.status.toLowerCase() === 'active').length;
                    const inactive   = list.filter(s => s.status && s.status.toLowerCase() === 'inactive').length;
                    const graduating = list.filter(s => s.status && s.status.toLowerCase() === 'graduating').length;
                    _cache.stats = { total, active, inactive, graduating };
                }
                applyStats(_cache.stats);

                renderStudents();
                renderPagination();
                console.log(`✅ [offline] Rendered ${students.length} of ${totalRecords} students`);
            } catch (err) {
                console.error('❌ [offline] Error serving students from cache:', err);
                renderStudents(); // render empty state rather than crash
                renderPagination();
            }
        }

        // Inline count-up animation fallback when window.animateCountUp is unavailable
        function animateCount(el, target) {
            if (!el) return;
            if (window.animateCountUp) { window.animateCountUp(el, target); return; }
            const start    = parseInt(el.textContent) || 0;
            const duration = 600;
            const range    = target - start;
            // Dynamic step: larger values animate in bigger increments for snappier feel
            const step     = range > 1000 ? 20 : range > 100 ? 10 : range > 10 ? 5 : 1;
            const interval = Math.max(16, Math.floor(duration / (Math.abs(range) / step || 1)));
            let current    = start;
            const timer    = setInterval(() => {
                current += range > 0 ? step : -step;
                if ((range > 0 && current >= target) || (range <= 0 && current <= target)) {
                    el.textContent = target;
                    clearInterval(timer);
                } else {
                    el.textContent = current;
                }
            }, interval);
        }

        // Apply a stats object to the DOM (shared by cache-hit path and fresh-fetch path)
        function applyStats(stats) {
            const totalEl       = document.getElementById('totalStudents');
            const activeEl      = document.getElementById('activeStudents');
            const inactiveEl    = document.getElementById('inactiveStudents');
            const graduatingEl  = document.getElementById('graduatingStudents');
            const activePctEl   = document.getElementById('activeStudentsPct');
            const inactivePctEl = document.getElementById('inactiveStudentsPct');
            const graduatingPctEl = document.getElementById('graduatingStudentsPct');

            // Static override: 546 total enrolled (until registrar fixes duplicate IDs)
            const total      = 546;
            const active     = 546;
            const inactive   = Number(stats.inactive)   || 0;
            const graduating = Number(stats.graduating) || 0;

            animateCount(totalEl,      total);
            animateCount(activeEl,     active);
            animateCount(inactiveEl,   inactive);
            animateCount(graduatingEl, graduating);

            const activePct     = total > 0 ? Math.round((active     / total) * 100) : 0;
            const inactivePct   = total > 0 ? Math.round((inactive   / total) * 100) : 0;
            const graduatingPct = total > 0 ? Math.round((graduating / total) * 100) : 0;

            if (activePctEl)     activePctEl.textContent     = `${activePct}%`;
            if (inactivePctEl)   inactivePctEl.textContent   = `${inactivePct}%`;
            if (graduatingPctEl) graduatingPctEl.textContent = `${graduatingPct}%`;
        }

        async function loadStats() {
            // Skip live stats fetch when offline — _serveStudentsOffline already derived them
            if (!navigator.onLine) {
                if (_cache.stats) applyStats(_cache.stats);
                else {
                    // Try reading from SW cache
                    try {
                        if ('caches' in window) {
                            const cacheNames = await caches.keys();
                            const apiCacheName = cacheNames.find(n => n.startsWith('osas-api-'));
                            if (apiCacheName) {
                                const cache = await caches.open(apiCacheName);
                                const cached = await cache.match(new Request(`${apiBase}?action=stats`));
                                if (cached) {
                                    const data = await cached.json();
                                    if (data.status === 'success') { _cache.stats = data.data; applyStats(data.data); }
                                }
                            }
                        }
                    } catch (e) { /* non-critical */ }
                }
                return;
            }
            try {
                const response = await fetch(`${apiBase}?action=stats`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error in stats:', parseError);
                    return; // Silently fail for stats
                }
                
                if (result.status === 'success') {
                    // Cache stats for instant restore on next page visit
                    _cache.stats = result.data;
                    applyStats(result.data);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
                // Don't show error for stats, just log it
            }
        }

        async function addStudent(formData) {
            try {
                const response = await fetch(`${apiBase}?action=add`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showSuccess(result.message || 'Student added successfully!');
                    await fetchStudents();
                    closeModal();
                } else {
                    showError(result.message || 'Failed to add student');
                }
            } catch (error) {
                console.error('Error adding student:', error);
                showError('Error adding student. Please try again.');
            }
        }

        async function updateStudent(studentId, formData) {
            try {
                const response = await fetch(`${apiBase}?action=update`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showSuccess(result.message || 'Student updated successfully!');
                    await fetchStudents();
                    closeModal();
                } else {
                    showError(result.message || 'Failed to update student');
                }
            } catch (error) {
                console.error('Error updating student:', error);
                showError('Error updating student. Please try again.');
            }
        }

        async function deleteStudent(studentId) {
            try {
                const response = await fetch(`${apiBase}?action=delete&id=${studentId}`, {
                    method: 'GET'
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showSuccess(result.message || 'Student archived successfully!');
                    await fetchStudents();
                } else {
                    showError(result.message || 'Failed to archive student');
                }
            } catch (error) {
                console.error('Error deleting student:', error);
                showError('Error archiving student. Please try again.');
            }
        }

        async function restoreStudent(studentId) {
            try {
                const response = await fetch(`${apiBase}?action=restore&id=${studentId}`, {
                    method: 'GET'
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showSuccess(result.message || 'Student restored successfully!');
                    await fetchStudents();
                } else {
                    showError(result.message || 'Failed to restore student');
                }
            } catch (error) {
                console.error('Error restoring student:', error);
                showError('Error restoring student. Please try again.');
            }
        }

        async function activateStudent(studentId) {
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('studentId', studentId);
                formData.append('studentStatus', 'active');
                
                // Get current student data first
                const student = allStudents.find(s => s.id === studentId);
                if (student) {
                    formData.append('studentIdCode', student.studentId);
                    formData.append('firstName', student.firstName);
                    formData.append('lastName', student.lastName);
                    formData.append('studentEmail', student.email);
                    formData.append('studentContact', student.contact || '');
                    formData.append('studentDept', student.department || '');
                    formData.append('studentSection', student.section_id || '');
                    formData.append('studentStatus', 'active');
                }
                
                const response = await fetch(`${apiBase}?action=update`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showSuccess('Student activated successfully!');
                    await fetchStudents();
                } else {
                    showError(result.message || 'Failed to activate student');
                }
            } catch (error) {
                console.error('Error activating student:', error);
                showError('Error activating student. Please try again.');
            }
        }

        async function deactivateStudent(studentId) {
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('studentId', studentId);
                formData.append('studentStatus', 'inactive');
                
                // Get current student data first
                const student = allStudents.find(s => s.id === studentId);
                if (student) {
                    formData.append('studentIdCode', student.studentId);
                    formData.append('firstName', student.firstName);
                    formData.append('lastName', student.lastName);
                    formData.append('studentEmail', student.email);
                    formData.append('studentContact', student.contact || '');
                    formData.append('studentDept', student.department || '');
                    formData.append('studentSection', student.section_id || '');
                    formData.append('studentStatus', 'inactive');
                }
                
                const response = await fetch(`${apiBase}?action=update`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showSuccess('Student deactivated successfully!');
                    await fetchStudents();
                } else {
                    showError(result.message || 'Failed to deactivate student');
                }
            } catch (error) {
                console.error('Error deactivating student:', error);
                showError('Error deactivating student. Please try again.');
            }
        }

        async function loadDepartments() {
            if (!studentDeptSelect) {
                console.warn('studentDeptSelect element not found');
                return;
            }
            
            try {
                const response = await fetch(departmentsApiBase);
                const result = await response.json();
                console.log('Departments API response:', result);
                
                // Clear existing options except the first one
                studentDeptSelect.innerHTML = '<option value="">Select Department</option>';
                
                if (result.status === 'success' && result.data && result.data.length > 0) {
                    result.data.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.code; // Use department_code as value
                        option.textContent = dept.name; // Use department_name as display text
                        studentDeptSelect.appendChild(option);
                    });
                    console.log(`Loaded ${result.data.length} departments`);
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No departments available';
                    studentDeptSelect.appendChild(option);
                    console.warn('No departments found or API error:', result);
                }
            } catch (error) {
                console.error('Error loading departments:', error);
                studentDeptSelect.innerHTML = '<option value="">Error loading departments</option>';
            }
        }

        async function loadFilterDepartments() {
            if (!deptFilterSelect) return;
            
            try {
                const response = await fetch(departmentsApiBase);
                const result = await response.json();
                
                deptFilterSelect.innerHTML = '<option value="all">All Departments</option>';
                
                if (result.status === 'success' && result.data && result.data.length > 0) {
                    result.data.forEach(dept => {
                        const option = document.createElement('option');
                        option.value = dept.code;
                        option.textContent = dept.name;
                        deptFilterSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading filter departments:', error);
            }
        }

        async function loadSectionsByDepartment(departmentCode) {
            if (!departmentCode || !studentSectionSelect) {
                console.warn('Missing departmentCode or studentSectionSelect');
                return;
            }
            
            try {
                const url = `${sectionsApiBase}?action=getByDepartment&department_code=${encodeURIComponent(departmentCode)}`;
                console.log('Loading sections from:', url);
                const response = await fetch(url);
                const result = await response.json();
                console.log('Sections API response:', result);
                
                // Clear existing options
                studentSectionSelect.innerHTML = '<option value="">Select Section</option>';
                
                if (result.status === 'success' && result.data && result.data.length > 0) {
                    result.data.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.id;
                        option.textContent = `${section.section_code} - ${section.section_name}`;
                        studentSectionSelect.appendChild(option);
                    });
                    console.log(`Loaded ${result.data.length} sections for department ${departmentCode}`);
                } else {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No sections available';
                    studentSectionSelect.appendChild(option);
                    console.warn('No sections found for department:', departmentCode, result);
                }
            } catch (error) {
                console.error('Error loading sections:', error);
                studentSectionSelect.innerHTML = '<option value="">Error loading sections</option>';
            }
        }

        async function loadFilterSections(departmentCode) {
            if (!sectionFilterSelect) return;
            
            try {
                const url = `${sectionsApiBase}?action=getByDepartment&department_code=${encodeURIComponent(departmentCode)}`;
                const response = await fetch(url);
                const result = await response.json();
                
                sectionFilterSelect.innerHTML = '<option value="all">All Sections</option>';
                
                if (result.status === 'success' && result.data && result.data.length > 0) {
                    result.data.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.id;
                        option.textContent = `${section.section_code} - ${section.section_name}`;
                        sectionFilterSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading filter sections:', error);
            }
        }

        // --- Render function ---
        function renderStudents() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const filterValue = filterSelect ? filterSelect.value : 'all';
            const deptFilter = deptFilterSelect ? deptFilterSelect.value : 'all';
            const sectionFilter = sectionFilterSelect ? sectionFilterSelect.value : 'all';
            
            const list = Array.isArray(students) ? students : [];
            const filteredStudents = list.filter(s => {
                const fullName = `${s.firstName || ''} ${s.middleName || ''} ${s.lastName || ''}`.toLowerCase();
                const matchesSearch = fullName.includes(searchTerm) || 
                                    (s.studentId || '').toLowerCase().includes(searchTerm) ||
                                    (s.email || '').toLowerCase().includes(searchTerm) ||
                                    (s.department || '').toLowerCase().includes(searchTerm) ||
                                    (s.section || '').toLowerCase().includes(searchTerm);
                
                // Filter by status, but exclude archived from normal view
                let matchesFilter = true;
                if (currentView === 'archived') {
                    matchesFilter = s.status === 'archived';
                } else {
                    matchesFilter = s.status !== 'archived' && (filterValue === 'all' || s.status === filterValue);
                }

                // Filter by department
                if (deptFilter !== 'all' && s.department_code !== deptFilter) {
                    matchesFilter = false;
                }

                // Filter by section
                if (sectionFilter !== 'all' && String(s.section_id) !== String(sectionFilter)) {
                    matchesFilter = false;
                }
                
                return matchesSearch && matchesFilter;
            });

            // Show/hide empty state
            const emptyState = document.getElementById('StudentsEmptyState');
            if (emptyState) {
                emptyState.style.display = filteredStudents.length === 0 ? 'flex' : 'none';
            }

            // Show/hide view containers based on viewMode
            const tableView = document.getElementById('StudentsPrintArea');
            const gridView  = document.getElementById('studentsGridView');
            const listView  = document.getElementById('studentsListView');
            if (tableView) tableView.style.display = viewMode === 'table' ? '' : 'none';
            if (gridView)  gridView.style.display  = viewMode === 'grid'  ? '' : 'none';
            if (listView)  listView.style.display  = viewMode === 'list'  ? '' : 'none';

            if (filteredStudents.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                            <i class='bx bx-inbox' style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                            <p>No students found</p>
                        </td>
                    </tr>
                `;
                const gridBody = document.getElementById('StudentsGridBody');
                const listBody = document.getElementById('StudentsListBody');
                if (gridBody) gridBody.innerHTML = `<p style="text-align:center;color:#999;padding:40px;grid-column:1/-1;">No students found</p>`;
                if (listBody) listBody.innerHTML = `<p style="text-align:center;color:#999;padding:40px;">No students found</p>`;
                renderPagination();
            } else {
                // students is already paginated by fetchStudents — use directly
                const pageItems = filteredStudents;

                // Helper: build avatar URL
                function buildAvatar(s, size) {
                    const fullName = `${s.firstName || ''} ${s.middleName ? s.middleName + ' ' : ''}${s.lastName || ''}`;
                    if (s.avatar && s.avatar !== '') {
                        if (s.avatar.startsWith('http') || s.avatar.startsWith('data:')) return s.avatar;
                        let norm = s.avatar;
                        if (norm.startsWith('assets/') && !norm.startsWith('app/assets/')) norm = norm.replace('assets/', 'app/assets/');
                        const pathMatch = window.location.pathname.match(/^(\/[^\/]+)\//);
                        const base = pathMatch ? pathMatch[1] : '';
                        return base + '/' + norm;
                    }
                    return `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=ffd700&color=333&size=${size}`;
                }

                // ── TABLE VIEW ──────────────────────────────────────────────
                tableBody.innerHTML = pageItems.map(s => {
                    const fullName = `${s.firstName || ''} ${s.middleName ? s.middleName + ' ' : ''}${s.lastName || ''}`;
                    const deptClass = getDepartmentClass(s.department);
                    const avatarUrl = buildAvatar(s, 40);
                    return `
                    <tr data-id="${s.id}">
                        <td class="student-image-cell" data-label="Image">
                            <div class="student-image-wrapper">
                                <img src="${avatarUrl}" alt="${escapeHtml(fullName)}" class="student-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=ffd700&color=333&size=40'">
                            </div>
                        </td>
                        <td class="student-id" data-label="Student ID">${escapeHtml(s.studentId || '')}</td>
                        <td class="student-name" data-label="Name">
                            <div class="student-name-wrapper">
                                <strong>${escapeHtml(fullName)}</strong>
                                <small>${escapeHtml(s.email || '')}</small>
                            </div>
                        </td>
                        <td class="student-dept" data-label="Department">
                            <span class="dept-badge ${deptClass}">${escapeHtml(s.department || 'N/A')}</span>
                        </td>
                        <td class="student-section" data-label="Section">${escapeHtml(s.section || 'N/A')}</td>
                        <td class="student-yearlevel" data-label="Year Level">
                            <span class="yearlevel-badge">${escapeHtml(s.yearlevel || 'N/A')}</span>
                        </td>
                        <td class="student-contact" data-label="Contact No">${escapeHtml(s.contact || 'N/A')}</td>
                        <td data-label="Status">
                            <span class="Students-status-badge ${s.status || 'active'}">${formatStatus(s.status || 'active')}</span>
                        </td>
                        <td data-label="Actions">
                            <div class="Students-action-buttons">
                                <button class="Students-action-btn view" data-id="${s.id}" title="View Profile">
                                    <i class='bx bx-user'></i>
                                </button>
                                ${ (s.status && s.status.toLowerCase() === 'archived') ? `
                                    <button class="Students-action-btn restore" data-id="${s.id}" title="Restore">
                                        <i class='bx bx-undo'></i>
                                    </button>
                                ` : '' }
                            </div>
                        </td>
                    </tr>
                `;
                }).join('');

                // ── GRID / CARD VIEW ────────────────────────────────────────
                const gridBody = document.getElementById('StudentsGridBody');
                if (gridBody) {
                    gridBody.innerHTML = pageItems.map(s => {
                        const fullName = `${s.firstName || ''} ${s.middleName ? s.middleName + ' ' : ''}${s.lastName || ''}`;
                        const deptClass = getDepartmentClass(s.department);
                        const avatarUrl = buildAvatar(s, 64);
                        const statusClass = s.status || 'active';
                        return `
                        <div class="student-card" data-id="${s.id}">
                            <img src="${avatarUrl}" alt="${escapeHtml(fullName)}" class="student-card-avatar"
                                 onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=ffd700&color=333&size=64'">
                            <p class="student-card-name">${escapeHtml(fullName)}</p>
                            <p class="student-card-id">${escapeHtml(s.studentId || '')}</p>
                            <div class="student-card-meta">
                                <div class="student-card-meta-row">
                                    <span>Dept</span>
                                    <span class="dept-badge ${deptClass}" style="font-size:9px;padding:2px 6px;">${escapeHtml(s.department || 'N/A')}</span>
                                </div>
                                <div class="student-card-meta-row">
                                    <span>Section</span>
                                    <span>${escapeHtml(s.section || 'N/A')}</span>
                                </div>
                                <div class="student-card-meta-row">
                                    <span>Year</span>
                                    <span>${escapeHtml(s.yearlevel || 'N/A')}</span>
                                </div>
                            </div>
                            <div class="student-card-badges">
                                <span class="Students-status-badge ${statusClass}">${formatStatus(s.status || 'active')}</span>
                            </div>
                            <div class="student-card-actions">
                                <button class="Students-action-btn view" data-id="${s.id}" title="View Profile">
                                    <i class='bx bx-user'></i>
                                </button>
                                ${ (s.status && s.status.toLowerCase() === 'archived') ? `
                                    <button class="Students-action-btn restore" data-id="${s.id}" title="Restore">
                                        <i class='bx bx-undo'></i>
                                    </button>
                                ` : '' }
                            </div>
                        </div>
                        `;
                    }).join('');
                }

                // ── LIST VIEW ───────────────────────────────────────────────
                const listBody = document.getElementById('StudentsListBody');
                if (listBody) {
                    listBody.innerHTML = pageItems.map(s => {
                        const fullName = `${s.firstName || ''} ${s.middleName ? s.middleName + ' ' : ''}${s.lastName || ''}`;
                        const deptClass = getDepartmentClass(s.department);
                        const avatarUrl = buildAvatar(s, 36);
                        const statusClass = s.status || 'active';
                        return `
                        <div class="student-list-item ${statusClass}" data-id="${s.id}">
                            <div class="student-list-top">
                                <img src="${avatarUrl}" alt="${escapeHtml(fullName)}" class="student-list-avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=ffd700&color=333&size=36'">
                                <div class="student-list-name-block">
                                    <span class="student-list-name">${escapeHtml(fullName)}</span>
                                    <span class="student-list-id">${escapeHtml(s.studentId || '')}</span>
                                </div>
                                <div class="student-list-actions">
                                    <button class="Students-action-btn view" data-id="${s.id}" title="View Profile">
                                        <i class='bx bx-user'></i>
                                    </button>
                                    ${ (s.status && s.status.toLowerCase() === 'archived') ? `
                                        <button class="Students-action-btn restore" data-id="${s.id}" title="Restore">
                                            <i class='bx bx-undo'></i>
                                        </button>
                                    ` : '' }
                                </div>
                            </div>
                            <div class="student-list-badges">
                                <span class="dept-badge ${deptClass}" style="font-size:9px;padding:2px 7px;">${escapeHtml(s.department || 'N/A')}</span>
                                <span style="font-size:9px;color:var(--dark-grey);display:flex;align-items:center;gap:3px;">
                                    <i class='bx bx-group'></i>${escapeHtml(s.section || 'N/A')}
                                </span>
                                <span class="yearlevel-badge" style="font-size:9px;padding:2px 7px;min-width:auto;">${escapeHtml(s.yearlevel || 'N/A')}</span>
                                <span class="Students-status-badge ${statusClass}" style="font-size:9px;">${formatStatus(s.status || 'active')}</span>
                            </div>
                        </div>
                        `;
                    }).join('');
                }
            }

            updateCounts(filteredStudents);
            renderPagination();
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getDepartmentClass(dept) {
            const classes = {
                'BSIT': 'bsit',
                'BSCS': 'bscs',
                'BSBA': 'business',
                'BSN': 'nursing',
                'BEED': 'education',
                'BSED': 'education',
                'CS': 'bsit',
                'BA': 'business',
                'NUR': 'nursing',
                'BSIS': 'bsit',
                'WFT': 'default',
                'BTVTEd': 'education'
            };
            return classes[dept] || 'default';
        }

        function formatStatus(status) {
            const statusMap = {
                'active': 'Active',
                'inactive': 'Inactive',
                'graduating': 'Graduating',
                'archived': 'Archived'
            };
            return statusMap[status] || status;
        }

        // --- Export Functions ---
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

        async function getFilteredStudentsForExport() {
            try {
                const filter = filterSelect ? filterSelect.value : 'all';
                const search = searchInput ? searchInput.value : '';
                const department = deptFilterSelect ? deptFilterSelect.value : 'all';
                const section = sectionFilterSelect ? sectionFilterSelect.value : 'all';
                
                let url = `${apiBase}?action=get&filter=${filter}&limit=all&department=${encodeURIComponent(department)}&section=${encodeURIComponent(section)}`;
                if (search) {
                    url += `&search=${encodeURIComponent(search)}`;
                }
                
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                
                const result = await response.json();
                if (result.status === 'success') {
                    return result.data.students || [];
                }
                return [];
            } catch (error) {
                console.error('Error fetching students for export:', error);
                return [];
            }
        }

        async function downloadStudentsPDF() {
            if (!window.jspdf) {
                if (typeof showNotification === 'function') {
                    showNotification('PDF library not loaded. Please refresh.', 'warning');
                } else {
                    console.warn('PDF library not loaded. Please refresh the page.');
                }
                return;
            }

            // Show loading state
            const exportPDFBtn = document.getElementById('exportPDF');
            const originalText = exportPDFBtn.innerHTML;
            exportPDFBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>Preparing PDF...</span>";
            exportPDFBtn.disabled = true;

            try {
                const exportStudents = await getFilteredStudentsForExport();
                
                if (exportStudents.length === 0) {
                    showError('No student records found to export.');
                    return;
                }

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                const now = new Date();
                
                // --- Header Design ---
                const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
                const headerData = await loadImage(headerPath);

                if (headerData) {
                    // Reduce width to 140mm (from 180mm) to fix stretching, height to 25mm
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
                doc.setFontSize(12); // Reduced from 14
                doc.setTextColor(41, 128, 185); 
                doc.setFont("helvetica", "bold");
                doc.text("STUDENT LIST REPORT", 105, 38, { align: 'center' });

                doc.setFontSize(8); // Reduced from 9
                doc.setTextColor(100, 100, 100);
                doc.setFont("helvetica", "normal");
                doc.text(`Generated on: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`, 105, 43, { align: 'center' });
                doc.text(`Exported by: ${getCurrentAdminName()}`, 105, 47, { align: 'center' });

                // Divider Line
                doc.setDrawColor(220, 220, 220);
                doc.setLineWidth(0.5);
                doc.line(14, 52, 196, 52);
                
                // Summary Stats
                doc.setFontSize(10);
                doc.setTextColor(60, 60, 60);
                doc.text(`Total Records: ${exportStudents.length}`, 14, 62);
                
                let startY = 67;

                // Table
                const tableColumn = ["Student ID", "Name", "Dept", "Section", "Year Level", "Contact No", "Status"];
                const tableRows = [];

                exportStudents.forEach(s => {
                    const fullName = `${s.firstName || ''} ${s.middleName ? s.middleName + ' ' : ''}${s.lastName || ''}`;
                    const rowData = [
                        s.studentId,
                        fullName,
                        s.department || 'N/A',
                        s.section || 'N/A',
                        s.yearlevel || 'N/A',
                        s.contact || 'N/A',
                        formatStatus(s.status || 'active')
                    ];
                    tableRows.push(rowData);
                });

                doc.autoTable({
                    head: [tableColumn],
                    body: tableRows,
                    startY: startY,
                    theme: 'grid',
                    styles: { fontSize: 7, cellPadding: 2, valign: 'middle' },
                    headStyles: { 
                        fillColor: [245, 245, 245], 
                        textColor: [44, 62, 80], 
                        fontStyle: 'bold',
                        lineWidth: 0.1,
                        lineColor: [200, 200, 200]
                    },
                    alternateRowStyles: { fillColor: [255, 255, 255] },
                    margin: { top: 60 }
                });

                doc.save(`Student_List_${now.toISOString().slice(0, 10)}.pdf`);
                if (exportModal) exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            } finally {
                exportPDFBtn.innerHTML = originalText;
                exportPDFBtn.disabled = false;
            }
        }

        async function downloadStudentsExcel() {
            const exportExcelBtn = document.getElementById('exportExcel');
            const originalText = exportExcelBtn.innerHTML;
            exportExcelBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>Preparing Excel...</span>";
            exportExcelBtn.disabled = true;

            try {
                const exportStudents = await getFilteredStudentsForExport();
                
                if (exportStudents.length === 0) {
                    showError('No student records found to export.');
                    return;
                }

                const now = new Date();
                
                // Create CSV content
                const lines = [];
                
                // Header
                lines.push('STUDENT LIST REPORT');
                lines.push(`Generated on,${now.toLocaleDateString()} ${now.toLocaleTimeString()}`);
                lines.push(`Exported by,${getCurrentAdminName()}`);
                lines.push(`Total Records,${exportStudents.length}`);
                lines.push('');
                
                // Column headers
                lines.push(['Student ID', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Contact', 'Address', 'Department', 'Section', 'Year Level', 'Status'].map(csvEscape).join(','));
                
                // Data rows
                exportStudents.forEach(s => {
                    lines.push([
                        s.studentId,
                        s.firstName || '',
                        s.middleName || '',
                        s.lastName || '',
                        s.email || '',
                        s.contact || '',
                        s.address || '',
                        s.department || '',
                        s.section || '',
                        s.yearlevel || '',
                        formatStatus(s.status || 'active')
                    ].map(csvEscape).join(','));
                });

                const csvContent = lines.join('\r\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const fileName = `Student_List_${now.toISOString().slice(0, 10)}.csv`;
                
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

                if (exportModal) exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            } finally {
                exportExcelBtn.innerHTML = originalText;
                exportExcelBtn.disabled = false;
            }
        }

        async function downloadStudentsWord() {
            if (!window.docx) {
                showError('DOCX library not loaded. Please refresh the page.');
                return;
            }

            const exportWordBtn = document.getElementById('exportWord');
            const originalText = exportWordBtn.innerHTML;
            exportWordBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>Preparing Word...</span>";
            exportWordBtn.disabled = true;

            try {
                const exportStudents = await getFilteredStudentsForExport();
                
                if (exportStudents.length === 0) {
                    showError('No student records found to export.');
                    return;
                }

                const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType, HeadingLevel, ImageRun, AlignmentType, BorderStyle, VerticalAlign } = window.docx;
                const now = new Date();

                // Load header image
                let headerImage = null;
                try {
                    const response = await fetch((getProjectRoot() + '/app/assets/headers/header.png'));
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

                // Table Header with modern styling
                const tableHeader = new TableRow({
                    children: [
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Student ID", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 14, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Full Name", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 25, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Email", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 20, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Department", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 12, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Section", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 10, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Year", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 8, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                        new TableCell({ 
                            children: [new Paragraph({ 
                                children: [new TextRun({ text: "Status", bold: true, size: 18, color: "FFFFFF" })],
                                alignment: AlignmentType.CENTER
                            })],
                            shading: { fill: "2C3E50", val: "clear", color: "auto" },
                            verticalAlign: VerticalAlign.CENTER,
                            width: { size: 8, type: WidthType.PERCENTAGE },
                            margins: { top: 80, bottom: 80, left: 80, right: 80 }
                        }),
                    ],
                    tableHeader: true,
                    height: { value: 600, rule: "atLeast" }
                });

                // Table Rows with modern styling
                const tableRows = exportStudents.map((s, index) => {
                    const fullName = `${s.firstName || ''} ${s.middleName ? s.middleName + ' ' : ''}${s.lastName || ''}`;
                    const isEven = index % 2 === 0;
                    const rowColor = isEven ? "FFFFFF" : "F8F9FA";
                    
                    return new TableRow({
                        children: [
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: s.studentId || '', size: 18 })],
                                    alignment: AlignmentType.LEFT
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 14, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: fullName, size: 18 })],
                                    alignment: AlignmentType.LEFT
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 25, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: s.email || 'N/A', size: 18 })],
                                    alignment: AlignmentType.LEFT
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 20, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: s.department || 'N/A', size: 18 })],
                                    alignment: AlignmentType.CENTER
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 12, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: s.section || 'N/A', size: 18 })],
                                    alignment: AlignmentType.CENTER
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 10, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: s.yearlevel || 'N/A', size: 18 })],
                                    alignment: AlignmentType.CENTER
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 8, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                            new TableCell({ 
                                children: [new Paragraph({ 
                                    children: [new TextRun({ text: formatStatus(s.status || 'active'), size: 18 })],
                                    alignment: AlignmentType.CENTER
                                })],
                                shading: { fill: rowColor, val: "clear", color: "auto" },
                                verticalAlign: VerticalAlign.CENTER,
                                width: { size: 8, type: WidthType.PERCENTAGE },
                                margins: { top: 60, bottom: 60, left: 80, right: 80 }
                            }),
                        ],
                        height: { value: 400, rule: "atLeast" }
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
                        text: 'STUDENT LIST REPORT',
                        heading: HeadingLevel.HEADING_1,
                        alignment: AlignmentType.CENTER,
                        spacing: { after: 200 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Generated on: ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`,
                                italics: true,
                                color: "666666",
                                size: 18,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                        spacing: { after: 100 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Exported by: ${getCurrentAdminName()}`,
                                italics: true,
                                color: "666666",
                                size: 18,
                            }),
                        ],
                        alignment: AlignmentType.CENTER,
                        spacing: { after: 100 },
                    }),
                    new Paragraph({
                        children: [
                            new TextRun({
                                text: `Total Records: ${exportStudents.length}`,
                                bold: true,
                                size: 20,
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
                        borders: {
                            top: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                            bottom: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                            left: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                            right: { style: BorderStyle.SINGLE, size: 2, color: "2C3E50" },
                            insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: "E0E0E0" },
                            insideVertical: { style: BorderStyle.SINGLE, size: 1, color: "E0E0E0" },
                        },
                        layout: "fixed",
                        columnWidths: [500, 1200, 2500, 2000, 1200, 1000, 800, 800]
                    })
                );

                const doc = new Document({
                    sections: [{
                        properties: {
                            page: {
                                margin: {
                                    top: 720,
                                    right: 720,
                                    bottom: 720,
                                    left: 720,
                                },
                            },
                        },
                        children: children,
                    }],
                });

                Packer.toBlob(doc).then(blob => {
                    saveAs(blob, `Student_List_${now.toISOString().slice(0, 10)}.docx`);
                });

                if (exportModal) exportModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            } finally {
                exportWordBtn.innerHTML = originalText;
                exportWordBtn.disabled = false;
            }
        }

        function updateCounts(filteredStudents) {
            const showingEl = document.getElementById('showingStudentsCount');
            const totalCountEl = document.getElementById('totalStudentsCount');
            
            if (showingEl) showingEl.textContent = filteredStudents.length;
            if (totalCountEl) totalCountEl.textContent = totalRecords;
        }

        // --- Modal functions ---
        async function openModal(editId = null) {
            if (!modal) return;
            if (editId === null) {
                showError('Adding students manually is disabled. Please use Import.');
                return;
            }
            
            const modalTitle = document.getElementById('StudentsModalTitle');
            const form = document.getElementById('StudentsForm');
            
            editingStudentId = editId;
            
            // Load departments every time modal opens
            await loadDepartments();
            
            const span = modalTitle.querySelector('span');
            if (span) {
                span.textContent = 'Edit Student';
            } else {
                modalTitle.innerHTML = '<i class=\'bx bxs-group\'></i><span>Edit Student</span>';
            }
            const student = allStudents.find(s => s.id === editId);
            if (student) {
                document.getElementById('studentId').value = student.studentId || '';
                document.getElementById('studentStatus').value = student.status || 'active';
                document.getElementById('firstName').value = student.firstName || '';
                document.getElementById('middleName').value = student.middleName || '';
                document.getElementById('lastName').value = student.lastName || '';
                document.getElementById('studentEmail').value = student.email || '';
                document.getElementById('studentContact').value = student.contact || '';
                document.getElementById('studentDept').value = student.department || '';
                document.getElementById('studentAddress').value = student.address || '';
                document.getElementById('studentYearlevel').value = student.yearlevel || '';
                
                if (student.department) {
                    await loadSectionsByDepartment(student.department);
                    if (student.section_id) {
                        document.getElementById('studentSection').value = student.section_id;
                    }
                }
                
                if (student.avatar && student.avatar !== '') {
                    const previewImg = document.querySelector('.Students-preview-img');
                    const previewPlaceholder = document.querySelector('.Students-preview-placeholder');
                    if (previewImg && previewPlaceholder) {
                        let avatarUrl = student.avatar;
                        if (!avatarUrl.startsWith('http') && !avatarUrl.startsWith('data:') && !avatarUrl.startsWith('/')) {
                            if (avatarUrl.startsWith('assets/') && !avatarUrl.startsWith('app/assets/')) {
                                avatarUrl = avatarUrl.replace('assets/', 'app/assets/');
                            }
                            const pathMatch = window.location.pathname.match(/^(\/[^\/]+)\//);
                            const projectBase = pathMatch ? pathMatch[1] : '';
                            avatarUrl = projectBase + '/' + avatarUrl;
                        }
                        previewImg.src = avatarUrl;
                        previewImg.setAttribute('data-existing-avatar', student.avatar);
                        previewImg.style.display = 'block';
                        previewPlaceholder.style.display = 'none';
                    }
                }
            } else if (form) {
                form.reset();
            }
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openProfileModal(student) {
            const profileModal = document.getElementById('StudentProfileModal');
            if (!profileModal) return;

            // Fill details
            const fullName = `${student.firstName} ${student.middleName ? student.middleName + ' ' : ''}${student.lastName}`;
            document.getElementById('profileFullName').textContent = fullName;
            document.getElementById('profileId').textContent = student.studentId || 'N/A';
            document.getElementById('profileDept').textContent = student.department || 'N/A';
            document.getElementById('profileSection').textContent = student.section || 'N/A';
            document.getElementById('profileYear').textContent = student.yearlevel || 'N/A';
            document.getElementById('profileEmail').textContent = student.email || 'N/A';
            document.getElementById('profileContact').textContent = student.contact || 'N/A';
            document.getElementById('profileDate').textContent = student.date || 'N/A';
            document.getElementById('profileAddress').textContent = student.address || 'No address provided.';
            
            // Avatar
            const avatarImg = document.getElementById('profileAvatar');
            if (avatarImg) {
                avatarImg.src = student.avatar || '../app/assets/img/default.png';
            }

            // Status Badge
            const statusBadge = document.getElementById('profileStatusBadge');
            if (statusBadge) {
                const status = student.status || 'active';
                statusBadge.className = `status-badge ${status.toLowerCase()}`;
                statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }

            profileModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeProfileModal() {
            const profileModal = document.getElementById('StudentProfileModal');
            if (profileModal) {
                profileModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }

        function closeModal() {
            if (!modal) return;
            
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            const form = document.getElementById('StudentsForm');
            if (form) form.reset();
            // Reset image preview
            const previewImg = document.querySelector('.Students-preview-img');
            const previewPlaceholder = document.querySelector('.Students-preview-placeholder');
            if (previewImg && previewPlaceholder) {
                previewImg.style.display = 'none';
                previewImg.src = '';
                previewImg.removeAttribute('data-existing-avatar');
                previewPlaceholder.style.display = 'flex';
            }
            // Reset image input
            const studentImageInput = document.getElementById('studentImage');
            if (studentImageInput) {
                studentImageInput.value = '';
            }
            editingStudentId = null;
        }

        // --- Event handlers ---
        function handleTableClick(e) {
            const viewBtn = e.target.closest('.Students-action-btn.view');
            const restoreBtn = e.target.closest('.Students-action-btn.restore');

            if (viewBtn) {
                const id = parseInt(viewBtn.dataset.id);
                const student = allStudents.find(s => s.id === id);
                if (student) {
                    openProfileModal(student);
                }
            }

            if (restoreBtn) {
                const id = parseInt(restoreBtn.dataset.id);
                const student = allStudents.find(s => s.id === id);
                if (student) {
                    showModernAlert({
                        title: 'Restore Student',
                        message: `Restore student "${student.firstName} ${student.lastName}" to active status?`,
                        icon: 'info',
                        confirmText: 'Yes, Restore'
                    }).then(confirmed => {
                        if (confirmed) restoreStudent(id);
                    });
                }
            }
        }

        // Utility functions
        function showError(message) {
            if (window.showNotification && typeof window.showNotification === 'function') {
                window.showNotification(message, 'error');
            } else if (typeof showNotification === 'function') {
                showNotification(message, 'error');
            } else {
                console.error(message);
            }
        }

        function showSuccess(message) {
            if (window.showNotification && typeof window.showNotification === 'function') {
                window.showNotification(message, 'success');
            } else if (typeof showNotification === 'function') {
                showNotification(message, 'success');
            } else {
                console.log(message);
            }
        }

        // --- Initialize ---
        async function initialize() {
            // Set default view to active (hide archived by default)
            currentView = 'active';
            if (filterSelect) {
                filterSelect.value = 'active';
            }

            // Load filters
            await loadFilterDepartments();

            // Initial load - only active students
            await fetchStudents();

            // Search functionality with debounce and page reset
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentPage = 1;
                        fetchStudents();
                    }, 500);
                });
            }

            // Filter change resets page and fetches
            if (filterSelect) {
                filterSelect.addEventListener('change', () => {
                    // Sync currentView with filter selection
                     if (filterSelect.value === 'archived') {
                         currentView = 'archived';
                     } else {
                         currentView = 'active';
                     }
                    currentPage = 1;
                    fetchStudents();
                });
            }

            // Department filter listener
            if (deptFilterSelect) {
                deptFilterSelect.addEventListener('change', async () => {
                    const deptCode = deptFilterSelect.value;
                    
                    // Reset section filter when department changes
                    if (sectionFilterSelect) {
                        sectionFilterSelect.innerHTML = '<option value="all">All Sections</option>';
                        sectionFilterSelect.value = 'all';
                    }
                    
                    if (deptCode !== 'all') {
                        await loadFilterSections(deptCode);
                    }
                    
                    currentPage = 1;
                    fetchStudents();
                });
            }

            // Section filter listener
            if (sectionFilterSelect) {
                sectionFilterSelect.addEventListener('change', () => {
                    currentPage = 1;
                    fetchStudents();
                });
            }

            // Event listeners for table
            tableBody.addEventListener('click', handleTableClick);

            // View toggle buttons
            const viewBtns = document.querySelectorAll('.Students-view-btn');
            viewBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    viewMode = btn.dataset.view;
                    localStorage.setItem('studentsViewMode', viewMode);
                    viewBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    renderStudents();
                });
            });

            // Set initial active state from saved preference
            viewBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === viewMode);
            });

            // Delegate clicks on grid and list views to handleTableClick
            const gridView = document.getElementById('studentsGridView');
            const listView = document.getElementById('studentsListView');
            if (gridView) gridView.addEventListener('click', handleTableClick);
            if (listView) listView.addEventListener('click', handleTableClick);

            // Export Students button
            if (exportBtn) {
                exportBtn.addEventListener('click', () => {
                    if (exportModal) {
                        exportModal.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                });
            }

            // --- Import Logic ---
            const importModal = document.getElementById('ImportStudentsModal');
            const importForm = document.getElementById('ImportStudentsForm');
            const fileInput = document.getElementById('enrollmentList');
            const dropZone = document.getElementById('dropZone');
            const selectedFileName = document.getElementById('selectedFileName');
            const submitImportBtn = document.getElementById('submitImportBtn');
            const closeImportBtn = document.getElementById('closeImportModal');
            const cancelImportBtn = document.getElementById('cancelImportBtn');
            const importModalOverlay = document.getElementById('ImportModalOverlay');
            let droppedFile = null;

            function openImportModal() {
                if (importModal) {
                    importModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }

            function closeImportModal() {
                if (importModal) {
                    importModal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    if (importForm) importForm.reset();
                    droppedFile = null; // Clear dropped file
                    if (selectedFileName) {
                        selectedFileName.textContent = '';
                        selectedFileName.style.display = 'none';
                    }
                    if (submitImportBtn) submitImportBtn.disabled = true;
                    // Reset drop zone
                    if (dropZone) {
                        dropZone.style.borderColor = '#ddd';
                        dropZone.querySelector('i').style.color = '#aaa';
                    }
                }
            }

            if (importBtn) {
                importBtn.addEventListener('click', openImportModal);
            }

            if (closeImportBtn) closeImportBtn.addEventListener('click', closeImportModal);
            if (cancelImportBtn) cancelImportBtn.addEventListener('click', closeImportModal);
            if (importModalOverlay) importModalOverlay.addEventListener('click', closeImportModal);

            if (dropZone) {
                dropZone.addEventListener('click', () => fileInput && fileInput.click());
                
                dropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropZone.style.borderColor = 'var(--gold)';
                    dropZone.querySelector('i').style.color = 'var(--gold)';
                });

                dropZone.addEventListener('dragleave', () => {
                    dropZone.style.borderColor = '#ddd';
                    dropZone.querySelector('i').style.color = '#aaa';
                });

                dropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        droppedFile = files[0]; // Store in variable
                        handleFileSelection(droppedFile);
                    }
                });
            }

            if (fileInput) {
                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length > 0) {
                        droppedFile = null; // Reset dropped file if manual choice
                        handleFileSelection(fileInput.files[0]);
                    }
                });
            }

            function handleFileSelection(file) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (['csv', 'xlsx', 'xls'].includes(ext)) {
                    selectedFileName.textContent = `Selected: ${file.name}`;
                    selectedFileName.style.display = 'block';
                    submitImportBtn.disabled = false;
                    dropZone.style.borderColor = '#27ae60';
                    dropZone.querySelector('i').style.color = '#27ae60';
                } else {
                    showError('Please select a valid CSV or Excel file.');
                    submitImportBtn.disabled = true;
                    selectedFileName.style.display = 'none';
                }
            }

            if (importForm) {
                importForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    // 1. Get the file BEFORE closing the modal
                    const fileToUpload = droppedFile || (fileInput.files.length > 0 ? fileInput.files[0] : null);
                    
                    if (!fileToUpload) {
                        showError('No file selected. Please select a file first.');
                        return;
                    }

                    const confirmed = await showModernAlert({
                        title: 'Confirm Import',
                        message: 'Sync students with the selected file?',
                        confirmText: 'Yes, Start Import',
                        cancelText: 'Cancel'
                    });

                    if (!confirmed) return;

                    // 2. Now we can safely close the modal and reset it
                    closeImportModal();
                    
                    showModernAlert({
                        title: 'Importing...',
                        message: 'Processing file...',
                        icon: 'loading',
                        showCancel: false,
                        confirmText: 'Processing...'
                    });

                    try {
                        const formData = new FormData();
                        formData.append('enrollmentList', fileToUpload);
                        
                        const response = await fetch(`${apiBase}?action=import`, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();

                        if (result.status === 'success') {
                            const { created, updated, skipped } = result.data;
                            await showModernAlert({
                                title: 'Import Successful',
                                message: 'Synchronization complete.',
                                icon: 'success',
                                showCancel: false,
                                confirmText: 'Great!',
                                stats: { created, updated, skipped }
                            });
                            fetchStudents();
                        } else {
                            await showModernAlert({
                                title: 'Import Failed',
                                message: result.message || 'Error processing file.',
                                icon: 'error',
                                showCancel: false,
                                confirmText: 'Try Again'
                            });
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        await showModernAlert({
                            title: 'Error',
                            message: error.message || 'Connection error.',
                            icon: 'error',
                            showCancel: false,
                            confirmText: 'Dismiss'
                        });
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
                    await downloadStudentsPDF();
                });
            }

            const exportExcelBtn = document.getElementById('exportExcel');
            if (exportExcelBtn) {
                exportExcelBtn.addEventListener('click', async () => {
                    await downloadStudentsExcel();
                });
            }

            const exportWordBtn = document.getElementById('exportWord');
            if (exportWordBtn) {
                exportWordBtn.addEventListener('click', async () => {
                    await downloadStudentsWord();
                });
            }

            if (btnImportFirstStudents) {
                btnImportFirstStudents.addEventListener('click', openImportModal);
            }

            // Close modal
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (modalOverlay) modalOverlay.addEventListener('click', closeModal);

            // Profile Modal close listeners
            const closeProfileModalBtn = document.getElementById('closeProfileModal');
            const closeProfileBtn = document.getElementById('closeProfileBtn');
            const profileModalOverlay = document.getElementById('ProfileModalOverlay');

            if (closeProfileModalBtn) closeProfileModalBtn.addEventListener('click', closeProfileModal);
            if (closeProfileBtn) closeProfileBtn.addEventListener('click', closeProfileModal);
            if (profileModalOverlay) profileModalOverlay.addEventListener('click', closeProfileModal);

            // Escape key to close modals
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (modal && modal.classList.contains('active')) {
                        closeModal();
                    }
                    const profileModal = document.getElementById('StudentProfileModal');
                    if (profileModal && profileModal.classList.contains('active')) {
                        closeProfileModal();
                    }
                }
            });

            // Image upload preview
            const studentImageInput = document.getElementById('studentImage');
            const uploadImageBtn = document.getElementById('uploadImageBtn');
            const previewImg = document.querySelector('.Students-preview-img');
            const previewPlaceholder = document.querySelector('.Students-preview-placeholder');

            if (uploadImageBtn) {
                uploadImageBtn.addEventListener('click', () => {
                    if (studentImageInput) studentImageInput.click();
                });
            }

            if (studentImageInput && previewImg && previewPlaceholder) {
                studentImageInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            previewImg.style.display = 'block';
                            previewPlaceholder.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Department change - load sections
            if (studentDeptSelect) {
                studentDeptSelect.addEventListener('change', function() {
                    const deptCode = this.value;
                    if (deptCode) {
                        loadSectionsByDepartment(deptCode);
                    } else {
                        if (studentSectionSelect) {
                            studentSectionSelect.innerHTML = '<option value="">Select Department First</option>';
                        }
                    }
                });
            }

            // Form submission
            if (studentsForm) {
                studentsForm.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    // Modern validation with highlighting
                    if (!validateStudentForm()) {
                        return;
                    }

                    const studentIdCode = (document.getElementById('studentId')?.value || '').trim();
                    const formData = new FormData(studentsForm);

                    // Ensure backend gets the expected student ID field
                    formData.set('studentIdCode', studentIdCode);

                    // Preserve existing avatar on update if no new image is selected
                    const studentImageInput = document.getElementById('studentImage');
                    const hasNewImage = !!(studentImageInput && studentImageInput.files && studentImageInput.files.length > 0);
                    if (!hasNewImage) {
                        const previewImg = document.querySelector('.Students-preview-img');
                        const existingAvatar = previewImg ? previewImg.getAttribute('data-existing-avatar') : '';
                        if (existingAvatar) {
                            formData.set('studentAvatar', existingAvatar);
                        }
                    }

                    if (editingStudentId) {
                        // Avoid conflicting with the form's studentId field by using `id` for DB id
                        formData.set('id', editingStudentId);
                        await updateStudent(editingStudentId, formData);
                    } else {
                        showError('Adding students manually is disabled. Please use Import.');
                    }
                });
            }

            // Sort functionality
            const sortHeaders = document.querySelectorAll('.Students-sortable');
            sortHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const sortBy = this.dataset.sort;
                    sortStudents(sortBy);
                });
            });

            function sortStudents(sortBy) {
                students.sort((a, b) => {
                    switch(sortBy) {
                        case 'name':
                            const nameA = `${a.firstName} ${a.lastName}`.toLowerCase();
                            const nameB = `${b.firstName} ${b.lastName}`.toLowerCase();
                            return nameA.localeCompare(nameB);
                        case 'studentId':
                            return (a.studentId || '').localeCompare(b.studentId || '');
                        case 'department':
                            return (a.department || '').localeCompare(b.department || '');
                        case 'section':
                            return (a.section || '').localeCompare(b.section || '');
                        case 'status':
                            return (a.status || '').localeCompare(b.status || '');
                        case 'id':
                            return (a.id || 0) - (b.id || 0);
                        default:
                            return 0;
                    }
                });
                renderStudents();
            }
        }

        // Start initialization
        initialize(); 
        
    } catch (error) {
        console.error('❌ Error initializing Students module:', error);
    }
}

// Make function globally available
window.initStudentsModule = initStudentsModule;
