(function () {
    'use strict';

    const { util: U, store, table, layout } = window.HAZRA;
    const SITE = window.HAZRA.api.base;

    window.HAZRA.boot(init);

    function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Blogs' }],
            title: 'Blogs',
            accent: 'Articles',
            sub: 'Manage blog posts, articles, and content updates.',
            actions: `
                <a class="btn btn--primary" href="blog-form">
                    <i class="fa-solid fa-plus"></i> New Blog Post</a>`,
        });

        document.getElementById('view').innerHTML = `
            <div id="statStrip" class="mb-4"></div>
            <div id="listCard"></div>`;

        paintStats();

        const list = table.create({
            mount: '#listCard',
            entity: 'blogs',
            searchFields: ['title', 'slug', 'author'],
            searchPlaceholder: 'Search blogs...',
            sort: 'created_at',
            empty: {
                icon: 'fa-newspaper',
                title: 'No blog posts yet',
                text: 'Publish your first blog article.',
                actionLabel: 'Create Post',
                onAction: () => { location.href = 'blog-form'; },
            },
            columns: [
                {
                    label: 'Title', sort: 'title',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.cover_image
                                ? `<img class="avatar avatar--sq" src="${U.esc(U.resolveUrl(r.cover_image))}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">POST</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.title, s.q)}</span>
                                <span class="cell-sub">by ${U.esc(r.author || 'Hazra EV Team')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Published Date', sort: 'published_at',
                    render: (r) => U.esc(r.published_at || r.created_at || '—'),
                },
                {
                    label: 'Status', sort: 'status',
                    render: (r) => U.statusTag(r.status || 'published'),
                },
            ],
            rowActions: (row) => [
                { label: 'View on site', icon: 'fa-arrow-up-right-from-square', onClick: () => { window.open(`${SITE}blog/${encodeURIComponent(row.slug || row.id)}`, '_blank'); } },
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `blog-form?id=${encodeURIComponent(row.id)}`; } },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: async () => {
                        await list.confirmDelete(row, { body: 'This blog post will be permanently removed.' });
                    },
                },
            ],
            onRowClick: (row) => { location.href = `blog-form?id=${encodeURIComponent(row.id)}`; },
        });
    }

    async function paintStats() {
        const rows = await store.all('blogs');
        const total = rows.length;
        const published = rows.filter(r => r.status === 'published').length;
        const draft = rows.filter(r => r.status === 'draft').length;

        const slot = document.getElementById('statStrip');
        if (slot) {
            slot.innerHTML = U.statStrip([
                ['fa-pen-nib', 'navy', total, 'Total Blogs', 'Articles on record'],
                ['fa-globe', 'blue', published, 'Published', 'Live on website'],
                ['fa-file-pen', 'warn', draft, 'Drafts', 'In progress'],
            ]);
        }
    }
}());
