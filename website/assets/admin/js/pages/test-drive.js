/* Test Drive Leads — filtered enquiries list. */
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

    let list = null;
    let users = [];

    window.TMH.boot(init);

    async function init() {
        users = (await store.all('users')).filter((u) => u.status !== 'hidden');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Leads' }, { label: 'Test Drive' }],
            title: 'Test Drive Leads',
            sub: 'Test drive requests from the website.',
            actions: `
                <a class="btn btn--ghost" href="settings-contact">
                    <i class="fa-solid fa-bell"></i> Who gets notified</a>`,
        });

        document.getElementById('view').innerHTML = `<article class="card card--flush" id="listCard"></article>`;

        list = table.create({
            mount: '#listCard',
            entity: 'enquiries',
            searchFields: ['name', 'email', 'phone', 'subject', 'message'],
            searchPlaceholder: 'Search name, subject or message',
            statusOptions: STATUS,
            filters: [
                { key: 'source', label: 'Source', options: [{ value: 'appointment', label: 'Test Drive' }] },
                {
                    key: 'assignedTo',
                    label: 'Assigned',
                    options: [{ value: '__none', label: 'Unassigned' }]
                        .concat(users.map((u) => ({ value: u.id, label: u.name }))),
                    match: (r, v) => (v === '__none' ? !r.assignedTo : r.assignedTo === v),
                },
            ],
            sort: 'receivedAt',
            dir: 'desc',
            rowClass: (r) => (r.status === 'new' ? 'is-unread' : ''),
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
                    label: 'Vehicle / Message', sort: 'subject', width: '30%',
                    render: (r, s) => `
                        <span class="cell-main">${r.priority === 'high' ? '<i class="fa-solid fa-circle-exclamation" style="color:var(--accent-orange)" title="High priority"></i> ' : ''}${U.mark(r.subject, s.q)}</span>
                        <span class="cell-sub clamp-2">${U.esc(r.message)}</span>`,
                },
                {
                    label: 'Assigned', sort: 'assignedTo', width: '15%',
                    render: (r) => {
                        const u = users.find((x) => x.id === r.assignedTo);
                        return u ? U.esc(u.name) : '<span class="muted">Unassigned</span>';
                    },
                },
                {
                    label: 'Received', sort: 'receivedAt', width: '15%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.receivedAt))}">${U.esc(U.ago(r.receivedAt))}</span>`,
                },
                {
                    label: 'Status', sort: 'status', width: '12%',
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
                icon: 'fa-road', title: 'No test drive leads',
                text: 'Test drive requests will appear here.',
            },
        });
    }

    function statusTag(status) {
        const s = STATUS.find((x) => x.value === status);
        return `<span class="tag ${TONE[status] || 'off'}">${U.esc(s ? s.label : status || 'Unknown')}</span>`;
    }

    function open(row, reply) {
        window.location.href = `enquiry-view?id=${encodeURIComponent(row.id)}${reply ? '&reply=1' : ''}`;
    }

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
            title: 'Delete this test drive lead?',
            body: `${row.name} — "${row.subject}". The message and its replies go with it.`,
            danger: true,
            confirmLabel: 'Delete',
        });
        if (!ok) return;

        const removed = await store.remove('enquiries', row.id);
        toast.success('Test drive lead deleted', {
            undo: async () => {
                await store.restore('enquiries', removed.row, removed.index);
                toast.success('Restored');
                list.load();
            },
        });
        list.load();
    }
}());