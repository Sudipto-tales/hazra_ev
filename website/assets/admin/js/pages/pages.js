/* All Pages — the index of public pages and where each one is edited. */
(function () {
    'use strict';

    const { util: U, store, table, layout } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    /* Which admin screen owns each page. Pages with no editor of their own are
       assembled from a list elsewhere — saying so is more useful than a
       disabled Edit button. */
    const EDITOR = {
        home: { href: 'page-home', label: 'Section editor' },
        about: { href: 'page-about', label: 'Section editor' },
        contact: { href: 'page-contact', label: 'Section editor' },
        careers: { href: 'page-careers', label: 'Section editor' },
        departments: { href: 'departments', label: 'Built from Departments' },
        doctors: { href: 'doctors', label: 'Built from Doctors' },
        facilities: { href: 'facilities', label: 'Built from Facilities' },
        blog: { href: 'blog', label: 'Built from Blog' },
    };

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Pages' }, { label: 'All Pages' }],
            title: 'All',
            accent: 'Pages',
            sub: 'Every page on the public website, and the screen that controls its content.',
            actions: '<a class="btn btn--ghost" href="seo"><i class="fa-solid fa-magnifying-glass-chart"></i> SEO overview</a>',
        });

        const rows = await store.all('pages');
        const departments = store.allSync('departments').filter((d) => d.status === 'published');
        const posts = store.allSync('posts').filter((p) => p.status === 'published');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-file-lines', 'red', rows.length + departments.length, 'Public pages', `${rows.length} fixed + ${departments.length} department pages`],
                ['fa-newspaper', 'navy', posts.length, 'Published articles', 'Each one is a page too'],
                ['fa-layer-group', 'blue', rows.reduce((n, p) => n + (p.sections || []).length, 0), 'Editable sections', 'Across the four section editors'],
                ['fa-triangle-exclamation', 'magenta', rows.filter((p) => !p.metaDescription).length, 'Missing meta description', 'Fix in SEO Manager'],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        table.create({
            mount: '#listCard',
            entity: 'pages',
            searchFields: ['title', 'path'],
            searchPlaceholder: 'Search pages',
            selectable: false,
            bulkActions: [],
            columns: [
                {
                    label: 'Page', sort: 'title', width: '26%',
                    /* No `/` prefix: the stored path carries its own leading
                       slash now. Printing one here would read as `//about`. */
                    render: (r, s) => `<span class="cell-main">${U.mark(r.title, s.q)}</span>
                        <span class="cell-sub">${U.esc(r.path)}</span>`,
                },
                {
                    label: 'Content comes from', width: '22%',
                    render: (r) => {
                        const e = EDITOR[r.id];
                        return e ? `<span class="pill">${U.esc(e.label)}</span>` : '<span class="muted">Static</span>';
                    },
                },
                {
                    label: 'Sections', width: '16%',
                    render: (r) => {
                        const total = (r.sections || []).length;
                        if (!total) return '<span class="muted">—</span>';
                        const on = r.sections.filter((s) => s.enabled !== false).length;
                        return `${on} of ${total} shown${on < total ? ' <span class="tag warn">Some hidden</span>' : ''}`;
                    },
                },
                {
                    label: 'SEO', width: '12%',
                    render: (r) => (r.metaTitle && r.metaDescription
                        ? '<span class="tag ok">Complete</span>'
                        : '<span class="tag warn">Incomplete</span>'),
                },
                { label: 'Updated', sort: 'updatedAt', width: '12%', render: (r) => U.esc(U.ago(r.updatedAt)) },
                { label: 'Status', sort: 'status', width: '10%', render: (r) => U.statusTag(r.status) },
            ],
            rowActions: (row) => {
                const e = EDITOR[row.id];
                const actions = [];
                if (e) actions.push({ label: 'Edit content', icon: 'fa-pen', onClick: () => { location.href = e.href; } });
                actions.push({ label: 'Edit SEO', icon: 'fa-magnifying-glass-chart', onClick: () => { location.href = `seo?page=${encodeURIComponent(row.id)}`; } });
                actions.push({ divider: true });
                /* The leading slash is stripped before joining: SITE already
                   ends in one, and `//about` is a hostname, not a path. */
                actions.push({ label: 'Open public page', icon: 'fa-arrow-up-right-from-square', onClick: () => window.open(SITE + String(row.path || '').replace(/^\/+/, ''), '_blank') });
                return actions;
            },
            onRowClick: (row) => {
                const e = EDITOR[row.id];
                if (e) location.href = e.href;
            },
            empty: { icon: 'fa-file-lines', title: 'No pages', text: '' },
        });
    }
}());
