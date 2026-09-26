/* Warranty Registrations — list, review, and status management. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, modal, confirm: confirmDialog } = window.HAZRA;

    const STATUS = [
        { value: 'all', label: 'All Statuses' },
        { value: 'pending', label: 'Pending' },
        { value: 'under_review', label: 'Under Review' },
        { value: 'approved', label: 'Approved' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'cancelled', label: 'Cancelled' },
    ];

    const TYPES = [
        { value: 'all', label: 'All Types' },
        { value: 'free', label: 'Free Warranty' },
        { value: 'paid', label: 'Paid Extension' },
    ];

    const TONE = {
        pending: 'warn',
        under_review: 'info',
        approved: 'ok',
        rejected: 'danger',
        cancelled: 'off',
    };

    let list = null;

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Service' }, { label: 'Warranty' }],
            title: 'Warranty Registrations',
            sub: 'Manage free warranty submissions and paid warranty extension requests.',
            actions: `
                <a class="btn btn--ghost" href="/warranty-free" target="_blank">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Free Form
                </a>
                <a class="btn btn--ghost" href="/warranty-paid" target="_blank">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Paid Form
                </a>`,
        });

        document.getElementById('view').innerHTML = `
            <div id="statStrip" class="mb-4"></div>
            <article class="card card--flush" id="listCard"></article>`;

        paintStats();

        list = table.create({
            mount: '#listCard',
            entity: 'warranty',
            searchFields: ['customer_name', 'mobile', 'email', 'chassis_no', 'motor_no', 'reference_no', 'dealer_name'],
            searchPlaceholder: 'Search reference, customer, mobile, chassis, or motor #...',
            statusOptions: STATUS,
            filters: [
                {
                    key: 'type',
                    label: 'Warranty Type',
                    options: TYPES,
                },
            ],
            sort: 'created_at',
            dir: 'desc',
            rowClass: (r) => (r.status === 'pending' ? 'is-unread' : ''),
            onRowClick: (row) => openDetailModal(row),
            columns: [
                {
                    label: 'Reference / Type', width: '20%',
                    render: (r, s) => {
                        const isPaid = r.type === 'paid';
                        const typeTag = isPaid
                            ? `<span class="tag info" style="font-size:10px;font-weight:700;">PAID ${U.esc(r.plan || '')}</span>`
                            : `<span class="tag ok" style="font-size:10px;font-weight:700;">FREE</span>`;
                        return `
                            <div style="display:flex;flex-direction:column;gap:3px;">
                                <strong class="cell-main" style="font-family:monospace;letter-spacing:0.5px;">${U.mark(r.reference_no, s.q)}</strong>
                                <div>${typeTag}</div>
                            </div>`;
                    },
                },
                {
                    label: 'Customer', sort: 'customer_name', width: '23%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid);background:var(--surface-3);border-radius:50%;width:32px;height:32px;">${U.esc(U.initials(r.customer_name || 'WR'))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.customer_name || 'Anonymous', s.q)}</span>
                                <span class="cell-sub">${U.mark(r.mobile || '', s.q)} ${r.mobile_verified_at ? '<i class="fa-solid fa-circle-check" style="color:var(--accent-green);margin-left:2px;" title="Mobile OTP Verified"></i>' : ''}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Vehicle (Chassis / Motor)', width: '22%',
                    render: (r, s) => `
                        <div>
                            <span class="cell-main" style="font-family:monospace;font-size:12px;"><i class="fa-solid fa-fingerprint" style="color:var(--brand-red);margin-right:4px;"></i>${U.mark(r.chassis_no || '—', s.q)}</span>
                            <span class="cell-sub" style="font-family:monospace;font-size:11px;">M: ${U.mark(r.motor_no || '—', s.q)}</span>
                        </div>`,
                },
                {
                    label: 'Purchase Date', sort: 'purchase_date', width: '13%',
                    render: (r) => {
                        const pDate = r.purchase_date || '—';
                        return `
                            <span class="cell-main">${U.esc(pDate)}</span>
                            <span class="cell-sub">${U.esc(U.ago(r.created_at))}</span>`;
                    },
                },
                {
                    label: 'Status', sort: 'status', width: '12%',
                    render: (r) => statusTag(r.status),
                },
                {
                    label: 'Invoice', width: '10%',
                    render: (r) => {
                        const invUrl = `/api/v1/admin/warranty/registrations/${r.id}/invoice`;
                        return `
                            <a class="btn btn--xs btn--ghost" href="${invUrl}" target="_blank" onclick="event.stopPropagation();" title="View uploaded invoice">
                                <i class="fa-solid fa-file-invoice"></i> View
                            </a>`;
                    },
                },
            ],
            rowActions: (row) => [
                { label: 'Review Details', icon: 'fa-eye', onClick: () => openDetailModal(row) },
                { label: 'Approve', icon: 'fa-circle-check', onClick: () => updateStatus(row, 'approved') },
                { label: 'Mark Under Review', icon: 'fa-clock', onClick: () => updateStatus(row, 'under_review') },
                { label: 'Reject', icon: 'fa-ban', danger: true, onClick: () => rejectModal(row) },
                { divider: true },
                { label: 'View Invoice', icon: 'fa-file-invoice', onClick: () => window.open(`/api/v1/admin/warranty/registrations/${row.id}/invoice`, '_blank') },
            ],
            empty: {
                icon: 'fa-shield-halved',
                title: 'No warranty registrations found',
                text: 'Submissions from free registration or paid extension will appear here.',
            },
        });
    }

    function statusTag(status) {
        const s = STATUS.find((x) => x.value === status);
        const label = s ? s.label : (status ? status.toUpperCase() : 'PENDING');
        const toneClass = TONE[status] || 'warn';
        return `<span class="tag ${toneClass}">${U.esc(label)}</span>`;
    }

    async function paintStats() {
        try {
            const res = await window.HAZRA.api.get('api/v1/admin/warranty/registrations', { pageSize: 1 });
            const counts = (res && res.meta && res.meta.counts) || {};

            const slot = document.getElementById('statStrip');
            if (slot) {
                slot.innerHTML = U.statStrip([
                    ['fa-shield-halved', 'blue', counts.all || 0, 'Total Registrations', 'All warranty applications'],
                    ['fa-clock', 'warn', counts.pending || 0, 'Pending Review', 'Awaiting administrative check'],
                    ['fa-circle-check', 'ok', counts.approved || 0, 'Approved Cover', 'Active registered vehicles'],
                    ['fa-circle-plus', 'purple', counts.paid || 0, 'Paid Extensions', 'Extended warranty plans'],
                ]);
            }
        } catch (e) {
            console.error('Failed to load warranty stats', e);
        }
    }

    async function updateStatus(row, status, notes = '') {
        try {
            await window.HAZRA.api.patch(`api/v1/admin/warranty/registrations/${row.id}`, {
                status,
                admin_notes: notes || row.admin_notes || '',
            });
            toast.success(`Registration status updated to ${status.replace('_', ' ')}`);
            if (list) list.load();
            paintStats();
        } catch (err) {
            toast.error('Failed to update status: ' + (err.message || 'Server error'));
        }
    }

    function rejectModal(row) {
        const html = `
            <form id="rejectForm" style="display:flex;flex-direction:column;gap:14px;">
                <p>Please enter the reason for rejecting warranty registration <strong>${U.esc(row.reference_no)}</strong>. This note will be recorded for audit.</p>
                <label class="field">
                    <span>Rejection Reason / Admin Note <strong style="color:var(--bad)">*</strong></span>
                    <textarea class="input" id="rejectNote" rows="3" placeholder="e.g. Purchase date exceeds policy window / Invalid invoice uploaded..." required></textarea>
                </label>
            </form>`;

        const footer = `
            <button type="button" class="btn btn--ghost" data-close>Cancel</button>
            <button type="button" class="btn btn--danger" id="confirmRejectBtn">
                <i class="fa-solid fa-ban"></i> Reject Registration
            </button>`;

        modal.open({
            title: 'Reject Warranty Registration',
            icon: 'fa-ban',
            html,
            footer,
            onMount: (panel, close) => {
                const btn = panel.querySelector('#confirmRejectBtn');
                btn.addEventListener('click', async () => {
                    const note = panel.querySelector('#rejectNote').value.trim();
                    if (!note) {
                        toast.error('Please enter a rejection reason.');
                        panel.querySelector('#rejectNote').focus();
                        return;
                    }
                    btn.disabled = true;
                    await updateStatus(row, 'rejected', note);
                    close(true);
                });
            },
        });
    }

    async function openDetailModal(row) {
        // Fetch full fresh detail
        let item = row;
        try {
            const res = await window.HAZRA.api.get(`api/v1/admin/warranty/registrations/${row.id}`);
            if (res && res.data) item = res.data;
        } catch (e) {}

        const isPaid = item.type === 'paid';
        const invUrl = `/api/v1/admin/warranty/registrations/${item.id}/invoice`;

        const html = `
            <div class="warranty-detail" style="display:flex;flex-direction:column;gap:18px;">
                <!-- Header Banner -->
                <div style="background:var(--surface-2);border-radius:var(--radius-md);padding:14px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <h3 style="margin:0;font-size:16px;font-family:monospace;letter-spacing:0.5px;">${U.esc(item.reference_no)}</h3>
                            ${statusTag(item.status)}
                        </div>
                        <p style="margin:4px 0 0;font-size:12px;color:var(--text-sub);">
                            Submitted: <strong>${U.esc(U.fmtDateTime(item.created_at))}</strong>
                            ${item.mobile_verified_at ? ` · <i class="fa-solid fa-circle-check" style="color:var(--accent-green)"></i> Mobile OTP verified at ${U.esc(U.fmtDateTime(item.mobile_verified_at))}` : ''}
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <a class="btn btn--sm btn--primary" href="${invUrl}" target="_blank">
                            <i class="fa-solid fa-file-invoice"></i> View Invoice
                        </a>
                    </div>
                </div>

                <!-- 2-col info grid -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <!-- Customer Box -->
                    <div style="background:var(--surface-1);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;">
                        <h4 style="margin:0 0 10px;font-size:13px;text-transform:uppercase;color:var(--text-sub);letter-spacing:0.5px;">
                            <i class="fa-solid fa-user"></i> Customer Information
                        </h4>
                        <dl class="kv-list" style="display:grid;grid-template-columns:110px 1fr;row-gap:8px;font-size:13px;margin:0;">
                            <dt style="color:var(--text-sub);">Name:</dt>
                            <dd style="margin:0;font-weight:600;">${U.esc(item.customer_name || '—')}</dd>
                            <dt style="color:var(--text-sub);">Mobile:</dt>
                            <dd style="margin:0;"><a href="tel:${U.esc(item.mobile)}">${U.esc(item.mobile || '—')}</a></dd>
                            <dt style="color:var(--text-sub);">Email:</dt>
                            <dd style="margin:0;"><a href="mailto:${U.esc(item.email)}">${U.esc(item.email || '—')}</a></dd>
                            <dt style="color:var(--text-sub);">Location:</dt>
                            <dd style="margin:0;">${U.esc(item.district ? `${item.district}, ${item.state}` : item.state || '—')}</dd>
                        </dl>
                    </div>

                    <!-- Vehicle Box -->
                    <div style="background:var(--surface-1);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;">
                        <h4 style="margin:0 0 10px;font-size:13px;text-transform:uppercase;color:var(--text-sub);letter-spacing:0.5px;">
                            <i class="fa-solid fa-motorcycle"></i> Vehicle & Battery
                        </h4>
                        <dl class="kv-list" style="display:grid;grid-template-columns:110px 1fr;row-gap:8px;font-size:13px;margin:0;">
                            <dt style="color:var(--text-sub);">Chassis (VIN):</dt>
                            <dd style="margin:0;font-family:monospace;font-weight:600;color:var(--brand-red);">${U.esc(item.chassis_no || '—')}</dd>
                            <dt style="color:var(--text-sub);">Motor No:</dt>
                            <dd style="margin:0;font-family:monospace;">${U.esc(item.motor_no || '—')}</dd>
                            <dt style="color:var(--text-sub);">Controller No:</dt>
                            <dd style="margin:0;font-family:monospace;">${U.esc(item.controller_no || '—')}</dd>
                            <dt style="color:var(--text-sub);">Battery Type:</dt>
                            <dd style="margin:0;">${U.esc(item.battery_type ? item.battery_type.toUpperCase() : 'N/A')} ${item.battery_volt ? `(${U.esc(item.battery_volt)}V)` : ''}</dd>
                            <dt style="color:var(--text-sub);">Battery Serial:</dt>
                            <dd style="margin:0;font-family:monospace;">${U.esc(item.battery_serial || '—')}</dd>
                            <dt style="color:var(--text-sub);">Charger Serial:</dt>
                            <dd style="margin:0;font-family:monospace;">${U.esc(item.charger_serial || '—')}</dd>
                        </dl>
                    </div>
                </div>

                <!-- Purchase & Dealer Details -->
                <div style="background:var(--surface-1);border:1px solid var(--border);border-radius:var(--radius-md);padding:14px;">
                    <h4 style="margin:0 0 10px;font-size:13px;text-transform:uppercase;color:var(--text-sub);letter-spacing:0.5px;">
                        <i class="fa-solid fa-store"></i> Purchase & Dealer Details
                    </h4>
                    <dl class="kv-list" style="display:grid;grid-template-columns:140px 1fr;row-gap:8px;font-size:13px;margin:0;">
                        <dt style="color:var(--text-sub);">Purchase Date:</dt>
                        <dd style="margin:0;font-weight:600;">${U.esc(item.purchase_date || '—')}</dd>
                        <dt style="color:var(--text-sub);">Dealer Name:</dt>
                        <dd style="margin:0;">${U.esc(item.dealer_name || '—')}</dd>
                        <dt style="color:var(--text-sub);">Dealer Email:</dt>
                        <dd style="margin:0;">${U.esc(item.dealer_email || '—')}</dd>
                        ${isPaid ? `
                            <dt style="color:var(--text-sub);">Extension Plan:</dt>
                            <dd style="margin:0;font-weight:600;color:var(--brand-red);">${U.esc(item.plan || '')} (${item.amount_paise ? '₹' + (item.amount_paise / 100).toLocaleString('en-IN') : '—'})</dd>
                            <dt style="color:var(--text-sub);">Payment Status:</dt>
                            <dd style="margin:0;"><span class="tag ${item.payment_status === 'paid' ? 'ok' : 'warn'}">${U.esc((item.payment_status || 'unpaid').toUpperCase())}</span></dd>
                            ${item.parent_registration ? `
                                <dt style="color:var(--text-sub);">Free Warranty Ref:</dt>
                                <dd style="margin:0;font-family:monospace;"><a href="javascript:void(0)" id="viewParentFreeBtn">${U.esc(item.parent_registration.reference_no)}</a></dd>
                            ` : ''}
                        ` : ''}
                    </dl>
                </div>

                <!-- Admin Notes & Action Form -->
                <div style="background:var(--surface-2);border-radius:var(--radius-md);padding:14px;">
                    <label class="field">
                        <span style="font-weight:600;font-size:13px;">Admin Review Notes</span>
                        <textarea class="input" id="detailAdminNotes" rows="2" placeholder="Add verification remarks, warranty conditions, or notes...">${U.esc(item.admin_notes || '')}</textarea>
                    </label>
                </div>
            </div>`;

        const footer = `
            <div style="display:flex;justify-content:space-between;width:100%;align-items:center;">
                <div>
                    <button type="button" class="btn btn--danger btn--sm" id="detailRejectBtn">
                        <i class="fa-solid fa-ban"></i> Reject
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" id="detailUnderReviewBtn">
                        <i class="fa-solid fa-clock"></i> Under Review
                    </button>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="btn btn--ghost" data-close>Close</button>
                    <button type="button" class="btn btn--primary" id="detailApproveBtn">
                        <i class="fa-solid fa-circle-check"></i> Approve Warranty
                    </button>
                </div>
            </div>`;

        modal.open({
            title: `${isPaid ? 'Paid Extension' : 'Free Warranty'} · Details`,
            icon: 'fa-shield-halved',
            html,
            footer,
            onMount: (panel, close) => {
                const notesInput = panel.querySelector('#detailAdminNotes');

                // Approve button
                panel.querySelector('#detailApproveBtn')?.addEventListener('click', async () => {
                    await updateStatus(item, 'approved', notesInput.value.trim());
                    close(true);
                });

                // Under Review button
                panel.querySelector('#detailUnderReviewBtn')?.addEventListener('click', async () => {
                    await updateStatus(item, 'under_review', notesInput.value.trim());
                    close(true);
                });

                // Reject button
                panel.querySelector('#detailRejectBtn')?.addEventListener('click', async () => {
                    const note = notesInput.value.trim();
                    if (!note) {
                        toast.error('Please enter a rejection reason in Admin Review Notes.');
                        notesInput.focus();
                        return;
                    }
                    await updateStatus(item, 'rejected', note);
                    close(true);
                });

                // Link to parent registration
                panel.querySelector('#viewParentFreeBtn')?.addEventListener('click', () => {
                    if (item.parent_registration) {
                        close();
                        openDetailModal(item.parent_registration);
                    }
                });
            },
        });
    }
}());
