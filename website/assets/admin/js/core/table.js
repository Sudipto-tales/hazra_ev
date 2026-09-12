/* =========================================================
   List-screen controller: toolbar, table, sorting, filters,
   selection, bulk bar, pagination, drag-reorder, empty and
   skeleton states.

   Every list page in the panel is a call to TMH.table.create()
   plus a column definition — see assets/js/pages/doctors.js
   for the reference use.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.TMH.util;
    const esc = U.esc;

    function create(config) {
        const cfg = Object.assign({
            mount: null,
            entity: '',
            title: 'Records',
            columns: [],
            searchFields: [],
            searchPlaceholder: 'Search…',
            statusChips: true,
            statusOptions: [
                { value: 'all', label: 'All' },
                { value: 'published', label: 'Published' },
                { value: 'draft', label: 'Draft' },
                { value: 'hidden', label: 'Hidden' },
            ],
            filters: [],            /* [{key, label, options:[{value,label}]}] */
            sort: 'order',
            dir: 'asc',
            pageSize: 20,
            reorder: false,
            selectable: true,
            bulkActions: ['publish', 'hide', 'delete'],
            rowActions: null,       /* fn(row) -> [{label, icon, danger, onClick}] */
            rowClass: null,         /* fn(row) -> string, e.g. unread rows */
            onRowClick: null,
            empty: { title: 'Nothing here yet', text: '', actionLabel: '', onAction: null },
            addLabel: '',
            onAdd: null,
        }, config);

        const host = typeof cfg.mount === 'string'
            ? document.querySelector(cfg.mount)
            : cfg.mount;

        const state = {
            q: U.param('q', '') || '',
            status: U.param('status', 'all') || 'all',
            filters: {},
            sort: U.param('sort', cfg.sort) || cfg.sort,
            dir: U.param('dir', cfg.dir) || cfg.dir,
            page: Number(U.param('page', 1)) || 1,
            pageSize: cfg.pageSize,
            selected: new Set(),
            rows: [],
            total: 0,
            counts: {},
            loading: true,
        };

        cfg.filters.forEach((f) => {
            state.filters[f.key] = U.param(f.key, 'all') || 'all';
        });

        /* ---------- markup ---------- */

        function toolbarHtml() {
            const chips = cfg.statusChips ? `
                <div class="filter-chips" role="group" aria-label="Filter by status">
                    ${cfg.statusOptions.map((o) => {
                        const n = o.value === 'all' ? state.counts.all : state.counts[o.value];
                        return `<button type="button" data-status="${esc(o.value)}"
                                    aria-pressed="${state.status === o.value}">${esc(o.label)}${
                                        n ? `<span class="count">${n}</span>` : ''}</button>`;
                    }).join('')}
                </div>` : '';

            /* A hidden filter still lives in state and in the URL, but draws no
               select — the page drives it from something else. lab-tests uses
               tabs for its category; a second control saying the same thing
               would only be a way to contradict the first. */
            const selects = cfg.filters.filter((f) => !f.hidden).map((f) => `
                <select data-filter="${esc(f.key)}" aria-label="${esc(f.label)}"
                        style="height:36px;padding:0 30px 0 12px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                    <option value="all">${esc(f.label)}: All</option>
                    ${f.options.map((o) => `<option value="${esc(o.value)}" ${state.filters[f.key] === String(o.value) ? 'selected' : ''}>${esc(o.label)}</option>`).join('')}
                </select>`).join('');

            return `
            <div class="toolbar">
                <div class="toolbar__search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" data-search value="${esc(state.q)}"
                           placeholder="${esc(cfg.searchPlaceholder)}" aria-label="${esc(cfg.searchPlaceholder)}">
                </div>
                ${chips}
                ${selects}
                <div class="grow"></div>
                ${cfg.reorder ? `<span class="pill" title="Drag rows to reorder — only while sorted by order">
                    <i class="fa-solid fa-up-down"></i> ${state.sort === 'order' ? 'Drag to reorder' : 'Sort by order to reorder'}</span>` : ''}
                <span class="pill">${state.total} record${state.total === 1 ? '' : 's'}</span>
            </div>`;
        }

        /* A bulk action is either one of the three store verbs, given as a
           string, or an object {key, label, icon, danger, onClick(ids, ctl)}
           for a workflow the store has no verb for — assigning an enquiry,
           confirming an appointment. Normalising here keeps every caller
           free to mix the two. */
        function bulkList() {
            const labels = { publish: 'Publish', hide: 'Hide', delete: 'Delete' };
            return cfg.bulkActions.map((a) => (typeof a === 'string'
                ? { key: a, label: labels[a] || a, danger: a === 'delete' }
                : a));
        }

        function bulkBarHtml() {
            if (!state.selected.size) return '';
            return `
            <div class="bulk-bar">
                <span>${state.selected.size} selected</span>
                ${bulkList().map((a) => `
                    <button type="button" class="btn btn--sm ${a.danger ? 'btn--danger' : 'btn--ghost'}" data-bulk="${esc(a.key)}">
                        ${a.icon ? `<i class="fa-solid ${esc(a.icon)}"></i> ` : ''}${esc(a.label)}</button>`).join('')}
                <button type="button" class="btn btn--link" data-bulk-clear>Clear</button>
            </div>`;
        }

        function skeletonHtml() {
            const cols = cfg.columns.length + (cfg.selectable ? 1 : 0) + 1;
            return `<tbody>${Array.from({ length: 6 }, () => `
                <tr>${Array.from({ length: cols }, (_, i) => `
                    <td><div class="skel" style="width:${i === 1 ? 70 : 40 + Math.random() * 40}%"></div></td>`).join('')}
                </tr>`).join('')}</tbody>`;
        }

        /* Hidden filters are the page's own scoping, not something the user
           chose here, so "clear filters" leaves them alone and an empty tab
           reads as empty rather than as over-filtered. */
        function isHidden(key) {
            const f = cfg.filters.find((x) => x.key === key);
            return !!(f && f.hidden);
        }

        function emptyHtml() {
            const filtered = state.q.trim() || state.status !== 'all'
                || Object.entries(state.filters).some(([k, v]) => v && v !== 'all' && !isHidden(k));
            if (filtered) {
                return `
                <div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-filter-circle-xmark"></i></div>
                    <h3>No matches</h3>
                    <p>Nothing here fits the current search and filters.</p>
                    <button type="button" class="btn btn--ghost" data-clear-filters>
                        <i class="fa-solid fa-rotate-left"></i> Clear filters</button>
                </div>`;
            }
            return `
            <div class="empty">
                <div class="empty__art"><i class="fa-solid ${esc(cfg.empty.icon || 'fa-inbox')}"></i></div>
                <h3>${esc(cfg.empty.title)}</h3>
                <p>${esc(cfg.empty.text || '')}</p>
                ${cfg.empty.actionLabel ? `<button type="button" class="btn btn--primary" data-empty-action>
                    <i class="fa-solid fa-plus"></i> ${esc(cfg.empty.actionLabel)}</button>` : ''}
            </div>`;
        }

        function headHtml() {
            const allOnPage = state.rows.length
                && state.rows.every((r) => state.selected.has(String(r.id)));
            const someOnPage = state.rows.some((r) => state.selected.has(String(r.id)));
            return `<thead><tr>
                ${cfg.reorder ? '<th style="width:34px"></th>' : ''}
                ${cfg.selectable ? `<th><label class="checkbox">
                    <input type="checkbox" data-select-all ${allOnPage ? 'checked' : ''} ${!allOnPage && someOnPage ? 'data-indeterminate' : ''}>
                    <span class="sr-only">Select all on this page</span></label></th>` : ''}
                ${cfg.columns.map((c) => `<th ${c.sort ? `data-sort="${esc(c.sort)}"` : ''}
                    ${state.sort === c.sort ? `aria-sort="${state.dir === 'asc' ? 'ascending' : 'descending'}"` : ''}
                    ${c.width ? `style="width:${c.width}"` : ''}>${esc(c.label)}</th>`).join('')}
                <th style="width:64px"><span class="sr-only">Actions</span></th>
            </tr></thead>`;
        }

        function bodyHtml() {
            return `<tbody>${state.rows.map((row) => {
                const id = String(row.id);
                const cls = cfg.rowClass ? cfg.rowClass(row) : '';
                return `<tr data-id="${esc(id)}" ${cls ? `class="${esc(cls)}"` : ''} ${cfg.reorder && state.sort === 'order' ? 'draggable="true"' : ''}>
                    ${cfg.reorder ? `<td data-label=""><span class="drag-handle" ${state.sort !== 'order' ? 'style="opacity:.25;cursor:not-allowed"' : ''}><i class="fa-solid fa-grip-vertical"></i></span></td>` : ''}
                    ${cfg.selectable ? `<td data-label=""><label class="checkbox"><input type="checkbox" data-row-select ${state.selected.has(id) ? 'checked' : ''}><span class="sr-only">Select</span></label></td>` : ''}
                    ${cfg.columns.map((c) => `<td data-label="${esc(c.label)}" ${c.cellClass ? `class="${esc(c.cellClass)}"` : ''}>${c.render ? c.render(row, state) : esc(row[c.key])}</td>`).join('')}
                    <td class="cell-actions" data-label="">
                        <div class="menu-wrap">
                            <button type="button" class="icon-btn" data-menu aria-haspopup="true" aria-label="Row actions">
                                <i class="fa-solid fa-ellipsis-vertical"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('')}</tbody>`;
        }

        function pagerHtml() {
            const pages = Math.max(1, Math.ceil(state.total / state.pageSize));
            if (state.total === 0) return '';
            const from = (state.page - 1) * state.pageSize + 1;
            const to = Math.min(state.total, state.page * state.pageSize);

            const nums = [];
            for (let p = 1; p <= pages; p += 1) {
                if (p === 1 || p === pages || Math.abs(p - state.page) <= 1) nums.push(p);
                else if (nums[nums.length - 1] !== '…') nums.push('…');
            }

            return `
            <div class="pager">
                <span>Showing ${from}–${to} of ${state.total}</span>
                <div class="row gap-2">
                    <select data-page-size aria-label="Rows per page"
                            style="height:32px;padding:0 26px 0 10px;border:1px solid var(--hairline);border-radius:var(--radius-xs);background:var(--surface-2);font-size:var(--fs-sm)">
                        ${[20, 50, 100].map((n) => `<option value="${n}" ${state.pageSize === n ? 'selected' : ''}>${n} / page</option>`).join('')}
                    </select>
                    <div class="pager__pages">
                        <button type="button" data-page="${state.page - 1}" ${state.page === 1 ? 'disabled' : ''} aria-label="Previous page"><i class="fa-solid fa-angle-left"></i></button>
                        ${nums.map((n) => (n === '…'
                            ? '<button type="button" disabled>…</button>'
                            : `<button type="button" data-page="${n}" ${n === state.page ? 'aria-current="page"' : ''}>${n}</button>`)).join('')}
                        <button type="button" data-page="${state.page + 1}" ${state.page >= pages ? 'disabled' : ''} aria-label="Next page"><i class="fa-solid fa-angle-right"></i></button>
                    </div>
                </div>
            </div>`;
        }

        /* The toolbar lives inside the block innerHTML replaces, so every
           repaint destroys the control the user is typing in. A search runs
           two repaints — skeleton, then rows — with the store's latency in
           between, which is why an unguarded paint eats a keystroke and drops
           the caret after the first letter. The focused control is therefore
           carried across the swap: which control it was, what is in it now,
           and where the caret sat. */
        const FOCUS_KEYS = ['[data-search]', '[data-page-size]'];

        function focusKey(el) {
            const hit = FOCUS_KEYS.find((k) => el.matches(k));
            if (hit) return hit;
            if (el.matches('[data-filter]')) return `[data-filter="${CSS.escape(el.dataset.filter)}"]`;
            return null;
        }

        function captureFocus() {
            const el = document.activeElement;
            if (!el || !host.contains(el)) return null;
            const key = focusKey(el);
            if (!key) return null;
            const snap = { key, value: el.value };
            try {
                snap.start = el.selectionStart;
                snap.end = el.selectionEnd;
            } catch (e) { /* the control has no text selection */ }
            return snap;
        }

        function restoreFocus(snap) {
            if (!snap) return;
            const el = host.querySelector(snap.key);
            if (!el) return;
            /* Letters typed while the store was working are newer than the
               state this paint was built from, so they win. */
            if (el.value !== snap.value) el.value = snap.value;
            el.focus();
            if (snap.start != null) {
                try {
                    el.setSelectionRange(snap.start, snap.end);
                } catch (e) { /* select elements have no range */ }
            }
        }

        function paint() {
            const focus = captureFocus();
            host.innerHTML = `
                ${toolbarHtml()}
                ${bulkBarHtml()}
                ${state.loading
                    ? `<div class="table-wrap"><table class="data-table">${headHtml()}${skeletonHtml()}</table></div>`
                    : (state.rows.length
                        ? `<div class="table-wrap"><table class="data-table">${headHtml()}${bodyHtml()}</table></div>${pagerHtml()}`
                        : emptyHtml())}`;
            wire();
            restoreFocus(focus);
        }

        /* ---------- events ---------- */

        function syncUrl() {
            const patch = {
                q: state.q.trim(),
                status: state.status,
                sort: state.sort === cfg.sort ? '' : state.sort,
                dir: state.dir === cfg.dir ? '' : state.dir,
                page: state.page === 1 ? '' : state.page,
            };
            Object.entries(state.filters).forEach(([k, v]) => { patch[k] = v; });
            U.setParams(patch);
        }

        function wire() {
            const $ = (sel) => host.querySelector(sel);
            const $$ = (sel) => [...host.querySelectorAll(sel)];

            const searchEl = $('[data-search]');
            if (searchEl) {
                /* Stored raw, trimmed only where it is used: trimming here
                   would strip the space the user just typed between two words
                   and leave them typing "heartsurgery". */
                searchEl.addEventListener('input', U.debounce(() => {
                    state.q = searchEl.value;
                    state.page = 1;
                    load();
                }, 220));
            }

            $$('[data-status]').forEach((b) => b.addEventListener('click', () => {
                state.status = b.dataset.status;
                state.page = 1;
                state.selected.clear();
                load();
            }));

            $$('[data-filter]').forEach((s) => s.addEventListener('change', () => {
                state.filters[s.dataset.filter] = s.value;
                state.page = 1;
                load();
            }));

            $$('th[data-sort]').forEach((th) => th.addEventListener('click', () => {
                const key = th.dataset.sort;
                if (state.sort === key) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                else {
                    state.sort = key;
                    state.dir = 'asc';
                }
                load();
            }));

            const selectAll = $('[data-select-all]');
            if (selectAll) {
                if (selectAll.hasAttribute('data-indeterminate')) selectAll.indeterminate = true;
                selectAll.addEventListener('change', () => {
                    state.rows.forEach((r) => {
                        if (selectAll.checked) state.selected.add(String(r.id));
                        else state.selected.delete(String(r.id));
                    });
                    paint();
                });
            }

            $$('[data-row-select]').forEach((cb) => cb.addEventListener('change', (e) => {
                e.stopPropagation();
                const id = cb.closest('tr').dataset.id;
                if (cb.checked) state.selected.add(id);
                else state.selected.delete(id);
                paint();
            }));

            $$('[data-bulk]').forEach((b) => b.addEventListener('click', () => runBulk(b.dataset.bulk)));
            const clearSel = $('[data-bulk-clear]');
            if (clearSel) clearSel.addEventListener('click', () => {
                state.selected.clear();
                paint();
            });

            $$('[data-page]').forEach((b) => b.addEventListener('click', () => {
                state.page = Number(b.dataset.page);
                load();
            }));

            const sizeEl = $('[data-page-size]');
            if (sizeEl) sizeEl.addEventListener('change', () => {
                state.pageSize = Number(sizeEl.value);
                state.page = 1;
                load();
            });

            const clearFilters = $('[data-clear-filters]');
            if (clearFilters) clearFilters.addEventListener('click', () => {
                state.q = '';
                state.status = 'all';
                Object.keys(state.filters).forEach((k) => {
                    if (!isHidden(k)) state.filters[k] = 'all';
                });
                state.page = 1;
                load();
            });

            const emptyAction = $('[data-empty-action]');
            if (emptyAction && cfg.empty.onAction) emptyAction.addEventListener('click', cfg.empty.onAction);

            /* row action menus */
            $$('[data-menu]').forEach((btn) => btn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeMenus();
                const tr = btn.closest('tr');
                const row = state.rows.find((r) => String(r.id) === tr.dataset.id);
                const actions = cfg.rowActions ? cfg.rowActions(row) : [];
                if (!actions.length) return;

                const menu = document.createElement('div');
                menu.className = 'menu';
                menu.setAttribute('role', 'menu');
                menu.innerHTML = actions.map((a) => (a.divider
                    ? '<hr>'
                    : `<button type="button" role="menuitem" class="${a.danger ? 'danger' : ''}">
                            <i class="fa-solid ${esc(a.icon || 'fa-circle')}"></i> ${esc(a.label)}</button>`)).join('');

                [...menu.querySelectorAll('button')].forEach((mb, i) => {
                    const act = actions.filter((a) => !a.divider)[i];
                    mb.addEventListener('click', () => {
                        closeMenus();
                        if (act && act.onClick) act.onClick(row);
                    });
                });

                btn.parentElement.appendChild(menu);
                /* Flip upward when the menu would fall off the viewport. */
                const rect = menu.getBoundingClientRect();
                if (rect.bottom > innerHeight - 8) menu.style.top = `-${rect.height + 4}px`;
            }));

            if (cfg.onRowClick) {
                $$('tbody tr').forEach((tr) => tr.addEventListener('click', (e) => {
                    if (e.target.closest('button, a, label, input, .drag-handle')) return;
                    const row = state.rows.find((r) => String(r.id) === tr.dataset.id);
                    if (row) cfg.onRowClick(row);
                }));
            }

            if (cfg.reorder && state.sort === 'order') wireDrag();
        }

        function closeMenus() {
            document.querySelectorAll('.menu-wrap .menu').forEach((m) => m.remove());
        }
        document.addEventListener('click', closeMenus);

        function wireDrag() {
            let dragged = null;
            const rows = [...host.querySelectorAll('tbody tr')];

            rows.forEach((tr) => {
                tr.addEventListener('dragstart', (e) => {
                    dragged = tr;
                    tr.classList.add('is-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    /* Firefox needs data set or the drag never starts. */
                    e.dataTransfer.setData('text/plain', tr.dataset.id);
                });
                tr.addEventListener('dragend', () => {
                    tr.classList.remove('is-dragging');
                    rows.forEach((r) => r.classList.remove('drop-target'));
                });
                tr.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (tr !== dragged) tr.classList.add('drop-target');
                });
                tr.addEventListener('dragleave', () => tr.classList.remove('drop-target'));
                tr.addEventListener('drop', async (e) => {
                    e.preventDefault();
                    tr.classList.remove('drop-target');
                    if (!dragged || tr === dragged) return;
                    const body = tr.parentElement;
                    const all = [...body.children];
                    const from = all.indexOf(dragged);
                    const to = all.indexOf(tr);
                    body.insertBefore(dragged, from < to ? tr.nextSibling : tr);
                    const ids = [...body.children].map((r) => r.dataset.id);
                    await root.TMH.store.reorder(cfg.entity, ids);
                    root.TMH.toast.success('Order saved');
                    load();
                });
            });
        }

        async function runBulk(action) {
            const ids = [...state.selected];
            if (!ids.length) return;

            const custom = bulkList().find((a) => a.key === action && a.onClick);
            if (custom) {
                await custom.onClick(ids, {
                    clear: () => { state.selected.clear(); },
                    reload: load,
                });
                return;
            }

            if (action === 'delete') {
                const sample = ids.slice(0, 3)
                    .map((id) => {
                        const r = state.rows.find((x) => String(x.id) === id);
                        return r ? (r.name || r.title || id) : id;
                    });
                const ok = await root.TMH.confirm({
                    title: `Delete ${ids.length} record${ids.length === 1 ? '' : 's'}?`,
                    body: `${sample.join(', ')}${ids.length > 3 ? ` and ${ids.length - 3} more` : ''}. This cannot be undone in bulk.`,
                    danger: true,
                    confirmLabel: `Delete ${ids.length}`,
                    typeToConfirm: ids.length >= 10 ? 'delete' : null,
                });
                if (!ok) return;
            }

            const res = await root.TMH.store.bulk(cfg.entity, ids, action);
            state.selected.clear();

            if (res.failed.length) {
                root.TMH.toast.warning(
                    `${res.succeeded.length} of ${ids.length} ${action === 'delete' ? 'deleted' : `${action}ed`}`,
                    {
                        body: `${res.failed.length} could not be processed.`,
                        action: {
                            label: 'View details',
                            onClick: () => root.TMH.confirm({
                                title: 'What failed',
                                blocked: true,
                                icon: 'fa-triangle-exclamation',
                                dependents: res.failed.map((f) => `${f.id} — ${f.reason}`),
                            }),
                        },
                    },
                );
            } else {
                const verb = { publish: 'published', hide: 'hidden', delete: 'deleted' }[action] || 'updated';
                root.TMH.toast.success(`${res.succeeded.length} record${res.succeeded.length === 1 ? '' : 's'} ${verb}`);
            }
            load();
        }

        /* ---------- data ---------- */

        /* Two keystrokes can put two list() calls in flight, and the mock
           store's latency is random, so the first can land last and repaint
           the table with results for a query the user has already moved past.
           Only the newest load is allowed to write to state. */
        let loadSeq = 0;

        async function load() {
            const seq = loadSeq + 1;
            loadSeq = seq;

            state.loading = true;
            paint();
            syncUrl();

            /* Filters that are not a plain field comparison — a date window,
               say — carry a match(row, value). They are handed to the store
               separately so filtering still happens before paging; post-load
               filtering would corrupt the page count. */
            const filterFns = {};
            cfg.filters.forEach((f) => {
                if (f.match) filterFns[f.key] = f.match;
            });

            const res = await root.TMH.store.list(cfg.entity, {
                q: state.q.trim(),
                searchFields: cfg.searchFields,
                status: state.status,
                filters: state.filters,
                filterFns,
                sort: state.sort,
                dir: state.dir,
                page: state.page,
                pageSize: state.pageSize,
            });

            if (seq !== loadSeq) return res;

            /* A delete that empties the last page should not strand the user
               on a blank page 3. */
            const pages = Math.max(1, Math.ceil(res.total / state.pageSize));
            if (state.page > pages) {
                state.page = pages;
                return load();
            }

            state.rows = res.rows;
            state.total = res.total;
            state.counts = res.counts;
            state.loading = false;
            paint();
            return res;
        }

        /* ---------- public ---------- */

        const api = {
            load,
            state,
            /* Highlights a freshly created row for 2s, then lets the flash
               animation fade it out. */
            flash(id) {
                const tr = host.querySelector(`tr[data-id="${CSS.escape(String(id))}"]`);
                if (tr) tr.classList.add('is-new');
            },
            /* The standard delete flow: dependency check → confirm → remove →
               toast with Undo. Every list page calls this rather than
               hand-rolling it. */
            async confirmDelete(row, opts) {
                const o = opts || {};
                const label = o.label || row.name || row.title || row.id;
                const deps = root.TMH.store.dependents(cfg.entity, row.id);

                if (deps.length && !o.allowForce) {
                    await root.TMH.confirm({
                        title: `Cannot delete ${label}`,
                        body: 'Other records depend on it. Reassign them first.',
                        blocked: true,
                        danger: true,
                        icon: 'fa-link-slash',
                        dependents: deps,
                    });
                    return false;
                }

                const ok = await root.TMH.confirm({
                    title: `Delete ${label}?`,
                    body: o.body || 'This removes it from the website immediately.',
                    danger: true,
                    confirmLabel: 'Delete',
                    dependents: deps.length ? deps : null,
                });
                if (!ok) return false;

                const removed = await root.TMH.store.remove(cfg.entity, row.id);
                root.TMH.toast.success(`${label} deleted`, {
                    undo: async () => {
                        await root.TMH.store.restore(cfg.entity, removed.row, removed.index);
                        root.TMH.toast.success('Restored');
                        load();
                    },
                });
                load();
                return true;
            },
        };

        load();
        return api;
    }

    root.TMH.table = { create };
}(window));
