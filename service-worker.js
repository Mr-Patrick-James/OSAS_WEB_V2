// Cache version — derived from this file's content at install time.
// No git hooks needed: whenever this file changes (new deploy / git pull),
// the browser treats it as a new SW, runs install, and the new cache names
// replace the old ones automatically.
const BUILD_DATE = '2026-05-16-ann';
const CACHE_NAME = 'osas-cache-' + BUILD_DATE;
const API_CACHE  = 'osas-api-'   + BUILD_DATE;

const PRECACHE_ASSETS = [
  '/', '/index.php', '/manifest.json',
  '/app/assets/styles/login.css',
  '/app/assets/styles/dashboard.css',
  '/app/assets/styles/topnav.css',
  '/app/assets/styles/content-layout.css',
  '/app/assets/styles/user_dashboard.css',
  '/app/assets/styles/user_topnav.css',
  '/app/assets/styles/settings.css',
  '/app/assets/styles/violation.css',
  '/app/assets/styles/report.css',
  '/app/assets/styles/students.css',
  '/app/assets/styles/department.css',
  '/app/assets/styles/section.css',
  '/app/assets/styles/announcements.css',
  '/app/assets/styles/chatbot.css',
  '/app/assets/img/default.png',
  '/app/assets/js/pwa.js',
  '/app/assets/js/dashboard.js',
  '/app/assets/js/user_dashboard.js',
  '/app/assets/js/dashboardData.js',
  '/app/assets/js/userDashboardData.js',
  '/app/assets/js/utils/theme.js',
  '/app/assets/js/utils/eyeCare.js',
  '/app/assets/js/utils/notification.js',
  '/app/assets/js/utils/offlineDB.js',
  '/app/assets/js/department.js',
  '/app/assets/js/section.js',
  '/app/assets/js/student.js',
  '/app/assets/js/violation.js',
  '/app/assets/js/reports.js',
  '/app/assets/js/announcement.js',
  '/app/assets/js/chatbot.js',
  '/app/assets/js/userViolations.js',
  '/app/assets/js/userAnnouncements.js',
  '/app/assets/js/push-notifications.js'
];

// ── INSTALL ───────────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache =>
      Promise.allSettled(
        PRECACHE_ASSETS.map(url =>
          fetch(url).then(r => { if (r.ok) cache.put(url, r); }).catch(() => {})
        )
      )
    )
  );
  self.skipWaiting();
});

// ── ACTIVATE ──────────────────────────────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(names =>
      Promise.all(
        names.filter(n => n !== CACHE_NAME && n !== API_CACHE).map(n => caches.delete(n))
      )
    )
  );
  self.clients.claim();
});

// ── FETCH ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET') return;

  // API calls — network first, offline fallback with client-side filtering
  if (url.pathname.includes('/api/')) {
    event.respondWith(handleAPIRequest(req, url));
    return;
  }

  // PHP pages — network first, cache fallback
  if (url.pathname.endsWith('.php') || url.pathname === '/') {
    event.respondWith(
      fetch(req).then(res => {
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(req, clone));
        }
        return res;
      }).catch(() =>
        caches.match(req).then(c => c || caches.match('/index.php'))
      )
    );
    return;
  }

  // Static assets — cache first
  event.respondWith(
    caches.match(req).then(cached => {
      if (cached) return cached;
      return fetch(req).then(res => {
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE_NAME).then(c => c.put(req, clone));
        }
        return res;
      }).catch(() => null);
    })
  );
});

// ── API REQUEST HANDLER ───────────────────────────────────────────────────────
async function handleAPIRequest(req, url) {
  try {
    const res = await fetch(req);
    if (res.ok) {
      // Cache the full dataset (base endpoint without pagination/search params)
      const clone = res.clone();
      const cache = await caches.open(API_CACHE);
      const baseKey = getBaseKey(url);
      if (baseKey) cache.put(baseKey, clone);
    }
    return res;
  } catch (e) {
    // Offline — serve from cache with client-side filtering
    return serveOffline(url);
  }
}

