/* Enquiries — the inbox. Everything the contact form, chat widget and phone
   desk collect lands here; the detail screen (enquiry-view) does the
   replying. This screen's job is triage: see what is unanswered, filter it
   down, and move a batch of it in one go. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, confirm: confirmDialog } = window.TMH;

    const STATUS = [
        { value: 'all', label: 'All' },
        { value: 'new', label: 'New' },
        { value: 'replied', label: 'Replied' },
        { value: 'closed', label: 'Closed' },
        { value: 'spam', label: 'Spam' },
    ];

    const TONE = {
        new: 'warn', replied: 'info', closed: 'ok', spam: 'off',
    };

    /* The values PublicIntakeController::SOURCES writes, not the words the
       filter prints. Sending a label filtered on a string no row has ever
       held, so the Source control returned an empty table whatever was picked.
       `appointment` was missing from the list altogether. */
    const SOURCES = [
        { value: 'contact', label: 'Contact form' },
        { value: 'appointment', label: 'Appointment form' },
        { value: 'chat', label: 'Chat widget' },
        { value: 'phone', label: 'Phone widget' },
        { value: 'landing', label: 'Landing page' },
    ];

    const sourceLabel = (value) => (SOURCES.find((s) => s.value === value) || {}).label || value || '—';

    /* Date windows. The store has no field to compare against, so each carries
       its own predicate — see the filterFns note in core/store.js. */
    const WINDOWS = [
        { value: '1', label: 'Today' },
        { value: '7', label: 'Last 7 days' },
        { value: '30', label: 'Last 30 days' },
    ];

    let list = null;
    let users = [];

    window.TMH.boot(init);

    async function init() {
        users = (await store.all('users')).filter((u) => u.status !== 'hidden');
        const departments = await store.all('departments');
        const rows = await store.all('enquiries');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Growth' }, { label: 'Enquiries' }],
            title: 'Enquiries',
            sub: 'Everything the public forms collect, in one queue.',
            actions: `
                <a class="btn btn--ghost" href="settings-contact">
                    <i class="fa-solid fa-bell"></i> Who gets notified</a>`,
        });

        const unanswered = rows.filter((r) => r.status === 'new');
        const week = rows.filter((r) => within(r.receivedAt, 7));

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-inbox', 'red', unanswered.length, 'Unanswered', unanswered.length ? 'Needs a reply' : 'Inbox is clear'],
                ['fa-envelope-open-text', 'navy', week.length, 'This week', 'Received in the last 7 days'],
                ['fa-circle-exclamation', 'blue', rows.filter((r) => r.priority === 'high' && r.status !== 'closed').length, 'High priority', 'Open and marked urgent'],
                ['fa-user-slash', 'magenta', rows.filter((r) => !r.assignedTo && r.status !== 'spam').length, 'Unassigned', 'Nobody owns these yet'],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        list = table.create({
            mount: '#listCard',
            entity: 'enquiries',
            searchFields: ['name', 'email', 'phone', 'subject', 'message'],
            searchPlaceholder: 'Search name, subject or message',
            statusOptions: STATUS,
            filters: [
                { key: 'source', label: 'Source', options: SOURCES },
                {
                    key: 'assignedTo',
                    label: 'Assigned',
                    /* '' would read as "no filter" in the store, so unassigned
                       gets a sentinel and a predicate of its own. */
                    options: [{ value: '__none', label: 'Unassigned' }]
                        .concat(users.map((u) => ({ value: u.id, label: u.name }))),
                    match: (r, v) => (v === '__none' ? !r.assignedTo : r.assignedTo === v),
                },
                {
                    key: 'departmentId',
                    label: 'Department',
                    options: departments.map((d) => ({ value: d.id, label: d.name })),
                },
                {
                    key: 'window',
                    label: 'Received',
                    options: WINDOWS,
                    match: (r, v) => within(r.receivedAt, Number(v)),
                },
            ],
            sort: 'receivedAt',
            dir: 'desc',
            rowClass: (r) => (r.status === 'new' ? 'is-unread' : ''),
            bulkActions: [
                { key: 'assign', label: 'Assign', icon: 'fa-user-check', onClick: bulkAssign },
                { key: 'close', label: 'Close', icon: 'fa-circle-check', onClick: (ids, ctl) => bulkStatus(ids, ctl, 'closed') },
                { key: 'spam', label: 'Mark spam', icon: 'fa-ban', onClick: (ids, ctl) => bulkStatus(ids, ctl, 'spam') },
                'delete',
            ],
            columns: [
                {
                    label: 'From', sort: 'name', width: '22%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.name))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.email || r.phone || 'No contact given')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Subject', sort: 'subject', width: '28%',
                    render: (r, s) => `
                        <span class="cell-main">${r.priority === 'high' ? '<i class="fa-solid fa-circle-exclamation" style="color:var(--accent-orange)" title="High priority"></i> ' : ''}${U.mark(r.subject, s.q)}</span>
                        <span class="cell-sub clamp-2">${U.esc(r.message)}</span>`,
                },
                { label: 'Source', sort: 'source', width: '12%', render: (r) => `<span class="chip">${U.esc(sourceLabel(r.source))}</span>` },
                {
                    label: 'Department', width: '13%',
                    render: (r) => {
                        const d = store.allSync('departments').find((x) => x.id === r.departmentId);
                        return d ? U.esc(d.name) : '<span class="muted">General</span>';
                    },
                },
                {
                    label: 'Assigned', sort: 'assignedTo', width: '12%',
                    render: (r) => {
                        const u = users.find((x) => x.id === r.assignedTo);
                        return u ? U.esc(u.name) : '<span class="muted">Unassigned</span>';
                    },
                },
                {
                    label: 'Received', sort: 'receivedAt', width: '10%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.receivedAt))}">${U.esc(U.ago(r.receivedAt))}</span>`,
                },
                {
                    label: 'Status', sort: 'status', width: '9%',
                    render: (r) => statusTag(r.status),
                },
            ],
            rowActions: (row) => [
                { label: 'Open', icon: 'fa-envelope-open', onClick: () => open(row) },
                { label: 'Reply', icon: 'fa-reply', onClick: () => open(row, true) },
                { divider: true },
                ...(row.assignedTo === 'usr-001'
                    ? []
                    : [{ label: 'Assign to me', icon: 'fa-user-check', onClick: () => assign(row, 'usr-001') }]),
                ...(row.status === 'closed'
                    ? [{ label: 'Reopen', icon: 'fa-rotate-left', onClick: () => setStatus(row, 'new') }]
                    : [{ label: 'Mark closed', icon: 'fa-circle-check', onClick: () => setStatus(row, 'closed') }]),
                ...(row.status === 'spam'
                    ? [{ label: 'Not spam', icon: 'fa-inbox', onClick: () => setStatus(row, 'new') }]
                    : [{ label: 'Mark spam', icon: 'fa-ban', onClick: () => setStatus(row, 'spam') }]),
                { divider: true },
                { label: 'Delete', icon: 'fa-trash', danger: true, onClick: () => remove(row) },
            ],
            onRowClick: (row) => open(row),
            empty: {
                icon: 'fa-envelope-open-text', title: 'No enquiries',
                text: 'They will land here once the contact form is wired to the backend.',
            },
        });
    }

    /* ---------- helpers ---------- */

    function statusTag(status) {
        const s = STATUS.find((x) => x.value === status);
        return `<span class="tag ${TONE[status] || 'off'}">${U.esc(s ? s.label : status || 'Unknown')}</span>`;
    }

    function within(iso, days) {
        if (!iso) return false;
        const t = new Date(iso).getTime();
        if (Number.isNaN(t)) return false;
        return Date.now() - t <= days * 86400000;
    }

    function open(row, reply) {
        window.location.href = `enquiry-view?id=${encodeURIComponent(row.id)}${reply ? '&reply=1' : ''}`;
    }

    /* ---------- single-row actions ---------- */

    async function setStatus(row, status) {
        await store.update('enquiries', row.id, { status });
        const label = (STATUS.find((s) => s.value === status) || {}).label;
        toast.success(`${row.name} marked ${String(label).toLowerCase()}`, {
            undo: async () => {
                await store.update('enquiries', row.id, { status: row.status });
                toast.success('Reverted');
                list.load();
            },
        });
        list.load();
    }

    async function assign(row, userId) {
        const user = users.find((u) => u.id === userId);
        await store.update('enquiries', row.id, { assignedTo: userId });
        toast.success(`Assigned to ${user ? user.name : 'nobody'}`, {
            undo: async () => {
                await store.update('enquiries', row.id, { assignedTo: row.assignedTo || '' });
                toast.success('Assignment reverted');
                list.load();
            },
        });
        list.load();
    }

    async function remove(row) {
        const ok = await confirmDialog({
            title: 'Delete this enquiry?',
            body: `${row.name} — "${row.subject}". The message and its replies go with it.`,
            danger: true,
            confirmLabel: 'Delete',
        });
        if (!ok) return;

        const removed = await store.remove('enquiries', row.id);
        toast.success('Enquiry deleted', {
            undo: async () => {
                await store.restore('enquiries', removed.row, removed.index);
                toast.success('Enquiry restored');
                list.load();
            },
        });
        list.load();
    }

    /* ---------- bulk actions ---------- */

    async function bulkAssign(ids, ctl) {
        const userId = await window.TMH.modal.open({
            title: `Assign ${ids.length} enquir${ids.length === 1 ? 'y' : 'ies'}`,
            icon: 'fa-user-check',
            html: `
                <div class="field">
                    <label for="bulkOwner">Owner</label>
                    <select id="bulkOwner">
                        <option value="">Unassigned</option>
                        ${users.map((u) => `<option value="${U.esc(u.id)}">${U.esc(u.name)}</option>`).join('')}
                    </select>
                    <small>Everyone selected is reassigned, including any that already have an owner.</small>
                </div>`,
            footer: `
                <button type="button" class="btn btn--ghost" data-cancel>Cancel</button>
                <button type="button" class="btn btn--primary" data-ok>Assign</button>`,
            onMount(panel, close) {
                panel.querySelector('[data-cancel]').addEventListener('click', () => close(undefined));
                panel.querySelector('[data-ok]').addEventListener('click', () =>
                    close(panel.querySelector('#bulkOwner').value));
            },
        });

        if (userId === undefined) return;

        const before = ids.map((id) => {
            const r = store.allSync('enquiries').find((x) => String(x.id) === String(id));
            return { id, assignedTo: r ? r.assignedTo || '' : '' };
        });

        const res = await store.bulk('enquiries', ids, 'patch', { assignedTo: userId });
        ctl.clear();

        const user = users.find((u) => u.id === userId);
        toast.success(`${res.succeeded.length} assigned to ${user ? user.name : 'nobody'}`, {
            undo: async () => {
                await Promise.all(before.map((b) => store.update('enquiries', b.id, { assignedTo: b.assignedTo })));
                toast.success('Assignment reverted');
                list.load();
            },
        });
        ctl.reload();
    }

    async function bulkStatus(ids, ctl, status) {
        const label = (STATUS.find((s) => s.value === status) || {}).label;

        if (status === 'spam') {
            const ok = await confirmDialog({
                title: `Mark ${ids.length} as spam?`,
                body: 'They stay in the spam filter rather than being deleted, so a mistake is recoverable.',
                confirmLabel: `Mark ${ids.length} spam`,
            });
            if (!ok) return;
        }

        const before = ids.map((id) => {
            const r = store.allSync('enquiries').find((x) => String(x.id) === String(id));
            return { id, status: r ? r.status : 'new' };
        });

        const res = await store.bulk('enquiries', ids, 'patch', { status });
        ctl.clear();

        toast.success(`${res.succeeded.length} marked ${String(label).toLowerCase()}`, {
            undo: async () => {
                await Promise.all(before.map((b) => store.update('enquiries', b.id, { status: b.status })));
                toast.success('Reverted');
                list.load();
            },
        });
        ctl.reload();
    }
}());
