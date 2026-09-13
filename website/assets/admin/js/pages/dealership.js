/* Dealership Leads — list & application management. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, confirm: confirmDialog } = window.HAZRA;

    const STATUS = [
        { value: 'all', label: 'All Statuses' },
        { value: 'new', label: 'New' },
        { value: 'contacted', label: 'Contacted' },
        { value: 'approved', label: 'Approved' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'archived', label: 'Archived' },
    ];

    const TONE = {
        new: 'warn', contacted: 'info', approved: 'ok', rejected: 'off', archived: 'off',
    };

    let list = null;

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Leads' }, { label: 'Dealership' }],
            title: 'Dealership Applications',
            sub: 'Review dealership and distribution partner applications submitted from the website.',
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
            entity: 'dealership',
            searchFields: ['name', 'email', 'phone', 'details'],
            searchPlaceholder: 'Search name, location, phone or email',
            statusOptions: STATUS,
            sort: 'created_at',
            dir: 'desc',
            rowClass: (r) => (r.status === 'new' ? 'is-unread' : ''),
            columns: [
                {
                    label: 'Applicant / Owner', sort: 'name', width: '25%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid);background:var(--surface-3);border-radius:50%;width:32px;height:32px;">${U.esc(U.initials(r.name || 'DL'))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.name || 'Applicant', s.q)}</span>
                                <span class="cell-sub">${U.esc(r.phone || r.email || 'No contact')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Location / Proposed Setup', width: '30%',
                    render: (r) => {
                        const d = r.details || {};
                        const loc = [d.city, d.state, d.district, d.pincode].filter(Boolean).join(', ') || d.address || 'Location not specified';
                        const exp = d.experience || d.investment || d.message || '';
                        return `
                            <span class="cell-main"><i class="fa-solid fa-store" style="color:var(--brand-red);"></i> ${U.esc(loc)}</span>
                            ${exp ? `<span class="cell-sub clamp-2">${U.esc(exp)}</span>` : ''}`;
                    },
                },
                {
                    label: 'Submitted', sort: 'created_at', width: '18%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.created_at))}">${U.esc(U.ago(r.created_at))}</span>`,
                },
                {
                    label: 'Status', sort: 'status', width: '15%',
                    render: (r) => statusTag(r.status),
                },
            ],
            rowActions: (row) => [
                { label: 'Mark Contacted', icon: 'fa-phone', onClick: () => updateStatus(row, 'contacted') },
                { label: 'Approve Dealership', icon: 'fa-circle-check', onClick: () => updateStatus(row, 'approved') },
                { label: 'Reject', icon: 'fa-ban', onClick: () => updateStatus(row, 'rejected') },
                { divider: true },
                { label: 'Delete', icon: 'fa-trash', danger: true, onClick: () => remove(row) },
            ],
            empty: {
                icon: 'fa-store', title: 'No dealership leads',
                text: 'Dealership applications will appear here.',
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
            await store.update('dealership', row.id, { status });
            toast.success(`Dealership application marked ${status}`);
            list.load();
        } catch (err) {
            toast.error('Failed to update status: ' + (err.message || 'Error'));
        }
    }

    async function remove(row) {
        const ok = await confirmDialog({
            title: 'Delete dealership lead?',
            body: `Delete application from ${row.name || 'Applicant'}. This action cannot be undone.`,
            danger: true,
            confirmLabel: 'Delete Permanently',
        });
        if (!ok) return;

        try {
            await store.remove('dealership', row.id);
            toast.success('Dealership application deleted');
            list.load();
            paintStats();
        } catch (err) {
            toast.error('Failed to delete lead: ' + (err.message || 'Error'));
        }
    }

    async function paintStats() {
        const rows = await store.all('dealership');
        const total = rows.length;
        const pending = rows.filter(r => r.status === 'new').length;
        const approved = rows.filter(r => r.status === 'approved').length;
        const contacted = rows.filter(r => r.status === 'contacted').length;

        const slot = document.getElementById('statStrip');
        if (slot) {
            slot.innerHTML = U.statStrip([
                ['fa-store', 'red', total, 'Total Applications', 'Partner inquiries'],
                ['fa-envelope-open-text', 'warn', pending, 'New Inquiries', 'Action needed'],
                ['fa-handshake', 'blue', approved, 'Approved Partners', 'Agreements active'],
                ['fa-phone', 'navy', contacted, 'Contacted', 'In discussion'],
            ]);
        }
    }
}());