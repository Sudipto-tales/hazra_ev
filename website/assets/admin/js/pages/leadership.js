/* Leadership — list.
   A separate entity from Doctor because board members and administrators are
   not clinicians. the about page currently fakes this section by reusing four
   doctor cards. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const CATEGORIES = [
        { value: 'board', label: 'Board of Trustees' },
        { value: 'management', label: 'Management' },
        { value: 'clinical-leadership', label: 'Clinical leadership' },
    ];

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Leadership' }],
            title: 'Leadership',
            sub: 'Board, management and clinical leads for the About page. Separate from the doctor roster — a trustee is not a consultant.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}about#leadership" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <a class="btn btn--primary" href="leadership-form">
                    <i class="fa-solid fa-plus"></i> Add member</a>`,
        });

        const all = await store.all('leadership');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-user-tie', 'red', all.length, 'People listed', `${all.filter((r) => r.status === 'published').length} published`],
                ['fa-gavel', 'navy', all.filter((r) => r.category === 'board').length, 'Board members', ''],
                ['fa-briefcase', 'blue', all.filter((r) => r.category === 'management').length, 'Management', ''],
                ['fa-quote-left', 'magenta', all.filter((r) => r.message).length, 'With a written message', 'Shown as a director’s message'],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        const list = table.create({
            mount: '#listCard',
            entity: 'leadership',
            searchFields: ['name', 'title'],
            searchPlaceholder: 'Search by name or title',
            filters: [{ key: 'category', label: 'Group', options: CATEGORIES }],
            reorder: true,
            columns: [
                {
                    label: 'Name', sort: 'name', width: '28%',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.photo
                                ? `<img class="avatar avatar--sq" src="${U.esc(r.photo)}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.name))}</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.title)}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Group', sort: 'category', width: '18%',
                    render: (r) => {
                        const c = CATEGORIES.find((x) => x.value === r.category);
                        return `<span class="pill">${U.esc(c ? c.label : r.category || '—')}</span>`;
                    },
                },
                {
                    label: 'Message', width: '26%',
                    render: (r) => (r.message
                        ? `<span class="clamp-2">${U.esc(U.plain(r.message))}</span>`
                        : '<span class="muted">None</span>'),
                },
                {
                    label: 'Doctor record', width: '14%',
                    render: (r) => {
                        if (!r.linkedDoctorId) return '<span class="muted">—</span>';
                        const d = store.allSync('doctors').find((x) => x.id === r.linkedDoctorId);
                        return d
                            ? `<a href="doctor-form?id=${U.esc(d.id)}">${U.esc(d.name)}</a>`
                            : '<span class="tag warn">Missing</span>';
                    },
                },
                { label: 'Status', sort: 'status', width: '10%', render: (r) => U.statusTag(r.status) },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `leadership-form?id=${encodeURIComponent(row.id)}`; } },
                {
                    label: row.status === 'published' ? 'Unpublish' : 'Publish',
                    icon: row.status === 'published' ? 'fa-eye-slash' : 'fa-cloud-arrow-up',
                    onClick: async () => {
                        const next = row.status === 'published' ? 'hidden' : 'published';
                        await store.update('leadership', row.id, { status: next });
                        toast.success(`${row.name} ${next === 'published' ? 'published' : 'hidden'}`);
                        list.load();
                    },
                },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: () => list.confirmDelete(row, { body: 'They are removed from the About page leadership strip.' }),
                },
            ],
            onRowClick: (row) => { location.href = `leadership-form?id=${encodeURIComponent(row.id)}`; },
            empty: {
                icon: 'fa-user-tie', title: 'Nobody listed yet',
                text: 'Add the medical director and the board so the About page stops borrowing doctor cards.',
                actionLabel: 'Add member',
                onAction: () => { location.href = 'leadership-form'; },
            },
        });
    }
}());
