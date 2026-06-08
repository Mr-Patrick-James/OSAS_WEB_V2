// sections.js - Database-integrated version
function initSectionsModule() {
    console.log('🛠 Sections module initializing...');
    
    try {
        // Elements
        const tableBody = document.getElementById('sectionsTableBody');
        const btnAddSection = document.getElementById('btnAddSection');
        const btnAddFirstSection = document.getElementById('btnAddFirstSection');
        const modal = document.getElementById('sectionsModal');
        const modalOverlay = document.getElementById('sectionsModalOverlay');
        const closeBtn = document.getElementById('closeSectionsModal');
        const cancelBtn = document.getElementById('cancelSectionsModal');
        const sectionsForm = document.getElementById('sectionsForm');
        const searchInput = document.getElementById('searchSection');
        const filterSelect = document.getElementById('sectionFilterSelect');
        const exportBtn = document.getElementById('btnExportSections');
        const exportModal = document.getElementById('ExportSectionsModal');
        const closeExportBtn = document.getElementById('closeExportModal');
        const exportModalOverlay = document.getElementById('ExportModalOverlay');
        const exportPDFBtn = document.getElementById('exportPDF');
        const exportExcelBtn = document.getElementById('exportExcel');
        const exportWordBtn = document.getElementById('exportWord');

        // Check for essential elements
        if (!tableBody) {
            console.error('❗ #sectionsTableBody not found');
            return;
        }

        if (!modal) {
            console.warn('⚠️ #sectionsModal not found');
        }

        // Use window-level cache so data persists when switching pages and back
        // This prevents re-fetching every time the sections page is visited
        if (!window._sectionsCache) window._sectionsCache = { sections: [], allSections: [], stats: null, loaded: false };
        const _cache = window._sectionsCache;

        let sections    = _cache.sections;
        let allSections = _cache.allSections;
        let viewMode    = localStorage.getItem('sectViewMode') || 'list'; // 'table', 'grid', 'list'

        // API path — works on AWS root AND local subfolder
        const _p = window.location.pathname.split('/').filter(Boolean);
        const _d = ['app','api','includes','assets','public'];
        let apiBase = ((_p.length===0||_d.includes(_p[0]))?'':'/'+_p[0]) + '/api/sections.php';

        let currentView = 'active';
        let currentPage = 1;
        let itemsPerPage = 10;
        let totalRecords = 0;
        let totalPages = 0;

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

        function renderPagination() {
            const paginationContainer = document.querySelector('.sections-pagination');
            if (!paginationContainer) return;

            let html = '';
            html += `<button class="sections-pagination-btn ${currentPage === 1 ? 'disabled' : ''}" ${currentPage === 1 ? 'disabled' : ''} onclick="window.changeSectionsPage(${currentPage - 1})"><i class='bx bx-chevron-left'></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    html += `<button class="sections-pagination-btn ${i === currentPage ? 'active' : ''}" onclick="window.changeSectionsPage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    html += `<span class="sections-pagination-ellipsis">...</span>`;
                }
            }

            html += `<button class="sections-pagination-btn ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} onclick="window.changeSectionsPage(${currentPage + 1})"><i class='bx bx-chevron-right'></i></button>`;
            paginationContainer.innerHTML = html;
        }

        window.changeSectionsPage = function(page) {
            if (page < 1 || page > totalPages || page === currentPage) return;
            currentPage = page;
            fetchSections();
        };

        async function fetchSections() {
            try {
                const filter = currentView === 'archived' ? 'archived' : 'active';
                const search = searchInput ? searchInput.value : '';
                
                let url = `${apiBase}?action=get&filter=${filter}&page=${currentPage}&limit=${itemsPerPage}`;
                if (search) {
                    url += `&search=${encodeURIComponent(search)}`;
                }

                // Cache-hit fast render: show cached data immediately while fresh fetch runs
                if (_cache.loaded && _cache.sections.length > 0) {
                    sections    = _cache.sections;
                    allSections = _cache.allSections;
                    renderSections();
                    renderPagination();
                    if (_cache.stats) updateStatsFromData(_cache.stats);
                }

                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response was:', text);
                    throw new Error('Invalid JSON response from server');
                }

                if (data.status === 'success') {
                    const payload = data.data;
                    if (Array.isArray(payload)) {
                        allSections  = payload;
                        totalRecords = payload.length;
                        totalPages   = Math.ceil(totalRecords / itemsPerPage);
                        const start  = (currentPage - 1) * itemsPerPage;
                        const end    = start + itemsPerPage;
                        sections     = payload.slice(start, end);
                    } else if (payload && Array.isArray(payload.sections)) {
                        sections     = payload.sections;
                        totalRecords = typeof payload.total       === 'number' ? payload.total       : sections.length;
                        totalPages   = typeof payload.total_pages === 'number' ? payload.total_pages : Math.ceil(totalRecords / itemsPerPage);
                        currentPage  = typeof payload.page        === 'number' ? payload.page        : currentPage;
                        allSections  = sections;
                    } else {
                        console.error('Unexpected API data shape:', payload);
                        showError('Unexpected response from server while loading sections.');
                        return;
                    }

                    // Sync fresh data back to window cache
                    _cache.sections    = sections;
                    _cache.allSections = allSections;
                    _cache.loaded      = true;

                    renderSections();
                    updateStats();
                    renderPagination();
                } else {
                    console.error('Error fetching sections:', data.message);
                    showError('Failed to load sections: ' + data.message);
                }
            } catch (error) {
                console.error('Error fetching sections:', error);
                console.error('Full error details:', error.message, error.stack);
                showError('Failed to load sections. Please check your connection and console for details.');
            }
        }

        async function fetchStats() {
            try {
                const response = await fetch(`${apiBase}?action=stats`);
                const data = await response.json();

                if (data.status === 'success') {
                    // Cache stats for instant restore on next page visit
                    _cache.stats = data.data;
                    updateStatsFromData(data.data);
                }
            } catch (error) {
                console.error('Error fetching stats:', error);
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

        function updateStatsFromData(stats) {
            const totalEl      = document.getElementById('totalSections');
            const activeEl     = document.getElementById('activeSections');
            const archivedEl   = document.getElementById('archivedSections');
            const activePctEl  = document.getElementById('activeSectionsPct');
            const archivedPctEl = document.getElementById('archivedSectionsPct');

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

        async function addSection(formData) {
            const submitBtn = document.querySelector('#sectionsForm button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            try {
                const response = await fetch(`${apiBase}?action=add`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.status === 'success') {
                    showSuccess(data.message || 'Section added successfully!');
                    await fetchSections();
                    await fetchStats();
                    closeModal();
                } else {
                    showError(data.message || 'Failed to add section');
                }
            } catch (error) {
                console.error('Error adding section:', error);
                showError('Failed to add section. Please try again.');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        }

        async function updateSection(sectionId, formData) {
            try {
                formData.append('sectionId', sectionId);
                const response = await fetch(`${apiBase}?action=update`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.status === 'success') {
                    showSuccess(data.message || 'Section updated successfully!');
                    await fetchSections();
                    await fetchStats();
                    closeModal();
                } else {
                    showError(data.message || 'Failed to update section');
                }
            } catch (error) {
                console.error('Error updating section:', error);
                showError('Failed to update section. Please try again.');
            }
        }

        async function deleteSection(sectionId) {
            try {
                const response = await fetch(`${apiBase}?action=delete&id=${sectionId}`, {
                    method: 'GET'
                });
                const data = await response.json();

                if (data.status === 'success') {
                    showSuccess(data.message || 'Section permanently deleted!');
                    await fetchSections();
                    await fetchStats();
                } else {
                    showError(data.message || 'Failed to delete section');
                }
            } catch (error) {
                console.error('Error deleting section:', error);
                showError('Failed to delete section. Please try again.');
            }
        }

        async function archiveSection(sectionId) {
            try {
                const response = await fetch(`${apiBase}?action=archive&id=${sectionId}`, {
                    method: 'GET'
                });
                const data = await response.json();

                if (data.status === 'success') {
                    showSuccess(data.message || 'Section archived successfully!');
                    await fetchSections();
                    await fetchStats();
                } else {
                    showError(data.message || 'Failed to archive section');
                }
            } catch (error) {
                console.error('Error archiving section:', error);
                showError('Failed to archive section. Please try again.');
            }
        }

        async function restoreSection(sectionId) {
            try {
                const response = await fetch(`${apiBase}?action=restore&id=${sectionId}`, {
                    method: 'GET'
                });
                const data = await response.json();

                if (data.status === 'success') {
                    showSuccess(data.message || 'Section restored successfully!');
                    await fetchSections();
                    await fetchStats();
                } else {
                    showError(data.message || 'Failed to restore section');
                }
            } catch (error) {
                console.error('Error restoring section:', error);
                showError('Failed to restore section. Please try again.');
            }
        }

        async function loadDepartments() {
            try {
                const _dp = window.location.pathname.split('/').filter(Boolean);
                const _dd = ['app','api','includes','assets','public'];
                const deptApi = ((_dp.length===0||_dd.includes(_dp[0]))?'':'/'+_dp[0]) + '/api/departments.php';

                const response = await fetch(deptApi);
                const data = await response.json();

                if (data.status === 'success') {
                    const select = document.getElementById('sectionDepartment');
                    if (select) {
                        // Clear existing options except the first one
                        const firstOption = select.querySelector('option[value=""]');
                        select.innerHTML = '';
                        if (firstOption) {
                            select.appendChild(firstOption);
                        }
                        
                        // Add departments from API
                        // Use correct mapping for department data
                        const depts = data.data.departments || data.data;
                        if (Array.isArray(depts)) {
                            depts.forEach(dept => {
                                const option = document.createElement('option');
                                option.value = dept.id || dept.dbId;
                                option.textContent = dept.name || dept.department_name;
                                select.appendChild(option);
                            });
                        }
                    }
                }
            } catch (error) {
                console.error('Error loading departments:', error);
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

        async function downloadSectionsPDF() {
            if (!window.jspdf) {
                if (typeof showNotification === 'function') {
                    showNotification('PDF library not loaded. Please refresh.', 'warning');
                } else {
                    alert('PDF library not loaded. Please refresh the page.');
                }
                return;
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const now = new Date();
            
            // --- Header Section ---
            const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
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
            doc.text("SECTION LIST REPORT", 105, 38, { align: 'center' });

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
            doc.text(`Total Records: ${sections.length}`, 14, 62);
            
            let startY = 67;

            const tableColumn = ["Section Name", "Department", "Academic Year", "Students", "Status"];
            const tableRows = sections.map(s => [
                s.name,
                s.department,
                s.academic_year,
                s.student_count,
                s.status ? s.status.charAt(0).toUpperCase() + s.status.slice(1) : 'Active'
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

            doc.save(`Sections_${now.toISOString().slice(0, 10)}.pdf`);
        }

        async function downloadSectionsExcel() {
            const exportExcelBtn = document.getElementById('exportExcel');
            if (!exportExcelBtn) return;
            
            const originalText = exportExcelBtn.innerHTML;
            exportExcelBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i><span>Preparing Excel...</span>";
            exportExcelBtn.disabled = true;

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
                            <tr><td colspan="6" class="title" align="center" style="text-align: center;">SECTION LIST REPORT</td></tr>
                            <tr><td colspan="6" class="subtitle" align="center" style="text-align: center;">Office of Student Affairs and Services</td></tr>
                            <tr><td colspan="6" class="stats" align="center" style="text-align: center;">Generated on: ${now.toLocaleString()}</td></tr>
                            <tr><td colspan="6" class="stats" align="center" style="text-align: center;">Exported by: ${getCurrentAdminName()}</td></tr>
                            <tr><td colspan="6" class="stats" align="center" style="text-align: center;">Total Records: ${sections.length}</td></tr>
                            <tr><td colspan="6" style="height: 20px;"></td></tr>
                            <tr class="data-table">
                                <th width="200" style="width: 200px; background-color: #e0e0e0; border: 0.5pt solid #000;">Section Name</th>
                                <th width="250" style="width: 250px; background-color: #e0e0e0; border: 0.5pt solid #000;">Department</th>
                                <th width="150" style="width: 150px; background-color: #e0e0e0; border: 0.5pt solid #000;">Academic Year</th>
                                <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Students</th>
                                <th width="100" style="width: 100px; background-color: #e0e0e0; border: 0.5pt solid #000;">Status</th>
                            </tr>
                `;

                sections.forEach(s => {
                    html += `
                        <tr>
                            <td>${s.name || ''}</td>
                            <td>${s.department || ''}</td>
                            <td>${s.academic_year || ''}</td>
                            <td align="center">${s.student_count || 0}</td>
                            <td>${s.status ? s.status.charAt(0).toUpperCase() + s.status.slice(1) : 'Active'}</td>
                        </tr>
                    `;
                });

                html += `
                        </table>
                    </body>
                    </html>
                `;

                const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
                const fileName = 'Sections_Export_' + now.toISOString().slice(0, 10) + '.xls';
                
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

        async function downloadSectionsWord() {
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
                
                const headerPath = (getProjectRoot() + '/app/assets/headers/header.png');
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
                        "Section Name", "Department", "Academic Year", "Students", "Status"
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
                
                const tableRows = sections.map((s, index) => {
                    const isEven = index % 2 === 0;
                    const rowColor = isEven ? "FFFFFF" : "F8F9FA";
                    
                    return new TableRow({
                        children: [
                            s.name || '',
                            s.department || '',
                            s.academic_year || '',
                            String(s.student_count || 0),
                            s.status ? s.status.charAt(0).toUpperCase() + s.status.slice(1) : 'Active'
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
                        text: 'SECTION LIST REPORT',
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
                                text: `Total Records: ${sections.length}`,
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
                saveAs(blob, `Sections_Export_${now.toISOString().slice(0, 10)}.docx`);
                
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

        // --- Render function ---
        function renderSections() {
            const tableBody = document.getElementById('sectionsTableBody');
            if (!tableBody) return;
            const list = Array.isArray(sections) ? sections : [];
            if (list.length === 0) {
                tableBody.innerHTML = '';
                const emptyState = document.getElementById('sectionsEmptyState');
                if (emptyState) emptyState.style.display = 'flex';
                updateCounts([]);
                renderPagination();
                return;
            }

            const emptyState = document.getElementById('sectionsEmptyState');
            if (emptyState) emptyState.style.display = 'none';

            // Show/hide view containers
            const tableViewEl = document.getElementById('sectionsPrintArea');
            const gridViewEl  = document.getElementById('sectGridView');
            const listViewEl  = document.getElementById('sectListView');
            if (tableViewEl) tableViewEl.style.display = viewMode === 'table' ? '' : 'none';
            if (gridViewEl)  gridViewEl.style.display  = viewMode === 'grid'  ? '' : 'none';
            if (listViewEl)  listViewEl.style.display  = viewMode === 'list'  ? '' : 'none';

            // ── Helper: action buttons ──────────────────────────
            function actionBtns(s) {
                return `
                    <button class="sections-action-btn view" data-id="${s.id}" title="View"><i class='bx bx-show'></i></button>
                    ${s.status === 'archived' ? `<button class="sections-action-btn restore" data-id="${s.id}" title="Restore"><i class='bx bx-reset'></i></button>` : ''}
                `;
            }

            // ── TABLE VIEW ──────────────────────────────────────
            tableBody.innerHTML = list.map(s => `
                <tr data-id="${s.id}">
                    <td class="section-name" data-label="Section Name">
                        <div class="section-name-wrapper">
                            <div class="section-icon"><i class='bx bx-group'></i></div>
                            <div>
                                <strong>${escapeHtml(s.name)}</strong>
                                <small class="section-year">${escapeHtml(s.academic_year || '')}</small>
                            </div>
                        </div>
                    </td>
                    <td class="department-name" data-label="Department">${escapeHtml(s.department || 'N/A')}</td>
                    <td class="student-count" data-label="Students">${s.student_count || 0}</td>
                    <td class="date-created" data-label="Date Created">${s.date || ''}</td>
                    <td data-label="Status">
                        <span class="sections-status-badge ${s.status || 'active'}">${(s.status || 'active') === 'active' ? 'Active' : 'Archived'}</span>
                    </td>
                    <td data-label="Actions">
                        <div class="sections-action-buttons">${actionBtns(s)}</div>
                    </td>
                </tr>
            `).join('');

            // ── GRID / CARD VIEW ────────────────────────────────
            const gridBody = document.getElementById('sectGridBody');
            if (gridBody) {
                gridBody.innerHTML = list.map(s => `
                    <div class="sect-card" data-id="${s.id}">
                        <div class="sect-card-top"></div>
                        <div class="sect-card-body">
                            <div class="sect-card-icon-row">
                                <div class="sect-card-icon"><i class='bx bx-group'></i></div>
                                <div>
                                    <p class="sect-card-name">${escapeHtml(s.name)}</p>
                                    <p class="sect-card-year">${escapeHtml(s.academic_year || '')}</p>
                                </div>
                            </div>
                            <div class="sect-card-divider"></div>
                            <div class="sect-card-meta">
                                <div class="sect-card-meta-row">
                                    <span class="sect-card-meta-label">Dept</span>
                                    <span class="sect-card-meta-value" style="text-align:right;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(s.department || 'N/A')}</span>
                                </div>
                                <div class="sect-card-meta-row">
                                    <span class="sect-card-meta-label">Students</span>
                                    <span class="sect-card-meta-value">${s.student_count || 0}</span>
                                </div>
                            </div>
                        </div>
                        <div class="sect-card-footer">
                            <span class="sections-status-badge ${s.status || 'active'}" style="font-size:9px;">${(s.status || 'active') === 'active' ? 'Active' : 'Archived'}</span>
                            <div class="sect-card-actions">${actionBtns(s)}</div>
                        </div>
                    </div>
                `).join('');
            }

            // ── LIST VIEW ───────────────────────────────────────
            const listBody = document.getElementById('sectListBody');
            if (listBody) {
                listBody.innerHTML = list.map(s => `
                    <div class="sect-list-item" data-id="${s.id}">
                        <div class="sect-list-top">
                            <div class="sect-list-icon"><i class='bx bx-group'></i></div>
                            <div class="sect-list-name-block">
                                <span class="sect-list-name">${escapeHtml(s.name)}</span>
                                <span class="sect-list-year">${escapeHtml(s.academic_year || '')}</span>
                            </div>
                            <div class="sect-list-actions">${actionBtns(s)}</div>
                        </div>
                        <div class="sect-list-badges">
                            <span style="font-size:9px;color:var(--dark-grey);display:flex;align-items:center;gap:3px;">
                                <i class='bx bx-buildings'></i>${escapeHtml(s.department || 'N/A')}
                            </span>
                            <span style="font-size:9px;color:var(--dark-grey);display:flex;align-items:center;gap:3px;">
                                <i class='bx bx-user'></i>${s.student_count || 0} students
                            </span>
                            <span class="sections-status-badge ${s.status || 'active'}" style="font-size:9px;">${(s.status || 'active') === 'active' ? 'Active' : 'Archived'}</span>
                        </div>
                    </div>
                `).join('');
            }

            updateCounts(list);
            renderPagination();
        }

        function updateStats() {
            fetchStats();
        }

        function updateCounts(filteredSections) {
            const showingEl    = document.getElementById('showingSectionsCount');
            const totalCountEl = document.getElementById('totalSectionsCount');
            
            if (showingEl)    showingEl.textContent    = filteredSections.length;
            if (totalCountEl) totalCountEl.textContent = totalRecords;
        }

        // --- Modal functions ---
        function openModal(viewId = null) {
            if (!modal) return;
            
            const modalTitle = document.getElementById('sectionsModalTitle');
            const form = document.getElementById('sectionsForm');
            const saveBtn = form ? form.querySelector('button[type="submit"]') : null;
            
            if (viewId) {
                // View mode — read-only
                const span = modalTitle.querySelector('span');
                if (span) {
                    span.textContent = 'View Section';
                } else {
                    modalTitle.textContent = 'View Section';
                }
                const subtitle = document.querySelector('.sections-modal-subtitle');
                if (subtitle) subtitle.textContent = 'Viewing section details and configuration.';
                
                const section = sections.find(s => String(s.id) === String(viewId));
                
                if (section) {
                    document.getElementById('sectionName').value = section.name || '';
                    document.getElementById('sectionCode').value = section.code || '';
                    document.getElementById('sectionDepartment').value = section.department_id || '';
                    document.getElementById('academicYear').value = section.academic_year || '';
                    document.getElementById('sectionStatus').value = section.status || 'active';
                } else {
                    console.error('❌ Could not find section with ID:', viewId);
                }

                // Make all fields read-only
                if (form) {
                    form.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('disabled', true));
                }
                if (saveBtn) saveBtn.style.display = 'none';

                delete modal.dataset.editingId;
            } else {
                // Add mode
                const span = modalTitle.querySelector('span');
                if (span) {
                    span.textContent = 'Add New Section';
                } else {
                    modalTitle.textContent = 'Add New Section';
                }
                const subtitle = document.querySelector('.sections-modal-subtitle');
                if (subtitle) subtitle.textContent = 'Create and manage class sections.';
                if (form) {
                    form.reset();
                    form.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
                }
                if (saveBtn) saveBtn.style.display = '';
                delete modal.dataset.editingId;
            }
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            if (!modal) return;
            
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            const form = document.getElementById('sectionsForm');
            if (form) {
                form.reset();
                form.querySelectorAll('input, textarea, select').forEach(el => el.removeAttribute('disabled'));
                const saveBtn = form.querySelector('button[type="submit"]');
                if (saveBtn) saveBtn.style.display = '';
            }
            delete modal.dataset.editingId;
        }

        // --- Event handlers ---
        function handleTableClick(e) {
            const viewBtn = e.target.closest('.sections-action-btn.view');
            const restoreBtn = e.target.closest('.sections-action-btn.restore');
            const deleteBtn = e.target.closest('.sections-action-btn.delete');

            if (viewBtn) {
                const id = viewBtn.dataset.id;
                openModal(id);
            }

            if (restoreBtn) {
                const id = restoreBtn.dataset.id;
                const section = sections.find(s => String(s.id) === String(id));
                if (section) {
                    showModernAlert({
                        title: 'Restore Section',
                        message: `Restore section "${section.name}"?`,
                        icon: 'info',
                        confirmText: 'Yes, Restore'
                    }).then(confirmed => {
                        if (confirmed) restoreSection(id);
                    });
                }
            }

            if (deleteBtn) {
                const id = deleteBtn.dataset.id;
                const section = sections.find(s => String(s.id) === String(id));
                if (section) {
                    if (section.status === 'archived') {
                        showModernAlert({
                            title: 'Permanent Delete',
                            message: `Permanently delete section "${section.name}"? This action cannot be undone.`,
                            icon: 'danger',
                            confirmText: 'Delete Permanently'
                        }).then(confirmed => {
                            if (confirmed) deleteSection(id);
                        });
                    } else {
                        showModernAlert({
                            title: 'Archive Section',
                            message: `Archive section "${section.name}"? This will move it to archived.`,
                            icon: 'warning',
                            confirmText: 'Yes, Archive'
                        }).then(confirmed => {
                            if (confirmed) archiveSection(id);
                        });
                    }
                }
            }
        }

        // --- Utility functions ---
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showSuccess(message) {
            if (typeof showNotification === 'function') {
                showNotification(message, 'success');
            } else {
                console.log(message);
            }
        }

        function showError(message) {
            if (typeof showNotification === 'function') {
                showNotification(message, 'error');
            } else {
                console.error(message);
            }
        }

        // --- Initialize ---
        async function initialize() {
            // Load departments for dropdown
            await loadDepartments();

            // Set default view to active (hide archived by default)
            currentView = 'active';
            if (filterSelect) {
                filterSelect.value = 'active';
            }

            // Initial load - only active sections
            await fetchSections();

            // Event listeners for table
            tableBody.addEventListener('click', handleTableClick);

            // View toggle buttons
            const sectViewBtns = document.querySelectorAll('.sect-view-btn');
            sectViewBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    viewMode = btn.dataset.view;
                    localStorage.setItem('sectViewMode', viewMode);
                    sectViewBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    renderSections();
                });
            });
            sectViewBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.view === viewMode));

            // Delegate clicks on grid/list views to the same handler
            const sectGridView = document.getElementById('sectGridView');
            const sectListView = document.getElementById('sectListView');
            if (sectGridView) sectGridView.addEventListener('click', handleTableClick);
            if (sectListView) sectListView.addEventListener('click', handleTableClick);

            // Add Section button
            if (btnAddSection) {
                btnAddSection.addEventListener('click', () => openModal());
            }

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
                    await downloadSectionsPDF();
                    if (exportModal) exportModal.classList.remove('active');
                    document.body.style.overflow = 'auto';
                });
            }

            if (exportExcelBtn) {
                exportExcelBtn.addEventListener('click', async () => {
                    await downloadSectionsExcel();
                });
            }

            if (exportWordBtn) {
                exportWordBtn.addEventListener('click', async () => {
                    await downloadSectionsWord();
                });
            }

            // Add First Section button
            if (btnAddFirstSection) {
                btnAddFirstSection.addEventListener('click', () => openModal());
            }

            // Close modal
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (modalOverlay) modalOverlay.addEventListener('click', closeModal);

            // Escape key to close modal
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
                    closeModal();
                }
            });

            // Form submission
            if (sectionsForm) {
                sectionsForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const sectionName = document.getElementById('sectionName').value.trim();
                    const sectionCode = document.getElementById('sectionCode').value.trim();
                    const sectionDepartment = document.getElementById('sectionDepartment').value;
                    const academicYear = document.getElementById('academicYear').value.trim();
                    const sectionStatus = document.getElementById('sectionStatus').value;
                    
                    if (!sectionName || !sectionCode || !sectionDepartment || !academicYear) {
                        showError('Please fill in all required fields.');
                        return;
                    }

                    const editingId = modal.dataset.editingId;
                    const formData = new FormData();
                    formData.append('sectionName', sectionName);
                    formData.append('sectionCode', sectionCode);
                    formData.append('sectionDepartment', sectionDepartment);
                    formData.append('academicYear', academicYear);
                    formData.append('sectionStatus', sectionStatus);
                    
                    if (editingId) {
                        await updateSection(editingId, formData);
                    } else {
                        await addSection(formData);
                    }
                });
            }

            // Search functionality
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        currentPage = 1;
                        fetchSections();
                    }, 500); // Debounce search
                });
            }

            // Filter functionality - hide archived by default
            if (filterSelect) {
                // Set default to active
                filterSelect.value = 'active';
                currentView = 'active';
                
                filterSelect.addEventListener('change', () => {
                    if (filterSelect.value === 'archived') {
                        currentView = 'archived';
                    } else {
                        currentView = 'active';
                    }
                    currentPage = 1;
                    fetchSections();
                    // Update archived button state
                    const btnArchived = document.getElementById('btnArchivedSections');
                    if (btnArchived) {
                        if (currentView === 'archived') {
                            btnArchived.classList.add('active');
                        } else {
                            btnArchived.classList.remove('active');
                        }
                    }
                });
            }

            // Archived button functionality
            const btnArchived = document.getElementById('btnArchivedSections');
            if (btnArchived) {
                btnArchived.addEventListener('click', () => {
                    if (currentView === 'archived') {
                        // Switch back to active view
                        currentView = 'active';
                        if (filterSelect) filterSelect.value = 'active';
                        btnArchived.classList.remove('active');
                    } else {
                        // Switch to archived view
                        currentView = 'archived';
                        if (filterSelect) filterSelect.value = 'archived';
                        btnArchived.classList.add('active');
                    }
                    fetchSections();
                });
            }

            // Sort functionality
            const sortHeaders = document.querySelectorAll('.sections-sortable');
            sortHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const sortBy = this.dataset.sort;
                    sortSections(sortBy);
                });
            });

            function sortSections(sortBy) {
                sections.sort((a, b) => {
                    switch(sortBy) {
                        case 'name':
                            return a.name.localeCompare(b.name);
                        case 'date':
                            return new Date(b.date) - new Date(a.date);
                        case 'id':
                        default:
                            return (a.section_id || '').localeCompare(b.section_id || '');
                    }
                });
                renderSections();
            }

            console.log('✅ Sections module initialized successfully!');
        }

        // Start initialization
        initialize();

    } catch (error) {
        console.error('❌ Error initializing sections module:', error);
    }
}

// Make function globally available
window.initSectionsModule = initSectionsModule;

