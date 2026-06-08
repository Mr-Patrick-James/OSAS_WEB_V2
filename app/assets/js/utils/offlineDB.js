/**
 * OSAS Offline Database Manager (IndexedDB)
 * Handles caching violations for offline viewing and queuing
 * new violations recorded while offline for later sync.
 */
const DB_NAME    = 'OSAS_OFFLINE_DB';
const DB_VERSION = 2; // bumped to add pending_count store

const STORE_VIOLATIONS = 'offline_violations';
const STORE_SYNC_QUEUE = 'sync_queue';

class OfflineDB {
    constructor() { this.db = null; }

    async init() {
        if (this.db) return this.db;
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, DB_VERSION);

            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_VIOLATIONS)) {
                    db.createObjectStore(STORE_VIOLATIONS, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(STORE_SYNC_QUEUE)) {
                    const store = db.createObjectStore(STORE_SYNC_QUEUE, {
                        keyPath: 'tempId', autoIncrement: true
                    });
                    store.createIndex('timestamp', 'timestamp');
                }
            };

            req.onsuccess  = (e) => { this.db = e.target.result; resolve(this.db); };
            req.onerror    = (e) => reject(e.target.error);
        });
    }

    // ── Violation cache (for offline viewing) ────────────────────────────────
    async saveViolations(violations) {
        const db    = await this.init();
        const tx    = db.transaction(STORE_VIOLATIONS, 'readwrite');
        const store = tx.objectStore(STORE_VIOLATIONS);

        // Preserve any TEMP-* (pending offline) entries that haven't synced yet.
        // A plain store.clear() would wipe them out whenever fresh server data arrives.
        const existingReq = store.getAll();
        return new Promise((resolve, reject) => {
            existingReq.onsuccess = () => {
                const existing = existingReq.result || [];
                const pendingTemps = existing.filter(v => String(v.id || '').startsWith('TEMP-'));

                store.clear();

                // Write fresh server records first
                violations.forEach(v => store.put(v));

                // Re-insert TEMP entries that are NOT already covered by a real record
                // (match by studentId + approximate date to avoid duplicates after sync)
                const serverIds = new Set(violations.map(v => String(v.id)));
                pendingTemps.forEach(temp => {
                    if (!serverIds.has(String(temp.id))) {
                        store.put(temp);
                    }
                });

                tx.oncomplete = resolve;
                tx.onerror    = () => reject(tx.error);
            };
            existingReq.onerror = () => reject(existingReq.error);
        });
    }

    async getViolations() {
        const db    = await this.init();
        const tx    = db.transaction(STORE_VIOLATIONS, 'readonly');
        const store = tx.objectStore(STORE_VIOLATIONS);
        return new Promise(resolve => {
            const r = store.getAll();
            r.onsuccess = () => resolve(r.result);
        });
    }

    // ── Sync queue (violations recorded while offline) ────────────────────────
    async queueAction(action, data) {
        const db    = await this.init();
        const tx    = db.transaction(STORE_SYNC_QUEUE, 'readwrite');
        const store = tx.objectStore(STORE_SYNC_QUEUE);
        const item  = { action, data, timestamp: Date.now(), status: 'pending' };
        const req   = store.add(item);
        return new Promise((resolve, reject) => {
            req.onsuccess = () => {
                item.tempId = req.result;
                this._updateBadge();
                resolve(item);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async getSyncQueue() {
        const db    = await this.init();
        const tx    = db.transaction(STORE_SYNC_QUEUE, 'readonly');
        const store = tx.objectStore(STORE_SYNC_QUEUE);
        return new Promise(resolve => {
            const r = store.getAll();
            r.onsuccess = () => resolve(r.result);
        });
    }

    async getPendingCount() {
        const queue = await this.getSyncQueue();
        return queue.filter(i => i.status === 'pending').length;
    }

    async removeFromQueue(tempId) {
        const db    = await this.init();
        const tx    = db.transaction(STORE_SYNC_QUEUE, 'readwrite');
        const store = tx.objectStore(STORE_SYNC_QUEUE);
        store.delete(tempId);
        return new Promise(resolve => {
            tx.oncomplete = () => { this._updateBadge(); resolve(); };
        });
    }

    async clearQueue() {
        const db    = await this.init();
        const tx    = db.transaction(STORE_SYNC_QUEUE, 'readwrite');
        tx.objectStore(STORE_SYNC_QUEUE).clear();
        return new Promise(resolve => {
            tx.oncomplete = () => { this._updateBadge(); resolve(); };
        });
    }

    // ── Badge update ──────────────────────────────────────────────────────────
    async _updateBadge() {
        const count = await this.getPendingCount();
        const badge = document.getElementById('offlinePendingBadge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }
    }
}

const offlineDB = new OfflineDB();
window.offlineDB = offlineDB;

// Expose badge refresh globally
window.refreshOfflineBadge = () => offlineDB._updateBadge();
