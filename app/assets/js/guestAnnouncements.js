/**
 * Guest landing: show latest announcements + poll for new ones (permission required for system notifs).
 */
(function () {
    'use strict';

    const POLL_MS = 60000;
    const SHOWN_KEY = 'eosas_ann_batch_shown';
    const LAST_ID_KEY = 'eosas_last_announcement_id';

    function apiBase() {
        const p = location.pathname.split('/').filter(Boolean);
        const d = ['app', 'api', 'includes', 'assets', 'public'];
        return ((!p.length || d.includes(p[0])) ? '' : '/' + p[0]) + '/api/';
    }

    function iconUrl() {
        const root = apiBase().replace(/\/api\/$/, '');
        return root + '/app/assets/img/default.png';
    }

    /** API returns { data: { announcements: [...] } } or legacy { data: [...] } */
    function parseAnnouncementList(json) {
        const payload = json && json.data;
        if (Array.isArray(payload)) return payload;
        if (payload && Array.isArray(payload.announcements)) return payload.announcements;
        if (json && Array.isArray(json.announcements)) return json.announcements;
        return [];
    }

    async function fetchLatest(limit) {
        const res = await fetch(apiBase() + 'announcements.php?action=active&limit=' + (limit || 5), {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const json = await res.json();
        if (json.status === 'error') return [];
        let list = parseAnnouncementList(json);
        list.sort((a, b) => Number(b.id) - Number(a.id));
        return list.slice(0, limit || 5);
    }

    async function showViaServiceWorker(title, body, tag, data) {
        if (Notification.permission !== 'granted') return;
        try {
            const reg = await navigator.serviceWorker.ready;
            await reg.showNotification(title, {
                body: body.substring(0, 200),
                icon: iconUrl(),
                badge: iconUrl(),
                tag: tag || 'eosas-ann',
                data: data || { type: 'announcement', url: '/' },
                renotify: true
            });
        } catch (e) {
            new Notification(title, { body, icon: iconUrl() });
        }
    }

    /** Only on first Enable — never replay when reopening the app. */
    async function showLatestBatch(force) {
        if (Notification.permission !== 'granted') return;
        if (!force) return;
        if (localStorage.getItem(SHOWN_KEY)) return;

        const list = await fetchLatest(5);
        if (!list.length) {
            await showViaServiceWorker('E-OSAS', 'No active announcements right now.', 'eosas-empty');
            localStorage.setItem(SHOWN_KEY, '1');
            return;
        }

        for (let i = 0; i < list.length; i++) {
            const a = list[i];
            const body = (a.message || '').replace(/<[^>]+>/g, '').trim();
            await showViaServiceWorker(
                a.title || 'Campus announcement',
                body || 'Open E-OSAS for details.',
                'announcement-' + a.id,
                { type: 'announcement', id: a.id, page: 'user-page/announcements', url: '/' }
            );
            if (i < list.length - 1) await new Promise(r => setTimeout(r, 450));
        }

        localStorage.setItem(SHOWN_KEY, '1');
        localStorage.setItem(LAST_ID_KEY, String(list[0].id));
    }

    async function seedLastAnnouncementId() {
        if (localStorage.getItem(LAST_ID_KEY)) return;
        try {
            const list = await fetchLatest(1);
            if (list.length) localStorage.setItem(LAST_ID_KEY, String(list[0].id));
        } catch (e) { /* ignore */ }
    }

    async function checkNewAnnouncement() {
        if (Notification.permission !== 'granted' || !navigator.onLine) return;
        try {
            const list = await fetchLatest(1);
            if (!list.length) return;
            const latest = list[0];
            const last = localStorage.getItem(LAST_ID_KEY);
            if (!last) {
                localStorage.setItem(LAST_ID_KEY, String(latest.id));
                return;
            }
            if (String(latest.id) === String(last)) return;

            const body = (latest.message || '').replace(/<[^>]+>/g, '').trim();
            await showViaServiceWorker(
                'New announcement',
                (latest.title || 'Campus update') + (body ? ': ' + body.substring(0, 80) : ''),
                'announcement-' + latest.id,
                { type: 'announcement', id: latest.id, url: '/' }
            );
            localStorage.setItem(LAST_ID_KEY, String(latest.id));
        } catch (e) {
            console.warn('Announcement poll:', e);
        }
    }

    function isInstalledPWA() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || navigator.standalone === true
            || (typeof window.isInstalledPWA === 'function' && window.isInstalledPWA());
    }

    function startGuestAnnouncementWatcher() {
        if (document.body.dataset.eosasPush !== 'guest') return;
        if (!isInstalledPWA()) return;
        if (window._eosasAnnWatcherOn) return;
        window._eosasAnnWatcherOn = true;

        if (Notification.permission === 'granted') {
            seedLastAnnouncementId().then(() => checkNewAnnouncement());
        }
        setInterval(checkNewAnnouncement, POLL_MS);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkNewAnnouncement();
        });
    }

    window.fetchLatestAnnouncements = fetchLatest;
    window.showLatestAnnouncementNotifications = (force) => showLatestBatch(!!force);
    window.startGuestAnnouncementWatcher = startGuestAnnouncementWatcher;

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(startGuestAnnouncementWatcher, 2500);
    });
})();
