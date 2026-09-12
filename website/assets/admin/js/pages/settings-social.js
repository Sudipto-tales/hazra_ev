/* Social links and languages. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media } = window.TMH;

    let doc = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        doc = await store.getDoc('settings');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Social Links' }],
            title: 'Social',
            accent: 'Links',
            sub: 'Profile links for the header and footer, the default share image, and the language switcher.',
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <form class="card" id="socialForm" novalidate>
                    ${F.section({
                        title: 'Profiles', icon: 'fa-share-nodes',
                        fields: [
                            F.repeater({
                                name: 'social', cols: 4, addLabel: 'Add a profile',
                                fields: [
                                    { key: 'platform', type: 'select', label: 'Platform',
                                        options: ['Facebook', 'Instagram', 'X', 'YouTube', 'LinkedIn', 'WhatsApp'] },
                                    { key: 'url', label: 'URL', placeholder: 'https://facebook.com/…' },
                                    { key: 'showInHeader', type: 'checkbox', label: 'Header' },
                                    { key: 'showInFooter', type: 'checkbox', label: 'Footer' },
                                ],
                            }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Sharing', icon: 'fa-image',
                        sub: 'What appears when someone shares a page that has no image of its own.',
                        fields: [
                            F.media({ name: 'shareImage', label: 'Default share image',
                                hint: '1200×630 works everywhere.' }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Languages', icon: 'fa-language',
                        sub: 'Today the site translates through the Google widget. Turning a language on here adds it to the switcher.',
                        fields: [
                            F.repeater({
                                name: 'languages', cols: 3, min: 1, addLabel: 'Add a language',
                                fields: [
                                    { key: 'code', label: 'Code', placeholder: 'bn' },
                                    { key: 'label', label: 'Label', placeholder: 'বাংলা' },
                                    { key: 'enabled', type: 'checkbox', label: 'Enabled' },
                                ],
                            }),
                            F.select({
                                name: 'defaultLanguage', label: 'Default language',
                                options: [{ value: 'en', label: 'English' }, { value: 'bn', label: 'বাংলা' }, { value: 'hi', label: 'हिन्दी' }],
                            }),
                        ],
                    })}

                    ${F.bar({ singleSave: true, saveLabel: 'Save social settings' })}
                </form>

                <aside class="split__rail">
                    <article class="card">
                        <div class="card__head"><h3>Footer preview</h3></div>
                        <div id="preview" class="row wrap gap-2"></div>
                    </article>
                    <article class="card card--quiet">
                        <p class="text-sm mid">Bengali and Hindi have no glyphs in Inter or Sora. The public site already
                        loads Noto Sans Bengali before first paint when the language is Bangla — see the head script in
                        <code>the home page</code>. Adding a language here does not add that font; that is a Phase 3 change.</p>
                    </article>
                </aside>
            </div>`;

        ctrl = formLib.create({
            el: '#socialForm', bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: async (data) => {
                doc.social = Object.assign({}, doc.social, data);
                await store.setDoc('settings', doc);
                toast.success('Social settings saved');
            },
        });

        ctrl.bind(doc.social);
        media.wire(document);

        const ICONS = {
            Facebook: 'fa-facebook-f', Instagram: 'fa-instagram', X: 'fa-x-twitter',
            YouTube: 'fa-youtube', LinkedIn: 'fa-linkedin-in', WhatsApp: 'fa-whatsapp',
        };
        const paint = () => {
            const d = ctrl.collect();
            const shown = (d.social || []).filter((s) => s.showInFooter);
            document.getElementById('preview').innerHTML = shown.length
                ? shown.map((s) => `<span class="btn btn--soft btn--icon" title="${U.esc(s.platform)}">
                    <i class="fa-brands ${U.esc(ICONS[s.platform] || 'fa-globe')}"></i></span>`).join('')
                : '<span class="muted text-sm">Nothing marked for the footer.</span>';
        };
        document.getElementById('socialForm').addEventListener('input', U.debounce(paint, 200));
        document.getElementById('socialForm').addEventListener('change', paint);
        paint();
    }
}());
