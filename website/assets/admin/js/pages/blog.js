/* Blog & News — list. */
(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Blog & News' }],
            title: 'Blog',
            accent: '& News',
            sub: 'The featured post is the one the article page renders in full; the rest are cards on the blog index.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}blog" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View blog</a>
                <a class="btn btn--primary" href="blog-form">
                    <i class="fa-solid fa-pen-nib"></i> Write a post</a>`,
        });

        const all = await store.all('posts');
        const cats = store.allSync('categories').filter((c) => c.type === 'category');
        const authors = store.allSync('doctors');
        const views = all.reduce((n, p) => n + (p.views || 0), 0);

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-newspaper', 'red', all.length, 'Posts', `${all.filter((p) => p.status === 'published').length} published`],
                ['fa-eye', 'navy', U.num(views), 'Total reads', 'Since launch'],
                ['fa-pen-ruler', 'blue', all.filter((p) => p.status === 'draft').length, 'Drafts', 'Not on the site'],
                ['fa-tags', 'magenta', cats.length, 'Categories', `${store.allSync('categories').filter((c) => c.type === 'tag').length} tags`],
            ])}
            <article class="card card--flush" id="listCard"></article>`;
        U.stagger(document.getElementById('view'));

        const list = table.create({
            mount: '#listCard',
            entity: 'posts',
            searchFields: ['title', 'excerpt'],
            searchPlaceholder: 'Search titles and excerpts',
            filters: [
                { key: 'categoryId', label: 'Category', options: cats.map((c) => ({ value: c.id, label: c.name })) },
                { key: 'authorId', label: 'Author', options: authors.map((a) => ({ value: a.id, label: a.name })) },
            ],
            sort: 'publishedAt',
            dir: 'desc',
            columns: [
                {
                    label: 'Post', sort: 'title', width: '34%',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.coverImage
                                ? `<img src="${U.esc(r.coverImage)}" alt="" loading="lazy"
                                     style="width:56px;height:40px;border-radius:var(--radius-xs);object-fit:cover;flex-shrink:0">`
                                : '<span style="width:56px;height:40px;border-radius:var(--radius-xs);background:var(--surface-3);display:grid;place-items:center;color:var(--text-muted);flex-shrink:0"><i class="fa-solid fa-image"></i></span>'}
                            <span style="min-width:0">
                                <span class="cell-main">${r.featured ? '<i class="fa-solid fa-star" style="color:var(--accent-orange)" title="Featured — rendered in full on the article page"></i> ' : ''}${U.mark(r.title, s.q)}</span>
                                <span class="cell-sub clamp-2">${U.esc(r.excerpt || '')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Category', width: '12%',
                    render: (r) => {
                        const c = store.allSync('categories').find((x) => x.id === r.categoryId);
                        return c ? `<span class="pill">${U.esc(c.name)}</span>` : '<span class="tag warn">None</span>';
                    },
                },
                {
                    label: 'Author', width: '14%',
                    render: (r) => {
                        const a = store.allSync('doctors').find((x) => x.id === r.authorId);
                        return a ? U.esc(a.name) : '<span class="muted">Unassigned</span>';
                    },
                },
                {
                    label: 'Published', sort: 'publishedAt', width: '12%',
                    render: (r) => (r.publishedAt ? U.esc(U.fmtDate(r.publishedAt)) : '<span class="muted">—</span>'),
                },
                { label: 'Reads', sort: 'views', width: '9%', render: (r) => U.num(r.views) },
                { label: 'Status', sort: 'status', width: '10%', render: (r) => U.statusTag(r.status) },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `blog-form?id=${encodeURIComponent(row.id)}`; } },
                {
                    label: row.featured ? 'Remove from featured' : 'Make featured', icon: 'fa-star',
                    onClick: () => setFeatured(row, list),
                },
                {
                    label: row.status === 'published' ? 'Unpublish' : 'Publish',
                    icon: row.status === 'published' ? 'fa-eye-slash' : 'fa-cloud-arrow-up',
                    onClick: async () => {
                        const next = row.status === 'published' ? 'hidden' : 'published';
                        await store.update('posts', row.id, {
                            status: next,
                            publishedAt: next === 'published' && !row.publishedAt ? new Date().toISOString() : row.publishedAt,
                        });
                        toast.success(`“${row.title}” ${next === 'published' ? 'published' : 'hidden'}`);
                        list.load();
                    },
                },
                {
                    label: 'Duplicate', icon: 'fa-clone',
                    onClick: async () => {
                        const copy = Object.assign({}, row, {
                            id: `${row.id}-copy`, title: `${row.title} (copy)`,
                            status: 'draft', featured: false, views: 0, publishedAt: null,
                        });
                        delete copy.createdAt;
                        delete copy.updatedAt;
                        await store.create('posts', copy);
                        toast.success('Duplicated as a draft');
                        list.load();
                    },
                },
                { divider: true },
                { label: 'View on site', icon: 'fa-arrow-up-right-from-square', onClick: () => window.open(`${SITE}blog`, '_blank') },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: () => list.confirmDelete(row, {
                        label: `“${row.title}”`,
                        body: 'The article and its card on the blog index are removed.',
                    }),
                },
            ],
            onRowClick: (row) => { location.href = `blog-form?id=${encodeURIComponent(row.id)}`; },
            empty: {
                icon: 'fa-newspaper', title: 'No posts yet',
                text: 'The blog index and the related-posts rail both read from this list.',
                actionLabel: 'Write the first post',
                onAction: () => { location.href = 'blog-form'; },
            },
        });
    }

    /* Only one post can be featured — the article page renders exactly one in
       full. Setting a new one clears the old, and says so. */
    async function setFeatured(row, list) {
        if (row.featured) {
            await store.update('posts', row.id, { featured: false });
            toast.warning('No featured post', {
                body: 'The article page falls back to the most recent published article.',
            });
            list.load();
            return;
        }
        const previous = store.allSync('posts').find((p) => p.featured && p.id !== row.id);
        if (previous) await store.update('posts', previous.id, { featured: false });
        await store.update('posts', row.id, { featured: true });
        toast.success(`“${row.title}” is now the featured post`, {
            body: previous ? `“${previous.title}” is no longer featured.` : '',
        });
        list.load();
    }
}());
