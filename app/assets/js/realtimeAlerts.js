/**
 * Real-time in-app alerts (no permission needed).
 * Polls announcements + violations; updates bell badge; shows system notification if already allowed.
 */
(function () {
    'use strict';

    const POLL_MS        = 20000; // 20s for violations (was 45s)
    const ANNOUNCE_MS   = 45000; // 45s for announcements
    const STORAGE_KEY    = 'eosas_last_alert_snapshot';

    function apiBase() {
        const p = location.pathname.split('/').filter(Boolean);
        const d = ['app', 'api', 'includes', 'assets', 'public'];
        return ((!p.length || d.includes(p[0])) ? '' : '/' + p[0]) + '/api/';
    }

    function loadSnapshot() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    function saveSnapshot(s) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(s));
    }

    function updateBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) badge.textContent = count > 9 ? '9+' : String(count);
    }

    function notifyUser(title, body, tag) {
        if (typeof window.showSystemAlert === 'function' && Notification.permission === 'granted') {
            window.showSystemAlert(title, body, tag);
        }
        if (typeof window.showNotification === 'function') {
            window.showNotification(body, 'info', title);
        } else if (typeof window.showInfo === 'function') {
            window.showInfo(title + ': ' + body);
        }
    }

    async function fetchViolations() {
        const res = await fetch(apiBase() + 'violations.php', { credentials: 'same-origin' });
        const data = await res.json();
        return data.data || data.violations || [];
    }

    async function fetchAnnouncements() {
        const res = await fetch(apiBase() + 'announcements.php?action=active&limit=50', { credentials: 'same-origin' });
        const data = await res.json();
        const payload = data.data;
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.announcements)) return payload.announcements;
        if (Array.isArray(data.announcements)) return data.announcements;
        return [];
    }

    async function checkForUpdates() {
        if (!navigator.onLine) return;

        try {
            const [violations, announcements] = await Promise.all([
                fetchViolations(),
                fetchAnnouncements()
            ]);

            violations.sort((a, b) => Number(b.id) - Number(a.id));
            announcements.sort((a, b) => Number(b.id) - Number(a.id));
            const latestV = violations[0];
            const latestA = announcements[0];
            const snap = loadSnapshot();
            const isFirst = !snap.initialized;

            if (isFirst) {
                saveSnapshot({
                    initialized: true,
                    lastViolationId: latestV?.id,
                    lastAnnouncementId: latestA?.id
                });
                // Initial badge count from unread violations
                _updateBadgeFromViolations(violations);
                return;
            }

            let newCount = 0;

            if (latestV && String(latestV.id) !== String(snap.lastViolationId)) {
                const isNew = !snap.lastViolationId || Number(latestV.id) > Number(snap.lastViolationId);
                if (isNew && latestV.is_read != 1) {
                    notifyUser(
                        'New Violation Recorded',
                        (latestV.case_id ? 'Case ' + latestV.case_id + ' — ' : '') +
                            (latestV.violation_type_name || latestV.violation_type || 'Check your violations tab'),
                        'violation-' + latestV.id
                    );
                    newCount++;
                }
                snap.lastViolationId = latestV.id;
            }

            if (latestA && String(latestA.id) !== String(snap.lastAnnouncementId)) {
                const isNew = !snap.lastAnnouncementId || Number(latestA.id) > Number(snap.lastAnnouncementId);
                if (isNew) {
                    notifyUser(
                        'New Announcement',
                        latestA.title || 'New campus update posted',
                        'announcement-' + latestA.id
                    );
                    newCount++;
                }
                snap.lastAnnouncementId = latestA.id;
            }

            saveSnapshot(snap);

            // Always silently update the badge count
            if (typeof window.refreshNotificationBadge === 'function') {
                window.refreshNotificationBadge();
            } else {
                _updateBadgeFromViolations(violations, newCount);
            }

            // If dropdown is already open, refresh it silently
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                if (typeof window.refreshNotificationDropdown === 'function') {
                    window.refreshNotificationDropdown();
                }
            }

        } catch (e) {
            console.warn('Realtime alerts:', e);
        }
    }

    function _updateBadgeFromViolations(violations, extraNew = 0) {
        const seen = JSON.parse(localStorage.getItem('seen_notifications') || '[]');
        const read = JSON.parse(localStorage.getItem('read_notifications') || '[]');
        const unreadViolations = violations.filter(v =>
            !read.includes('v-' + v.id) &&
            !seen.includes('v-' + v.id) &&
            v.is_read != 1
        ).length;
        updateBadge(unreadViolations + extraNew);
    }

    function startRealtimeAlerts() {
        if (!document.getElementById('notificationBtn')) return;
        checkForUpdates();
        setInterval(checkForUpdates, POLL_MS); // poll every 20s
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkForUpdates();
        });
    }

    window.startRealtimeAlerts = startRealtimeAlerts;
    window.checkForUpdates = checkForUpdates;

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(startRealtimeAlerts, 3000);
    });
})();