// Strip pagination/search params to get the base cache key.
// Returns null for requests that should not be cached (e.g. timestamped
// violation fetches — those are handled by IndexedDB in violation.js).
function getBaseKey(url) {
  const base   = url.origin + url.pathname;
  const action = url.searchParams.get('action') || '';

  if (url.pathname.includes('violations.php')) {
    if (action === 'types') return new Request(base + '?action=types');
    // Violations with is_archived param: cache keyed by archived state
    // so both active (0) and archived (1) datasets are stored separately.
    const isArchived = url.searchParams.get('is_archived');
    if (isArchived !== null) {
      return new Request(base + `?is_archived=${isArchived}&limit=all`);
    }
    // Generic violations request — cache to the all-records key
    return new Request(base + '?limit=all');
  }
  if (url.pathname.includes('students.php')) {
    // Cache the full active dataset keyed by a stable URL.
    // student.js offline handler reads this key directly.
    return new Request(base + '?action=get&filter=active&page=1&limit=1000');
  }
  return new Request(url.href);
}

// Serve offline response with client-side filtering
async function serveOffline(url) {
  const cache  = await caches.open(API_CACHE);
  const base   = url.origin + url.pathname;
  const action = url.searchParams.get('action') || '';

  // ── violations.php ────────────────────────────────────────────────────────
  if (url.pathname.includes('violations.php')) {
    if (action === 'types') {
      const c = await cache.match(new Request(base + '?action=types'));
      if (c) return c.clone();
      return offlineJSON({ status: 'success', data: [] });
    }

    const isArchived = url.searchParams.get('is_archived');

    // Try the keyed cache entry first (is_archived=0 or is_archived=1)
    let cached = null;
    if (isArchived !== null) {
      cached = await cache.match(new Request(base + `?is_archived=${isArchived}&limit=all`));
    }
    // Fallback: generic limit=all key
    if (!cached) cached = await cache.match(new Request(base + '?limit=all'));
    // Last resort: scan all cached keys for any violations entry
    if (!cached) {
      const keys = await cache.keys();
      for (const k of keys) {
        if (k.url.includes('violations.php') && !k.url.includes('action=')) {
          cached = await cache.match(k); break;
        }
      }
    }
    if (!cached) return offlineJSON({ status: 'success', violations: [], data: [], total: 0 });

    const data = await cached.json();
    let list = data.violations || data.data?.violations || data.data || [];
    if (!Array.isArray(list)) list = [];

    // If we loaded from a generic cache key, filter by is_archived ourselves
    if (isArchived !== null) {
      list = list.filter(v => (v.is_archived || 0) == parseInt(isArchived));
    }

    // Client-side filters
    const search     = url.searchParams.get('search') || '';
    const dept       = url.searchParams.get('department') || '';
    const status     = url.searchParams.get('status') || '';
    const studentId  = url.searchParams.get('student_id') || '';

    if (studentId)  list = list.filter(v => (v.student_id || v.studentId || '').toLowerCase() === studentId.toLowerCase());
    if (search)     { const s = search.toLowerCase(); list = list.filter(v => JSON.stringify(v).toLowerCase().includes(s)); }
    if (dept && dept !== 'all') list = list.filter(v => (v.department || v.student_dept || '').toLowerCase().includes(dept.toLowerCase()));
    if (status && status !== 'all') list = list.filter(v => (v.status || '').toLowerCase() === status.toLowerCase());

    const page  = parseInt(url.searchParams.get('page') || '1');
    const limit = parseInt(url.searchParams.get('limit') || '10');
    const total = list.length;
    const paged = list.slice((page - 1) * limit, page * limit);

    return offlineJSON({ status: 'success', offline: true, violations: paged, data: { violations: paged, total, page, limit, pages: Math.ceil(total / limit) }, total });
  }

  // ── students.php ──────────────────────────────────────────────────────────
  if (url.pathname.includes('students.php')) {
    let cached = await cache.match(new Request(base + '?action=get&filter=active&page=1&limit=1000'));
    if (!cached) {
      const keys = await cache.keys();
      for (const k of keys) { if (k.url.includes('students.php')) { cached = await cache.match(k); break; } }
    }
    if (!cached) return offlineJSON({ status: 'success', data: { students: [], total: 0 } });

    const data = await cached.json();
    let list = data.data?.students || data.students || data.data || [];
    if (!Array.isArray(list)) list = [];

    const search  = url.searchParams.get('search') || '';
    const dept    = url.searchParams.get('department') || '';
    const section = url.searchParams.get('section') || '';

    if (search)  { const s = search.toLowerCase(); list = list.filter(st => JSON.stringify(st).toLowerCase().includes(s)); }
    if (dept && dept !== 'all')    list = list.filter(st => (st.department || '').toLowerCase() === dept.toLowerCase());
    if (section && section !== 'all') list = list.filter(st => (st.section_id || st.section || '').toString() === section);

    const page  = parseInt(url.searchParams.get('page') || '1');
    const limit = parseInt(url.searchParams.get('limit') || '10');
    const total = list.length;
    const paged = list.slice((page - 1) * limit, page * limit);

    return offlineJSON({ status: 'success', offline: true, data: { students: paged, total, page, limit, pages: Math.ceil(total / limit) } });
  }

  // ── Other endpoints — return cached or empty ──────────────────────────────
  const endpoints = ['departments.php', 'sections.php', 'announcements.php', 'dashboard_stats.php', 'reports.php'];
  for (const ep of endpoints) {
    if (url.pathname.includes(ep)) {
      const keys = await cache.keys();
      for (const k of keys) { if (k.url.includes(ep)) { const c = await cache.match(k); if (c) return c.clone(); } }
      return offlineJSON({ status: 'success', offline: true, data: [] });
    }
  }

  return offlineJSON({ status: 'offline', message: 'No cached data available.', data: [] });
}

