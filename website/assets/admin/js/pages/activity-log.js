/* Activity Log — docs/03-page-specs.md §41.

   Reverse-chronological, filtered by user, collection, action and a date
   window. Expanding a row shows the field-level diff.

   This is the one screen nobody visits until something has gone wrong, which
   is why every row carries its IP and why nothing here writes: the log is the
   record of what was done, and a record that can be edited is not one. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, modal } = window.TMH;

    /* Where a collection's records are edited. Same map the dashboard uses;
       both turn a name the server gave them into a screen. */
    const SCREEN = {
        doctors: 'doctors', leadership: 'leadership', departments: 'departments',
        facilities: 'facilities', 'lab-tests': 'lab-tests', posts: 'blog',
        categories: 'blog-categories', testimonials: 'testimonials', faqs: 'faqs',
        counters: 'stats', 'nav-items': 'navigation', redirects: 'redirects',
        jobs: 'jobs', applications: 'applications', enquiries: 'enquiries',
        appointments: 'appointments', users: 'users', roles: 'users',
        media: 'gallery', pages: 'pages', settings: 'settings-general',
    };

    const NOUN = {
        doctors: 'Doctors', leadership: 'Leadership', departments: 'Departments',
        facilities: 'Facilities', 'lab-tests': 'Lab tests', posts: 'Blog',
        categories: 'Categories', testimonials: 'Testimonials', faqs: 'FAQs',
        counters: 'Counters', 'nav-items': 'Navigation', redirects: 'Redirects',
        jobs: 'Vacancies', applications: 'Applications', enquiries: 'Enquiries',
        appointments: 'Appointments', users: 'Users', roles: 'Roles', media: 'Media',
        pages: 'Pages', settings: 'Settings', auth: 'Account',
    };

    /* `view` is the CV stream in ApplicationController — somebody opened a
       named person's CV, and that is worth a line in the log even though it
       changed nothing. */
    const ACTIONS = [
        { value: 'create', label: 'Created', tone: 'ok', icon: 'fa-plus' },
        { value: 'update', label: 'Updated', tone: 'info', icon: 'fa-pen' },
        { value: 'publish', label: 'Published', tone: 'ok', icon: 'fa-globe' },
        { value: 'delete', label: 'Deleted', tone: 'bad', icon: 'fa-trash' },
        { value: 'restore', label: 'Restored', tone: 'info', icon: 'fa-rotate-left' },
        { value: 'view', label: 'Viewed', tone: 'off', icon: 'fa-eye' },
        { value: 'login', label: 'Signed in', tone: 'off', icon: 'fa-arrow-right-to-bracket' },
        { value: 'logout', label: 'Signed out', tone: 'off', icon: 'fa-arrow-right-from-bracket' },
    ];

    const WINDOWS = [
        { value: '1', label: 'Today' },
        { value: '7', label: 'Last 7 days' },
        { value: '30', label: 'Last 30 days' },
    ];

    const actionOf = (value) => ACTIONS.find((a) => a.value === value)
        || { value, label: value, tone: 'off', icon: 'fa-circle' };
    const nounFor = (entity) => NOUN[entity] || entity || '—';

    let users = [];

    window.TMH.boot(init);

    async function init() {
        users = store.available('users') ? await store.all('users') : [];

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Activity Log' }],
            title: 'Activity Log',
            sub: 'Every change anyone has made, and who made it.',
        });

        document.getElementById('view').innerHTML = `
            <div class="banner banner--info">
                <i class="fa-solid fa-circle-info"></i>
                <span><b>The log is read-only.</b> Entries are never edited or removed — that is the
                    point of keeping one. A deleted record can be brought back from its row; an edit
                    is undone on the record itself.</span>
            </div>
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        table.create({
            mount: '#listCard',
            entity: 'activity',
            searchFields: ['summary', 'userName', 'entityId'],
            searchPlaceholder: 'Search what was done',
            statusChips: false,
            selectable: false,
            bulkActions: [],
            sort: 'at',
            dir: 'desc',
            pageSize: 25,
            filters: [
                {
                    key: 'userId', label: 'User',
                    options: users.map((u) => ({ value: u.id, label: u.name })),
                },
                {
                    key: 'entity', label: 'Collection',
                    options: Object.keys(NOUN).map((k) => ({ value: k, label: NOUN[k] })),
                },
                {
                    key: 'action', label: 'Action',
                    options: ACTIONS.map((a) => ({ value: a.value, label: a.label })),
                },
                {
                    key: 'withinDays', label: 'When',
                    options: WINDOWS,
                    /* The store has no field to compare a window against, so
                       it carries its own predicate — the same shape the
                       enquiries screen uses. The backend reads the identically
                       named query parameter. */
                    match: (row, value) => {
                        const days = Number(value);
                        if (!days) return true;
                        const from = new Date();
                        from.setHours(0, 0, 0, 0);
                        from.setDate(from.getDate() - (days - 1));
                        return new Date(row.at) >= from;
                    },
                },
            ],
            columns: [
                {
                    label: 'Who', sort: 'userName', width: '18%',
                    render: (r, s) => {
                        const name = nameOf(r);
                        return `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(name))}</span>
                            <span>
                                <span class="cell-main">${U.mark(name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.ip || '')}</span>
                            </span>
                        </div>`;
                    },
                },
                {
                    label: 'Action', sort: 'action', width: '11%',
                    render: (r) => {
                        const a = actionOf(r.action);
                        return `<span class="tag ${U.esc(a.tone)}">${U.esc(a.label)}</span>`;
                    },
                },
                {
                    label: 'What', width: '39%',
                    render: (r, s) => {
                        const changed = Object.keys(r.diff || {}).length;
                        return `
                            <span class="cell-main">${U.mark(r.summary || `${actionOf(r.action).label} ${nounFor(r.entity)}`, s.q)}</span>
                            ${changed
                                ? `<span class="cell-sub">${changed} field${changed === 1 ? '' : 's'} changed</span>`
                                : (r.entityId ? `<span class="cell-sub">${U.esc(r.entityId)}</span>` : '')}`;
                    },
                },
                {
                    label: 'Collection', sort: 'entity', width: '14%',
                    render: (r) => (r.entity && SCREEN[r.entity]
                        ? `<a href="${U.esc(SCREEN[r.entity])}">${U.esc(nounFor(r.entity))}</a>`
                        : `<span class="muted">${U.esc(nounFor(r.entity))}</span>`),
                },
                {
                    label: 'When', sort: 'at', width: '18%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.at))}">${U.esc(U.ago(r.at))}</span>`,
                },
            ],
            rowActions: (row) => {
                const actions = [{ label: 'Show details', icon: 'fa-eye', onClick: () => openEntry(row) }];

                if (row.entity && SCREEN[row.entity] && row.entityId && row.action !== 'delete') {
                    actions.push({
                        label: `Open in ${nounFor(row.entity)}`,
                        icon: 'fa-arrow-up-right-from-square',
                        onClick: () => { location.href = `${SCREEN[row.entity]}?q=${encodeURIComponent(row.entityId)}`; },
                    });
                }

                /* Only a delete can be undone, and only through the
                   collection's own restore endpoint. An update cannot: the
                   diff stores a summary of long values, not the values, so
                   "reverting" one would write the summary into the record.
                   See api/controllers/DashboardController.php. */
                if (canRestore(row)) {
                    actions.push({ divider: true });
                    actions.push({
                        label: 'Restore this record', icon: 'fa-rotate-left',
                        onClick: () => restore(row),
                    });
                }

                return actions;
            },
            onRowClick: openEntry,
            empty: {
                icon: 'fa-clock-rotate-left',
                title: 'Nothing logged yet',
                text: 'Every create, edit, delete and sign-in lands here from the moment somebody makes one.',
            },
        });
    }

    /* The name is on the row because the log stores it — an account can be
       deleted and the entry still has to say who did the thing. The lookup is
       a fallback for rows that carry only an id. */
    function nameOf(row) {
        return row.userName
            || (users.find((u) => u.id === row.userId) || {}).name
            || 'Deleted account';
    }

    /* The server decides this — it is the only side that knows whether the
       record is still there under a deleted_at. The fallback is for the
       seeded rows, which predate the field. */
    function canRestore(row) {
        return typeof row.revertable === 'boolean'
            ? row.revertable
            : row.action === 'delete' && !!row.entityId && !!SCREEN[row.entity];
    }

    /* ---------------------------------------------------------
       One entry, in full
       --------------------------------------------------------- */

    function openEntry(row) {
        const a = actionOf(row.action);
        const diff = row.diff || {};
        const fields = Object.keys(diff);

        modal.drawer({
            title: row.summary || `${a.label} ${nounFor(row.entity)}`,
            html: `
                <dl class="kv mb-4">
                    <dt>Who</dt><dd>${U.esc(nameOf(row))}</dd>
                    <dt>Action</dt><dd><span class="tag ${U.esc(a.tone)}">${U.esc(a.label)}</span></dd>
                    <dt>Collection</dt><dd>${U.esc(nounFor(row.entity))}</dd>
                    <dt>Record</dt><dd>${row.entityId ? U.esc(row.entityId) : '<span class="muted">—</span>'}</dd>
                    <dt>When</dt><dd>${U.esc(U.fmtDateTime(row.at))}</dd>
                    <dt>IP address</dt><dd>${row.ip ? U.esc(row.ip) : '<span class="muted">Not recorded</span>'}</dd>
                </dl>

                <h3 class="mb-2">What changed</h3>
                ${fields.length
                    ? `<div class="diff">
                        <span class="diff__label">Field</span>
                        <span class="diff__label">Was</span>
                        <span class="diff__label">Is now</span>
                        ${fields.map((f) => `
                            <span>${U.esc(f)}</span>
                            <span class="diff__from">${value(diff[f].from)}</span>
                            <span class="diff__to">${value(diff[f].to)}</span>`).join('')}
                       </div>`
                    : `<p class="empty--sm">${U.esc(noDiffReason(row))}</p>`}`,
        });
    }

    /* A long value is stored as a description of itself ("1,240 characters"),
       so the log cannot outgrow the content it describes. An empty one has to
       say so — a blank cell in a diff reads as "unchanged". */
    function value(v) {
        if (v === null || v === undefined || v === '') return '<em>empty</em>';
        if (typeof v === 'boolean') return v ? 'Yes' : 'No';
        return U.esc(String(v));
    }

    function noDiffReason(row) {
        if (row.action === 'create') return 'The record was created here — everything on it is new.';
        if (row.action === 'delete') return 'The record was deleted. Restore it to see its contents again.';
        if (row.action === 'view') return 'Nothing was changed — a file was opened.';
        if (row.action === 'login' || row.action === 'logout') return 'A sign-in leaves no record to compare.';
        return 'No field-level detail was recorded for this entry.';
    }

    /* ---------------------------------------------------------
       Restore
       --------------------------------------------------------- */

    async function restore(row) {
        const ok = await window.TMH.confirm({
            title: 'Restore this record?',
            body: `“${row.summary || row.entityId}” goes back into ${nounFor(row.entity)} as it was when it was deleted.`,
            icon: 'fa-rotate-left',
            confirmLabel: 'Restore it',
        });

        if (!ok) return;

        try {
            await store.revert(row.entity, row.entityId);
            toast.success('Restored', {
                body: `Back in ${nounFor(row.entity)}.`,
                action: SCREEN[row.entity]
                    ? { label: 'Open', onClick: () => { location.href = SCREEN[row.entity]; } }
                    : null,
            });
        } catch (e) {
            toast.error('Not restored', { body: e.message || 'Something went wrong.' });
        }
    }
}());
