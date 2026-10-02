(function () {
    'use strict';

    const { util: U, store, table, layout, toast } = window.HAZRA;
    const SITE = window.HAZRA.api.base;

    window.HAZRA.boot(init);

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
            sort: 'updated_at',
            dir: 'desc',
            statusOptions: [
                { value: 'all', label: 'All' },
                { value: 'published', label: 'Published' },
                { value: 'hidden', label: 'Hidden' },
            ],
            bulkActions: ['publish', 'hide'],
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
                                ? `<img class="avatar avatar--sq" src="${U.esc(U.resolveUrl(r.hero_image || r.image_path || r.image_url))}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">EV</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.brand || 'Hazra EV')} &bull; ${U.esc(r.model_code || '')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Category', sort: 'category',
                    render: (r) => `<span class="pill">${U.esc(r.category || 'others')}</span>`,
                },
                {
                    label: 'Range / Speed',
                    render: (r) => `${U.esc(r.range_km ?? 'N/A')} km &bull; ${U.esc(r.top_speed_kmph ?? 'N/A')} km/h`,
                },
                {
                    label: 'Battery / Motor',
                    render: (r) => `<span class="cell-main">${U.esc(r.battery_capacity || 'N/A')}</span><span class="cell-sub">${U.esc(r.motor_power || 'N/A')}</span>`,
                },
                {
                    label: 'Colours',
                    render: (r) => U.esc((r.colors || []).map(c => c.name).join(', ') || 'N/A'),
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
                {
                    label: row.status === 'hidden' ? 'Publish' : 'Hide', icon: 'fa-eye',
                    onClick: async () => {
                        await store.update('products', row.id, { active: row.status === 'hidden' });
                        toast.success(`${row.name} ${row.status === 'hidden' ? 'published' : 'hidden'}`);
                        list.load();
                        paintStats();
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
        const scooties = rows.filter(r => (r.category || '').toLowerCase() === 'scooty').length;
        const others = total - scooties;

        document.getElementById('statStrip').innerHTML = U.statStrip([
            ['fa-charging-station', 'navy', total, 'Total Products', 'In database'],
            ['fa-star', 'blue', featured, 'Featured Models', 'Home slider'],
            ['fa-motorcycle', 'red', scooties, 'Scooters', 'E-scooty lineup'],
            ['fa-bicycle', 'magenta', others, 'Other Models', 'Bikes & custom'],
        ]);
    }
}());
