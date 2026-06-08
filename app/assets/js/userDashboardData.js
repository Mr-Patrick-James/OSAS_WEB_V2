/**
 * User Dashboard Data Loader
 * Connects user dashboard features to database APIs
 */

const USER_API_BASE = (function() {
    const p = window.location.pathname.split('/').filter(Boolean);
    const d = ['app','api','includes','assets','public'];
    return ((p.length===0||d.includes(p[0]))?'':'/'+p[0])+'/api/';
})();

console.log('🔗 User API Base Path:', USER_API_BASE);

class UserDashboardData {
    constructor() {
        this.userId = null;
        this.studentId = null;
        this.stats = {
            activeViolations: 0,
            totalViolations: 0,
            resolvedViolations: 0,
            daysClean: 0,
            violationTypes: {}
        };
        this.violations = [];
        this.announcements = [];
        this.dashcontents = [];
    }

    /**
     * Initialize dashboard
     */
    init() {
        // Get student ID directly from HTML attribute
        const mainContent = document.getElementById('main-content');
        if (!mainContent) {
            console.error('⚠️ Main content container not found');
            return false;
        }

        this.studentId = mainContent.dataset.studentId;
        if (!this.studentId) {
            console.error('⚠️ Student ID not found in main-content data attribute');
            return false;
        }

        console.log('✅ Student ID detected:', this.studentId);
        return true;
    }

    async loadAllData() {
        if (!this.init()) {
            this.displayNoStudentIdMessage();
            return;
        }

        console.log('🔄 Loading all dashboard data...');
        
        try {
            await Promise.all([
                this.loadUserViolations(),
                this.loadAnnouncements(),
                this.loadDashcontents()
            ]);

            console.log('✅ All data loaded, updating display...');
            this.updateDashboardDisplay();
        } catch (error) {
            console.error('❌ Error loading dashboard data:', error);
            // Still try to update display even if there's an error
            this.updateDashboardDisplay();
        }
    }

    displayNoStudentIdMessage() {
        const container = document.getElementById('recentViolationsList') ||
                          document.querySelector('.sd-violations-list');
        if (container) {
            container.innerHTML = `
                <div class="sd-violations-empty" style="color:red;">
                    Student ID not found. Please login again.
                </div>
            `;
        }
    }

    async loadUserViolations() {
        if (!this.studentId) {
            console.warn('⚠️ Student ID not available for loading violations');
            return;
        }

        try {
            const url = `${USER_API_BASE}violations.php`;
            console.log('🔄 Loading violations from:', url);
            
            // Fetch active + archived in parallel
            const [resActive, resArchived] = await Promise.all([
                fetch(url),
                fetch(`${url}?is_archived=1`)
            ]);

            if (!resActive.ok) throw new Error(`HTTP ${resActive.status}`);

            const dataActive = await resActive.json();
            if (dataActive.status === 'error') throw new Error(dataActive.message || 'Error loading violations');

            this.violations = dataActive.violations || dataActive.data || [];
            
            // Load archived violations
            let archivedViolations = [];
            if (resArchived.ok) {
                const dataArchived = await resArchived.json();
                if (dataArchived.status === 'success') {
                    archivedViolations = dataArchived.violations || dataArchived.data || [];
                }
            }

            // All violations (active + archived) for all-time stats
            this.allViolations = [...this.violations, ...archivedViolations];
            console.log('✅ Loaded', this.violations.length, 'active +', archivedViolations.length, 'archived violations');
            
            // SYNC with userViolations.js so viewViolationDetails works
            window.userViolations = this.violations;
            window.allUserViolations = this.allViolations;
            // Also update the let-declared variables in userViolations.js
            if (typeof userViolations !== 'undefined') {
                userViolations = this.violations;
            }
            if (typeof allUserViolations !== 'undefined') {
                allUserViolations = this.allViolations;
            }

            this.calculateViolationStats();
        } catch (error) {
            console.error('❌ Error loading violations:', error);
            this.violations = [];
            this.allViolations = [];
            this.calculateViolationStats();
        }
    }

