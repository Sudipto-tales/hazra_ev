/* General settings — hospital identity, logos, hours, maintenance mode. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media } = window.TMH;

    let doc = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        doc = await store.getDoc('settings');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'General Settings' }],
            title: 'General',
            accent: 'Settings',
            sub: 'Identity, logos and opening hours. Phone numbers and addresses live in Contact Details.',
            actions: '<a class="btn btn--ghost" href="settings-contact"><i class="fa-solid fa-address-book"></i> Contact details</a>',
        });

        document.getElementById('view').innerHTML = `
            ${doc.general.maintenanceMode ? `
            <div class="banner banner--warn">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span class="grow"><b>Maintenance mode is on.</b> Visitors see the holding page instead of the website.</span>
            </div>` : ''}

            <form class="card" id="settingsForm" novalidate>
                ${F.section({
                    title: 'Identity', icon: 'fa-hospital',
                    sub: 'Used in the header, the footer, the browser tab and the schema markup.',
                    fields: [
                        F.text({ name: 'name', label: 'Hospital name', required: true }),
                        F.text({ name: 'shortName', label: 'Short name', hint: 'Used in the browser tab and the mobile header.' }),
                        F.text({ name: 'tagline', label: 'Tagline', wide: true, placeholder: 'Compassionate Care, Every Day' }),
                        F.number({ name: 'establishedYear', label: 'Established', min: 1800,
                            hint: 'Drives the “since 2019” line and the milestone timeline.' }),
                        F.text({ name: 'registrationNo', label: 'Registration number', hint: 'Printed in the footer.' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Logos', icon: 'fa-image',
                    fields: [
                        F.media({ name: 'logo', label: 'Header logo', wide: false }),
                        F.media({ name: 'logoDark', label: 'Dark-theme logo', wide: false }),
                        F.media({ name: 'favicon', label: 'Favicon', wide: false, hint: '512×512 PNG.' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Opening hours', icon: 'fa-clock',
                    sub: 'Outpatient hours. Emergency is handled separately below.',
                    fields: [
                        F.repeater({
                            name: 'openingHours', cols: 4, min: 1, max: 7, addLabel: 'Add a day',
                            fields: [
                                { key: 'day', label: 'Day', placeholder: 'Monday' },
                                { key: 'from', type: 'time', label: 'Opens' },
                                { key: 'to', type: 'time', label: 'Closes' },
                                { key: 'closed', type: 'checkbox', label: 'Closed' },
                            ],
                        }),
                        F.toggle({ name: 'emergencyAlwaysOpen', label: 'Emergency department is open 24/7',
                            hint: 'Renders “24/7” beside the emergency line instead of the hours above.' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Maintenance mode', icon: 'fa-screwdriver-wrench',
                    sub: 'Turning this on replaces the whole public website with a holding page. Emergency phone numbers stay visible on it.',
                    fields: [
                        F.toggle({ name: 'maintenanceMode', label: 'Show the holding page instead of the website' }),
                        F.textarea({ name: 'maintenanceMessage', label: 'Holding page message', rows: 3 }),
                    ],
                })}

                ${F.bar({ singleSave: true, saveLabel: 'Save settings' })}
            </form>`;

        ctrl = formLib.create({
            el: '#settingsForm',
            bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: save,
        });

        ctrl.bind(doc.general);
        media.wire(document);
        guardMaintenance();
    }

    /* Turning the whole website off is a decision, not a checkbox. */
    function guardMaintenance() {
        const toggle = document.querySelector('[name="maintenanceMode"]');
        toggle.addEventListener('change', async () => {
            if (!toggle.checked) return;
            const ok = await window.TMH.confirm({
                title: 'Take the website offline?',
                body: 'Every page except the holding message becomes unreachable. Emergency numbers stay visible.',
                danger: true,
                icon: 'fa-power-off',
                confirmLabel: 'Turn on maintenance mode',
                typeToConfirm: 'maintenance',
            });
            if (!ok) toggle.checked = false;
        });
    }

    async function save(data) {
        doc.general = Object.assign({}, doc.general, data);
        await store.setDoc('settings', doc);
        toast.success('Settings saved');
        if (data.maintenanceMode) {
            toast.warning('Site is in maintenance mode', { persistent: true, id: 'maintenance' });
        } else {
            window.TMH.toast.dismiss('maintenance');
        }
    }
}());
