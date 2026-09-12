/* Contact page — section editor.
   Deliberately does NOT re-edit phone numbers or the address. Those live in
   Contact Details; duplicating them here is how two screens end up
   disagreeing about the emergency number. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    let page = null;
    let settings = null;
    let ctrl = null;
    let order = null;

    window.TMH.boot(init);

    async function init() {
        page = await store.get('pages', 'contact');
        settings = await store.getDoc('settings');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Pages', href: 'pages' }, { label: 'Contact Page' }],
            title: 'Contact',
            accent: 'Page',
            sub: 'Headings, the appointment form and the map block. Numbers and addresses come from Contact Details.',
            actions: `
                <a class="btn btn--ghost" href="settings-contact"><i class="fa-solid fa-address-book"></i> Contact details</a>
                <a class="btn btn--ghost" href="${SITE}contact" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View page</a>`,
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
    }

    const on = (key) => U.sectionEnabled(page, key);

    function markup() {
        const c = settings.contact;
        const primary = (c.phones || []).find((p) => p.isPrimary) || (c.phones || [])[0] || {};
        const address = (c.addressLines || []).map((l) => l.line).join(', ');

        return `
        <form id="pageForm" novalidate>
            ${F.sect({
                key: 'reach-us', label: 'Reach us', open: true, enabled: on('reach-us'),
                sub: 'The contact card grid.',
                fields: [
                    F.text({ name: 'reach-us.title', label: 'Heading' }),
                    F.text({ name: 'reach-us.lead', label: 'Standfirst' }),
                    F.mirror({ label: 'Primary phone', value: primary.number || '—', href: 'settings-contact', source: 'Contact Details' }),
                    F.mirror({ label: 'Address', value: `${address}, ${c.city} ${c.pincode}`, href: 'settings-contact', source: 'Contact Details' }),
                    F.mirror({ label: 'Department direct lines', value: `${(c.departmentLines || []).length} lines listed`, href: 'settings-contact', source: 'Contact Details' }),
                ],
            })}

            ${F.sect({
                key: 'appointment', label: 'Appointment form', enabled: on('appointment'),
                sub: 'What the booking form asks for, and what the patient sees after submitting.',
                fields: [
                    F.text({ name: 'appointment.title', label: 'Heading' }),
                    F.text({ name: 'appointment.lead', label: 'Standfirst' }),
                    F.toggle({ name: 'appointment.askDepartment', label: 'Ask which department', wide: false }),
                    F.toggle({ name: 'appointment.askDoctor', label: 'Ask for a specific doctor', wide: false }),
                    F.toggle({ name: 'appointment.askDate', label: 'Ask for a preferred date', wide: false }),
                    F.toggle({ name: 'appointment.askReason', label: 'Ask for a reason for the visit', wide: false }),
                    F.textarea({ name: 'appointment.confirmation', label: 'Confirmation message', rows: 2,
                        hint: 'Shown after submitting. Say when somebody will actually call back.' }),
                    F.mirror({
                        label: 'Where submissions go', source: 'Appointments', href: 'appointments',
                        value: `${store.allSync('appointments').length} requests received`,
                        hint: 'The live form posts nowhere today. Phase 2 wires it to /api/public/appointment.',
                    }),
                ],
            })}

            ${F.sect({
                key: 'location', label: 'Map and directions', enabled: on('location'),
                fields: [
                    F.text({ name: 'location.title', label: 'Heading', wide: true }),
                    F.mirror({ label: 'Map embed', value: c.mapEmbed ? 'Set' : 'Not set', href: 'settings-contact', source: 'Contact Details' }),
                    F.mirror({ label: 'Directions', value: c.directions || '—', href: 'settings-contact', source: 'Contact Details' }),
                ],
            })}

            ${F.sect({
                key: 'cta', label: 'Emergency call to action', enabled: on('cta'),
                fields: [
                    F.text({ name: 'cta.title', label: 'Heading', wide: true }),
                    F.textarea({ name: 'cta.body', label: 'Body', rows: 2 }),
                    F.mirror({ label: 'Emergency number', value: c.emergencyNumber || '—', href: 'settings-contact', source: 'Contact Details' }),
                ],
            })}

            <article class="card mt-6">
                ${F.seo({ optional: true })}
                ${F.bar({ singleSave: true, saveLabel: 'Save contact page' })}
            </article>
        </form>`;
    }

    async function save(flat) {
        page = await store.update('pages', 'contact', U.applySections(page, flat, order));
        order = null;
        toast.success('Contact page saved', {
            action: { label: 'View page', href: `${SITE}contact` },
        });
    }
}());
