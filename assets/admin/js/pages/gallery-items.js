/* =========================================================
   Our Gallery — the public wall at /gallery.

   Not the Media Gallery screen next to it in the sidebar.
   That one is the asset library: every file anybody has ever
   uploaded, addressed by picker. This is a curated, ordered,
   published list, and the two have different lifetimes — hiding
   a gallery item must not delete the photo a doctor's profile
   is also using.

   A card grid rather than a table, for the same reason
   facilities.js is: the cards look like what the page renders.
   Order matters — the site prints them in this sequence and the
   album chips read left to right in the same order.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast, api,
    } = window.HAZRA;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = api.base;

    const TYPES = {
        image: { label: 'Photograph', icon: 'fa-image' },
    };

    /* Which album the grid is showing. '' is everything. */
    let album = '';

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Our Gallery' }],
            title: 'Our Gallery',
            sub: 'Published gallery images on /gallery. Drag a card to change the order — the page prints them in this sequence, and the album chips follow it.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}gallery" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <button type="button" class="btn btn--primary" id="addBtn">
                    <i class="fa-solid fa-plus"></i> Add item</button>`,
        });

        document.getElementById('addBtn').addEventListener('click', () => edit(null));
        await render();
    }

    /* ---------------------------------------------------------
       The grid
       --------------------------------------------------------- */
    async function render() {
        const all = (await store.all('gallery'))
            .map((row) => ({ ...row, image: row.image_url || row.image_path || '', type: 'image', status: row.status === 'active' ? 'published' : (row.status || 'hidden') }))
            .sort((a, b) => (Number(a.order_num) || 0) - (Number(b.order_num) || 0));

        const albums = [...new Set(all.map((r) => (r.album || '').trim()).filter(Boolean))];

        /* An album emptied while the screen was open would otherwise leave the
           grid showing nothing and no obvious way back. */
        if (album && !albums.includes(album)) album = '';

        const rows = album ? all.filter((r) => r.album === album) : all;
        const live = all.filter((r) => r.status === 'published');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-photo-film', 'navy', all.length, 'Items', `${live.length} published`],
                ['fa-image', 'blue', all.filter((r) => r.type === 'image').length, 'Photographs', 'Open full size'],
            ])}
            <article class="card anim-item">
                <div class="card__head">
                    <div>
                        <h3>Everything on the wall</h3>
                        <p>Click a card to edit it. Drag to reorder — the page prints them in this sequence.</p>
                    </div>
                    ${albums.length ? albumFilter(albums) : ''}
                </div>
                ${rows.length ? `<div class="tile-grid" id="grid">${rows.map(cardHtml).join('')}</div>` : emptyHtml(album !== '')}
            </article>`;

        U.stagger(document.getElementById('view'));
        wire(all, rows);
    }

    function albumFilter(albums) {
        return `
        <div class="field" style="margin:0;min-width:180px">
            <select id="albumFilter" aria-label="Filter by album">
                <option value="">All albums</option>
                ${albums.map((a) => `<option value="${U.esc(a)}"${a === album ? ' selected' : ''}>${U.esc(a)}</option>`).join('')}
            </select>
        </div>`;
    }

    function cardHtml(row) {
        const kind = TYPES[row.type] || TYPES.image;
        const poster = row.image
            || (row.youtubeId ? `https://img.youtube.com/vi/${encodeURIComponent(row.youtubeId)}/hqdefault.jpg` : '');

        return `
        <article class="tile ${row.status !== 'published' ? 'tile--muted' : ''}"
                 data-id="${U.esc(row.id)}" tabindex="0" role="button"
                 aria-label="Edit ${U.esc(row.title)}">
            <span class="drag-handle tile__grip" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
            ${poster
                ? `<img class="tile__thumb" src="${U.esc(U.resolveUrl(poster))}" alt="" loading="lazy">`
                : `<div class="tile__icon"><i class="fa-${row.type === 'youtube' ? 'brands' : 'solid'} ${U.esc(kind.icon)}"></i></div>`}
            <div class="tile__body">
                <h4>${U.esc(row.title)}</h4>
                <p>${U.esc(row.caption || '')}</p>
            </div>
            <div class="tile__foot">
                ${U.statusTag(row.status)}
                <span class="pill pill--soft"><i class="fa-${row.type === 'youtube' ? 'brands' : 'solid'} ${U.esc(kind.icon)}"></i> ${U.esc(kind.label)}</span>
                ${row.category || row.album ? `<span class="pill pill--soft"><i class="fa-solid fa-folder"></i> ${U.esc(row.category || row.album)}</span>` : ''}
                ${row.size ? `<span class="pill pill--soft"><i class="fa-solid fa-expand"></i> ${U.esc(row.size.toUpperCase())}</span>` : ''}
                ${poster ? '' : '<span class="pill pill--soft"><i class="fa-solid fa-triangle-exclamation"></i> No poster</span>'}
                ${row.sizeBytes ? `<span class="pill pill--soft">${U.esc(U.bytes(row.sizeBytes))}</span>` : ''}
                <span class="grow"></span>
                <button type="button" class="icon-btn" data-edit="${U.esc(row.id)}" aria-label="Edit ${U.esc(row.title)}">
                    <i class="fa-solid fa-pen"></i></button>
                <button type="button" class="icon-btn" data-toggle="${U.esc(row.id)}"
                        aria-label="${row.status === 'published' ? 'Hide' : 'Publish'} ${U.esc(row.title)}">
                    <i class="fa-solid ${row.status === 'published' ? 'fa-eye-slash' : 'fa-cloud-arrow-up'}"></i></button>
                <button type="button" class="icon-btn" data-del="${U.esc(row.id)}" aria-label="Delete ${U.esc(row.title)}">
                    <i class="fa-solid fa-trash-can"></i></button>
            </div>
        </article>`;
    }

    function emptyHtml(filtered) {
        return `
        <div class="empty">
            <div class="empty__art"><i class="fa-solid fa-photo-film"></i></div>
            <h3>${filtered ? 'Nothing in that album' : 'The gallery is empty'}</h3>
            <p>${filtered
                ? 'Pick All albums to see everything, or add an item to this one.'
                : 'The /gallery page renders its own "nothing here yet" panel until something is published.'}</p>
            <button type="button" class="btn btn--primary" id="emptyAdd"><i class="fa-solid fa-plus"></i> Add item</button>
        </div>`;
    }

    function wire(all, rows) {
        const byId = (id) => all.find((r) => r.id === id);
        const view = document.getElementById('view');

        const empty = document.getElementById('emptyAdd');
        if (empty) empty.addEventListener('click', () => edit(null));

        const filter = document.getElementById('albumFilter');
        if (filter) {
            filter.addEventListener('change', () => {
                album = filter.value;
                render();
            });
        }

        view.querySelectorAll('[data-edit]').forEach((b) =>
            b.addEventListener('click', (e) => { e.stopPropagation(); edit(byId(b.dataset.edit)); }));

        view.querySelectorAll('[data-toggle]').forEach((b) =>
            b.addEventListener('click', (e) => { e.stopPropagation(); toggle(byId(b.dataset.toggle)); }));

        view.querySelectorAll('[data-del]').forEach((b) =>
            b.addEventListener('click', (e) => { e.stopPropagation(); remove(byId(b.dataset.del)); }));

        view.querySelectorAll('.tile').forEach((tile) => {
            tile.addEventListener('click', (e) => {
                if (e.target.closest('.icon-btn')) return;
                edit(byId(tile.dataset.id));
            });
            tile.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    edit(byId(tile.dataset.id));
                }
            });
        });

        const grid = document.getElementById('grid');
        if (!grid) return;

        U.sortable(grid, '.tile', async (ids) => {
            const orderedIds = album ? merge(all, rows, ids) : ids;
            for (const [index, id] of orderedIds.entries()) {
                await store.update('gallery', id, { order_num: index + 1 });
            }
            toast.success('Order saved', { body: 'The page prints them in this sequence.', id: 'gal-order' });
            render();
        });
    }

    /**
     * Splice a filtered album's new sequence back into the whole list.
     *
     * Reorder is sent for the entire collection, so a filtered view has to
     * hand back the rows it is not showing as well. The rows outside the album
     * keep their positions and the ones inside are dealt, in their new order,
     * into the slots the album already occupied. Sending only the visible ids
     * would put every hidden row at the end of the gallery.
     */
    function merge(all, shown, ids) {
        const inAlbum = new Set(shown.map((r) => r.id));
        const queue = ids.slice();

        return all.map((row) => (inAlbum.has(row.id) ? queue.shift() : row.id));
    }

    /* ---------------------------------------------------------
       Add / edit

       One dialog for three kinds of item. The fields that do not
       apply are hidden rather than absent, so switching the kind
       back does not lose what was already typed — and blanked on
       save, so an image record cannot keep a stale YouTube id.
       --------------------------------------------------------- */
    async function edit(record) {
        const known = [...new Set((store.allSync('gallery') || [])
            .map((r) => (r.album || '').trim()).filter(Boolean))];

        const data = await formLib.editModal({
            title: record ? `Edit ${record.title}` : 'Add a gallery item',
            subtitle: 'Choose an image and publish it on the gallery page.',
            icon: 'fa-photo-film',
            record,
            defaults: { status: 'published', type: 'image', album: 'Products', category: 'Products', size: 'sm' },
            html: F.section({
                fields: [
                    F.text({
                        name: 'album', label: 'Category / Album', list: 'galAlbums',
                        placeholder: 'Products',
                        hint: 'Becomes a filter chip on the public gallery page (e.g. Products, Events, Factory, Riders).',
                    }),
                    `<datalist id="galAlbums">${known.map((a) => `<option value="${U.esc(a)}"></option>`).join('')}</datalist>`,
                    F.select({
                        name: 'size', label: 'Tile Size (Masonry Grid)', required: true,
                        options: [
                            { value: 'sm', label: 'Small (1×1 Square)' },
                            { value: 'wide', label: 'Wide (2×1 Landscape)' },
                            { value: 'tall', label: 'Tall (1×2 Portrait)' },
                            { value: 'lg', label: 'Large (2×2 Big Tile)' },
                        ],
                    }),
                    F.text({
                        name: 'title', label: 'Title', required: true, wide: true,
                        placeholder: 'Showroom opening',
                    }),
                    F.textarea({
                        name: 'caption', label: 'Caption', wide: true, rows: 2, max: 180,
                        placeholder: 'Describe this gallery photo.',
                        hint: 'Shown under the tile, and again in the viewer.',
                    }),

                    F.media({
                        name: 'image', label: 'Gallery image', required: true,
                        hint: 'Upload an image, select one from the library, or paste its URL.',
                    }),

                    F.status({}),
                ],
            }),
        });
        if (!data) return;

        if (!String(data.image || '').trim()) {
            toast.error('Choose a gallery image before saving.');
            return;
        }
        data.image_url = data.image;
        data.image_path = data.image;
        data.category = data.album || data.category || 'General';
        data.album = data.category;

        if (record) {
            await store.update('gallery', record.id, data);
            toast.success(`${data.title} updated`);
        } else {
            await store.create('gallery', data);
            toast.success(`${data.title} added`, { body: 'It goes to the end of the list — drag it where it belongs.' });
        }
        render();
    }

    async function toggle(row) {
        const next = row.status === 'published' ? 'hidden' : 'published';
        await store.update('gallery', row.id, { status: next });
        toast.success(`${row.title} ${next === 'published' ? 'published' : 'hidden'}`, {
            undo: async () => {
                await store.update('gallery', row.id, { status: row.status });
                toast.success('Reverted');
                render();
            },
        });
        render();
    }

    async function remove(row) {
        const ok = await window.HAZRA.confirm({
            title: `Delete “${row.title}”?`,
            body: 'It disappears from /gallery. The uploaded file stays on the server; hiding it keeps the record too.',
            danger: true,
            confirmLabel: 'Delete item',
        });
        if (!ok) return;

        await store.remove('gallery', row.id);
        toast.success(`${row.title} deleted`);
        render();
    }
}());
