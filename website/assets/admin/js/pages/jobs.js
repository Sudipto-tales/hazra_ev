/* Vacancies — list. Replaces window.TMH_JOBS in assets/jobs.js. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Careers' }, { label: 'Vacancies' }],
            title: 'Vacancies',
            sub: 'An empty published list is a supported state — the careers page then shows its “nothing open” panel.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}careers" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View careers page</a>
                <a class="btn btn--primary" href="job-form"><i class="fa-solid fa-plus"></i> Post a vacancy</a>`,
        });

        const rows = await store.all('jobs');
        const apps = store.allSync('applications');
        const closingSoon = rows.filter((j) => {
            const d = U.daysUntil(j.closesAt);
            return j.status === 'published' && d !== null && d >= 0 && d <= 7;
        });

        document.getElementById('view').innerHTML = `
            ${closingSoon.length ? `
            <div class="banner banner--warn">
                <i class="fa-solid fa-clock"></i>
                <span class="grow"><b>${closingSoon.length} vacanc${closingSoon.length === 1 ? 'y closes' : 'ies close'} within a week.</b>
                    ${U.esc(closingSoon.map((j) => j.title).join(', '))}</span>
            </div>` : ''}

            ${U.statStrip([
                ['fa-bullhorn', 'red', rows.filter((j) => j.status === 'published').length, 'Open roles', `${rows.length} on record`],
                ['fa-file-signature', 'navy', apps.length, 'Applications', `${apps.filter((a) => a.stage === 'new').length} not yet reviewed`],
                ['fa-user-plus', 'blue', rows.filter((j) => j.status === 'published').reduce((n, j) => n + (j.openings || 0), 0), 'Positions to fill', 'Across all open roles'],
                ['fa-clock', 'magenta', closingSoon.length, 'Closing this week', closingSoon.length ? 'Repost or extend' : 'Nothing urgent'],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        store.registerDependents('jobs', (id) => store.allSync('applications')
            .filter((a) => a.jobId === id)
            .map((a) => `Application from ${a.name}`));

        const list = table.create({
            mount: '#listCard',
            entity: 'jobs',
            searchFields: ['title', 'dept', 'summary'],
            searchPlaceholder: 'Search vacancies',
            filters: [
                { key: 'dept', label: 'Department', options: [...new Set(rows.map((r) => r.dept))].map((d) => ({ value: d, label: d })) },
                { key: 'type', label: 'Type', options: ['Full time', 'Part time', 'Contract', 'Locum'].map((t) => ({ value: t, label: t })) },
            ],
            sort: 'postedAt',
            dir: 'desc',
            statusOptions: [
                { value: 'all', label: 'All' },
                { value: 'published', label: 'Open' },
                { value: 'draft', label: 'Draft' },
                { value: 'hidden', label: 'Closed' },
            ],
            columns: [
                {
                    label: 'Role', sort: 'title', width: '28%',
                    render: (r, s) => `<span class="cell-main">${U.mark(r.title, s.q)}</span>
                        <span class="cell-sub">${U.esc(r.dept)} · ${U.esc(r.location || '')}</span>`,
                },
                { label: 'Type', sort: 'type', width: '10%', render: (r) => `<span class="pill">${U.esc(r.type)}</span>` },
                { label: 'Experience', width: '12%', render: (r) => U.esc(r.experience || '—') },
                { label: 'Posted', sort: 'postedAt', width: '11%', render: (r) => U.esc(U.fmtDate(r.postedAt)) },
                {
                    label: 'Closes', sort: 'closesAt', width: '13%',
                    render: (r) => {
                        const d = U.daysUntil(r.closesAt);
                        if (d === null) return '<span class="muted">—</span>';
                        if (d < 0) return `${U.fmtDate(r.closesAt)}<span class="cell-sub">Closed</span>`;
                        return `${U.fmtDate(r.closesAt)}<span class="cell-sub">${d <= 7 ? `<span class="tag warn">In ${d} day${d === 1 ? '' : 's'}</span>` : `in ${d} days`}</span>`;
                    },
                },
                {
                    label: 'Applications', width: '11%',
                    render: (r) => {
                        const n = store.allSync('applications').filter((a) => a.jobId === r.id).length;
                        return n
                            ? `<a href="applications?jobId=${U.esc(r.id)}">${n}</a>`
                            : '<span class="muted">None</span>';
                    },
                },
                {
                    label: 'Status', sort: 'status', width: '10%',
                    render: (r) => (r.status === 'hidden'
                        ? '<span class="tag off">Closed</span>'
                        : U.statusTag(r.status)),
                },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `job-form?id=${encodeURIComponent(row.id)}`; } },
                { label: 'View applications', icon: 'fa-file-signature', onClick: () => { location.href = `applications?jobId=${encodeURIComponent(row.id)}`; } },
                { divider: true },
                {
                    label: row.status === 'published' ? 'Close this role' : 'Open this role',
                    icon: row.status === 'published' ? 'fa-lock' : 'fa-lock-open',
                    onClick: async () => {
                        const next = row.status === 'published' ? 'hidden' : 'published';
                        await store.update('jobs', row.id, { status: next });
                        toast.success(`${row.title} ${next === 'published' ? 'reopened' : 'closed'}`);
                        list.load();
                    },
                },
                {
                    label: 'Repost', icon: 'fa-clone',
                    onClick: async () => {
                        const today = new Date();
                        const closes = new Date(today.getTime() + 30 * 86400000);
                        const copy = Object.assign({}, row, {
                            id: `${row.id}-${today.getFullYear()}`,
                            status: 'draft',
                            postedAt: today.toISOString().slice(0, 10),
                            closesAt: closes.toISOString().slice(0, 10),
                        });
                        delete copy.createdAt;
                        delete copy.updatedAt;
                        try {
                            const made = await store.create('jobs', copy);
                            toast.success('Reposted as a draft', {
                                body: 'Dates moved on by a month. Check the summary before publishing.',
                                action: { label: 'Edit it', onClick: () => { location.href = `job-form?id=${made.id}`; } },
                            });
                            list.load();
                        } catch (e) {
                            toast.error('A repost from this year already exists');
                        }
                    },
                },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: () => list.confirmDelete(row, {
                        label: row.title,
                        body: 'The vacancy leaves the careers page.',
                    }),
                },
            ],
            onRowClick: (row) => { location.href = `job-form?id=${encodeURIComponent(row.id)}`; },
            empty: {
                icon: 'fa-bullhorn', title: 'No vacancies',
                text: 'The careers page is showing its "nothing open" panel and the HR mailto.',
                actionLabel: 'Post a vacancy',
                onAction: () => { location.href = 'job-form'; },
            },
        });
    }
}());
