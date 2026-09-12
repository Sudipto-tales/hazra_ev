/* =========================================================
   Facilities.

   Twelve short records, every field fitting in one dialog, so
   this is a card grid rather than a table — the cards look like
   what the public page renders, which is the whole point of the
   screen. Order matters: the site prints them in this sequence
   and only the first six survive on the home page band.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast,
    } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    /* The home page band renders six. Anything past that only appears on the
       facilities page itself, and the editor should be told which is which. */
    const HOME_BAND = 6;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Facilities' }],
            title: 'Facilities',
            sub: 'The icon grid on the home page and the facilities page. Drag a card to change the order — the first six are the ones the home page shows.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}facilities" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <button type="button" class="btn btn--primary" id="addBtn">
                    <i class="fa-solid fa-plus"></i> Add facility</button>`,
        });

        document.getElementById('addBtn').addEventListener('click', () => edit(null));
        await render();
    }

    async function render() {
        const rows = (await store.all('facilities'))
            .sort((a, b) => (a.order || 0) - (b.order || 0));

        const live = rows.filter((r) => r.status === 'published');

        /* The cut line is drawn down the published sequence, not the raw
           list: hiding the second facility does not push a seventh off the
           home band, it promotes it. */
        const onBand = new Set(live.slice(0, HOME_BAND).map((r) => r.id));

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-hospital', 'red', rows.length, 'Facilities', `${live.length} published`],
                ['fa-house', 'navy', Math.min(live.length, HOME_BAND), 'On the home band', `The band renders ${HOME_BAND}`],
                ['fa-image', 'blue', rows.filter((r) => r.image).length, 'With a photo', 'Used on the facilities page'],
                ['fa-eye-slash', 'magenta', rows.filter((r) => r.status !== 'published').length, 'Not live', 'Draft or hidden'],
            ])}
            <article class="card anim-item">
                <div class="card__head">
                    <div>
                        <h3>All facilities</h3>
                        <p>Click a card to edit it. Drag to reorder — the order is what the site prints.</p>
                    </div>
                    <span class="pill"><i class="fa-solid fa-up-down"></i> Drag to reorder</span>
                </div>
                ${rows.length ? `<div class="tile-grid" id="grid">${rows.map((r) => cardHtml(r, onBand)).join('')}</div>` : emptyHtml()}
            </article>`;

        U.stagger(document.getElementById('view'));
        wire(rows);
    }

    function cardHtml(row, bandIds) {
        return `
        <article class="tile ${row.status !== 'published' ? 'tile--muted' : ''}"
                 data-id="${U.esc(row.id)}" tabindex="0" role="button"
                 aria-label="Edit ${U.esc(row.title)}">
            <span class="drag-handle tile__grip" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
            <div class="tile__icon"><i class="fa-solid ${U.esc(row.icon || 'fa-circle')}"></i></div>
            <div class="tile__body">
                <h4>${U.esc(row.title)}</h4>
                <p>${U.esc(row.text)}</p>
            </div>
            <div class="tile__foot">
                ${U.statusTag(row.status)}
                ${bandIds.has(row.id) ? '<span class="pill pill--soft"><i class="fa-solid fa-house"></i> Home band</span>' : ''}
                ${row.image ? '<span class="pill pill--soft"><i class="fa-solid fa-image"></i> Photo</span>' : ''}
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

    function emptyHtml() {
        return `
        <div class="empty">
            <div class="empty__art"><i class="fa-solid fa-hospital"></i></div>
            <h3>No facilities listed</h3>
            <p>The home page band and the facilities page both read this list. Empty, they render nothing at all.</p>
            <button type="button" class="btn btn--primary" id="emptyAdd"><i class="fa-solid fa-plus"></i> Add facility</button>
        </div>`;
    }

    function wire(rows) {
        const byId = (id) => rows.find((r) => r.id === id);
        const view = document.getElementById('view');

        const empty = document.getElementById('emptyAdd');
        if (empty) empty.addEventListener('click', () => edit(null));

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

        U.sortable(document.getElementById('grid'), '.tile', async (ids) => {
            await store.reorder('facilities', ids);
            toast.success('Order saved', { body: 'The site prints them in this sequence.', id: 'fac-order' });
            render();
        });
    }

    /* ---------------------------------------------------------
       Add / edit
       --------------------------------------------------------- */
    async function edit(record) {
        const data = await formLib.editModal({
            title: record ? `Edit ${record.title}` : 'Add a facility',
            subtitle: 'Everything the public card shows.',
            icon: 'fa-hospital',
            record,
            defaults: { status: 'published', icon: 'fa-hospital' },
            html: F.section({
                fields: [
                    F.text({
                        name: 'title', label: 'Title', required: true, placeholder: '24/7 Emergency',
                    }),
                    F.icon({
                        name: 'icon', label: 'Icon', required: true, value: record && record.icon,
                        hint: 'A Font Awesome solid name — <code>fa-truck-medical</code>, <code>fa-x-ray</code>.',
                    }),
                    F.textarea({
                        name: 'text', label: 'Description', required: true, wide: true, rows: 3,
                        max: 180,
                        placeholder: 'A resuscitation bay, triage nurse and duty physician on site every hour of the year.',
                        hint: 'One or two sentences. The card clamps longer text rather than growing.',
                    }),
                    F.media({
                        name: 'image', label: 'Photo',
                        hint: 'Optional. Shown on the facilities page, not on the home band.',
                    }),
                    F.status({}),
                ],
            }),
        });
        if (!data) return;

        if (record) {
            await store.update('facilities', record.id, data);
            toast.success(`${data.title} updated`);
        } else {
            await store.create('facilities', data);
            toast.success(`${data.title} added`, { body: 'It goes to the end of the list — drag it where it belongs.' });
        }
        render();
    }

    async function toggle(row) {
        const next = row.status === 'published' ? 'hidden' : 'published';
        await store.update('facilities', row.id, { status: next });
        toast.success(`${row.title} ${next === 'published' ? 'published' : 'hidden'}`, {
            undo: async () => {
                await store.update('facilities', row.id, { status: row.status });
                toast.success('Reverted');
                render();
            },
        });
        render();
    }

    async function remove(row) {
        const ok = await window.TMH.confirm({
            title: `Delete “${row.title}”?`,
            body: 'It disappears from the home band and the facilities page. Hiding it keeps the record instead.',
            danger: true,
            confirmLabel: 'Delete facility',
        });
        if (!ok) return;

        const removed = await store.remove('facilities', row.id);
        toast.success(`${row.title} deleted`, {
            undo: async () => {
                await store.restore('facilities', removed.row, removed.index);
                toast.success('Restored');
                render();
            },
        });
        render();
    }
}());