function offlineJSON(data) {
  return new Response(JSON.stringify(data), {
    status: 200,
    headers: { 'Content-Type': 'application/json', 'X-Offline': '1' }
  });
}

// ── BACKGROUND SYNC ───────────────────────────────────────────────────────────
self.addEventListener('sync', event => {
  if (event.tag === 'sync-violations') {
    event.waitUntil(
      self.clients.matchAll({ type: 'window' }).then(clients =>
        clients.forEach(c => c.postMessage({ type: 'SYNC_VIOLATIONS' }))
      )
    );
  }
});

self.addEventListener('message', event => {
  if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('push', event => {
  let p = { title: 'E-OSAS', body: 'You have a new update.', icon: '/app/assets/img/default.png', tag: 'eosas', data: { page: 'user-page/user_dashcontent' } };
  if (event.data) {
    try {
      const j = event.data.json();
      p = { ...p, ...j, data: { ...p.data, ...(j.data || {}) } };
    } catch (e) { p.body = event.data.text() || p.body; }
  }
  event.waitUntil(self.registration.showNotification(p.title, {
    body: p.body, icon: p.icon || '/app/assets/img/default.png', badge: p.icon, tag: p.tag, data: p.data, vibrate: [180, 80, 180], renotify: true
  }));
});

function notificationTargetPath(data) {
  const type = data.type || '';
  const page = data.page || '';
  if (type === 'violation' || (page && page.includes('violation'))) {
    return '/includes/user_dashboard.php?push_page=' + encodeURIComponent(page || 'user-page/my_violations');
  }
  if (page) {
    return '/includes/user_dashboard.php?push_page=' + encodeURIComponent(page);
  }
  return data.url || '/';
}

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const data = event.notification.data || {};
  const path = notificationTargetPath(data);
  const targetUrl = new URL(path, self.registration.scope).href;

  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    const sameOrigin = (c) => {
      try { return new URL(c.url).origin === self.location.origin; } catch (e) { return false; }
    };

    // Prefer an already-open E-OSAS window (installed PWA or tab) — focus + navigate into it
    for (const client of allClients) {
      if (!sameOrigin(client)) continue;
      if ('focus' in client) await client.focus();
      if (typeof client.navigate === 'function') {
        try {
          return await client.navigate(targetUrl);
        } catch (e) { /* fall through */ }
      }
      client.postMessage({ type: 'PUSH_NAVIGATE', page: data.page || '', url: path });
      return;
    }

    // No window open — openURL; with manifest scope this should launch the installed PWA on Android
    return clients.openWindow(targetUrl);
  })());
});
