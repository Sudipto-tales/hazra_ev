/* Applications — list with a detail drawer and a stage pipeline. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast, modal } = window.TMH;

    const STAGES = [
        { value: 'new', label: 'New', tone: 'warn' },
        { value: 'shortlisted', label: 'Shortlisted', tone: 'info' },
        { value: 'interview', label: 'Interview', tone: 'info' },
        { value: 'offered', label: 'Offered', tone: 'ok' },
        { value: 'rejected', label: 'Rejected', tone: 'off' },
    ];

    let list = null;

    window.TMH.boot(init);

    async function init() {
        const jobId = U.param('jobId');
        const jobs = await store.all('jobs');
        const job = jobs.find((j) => j.id === jobId);

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Careers' },
                { label: 'Vacancies', href: 'jobs' },
                { label: job ? job.title : 'Applications' },
            ],
            title: 'Applications',
            sub: job
                ? `Applications for ${job.title}.`
                : 'Everyone who has applied, across every vacancy.',
            actions: job
                ? '<a class="btn btn--ghost" href="applications"><i class="fa-solid fa-list"></i> All applications</a>'
                : '',
        });

        const rows = await store.all('applications');
        const scoped = job ? rows.filter((r) => r.jobId === job.id) : rows;

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-file-signature', 'red', scoped.length, 'Applications', job ? `For ${job.title}` : 'Across all roles'],
                ['fa-inbox', 'navy', scoped.filter((a) => a.stage === 'new').length, 'Not yet reviewed', 'Waiting on you'],
                ['fa-star', 'blue', scoped.filter((a) => a.stage === 'shortlisted' || a.stage === 'interview').length, 'In progress', 'Shortlisted or interviewing'],
                ['fa-circle-check', 'magenta', scoped.filter((a) => a.stage === 'offered').length, 'Offered', ''],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        list = table.create({
            mount: '#listCard',
            entity: 'applications',
            searchFields: ['name', 'email', 'currentEmployer', 'experience'],
            searchPlaceholder: 'Search applicants',
            statusChips: false,
            filters: [
                { key: 'stage', label: 'Stage', options: STAGES.map((s) => ({ value: s.value, label: s.label })) },
                { key: 'jobId', label: 'Vacancy', options: jobs.map((j) => ({ value: j.id, label: j.title })) },
            ],
            sort: 'appliedAt',
            dir: 'desc',
            bulkActions: [],
            columns: [
                {
                    label: 'Applicant', sort: 'name', width: '24%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.name))}</span>
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.email)}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Applied for', width: '20%',
                    render: (r) => {
                        const j = store.allSync('jobs').find((x) => x.id === r.jobId);
                        return j ? `<a href="job-form?id=${U.esc(j.id)}">${U.esc(j.title)}</a>`
                            : '<span class="tag warn">Vacancy deleted</span>';
                    },
                },
                { label: 'Experience', width: '20%', render: (r) => `${U.esc(r.experience || '—')}<span class="cell-sub">${U.esc(r.currentEmployer || '')}</span>` },
                { label: 'Applied', sort: 'appliedAt', width: '12%', render: (r) => U.esc(U.ago(r.appliedAt)) },
                {
                    label: 'Rating', sort: 'rating', width: '10%',
                    render: (r) => (r.rating
                        ? `${'★'.repeat(r.rating)}<span class="muted">${'★'.repeat(5 - r.rating)}</span>`
                        : '<span class="muted">Unrated</span>'),
                },
                {
                    label: 'Stage', sort: 'stage', width: '12%',
                    render: (r) => {
                        const s = STAGES.find((x) => x.value === r.stage) || STAGES[0];
                        return `<span class="tag ${s.tone}">${U.esc(s.label)}</span>`;
                    },
                },
            ],
            rowActions: (row) => [
                { label: 'Open', icon: 'fa-eye', onClick: () => openDrawer(row) },
                { label: 'Download CV', icon: 'fa-file-arrow-down', onClick: () => downloadCv(row) },
                { divider: true },
                ...STAGES.filter((s) => s.value !== row.stage).map((s) => ({
                    label: `Move to ${s.label}`,
                    icon: 'fa-arrow-right',
                    onClick: () => setStage(row, s.value),
                })),
            ],
            onRowClick: openDrawer,
            empty: {
                icon: 'fa-file-signature', title: 'No applications',
                text: 'They will land here once the careers form is wired to the backend.',
            },
        });

        /* Deep link from the vacancies list. */
        if (jobId) {
            list.state.filters.jobId = jobId;
            list.load();
        }
    }

    async function setStage(row, stage) {
        await store.update('applications', row.id, { stage });
        const label = (STAGES.find((s) => s.value === stage) || {}).label;
        toast.success(`${row.name} moved to ${label}`, {
            undo: async () => {
                await store.update('applications', row.id, { stage: row.stage });
                toast.success('Moved back');
                list.load();
            },
        });
        list.load();
    }

    /* The applicant's CV arrives with the application: it is attached to the
       mail HR receives and stored server-side, streamed back through
       /api/applications/{id}/cv rather than served from a public path. Until
       that endpoint exists the seed rows carry a placeholder, and saying so is
       better than handing the reviewer a link that quietly does nothing. */
    function downloadCv(row) {
        if (row && row.cvUrl && row.cvUrl !== '#') {
            window.open(row.cvUrl, '_blank', 'noopener');
            return;
        }
        toast.warning('No file stored for this application', {
            body: `Phase 2 serves ${(row && row.cvFile) || 'the CV'} from /api/applications/${(row && row.id) || '{id}'}/cv.`,
        });
    }

    async function openDrawer(row) {
        const job = store.allSync('jobs').find((j) => j.id === row.jobId);

        await modal.drawer({
            title: row.name,
            html: `
                <div class="col gap-6">
                    <div>
                        <div class="eyebrow mb-4">Contact</div>
                        <dl class="kv">
                            <dt>Email</dt><dd><a href="mailto:${U.esc(row.email)}">${U.esc(row.email)}</a></dd>
                            <dt>Phone</dt><dd><a href="tel:${U.esc(row.phone)}">${U.esc(row.phone)}</a></dd>
                            <dt>Applied</dt><dd>${U.esc(U.fmtDateTime(row.appliedAt))}</dd>
                            <dt>Vacancy</dt><dd>${U.esc(job ? job.title : 'Deleted vacancy')}</dd>
                        </dl>
                    </div>

                    <div>
                        <div class="eyebrow mb-4">Background</div>
                        <dl class="kv">
                            <dt>Experience</dt><dd>${U.esc(row.experience || '—')}</dd>
                            <dt>Currently at</dt><dd>${U.esc(row.currentEmployer || '—')}</dd>
                            <dt>CV</dt><dd>${row.cvFile
                                ? `<button type="button" class="btn btn--link btn--sm" data-cv>${U.esc(row.cvFile)}</button>`
                                : '<span class="muted">Not attached</span>'}</dd>
                        </dl>
                    </div>

                    ${row.coverNote ? `
                    <div>
                        <div class="eyebrow mb-4">Cover note</div>
                        <p class="text-sm mid">${U.esc(row.coverNote)}</p>
                    </div>` : ''}

                    <div>
                        <div class="eyebrow mb-4">Stage</div>
                        <div class="row wrap gap-2">
                            ${STAGES.map((s) => `
                                <button type="button" class="btn ${s.value === row.stage ? 'btn--primary' : 'btn--ghost'} btn--sm"
                                        data-stage="${s.value}">${U.esc(s.label)}</button>`).join('')}
                        </div>
                    </div>

                    <div>
                        <div class="eyebrow mb-4">Rating</div>
                        <div class="row gap-1" data-rating>
                            ${[1, 2, 3, 4, 5].map((n) => `
                                <button type="button" class="icon-btn" data-star="${n}"
                                    aria-label="Rate ${n} out of 5"
                                    style="color:${n <= (row.rating || 0) ? 'var(--accent-orange)' : 'var(--text-muted)'}">
                                    <i class="fa-solid fa-star"></i></button>`).join('')}
                        </div>
                    </div>
                </div>`,
            footer: `
                <button type="button" class="btn btn--ghost grow" data-cv><i class="fa-solid fa-file-arrow-down"></i> CV</button>
                <a class="btn btn--primary grow" href="mailto:${U.esc(row.email)}"><i class="fa-solid fa-reply"></i> Email</a>`,
            onMount(panel, close) {
                panel.querySelectorAll('[data-stage]').forEach((b) =>
                    b.addEventListener('click', async () => {
                        await setStage(row, b.dataset.stage);
                        close();
                    }));

                panel.querySelectorAll('[data-star]').forEach((b) =>
                    b.addEventListener('click', async () => {
                        const rating = Number(b.dataset.star);
                        await store.update('applications', row.id, { rating });
                        toast.success(`Rated ${rating}/5`);
                        panel.querySelectorAll('[data-star]').forEach((s) => {
                            s.style.color = Number(s.dataset.star) <= rating
                                ? 'var(--accent-orange)' : 'var(--text-muted)';
                        });
                        list.load();
                    }));

                /* Two of them — the filename in the Background block and the
                   footer button. Both do the same thing. */
                panel.querySelectorAll('[data-cv]').forEach((b) =>
                    b.addEventListener('click', () => downloadCv(row)));
            },
        });
    }
}());
