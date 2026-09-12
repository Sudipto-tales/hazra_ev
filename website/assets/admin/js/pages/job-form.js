/* Vacancy — create / edit.
   Four list fields, which is why the repeater supports paste-a-list: a
   twelve-item responsibilities list should be one paste, not twelve clicks. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const id = U.param('id');
    const isEdit = !!id;
    let record = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        record = isEdit ? await store.get('jobs', id) : null;

        if (isEdit && !record) {
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-bullhorn"></i></div>
                    <h3>That vacancy no longer exists</h3>
                    <a class="btn btn--primary mt-4" href="jobs">Back to vacancies</a>
                </div></article>`;
            return;
        }

        const settings = await store.getDoc('settings');
        const careersEmail = (settings.contact.emails.find((e) => /careers/i.test(e.label || e.address)) || {}).address
            || 'headhrtmh@gmail.com';

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Careers' },
                { label: 'Vacancies', href: 'jobs' },
                { label: isEdit ? record.title : 'New vacancy' },
            ],
            title: isEdit ? 'Edit' : 'Post a',
            accent: 'Vacancy',
            actions: '<a class="btn btn--ghost" href="jobs"><i class="fa-solid fa-arrow-left"></i> Back</a>',
        });

        document.getElementById('view').innerHTML = markup(careersEmail);

        ctrl = formLib.create({
            el: '#jobForm', bar: '#formBar',
            autosaveKey: `job:${id || 'new'}`,
            onCancel: () => { location.href = 'jobs'; },
            onSave: save,
        });

        ctrl.bind(record || defaults(careersEmail));

        document.getElementById('publishLabel').textContent =
            record && record.status === 'published' ? 'Update & republish' : 'Publish vacancy';

        if (!isEdit) {
            const title = document.querySelector('[name="title"]');
            const slug = document.querySelector('[name="id"]');
            slug.addEventListener('input', () => { slug.dataset.touched = '1'; });
            title.addEventListener('input', () => {
                if (!slug.dataset.touched) slug.value = U.slug(title.value);
            });
        }

        if (isEdit) wireApplicationsCard();
    }

    function defaults(careersEmail) {
        const today = new Date();
        const closes = new Date(today.getTime() + 30 * 86400000);
        return {
            status: 'draft', openings: 1, type: 'Full time',
            location: 'Bardhaman — main campus',
            postedAt: today.toISOString().slice(0, 10),
            closesAt: closes.toISOString().slice(0, 10),
            applyEmail: careersEmail,
            responsibilities: [], requirements: [], niceToHave: [], benefits: [],
        };
    }

    function markup(careersEmail) {
        const departments = store.allSync('departments').map((d) => d.name);
        const listField = (name, label, hint) => F.repeater({
            name, label, cols: 1, addLabel: 'Add a point',
            fields: [{ key: 'text', placeholder: 'One point per row' }],
            hint: hint || 'Paste a whole list and it splits into one row per line.',
        });

        return `
        <form id="jobForm" novalidate>
            <div class="split">
                <article class="card">
                    ${F.section({
                        title: 'The role', icon: 'fa-briefcase',
                        fields: [
                            F.text({ name: 'title', label: 'Job title', required: true, placeholder: 'Staff Nurse — Intensive Care' }),
                            F.text({ name: 'id', label: 'URL slug', required: true, rule: 'slug',
                                hint: 'Used as <code>job?id=…</code>.' }),
                            F.select({ name: 'dept', label: 'Department', required: true,
                                placeholderOption: 'Choose a department',
                                options: ['Critical Care', 'Anaesthesia', 'Radiology', 'Administration', 'Physiotherapy', ...departments] }),
                            F.select({ name: 'type', label: 'Contract type',
                                options: ['Full time', 'Part time', 'Contract', 'Locum'] }),
                            F.text({ name: 'location', label: 'Location' }),
                            F.text({ name: 'experience', label: 'Experience wanted', placeholder: '2+ years' }),
                            F.number({ name: 'openings', label: 'Positions to fill', min: 1 }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Description', icon: 'fa-align-left',
                        sub: 'The summary is what a candidate reads first. Concrete beats aspirational — team size, bed count, shift pattern.',
                        fields: [
                            F.textarea({ name: 'summary', label: 'Summary', required: true, rows: 4,
                                placeholder: 'The ICU runs 18 beds across two pods with a 1:2 nurse-to-bed ratio on every shift…' }),
                            listField('responsibilities', 'Responsibilities'),
                            listField('requirements', 'Requirements'),
                            listField('niceToHave', 'Nice to have', 'Optional. Leave empty and the section is dropped from the page.'),
                            listField('benefits', 'What we offer'),
                        ],
                    })}

                    ${F.bar({ saveLabel: 'Publish vacancy' })}
                </article>

                <aside class="split__rail">
                    <article class="card">
                        <div class="card__head"><h3>Dates & visibility</h3></div>
                        <div class="col gap-4">
                            ${F.text({ name: 'postedAt', type: 'date', label: 'Posted on' })}
                            ${F.text({ name: 'closesAt', type: 'date', label: 'Closes on', matchAfter: 'postedAt' })}
                            ${F.select({ name: 'status', label: 'Status', options: [
                                { value: 'draft', label: 'Draft — not advertised' },
                                { value: 'published', label: 'Open — on the careers page' },
                                { value: 'hidden', label: 'Closed — kept for the record' },
                            ] })}
                        </div>
                    </article>

                    <article class="card">
                        <div class="card__head"><h3>Compensation</h3></div>
                        <div class="col gap-4">
                            ${F.number({ name: 'salaryFrom', label: 'From (₹ / month)', min: 0 })}
                            ${F.number({ name: 'salaryTo', label: 'To (₹ / month)', min: 0 })}
                            ${F.text({ name: 'salaryNote', label: 'Note', placeholder: 'Plus night differential' })}
                        </div>
                        <p class="text-xs muted mt-4">Leave both at zero to print “Negotiable”. A stated band gets
                        meaningfully more applications than “as per industry standards”.</p>
                    </article>

                    <article class="card">
                        <div class="card__head"><h3>Applications</h3></div>
                        ${F.email({ name: 'applyEmail', label: 'Send applications to',
                            hint: `Defaults to ${U.esc(careersEmail)} from Contact Details.` })}
                        <div id="appsBox" class="mt-4"></div>
                    </article>
                </aside>
            </div>
        </form>`;
    }

    function wireApplicationsCard() {
        const apps = store.allSync('applications').filter((a) => a.jobId === record.id);
        document.getElementById('appsBox').innerHTML = apps.length
            ? `<dl class="kv">
                    <dt>Received</dt><dd>${apps.length}</dd>
                    <dt>Not reviewed</dt><dd>${apps.filter((a) => a.stage === 'new').length}</dd>
                    <dt>Shortlisted</dt><dd>${apps.filter((a) => a.stage === 'shortlisted').length}</dd>
                </dl>
                <a class="btn btn--ghost btn--sm mt-4" href="applications?jobId=${U.esc(record.id)}">
                    <i class="fa-solid fa-file-signature"></i> Open applications</a>`
            : '<p class="text-sm muted">No applications yet.</p>';
    }

    async function save(data, opts) {
        if (data.closesAt && data.postedAt && new Date(data.closesAt) <= new Date(data.postedAt)) {
            toast.error('The closing date has to be after the posting date');
            return;
        }

        const payload = Object.assign({}, data, {
            status: opts.publish ? 'published' : (data.status === 'published' ? 'published' : data.status || 'draft'),
        });

        if (isEdit) {
            record = await store.update('jobs', id, payload);
            toast.success(opts.publish ? 'Vacancy published' : 'Changes saved', {
                action: opts.publish ? { label: 'View careers page', href: `${SITE}careers` } : null,
            });
            if (payload.id !== id) {
                U.setParams({ id: payload.id });
                setTimeout(() => location.reload(), 400);
            }
        } else {
            record = await store.create('jobs', payload);
            toast.success(opts.publish ? 'Vacancy published' : 'Saved as draft');
            setTimeout(() => { location.href = 'jobs'; }, 600);
        }
    }
}());