    calculateViolationStats() {
        // All-time total (active + archived)
        this.stats.totalViolations = this.allViolations ? this.allViolations.length : this.violations.length;

        // Current month stats (from active violations only)
        this.stats.activeViolations = this.violations.filter(v => {
            const status = (v.status || '').toLowerCase();
            const defaultStatus = (v.levelDefaultStatus || '').toLowerCase();
            
            // It's active if it's disciplinary or warning, or if the level defaults to those
            return status === 'disciplinary' || status === 'warning' || 
                   (status === 'active' && (defaultStatus === 'disciplinary' || defaultStatus === 'warning'));
        }).length;

        this.stats.resolvedViolations = this.violations.filter(v => {
            const status = (v.status || '').toLowerCase();
            const defaultStatus = (v.levelDefaultStatus || '').toLowerCase();
            return status === 'resolved' || status === 'permitted' || 
                   (status === 'active' && (defaultStatus === 'resolved' || defaultStatus === 'permitted'));
        }).length;

        this.stats.violationTypes = {};
        this.violations.forEach(v => {
            const type = (v.violation_type || v.type || 'unknown').toLowerCase().replace(/\s+/g, '_');
            this.stats.violationTypes[type] = (this.stats.violationTypes[type] || 0) + 1;
        });

        // Days clean — use all-time data for accurate calculation
        const allViolations = this.allViolations || this.violations;
        if (allViolations.length > 0) {
            const sorted = [...allViolations].sort((a, b) => new Date(b.date || b.created_at || b.violation_date) - new Date(a.date || a.created_at || a.violation_date));
            const lastDate = new Date(sorted[0].date || sorted[0].created_at || sorted[0].violation_date);
            const diffDays = Math.floor((new Date() - lastDate) / (1000 * 60 * 60 * 24));
            this.stats.daysClean = diffDays;
        } else {
            this.stats.daysClean = 'Always'; // No violations ever
        }
    }

