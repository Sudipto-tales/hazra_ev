/* =========================================================
   Doctor — create / edit form.

   The reference form screen. Branches once on ?id= at the
   top; everything after that is the same code for create and
   edit. TMH.form.create() owns dirty tracking, the leave
   guard and validation timing.
   ========================================================= */
(function () {
    'use strict';

    const { util: U, store, form: formLib, layout, toast, media } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const id = U.param('id');
    const isEdit = !!id;
    let record = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        paintHead();
        await fillDepartments();

        record = isEdit ? await store.get('doctors', id) : null;

        if (isEdit && !record) {
            document.getElementById('doctorForm').innerHTML = `
                <div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-user-slash"></i></div>
                    <h3>That doctor no longer exists</h3>
                    <p>It may have been deleted from another tab.</p>
                    <a class="btn btn--primary" href="doctors">Back to the list</a>
                </div>`;
            return;
        }

        ctrl = formLib.create({
            el: '#doctorForm',
            bar: '#formBar',
            autosaveKey: `doctor:${id || 'new'}`,
            onCancel: () => { location.href = 'doctors'; },
            onSave: save,
        });

        ctrl.bind(record || defaults());
        media.wire(document);

        /* Publish button reads "Update & republish" on a live record — the
           user should know the change goes straight to the website. */
        document.getElementById('publishLabel').textContent =
            record && record.status === 'published' ? 'Update & republish' : 'Publish';

        wireSlugFromName();
        wirePreview();
        paintMeta();
        offerDraftRestore();
    }

    function defaults() {
        return {
            status: 'draft',
            experienceYears: null,
            departments: [],
            schedule: [],
            isLeadership: false,
            appointmentEnabled: true,
        };
    }

    function paintHead() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Content', href: 'pages' },
                { label: 'Doctors', href: 'doctors' },
                { label: isEdit ? 'Edit' : 'New doctor' },
            ],
            title: isEdit ? 'Edit' : 'Add a',
            accent: 'Doctor',
            sub: isEdit
                ? 'Changes go live the moment you publish.'
                : 'A new doctor starts as a draft — nothing appears on the website until you publish.',
            actions: `<a class="btn btn--ghost" href="doctors">
                <i class="fa-solid fa-arrow-left"></i> Back to list</a>`,
        });
    }

    /* Before ctrl.bind(), so the multi-select already knows the labels for the
       ids the record hands it — options that arrive later would have had their
       chips dropped as unknown. */
    async function fillDepartments() {
        const rows = await store.all('departments');
        window.TMH.multiselect.setOptions(
            document.getElementById('f-depts'),
            rows.sort((a, b) => (a.order || 0) - (b.order || 0))
                .map((d) => ({ value: d.id, label: d.name })),
        );
    }

    /* Typing a name fills the slug — but only while the slug is untouched, so
       an existing URL is never silently rewritten. */
    function wireSlugFromName() {
        const name = document.getElementById('f-name');
        const slug = document.getElementById('f-id');
        if (isEdit) {
            slug.dataset.touched = '1';
            slug.addEventListener('focus', warnSlugChange, { once: true });
            return;
        }
        slug.addEventListener('input', () => { slug.dataset.touched = '1'; });
        name.addEventListener('input', () => {
            if (slug.dataset.touched) return;
            slug.value = U.slug(name.value);
        });
    }

    async function warnSlugChange() {
        const ok = await window.TMH.confirm({
            title: 'Change the URL slug?',
            body: 'Existing links to this doctor will stop working. Add a redirect afterwards if the old address was shared.',
            icon: 'fa-link-slash',
            confirmLabel: 'I understand',
            cancelLabel: 'Leave it alone',
        });
        if (!ok) document.getElementById('f-id').blur();
    }

    /* The rail preview is not decoration — it is the fastest way to catch a
       role that is too long or a portrait that crops badly. */
    function wirePreview() {
        const paint = () => {
            const d = ctrl.collect();
            document.getElementById('preview').innerHTML = `
                <div style="text-align:center;padding:var(--s4) 0">
                    ${d.photo
                        ? `<img src="${U.esc(d.photo)}" alt="" style="width:110px;height:110px;border-radius:var(--radius-sm);object-fit:cover;margin:0 auto var(--s3)">`
                        : `<span style="width:110px;height:110px;border-radius:var(--radius-sm);background:var(--surface-3);display:grid;place-items:center;margin:0 auto var(--s3);color:var(--text-muted);font-size:24px">
                            <i class="fa-solid fa-user"></i></span>`}
                    <h4 style="font-family:var(--font-head);font-size:1rem">${U.esc(d.name || 'Doctor name')}</h4>
                    <p class="text-sm mid">${U.esc(d.role || 'Role')}</p>
                    <p class="text-xs muted mt-2">${U.esc(d.qualification || 'Qualification')}${d.experienceYears ? ` · ${U.esc(d.experienceYears)} yrs` : ''}</p>
                    <div class="mt-4">${U.statusTag(d.status)}</div>
                </div>`;
        };
        document.getElementById('doctorForm').addEventListener('input', U.debounce(paint, 200));
        document.getElementById('doctorForm').addEventListener('change', paint);
        paint();
    }

    function paintMeta() {
        const card = document.getElementById('metaCard');
        if (!isEdit) {
            card.innerHTML = `
                <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s2)">Before you publish</h3>
                <ul class="text-sm mid col gap-2">
                    <li><i class="fa-solid fa-circle-check" style="color:var(--good)"></i> Portrait, name, role and qualification are required</li>
                    <li><i class="fa-solid fa-circle-check" style="color:var(--good)"></i> A meta title is required to publish</li>
                    <li><i class="fa-solid fa-circle-check" style="color:var(--good)"></i> Assign at least one department, or they will not appear on any team strip</li>
                </ul>`;
            return;
        }
        card.innerHTML = `
            <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s3)">Record</h3>
            <dl class="kv">
                <dt>Status</dt><dd>${U.statusTag(record.status)}</dd>
                <dt>Slug</dt><dd><code>${U.esc(record.id)}</code></dd>
                <dt>Last edited</dt><dd>${U.esc(U.ago(record.updatedAt))}</dd>
                <dt>By</dt><dd>${U.esc(record.updatedBy || 'Admin Desk')}</dd>
            </dl>
            <div class="card__foot">
                <button type="button" class="btn btn--ghost btn--sm" id="deleteBtn">
                    <i class="fa-solid fa-trash-can"></i> Delete this doctor</button>
            </div>`;

        document.getElementById('deleteBtn').addEventListener('click', onDelete);
    }

    async function onDelete() {
        const deps = store.dependents('doctors', record.id);
        if (deps.length) {
            await window.TMH.confirm({
                title: `Cannot delete ${record.name}`,
                body: 'Other records depend on this doctor. Reassign them first.',
                blocked: true, danger: true, icon: 'fa-link-slash', dependents: deps,
            });
            return;
        }
        const ok = await window.TMH.confirm({
            title: `Delete ${record.name}?`,
            body: 'They are removed from the doctors page and from every department team strip.',
            danger: true,
            confirmLabel: 'Delete doctor',
        });
        if (!ok) return;

        const removed = await store.remove('doctors', record.id);
        toast.success(`${record.name} deleted`, {
            undo: async () => {
                await store.restore('doctors', removed.row, removed.index);
                toast.success('Restored');
            },
        });
        ctrl.clearDraft();
        setTimeout(() => { location.href = 'doctors'; }, 900);
    }

    /* An autosaved draft that is newer than the stored record means the tab
       closed mid-edit. Offer it rather than silently discarding the work. */
    function offerDraftRestore() {
        const draft = ctrl.restorableDraft();
        if (!draft) return;
        const stale = record && new Date(record.updatedAt).getTime() >= draft.at;
        if (stale) {
            ctrl.clearDraft();
            return;
        }
        toast.warning('Unsaved draft found', {
            body: `From ${U.ago(new Date(draft.at).toISOString())}.`,
            persistent: true,
            id: 'draft-restore',
            action: {
                label: 'Restore it',
                onClick: () => {
                    ctrl.bind(draft.data);
                    toast.success('Draft restored');
                },
            },
        });
    }

    async function save(data, opts) {
        const payload = Object.assign({}, data, {
            status: opts.publish ? 'published' : (data.status === 'published' ? 'published' : 'draft'),
            experienceYears: data.experienceYears || 0,
        });

        if (isEdit) {
            record = await store.update('doctors', id, payload);
            toast.success(opts.publish ? `${record.name} published` : 'Changes saved', {
                action: opts.publish
                    ? { label: 'View on site', href: `${SITE}doctors` }
                    : null,
            });
            paintMeta();
            /* An id change is a slug change — keep the URL in step so a
               reload does not 404 on the old id. */
            if (payload.id && payload.id !== id) {
                U.setParams({ id: payload.id });
                setTimeout(() => location.reload(), 400);
            }
        } else {
            record = await store.create('doctors', payload);
            toast.success(opts.publish ? `${record.name} published` : 'Saved as draft');
            /* Back to the list on publish; stay put on a draft so the user can
               keep filling it in. */
            if (opts.publish) {
                setTimeout(() => {
                    location.href = `doctors?created=${encodeURIComponent(record.id)}`;
                }, 600);
            } else {
                U.setParams({ id: record.id });
                setTimeout(() => location.reload(), 500);
            }
        }
    }
}());
