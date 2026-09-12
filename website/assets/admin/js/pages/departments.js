/* Departments — list. Twelve records, each one a whole public page. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Departments' }],
            title: 'Departments',
            sub: 'Each record drives one public department page, its entry in the mega menu, and a card on the departments index.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}departments" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <a class="btn btn--primary" href="department-form">
                    <i class="fa-solid fa-plus"></i> Add department</a>`,
        });

        const all = await store.all('departments');
        const doctors = store.allSync('doctors');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-hospital', 'red', all.length, 'Departments', `${all.filter((d) => d.status === 'published').length} live`],
                ['fa-user-doctor', 'navy', doctors.length, 'Consultants on record', `${all.filter((d) => !(d.doctorIds || []).length).length} departments have none assigned`],
                ['fa-bars', 'blue', all.filter((d) => d.showInMenu).length, 'In the mega menu', 'Drag rows to reorder'],
                ['fa-pen-ruler', 'magenta', all.filter((d) => !d.introTitle).length, 'Missing page content', 'Intro section not filled in'],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        /* A department cannot go while doctors or counters still point at it. */
        store.registerDependents('departments', (id) => {
            const out = [];
            store.allSync('doctors')
                .filter((d) => (d.departments || []).includes(id))
                .forEach((d) => out.push(`${d.name} is assigned to it`));
            store.allSync('counters')
                .filter((c) => c.departmentId === id)
                .forEach((c) => out.push(`Counter “${c.label}” is scoped to it`));
            return out;
        });

        const list = table.create({
            mount: '#listCard',
            entity: 'departments',
            searchFields: ['name', 'id', 'lead'],
            searchPlaceholder: 'Search departments',
            reorder: true,
            columns: [
                {
                    label: 'Department', sort: 'name', width: '26%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="stat__icon red" style="width:32px;height:32px;font-size:13px;border-radius:var(--radius-xs)">
                                <i class="fa-solid ${U.esc(r.icon || 'fa-hospital')}"></i></span>
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">/${U.esc(r.id)}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Page content', width: '18%',
                    render: (r) => {
                        const filled = [
                            r.introTitle,
                            (r.procedures || []).length,
                            (r.conditions || []).length,
                            (r.stats || []).length,
                        ].filter(Boolean).length;
                        const tone = filled === 4 ? 'ok' : (filled === 0 ? 'off' : 'warn');
                        return `<span class="tag ${tone}">${filled}/4 sections</span>`;
                    },
                },
                {
                    label: 'Doctors', width: '9%',
                    render: (r) => ((r.doctorIds || []).length
                        ? U.num((r.doctorIds || []).length)
                        : '<span class="muted">None</span>'),
                },
                {
                    label: 'Mega menu', width: '14%',
                    render: (r) => (r.showInMenu
                        ? `<span class="tag ok">Shown</span><span class="cell-sub">${U.esc(r.menuNote || '')}</span>`
                        : '<span class="tag off">Hidden</span>'),
                },
                { label: 'Updated', sort: 'updatedAt', width: '11%', render: (r) => U.esc(U.ago(r.updatedAt)) },
                { label: 'Status', sort: 'status', width: '10%', render: (r) => U.statusTag(r.status) },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `department-form?id=${encodeURIComponent(row.id)}`; } },
                { label: 'Edit counters', icon: 'fa-arrow-up-9-1', onClick: () => { location.href = `department-form?id=${encodeURIComponent(row.id)}&tab=tab-stats`; } },
                { label: 'Manage team', icon: 'fa-user-group', onClick: () => { location.href = `department-form?id=${encodeURIComponent(row.id)}&tab=tab-team`; } },
                { divider: true },
                {
                    label: row.showInMenu ? 'Remove from menu' : 'Show in menu', icon: 'fa-bars',
                    onClick: async () => {
                        await store.update('departments', row.id, { showInMenu: !row.showInMenu });
                        toast.success(row.showInMenu ? 'Removed from the mega menu' : 'Added to the mega menu');
                        list.load();
                    },
                },
                { label: 'Open public page', icon: 'fa-arrow-up-right-from-square', onClick: () => window.open(`${SITE}${row.id}`, '_blank') },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: () => list.confirmDelete(row, {
                        body: `The public page /${row.id} stops resolving. Add a redirect afterwards if the address has been shared.`,
                    }),
                },
            ],
            onRowClick: (row) => { location.href = `department-form?id=${encodeURIComponent(row.id)}`; },
            empty: {
                icon: 'fa-hospital', title: 'No departments yet',
                text: 'A department gives you a public page, a mega-menu entry and a card on the index.',
                actionLabel: 'Add department',
                onAction: () => { location.href = 'department-form'; },
            },
        });
    }
}());
