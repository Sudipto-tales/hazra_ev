(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.TMH;
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Catalog' }, { label: 'Products' }],
            title: 'Products',
            accent: 'Catalog',
            sub: 'Manage EV scooters, specifications, featured status, and hero images.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}products" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <a class="btn btn--primary" href="product-form">
                    <i class="fa-solid fa-plus"></i> Add Product</a>`,
        });

        document.getElementById('view').innerHTML = `
            <div id="statStrip" class="grid-stats mb-lg"></div>
            <div id="listCard"></div>
        `;

        paintStats();

        const list = table.create({
            mount: '#listCard',
            entity: 'products',
            searchFields: ['name', 'model_code', 'category', 'brand'],
            searchPlaceholder: 'Search products by name or model code',
            sort: 'created_at',
            empty: {
                icon: 'fa-charging-station',
                title: 'No products found',
                text: 'Add your first EV model to show on the website.',
                actionLabel: 'Add product',
                onAction: () => { location.href = 'product-form'; },
            },
            columns: [
                {
                    label: 'Product', sort: 'name',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.hero_image || r.image_path || r.image_url
                                ? `<img class="avatar avatar--sq" src="${U.esc(r.hero_image || r.image_path || r.image_url)}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">EV</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.brand || 'Hazra EV')} &bull; ${U.esc(r.model_code || '')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Category', sort: 'category',
                    render: (r) => `<span class="pill">${U.esc(r.category || 'High Speed')}</span>`,
                },
                {
                    label: 'Range / Speed',
                    render: (r) => `${U.esc(r.range_km || 'N/A')} km &bull; ${U.esc(r.top_speed_kmph || 'N/A')} km/h`,
                },
                {
                    label: 'Featured', sort: 'is_featured',
                    render: (r) => (r.is_featured
                        ? `<span class="tag ok"><i class="fa-solid fa-star"></i> Featured (${U.esc(r.featured_order || 0)})</span>`
                        : '<span class="tag muted">Standard</span>'),
                },
                {
                    label: 'Status', sort: 'status',
                    render: (r) => U.statusTag(r.status || 'published'),
                },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => { location.href = `product-form?id=${encodeURIComponent(row.id)}`; } },
                {
                    label: row.is_featured ? 'Unfeature' : 'Mark Featured',
                    icon: row.is_featured ? 'fa-star-half-stroke' : 'fa-star',
                    onClick: async () => {
                        const next = row.is_featured ? 0 : 1;
                        await store.update('products', row.id, { is_featured: next });
                        toast.success(`${row.name} ${next ? 'marked as featured' : 'unfeatured'}`);
                        list.load();
                        paintStats();
                    },
                },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: async () => {
                        const done = await list.confirmDelete(row, {
                            body: 'This product will be removed from the catalog.',
                        });
                        if (done) paintStats();
                    },
                },
            ],
            onRowClick: (row) => { location.href = `product-form?id=${encodeURIComponent(row.id)}`; },
        });

        const created = U.param('created');
        if (created) {
            setTimeout(() => list.flash(created), 500);
            U.setParams({ created: '' });
        }
    }

    async function paintStats() {
        const rows = await store.all('products');
        const total = rows.length;
        const featured = rows.filter(r => r.is_featured).length;

        document.getElementById('statStrip').innerHTML = `
            <article class="card stat anim-item">
                <div class="stat__icon navy"><i class="fa-solid fa-charging-station"></i></div>
                <h3>${total}</h3>
                <p>Total Products</p>
                <span class="delta flat">In database</span>
            </article>
            <article class="card stat anim-item">
                <div class="stat__icon blue"><i class="fa-solid fa-star"></i></div>
                <h3>${featured}</h3>
                <p>Featured Products</p>
                <span class="delta flat">Shown on home slider</span>
            </article>
        `;
    }
}());
