/* Careers page — section editor.
   Owns CAREER_CHECKS and CAREER_BENEFITS from tools/site-data.mjs
   (lines 556–572). The vacancy list itself lives in Vacancies. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    let page = null;
    let ctrl = null;
    let order = null;

    window.TMH.boot(init);

    async function init() {
        page = await store.get('pages', 'careers');

        const jobs = store.allSync('jobs');
        const openJobs = jobs.filter((j) => j.status === 'published');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Pages', href: 'pages' }, { label: 'Careers Page' }],
            title: 'Careers',
            accent: 'Page',
            sub: 'The copy around the vacancy list. The vacancies themselves are managed separately.',
            actions: `
                <a class="btn btn--ghost" href="jobs"><i class="fa-solid fa-bullhorn"></i> Vacancies</a>
                <a class="btn btn--ghost" href="${SITE}careers" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View page</a>`,
        });

        document.getElementById('view').innerHTML = `
            ${!openJobs.length ? `
            <div class="banner banner--warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="grow">No published vacancies. The careers page is showing its "nothing open" panel — the message below is what visitors read.</span>
                <a class="btn btn--soft btn--sm" href="job-form">Post a vacancy</a>
            </div>` : ''}
            ${markup(openJobs)}`;

        F.wireSections(document, (keys) => {
            order = keys;
            toast.info('Section order changed', { body: 'Save to apply it.' });
        });

        ctrl = formLib.create({
            el: '#pageForm', bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: save,
        });

        ctrl.bind(U.flattenSections(page));
    }

    const on = (key) => U.sectionEnabled(page, key);

    function markup(openJobs) {
        return `
        <form id="pageForm" novalidate>
            ${F.sect({
                key: 'why-us', label: 'Why work here', open: true, enabled: on('why-us'),
                fields: [
                    F.text({ name: 'why-us.title', label: 'Heading', wide: true }),
                    F.repeater({
                        name: 'why-us.checks', label: 'Tick list', cols: 1, addLabel: 'Add a point',
                        fields: [{ key: 'text', placeholder: 'Salaries benchmarked twice a year' }],
                        hint: 'Paste a whole list at once — one line becomes one row.',
                    }),
                ],
            })}

            ${F.sect({
                key: 'what-we-offer', label: 'What we offer', enabled: on('what-we-offer'),
                fields: [
                    F.text({ name: 'what-we-offer.title', label: 'Heading', wide: true }),
                    F.repeater({
                        name: 'what-we-offer.benefits', cols: 3, addLabel: 'Add a benefit',
                        fields: [
                            { key: 'icon', type: 'icon', label: 'Icon' },
                            { key: 'title', label: 'Title' },
                            { key: 'text', type: 'textarea', label: 'Text' },
                        ],
                    }),
                ],
            })}

            ${F.sect({
                key: 'openings', label: 'Open roles', enabled: on('openings'),
                fields: [
                    F.text({ name: 'openings.title', label: 'Heading', wide: true }),
                    F.textarea({ name: 'openings.emptyMessage', label: 'Message when nothing is open', rows: 2,
                        hint: 'An empty vacancy list is a supported state — this is what the page shows instead.' }),
                    F.mirror({
                        label: 'Vacancies', source: 'Vacancies', href: 'jobs',
                        value: `${openJobs.length} published`,
                    }),
                ],
            })}

            ${F.sect({
                key: 'contact-hr', label: 'Contact HR', enabled: on('contact-hr'),
                fields: [
                    F.text({ name: 'contact-hr.title', label: 'Heading' }),
                    F.email({ name: 'contact-hr.email', label: 'HR email address',
                        hint: 'A mailto cannot carry a CV, so the direct route stays on the page alongside the form.' }),
                ],
            })}

            <article class="card mt-6">
                ${F.seo({ optional: true })}
                ${F.bar({ singleSave: true, saveLabel: 'Save careers page' })}
            </article>
        </form>`;
    }

    async function save(flat) {
        page = await store.update('pages', 'careers', U.applySections(page, flat, order));
        order = null;
        toast.success('Careers page saved', {
            action: { label: 'View page', href: `${SITE}careers` },
        });
    }
}());