    async loadAnnouncements() {
        try {
            const url = `${USER_API_BASE}announcements.php?action=active&limit=5`;
            console.log('🔄 Loading announcements from:', url);
            
            const response = await fetch(url);
            if (!response.ok) {
                const errorText = await response.text();
                console.error('❌ HTTP Error:', response.status, errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const responseText = await response.text();
            console.log('📦 Announcements API raw response:', responseText.substring(0, 500));
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('❌ Failed to parse JSON:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Invalid JSON response from announcements API');
            }
            
            console.log('📦 Announcements API parsed response:', data);
            
            if (data.status === 'error') {
                console.error('❌ API returned error:', data.message);
                throw new Error(data.message || 'Error loading announcements');
            }

            const annPayload = data.data;
            this.announcements = Array.isArray(annPayload)
                ? annPayload
                : (annPayload && Array.isArray(annPayload.announcements) ? annPayload.announcements : (data.announcements || []));

            if (Array.isArray(this.announcements)) {
                console.log('✅ Loaded', this.announcements.length, 'announcements');
            } else {
                console.warn('⚠️ Announcements data is not an array:', this.announcements);
                this.announcements = [];
            }
        } catch (error) {
            console.error('❌ Error loading announcements:', error);
            console.error('Error details:', error.message, error.stack);
            this.announcements = [];
        }
    }

    async loadDashcontents() {
        try {
            const url = `${USER_API_BASE}dashcontents.php?action=active&audience=user`;
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            if (data.status === 'error') throw new Error(data.message || 'Error loading dashcontents');

            this.dashcontents = data.data || [];
        } catch (error) {
            console.error('❌ Error loading dashcontents:', error);
            this.dashcontents = [];
        }
    }

    updateDashboardDisplay() {
        console.log('🔄 Updating dashboard display...');

        // Update Stats
        const setStat = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        setStat('statActiveViolations', this.stats.activeViolations);
        setStat('statTotalViolations', this.stats.totalViolations);
        setStat('statResolvedViolations', this.stats.resolvedViolations);
        setStat('statDaysClean', this.stats.daysClean);

        // Update compliance ring (new redesigned hero)
        // Use all-time data for compliance: total vs all resolved (across all time)
        const allTimeResolved = this.allViolations ? this.allViolations.filter(v => {
            const status = (v.status || '').toLowerCase();
            return status === 'permitted' || status === 'resolved';
        }).length : this.stats.resolvedViolations;

        if (typeof window.updateComplianceRing === 'function') {
            window.updateComplianceRing(this.stats.totalViolations, allTimeResolved);
        }

        // Update hero student name
        const nameEl = document.getElementById('sdStudentName');
        if (nameEl && nameEl.textContent === 'Student') {
            const navName = document.querySelector('.user-name');
            const sidebarName = document.getElementById('sidebarUsername');
            const src = (navName && navName.textContent.trim() && navName.textContent.trim() !== 'User')
                ? navName.textContent.trim()
                : (sidebarName && sidebarName.textContent.trim() && sidebarName.textContent.trim() !== 'User')
                    ? sidebarName.textContent.trim()
                    : null;
            if (src) nameEl.textContent = src;
        }

        this.updateViolationSummary();
        this.updateRecentViolationsTable();
        this.updateAnnouncementsDisplay();
        this.updateDashcontents();
        console.log('✅ Dashboard display updated');
    }

    updateViolationSummary() {
        const container = document.querySelector('.sd-violation-summary') ||
                          document.querySelector('.violation-summary') ||
                          document.getElementById('violationSummary');
        if (!container) return;

        const typeCounts = {};
        this.violations.forEach(v => {
            const label = v.violationTypeLabel || v.violation_type_name || 'Other';
            typeCounts[label] = (typeCounts[label] || 0) + 1;
        });

        const sortedTypes = Object.entries(typeCounts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 4);

        const getIcon = (label) => {
            const lower = label.toLowerCase();
            if (lower.includes('uniform')) return { prefix: 'bx', icon: 'bxs-t-shirt' };
            if (lower.includes('footwear') || lower.includes('shoe')) return { prefix: 'fas', icon: 'fa-shoe-prints' };
            if (lower.includes('id')) return { prefix: 'bx', icon: 'bxs-id-card' };
            if (lower.includes('hair') || lower.includes('cut')) return { prefix: 'bx', icon: 'bxs-face' };
            if (lower.includes('conduct') || lower.includes('behavior')) return { prefix: 'bx', icon: 'bxs-user-x' };
            return { prefix: 'bx', icon: 'bxs-error-circle' };
        };

        const getColorByType = (label) => {
            // Consistent gold for all types — avoids confusion with warning/severity colors
            return { bg: 'var(--gold-light)', color: 'var(--gold-dark)', bar: 'var(--gold)' };
        };

        if (sortedTypes.length === 0) {
            container.innerHTML = `
                <div class="violation-type" style="justify-content:center; text-align:center;">
                    <div class="violation-details">
                        <h4 style="color:var(--green)">🎉 No Violations</h4>
                        <p>You're in good standing. Keep it up!</p>
                    </div>
                </div>`;
            return;
        }

        const maxCount = sortedTypes[0][1];
        container.innerHTML = sortedTypes.map(([label, count], i) => {
            const c = getColorByType(label);
            const pct = Math.round((count / maxCount) * 100);
            const iconData = getIcon(label);
            return `
                <div class="violation-type">
                    <div class="violation-icon" style="background:${c.bg}; color:${c.color};">
                        <i class='${iconData.prefix} ${iconData.icon}'></i>
                    </div>
                    <div class="violation-details" style="flex:1;">
                        <h4>${this.escapeHtml(label)}</h4>
                        <div class="violation-bar-wrap">
                            <div class="violation-bar" style="width:${pct}%; background:${c.bar};"></div>
                        </div>
                    </div>
                    <span class="violation-count">${count}</span>
                </div>`;
        }).join('');
    }

    updateRecentViolationsTable() {
        const container = document.getElementById('recentViolationsList') ||
                      document.querySelector('.sd-violations-list');
        if (!container) return;

        if (this.violations.length === 0) {
            container.innerHTML = `
                <div class="sd-violations-empty">
                    <i class='bx bx-info-circle'></i>
                    <p>No violations found</p>
                </div>`;
            return;
        }

        const sorted = [...this.allViolations || this.violations]
            .sort((a, b) => new Date(b.date || b.created_at) - new Date(a.date || a.created_at))
            .slice(0, 5);

        const getIcon = (label) => {
            const lower = (label || '').toLowerCase();
            if (lower.includes('uniform')) return { prefix: 'bx', icon: 'bxs-t-shirt' };
            if (lower.includes('footwear') || lower.includes('shoe')) return { prefix: 'fas', icon: 'fa-shoe-prints' };
            if (lower.includes('id')) return { prefix: 'bx', icon: 'bxs-id-card' };
            if (lower.includes('hair') || lower.includes('cut')) return { prefix: 'bx', icon: 'bxs-face' };
            if (lower.includes('conduct') || lower.includes('behavior')) return { prefix: 'bx', icon: 'bxs-user-x' };
            return { prefix: 'bx', icon: 'bxs-info-circle' };
        };

        const getIconColor = (label) => {
            const lower = (label || '').toLowerCase();
            if (lower.includes('uniform')) return 'uniform';
            if (lower.includes('footwear') || lower.includes('shoe')) return 'footwear';
            if (lower.includes('id')) return 'id';
            return 'default';
        };

        container.innerHTML = sorted.map(v => {
            const typeLabel = v.violationTypeLabel || v.violation_type_name || v.violation_type || v.type || 'Unknown';
            const date = new Date(v.date || v.created_at || v.violation_date)
                .toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

            const status = (v.status || 'warning').toLowerCase();
            const level = String(v.violation_level_name || v.violationLevelLabel || v.level || v.offense_level || '').toLowerCase();
            const isDisciplinary = level.includes('3') || level.includes('disciplinary') || status === 'disciplinary';

            let badgeClass, badgeText;
            if (status === 'resolved') {
                badgeClass = 'sd-badge--resolved'; badgeText = 'Resolved';
            } else if (status === 'permitted') {
                badgeClass = 'sd-badge--resolved'; badgeText = 'Permitted';
            } else if (status === 'disciplinary' || isDisciplinary) {
                badgeClass = 'sd-badge--warning'; badgeText = 'Disciplinary';
            } else if (status === 'warning') {
                badgeClass = 'sd-badge--pending'; badgeText = 'Warning';
            } else {
                badgeClass = 'sd-badge--pending'; badgeText = 'Pending';
            }

            const iconType = getIconColor(typeLabel);
            const iconData = getIcon(typeLabel);

            return `
                <div class="sd-violation-item" onclick="viewViolationDetails(${v.id || v.violation_id})">
                    <div class="sd-violation-item__icon sd-violation-item__icon--${iconType}">
                        <i class='${iconData.prefix} ${iconData.icon}'></i>
                    </div>
                    <div class="sd-violation-item__details">
                        <h4>${this.escapeHtml(this.formatViolationType(typeLabel))}</h4>
                        <div class="sd-violation-item__meta">
                            <span class="sd-violation-item__date"><i class='bx bx-calendar'></i> ${date}</span>
                            <span class="sd-badge ${badgeClass}">${badgeText}</span>
                        </div>
                    </div>
                    <div class="sd-violation-item__action">
                        <button class="sd-view-btn" title="View Details">
                            <i class='bx bx-chevron-right'></i>
                        </button>
                    </div>
                </div>`;
        }).join('');
    }

    updateAnnouncementsDisplay() {
        const container = document.getElementById('announcementsContent');
        if (!container) {
            console.warn('⚠️ Announcements container not found, retrying in 500ms...');
            // Retry after a short delay in case the container hasn't loaded yet
            setTimeout(() => this.updateAnnouncementsDisplay(), 500);
            return;
        }

        console.log('🔄 Updating announcements display...', {
            announcementsCount: this.announcements ? this.announcements.length : 0,
            announcements: this.announcements
        });

        // Clear content first (like admin side)
        container.innerHTML = '';

        if (!this.announcements || this.announcements.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class='bx bx-info-circle' style="font-size: 48px; color: var(--dark-grey); margin-bottom: 10px;"></i>
                    <p style="color: var(--dark-grey);">No active announcements at this time.</p>
                </div>
            `;
            console.log('⚠️ No announcements to display');
            return;
        }

        // Display announcements (similar to admin side - using forEach and appendChild)
        const latestAnnouncements = [...this.announcements]
            .sort((a, b) => new Date(b.created_at || b.createdAt) - new Date(a.created_at || a.createdAt))
            .slice(0, 5);

        latestAnnouncements.forEach(announcement => {
            const type = announcement.type || 'info';
            const typeClass = type === 'urgent' ? 'urgent' : type === 'warning' ? 'warning' : 'general';
            
            let icon = 'bxs-info-circle';
            if (type === 'urgent') icon = 'bxs-error-circle';
            else if (type === 'warning') icon = 'bxs-error';
            else if (type === 'info') icon = 'bxs-info-circle';
            else icon = 'bxs-bell';

            const timeAgo = this.formatTimeAgo(announcement.created_at || announcement.createdAt);
            const category = type === 'urgent' ? 'Urgent' : type === 'warning' ? 'Warning' : 'General';

            const item = document.createElement('div');
            item.className = `announcement-item ${typeClass}`;
            item.innerHTML = `
                <div class="announcement-icon ${typeClass}">
                    <i class='bx ${icon}'></i>
                </div>
                <div class="announcement-details">
                    <h4>${this.escapeHtml(announcement.title || 'Untitled')}</h4>
                    <p>${this.escapeHtml(announcement.message || announcement.content || '')}</p>
                    <div class="announcement-meta">
                        <span class="announcement-time">${timeAgo}</span>
                        <span class="announcement-category ${typeClass}">${category}</span>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
        
        console.log('✅ Announcements displayed:', this.announcements.length);
    }

    formatTimeAgo(dateString) {
        if (!dateString) return 'Recently';
        
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    updateDashcontents() {
        // Update tips and guidelines in user dashboard
        const tipsContainer = document.querySelector('.tips-container .tips-content');
        if (tipsContainer && this.dashcontents.length > 0) {
            const tips = this.dashcontents.filter(dc => dc.content_type === 'tip' && (dc.target_audience === 'user' || dc.target_audience === 'both'));
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
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatViolationType(type) {
        if (!type) return 'Unknown';
        
        const typeMap = {
            'improper_uniform': 'Improper Uniform',
            'improper_footwear': 'Improper Footwear',
            'no_id': 'No ID Card',
            'misconduct': 'Misconduct'
        };
        
        // Check if it's already formatted
        if (typeMap[type.toLowerCase()]) {
            return typeMap[type.toLowerCase()];
        }
        
        // If it contains the key, return the formatted version
        const lowerType = type.toLowerCase();
        for (const [key, value] of Object.entries(typeMap)) {
            if (lowerType.includes(key.replace('_', ' ')) || lowerType === key) {
                return value;
            }
        }
        
        // Otherwise, format it nicely
        return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
}

// Auto-load when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait a bit for main-content to be available
    setTimeout(() => {
        if (!window.userDashboardData) {
            window.userDashboardData = new UserDashboardData();
        }
        // Only load if we're on dashboard page
        const mainContent = document.getElementById('main-content');
        if (mainContent && (mainContent.innerHTML.includes('My Dashboard') || mainContent.innerHTML.includes('sd-violations-list') || mainContent.innerHTML.includes('student-dash'))) {
            window.userDashboardData.loadAllData();
        }
    }, 100);
});

// Function to initialize dashboard data (can be called from other scripts)
window.initializeUserDashboard = function() {
    if (!window.userDashboardData) {
        window.userDashboardData = new UserDashboardData();
    }
    window.userDashboardData.loadAllData();
};
