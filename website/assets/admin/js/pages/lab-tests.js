/* =========================================================
   Lab tests & health packages.

   One entity, two tabs, because a test and a package are the
   same record with a different `category` — a package just
   carries an `includes` list. Splitting them into two entities
   would double the screen for no gain.

   The home page block renders six featured rows and no more.
   Featuring a seventh does not fail, it silently drops one, so
   this screen says so out loud rather than letting somebody
   discover it on the live site.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, table, fields: F, form: formLib, layout, toast,
    } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const HOME_BLOCK = 6;

    /* Values are the registry's enum (config/resources.php, `category`), never
       the labels. They travel two ways — into `?category=` on the list, and
       into the PATCH body on save — and a label in either place is a filter
       that matches nothing and a save the server refuses. */
    const TEST = 'test';
    const PACKAGE = 'package';

    const CATEGORIES = [
        { value: TEST, label: 'Test' },
        { value: PACKAGE, label: 'Health package' },
    ];

    const TABS = [
        ['tab-tests', TEST, 'Tests', 'fa-vial'],
        ['tab-packages', PACKAGE, 'Health packages', 'fa-clipboard-check'],
    ];

    let list = null;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Lab Tests' }],
            title: 'Lab Tests',
            accent: '& Packages',
            sub: 'The price list behind the diagnostics page and the home page block.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}pathology" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <button type="button" class="btn btn--primary" id="addBtn">
                    <i class="fa-solid fa-plus"></i> Add</button>`,
        });

        document.getElementById('view').innerHTML = `
            <div id="strip"></div>
            <div id="banner"></div>
            <article class="card card--flush anim-item">
                <div class="tabs" role="tablist" aria-label="Kind">
                    ${TABS.map(([tid, , label, icon]) => `
                        <button type="button" role="tab" data-tab="${tid}" aria-selected="false">
                            <i class="fa-solid ${icon}"></i> ${label}
                            <span class="pill" data-count="${U.esc(tid)}">0</span>
                        </button>`).join('')}
                </div>
                <div id="listCard"></div>
            </article>`;

        document.getElementById('addBtn').addEventListener('click', () => edit(null));

        buildTable(categoryFor(U.param('tab')));
        wireFeatureClicks();

        /* Both tabs are the same table underneath, so wireTabs supplies only
           the selected state and the ?tab= round-trip; the switch itself is
           the hidden category filter. The first call matches what the table
           was built with, so it costs nothing. */
        U.wireTabs(document.getElementById('view'), {
            onChange: (tid) => {
                const cat = categoryFor(tid);
                if (!list || list.state.filters.category === cat) return;
                list.state.filters.category = cat;
                list.state.page = 1;
                list.load();
            },
        });

        await paintSummary();
    }

    /* ---------------------------------------------------------
       Summary strip + the over-six warning
       --------------------------------------------------------- */
    async function paintSummary() {
        const rows = await store.all('lab-tests');
        const tests = rows.filter((r) => r.category === TEST);
        const packages = rows.filter((r) => r.category === PACKAGE);
        const featured = rows.filter((r) => r.featured && r.status === 'published');

        document.getElementById('strip').innerHTML = U.statStrip([
            ['fa-vial', 'red', tests.length, 'Tests', `${tests.filter((r) => r.status === 'published').length} published`],
            ['fa-clipboard-check', 'navy', packages.length, 'Health packages', `${packages.filter((r) => r.status === 'published').length} published`],
            ['fa-star', 'blue', featured.length, 'Featured', `The home block shows ${HOME_BLOCK}`],
            ['fa-house-medical', 'magenta', rows.filter((r) => r.homeCollection).length, 'Home collection', 'Sample collected at home'],
        ]);

        document.getElementById('banner').innerHTML = featured.length > HOME_BLOCK ? `
            <div class="banner banner--warn anim-item mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="grow">
                    <b>${featured.length} rows are featured, and the home page block renders ${HOME_BLOCK}.</b>
                    The extra ${featured.length - HOME_BLOCK} will not appear anywhere — the block takes the first ${HOME_BLOCK} by order.
                </span>
                <button type="button" class="btn btn--ghost btn--sm" id="showFeatured">Show featured</button>
            </div>` : '';

        const show = document.getElementById('showFeatured');
        if (show) show.addEventListener('click', () => {
            list.state.filters.featured = 'true';
            list.state.page = 1;
            list.load();
        });

        TABS.forEach(([tid, cat]) => {
            const el = document.querySelector(`[data-count="${tid}"]`);
            if (el) el.textContent = rows.filter((r) => r.category === cat).length;
        });
    }

    /* ---------------------------------------------------------
       Table
       --------------------------------------------------------- */
    function money(n) {
        const v = Number(n) || 0;
        return v ? `₹${U.num(v)}` : '—';
    }

    function categoryFor(tabId) {
        return (TABS.find((t) => t[0] === tabId) || TABS[0])[1];
    }

    function buildTable(initialCategory) {
        list = table.create({
            mount: '#listCard',
            entity: 'lab-tests',
            searchFields: ['name', 'description'],
            searchPlaceholder: 'Search tests and packages',
            reorder: true,
            filters: [
                { key: 'category', label: 'Kind', hidden: true, options: CATEGORIES },
                {
                    key: 'featured',
                    label: 'Featured',
                    options: [{ value: 'true', label: 'Featured only' }, { value: 'false', label: 'Not featured' }],
                    match: (r, v) => !!r.featured === (v === 'true'),
                },
                {
                    key: 'homeCollection',
                    label: 'Home collection',
                    options: [{ value: 'true', label: 'Available' }, { value: 'false', label: 'Not available' }],
                    match: (r, v) => !!r.homeCollection === (v === 'true'),
                },
            ],
            columns: [
                {
                    label: 'Name', sort: 'name', width: '34%',
                    render: (r, s) => `
                        <div class="cell-media">
                            <span class="stat__icon red" style="width:32px;height:32px;font-size:13px;border-radius:var(--radius-xs)">
                                <i class="fa-solid ${U.esc(r.icon || 'fa-vial')}"></i></span>
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}</span>
                                <span class="cell-sub">${U.esc(r.description || '')}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Includes', width: '10%',
                    render: (r) => (r.category === PACKAGE
                        ? `<span class="pill" title="${U.esc((r.includes || []).map((i) => i.item).join(', '))}">${(r.includes || []).length} item${(r.includes || []).length === 1 ? '' : 's'}</span>`
                        : '<span class="muted">—</span>'),
                },
                {
                    label: 'Price', sort: 'price', width: '14%',
                    render: (r) => {
                        const off = Number(r.discountPrice) > 0 && Number(r.discountPrice) < Number(r.price);
                        if (!off) return `<span class="cell-main">${money(r.price)}</span>`;
                        return `
                            <span class="cell-main">${money(r.discountPrice)}</span>
                            <span class="cell-sub"><s>${money(r.price)}</s> · ${
                                Math.round((1 - Number(r.discountPrice) / Number(r.price)) * 100)}% off</span>`;
                    },
                },
                {
                    label: 'Report', width: '13%',
                    render: (r) => `
                        <span class="cell-main">${U.esc(r.reportTime || '—')}</span>
                        <span class="cell-sub">${r.homeCollection ? 'Home collection' : 'At the hospital'}</span>`,
                },
                {
                    label: 'Featured', width: '11%',
                    render: (r) => `
                        <button type="button" class="star-btn ${r.featured ? 'is-on' : ''}"
                                data-feature="${U.esc(r.id)}"
                                aria-pressed="${!!r.featured}"
                                aria-label="${r.featured ? 'Remove' : 'Add'} ${U.esc(r.name)} ${r.featured ? 'from' : 'to'} the home block">
                            <i class="fa-${r.featured ? 'solid' : 'regular'} fa-star"></i>
                            <span>${r.featured ? 'Featured' : 'Feature'}</span>
                        </button>`,
                },
                { label: 'Status', sort: 'status', width: '10%', render: (r) => U.statusTag(r.status) },
            ],
            rowActions: (row) => [
                { label: 'Edit', icon: 'fa-pen', onClick: () => edit(row) },
                {
                    label: row.featured ? 'Remove from home block' : 'Show on home block',
                    icon: 'fa-star',
                    onClick: () => feature(row),
                },
                {
                    label: row.status === 'published' ? 'Unpublish' : 'Publish',
                    icon: row.status === 'published' ? 'fa-eye-slash' : 'fa-cloud-arrow-up',
                    onClick: async () => {
                        const next = row.status === 'published' ? 'hidden' : 'published';
                        await store.update('lab-tests', row.id, { status: next });
                        toast.success(`${row.name} ${next === 'published' ? 'published' : 'hidden'}`);
                        refresh();
                    },
                },
                { divider: true },
                {
                    label: 'Delete', icon: 'fa-trash-can', danger: true,
                    onClick: async () => {
                        const done = await list.confirmDelete(row, {
                            body: 'It disappears from the diagnostics price list immediately.',
                        });
                        if (done) paintSummary();
                    },
                },
            ],
            onRowClick: (row) => edit(row),
            empty: {
                icon: 'fa-vial',
                title: 'Nothing in this list',
                text: 'The diagnostics page prints whatever is here. Empty, it prints nothing.',
                actionLabel: 'Add a test',
                onAction: () => edit(null),
            },
        });

        /* table.create() defaults every filter to 'all'. The first load is
           still in flight and reads state.filters by reference, so setting
           the scope here lands before it renders rather than after. */
        list.state.filters.category = initialCategory;
    }

    /* The table rewrites its own rows on every load, so the star is caught by
       delegation on the card that survives them. */
    function wireFeatureClicks() {
        document.getElementById('listCard').addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-feature]');
            if (!btn) return;
            e.stopPropagation();
            const row = await store.get('lab-tests', btn.dataset.feature);
            if (row) feature(row);
        });
    }

    async function feature(row) {
        await store.update('lab-tests', row.id, { featured: !row.featured });

        const featured = store.allSync('lab-tests').filter((r) => r.featured && r.status === 'published');
        toast.success(row.featured ? `${row.name} removed from the home block` : `${row.name} added to the home block`, {
            body: !row.featured && featured.length > HOME_BLOCK
                ? `That makes ${featured.length} featured — the block still renders only ${HOME_BLOCK}.`
                : '',
            undo: async () => {
                await store.update('lab-tests', row.id, { featured: !!row.featured });
                toast.success('Reverted');
                refresh();
            },
        });
        refresh();
    }

    function refresh() {
        list.load();
        paintSummary();
    }

    /* ---------------------------------------------------------
       Add / edit
       --------------------------------------------------------- */
    async function edit(record) {
        const isPackage = record
            ? record.category === PACKAGE
            : (list.state.filters.category === PACKAGE);

        const data = await formLib.editModal({
            title: record ? `Edit ${record.name}` : `Add a ${isPackage ? 'health package' : 'test'}`,
            subtitle: 'Everything the diagnostics page prints for this row.',
            icon: isPackage ? 'fa-clipboard-check' : 'fa-vial',
            record,
            defaults: {
                category: isPackage ? PACKAGE : TEST,
                status: 'published',
                icon: isPackage ? 'fa-clipboard-check' : 'fa-vial',
                homeCollection: !isPackage,
            },
            html: F.section({
                fields: [
                    F.text({ name: 'name', label: 'Name', required: true, placeholder: 'Complete Blood Count' }),
                    F.select({ name: 'category', label: 'Kind', options: CATEGORIES }),
                    F.icon({ name: 'icon', label: 'Icon', value: record && record.icon }),
                    F.text({ name: 'reportTime', label: 'Report time', placeholder: 'Same day' }),
                    F.textarea({
                        name: 'description', label: 'Description', wide: true, rows: 2, max: 140,
                        placeholder: 'Haemoglobin, white cells and platelets.',
                    }),
                    F.number({
                        name: 'price', label: 'Price (₹)', required: true, min: 0, step: '1',
                        placeholder: '350',
                    }),
                    F.number({
                        name: 'discountPrice', label: 'Discounted price (₹)', min: 0, step: '1',
                        hint: 'Leave at 0 when there is no offer. The full price is struck through when this is lower.',
                    }),
                    F.textarea({
                        name: 'prepInstructions', label: 'Preparation', wide: true, rows: 2,
                        placeholder: 'Fasting 12 hours',
                        hint: 'Printed next to the price. “No fasting needed” is worth saying explicitly.',
                    }),
                    F.repeater({
                        name: 'includes', label: 'What the package includes',
                        addLabel: 'Add an item',
                        fields: [{ key: 'item', placeholder: 'Complete blood count' }],
                        hint: 'Packages only — a single test leaves this empty.',
                    }),
                    F.toggle({ name: 'homeCollection', label: 'Home sample collection available' }),
                    F.toggle({
                        name: 'featured', label: 'Show on the home page block',
                        hint: `The block renders ${HOME_BLOCK} rows, by order.`,
                    }),
                    F.status({}),
                ],
            }),
        });
        if (!data) return;

        /* A test with an includes list is a package somebody forgot to
           retype. Say so rather than saving a row the site cannot render. */
        if (data.category === TEST && (data.includes || []).length) {
            toast.error('A test cannot include other tests', {
                body: 'Change the kind to Health package, or clear the includes list.',
            });
            return;
        }

        if (Number(data.discountPrice) > Number(data.price)) {
            toast.error('The discounted price is higher than the price', {
                body: 'The site would print a negative saving.',
            });
            return;
        }

        if (record) {
            await store.update('lab-tests', record.id, data);
            toast.success(`${data.name} updated`);
        } else {
            const row = await store.create('lab-tests', data);
            toast.success(`${data.name} added`);
            list.state.filters.category = data.category;
            list.load();
            setTimeout(() => list.flash(row.id), 400);
            paintSummary();
            return;
        }
        refresh();
    }
}());
