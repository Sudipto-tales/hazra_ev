(function () {
    'use strict';

    const { util: U, store, table, layout } = window.TMH;

    window.TMH.boot(init);

    function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'News' }],
            title: 'News & Announcements',
            accent: 'Press',
            sub: 'Publish press releases and news updates.',
            actions: `<a class="btn btn--primary" href="news-form"><i class="fa-solid fa-plus"></i> New News Article</a>`,
        });

        document.getElementById('view').innerHTML = `<div id="listCard"></div>`;

        const list = table.create({
            mount: '#listCard',
            entity: 'posts',
            filters: { type: 'news' },
            searchFields: ['title', 'author'],
            searchPlaceholder: 'Search news...',
            sort: 'created_at',
            empty: {
                icon: 'fa-bullhorn',
                title: 'No news updates yet',
                text: 'Publish your first news post.',
                actionLabel: 'Add News',
                onAction: () => { location.href = 'news-form'; },
            },
            columns: [
                {
                    label: 'News Title', sort: 'title',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.cover_image
                                ? `<img class="avatar avatar--sq" src="${U.esc(r.cover_image)}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">NEWS</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.title, s.q)}</span>
                                <span class="cell-sub">by ${U.esc(r.author || 'Admin')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Date', sort: 'created_at',
                    render: (r) => U.esc(r.published_at || r.created_at || '—'),
                },
                {
                    label: 'Status', sort: 'status',
                    render: (r) => U.statusTag(r.status || 'published'),
                },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `news-form?id=${encodeURIComponent(row.id)}`; } },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: async () => {
                        await list.confirmDelete(row, { body: 'Delete this news entry?' });
                    },
                },
            ],
            onRowClick: (row) => { location.href = `news-form?id=${encodeURIComponent(row.id)}`; },
        });
    }
}());
