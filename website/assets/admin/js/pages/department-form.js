/* =========================================================
   Department — tabbed create / edit form.

   The largest record in the panel: one department fills an
   entire public page. Split across eight tabs rather than one
   forty-field wall, with a dot on any tab holding an unfilled
   required field.
   ========================================================= */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const id = U.param('id');
    const isEdit = !!id;
    let record = null;
    let ctrl = null;
    let tabs = null;

    const TABS = [
        ['tab-basics', 'Basics', 'fa-circle-info'],
        ['tab-banner', 'Banner', 'fa-image'],
        ['tab-stats', 'Counters', 'fa-arrow-up-9-1'],
        ['tab-intro', 'Intro', 'fa-align-left'],
        ['tab-procedures', 'Procedures', 'fa-list-check'],
        ['tab-conditions', 'Conditions', 'fa-notes-medical'],
        ['tab-team', 'Team', 'fa-user-group'],
        ['tab-seo', 'SEO', 'fa-magnifying-glass-chart'],
    ];

    window.TMH.boot(init);

    async function init() {
        record = isEdit ? await store.get('departments', id) : null;

        if (isEdit && !record) {
            notFound();
            return;
        }

        paintHead();
        const doctors = await store.all('doctors');
        document.getElementById('view').innerHTML = markup(doctors);

        tabs = U.wireTabs(document, { onChange: markInvalidTabs });
        F.wirePreviews(document);

        ctrl = formLib.create({
            el: '#deptForm',
            bar: '#formBar',
            autosaveKey: `department:${id || 'new'}`,
            onCancel: () => { location.href = 'departments'; },
            onSave: save,
        });

        ctrl.bind(record || defaults());
        media.wire(document);

        document.getElementById('publishLabel').textContent =
            record && record.status === 'published' ? 'Update & republish' : 'Publish';

        wireSlug();
        document.getElementById('deptForm').addEventListener('input', U.debounce(markInvalidTabs, 400));
    }

    function notFound() {
        document.getElementById('view').innerHTML = `
            <article class="card"><div class="empty">
                <div class="empty__art"><i class="fa-solid fa-hospital"></i></div>
                <h3>That department no longer exists</h3>
                <p>It may have been deleted from another tab.</p>
                <a class="btn btn--primary" href="departments">Back to the list</a>
            </div></article>`;
    }

    function defaults() {
        return {
            status: 'draft', showInMenu: true, icon: 'fa-hospital',
            chips: [], stats: [], introBody: [], checks: [],
            procedures: [], conditions: [], doctorIds: [],
        };
    }

    function paintHead() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Content' },
                { label: 'Departments', href: 'departments' },
                { label: isEdit ? record.name : 'New department' },
            ],
            title: isEdit ? 'Edit' : 'Add a',
            accent: 'Department',
            sub: isEdit
                ? `Everything on /${record.id} is edited here.`
                : 'A department creates a public page, a mega-menu entry and a card on the departments index.',
            actions: `
                ${isEdit ? `<a class="btn btn--ghost" href="${SITE}${U.esc(record.id)}" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View page</a>` : ''}
                <a class="btn btn--ghost" href="departments"><i class="fa-solid fa-arrow-left"></i> Back</a>`,
        });
    }

    function markup(doctors) {
        return `
        <form id="deptForm" novalidate>
            <div class="tab-rail">
                <nav class="tab-rail__nav" role="tablist" aria-label="Department sections">
                    ${TABS.map(([tid, label, icon]) => `
                        <button type="button" role="tab" data-tab="${tid}" aria-selected="false">
                            <i class="fa-solid ${icon}"></i> ${label}
                            <i class="fa-solid fa-circle-exclamation warn hidden"></i>
                        </button>`).join('')}
                </nav>

                <article class="card">
                    <!-- ---- BASICS ---- -->
                    <div class="tab-panel" id="tab-basics" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Basics', icon: 'fa-circle-info',
                            sub: 'The name, address and menu entry for this department.',
                            fields: [
                                F.text({ name: 'name', label: 'Department name', required: true, placeholder: 'Cardiology' }),
                                F.text({ name: 'id', label: 'URL slug', required: true, rule: 'slug', placeholder: 'cardiology', hint: 'Becomes <code>/cardiology</code>. Changing it on a live department breaks existing links.' }),
                                F.icon({ name: 'icon', label: 'Icon', required: true, hint: 'Any Font Awesome solid name, e.g. <code>fa-heart-pulse</code>.' }),
                                F.text({ name: 'menuNote', label: 'Mega-menu note', placeholder: '6+ Doctors Available' }),
                                F.select({ name: 'status', label: 'Status', options: [
                                    { value: 'draft', label: 'Draft — not on the site' },
                                    { value: 'published', label: 'Published — live' },
                                    { value: 'hidden', label: 'Hidden — kept, but not shown' },
                                ] }),
                                F.number({ name: 'order', label: 'Display order', min: 1, hint: 'Also sets the mega-menu order.' }),
                                F.toggle({ name: 'showInMenu', label: 'Show in the header mega menu' }),
                            ],
                        })}
                    </div>

                    <!-- ---- BANNER ---- -->
                    <div class="tab-panel" id="tab-banner" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Page banner', icon: 'fa-image',
                            sub: 'The headline splits in two so the second half can carry the brand colour.',
                            fields: [
                                F.media({ name: 'banner', label: 'Banner image', hint: 'Wide crop, at least 1600px across.' }),
                                F.text({ name: 'titleLead', label: 'Headline — first half', required: true, placeholder: 'Cardiology &' }),
                                F.text({ name: 'titleStrong', label: 'Headline — second half', required: true, placeholder: 'Heart Care', hint: 'Rendered in the brand colour.' }),
                                F.textarea({ name: 'lead', label: 'Standfirst', required: true, rows: 3, placeholder: 'One or two sentences a patient would recognise their problem in.' }),
                                F.repeater({ name: 'chips', label: 'Banner chips', cols: 1, addLabel: 'Add a chip', max: 4,
                                    fields: [{ key: 'text', placeholder: '24/7 Cath Lab' }],
                                    hint: 'Up to four. Short claims, not sentences.' }),
                            ],
                        })}
                    </div>

                    <!-- ---- COUNTERS ---- -->
                    <div class="tab-panel" id="tab-stats" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Counters', icon: 'fa-arrow-up-9-1',
                            sub: 'The four animated numbers under the banner. Exactly four — the layout has four columns.',
                            fields: [
                                F.repeater({ name: 'stats', label: '', cols: 5, min: 1, max: 4, addLabel: 'Add a counter',
                                    fields: [
                                        { key: 'icon', type: 'icon', label: 'Icon' },
                                        { key: 'count', type: 'number', label: 'Value', placeholder: '4200' },
                                        { key: 'suffix', label: 'Suffix', placeholder: '+' },
                                        { key: 'label', label: 'Label', placeholder: 'Procedures a year' },
                                        { key: 'note', label: 'Note', placeholder: '18% more than 2024' },
                                    ],
                                    hint: 'These rows also appear on <a href="stats">Counters &amp; Numbers</a>, scoped to this department.' }),
                            ],
                        })}
                    </div>

                    <!-- ---- INTRO ---- -->
                    <div class="tab-panel" id="tab-intro" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Introduction', icon: 'fa-align-left',
                            sub: 'The two-column block below the counters: copy on one side, photo and tick list on the other.',
                            fields: [
                                F.text({ name: 'introTitle', label: 'Section heading', wide: true,
                                    placeholder: 'Every minute of a cardiac event <strong>is treated like one</strong>',
                                    hint: 'A <code>&lt;strong&gt;</code> is allowed here — it is what carries the brand colour.' }),
                                F.repeater({ name: 'introBody', label: 'Paragraphs', cols: 1, addLabel: 'Add a paragraph',
                                    fields: [{ key: 'paragraph', type: 'textarea', label: 'Paragraph' }] }),
                                F.repeater({ name: 'checks', label: 'Tick list', cols: 1, addLabel: 'Add a point', max: 8,
                                    fields: [{ key: 'text', placeholder: '24/7 interventional cover' }],
                                    hint: 'Paste a whole list at once — one line becomes one row.' }),
                                F.media({ name: 'introImg', label: 'Section photo' }),
                            ],
                        })}
                        ${F.divider()}
                        ${F.section({
                            title: 'Floating badge', icon: 'fa-triangle-exclamation',
                            sub: 'The small alert card over the photo. Leave the title empty to drop it.',
                            fields: [
                                F.icon({ name: 'badgeIcon', label: 'Badge icon' }),
                                F.text({ name: 'badgeTitle', label: 'Badge title', placeholder: 'Chest pain?' }),
                                F.textarea({ name: 'badgeText', label: 'Badge text', rows: 2, placeholder: 'Call +91 90460 05557 — do not drive yourself.' }),
                            ],
                        })}
                    </div>

                    <!-- ---- PROCEDURES ---- -->
                    <div class="tab-panel" id="tab-procedures" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Procedures', icon: 'fa-list-check',
                            sub: 'The card grid of what this department actually does.',
                            fields: [
                                F.repeater({ name: 'procedures', label: '', cols: 3, addLabel: 'Add a procedure',
                                    fields: [
                                        { key: 'icon', type: 'icon', label: 'Icon' },
                                        { key: 'title', label: 'Title', placeholder: 'Primary Angioplasty' },
                                        { key: 'text', type: 'textarea', label: 'Description' },
                                    ] }),
                            ],
                        })}
                    </div>

                    <!-- ---- CONDITIONS ---- -->
                    <div class="tab-panel" id="tab-conditions" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Conditions treated', icon: 'fa-notes-medical',
                            sub: 'The chip list a patient scans to decide whether this is the right clinic.',
                            fields: [
                                F.text({ name: 'conditionsTitle', label: 'Section heading', wide: true }),
                                F.textarea({ name: 'conditionsLead', label: 'Standfirst', rows: 2 }),
                                F.repeater({ name: 'conditions', label: 'Conditions', cols: 1, addLabel: 'Add a condition',
                                    fields: [{ key: 'text', placeholder: 'Coronary artery disease' }],
                                    hint: 'Paste your whole list at once — one line per condition.' }),
                            ],
                        })}
                    </div>

                    <!-- ---- TEAM ---- -->
                    <div class="tab-panel" id="tab-team" role="tabpanel" hidden>
                        ${F.section({
                            title: 'Team', icon: 'fa-user-group',
                            sub: 'Consultants shown in the team strip on this department page.',
                            fields: [
                                F.select({
                                    name: 'doctorIds', label: 'Consultants', multiple: true, wide: true,
                                    placeholder: 'Choose consultants',
                                    searchPlaceholder: 'Search doctors',
                                    options: doctors.map((d) => ({ value: d.id, label: `${d.name} — ${d.role}` })),
                                    hint: 'A doctor can belong to several departments. <a href="doctors">Manage doctors</a>.',
                                }),
                            ],
                        })}
                    </div>

                    <!-- ---- SEO ---- -->
                    <div class="tab-panel" id="tab-seo" role="tabpanel" hidden>
                        ${F.seo({ titlePlaceholder: 'Cardiology & Heart Care — Teresa Memorial Hospital' })}
                    </div>

                    ${F.bar()}
                </article>
            </div>
        </form>`;
    }

    /* Slug follows the name until it is touched. On an existing record it is
       left alone and a warning fires the first time it gains focus. */
    function wireSlug() {
        const name = document.querySelector('[name="name"]');
        const slug = document.querySelector('[name="id"]');
        if (isEdit) {
            slug.addEventListener('focus', async () => {
                const ok = await window.TMH.confirm({
                    title: 'Change the URL slug?',
                    body: `The page currently lives at /${record.id}. Renaming breaks every existing link to it.`,
                    icon: 'fa-link-slash',
                    confirmLabel: 'I understand',
                    cancelLabel: 'Leave it alone',
                });
                if (!ok) slug.blur();
            }, { once: true });
            return;
        }
        slug.addEventListener('input', () => { slug.dataset.touched = '1'; });
        name.addEventListener('input', () => {
            if (!slug.dataset.touched) slug.value = U.slug(name.value);
        });
    }

    /* Marks the tab rail so a required field two tabs away is not invisible. */
    function markInvalidTabs() {
        document.querySelectorAll('.tab-panel').forEach((panel) => {
            const bad = [...panel.querySelectorAll('[required]')]
                .some((c) => !String(c.value || '').trim());
            const btn = document.querySelector(`[data-tab="${panel.id}"]`);
            if (!btn) return;
            btn.dataset.invalid = bad ? 'true' : 'false';
            btn.querySelector('.warn').classList.toggle('hidden', !bad);
        });
    }

    async function save(data, opts) {
        const payload = Object.assign({}, data, {
            status: opts.publish ? 'published' : (data.status === 'published' ? 'published' : 'draft'),
            order: data.order || 99,
        });

        if (isEdit) {
            const slugChanged = payload.id && payload.id !== id;
            record = await store.update('departments', id, payload);
            toast.success(opts.publish ? `${record.name} published` : 'Changes saved', {
                action: opts.publish ? { label: 'View page', href: `${SITE}${record.id}` } : null,
            });
            if (slugChanged) {
                await offerRedirect(id, payload.id);
                U.setParams({ id: payload.id });
                setTimeout(() => location.reload(), 400);
            }
        } else {
            record = await store.create('departments', payload);
            toast.success(opts.publish ? `${record.name} published` : 'Saved as draft');
            setTimeout(() => {
                location.href = opts.publish
                    ? `departments?created=${encodeURIComponent(record.id)}`
                    : `department-form?id=${encodeURIComponent(record.id)}`;
            }, 600);
        }
    }

    /* Renaming a live page without a redirect is how a hospital loses its
       search ranking for "cardiology bardhaman". Offer it at the moment it
       matters, not in a settings screen nobody visits. */
    async function offerRedirect(from, to) {
        const ok = await window.TMH.confirm({
            title: 'Add a redirect?',
            body: `Point /${from} at /${to} so old links keep working.`,
            icon: 'fa-right-left',
            confirmLabel: 'Create the redirect',
            cancelLabel: 'No thanks',
        });
        if (!ok) return;
        /* Clean paths on both sides. An old `/${from}` link still lands:
           it misses every route, ErrorController strips the extension and
           301s to `/${from}`, which is the row created here. Two hops, and
           only for the spelling nothing emits any more. */
        await store.create('redirects', {
            from: `/${from}`, to: `/${to}`, code: 301, hits: 0, status: 'published',
        });
        toast.success('Redirect created', {
            action: { label: 'Manage redirects', onClick: () => { location.href = 'redirects'; } },
        });
    }
}());
