/* =========================================================
   The data layer, against /api/*.

   This replaces core/store.js, which served the panel from
   localStorage so the flows could be walked before a backend
   existed. Every method below keeps that file's name, argument
   list and promise shape, because forty-one page scripts call
   them and docs/php/06-decisions.md §1 is the promise that they
   do not change. What changes is where the answer comes from.

   Two things the mock could do that a network cannot, and how
   each is handled:

   1. allSync(entity) — a synchronous read. A table cell renders
      a post's author while it is building a row and cannot
      await. So GET /api/bootstrap fills a cache with every
      collection before any page script runs, and allSync reads
      that. The prototype did the same thing with its nine seed
      files, which every screen loaded whether it used them or
      not; the difference is that this is read from the database
      on each page load and cannot be stale.

   2. remove(entity, id) — returned {row, index} so the toast
      could offer Undo. DELETE answers 204, so the row is taken
      from the cache before the request and the Undo is
      POST /api/{entity}/{id}/restore, which is what a soft
      delete is for.

   TMH.boot(fn) is how a page script waits for that cache. It is
   the one line each of them changed.
   ========================================================= */
(function (root) {
    'use strict';

    const meta = (name) => {
        const el = document.querySelector(`meta[name="${name}"]`);
        return el ? el.getAttribute('content') || '' : '';
    };

    /* The application may be installed in a subdirectory, so "/api" is not
       necessarily the API. app/components/admin/head.php prints the real base. */
    const BASE = (meta('app-base') || '/').replace(/\/+$/, '') + '/';
    const CSRF = meta('csrf-token');

    /* Collections whose rows are the panel's cross-entity lookups. Filled by
       one request at boot; a screen's own list is still fetched, filtered and
       paged by the server. */
    const cache = {};

    let settingsDoc = null;      /* the last /api/settings response … */
    let settingsSnapshot = {};   /* … as JSON per group, so setDoc patches only what moved */
    let identity = null;         /* GET /api/auth/me — session.js reads it back */

    const dependentHooks = {};   /* entity -> fn(id) => [string] */

    const clone = (v) => (v === undefined ? v : JSON.parse(JSON.stringify(v)));

    /* ---------------------------------------------------------
       Requests
       --------------------------------------------------------- */

    /**
     * An API error carrying the envelope's own fields, because form.js binds
     * `err.fields` straight onto the inputs and toasts `err.message`. Same
     * shape store.js threw, so nothing downstream can tell them apart.
     */
    function apiError(status, body) {
        const error = (body && body.error) || {};
        const err = new Error(error.message || `Request failed (${status})`);
        err.status = status;
        err.code = error.code || 'SERVER_ERROR';
        if (error.fields) err.fields = error.fields;
        if (error.dependents) err.dependents = error.dependents;
        return err;
    }

    async function request(method, path, options) {
        const o = options || {};
        const url = new URL(BASE + String(path).replace(/^\/+/, ''), location.origin);

        /* 'all' is the panel's "no filter" sentinel, so it is dropped rather
           than sent. It is not a sentinel in the search box: `q=all` is a
           person looking for the word, and skipping it here silently returned
           the unfiltered table instead. */
        Object.entries(o.query || {}).forEach(([k, v]) => {
            if (v === undefined || v === null || v === '') return;
            if (v === 'all' && k !== 'q') return;
            url.searchParams.set(k, v);
        });

        const headers = { Accept: 'application/json' };
        let body;

        if (o.form) {
            body = o.form;                       /* multipart: the browser sets the boundary */
        } else if (o.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(o.body);
        }

        if (method !== 'GET') headers['X-CSRF-Token'] = CSRF;

        const res = await fetch(url.toString(), {
            method,
            credentials: 'same-origin',
            headers,
            body,
        });

        /* A session that expired mid-visit is not an error the screen can do
           anything with — every later request would fail the same way. Going to
           the sign-in with `next` set means one password and the same screen
           back. */
        if (res.status === 401 && !o.allow401) {
            location.assign(`${BASE}admin/login?next=${encodeURIComponent(document.body.dataset.page || '')}`);
            await new Promise(() => {});          /* never settles: the page is leaving */
        }

        if (res.status === 204) return null;

        const payload = await res.json().catch(() => null);

        if (!res.ok) throw apiError(res.status, payload);

        return payload;
    }

    const get = (path, query, options) => request('GET', path, Object.assign({ query }, options));
    const post = (path, body) => request('POST', path, { body });
    const patch = (path, body) => request('PATCH', path, { body });
    const del = (path, query) => request('DELETE', path, { query });

    /* ---------------------------------------------------------
       Where a collection lives

       Most are the generic block in api/gateway.php. Three are
       not: pages and media have controllers of their own, and
       the activity log is a report rather than a resource.
       --------------------------------------------------------- */

    const PATHS = { activity: 'api/activity', pages: 'api/pages', media: 'api/media' };
    const pathFor = (entity) => PATHS[entity] || `api/${entity}`;

    /* ---------------------------------------------------------
       Boot
       --------------------------------------------------------- */

    /**
     * One request for the collections, one for the settings, one for the
     * fixed pages, one for whoever is signed in — in parallel, before the
     * screen paints anything.
     */
    const ready = (async function warm() {
        const [collections, settings, pages, me] = await Promise.all([
            get('api/bootstrap'),
            get('api/settings'),
            get('api/pages'),
            get('api/auth/me'),
        ]);

        Object.assign(cache, collections.data || {});
        cache.pages = pages.data || [];

        settingsDoc = settings.data || {};
        snapshotSettings();

        identity = me.data || {};
    }());

    ready.catch((err) => {
        /* A 401 has already navigated away. Anything else means the panel has
           no data at all, and a screen that paints an empty table over that is
           a screen that says the hospital has no doctors. */
        console.error('[api] could not load the panel', err);
        document.body.innerHTML = '<div class="empty" style="padding:80px 24px;text-align:center">'
            + '<h3>The panel could not be loaded</h3>'
            + '<p>The server did not answer. Reload the page, and tell whoever runs this site if it keeps happening.</p></div>';
    });

    /**
     * Run `fn` once the DOM is ready and the cache is warm.
     *
     * The prototype's page scripts bound to DOMContentLoaded, which was enough
     * when the data was already in the page. It is not enough now: the first
     * thing most of them do is read a lookup collection synchronously.
     */
    function boot(fn) {
        /* The rejection is already reported and painted by the handler above;
           swallowing it here only stops a second unhandled one per screen. */
        const start = () => { ready.then(fn).catch(() => {}); };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }

    /* ---------------------------------------------------------
       Settings
       --------------------------------------------------------- */

    function snapshotSettings() {
        settingsSnapshot = {};
        Object.entries(settingsDoc || {}).forEach(([group, value]) => {
            settingsSnapshot[group] = JSON.stringify(value);
        });
    }

    /* ---------------------------------------------------------
       The store
       --------------------------------------------------------- */

    const store = {

        /* ----- read ----- */

        /**
         * list('doctors', {q, status, filters, sort, dir, page, pageSize})
         * → {rows, total, page, pageSize, counts}
         *
         * `searchFields` and `filterFns` are accepted and ignored: the server
         * knows which columns a collection searches (config/resources.php) and
         * every filter the panel sends has a name the registry answers to. The
         * mock needed both because it was filtering an array in the browser.
         */
        async list(entity, opts) {
            const o = Object.assign({ page: 1, pageSize: 20 }, opts || {});

            const query = {
                q: (o.q || '').trim(),
                status: o.status,
                sort: o.sort,
                dir: o.dir,
                page: o.page,
                pageSize: o.pageSize,
            };

            Object.entries(o.filters || {}).forEach(([k, v]) => { query[k] = v; });

            const res = await get(pathFor(entity), query);
            const meta_ = res.meta || {};

            return {
                rows: res.data || [],
                total: meta_.total || 0,
                page: meta_.page || o.page,
                pageSize: meta_.pageSize === undefined ? o.pageSize : meta_.pageSize,
                counts: meta_.counts || {},
            };
        },

        /** Every row, unfiltered — for pickers and cross-entity lookups. */
        async all(entity) {
            const res = await get(pathFor(entity), { pageSize: 0 });
            cache[entity] = res.data || [];
            return clone(cache[entity]);
        },

        /**
         * Synchronous read, from the boot cache. Only for render-time lookups
         * — an author's name on a post row — where an await per row would be
         * absurd. Returns [] before boot has finished, which is why every page
         * script starts inside TMH.boot().
         */
        allSync(entity) {
            return cache[entity] || [];
        },

        /** Whether the cache can answer for this collection at all. */
        available(entity) {
            return Array.isArray(cache[entity]);
        },

        async get(entity, id) {
            try {
                const res = await get(`${pathFor(entity)}/${encodeURIComponent(id)}`);
                return res.data || null;
            } catch (err) {
                if (err.status === 404) return null;
                throw err;
            }
        },

        /* ----- write ----- */

        async create(entity, data) {
            const res = await post(pathFor(entity), data);
            return store.remember(entity, res.data);
        },

        async update(entity, id, patchBody) {
            const res = await patch(`${pathFor(entity)}/${encodeURIComponent(id)}`, patchBody);
            return store.remember(entity, res.data, id);
        },

        /**
         * Returns {row, index} so the toast can offer Undo, which is what
         * store.js returned. The row comes from the cache because DELETE
         * answers 204 and by then it is the only copy left in the browser.
         */
        async remove(entity, id) {
            const rows = cache[entity] || [];
            const index = rows.findIndex((r) => String(r.id) === String(id));
            const row = index === -1 ? await store.get(entity, id) : rows[index];

            await del(`${pathFor(entity)}/${encodeURIComponent(id)}`);

            if (index !== -1) rows.splice(index, 1);

            return row ? { row: clone(row), index: index === -1 ? rows.length : index } : null;
        },

        /**
         * Undo that delete. The signature keeps store.js's `(entity, row,
         * index)` because table.js hands back exactly what remove() gave it —
         * but a soft delete means the record is still there, so only its id is
         * needed and the position is the one it never lost.
         */
        async restore(entity, row, index) {
            const id = row && row.id !== undefined ? row.id : row;
            const res = await post(`${pathFor(entity)}/${encodeURIComponent(id)}/restore`);

            return store.remember(entity, res.data, id, index);
        },

        /**
         * revert('testimonials', 'tst-009') — undo a delete from its log row.
         *
         * The mock refused: it spliced rows out of an array, so by the time the
         * log row was read the record was gone and an id was all there was.
         * Here a delete only sets deleted_at, so the record is still there to
         * bring back and this is the restore endpoint under another name. The
         * log row's `revertable` flag is the server saying which rows qualify.
         */
        async revert(entity, id) {
            return store.restore(entity, { id });
        },

        async reorder(entity, idsInOrder) {
            await post(`${pathFor(entity)}/reorder`, { ids: idsInOrder });

            const rows = cache[entity];

            if (rows) {
                idsInOrder.forEach((id, i) => {
                    const row = rows.find((r) => String(r.id) === String(id));
                    if (row) row.order = i + 1;
                });
            }

            return true;
        },

        /**
         * bulk('doctors', ids, 'publish')
         * → {succeeded: [], failed: [{id, reason}]}
         * Partial failure is reported honestly rather than swallowed — see the
         * bulk toast rule in docs/04-crud-flows.md.
         */
        async bulk(entity, ids, action, payload) {
            const res = await post(`${pathFor(entity)}/bulk`, { ids, action, payload: payload || {} });
            const result = res.data || { succeeded: [], failed: [] };

            /* The cache has to follow, or the sidebar badge and every lookup on
               the screen still count rows that have just gone. */
            if (action === 'delete' && cache[entity]) {
                const gone = new Set(result.succeeded.map(String));
                cache[entity] = cache[entity].filter((r) => !gone.has(String(r.id)));
            } else if (cache[entity]) {
                await store.all(entity);
            }

            return result;
        },

        /* ----- cross-entity reads ----- */

        /**
         * summary() → {stats, attention, recentEnquiries, recentActivity, setup}
         *
         * One question that spans eight tables, and no number of list() calls
         * is that question. The comparisons behind the four tiles are the
         * server's — see api/controllers/DashboardController.php.
         */
        async summary() {
            const res = await get('api/dashboard/summary');
            return res.data;
        },

        /**
         * The topbar's global search. The mock scanned its own seed; this is
         * GET /api/search, which searches columns the registry names and
         * reaches pages and media too — neither of which is a collection the
         * cache holds.
         */
        async search(q) {
            const res = await get('api/search', { q });
            return res.data || [];
        },

        /* ----- singletons ----- */

        async getDoc(key) {
            if (key !== 'settings') {
                throw new Error(`No document called “${key}”`);
            }

            if (!settingsDoc) {
                const res = await get('api/settings');
                settingsDoc = res.data || {};
                snapshotSettings();
            }

            return clone(settingsDoc);
        },

        /**
         * The settings screens read the whole document, change one group and
         * hand the whole thing back — which is what the mock wanted, and what
         * PATCH /api/settings/{group} does not. So the groups that actually
         * moved are the ones sent.
         *
         * Not a micro-optimisation: every write is an activity log entry, and
         * saving the social links should not read as having edited the SMTP
         * password on the same afternoon.
         */
        async setDoc(key, data) {
            if (key !== 'settings') {
                throw new Error(`No document called “${key}”`);
            }

            const changed = Object.keys(data || {}).filter(
                (group) => JSON.stringify(data[group]) !== settingsSnapshot[group]
            );

            for (const group of changed) {
                const res = await patch(`api/settings/${encodeURIComponent(group)}`, data[group]);
                data[group] = res.data;
            }

            settingsDoc = clone(data);
            snapshotSettings();

            return clone(settingsDoc);
        },

        /* ----- dependency guards -----
           A page registers what blocks a delete and confirm() renders the
           list. The server refuses the delete either way — this is what puts
           the reason in front of somebody before they click it. */

        registerDependents(entity, fn) {
            dependentHooks[entity] = fn;
        },

        dependents(entity, id) {
            const fn = dependentHooks[entity];
            if (!fn) return [];
            try {
                return fn(id) || [];
            } catch (e) {
                console.warn('[api] dependent check failed for', entity, e);
                return [];
            }
        },

        /* ----- cache housekeeping ----- */

        /**
         * Put a written record back into the cache, so the lookups on the
         * screen agree with what was just saved without a second request.
         */
        remember(entity, row, previousId, index) {
            if (!row || !cache[entity]) return clone(row);

            const rows = cache[entity];
            const at = rows.findIndex(
                (r) => String(r.id) === String(previousId === undefined ? row.id : previousId)
            );

            if (at === -1) {
                rows.splice(typeof index === 'number' ? index : rows.length, 0, row);
            } else {
                rows[at] = row;
            }

            return clone(row);
        },

        /** Drop a collection so the next allSync() reader gets it fresh. */
        async refresh(entity) {
            return store.all(entity);
        },
    };

    root.TMH = root.TMH || {};
    root.TMH.store = store;
    root.TMH.boot = boot;
    root.TMH.api = {
        request, get, post, patch, del, base: BASE, ready,
        /* {user, permissions}, as GET /api/auth/me answered at boot. Read
           rather than pushed, so api.js does not have to know that session.js
           exists — it is the next script down the list, and a module that
           reaches forward to the one after it is a load order waiting to be
           got wrong. */
        me: () => identity,
    };
}(window));
