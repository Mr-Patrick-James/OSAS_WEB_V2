/**
 * OSAS Violation Slip Generator (PDF)
 * Uses jsPDF and AutoTable to generate a professional entrance slip.
 */

async function generateViolationSlipPDF(violationId) {
    console.log('📄 Generating Violation Slip PDF for ID:', violationId);
    
    if (typeof showLoadingOverlay === 'function') showLoadingOverlay('Preparing Entrance Slip...');
    
    try {
        // 1. Fetch Violation Data from API
        const response = await fetch(`${API_BASE}violations.php?action=get_slip_data&violation_id=${violationId}`);
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to fetch violation data');
        }
        
        const result = await response.json();
        const { violation, monthlyViolations, adminName } = result.data;
        
        if (!window.jspdf) {
            throw new Error('PDF library (jsPDF) not loaded. Please refresh the page.');
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        const now = new Date();

        // Helper function to load image
        const loadImage = (url) => {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.onload = () => resolve(img);
                img.onerror = (e) => reject(e);
                img.src = url;
            });
        };

        // --- Header ---
        const headerPath = `${API_BASE.replace('api/', '')}app/assets/headers/header.png`;
        try {
            const headerImg = await loadImage(headerPath);
            doc.addImage(headerImg, 'PNG', 35, 10, 140, 25);
        } catch (e) {
            console.warn('Could not load header image, using text fallback');
            doc.setFontSize(16);
            doc.setTextColor(44, 62, 80);
            doc.text("E-OSAS SYSTEM", 105, 15, { align: 'center' });
            doc.setFontSize(10);
            doc.text("Office of Student Affairs and Services", 105, 22, { align: 'center' });
        }

        doc.setFontSize(14);
        doc.setFont("helvetica", "bold");
        doc.setTextColor(0, 0, 0);
        doc.text("ENTRANCE SLIP", 105, 45, { align: 'center' });

        // --- Student Info ---
        doc.setFontSize(10);
        doc.setFont("helvetica", "normal");
        
        const startY = 55;
        const leftColX = 20;
        const rightColX = 110;
        const lineSpacing = 7;

        // Draw info with labels
        const drawField = (label, value, x, y, width) => {
            doc.setFont("helvetica", "bold");
            doc.text(label + ":", x, y);
            doc.setFont("helvetica", "normal");
            doc.text(String(value || 'N/A'), x + 35, y);
            doc.setDrawColor(200);
            doc.line(x + 35, y + 1, x + width, y + 1);
        };

        const courseYear = `${violation.section || 'N/A'} - ${violation.studentYearlevel || 'N/A'}`;

        drawField("Name", violation.studentName, leftColX, startY, 190);
        drawField("ID Number", violation.studentId, leftColX, startY + lineSpacing, 100);
        drawField("Course & Year", courseYear, rightColX, startY + lineSpacing, 190);

        // --- Checkboxes ---
        const vType = (violation.violationTypeLabel || '').toLowerCase();
        const vLevel = (violation.violationLevelLabel || '').toLowerCase();

        const drawCheckbox = (label, isChecked, x, y) => {
            doc.setDrawColor(0);
            doc.rect(x, y - 3, 4, 4); // Box
            if (isChecked) {
                doc.setFont("zapfdingbats");
                doc.text("4", x + 0.5, y - 0.2); // Checkmark
                doc.setFont("helvetica", "normal");
            }
            doc.text(label, x + 6, y);
        };

        let checkY = startY + lineSpacing * 3;
        doc.setFont("helvetica", "bold");
        doc.text("Violation Type:", leftColX, checkY);
        doc.setFont("helvetica", "normal");
        
        drawCheckbox("Improper Uniform", vType.includes('uniform'), leftColX + 35, checkY);
        drawCheckbox("Improper Footwear", vType.includes('foot') || vType.includes('shoe'), leftColX + 85, checkY);
        drawCheckbox("No ID Card", vType.includes('id'), leftColX + 135, checkY);

        checkY += lineSpacing;
        doc.setFont("helvetica", "bold");
        doc.text("Offense Level:", leftColX, checkY);
        doc.setFont("helvetica", "normal");
        
        drawCheckbox("1st Offense", vLevel.includes('1st'), leftColX + 35, checkY);
        drawCheckbox("2nd Offense", vLevel.includes('2nd'), leftColX + 85, checkY);
        drawCheckbox("3rd Offense", vLevel.includes('3rd'), leftColX + 135, checkY);

        // --- Monthly History Tables ---
        let tableY = checkY + 12;
        doc.setFont("helvetica", "bold");
        doc.text("Monthly Violation Record:", leftColX, tableY);
        tableY += 5;

        const tableColumn = ["Violation Type", "1st Date", "2nd Date", "3rd Date"];
        const tableRows = [];

        const categories = [
            { label: 'Improper Uniform', data: monthlyViolations['Improper Uniform'] },
            { label: 'Improper Footwear', data: monthlyViolations['Improper Foot Wear'] },
            { label: 'No ID Card', data: monthlyViolations['No ID'] }
        ];

        categories.forEach(cat => {
            const formatRecordDate = (vList, index) => {
                if (!vList || !vList[index]) return "-";
                return vList[index].dateReported;
            };
            tableRows.push([
                cat.label,
                formatRecordDate(cat.data, 0),
                formatRecordDate(cat.data, 1),
                formatRecordDate(cat.data, 2)
            ]);
        });

        doc.autoTable({
            head: [tableColumn],
            body: tableRows,
            startY: tableY,
            theme: 'grid',
            styles: { fontSize: 9, cellPadding: 3, halign: 'center' },
            headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' },
            columnStyles: { 0: { halign: 'left', cellWidth: 50 } }
        });

        let finalY = doc.lastAutoTable.finalY + 20;

        // --- Signatures ---
        doc.setFont("helvetica", "bold");
        doc.text("__________________________", leftColX + 10, finalY);
        doc.text("__________________________", rightColX + 10, finalY);
        
        doc.setFont("helvetica", "normal");
        doc.setFontSize(9);
        doc.text("Student Signature", leftColX + 22, finalY + 5);
        doc.text(adminName || "OSAS Administrator", rightColX + 22, finalY + 5);

        // --- Footer ---
        doc.setFontSize(8);
        doc.setTextColor(150);
        const footerText = `Generated on: ${now.toLocaleString()} | Violation ID: ${violation.id}`;
        doc.text(footerText, 105, 285, { align: 'center' });

        doc.save(`EntranceSlip_${violation.studentName.replace(/[^a-z0-9]/gi, '_')}.pdf`);
        if (typeof showNotification === 'function') showNotification('PDF generated successfully!', 'success');

    } catch (error) {
        console.error('❌ Error generating PDF:', error);
        if (typeof showNotification === 'function') showNotification('Failed to generate PDF: ' + error.message, 'error');
    } finally {
        if (typeof hideLoadingOverlay === 'function') hideLoadingOverlay();
    }
}
