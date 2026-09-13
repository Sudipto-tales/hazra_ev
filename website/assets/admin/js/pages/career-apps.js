(function () {
    'use strict';

    const { util: U, store, table, layout, toast, confirm: confirmDialog } = window.HAZRA;

    const STATUS = [
        { value: 'all', label: 'All' },
        { value: 'new', label: 'New' },
        { value: 'shortlisted', label: 'Shortlisted' },
        { value: 'rejected', label: 'Rejected' },
    ];

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Careers' }, { label: 'Applications' }],
            title: 'Career Applications',
            sub: 'Manage job applications received from candidates.',
        });

        document.getElementById('view').innerHTML = `
            <div id="statStrip" class="grid-stats mb-lg"></div>
            <div id="listCard"></div>
        `;

        const jobs = await store.all('jobs');
        paintStats();

        const list = table.create({
            mount: '#listCard',
            entity: 'career-apps',
            searchFields: ['applicant_name', 'name', 'email', 'phone', 'message'],
            searchPlaceholder: 'Search by applicant name, email or phone',
            statusOptions: STATUS,
            filters: [
                {
                    key: 'job_id',
                    label: 'Job Opening',
                    options: jobs.map((j) => ({ value: j.id, label: j.title })),
                },
            ],
            sort: 'created_at',
            dir: 'desc',
            empty: {
                icon: 'fa-user-graduate',
                title: 'No applications found',
                text: 'Applications submitted for posted vacancies will appear here.',
            },
            columns: [
                {
                    label: 'Applicant', sort: 'applicant_name', width: '25%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.applicant_name || r.name || 'A'))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.applicant_name || r.name || 'Anonymous', s.q)}</span>
                                <span class="cell-sub">${U.esc(r.email || '')} &bull; ${U.esc(r.phone || '')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Job Position', width: '20%',
                    render: (r) => {
                        const j = jobs.find((x) => String(x.id) === String(r.job_id));
                        return j ? U.esc(j.title) : `<span class="muted">${U.esc(r.job_id || 'General')}</span>`;
                    },
                },
                {
                    label: 'Message / Cover Letter', width: '25%',
                    render: (r, s) => `<span class="cell-sub clamp-2">${U.mark(r.message || r.cover_letter || 'No message attached', s.q)}</span>`,
                },
                {
                    label: 'Applied', sort: 'created_at', width: '15%',
                    render: (r) => `<span title="${U.esc(U.fmtDateTime(r.created_at || r.applied_at))}">${U.esc(U.ago(r.created_at || r.applied_at))}</span>`,
                },
                {
                    label: 'Status', sort: 'status', width: '15%',
                    render: (r) => U.statusTag(r.status || 'new'),
                },
            ],
            rowActions: (row) => [
                {
                    label: 'Shortlist', icon: 'fa-user-check',
                    onClick: async () => {
                        await store.update('career-apps', row.id, { status: 'shortlisted' });
                        toast.success('Applicant shortlisted');
                        list.load();
                        paintStats();
                    },
                },
                {
                    label: 'Reject', icon: 'fa-user-xmark',
                    onClick: async () => {
                        await store.update('career-apps', row.id, { status: 'rejected' });
                        toast.success('Applicant marked as rejected');
                        list.load();
                        paintStats();
                    },
                },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: async () => {
                        const done = await list.confirmDelete(row, {
                            body: 'This application will be removed.',
                        });
                        if (done) paintStats();
                    },
                },
            ],
        });
    }

    async function paintStats() {
        const apps = await store.all('career-apps');
        const total = apps.length;
        const newCount = apps.filter((a) => a.status === 'new' || !a.status).length;
        const shortlisted = apps.filter((a) => a.status === 'shortlisted').length;

        document.getElementById('statStrip').innerHTML = `
            <article class="card stat anim-item">
                <div class="stat__icon navy"><i class="fa-solid fa-users"></i></div>
                <h3>${total}</h3>
                <p>Total Applications</p>
                <span class="delta flat">All time</span>
            </article>
            <article class="card stat anim-item">
                <div class="stat__icon blue"><i class="fa-solid fa-user-clock"></i></div>
                <h3>${newCount}</h3>
                <p>New Applications</p>
                <span class="delta flat">Pending review</span>
            </article>
            <article class="card stat anim-item">
                <div class="stat__icon green"><i class="fa-solid fa-user-check"></i></div>
                <h3>${shortlisted}</h3>
                <p>Shortlisted</p>
                <span class="delta flat">Candidates selected</span>
            </article>
        `;
    }
}());
