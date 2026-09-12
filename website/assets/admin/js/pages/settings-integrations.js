/* Integrations — analytics, mail, captcha, chat.
   Secrets are masked with a reveal button rather than shown in plain text,
   because these screens get shared over a screen recording more often than
   anyone admits. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    let doc = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        doc = await store.getDoc('settings');

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Integrations' }],
            title: 'Integrations',
            sub: 'Analytics, outgoing mail, spam protection and live chat.',
        });

        document.getElementById('view').innerHTML = `
            <form class="card" id="intForm" novalidate>
                ${F.section({
                    title: 'Analytics', icon: 'fa-chart-line',
                    fields: [
                        F.text({ name: 'ga4Id', label: 'Google Analytics 4 ID', placeholder: 'G-XXXXXXXXXX' }),
                        F.text({ name: 'gtmId', label: 'Google Tag Manager ID', placeholder: 'GTM-XXXXXXX' }),
                        F.text({ name: 'searchConsoleTag', label: 'Search Console verification', wide: true }),
                        F.text({ name: 'facebookPixel', label: 'Meta Pixel ID' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Outgoing mail', icon: 'fa-envelope',
                    sub: 'Used for enquiry notifications, appointment confirmations and password resets.',
                    fields: [
                        F.text({ name: 'smtpHost', label: 'SMTP host', placeholder: 'smtp.example.org' }),
                        F.number({ name: 'smtpPort', label: 'Port', min: 1, placeholder: '587' }),
                        F.text({ name: 'smtpUser', label: 'Username' }),
                        F.text({ name: 'smtpPass', label: 'Password', type: 'password',
                            hint: '<button type="button" class="btn btn--link text-xs" data-reveal="smtpPass">Reveal</button>' }),
                        F.select({ name: 'smtpSecure', label: 'Encryption', options: ['tls', 'ssl', 'none'] }),
                        F.text({ name: 'smtpFromName', label: 'From name' }),
                        F.email({ name: 'smtpFromEmail', label: 'From address' }),
                        F.repeater({
                            name: 'notifyEnquiryTo', label: 'Notify these addresses of a new enquiry',
                            cols: 1, addLabel: 'Add an address',
                            fields: [{ key: 'email', placeholder: 'tmh.bdn@gmail.com' }],
                        }),
                    ],
                })}

                <div class="row mt-2">
                    <button type="button" class="btn btn--ghost btn--sm" id="testSmtp">
                        <i class="fa-solid fa-paper-plane"></i> Send a test email</button>
                    <span class="text-xs muted">Sends to the first notify address.</span>
                </div>

                ${F.divider()}

                ${F.section({
                    title: 'Spam protection', icon: 'fa-shield-halved',
                    sub: 'The contact and appointment forms are open to the internet. Without a captcha they will be filled with junk within a week.',
                    fields: [
                        F.text({ name: 'recaptchaSiteKey', label: 'reCAPTCHA site key' }),
                        F.text({ name: 'recaptchaSecret', label: 'reCAPTCHA secret', type: 'password',
                            hint: '<button type="button" class="btn btn--link text-xs" data-reveal="recaptchaSecret">Reveal</button>' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Google reviews', icon: 'fa-star',
                    sub: 'The rating tile on the home page and the About page. With a key and a place ID it shows the live Google figures, refreshed twice a day; without them it shows the two fallback numbers below.',
                    fields: [
                        F.text({ name: 'googlePlacesApiKey', label: 'Places API key', type: 'password',
                            hint: '<button type="button" class="btn btn--link text-xs" data-reveal="googlePlacesApiKey">Reveal</button>' }),
                        F.text({ name: 'googlePlaceId', label: 'Place ID', placeholder: 'ChIJ…',
                            hint: 'From the hospital\'s Google Business Profile, or Google\'s Place ID Finder.' }),
                        F.text({ name: 'googleRating', label: 'Fallback rating', placeholder: '4.4',
                            hint: 'Shown until the first live reading arrives, and if Google is unreachable.' }),
                        F.number({ name: 'googleReviewCount', label: 'Fallback review count', min: 0, placeholder: '0' }),
                        F.text({ name: 'googleReviewsUrl', label: 'Reviews link', wide: true,
                            hint: 'Optional. Leave empty and the link is built from the Place ID.' }),
                    ],
                })}

                ${F.divider()}

                ${F.section({
                    title: 'Live chat', icon: 'fa-comments',
                    fields: [
                        F.select({ name: 'liveChatProvider', label: 'Provider', options: ['None', 'Tawk.to', 'Crisp', 'WhatsApp Business', 'Custom'] }),
                        F.toggle({ name: 'liveChatEnabled', label: 'Show the chat widget on the website', wide: false }),
                        F.text({ name: 'liveChatFrom', type: 'time', label: 'Available from' }),
                        F.text({ name: 'liveChatTo', type: 'time', label: 'Available until' }),
                        F.textarea({ name: 'liveChatEmbed', label: 'Embed code', rows: 4,
                            hint: 'Pasted verbatim before <code>&lt;/body&gt;</code>. Only paste code you trust.' }),
                    ],
                })}

                ${F.bar({ singleSave: true, saveLabel: 'Save integrations' })}
            </form>`;

        ctrl = formLib.create({
            el: '#intForm', bar: '#formBar',
            onCancel: () => location.reload(),
            onSave: async (data) => {
                doc.integrations = Object.assign({}, doc.integrations, data);
                await store.setDoc('settings', doc);
                toast.success('Integrations saved');
            },
        });

        ctrl.bind(doc.integrations);

        document.querySelectorAll('[data-reveal]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.querySelector(`[name="${btn.dataset.reveal}"]`);
                const showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                btn.textContent = showing ? 'Reveal' : 'Hide';
            });
        });

        document.getElementById('testSmtp').addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const data = ctrl.collect();
            const to = (data.notifyEnquiryTo || [])[0];
            if (!to || !to.email) {
                toast.error('Add a notification address first');
                return;
            }
            btn.classList.add('is-busy');
            await new Promise((r) => setTimeout(r, 900));
            btn.classList.remove('is-busy');
            /* Phase 1 has no mail server; be honest about that rather than
               faking a success the user will trust. */
            toast.warning('No mail server yet', {
                body: `In Phase 2 this posts to /api/settings/integrations/test-smtp and mails ${to.email}.`,
            });
        });
    }
}());
