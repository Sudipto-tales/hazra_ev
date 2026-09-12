/* About page — section editor.
   Owns PILLARS, VALUES and MILESTONES, which live in tools/site-data.mjs
   today (lines 514–544). */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    let page = null;
    let ctrl = null;
    let order = null;

    window.TMH.boot(init);

    async function init() {
        page = await store.get('pages', 'about');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Pages', href: 'pages' }, { label: 'About Page' }],
            title: 'About',
            accent: 'Page',
            sub: 'Story, mission, values, milestones and the proof mosaic.',
            actions: `<a class="btn btn--ghost" href="${SITE}about" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> View page</a>`,
        });

        document.getElementById('view').innerHTML = markup();

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
        media.wire(document);
    }

    const on = (key) => U.sectionEnabled(page, key);

    function markup() {
        const leaders = store.allSync('leadership');

        return `
        <form id="pageForm" novalidate>
            ${F.sect({
                key: 'story', label: 'Our story', open: true, enabled: on('story'),
                fields: [
                    F.text({ name: 'story.title', label: 'Heading', wide: true }),
                    F.textarea({ name: 'story.body', label: 'Body', rows: 5,
                        hint: 'Basic HTML is allowed — this block renders as rich text.' }),
                    F.media({ name: 'story.image', label: 'Photo' }),
                ],
            })}

            ${F.sect({
                key: 'purpose', label: 'Mission, vision, values', enabled: on('purpose'),
                sub: 'The three pastel cards under the banner. Fixed at three — the CSS tints them by position.',
                fields: [
                    F.repeater({
                        name: 'purpose.pillars', cols: 3, min: 3, max: 3, addLabel: 'Add a pillar',
                        fields: [
                            { key: 'icon', type: 'icon', label: 'Icon' },
                            { key: 'title', label: 'Title', placeholder: 'Our Mission' },
                            { key: 'text', type: 'textarea', label: 'Text' },
                        ],
                    }),
                ],
            })}

            ${F.sect({
                key: 'values', label: 'Values grid', enabled: on('values'),
                fields: [
                    F.text({ name: 'values.title', label: 'Heading', wide: true }),
                    F.repeater({
                        name: 'values.values', cols: 3, addLabel: 'Add a value',
                        fields: [
                            { key: 'icon', type: 'icon', label: 'Icon' },
                            { key: 'title', label: 'Title' },
                            { key: 'text', type: 'textarea', label: 'Text' },
                        ],
                    }),
                ],
            })}

            ${F.sect({
                key: 'milestones', label: 'Milestones', enabled: on('milestones'),
                sub: 'The timeline. Kept in year order on the page regardless of the order here.',
                fields: [
                    F.text({ name: 'milestones.title', label: 'Heading', wide: true }),
                    F.repeater({
                        name: 'milestones.milestones', cols: 2, addLabel: 'Add a milestone',
                        fields: [
                            { key: 'year', label: 'Year', placeholder: '2011' },
                            { key: 'text', label: 'What happened', placeholder: 'Cath lab commissioned' },
                        ],
                    }),
                ],
            })}

            ${F.sect({
                key: 'leadership', label: 'Leadership strip', enabled: on('leadership'),
                fields: [
                    F.text({ name: 'leadership.title', label: 'Heading' }),
                    F.select({
                        name: 'leadership.category', label: 'Which group to show',
                        options: [
                            { value: 'all', label: 'Everyone' },
                            { value: 'board', label: 'Board of Trustees only' },
                            { value: 'management', label: 'Management only' },
                            { value: 'clinical-leadership', label: 'Clinical leadership only' },
                        ],
                    }),
                    F.mirror({
                        label: 'Content source', source: 'Leadership', href: 'leadership',
                        value: `${leaders.filter((l) => l.status === 'published').length} people published`,
                        hint: 'This section currently reuses doctor cards on the live site. Publishing entries in Leadership replaces them.',
                    }),
                ],
            })}

            ${F.sect({
                key: 'in-practice', label: 'Proof mosaic', enabled: on('in-practice'),
                fields: [
                    F.text({ name: 'in-practice.title', label: 'Heading', wide: true }),
                    F.media({ name: 'in-practice.photoA', label: 'Photo — left', wide: false }),
                    F.media({ name: 'in-practice.photoB', label: 'Photo — right', wide: false }),
                    F.text({ name: 'in-practice.rating', label: 'Rating shown on the badge', placeholder: '4.8' }),
                    F.mirror({
                        label: 'Quotes', source: 'Testimonials', href: 'testimonials',
                        value: `${store.allSync('testimonials').filter((t) => t.featured).length} featured quotes`,
                    }),
                ],
            })}

            ${F.sect({
                key: 'careers-cta', label: 'Careers call to action', enabled: on('careers-cta'),
                fields: [
                    F.text({ name: 'careers-cta.title', label: 'Heading', wide: true }),
                    F.textarea({ name: 'careers-cta.body', label: 'Body', rows: 2 }),
                ],
            })}

            <article class="card mt-6">
                ${F.seo({ optional: true })}
                ${F.bar({ singleSave: true, saveLabel: 'Save about page' })}
            </article>
        </form>`;
    }

    async function save(flat) {
        page = await store.update('pages', 'about', U.applySections(page, flat, order));
        order = null;
        toast.success('About page saved', {
            action: { label: 'View page', href: `${SITE}about` },
        });
    }
}());
