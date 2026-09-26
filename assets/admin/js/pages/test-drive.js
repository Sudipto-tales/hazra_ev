/* Test Drive Leads — list & approval management. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, modal, confirm: confirmDialog } = window.HAZRA;

    const STATUS = [
        { value: 'all', label: 'All Statuses' },
        { value: 'new', label: 'New' },
        { value: 'approved', label: 'Approved' },
        { value: 'contacted', label: 'Contacted' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'archived', label: 'Archived' },
    ];

    const TONE = {
        new: 'warn', approved: 'ok', contacted: 'info', rejected: 'off', archived: 'off',
    };

    let list = null;

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Leads' }, { label: 'Test Drive' }],
            title: 'Test Drive Leads',
            sub: 'Review, schedule, and approve test drive requests submitted from the website.',
            actions: `
                <a class="btn btn--ghost" href="/admin/settings">
                    <i class="fa-solid fa-sliders"></i> Email Toggles</a>`,
        });

        document.getElementById('view').innerHTML = `
            <div id="statStrip" class="mb-4"></div>
            <article class="card card--flush" id="listCard"></article>`;

        paintStats();

        list = table.create({
            mount: '#listCard',
            entity: 'test-drive',
            searchFields: ['name', 'email', 'phone', 'details'],
            searchPlaceholder: 'Search customer name, phone or email',
            statusOptions: STATUS,
            sort: 'created_at',
            dir: 'desc',
            rowClass: (r) => (r.status === 'new' ? 'is-unread' : ''),
            columns: [
                {
                    label: 'Customer', sort: 'name', width: '22%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid);background:var(--surface-3);border-radius:50%;width:32px;height:32px;">${U.esc(U.initials(r.name || 'TD'))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.name || 'Anonymous', s.q)}</span>
                                <span class="cell-sub">${U.esc(r.phone || r.email || 'No contact')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Vehicle / City', width: '25%',
                    render: (r) => {
                        const d = r.details || {};
                        const vehicle = d.model || d.vehicle || d.scooter || 'Hazra EV Scooter';
                        const city = d.city || d.location || d.address || '';
                        return `
                            <span class="cell-main"><i class="fa-solid fa-bicycle" style="color:var(--brand-red);"></i> ${U.esc(vehicle)}</span>
                            ${city ? `<span class="cell-sub"><i class="fa-solid fa-location-dot"></i> ${U.esc(city)}</span>` : ''}`;
                    },
                },
                {
                    label: 'Schedule / Note', width: '25%',
                    render: (r) => {
                        if (r.status === 'approved' && r.scheduled_at) {
                            return `
                                <span class="cell-main" style="color:var(--accent-green);font-weight:600;"><i class="fa-solid fa-calendar-check"></i> ${U.esc(r.scheduled_at)}</span>
                                ${r.admin_note ? `<span class="cell-sub clamp-1">${U.esc(r.admin_note)}</span>` : ''}`;
                        }
                        if (r.admin_note) {
                            return `<span class="cell-sub clamp-2">${U.esc(r.admin_note)}</span>`;
                        }
                        return `<span class="muted">Not scheduled</span>`;
                    },
                },
                {
                    label: 'Submitted', sort: 'created_at', width: '15%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.created_at))}">${U.esc(U.ago(r.created_at))}</span>`,
                },
                {
                    label: 'Status', sort: 'status', width: '13%',
                    render: (r) => statusTag(r.status),
                },
            ],
            rowActions: (row) => [
                { label: 'Approve & Schedule', icon: 'fa-calendar-check', onClick: () => approveModal(row) },
                { label: 'Mark Contacted', icon: 'fa-phone', onClick: () => updateStatus(row, 'contacted') },
                { label: 'Reject', icon: 'fa-ban', onClick: () => rejectLead(row) },
                { divider: true },
                { label: 'Delete', icon: 'fa-trash', danger: true, onClick: () => remove(row) },
            ],
            empty: {
                icon: 'fa-road', title: 'No test drive leads',
                text: 'Website test ride submissions will show up here.',
            },
        });
    }

    function statusTag(status) {
        const s = STATUS.find((x) => x.value === status);
        const label = s ? s.label : (status ? status.toUpperCase() : 'NEW');
        return `<span class="tag ${TONE[status] || 'off'}">${U.esc(label)}</span>`;
    }

    async function approveModal(row) {
        const html = `
            <form id="approveForm" class="p-2">
                <p class="mb-3 text-sm mid">Approve <strong>${U.esc(row.name || 'Customer')}</strong>'s test drive request and set the appointment date & time.</p>
                
                <div class="field mb-3">
                    <label for="scheduled_at" class="font-bold">Scheduled Date & Time <span class="text-bad">*</span></label>
                    <input class="input" type="datetime-local" id="scheduled_at" name="scheduled_at" required data-autofocus>
                    <small class="text-xs mid">Select when the test ride will take place.</small>
                </div>

                <div class="field mb-3">
                    <label for="admin_note" class="font-bold">Admin Note / Special Instructions (Optional)</label>
                    <textarea class="input" id="admin_note" name="admin_note" rows="3" placeholder="e.g. Sales representative assigned, helmet provided..."></textarea>
                </div>
            </form>`;

        const footer = `
            <button type="button" class="btn btn--ghost" data-close>Cancel</button>
            <button type="button" class="btn btn--primary" id="confirmApproveBtn">
                <i class="fa-solid fa-check"></i> Confirm Approval
            </button>`;

        modal.open({
            title: 'Approve Test Drive Request',
            icon: 'fa-calendar-check',
            html,
            footer,
            onMount: (panel, close) => {
                // Default date to tomorrow 10:00 AM
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(10, 0, 0, 0);
                const defaultIso = tomorrow.toISOString().slice(0, 16);
                
                const dateInput = panel.querySelector('#scheduled_at');
                if (dateInput) dateInput.value = defaultIso;

                const submitBtn = panel.querySelector('#confirmApproveBtn');
                submitBtn.addEventListener('click', async () => {
                    const scheduledAtVal = dateInput.value;
                    if (!scheduledAtVal) {
                        toast.error('Scheduled Date & Time is required');
                        dateInput.focus();
                        return;
                    }

                    const noteVal = panel.querySelector('#admin_note').value || '';

                    submitBtn.disabled = true;
                    submitBtn.classList.add('is-busy');

                    try {
                        const formattedDate = new Date(scheduledAtVal).toLocaleString('en-IN', {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        });

                        await store.update('test-drive', row.id, {
                            status: 'approved',
                            scheduled_at: formattedDate,
                            admin_note: noteVal,
                        });

                        toast.success('Test drive approved!', {
                            body: `Scheduled for ${formattedDate}. Notification emails dispatched per settings.`,
                        });

                        close(true);
                        list.load();
                    } catch (err) {
                        toast.error('Failed to approve lead: ' + (err.message || 'Server error'));
                    } finally {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('is-busy');
                    }
                });
            },
        });
    }

    async function rejectLead(row) {
        const ok = await confirmDialog({
            title: 'Reject this test drive request?',
            body: `Are you sure you want to mark ${row.name || 'this request'} as rejected?`,
            danger: true,
            confirmLabel: 'Reject Request',
        });
        if (!ok) return;

        await updateStatus(row, 'rejected');
    }

    async function updateStatus(row, status) {
        try {
            await store.update('test-drive', row.id, { status });
            toast.success(`Status updated to ${status}`);
            list.load();
        } catch (err) {
            toast.error('Failed to update status: ' + (err.message || 'Error'));
        }
    }

    async function remove(row) {
        const ok = await confirmDialog({
            title: 'Delete test drive lead?',
            body: `Delete inquiry from ${row.name || 'Customer'}. This action cannot be undone.`,
            danger: true,
            confirmLabel: 'Delete Permanently',
        });
        if (!ok) return;

        try {
            await store.remove('test-drive', row.id);
            toast.success('Test drive lead deleted');
            list.load();
            paintStats();
        } catch (err) {
            toast.error('Failed to delete lead: ' + (err.message || 'Error'));
        }
    }

    async function paintStats() {
        const rows = await store.all('test-drive');
        const total = rows.length;
        const pending = rows.filter(r => r.status === 'new').length;
        const approved = rows.filter(r => r.status === 'approved').length;
        const contacted = rows.filter(r => r.status === 'contacted').length;

        const slot = document.getElementById('statStrip');
        if (slot) {
            slot.innerHTML = U.statStrip([
                ['fa-road', 'red', total, 'Total Requests', 'All time submissions'],
                ['fa-clock', 'warn', pending, 'New / Pending', 'Action needed'],
                ['fa-calendar-check', 'blue', approved, 'Approved', 'Date & time set'],
                ['fa-check-double', 'navy', contacted, 'Contacted', 'Followed up'],
            ]);
        }
    }
}());