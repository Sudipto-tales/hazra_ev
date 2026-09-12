/* Leadership member — create / edit. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media } = window.TMH;

    const id = U.param('id');
    const isEdit = !!id;
    let record = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        record = isEdit ? await store.get('leadership', id) : null;

        if (isEdit && !record) {
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-user-slash"></i></div>
                    <h3>That entry no longer exists</h3>
                    <a class="btn btn--primary mt-4" href="leadership">Back to the list</a>
                </div></article>`;
            return;
        }

        const doctors = await store.all('doctors');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Content' },
                { label: 'Leadership', href: 'leadership' },
                { label: isEdit ? record.name : 'New member' },
            ],
            title: isEdit ? 'Edit' : 'Add a',
            accent: 'Leader',
            actions: '<a class="btn btn--ghost" href="leadership"><i class="fa-solid fa-arrow-left"></i> Back</a>',
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <form class="card" id="leadForm" novalidate>
                    ${F.section({
                        title: 'Identity', icon: 'fa-id-card',
                        fields: [
                            F.text({ name: 'name', label: 'Full name', required: true, placeholder: 'Dr. Jonathon Ronan' }),
                            F.text({ name: 'id', label: 'URL slug', required: true, rule: 'slug', placeholder: 'medical-director' }),
                            F.text({ name: 'title', label: 'Title', required: true, placeholder: 'Medical Director' }),
                            F.select({
                                name: 'category', label: 'Group', required: true,
                                options: [
                                    { value: 'board', label: 'Board of Trustees' },
                                    { value: 'management', label: 'Management' },
                                    { value: 'clinical-leadership', label: 'Clinical leadership' },
                                ],
                            }),
                            F.media({ name: 'photo', label: 'Portrait', required: true }),
                        ],
                    })}
                    ${F.divider()}
                    ${F.section({
                        title: 'Message', icon: 'fa-quote-left',
                        sub: 'Optional. Filled in, it renders as a director’s message rather than a plain card.',
                        fields: [
                            F.textarea({ name: 'message', label: 'Message', rows: 5,
                                placeholder: 'Two or three sentences in their own voice.' }),
                        ],
                    })}
                    ${F.divider()}
                    ${F.section({
                        title: 'Links & visibility', icon: 'fa-eye',
                        fields: [
                            F.select({
                                name: 'linkedDoctorId', label: 'Linked doctor record',
                                placeholderOption: 'Not a clinician',
                                options: doctors.map((d) => ({ value: d.id, label: d.name })),
                                hint: 'Links the card through to their consultant profile.',
                            }),
                            F.status({}),
                            F.number({ name: 'order', label: 'Display order', min: 1 }),
                        ],
                    })}
                    ${F.bar()}
                </form>

                <aside class="split__rail">
                    <article class="card">
                        <div class="card__head"><h3>Live preview</h3></div>
                        <div id="preview"></div>
                    </article>
                    <article class="card card--quiet" id="metaCard"></article>
                </aside>
            </div>`;

        ctrl = formLib.create({
            el: '#leadForm',
            bar: '#formBar',
            onCancel: () => { location.href = 'leadership'; },
            onSave: save,
        });

        ctrl.bind(record || { status: 'draft', category: 'management' });
        media.wire(document);

        const name = document.querySelector('[name="name"]');
        const slug = document.querySelector('[name="id"]');
        if (!isEdit) {
            slug.addEventListener('input', () => { slug.dataset.touched = '1'; });
            name.addEventListener('input', () => {
                if (!slug.dataset.touched) slug.value = U.slug(name.value);
            });
        }

        const paint = () => {
            const d = ctrl.collect();
            document.getElementById('preview').innerHTML = `
                <div style="text-align:center;padding:var(--s4) 0">
                    ${d.photo
                        ? `<img src="${U.esc(d.photo)}" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto var(--s3)">`
                        : '<span style="width:96px;height:96px;border-radius:50%;background:var(--surface-3);display:grid;place-items:center;margin:0 auto var(--s3);color:var(--text-muted)"><i class="fa-solid fa-user"></i></span>'}
                    <h4 style="font-family:var(--font-head)">${U.esc(d.name || 'Name')}</h4>
                    <p class="text-sm mid">${U.esc(d.title || 'Title')}</p>
                    ${d.message ? `<p class="text-sm muted mt-4" style="font-style:italic">“${U.esc(U.plain(d.message).slice(0, 140))}”</p>` : ''}
                </div>`;
        };
        document.getElementById('leadForm').addEventListener('input', U.debounce(paint, 200));
        paint();

        paintMeta();
    }

    function paintMeta() {
        const card = document.getElementById('metaCard');
        if (!isEdit) {
            card.innerHTML = '<p class="text-sm mid">New entries start as a draft. Nothing appears on the About page until you publish.</p>';
            return;
        }
        card.innerHTML = `
            <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s3)">Record</h3>
            <dl class="kv">
                <dt>Status</dt><dd>${U.statusTag(record.status)}</dd>
                <dt>Last edited</dt><dd>${U.esc(U.ago(record.updatedAt))}</dd>
            </dl>
            <div class="card__foot">
                <button type="button" class="btn btn--ghost btn--sm" id="delBtn">
                    <i class="fa-solid fa-trash-can"></i> Delete</button>
            </div>`;
        document.getElementById('delBtn').addEventListener('click', async () => {
            const ok = await window.TMH.confirm({
                title: `Delete ${record.name}?`, danger: true, confirmLabel: 'Delete',
                body: 'They are removed from the About page leadership strip.',
            });
            if (!ok) return;
            const removed = await store.remove('leadership', record.id);
            toast.success('Deleted', {
                undo: () => store.restore('leadership', removed.row, removed.index),
            });
            setTimeout(() => { location.href = 'leadership'; }, 900);
        });
    }

    async function save(data, opts) {
        const payload = Object.assign({}, data, {
            status: opts.publish ? 'published' : (data.status === 'published' ? 'published' : 'draft'),
        });
        if (isEdit) {
            record = await store.update('leadership', id, payload);
            toast.success(opts.publish ? 'Published' : 'Changes saved');
            paintMeta();
        } else {
            record = await store.create('leadership', payload);
            toast.success(opts.publish ? 'Published' : 'Saved as draft');
            setTimeout(() => { location.href = 'leadership'; }, 600);
        }
    }
}());
