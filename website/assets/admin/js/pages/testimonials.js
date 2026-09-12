/* =========================================================
   Testimonials.

   `status: draft` doubles as the moderation queue — anything
   arriving from the website form lands there and nothing
   reaches the public rail until somebody approves it. The three
   tabs are that queue, the live rail, and the rejected pile.

   Rejecting hides rather than deletes: a complaint left on the
   feedback form is worth keeping even when it never goes on the
   site, and deleting it is how a hospital loses the only record
   that somebody was unhappy.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast,
    } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    /* The values are the registry's enum (config/resources.php, `source`), not
       the labels. A select built from bare strings uses the label as the value,
       which meant the record's own `manual` matched no option, the select bound
       empty, and the PATCH sent `source: ""` — NULL into a NOT NULL column. */
    const SOURCES = [
        { value: 'website', label: 'Website form' },
        { value: 'google', label: 'Google' },
        { value: 'manual', label: 'Manual' },
    ];

    const sourceLabel = (value) => (SOURCES.find((s) => s.value === value) || {}).label || 'Manual';

    const TABS = [
        ['tab-pending', 'draft', 'Pending', 'fa-inbox'],
        ['tab-published', 'published', 'Published', 'fa-circle-check'],
        ['tab-hidden', 'hidden', 'Rejected', 'fa-eye-slash'],
    ];

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Testimonials' }],
            title: 'Testimonials',
            sub: 'Patient quotes for the home page rail. Nothing goes live until it is approved here.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}#testimonials" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <button type="button" class="btn btn--primary" id="addBtn">
                    <i class="fa-solid fa-plus"></i> Add testimonial</button>`,
        });

        document.getElementById('addBtn').addEventListener('click', () => edit(null));
        await render();
    }

    async function render() {
        const rows = (await store.all('testimonials'))
            .sort((a, b) => (a.order || 0) - (b.order || 0));

        const of = (status) => rows.filter((r) => r.status === status);
        const rated = rows.filter((r) => Number(r.rating) > 0);
        const avg = rated.length
            ? (rated.reduce((n, r) => n + Number(r.rating), 0) / rated.length).toFixed(1)
            : '—';

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-inbox', 'red', of('draft').length, 'Awaiting review', 'Nobody sees these yet'],
                ['fa-circle-check', 'navy', of('published').length, 'Published', 'Live on the home rail'],
                ['fa-star', 'blue', rows.filter((r) => r.featured).length, 'Featured', 'Shown first in the rail'],
                ['fa-star-half-stroke', 'magenta', avg, 'Average rating', `${rated.length} of ${rows.length} rated`],
            ])}

            <article class="card anim-item">
                <div class="tabs" role="tablist" aria-label="Moderation state">
                    ${TABS.map(([tid, status, label, icon]) => `
                        <button type="button" role="tab" data-tab="${tid}" aria-selected="false">
                            <i class="fa-solid ${icon}"></i> ${label}
                            <span class="pill">${of(status).length}</span>
                        </button>`).join('')}
                </div>

                ${TABS.map(([tid, status]) => `
                    <div class="tab-panel" id="${tid}" role="tabpanel" hidden>
                        ${panelHtml(status, of(status))}
                    </div>`).join('')}
            </article>`;

        U.stagger(document.getElementById('view'));
        U.wireTabs(document.getElementById('view'));
        wire(rows);
    }

    function panelHtml(status, list) {
        if (!list.length) return emptyHtml(status);
        return `<div class="quote-grid">${list.map(quoteHtml).join('')}</div>`;
    }

    function emptyHtml(status) {
        const copy = {
            draft: ['fa-inbox', 'Nothing waiting', 'New submissions from the website form land here for approval.'],
            published: ['fa-comment-slash', 'Nothing published', 'The home page rail renders nothing until at least one quote is approved.'],
            hidden: ['fa-eye-slash', 'Nothing rejected', 'Quotes you turn down are kept here rather than deleted.'],
        }[status];

        return `
        <div class="empty">
            <div class="empty__art"><i class="fa-solid ${copy[0]}"></i></div>
            <h3>${copy[1]}</h3>
            <p>${copy[2]}</p>
        </div>`;
    }

    function stars(n) {
        const r = Math.max(0, Math.min(5, Number(n) || 0));
        if (!r) return '<span class="muted text-xs">Not rated</span>';
        return `<span class="stars" aria-label="${r} out of 5">${
            '<i class="fa-solid fa-star"></i>'.repeat(r)
        }${'<i class="fa-regular fa-star"></i>'.repeat(5 - r)}</span>`;
    }

    function quoteHtml(row) {
        const dept = row.departmentId
            ? store.allSync('departments').find((d) => d.id === row.departmentId)
            : null;

        /* A three-star review sitting in the queue is the one an editor most
           needs to notice, so it is flagged rather than left to be read. */
        const lowRating = Number(row.rating) > 0 && Number(row.rating) <= 3;

        return `
        <article class="quote-card ${row.status !== 'published' ? 'quote-card--muted' : ''}" data-id="${U.esc(row.id)}">
            <div class="quote-card__mark"><i class="fa-solid fa-quote-left"></i></div>
            <p class="quote-card__text">${U.esc(row.text)}</p>

            <div class="quote-card__who">
                ${row.photo
                    ? `<img class="avatar" src="${U.esc(row.photo)}" alt="" loading="lazy">`
                    : `<span class="avatar" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(row.name))}</span>`}
                <span>
                    <b>${U.esc(row.name)}</b>
                    <small>${U.esc(row.role || 'Patient')}</small>
                </span>
                <span class="grow"></span>
                ${stars(row.rating)}
            </div>

            <div class="quote-card__meta">
                <span class="pill pill--soft">${U.esc(sourceLabel(row.source))}</span>
                ${dept ? `<span class="pill pill--soft">${U.esc(dept.name)}</span>` : ''}
                ${row.featured ? '<span class="tag info"><i class="fa-solid fa-star"></i> Featured</span>' : ''}
                ${lowRating ? '<span class="tag warn">Low rating</span>' : ''}
            </div>

            <div class="quote-card__foot">
                ${row.status === 'draft' ? `
                    <button type="button" class="btn btn--primary btn--sm" data-act="approve" data-id="${U.esc(row.id)}">
                        <i class="fa-solid fa-check"></i> Approve</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-act="reject" data-id="${U.esc(row.id)}">
                        <i class="fa-solid fa-xmark"></i> Reject</button>` : ''}

                ${row.status === 'published' ? `
                    <button type="button" class="btn btn--ghost btn--sm" data-act="feature" data-id="${U.esc(row.id)}">
                        <i class="fa-${row.featured ? 'solid' : 'regular'} fa-star"></i> ${row.featured ? 'Unfeature' : 'Feature'}</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-act="reject" data-id="${U.esc(row.id)}">
                        <i class="fa-solid fa-eye-slash"></i> Take down</button>` : ''}

                ${row.status === 'hidden' ? `
                    <button type="button" class="btn btn--ghost btn--sm" data-act="approve" data-id="${U.esc(row.id)}">
                        <i class="fa-solid fa-rotate-left"></i> Restore</button>` : ''}

                <span class="grow"></span>
                <button type="button" class="icon-btn" data-act="edit" data-id="${U.esc(row.id)}" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="icon-btn" data-act="delete" data-id="${U.esc(row.id)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
            </div>
        </article>`;
    }

    function wire(rows) {
        const ACTIONS = {
            approve, reject, feature, edit, delete: remove,
        };

        document.getElementById('view').querySelectorAll('[data-act]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = rows.find((r) => r.id === btn.dataset.id);
                const fn = ACTIONS[btn.dataset.act];
                if (row && fn) fn(row);
            });
        });
    }

    /* ---------------------------------------------------------
       Moderation
       --------------------------------------------------------- */
    async function approve(row) {
        await store.update('testimonials', row.id, { status: 'published' });
        toast.success(`${row.name}’s quote is live`, {
            body: 'It now appears in the home page rail.',
            undo: async () => {
                await store.update('testimonials', row.id, { status: row.status });
                toast.success('Put back in the queue');
                render();
            },
        });
        render();
    }

    async function reject(row) {
        const wasLive = row.status === 'published';
        await store.update('testimonials', row.id, { status: 'hidden', featured: false });
        toast.success(wasLive ? `${row.name}’s quote taken down` : `${row.name}’s quote rejected`, {
            body: 'Kept on the Rejected tab — nothing is deleted.',
            undo: async () => {
                await store.update('testimonials', row.id, { status: row.status, featured: !!row.featured });
                toast.success('Reverted');
                render();
            },
        });
        render();
    }

    async function feature(row) {
        await store.update('testimonials', row.id, { featured: !row.featured });
        toast.success(row.featured ? 'No longer featured' : `${row.name}’s quote featured`, {
            body: row.featured ? '' : 'Featured quotes lead the rail.',
        });
        render();
    }

    /* ---------------------------------------------------------
       Add / edit
       --------------------------------------------------------- */
    async function edit(record) {
        const departments = store.allSync('departments');

        const data = await formLib.editModal({
            title: record ? `Edit ${record.name}’s quote` : 'Add a testimonial',
            subtitle: 'Typed in here rather than submitted through the site.',
            icon: 'fa-quote-left',
            record,
            defaults: { status: 'published', source: 'manual', rating: '5' },
            html: F.section({
                fields: [
                    F.textarea({
                        name: 'text', label: 'Quote', required: true, wide: true, rows: 4, max: 320,
                        placeholder: 'In their own words, as they wrote it.',
                        hint: 'Keep the patient’s wording. Fix spelling if you must, never the meaning.',
                    }),
                    F.text({ name: 'name', label: 'Name', required: true, placeholder: 'Anjali Das' }),
                    F.text({
                        name: 'role', label: 'Role', placeholder: 'Patient — Cardiology',
                        hint: 'Printed under the name.',
                    }),
                    F.media({ name: 'photo', label: 'Photo', hint: 'Optional — initials are shown when there is none.' }),
                    F.select({
                        name: 'rating', label: 'Rating',
                        options: [5, 4, 3, 2, 1].map((n) => ({ value: String(n), label: `${n} star${n === 1 ? '' : 's'}` })),
                    }),
                    F.select({
                        name: 'departmentId', label: 'Department', placeholderOption: 'Not department-specific',
                        options: departments.map((d) => ({ value: d.id, label: d.name })),
                    }),
                    F.select({ name: 'source', label: 'Where it came from', options: SOURCES }),
                    F.status({ hint: 'Draft is the moderation queue — nothing on the site reads it.' }),
                    F.toggle({ name: 'featured', label: 'Feature this quote', hint: 'Featured quotes lead the home page rail.' }),
                ],
            }),
        });
        if (!data) return;

        /* The select hands back a string; the content model says 1–5. */
        data.rating = Number(data.rating) || 0;

        if (record) {
            await store.update('testimonials', record.id, data);
            toast.success(`${data.name}’s quote updated`);
        } else {
            await store.create('testimonials', data);
            toast.success(`${data.name}’s quote added`);
        }
        render();
    }

    async function remove(row) {
        const ok = await window.TMH.confirm({
            title: `Delete ${row.name}’s quote?`,
            body: 'Rejecting keeps the record on the Rejected tab. Deleting leaves no trace it was ever submitted.',
            danger: true,
            confirmLabel: 'Delete permanently',
        });
        if (!ok) return;

        const removed = await store.remove('testimonials', row.id);
        toast.success('Testimonial deleted', {
            undo: async () => {
                await store.restore('testimonials', removed.row, removed.index);
                toast.success('Restored');
                render();
            },
        });
        render();
    }
}());
