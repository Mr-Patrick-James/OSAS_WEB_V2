// department.js
function initDepartmentModule() {
  console.log('🛠 Initializing Department module...');

  // Elements
  const tableBody = document.getElementById('departmentTableBody');
  const btnAddDepartment = document.getElementById('btnAddDepartment');
  const btnAddFirstDept = document.getElementById('btnAddFirstDepartment');
  const modal = document.getElementById('departmentModal');
  const modalOverlay = document.getElementById('modalOverlay');
  const closeBtn = document.getElementById('closeModal');
  const cancelBtn = document.getElementById('cancelModal');
  const departmentForm = document.getElementById('departmentForm');
  const searchInput = document.getElementById('searchDepartment');
  const filterSelect = document.getElementById('departmentFilter');
  const exportBtn = document.getElementById('btnExport');
  const exportModal = document.getElementById('ExportDepartmentsModal');
  const closeExportBtn = document.getElementById('closeExportModal');
  const exportModalOverlay = document.getElementById('ExportModalOverlay');
  const exportPDFBtn = document.getElementById('exportPDF');
  const exportExcelBtn = document.getElementById('exportExcel');
  const exportWordBtn = document.getElementById('exportWord');

  // Check for essential elements
  if (!tableBody) {
    console.error('❗ #departmentTableBody not found. Table won\'t render.');
    return;
  }

  if (!modal) {
    console.warn('⚠️ #departmentModal not found. Modal functionality disabled.');
  }

  // Use window-level cache so data persists when switching pages and back
  // This prevents re-fetching every time the departments page is visited
  if (!window._departmentsCache) window._departmentsCache = { departments: [], stats: null, loaded: false };
  const _cache = window._departmentsCache;

  // --- Department data (loaded from database) ---
  let departments = _cache.departments;
  let currentView = 'active'; // 'active' or 'archived'
  let viewMode    = localStorage.getItem('deptViewMode') || 'list'; // 'table', 'grid', 'list'
  let currentPage  = 1;
  let itemsPerPage = 10;
  let totalRecords = 0;
  let totalPages   = 0;

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

  // Get department icon based on code
  function getDeptIcon(code) {
    const icons = {
      'CS': 'bx-code-alt',
      'BA': 'bx-briefcase-alt',
      'NUR': 'bx-heart',
      'BSIS': 'bx-laptop',
      'WFT': 'bx-cog',
      'BTVTEd': 'bx-wrench',
      'ENG': 'bx-calculator',
      'ART': 'bx-palette'
    };
    return icons[code] || 'bx-building';
  }

  // --- Render helper (updated for new table structure) ---
  function renderDepartments(deptArray = departments) {
    // Show/hide empty state
    const emptyState = document.getElementById('emptyState');
    if (deptArray.length === 0) {
      if (emptyState) emptyState.style.display = 'flex';
      tableBody.innerHTML = '';
      updateCounts(0);
      renderPagination();
      return;
    } else {
      if (emptyState) emptyState.style.display = 'none';
    }

    // Show/hide view containers
    const tableViewEl = document.getElementById('deptTableView');
    const gridViewEl  = document.getElementById('deptGridView');
    const listViewEl  = document.getElementById('deptListView');
    if (tableViewEl) tableViewEl.style.display = viewMode === 'table' ? '' : 'none';
    if (gridViewEl)  gridViewEl.style.display  = viewMode === 'grid'  ? '' : 'none';
    if (listViewEl)  listViewEl.style.display  = viewMode === 'list'  ? '' : 'none';

    // ── Helper: action buttons ──────────────────────────────
    function actionBtns(d) {
      return `
        <button class="action-btn view" data-id="${d.id}" title="View"><i class='bx bx-show'></i></button>
        ${d.status !== 'archived' ? `<button class="action-btn edit" data-id="${d.id}" title="Edit"><i class='bx bx-edit'></i></button>` : ''}
        ${d.status === 'archived' ? `<button class="action-btn restore" data-id="${d.id}" title="Restore"><i class='bx bx-reset'></i></button>` : ''}
      `;
    }

    // ── TABLE VIEW ──────────────────────────────────────────
    tableBody.innerHTML = deptArray.map(d => `
      <tr data-id="${d.id}">
        <td class="department-name" data-label="Department Name">
          <div class="name-wrapper">
            <div class="department-icon">
              <i class='bx ${getDeptIcon(d.code)}'></i>
            </div>
            <div>
              <strong>${d.name}</strong>
              <small class="department-code">${d.code}</small>
            </div>
          </div>
        </td>
        <td class="hod-name" data-label="HOD">${d.hod}</td>
        <td class="student-count" data-label="Students">${d.studentCount}</td>
        <td class="date-created" data-label="Date Created">${d.date}</td>
        <td data-label="Status">
          <span class="status-badge ${d.status}">${d.status === 'active' ? 'Active' : 'Archived'}</span>
        </td>
        <td data-label="Actions">
          <div class="action-buttons">${actionBtns(d)}</div>
        </td>
      </tr>
    `).join('');

    // ── GRID / CARD VIEW ────────────────────────────────────
    const gridBody = document.getElementById('deptGridBody');
    if (gridBody) {
      gridBody.innerHTML = deptArray.map(d => `
        <div class="dept-card" data-id="${d.id}">
          <div class="dept-card-top"></div>
          <div class="dept-card-body">
            <div class="dept-card-icon-row">
              <div class="dept-card-icon"><i class='bx ${getDeptIcon(d.code)}'></i></div>
              <div>
                <p class="dept-card-name">${d.name}</p>
                <p class="dept-card-code">${d.code}</p>
              </div>
            </div>
            <div class="dept-card-divider"></div>
            <div class="dept-card-meta">
              <div class="dept-card-meta-row">
                <span class="dept-card-meta-label">HOD</span>
                <span class="dept-card-meta-value" style="text-align:right;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${d.hod || 'N/A'}</span>
              </div>
              <div class="dept-card-meta-row">
                <span class="dept-card-meta-label">Students</span>
                <span class="dept-card-meta-value">${d.studentCount}</span>
              </div>
            </div>
          </div>
          <div class="dept-card-footer">
            <span class="status-badge ${d.status}" style="font-size:9px;">${d.status === 'active' ? 'Active' : 'Archived'}</span>
            <div class="dept-card-actions">${actionBtns(d)}</div>
          </div>
        </div>
      `).join('');
    }

    // ── LIST VIEW ───────────────────────────────────────────
    const listBody = document.getElementById('deptListBody');
    if (listBody) {
      listBody.innerHTML = deptArray.map(d => `
        <div class="dept-list-item" data-id="${d.id}">
          <div class="dept-list-top">
            <div class="dept-list-icon"><i class='bx ${getDeptIcon(d.code)}'></i></div>
            <div class="dept-list-name-block">
              <span class="dept-list-name">${d.name}</span>
              <span class="dept-list-code">${d.code}</span>
            </div>
            <div class="dept-list-actions">${actionBtns(d)}</div>
          </div>
          <div class="dept-list-badges">
            <span style="font-size:9px;color:var(--dark-grey);display:flex;align-items:center;gap:3px;">
              <i class='bx bx-user'></i>${d.studentCount} students
            </span>
            <span style="font-size:9px;color:var(--dark-grey);display:flex;align-items:center;gap:3px;">
              <i class='bx bx-user-pin'></i>${d.hod || 'N/A'}
            </span>
            <span class="status-badge ${d.status}" style="font-size:9px;">${d.status === 'active' ? 'Active' : 'Archived'}</span>
          </div>
        </div>
      `).join('');
    }

    // Update stats and counts
    updateStats();
    updateCounts(deptArray.length);
    renderPagination();
  }

  // Update statistics (now loaded from database)
  function updateStats() {
    // Stats are loaded from database via loadStats()
    // This function is kept for compatibility but stats are updated via API
  }

  // Update showing/total counts
  function updateCounts(showingCount) {
    const showingEl = document.getElementById('showingCount');
    const totalCountEl = document.getElementById('totalCount');
    
    if (showingEl) showingEl.textContent = showingCount;
    if (totalCountEl) totalCountEl.textContent = totalRecords;
  }

  // --- Pagination Renderer ---
  function renderPagination() {
    const paginationContainer = document.querySelector('.pagination');
    if (!paginationContainer) return;

    let html = '';
    
    // Previous button
    html += `
      <button class="pagination-btn ${currentPage === 1 ? 'disabled' : ''}" 
              ${currentPage === 1 ? 'disabled' : ''} 
              onclick="window.changeDepartmentPage(${currentPage - 1})">
        <i class='bx bx-chevron-left'></i>
      </button>
    `;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
        html += `
          <button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                  onclick="window.changeDepartmentPage(${i})">${i}</button>
        `;
      } else if (i === currentPage - 2 || i === currentPage + 2) {
        html += `<span class="pagination-ellipsis">...</span>`;
      }
    }

    // Next button
    html += `
      <button class="pagination-btn ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}" 
              ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} 
              onclick="window.changeDepartmentPage(${currentPage + 1})">
        <i class='bx bx-chevron-right'></i>
      </button>
    `;

    paginationContainer.innerHTML = html;
  }

  // Global function for pagination buttons
  window.changeDepartmentPage = function(page) {
    if (page < 1 || page > totalPages || page === currentPage) return;
    currentPage = page;
    loadDepartments(filterSelect ? filterSelect.value : currentView);
  };

  // --- Load departments from database ---
  async function loadDepartments(filter = 'active') {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';
      
      const searchTerm = searchInput ? searchInput.value : '';
      const url = `${apiPath}?action=get&filter=${filter}&search=${encodeURIComponent(searchTerm)}&page=${currentPage}&limit=${itemsPerPage}`;

      // Cache-hit fast render: show cached data immediately while fresh fetch runs
      if (_cache.loaded && _cache.departments.length > 0) {
        departments = _cache.departments;
        renderDepartments(departments);
        if (_cache.stats) applyStats(_cache.stats);
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
        throw new Error('Invalid JSON response from server');
      }
      
      if (result.status === 'success') {
        const payload = result.data;

        let list = [];
        let meta = {
          total: 0,
          page: currentPage || 1,
          limit: itemsPerPage,
          total_pages: 1
        };

        if (Array.isArray(payload)) {
          list = payload;
          meta.total = payload.length;
        } else if (payload && Array.isArray(payload.departments)) {
          list = payload.departments;
          meta.total       = typeof payload.total       === 'number' ? payload.total       : list.length;
          meta.page        = typeof payload.page        === 'number' ? payload.page        : meta.page;
          meta.limit       = typeof payload.limit       === 'number' ? payload.limit       : meta.limit;
          meta.total_pages = typeof payload.total_pages === 'number' ? payload.total_pages : Math.ceil(meta.total / meta.limit);
        } else {
          console.error('Unexpected API data shape:', payload);
          if (typeof showNotification === 'function') {
            showNotification('Unexpected response from server while loading departments.', 'error');
          }
          return;
        }

        totalRecords = meta.total;
        totalPages   = meta.total_pages;
        currentPage  = meta.page;

        departments = list.map(dept => ({
          id:           dept.department_id,
          name:         dept.name,
          code:         dept.code,
          hod:          dept.hod,
          studentCount: dept.student_count,
          date:         dept.date,
          status:       dept.status,
          description:  dept.description,
          dbId:         dept.id
        }));

        // Sync fresh data back to window cache
        _cache.departments = departments;
        _cache.loaded      = true;

        renderDepartments(departments);
        loadStats();
      } else {
        console.error('Error loading departments:', result.message);
        if (typeof showNotification === 'function') {
          showNotification('Error loading departments: ' + result.message, 'error');
        }
      }
    } catch (error) {
      console.error('Error fetching departments:', error);
      if (typeof showNotification === 'function') {
        showNotification('Error fetching departments. Please check console.', 'error');
      }
    }
  }

  // --- Load statistics from database ---

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
    const totalEl      = document.getElementById('totalDepartments');
    const activeEl     = document.getElementById('activeDepartments');
    const archivedEl   = document.getElementById('archivedDepartments');
    const activePctEl  = document.getElementById('activeDepartmentsPct');
    const archivedPctEl = document.getElementById('archivedDepartmentsPct');

    const total    = Number(stats.total)    || 0;
    const active   = Number(stats.active)   || 0;
    const archived = Number(stats.archived) || 0;

    animateCount(totalEl,    total);
    animateCount(activeEl,   active);
    animateCount(archivedEl, archived);

    const activePct   = total > 0 ? Math.round((active   / total) * 100) : 0;
    const archivedPct = total > 0 ? Math.round((archived / total) * 100) : 0;
    if (activePctEl)   activePctEl.textContent   = `${activePct}%`;
    if (archivedPctEl) archivedPctEl.textContent = `${archivedPct}%`;
  }

  async function loadStats() {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';
        
      const response = await fetch(`${apiPath}?action=stats`);
      const result = await response.json();
      
      if (result.status === 'success') {
        // Cache stats for instant restore on next page visit
        _cache.stats = result.data;
        applyStats(result.data);
      }
    } catch (error) {
      console.error('Error loading stats:', error);
    }
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

  async function downloadDepartmentsPDF() {
    if (!window.jspdf) {
      if (typeof showNotification === 'function') {
        showNotification('PDF library not loaded. Please refresh.', 'warning');
      } else {
        console.warn('PDF library not loaded. Please refresh the page.');
      }
      return;
    }
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const now = new Date();
    
    // --- Header Section ---
    const headerPath = (function(){ const p=window.location.pathname.split('/').filter(Boolean); const d=['app','api','includes','assets','public']; return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/app/assets/headers/header.png'; })();
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
    doc.text("DEPARTMENT LIST REPORT", 105, 38, { align: 'center' });

    doc.setFontSize(8);
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
    doc.text(`Total Records: ${departments.length}`, 14, 62);
    
    let startY = 67;

    const tableColumn = ["Code", "Department Name", "HOD", "Students", "Status"];
    const tableRows = departments.map(d => [
      d.code,
      d.name,
      d.hod,
      d.studentCount,
      d.status.charAt(0).toUpperCase() + d.status.slice(1)
    ]);

    doc.autoTable({
      head: [tableColumn],
      body: tableRows,
      startY: startY,
      theme: 'grid',
      styles: { fontSize: 8, cellPadding: 3 },
      headStyles: { fillColor: [245, 245, 245], textColor: [44, 62, 80], fontStyle: 'bold' },
      margin: { top: 60 }
    });

    doc.save(`Departments_${now.toISOString().slice(0, 10)}.pdf`);
  }

  async function downloadDepartmentsExcel() {
    const exportExcelBtn = document.getElementById('exportExcel');
    if (!exportExcelBtn) return;
    
    const originalText = exportExcelBtn.innerHTML;
    exportExcelBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>Preparing Excel...</span>";
    exportExcelBtn.disabled = true;

    try {
      const now = new Date();
      const headerPath = (function(){ const p=window.location.pathname.split('/').filter(Boolean); const d=['app','api','includes','assets','public']; return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/app/assets/headers/header.png'; })();
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
          <table width="900" style="width: 900px; border-collapse: collapse;">
            ${headerData ? `
            <tr height="100" style="height: 100px;">
              <td colspan="6" width="900" align="center" valign="middle" style="width: 900px; text-align: center; vertical-align: middle;">
                <center>
                  <div align="center" style="text-align: center;">
                    <p align="center" style="text-align: center; margin: 0; padding: 0;">
                      <img src="${headerData}" width="400" height="80" border="0" style="display: inline-block;">
                    </p>
                  </div>
                </center>
              </td>
            </tr>` : ''}
            <tr><td colspan="6" class="title" align="center" style="text-align: center;">DEPARTMENT LIST REPORT</td></tr>
            <tr><td colspan="6" class="subtitle" align="center" style="text-align: center;">Office of Student Affairs and Services</td></tr>
            <tr><td colspan="6" class="stats" align="center" style="text-align: center;">Generated on: ${now.toLocaleString()}</td></tr>
            <tr><td colspan="6" class="stats" align="center" style="text-align: center;">Exported by: ${getCurrentAdminName()}</td></tr>
            <tr><td colspan="6" class="stats" align="center" style="text-align: center;">Total Records: ${departments.length}</td></tr>
            <tr><td colspan="6" style="height: 20px;"></td></tr>
            <tr class="data-table">
              <th width="120" style="width: 120px; background-color: #e0e0e0; border: 0.5pt solid #000;">Code</th>
              <th width="300" style="width: 300px; background-color: #e0e0e0; border: 0.5pt solid #000;">Department Name</th>
              <th width="200" style="width: 200px; background-color: #e0e0e0; border: 0.5pt solid #000;">Head of Department</th>
              <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Students</th>
              <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Status</th>
            </tr>
      `;

      departments.forEach(d => {
        html += `
          <tr>
            <td>${d.code || ''}</td>
            <td>${d.name || ''}</td>
            <td>${d.hod || 'N/A'}</td>
            <td align="center">${d.studentCount || 0}</td>
            <td>${d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : 'Active'}</td>
          </tr>
        `;
      });

      html += `
          </table>
        </body>
        </html>
      `;

      const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
      const fileName = 'Departments_Export_' + now.toISOString().slice(0, 10) + '.xls';
      
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
    } catch (error) {
      console.error('Excel export error:', error);
      if (typeof showNotification === 'function') {
        showNotification('Failed to generate Excel document.', 'error');
      }
    } finally {
      exportExcelBtn.innerHTML = originalText;
      exportExcelBtn.disabled = false;
    }
  }

  async function downloadDepartmentsWord() {
    if (!window.docx) {
      if (typeof showNotification === 'function') {
        showNotification('DOCX library not loaded. Please refresh.', 'warning');
      }
      return;
    }

    const exportWordBtn = document.getElementById('exportWord');
    if (!exportWordBtn) return;
    
    const originalText = exportWordBtn.innerHTML;
    exportWordBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>Preparing Word...</span>";
    exportWordBtn.disabled = true;

    try {
      const { Document, Packer, Paragraph, Table, TableCell, TableRow, WidthType, HeadingLevel, TextRun, AlignmentType, ImageRun, VerticalAlign, BorderStyle } = window.docx;
      const now = new Date();
      
      const headerPath = (function(){ const p=window.location.pathname.split('/').filter(Boolean); const d=['app','api','includes','assets','public']; return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/app/assets/headers/header.png'; })();
      let headerImage = null;
      try {
        const response = await fetch(headerPath);
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
      
      const tableHeader = new TableRow({
        children: [
          "Code", "Department Name", "Head of Department", "Students", "Status"
        ].map(text => new TableCell({
          children: [new Paragraph({ 
            children: [new TextRun({ text, bold: true, size: 18, color: "FFFFFF" })],
            alignment: AlignmentType.CENTER
          })],
          shading: { fill: "2C3E50", val: "clear", color: "auto" },
          verticalAlign: VerticalAlign.CENTER,
          margins: { top: 80, bottom: 80, left: 80, right: 80 }
        })),
        tableHeader: true,
        height: { value: 600, rule: "atLeast" }
      });
      
      const tableRows = departments.map((d, index) => {
        const isEven = index % 2 === 0;
        const rowColor = isEven ? "FFFFFF" : "F8F9FA";
        
        return new TableRow({
          children: [
            d.code || '',
            d.name || '',
            d.hod || 'N/A',
            String(d.studentCount || 0),
            d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : 'Active'
          ].map(text => new TableCell({
            children: [new Paragraph({ 
              children: [new TextRun({ text, size: 18 })],
              alignment: AlignmentType.LEFT
            })],
            shading: { fill: rowColor, val: "clear", color: "auto" },
            verticalAlign: VerticalAlign.CENTER,
            margins: { top: 60, bottom: 60, left: 80, right: 80 }
          })),
          height: { value: 400, rule: "atLeast" }
        });
      });

      const children = [];
      
      if (headerImage) {
        children.push(headerImage);
      }
      
      children.push(
        new Paragraph({
          text: 'DEPARTMENT LIST REPORT',
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
              text: `Total Records: ${departments.length}`,
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
          }
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
                left: 720
              }
            }
          },
          children: children
        }]
      });
      
      const blob = await Packer.toBlob(doc);
      saveAs(blob, `Departments_Export_${now.toISOString().slice(0, 10)}.docx`);
      
      if (exportModal) exportModal.classList.remove('active');
      document.body.style.overflow = 'auto';
    } catch (error) {
      console.error('Word export error:', error);
      if (typeof showNotification === 'function') {
        showNotification('Failed to generate Word document.', 'error');
      }
    } finally {
      exportWordBtn.innerHTML = originalText;
      exportWordBtn.disabled = false;
    }
  }

  // Initial load - fetch from database
  loadDepartments('active');

  // --- Modal functions ---
  function openModal(viewId = null, editMode = false) {
    if (!modal) return;
    
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('departmentForm');
    const saveBtn = form ? form.querySelector('button[type="submit"]') : null;
    
    if (viewId && editMode) {
      // Edit mode — fields enabled, save button visible
      const span = modalTitle.querySelector('span');
      if (span) {
        span.textContent = 'Edit Department';
      } else {
        modalTitle.textContent = 'Edit Department';
      }
      const subtitle = document.querySelector('.dept-modal-subtitle');
      if (subtitle) subtitle.textContent = 'Update department information.';

      const dept = departments.find(d => String(d.id) === String(viewId));

      if (dept) {
        document.getElementById('deptName').value = dept.name || '';
        document.getElementById('deptCode').value = dept.code || '';
        document.getElementById('hodName').value = (dept.hod === 'N/A' || !dept.hod) ? '' : dept.hod;
        document.getElementById('deptDescription').value = dept.description || '';
        document.getElementById('deptStatus').value = dept.status || 'active';
      } else {
        console.error('❌ Could not find department with ID:', viewId);
      }

      // Enable all fields
      if (form) {
        form.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
      }
      if (saveBtn) {
        saveBtn.style.display = '';
        saveBtn.textContent = 'Update Department';
      }

      // Store IDs for the submit handler
      modal.dataset.editingId = viewId;
      const dept2 = departments.find(d => String(d.id) === String(viewId));
      if (dept2) modal.dataset.editingDbId = dept2.dbId;

    } else if (viewId) {
      // View mode — read-only
      const span = modalTitle.querySelector('span');
      if (span) {
        span.textContent = 'View Department';
      } else {
        modalTitle.textContent = 'View Department';
      }
      const subtitle = document.querySelector('.dept-modal-subtitle');
      if (subtitle) subtitle.textContent = 'Viewing department details and configuration.';
      
      const dept = departments.find(d => String(d.id) === String(viewId));
      
      if (dept) {
        document.getElementById('deptName').value = dept.name || '';
        document.getElementById('deptCode').value = dept.code || '';
        document.getElementById('hodName').value = (dept.hod === 'N/A' || !dept.hod) ? '' : dept.hod;
        document.getElementById('deptDescription').value = dept.description || '';
        document.getElementById('deptStatus').value = dept.status || 'active';
      } else {
        console.error('❌ Could not find department with ID:', viewId);
      }

      // Make all fields read-only
      if (form) {
        form.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('disabled', true));
      }
      if (saveBtn) saveBtn.style.display = 'none';

      delete modal.dataset.editingId;
      delete modal.dataset.editingDbId;
    } else {
      // Add mode
      const span = modalTitle.querySelector('span');
      if (span) {
        span.textContent = 'Add New Department';
      } else {
        modalTitle.textContent = 'Add New Department';
      }
      const subtitle = document.querySelector('.dept-modal-subtitle');
      if (subtitle) subtitle.textContent = 'Create and manage academic departments.';
      if (form) {
        form.reset();
        form.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
      }
      if (saveBtn) saveBtn.style.display = '';
      delete modal.dataset.editingId;
      delete modal.dataset.editingDbId;
    }
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    if (!modal) return;
    
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    const form = document.getElementById('departmentForm');
    if (form) {
      form.reset();
      form.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
      const saveBtn = form.querySelector('button[type="submit"]');
      if (saveBtn) {
        saveBtn.style.display = '';
        saveBtn.textContent = 'Save Department';
      }
    }
    delete modal.dataset.editingId;
  }

  // --- Actions (event delegation) ---
  function handleDeptActionClick(e) {
    const viewBtn = e.target.closest('.action-btn.view');
    const editBtn = e.target.closest('.action-btn.edit');
    const restoreBtn = e.target.closest('.action-btn.restore');
    const deleteBtn = e.target.closest('.action-btn.delete');

    if (viewBtn) {
      const id = viewBtn.dataset.id;
      openModal(id);
    }

    if (editBtn) {
      const id = editBtn.dataset.id;
      openModal(id, true);
    }

    if (restoreBtn) {
      const id = restoreBtn.dataset.id;
      const dept = departments.find(d => d.id === id);
      if (dept) {
        showModernAlert({
          title: 'Restore Department',
          message: `Restore department "${dept.name}"?`,
          icon: 'info',
          confirmText: 'Yes, Restore'
        }).then(confirmed => {
          if (confirmed) restoreDepartment(dept.dbId);
        });
      }
    }

    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      const dept = departments.find(d => String(d.id) === String(id));
      if (dept) {
        if (dept.status === 'archived') {
          showModernAlert({
            title: 'Permanent Delete',
            message: `Permanently delete department "${dept.name}"? This action cannot be undone.`,
            icon: 'error',
            confirmText: 'Delete Permanently'
          }).then(confirmed => {
            if (confirmed) deleteDepartment(dept.dbId);
          });
        } else {
          showModernAlert({
            title: 'Archive Department',
            message: `Archive department "${dept.name}"? This will move it to the archived list.`,
            icon: 'warning',
            confirmText: 'Yes, Archive'
          }).then(confirmed => {
            if (confirmed) deleteDepartment(dept.dbId);
          });
        }
      }
    }
  }
  tableBody.addEventListener('click', handleDeptActionClick);

  // --- Modal open/close + Save ---
  if (btnAddDepartment && modal) {
    // Add Department button
    btnAddDepartment.addEventListener('click', () => {
      openModal();
    });

    // View toggle buttons
    const deptViewBtns = document.querySelectorAll('.dept-view-btn');
    deptViewBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        viewMode = btn.dataset.view;
        localStorage.setItem('deptViewMode', viewMode);
        deptViewBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderDepartments();
      });
    });
    // Set initial active state
    deptViewBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.view === viewMode));

    // Delegate clicks on grid/list views
    const deptGridView = document.getElementById('deptGridView');
    const deptListView = document.getElementById('deptListView');
    if (deptGridView) deptGridView.addEventListener('click', handleDeptActionClick);
    if (deptListView) deptListView.addEventListener('click', handleDeptActionClick);

    // Export button
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
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

    // Export formats
    if (exportPDFBtn) {
      exportPDFBtn.addEventListener('click', async () => {
        await downloadDepartmentsPDF();
        if (exportModal) exportModal.classList.remove('active');
        document.body.style.overflow = 'auto';
      });
    }

    if (exportExcelBtn) {
      exportExcelBtn.addEventListener('click', async () => {
        await downloadDepartmentsExcel();
      });
    }

    if (exportWordBtn) {
      exportWordBtn.addEventListener('click', async () => {
        await downloadDepartmentsWord();
      });
    }

    // Add First Department button (empty state)
    if (btnAddFirstDept) {
      btnAddFirstDept.addEventListener('click', () => {
        openModal();
      });
    }

    // Close modal buttons
    if (closeBtn) {
      closeBtn.addEventListener('click', closeModal);
    }

    if (cancelBtn) {
      cancelBtn.addEventListener('click', closeModal);
    }

    if (modalOverlay) {
      modalOverlay.addEventListener('click', closeModal);
    }

    // Escape key to close modal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('active')) {
        closeModal();
      }
    });

    // Form submission
    if (departmentForm) {
      departmentForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const deptName = document.getElementById('deptName').value.trim();
        const deptCode = document.getElementById('deptCode').value.trim();
        const hodName = document.getElementById('hodName').value.trim();
        const deptDescription = document.getElementById('deptDescription').value.trim();
        const deptStatus = document.getElementById('deptStatus').value;
        
        if (!deptName || !deptCode) {
          if (typeof showNotification === 'function') {
            showNotification('Department name and code are required.', 'warning');
          } else {
            console.warn('Department name and code are required.');
          }
          return;
        }

        const editingDbId = modal.dataset.editingDbId;
        
        if (editingDbId) {
          // Update existing department
          updateDepartment(editingDbId, {
            deptName,
            deptCode,
            hodName,
            deptDescription,
            deptStatus
          });
        } else {
          // Add new department
          addDepartment({
            deptName,
            deptCode,
            hodName,
            deptDescription,
            deptStatus
          });
        }
      });
    }
  } else {
    console.warn('ℹ️ Department modal elements not found or not mounted (skipping modal wiring).');
  }

  // --- Search functionality ---
  if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        currentPage = 1; // Reset to first page on search
        loadDepartments(currentView);
      }, 300); // Debounce search
    });
  }

  // --- Archived button functionality ---
  const btnArchived = document.getElementById('btnArchived');

  // --- Filter functionality ---
  if (filterSelect) {
    filterSelect.addEventListener('change', () => {
      currentPage = 1; // Reset to first page on filter change
      const filterValue = filterSelect.value;
      if (filterValue === 'archived') {
        currentView = 'archived';
        loadDepartments('archived');
        if (btnArchived) btnArchived.classList.add('active');
      } else if (filterValue === 'active') {
        currentView = 'active';
        loadDepartments('active');
        if (btnArchived) btnArchived.classList.remove('active');
      } else {
        currentView = 'all';
        loadDepartments('all');
        if (btnArchived) btnArchived.classList.remove('active');
      }
    });
  }

  if (btnArchived) {
    btnArchived.addEventListener('click', () => {
      currentPage = 1; // Reset to first page on view change
      if (currentView === 'archived') {
        // Switch back to active view
        currentView = 'active';
        if (filterSelect) filterSelect.value = 'active';
        loadDepartments('active');
        btnArchived.classList.remove('active');
      } else {
        // Switch to archived view
        currentView = 'archived';
        if (filterSelect) filterSelect.value = 'archived';
        loadDepartments('archived');
        btnArchived.classList.add('active');
      }
    });
  }

  // --- API Functions ---
  async function addDepartment(data) {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';

      const formData = new FormData();
      Object.keys(data).forEach(key => formData.append(key, data[key]));

      const response = await fetch(`${apiPath}?action=add`, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      
      if (result.status === 'success') {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'success');
        } else {
          console.log(result.message);
        }
        closeModal();
        loadDepartments(currentView);
        loadStats();
      } else {
        if (typeof showNotification === 'function') {
          showNotification('Error: ' + result.message, 'error');
        } else {
          console.error('Error: ' + result.message);
        }
      }
    } catch (error) {
      console.error('Error adding department:', error);
      if (typeof showNotification === 'function') {
        showNotification('Error adding department. Please try again.', 'error');
      } else {
        console.error('Error adding department. Please try again.');
      }
    }
  }

  async function updateDepartment(dbId, data) {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';

      const formData = new FormData();
      formData.append('deptId', dbId);
      Object.keys(data).forEach(key => formData.append(key, data[key]));

      const response = await fetch(`${apiPath}?action=update`, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      
      if (result.status === 'success') {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'success');
        } else {
          console.log(result.message);
        }
        closeModal();
        loadDepartments(currentView);
        loadStats();
      } else {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'error');
        } else {
          console.error('Error: ' + result.message);
        }
      }
    } catch (error) {
      console.error('Error updating department:', error);
      if (typeof showNotification === 'function') {
        showNotification('Error updating department. Please try again.', 'error');
      } else {
        console.error('Error updating department. Please try again.');
      }
    }
  }

  async function deleteDepartment(dbId) {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';

      const response = await fetch(`${apiPath}?action=delete&id=${dbId}`, {
        method: 'GET'
      });

      const result = await response.json();
      
      if (result.status === 'success') {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'success');
        } else {
          console.log(result.message);
        }
        loadDepartments(currentView);
        loadStats();
      } else {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'error');
        } else {
          console.error('Error: ' + result.message);
        }
      }
    } catch (error) {
      console.error('Error deleting department:', error);
      if (typeof showNotification === 'function') {
        showNotification('Error deleting department. Please try again.', 'error');
      } else {
        console.error('Error deleting department. Please try again.');
      }
    }
  }

  async function archiveDepartment(dbId) {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';

      const response = await fetch(`${apiPath}?action=archive&id=${dbId}`, {
        method: 'GET'
      });

      const result = await response.json();
      
      if (result.status === 'success') {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'success');
        } else {
          console.log(result.message);
        }
        loadDepartments(currentView);
        loadStats();
      } else {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'error');
        } else {
          console.error('Error: ' + result.message);
        }
      }
    } catch (error) {
      console.error('Error archiving department:', error);
      if (typeof showNotification === 'function') {
        showNotification('Error archiving department. Please try again.', 'error');
      } else {
        console.error('Error archiving department. Please try again.');
      }
    }
  }

  async function restoreDepartment(dbId) {
    try {
      const _dp=window.location.pathname.split('/').filter(Boolean); const _dd=['app','api','includes','assets','public']; const apiPath=((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0])+'/api/departments.php';

      const response = await fetch(`${apiPath}?action=restore&id=${dbId}`, {
        method: 'GET'
      });

      const result = await response.json();
      
      if (result.status === 'success') {
        if (typeof showNotification === 'function') {
          showNotification(result.message, 'success');
        } else {
          console.log(result.message);
        }
        loadDepartments(currentView);
        loadStats();
      } else {
        if (typeof showNotification === 'function') {
          showNotification('Error: ' + result.message, 'error');
        } else {
          console.error('Error: ' + result.message);
        }
      }
    } catch (error) {
      console.error('Error restoring department:', error);
      if (typeof showNotification === 'function') {
        showNotification('Error restoring department. Please try again.', 'error');
      } else {
        console.error('Error restoring department. Please try again.');
      }
    }
  }

  // --- Sort functionality ---
  const sortHeaders = document.querySelectorAll('.sortable');
  sortHeaders.forEach(header => {
    header.addEventListener('click', function() {
      const sortBy = this.dataset.sort;
      sortDepartments(sortBy);
    });
  });

  function sortDepartments(sortBy) {
    departments.sort((a, b) => {
      switch(sortBy) {
        case 'name':
          return a.name.localeCompare(b.name);
        case 'date':
          return new Date(b.date) - new Date(a.date); // newest first
        case 'id':
        default:
          return a.id.localeCompare(b.id);
      }
    });
    renderDepartments();
  }

  console.log('✅ Department module ready!');
}

// Make function globally available
window.initDepartmentModule = initDepartmentModule;
