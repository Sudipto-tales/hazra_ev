/* =========================================================
   Contact details.

   This is the screen the whole panel exists for. Today
   +91 90460 05557 and tmh.bdn@gmail.com are typed
   into all 20 public pages — roughly four times each,
   in the header bar, the mobile dock, the CTA band and the
   footer. Changing a number means a repo-wide find-and-replace
   and a rebuild.
   ========================================================= */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    let doc = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        doc = await store.getDoc('settings');
        const departments = await store.all('departments');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Contact Details' }],
            title: 'Contact',
            accent: 'Details',
            sub: 'One place for every phone number, email address and map on the website.',
            actions: `<a class="btn btn--ghost" href="${SITE}contact" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> View contact page</a>`,
        });

        document.getElementById('view').innerHTML = `
            <div class="banner banner--info">
                <i class="fa-solid fa-circle-info"></i>
                <span class="grow">These values appear in the header bar, the mobile dock, every page footer, the contact page and the schema markup — roughly <b>80 places</b> across the site.</span>
            </div>

            <div class="split">
                <form class="card" id="contactForm" novalidate>
                    ${F.section({
                        title: 'Phone numbers', icon: 'fa-phone',
                        sub: 'The primary number is the one shown in the header bar. Dock numbers appear in the sticky mobile bar.',
                        fields: [
                            F.repeater({
                                name: 'phones', cols: 5, min: 1, addLabel: 'Add a number',
                                fields: [
                                    { key: 'label', label: 'Label', placeholder: 'Reception' },
                                    { key: 'number', label: 'Number', placeholder: '+91 90460 05557' },
                                    { key: 'isPrimary', type: 'checkbox', label: 'Primary' },
                                    { key: 'showInHeader', type: 'checkbox', label: 'Header' },
                                    { key: 'showInDock', type: 'checkbox', label: 'Mobile dock' },
                                ],
                            }),
                            F.text({ name: 'emergencyNumber', label: 'Emergency line', required: true, rule: 'phone',
                                hint: 'The number the red emergency button dials. Kept separate so it can never be lost in a reorder.' }),
                            F.text({ name: 'whatsapp', label: 'WhatsApp number', rule: 'phone' }),
                            F.text({ name: 'whatsappMessage', label: 'WhatsApp prefilled message', wide: true }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Email addresses', icon: 'fa-envelope',
                        fields: [
                            F.repeater({
                                name: 'emails', cols: 3, min: 1, addLabel: 'Add an address',
                                fields: [
                                    { key: 'label', label: 'Label', placeholder: 'General' },
                                    { key: 'address', label: 'Address', placeholder: 'tmh.bdn@gmail.com' },
                                    { key: 'showInHeader', type: 'checkbox', label: 'Show in header' },
                                ],
                            }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Address', icon: 'fa-location-dot',
                        fields: [
                            F.repeater({
                                name: 'addressLines', cols: 1, min: 1, addLabel: 'Add a line',
                                fields: [{ key: 'line', placeholder: 'GT Road, Nabapally' }],
                            }),
                            F.text({ name: 'city', label: 'City', required: true }),
                            F.text({ name: 'state', label: 'State' }),
                            F.text({ name: 'pincode', label: 'PIN code' }),
                            F.textarea({ name: 'directions', label: 'Landmark and directions', rows: 2,
                                hint: 'Plain-language directions matter more than coordinates for most visitors.' }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Map', icon: 'fa-map',
                        fields: [
                            F.textarea({ name: 'mapEmbed', label: 'Google Maps embed URL', rows: 2,
                                hint: 'The <code>src</code> from the iframe Google gives you under Share &rsaquo; Embed a map.' }),
                            F.text({ name: 'mapLat', type: 'number', step: 'any', label: 'Latitude', hint: 'Used in the schema markup.' }),
                            F.text({ name: 'mapLng', type: 'number', step: 'any', label: 'Longitude' }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Department direct lines', icon: 'fa-list',
                        sub: 'Shown as a table on the contact page.',
                        fields: [
                            F.repeater({
                                name: 'departmentLines', cols: 2, addLabel: 'Add a line',
                                fields: [
                                    { key: 'department', type: 'select', label: 'Department',
                                        options: ['Emergency', ...departments.map((d) => d.name)] },
                                    { key: 'number', label: 'Number', placeholder: '+91 90460 05557' },
                                ],
                            }),
                        ],
                    })}

                    ${F.bar({ singleSave: true, saveLabel: 'Save contact details' })}
                </form>

                <aside class="split__rail">
                    <article class="card">
                        <div class="card__head"><h3>Header bar preview</h3></div>
                        <div id="previewHeader" style="background:var(--surface-2);border-radius:var(--radius-sm);padding:var(--s3);font-size:var(--fs-sm)"></div>
                        <p class="text-xs muted mt-4">This is the thin bar above the navigation on every public page.</p>
                    </article>

                    <article class="card">
                        <div class="card__head"><h3>Mobile dock preview</h3></div>
                        <div id="previewDock" class="row gap-2"></div>
                    </article>

                    <article class="card card--quiet">
                        <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s2)">Map</h3>
                        <div id="mapBox" style="border-radius:var(--radius-sm);overflow:hidden;background:var(--surface-3);min-height:160px;display:grid;place-items:center;color:var(--text-muted)">
                            <span class="text-sm">Paste an embed URL to preview</span>
                        </div>
                    </article>
                </aside>
            </div>`;

        ctrl = formLib.create({
            el: '#contactForm',
            bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: save,
        });

        ctrl.bind(doc.contact);
        wirePreview();
    }

    function wirePreview() {
        const paint = () => {
            const d = ctrl.collect();
            const header = d.emails.filter((e) => e.showInHeader).map((e) => `<i class="fa-solid fa-envelope"></i> ${U.esc(e.address)}`)
                .concat(d.phones.filter((p) => p.showInHeader).map((p) => `<i class="fa-solid fa-phone"></i> ${U.esc(p.number)}`));

            document.getElementById('previewHeader').innerHTML = header.length
                ? header.map((h) => `<span style="margin-right:var(--s4);white-space:nowrap">${h}</span>`).join('')
                : '<span class="muted">Nothing marked "show in header" — the bar renders empty.</span>';

            const dock = d.phones.filter((p) => p.showInDock);
            document.getElementById('previewDock').innerHTML = dock.length
                ? dock.map((p) => `<span class="btn btn--soft btn--sm"><i class="fa-solid fa-phone"></i> ${U.esc(p.label || p.number)}</span>`).join('')
                : '<span class="muted text-sm">No numbers marked for the mobile dock.</span>';

            const box = document.getElementById('mapBox');
            if (d.mapEmbed && /^https?:\/\//.test(d.mapEmbed)) {
                box.innerHTML = `<iframe src="${U.esc(d.mapEmbed)}" width="100%" height="180" style="border:0"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map preview"></iframe>`;
            }
        };

        document.getElementById('contactForm').addEventListener('input', U.debounce(paint, 250));
        document.getElementById('contactForm').addEventListener('change', paint);
        paint();
    }

    async function save(data) {
        /* Exactly one primary. Without this a reorder can silently leave the
           header bar with no number at all. */
        const phones = data.phones || [];
        if (phones.length && !phones.some((p) => p.isPrimary)) {
            phones[0].isPrimary = true;
            toast.info(`${phones[0].label || phones[0].number} set as the primary number`);
        }
        const primaries = phones.filter((p) => p.isPrimary);
        if (primaries.length > 1) {
            phones.forEach((p, i) => { p.isPrimary = p === primaries[0]; });
            toast.info('Only one number can be primary — kept the first.');
        }

        doc.contact = Object.assign({}, doc.contact, data, { phones });
        await store.setDoc('settings', doc);
        toast.success('Contact details saved', {
            body: 'In Phase 2 this rewrites every page that carries a number.',
        });
    }
}());
