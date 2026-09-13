/* Contact Enquiries — list & message management. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, confirm: confirmDialog } = window.HAZRA;

    const STATUS = [
        { value: 'all', label: 'All Statuses' },
        { value: 'new', label: 'New' },
        { value: 'contacted', label: 'Contacted' },
        { value: 'read', label: 'Read' },
        { value: 'archived', label: 'Archived' },
    ];

    const TONE = {
        new: 'warn', contacted: 'info', read: 'ok', archived: 'off',
    };

    let list = null;

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Leads' }, { label: 'Contact' }],
            title: 'Contact Enquiries',
            sub: 'Contact form submissions and messages received from the website.',
            actions: `
                <a class="btn btn--ghost" href="/admin/settings">
                    <i class="fa-solid fa-sliders"></i> Email Rules</a>`,
        });

        document.getElementById('view').innerHTML = `
            <div id="statStrip" class="mb-4"></div>
            <article class="card card--flush" id="listCard"></article>`;

        paintStats();

        list = table.create({
            mount: '#listCard',
            entity: 'contact',
            searchFields: ['name', 'email', 'phone', 'details'],
            searchPlaceholder: 'Search name, subject, phone or email',
            statusOptions: STATUS,
            sort: 'created_at',
            dir: 'desc',
            rowClass: (r) => (r.status === 'new' ? 'is-unread' : ''),
            columns: [
                {
                    label: 'From', sort: 'name', width: '25%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid);background:var(--surface-3);border-radius:50%;width:32px;height:32px;">${U.esc(U.initials(r.name || 'CE'))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.name || 'Anonymous', s.q)}</span>
                                <span class="cell-sub">${U.esc(r.email || r.phone || 'No contact')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Message / Subject', width: '35%',
                    render: (r, s) => {
                        const d = r.details || {};
                        const subject = d.subject || r.subject || 'General Inquiry';
                        const msg = d.message || r.message || d.comments || '';
                        return `
                            <span class="cell-main">${U.mark(subject, s.q)}</span>
                            ${msg ? `<span class="cell-sub clamp-2">${U.esc(msg)}</span>` : ''}`;
                    },
                },
                {
                    label: 'Received', sort: 'created_at', width: '15%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.created_at))}">${U.esc(U.ago(r.created_at))}</span>`,
                },
                {
                    label: 'Status', sort: 'status', width: '13%',
                    render: (r) => statusTag(r.status),
                },
            ],
            rowActions: (row) => [
                { label: 'Mark Contacted', icon: 'fa-phone', onClick: () => updateStatus(row, 'contacted') },
                { label: 'Mark Read', icon: 'fa-check', onClick: () => updateStatus(row, 'read') },
                { label: 'Archive', icon: 'fa-box-archive', onClick: () => updateStatus(row, 'archived') },
                { divider: true },
                { label: 'Delete', icon: 'fa-trash', danger: true, onClick: () => remove(row) },
            ],
            empty: {
                icon: 'fa-envelope', title: 'No contact enquiries',
                text: 'Contact form submissions will appear here.',
            },
        });
    }

    function statusTag(status) {
        const s = STATUS.find((x) => x.value === status);
        const label = s ? s.label : (status ? status.toUpperCase() : 'NEW');
        return `<span class="tag ${TONE[status] || 'off'}">${U.esc(label)}</span>`;
    }

    async function updateStatus(row, status) {
        try {
            await store.update('contact', row.id, { status });
            toast.success(`Enquiry marked ${status}`);
            list.load();
        } catch (err) {
            toast.error('Failed to update status: ' + (err.message || 'Error'));
        }
    }

    async function remove(row) {
        const ok = await confirmDialog({
            title: 'Delete contact enquiry?',
            body: `Delete enquiry from ${row.name || 'Customer'}. This action cannot be undone.`,
            danger: true,
            confirmLabel: 'Delete Permanently',
        });
        if (!ok) return;

        try {
            await store.remove('contact', row.id);
            toast.success('Contact enquiry deleted');
            list.load();
            paintStats();
        } catch (err) {
            toast.error('Failed to delete enquiry: ' + (err.message || 'Error'));
        }
    }

    async function paintStats() {
        const rows = await store.all('contact');
        const total = rows.length;
        const pending = rows.filter(r => r.status === 'new').length;
        const contacted = rows.filter(r => r.status === 'contacted').length;
        const read = rows.filter(r => r.status === 'read' || r.status === 'archived').length;

        const slot = document.getElementById('statStrip');
        if (slot) {
            slot.innerHTML = U.statStrip([
                ['fa-envelope', 'red', total, 'Total Enquiries', 'Website form submissions'],
                ['fa-circle-exclamation', 'warn', pending, 'New Messages', 'Needs response'],
                ['fa-reply', 'navy', contacted, 'Contacted', 'Replied to customer'],
                ['fa-box-archive', 'blue', read, 'Read / Archived', 'Processed'],
            ]);
        }
    }
}());