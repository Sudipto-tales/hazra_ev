/* =========================================================
   Doctors — list screen.

   This is the reference implementation every other list page
   in the panel copies: stat strip, TMH.table.create() with a
   column definition, a dependency guard, and row actions that
   route through table.confirmDelete().
   ========================================================= */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content', href: 'pages' }, { label: 'Doctors' }],
            title: 'Doctor',
            accent: 'Records',
            sub: 'Everything on the public doctors page and on each department’s team strip is driven by this list.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}doctors" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <a class="btn btn--primary" href="doctor-form">
                    <i class="fa-solid fa-plus"></i> Add doctor</a>`,
        });

        /* A doctor cannot be deleted while they are the author of a post or
           the only consultant listed on a department. The confirm modal
           renders whatever this returns. */
        store.registerDependents('doctors', (id) => {
            const out = [];
            store.allSync('posts')
                .filter((p) => p.authorId === id)
                .forEach((p) => out.push(`Author of the post “${p.title}”`));
            store.allSync('departments')
                .filter((d) => (d.doctorIds || []).includes(id))
                .forEach((d) => out.push(`Listed on the ${d.name} department page`));
            return out;
        });

        paintStats();

        const departments = store.allSync('departments')
            .map((d) => ({ value: d.id, label: d.name }));

        const list = table.create({
            mount: '#listCard',
            entity: 'doctors',
            searchFields: ['name', 'role', 'qualification', 'speciality'],
            searchPlaceholder: 'Search by name, role or speciality',
            filters: [{ key: 'departments', label: 'Department', options: departments }],
            reorder: true,
            sort: 'order',
            empty: {
                icon: 'fa-user-doctor',
                title: 'No doctors yet',
                text: 'Add the first consultant and they will appear on the public doctors page straight away.',
                actionLabel: 'Add doctor',
                onAction: () => { location.href = 'doctor-form'; },
            },
            columns: [
                {
                    label: 'Doctor', sort: 'name', width: '26%',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.photo
                                ? `<img class="avatar avatar--sq" src="${U.esc(r.photo)}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.name))}</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.role)}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Qualification', sort: 'qualification',
                    render: (r) => `${U.esc(r.qualification)}<span class="cell-sub">${U.esc(r.speciality || '')}</span>`,
                },
                {
                    label: 'Experience', sort: 'experienceYears', width: '9%',
                    render: (r) => `${U.esc(r.experienceYears || 0)} yrs`,
                },
                {
                    label: 'Departments', width: '18%',
                    render: (r) => {
                        const names = (r.departments || []).map((slug) => {
                            const d = store.allSync('departments').find((x) => x.id === slug);
                            return d ? d.name : slug;
                        });
                        return names.length
                            ? names.map((n) => `<span class="pill">${U.esc(n)}</span>`).join(' ')
                            : '<span class="muted">Unassigned</span>';
                    },
                },
                {
                    label: 'Rating', sort: 'rating', width: '9%',
                    render: (r) => (r.rating
                        ? `<b>${U.esc(r.rating)}</b> <span class="muted">★ ${U.num(r.reviewCount)}</span>`
                        : '<span class="muted">—</span>'),
                },
                {
                    /* The site takes no bookings — this only decides whether the
                       doctor card carries a link to the contact page. */
                    label: 'Appt', sort: 'appointmentEnabled', width: '8%',
                    render: (r) => (r.appointmentEnabled === false
                        ? '<span class="tag off">No</span>'
                        : '<span class="tag ok">Yes</span>'),
                },
                {
                    label: 'Status', sort: 'status', width: '10%',
                    render: (r) => U.statusTag(r.status),
                },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `doctor-form?id=${encodeURIComponent(row.id)}`; } },
                {
                    label: row.status === 'published' ? 'Unpublish' : 'Publish',
                    icon: row.status === 'published' ? 'fa-eye-slash' : 'fa-cloud-arrow-up',
                    onClick: async () => {
                        const next = row.status === 'published' ? 'hidden' : 'published';
                        await store.update('doctors', row.id, { status: next });
                        toast.success(`${row.name} ${next === 'published' ? 'published' : 'hidden'}`);
                        list.load();
                        paintStats();
                    },
                },
                {
                    label: 'Duplicate', icon: 'fa-clone',
                    onClick: async () => {
                        const copy = Object.assign({}, row, {
                            id: `${row.id}-copy`,
                            name: `${row.name} (copy)`,
                            status: 'draft',
                        });
                        delete copy.createdAt;
                        delete copy.updatedAt;
                        await store.create('doctors', copy);
                        toast.success('Duplicated as a draft');
                        list.load();
                        paintStats();
                    },
                },
                { divider: true },
                { label: 'View on site', icon: 'fa-arrow-up-right-from-square', onClick: () => window.open(`${SITE}doctors`, '_blank') },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: async () => {
                        const done = await list.confirmDelete(row, {
                            body: 'They are removed from the doctors page and from every department team strip.',
                        });
                        if (done) paintStats();
                    },
                },
            ],
            onRowClick: (row) => { location.href = `doctor-form?id=${encodeURIComponent(row.id)}`; },
        });

        /* A newly saved record announces itself through the query string, so
           the list can flash the row and confirm the round trip. */
        const created = U.param('created');
        if (created) {
            setTimeout(() => list.flash(created), 500);
            U.setParams({ created: '' });
        }
    }

    async function paintStats() {
        const rows = await store.all('doctors');
        const departments = await store.all('departments');
        const covered = new Set();
        rows.forEach((r) => (r.departments || []).forEach((d) => covered.add(d)));

        const cards = [
            ['fa-user-doctor', 'red', rows.length, 'Doctors on record', `${rows.filter((r) => r.isLeadership).length} on the leadership strip`],
            ['fa-circle-check', 'navy', rows.filter((r) => r.status === 'published').length, 'Published',
                `${rows.filter((r) => r.appointmentEnabled !== false).length} accept appointments`],
            ['fa-pen-ruler', 'blue', rows.filter((r) => r.status !== 'published').length, 'Drafts & hidden', 'Not shown to visitors'],
            ['fa-hospital', 'magenta', `${covered.size}/${departments.length}`, 'Departments covered', covered.size < departments.length ? 'Some have no consultant listed' : 'Every department has a consultant'],
        ];

        document.getElementById('statStrip').innerHTML = cards.map(([icon, tone, value, label, note]) => `
            <article class="card stat c3 anim-item">
                <div class="stat__icon ${tone}"><i class="fa-solid ${icon}"></i></div>
                <h3>${U.esc(value)}</h3>
                <p>${U.esc(label)}</p>
                <span class="delta flat">${U.esc(note)}</span>
            </article>`).join('');

        U.stagger(document.getElementById('statStrip'));
    }
}());
