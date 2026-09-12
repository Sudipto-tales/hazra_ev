/* Theme & branding — brand colours, fonts, default theme.
   The contrast check is not decoration: #C1272D on white is 5.9:1, and it is
   easy to lighten a brand red into something that fails AA for body text
   without noticing on a bright monitor. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    let doc = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        doc = await store.getDoc('settings');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Theme & Branding' }],
            title: 'Theme',
            accent: '& Branding',
            sub: 'Colours and fonts for the public website. The admin panel keeps its own palette.',
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <form class="card" id="themeForm" novalidate>
                    ${F.section({
                        title: 'Brand colours', icon: 'fa-palette',
                        fields: [
                            F.colour({ name: 'brandPrimary', label: 'Primary', value: doc.theme.brandPrimary }),
                            F.colour({ name: 'brandAccent', label: 'Accent', value: doc.theme.brandAccent }),
                            F.colour({ name: 'brandDeep', label: 'Deep shade', value: doc.theme.brandDeep,
                                hint: 'Used for gradients and shadow tints.' }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Typography', icon: 'fa-font',
                        fields: [
                            F.select({ name: 'headingFont', label: 'Heading font', options: ['Sora', 'Inter', 'Poppins', 'DM Sans'] }),
                            F.select({ name: 'bodyFont', label: 'Body font', options: ['Inter', 'Sora', 'Source Sans 3', 'System default'] }),
                        ],
                    })}

                    ${F.divider()}

                    ${F.section({
                        title: 'Appearance', icon: 'fa-circle-half-stroke',
                        fields: [
                            F.select({
                                name: 'defaultTheme', label: 'Default theme for visitors',
                                options: [
                                    { value: 'system', label: 'Follow the visitor’s device' },
                                    { value: 'light', label: 'Always light' },
                                    { value: 'dark', label: 'Always dark' },
                                ],
                            }),
                            F.select({ name: 'bannerStyle', label: 'Page banner style',
                                options: ['Photo with overlay', 'Solid colour', 'Gradient'] }),
                        ],
                    })}

                    ${F.bar({ singleSave: true, saveLabel: 'Save branding' })}
                </form>

                <aside class="split__rail">
                    <article class="card">
                        <div class="card__head"><h3>Swatches</h3></div>
                        <div id="swatches" class="col gap-2"></div>
                    </article>

                    <article class="card">
                        <div class="card__head"><h3>Contrast</h3></div>
                        <div id="contrast" class="col gap-2"></div>
                        <p class="text-xs muted mt-4">WCAG AA needs 4.5:1 for body text and 3:1 for large text and interface
                        elements. A colour that fails is still usable for a filled button with white text — it is body copy
                        and small labels that break.</p>
                    </article>

                    <article class="card card--quiet">
                        <div class="card__head"><h3>Preview</h3></div>
                        <div id="brandPreview"></div>
                    </article>
                </aside>
            </div>`;

        ctrl = formLib.create({
            el: '#themeForm', bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: async (data) => {
                doc.theme = Object.assign({}, doc.theme, data);
                await store.setDoc('settings', doc);
                toast.success('Branding saved', {
                    body: 'In Phase 3 this rewrites the CSS custom properties in the generated pages.',
                });
            },
        });

        ctrl.bind(doc.theme);
        F.wirePreviews(document);

        const paint = () => paintPreview(ctrl.collect());
        document.getElementById('themeForm').addEventListener('input', U.debounce(paint, 200));
        document.getElementById('themeForm').addEventListener('change', paint);
        paint();
    }

    /* --- contrast maths (WCAG relative luminance) --- */

    function parseHex(hex) {
        const m = /^#?([a-f\d]{6})$/i.exec(String(hex || '').trim());
        if (!m) return null;
        const n = parseInt(m[1], 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    function luminance(rgb) {
        const [r, g, b] = rgb.map((v) => {
            const c = v / 255;
            return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    function ratio(a, b) {
        const la = luminance(a);
        const lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    function paintPreview(d) {
        const entries = [
            ['Primary', d.brandPrimary],
            ['Accent', d.brandAccent],
            ['Deep', d.brandDeep],
        ];

        document.getElementById('swatches').innerHTML = entries.map(([label, hex]) => `
            <div class="row">
                <span style="width:34px;height:34px;border-radius:var(--radius-xs);background:${U.esc(hex)};border:1px solid var(--hairline)"></span>
                <span class="grow text-sm"><b>${U.esc(label)}</b><br><code class="text-xs">${U.esc(hex)}</code></span>
            </div>`).join('');

        document.getElementById('contrast').innerHTML = entries.map(([label, hex]) => {
            const rgb = parseHex(hex);
            if (!rgb) return `<div class="text-sm"><b>${U.esc(label)}</b> — <span class="tag warn">not a hex colour</span></div>`;
            const onWhite = ratio(rgb, [255, 255, 255]);
            const tone = onWhite >= 4.5 ? 'ok' : (onWhite >= 3 ? 'warn' : 'bad');
            const verdict = onWhite >= 4.5 ? 'Passes AA' : (onWhite >= 3 ? 'Large text only' : 'Fails AA');
            return `<div class="row text-sm">
                <span class="grow">${U.esc(label)} on white</span>
                <b>${onWhite.toFixed(2)}:1</b>
                <span class="tag ${tone}">${verdict}</span>
            </div>`;
        }).join('');

        document.getElementById('brandPreview').innerHTML = `
            <div style="border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--hairline)">
                <div style="background:linear-gradient(135deg, ${U.esc(d.brandPrimary)}, ${U.esc(d.brandDeep)});color:#fff;padding:var(--s5)">
                    <div style="font-family:'${U.esc(d.headingFont)}',var(--font-head);font-size:1.2rem;font-weight:600">Compassionate care</div>
                    <div style="font-family:'${U.esc(d.bodyFont)}',var(--font-body);font-size:var(--fs-sm);opacity:.9">Every single day</div>
                </div>
                <div style="padding:var(--s4);background:var(--surface)">
                    <span style="display:inline-block;background:${U.esc(d.brandPrimary)};color:#fff;padding:8px 16px;border-radius:var(--radius-sm);font-size:var(--fs-sm);font-weight:600">Book an appointment</span>
                    <span style="display:inline-block;color:${U.esc(d.brandAccent)};padding:8px 12px;font-size:var(--fs-sm);font-weight:600">Learn more</span>
                </div>
            </div>`;
    }
}());
