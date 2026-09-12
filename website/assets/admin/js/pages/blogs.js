(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

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

        document.getElementById('view').innerHTML = `<div id="listCard"></div>`;

        const list = table.create({
            mount: '#listCard',
            entity: 'posts',
            filters: { type: 'blog' },
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
                                ? `<img class="avatar avatar--sq" src="${U.esc(r.cover_image)}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">POST</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.title, s.q)}</span>
                                <span class="cell-sub">by ${U.esc(r.author || 'Admin')}</span>
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
}());
