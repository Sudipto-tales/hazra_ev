(function () {
    'use strict';

    const { util: U, layout, toast, store, media, fields: F } = window.HAZRA;
    const API = window.HAZRA.api;

    let settingsDoc = {};

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Settings' }],
            title: 'Website & Messaging Settings',
            sub: 'Configure site identity, contact channels, social presence, email, WhatsApp, and lead notifications.',
        });

        document.getElementById('view').innerHTML = `
            <div class="settings-v2-container">
                <!-- Navigation Tabs -->
                <nav class="settings-v2-nav" role="tablist" aria-label="Settings sections">
                    <button type="button" role="tab" data-tab="general" aria-selected="true">
                        <i class="fa-solid fa-sliders"></i> General & Branding
                    </button>
                    <button type="button" role="tab" data-tab="contact" aria-selected="false">
                        <i class="fa-solid fa-address-book"></i> Showroom & Contact
                    </button>
                    <button type="button" role="tab" data-tab="social" aria-selected="false">
                        <i class="fa-solid fa-share-nodes"></i> Social Presence
                    </button>
                    <button type="button" role="tab" data-tab="messaging" aria-selected="false">
                        <i class="fa-solid fa-tower-broadcast"></i> Messaging &amp; Webhooks
                    </button>
                    <button type="button" role="tab" data-tab="email" aria-selected="false">
                        <i class="fa-solid fa-bell"></i> Notifications
                    </button>
                </nav>

                <form id="settingsForm" novalidate>
                    <div class="tab-panel" id="messaging" role="tabpanel" hidden>
                        <div class="settings-v2-card mb-4">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon"><i class="fa-brands fa-whatsapp"></i></div>
                                <div>
                                    <h3>WhatsApp channel</h3>
                                    <p>Switches the WhatsApp channel on or off wherever it appears in a delivery order below.</p>
                                </div>
                            </div>
                            <div class="form-grid mb-4">
                                <div class="st-toggle-card">
                                    <div class="st-toggle-info"><strong id="waEnabledLabel">Enable WhatsApp</strong><span>Off means every WhatsApp step is skipped, whatever the order says.</span></div>
                                    <label class="st-switch"><input type="checkbox" id="wa_enabled" name="general[wa_enabled]" value="1" aria-labelledby="waEnabledLabel"><span class="st-slider"></span></label>
                                </div>
                                <div class="st-toggle-card">
                                    <div class="st-toggle-info"><strong id="waAutoLabel">Automatic enquiry updates</strong><span>Send enquiry messages on WhatsApp to customers who opted in.</span></div>
                                    <label class="st-switch"><input type="checkbox" id="wa_auto_leads" name="general[wa_auto_leads]" value="1" aria-labelledby="waAutoLabel"><span class="st-slider"></span></label>
                                </div>
                            </div>
                            <p class="text-sm muted">Credentials, approved template names, webhook setup and the per-event delivery
                               order are below. A customer must have opted in before any enquiry or warranty message is sent on WhatsApp.</p>
                        </div>
                        ${window.HAZRA.messagingTab.html()}
                    </div>

                    <!-- General & Branding Tab -->
                    <div class="tab-panel" id="general" role="tabpanel">
                        <div class="settings-v2-card">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon"><i class="fa-solid fa-globe"></i></div>
                                <div>
                                    <h3>Site Identity & Branding</h3>
                                    <p>Configure default brand name, header tagline, and public assets.</p>
                                </div>
                            </div>
                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="site_name">Site Title</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-building"></i>
                                        <input class="input" type="text" id="site_name" name="general[site_name]" placeholder="Hazra EV">
                                    </div>
                                    <small class="muted">Displayed in browser title bar and email footers.</small>
                                </div>
                                <div class="field">
                                    <label for="tagline">Tagline</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-quote-left"></i>
                                        <input class="input" type="text" id="tagline" name="general[tagline]" placeholder="Powering the Future of Mobility">
                                    </div>
                                    <small class="muted">Main header subtitle on homepage.</small>
                                </div>
                            </div>
                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label>Header Logo</label>
                                    <div data-media="general[logo]"></div>
                                </div>
                                <div class="field">
                                    <label>Favicon / App Mark</label>
                                    <div data-media="general[favicon]"></div>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="google_site_verification">Google Search Console Verification Code</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-shield-halved"></i>
                                        <input class="input" type="text" id="google_site_verification" name="general[google_site_verification]" placeholder="Q4FWGLTexIVX2B-2jie8q39ECbwaq2fqtEXgayv8XW8">
                                    </div>
                                    <small class="muted">Code from &lt;meta name="google-site-verification" content="..."&gt;</small>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Appearance Card -->
                        <div class="settings-v2-card mt-4">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon" style="background:color-mix(in srgb, var(--brand-violet, #7b2ff7) 14%, transparent);color:var(--brand-violet, #7b2ff7)"><i class="fa-solid fa-image"></i></div>
                                <div>
                                    <h3>Footer Styling & Background</h3>
                                    <p>Control the appearance of the graphic background illustration in the public website footer.</p>
                                </div>
                            </div>
                            <div class="st-toggle-card">
                                <div class="st-toggle-info">
                                    <strong>Show Footer Background Graphic</strong>
                                    <span>When enabled (default), displays the skyline graphic illustration. When disabled, renders a clean solid white background in Light Mode and solid dark in Dark Mode.</span>
                                </div>
                                <div class="row gap-4 align-center">
                                    <label class="row gap-2 align-center text-xs font-semibold">
                                        <span class="st-switch">
                                            <input type="checkbox" id="show_footer_bg" name="general[show_footer_bg]" value="1">
                                            <span class="st-slider"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Tab -->
                    <div class="tab-panel" id="contact" role="tabpanel" hidden>
                        <div class="settings-v2-card">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon" style="background:color-mix(in srgb, var(--hazra-blue) 12%, transparent);color:var(--hazra-blue)"><i class="fa-solid fa-headset"></i></div>
                                <div>
                                    <h3>Showroom & Support Channels</h3>
                                    <p>Public contact details shown on the website footer, contact page, and dealer locator.</p>
                                </div>
                            </div>
                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="phone">Primary Phone</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-phone"></i>
                                        <input class="input" type="text" id="phone" name="contact[phone]" placeholder="+91 98290 16542">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="email_contact">Public Inbox Email</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-envelope"></i>
                                        <input class="input" type="email" id="email_contact" name="contact[email]" placeholder="info@hazraev.com">
                                    </div>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="whatsapp">WhatsApp Support Number</label>
                                    <div class="input-icon-group">
                                        <i class="fa-brands fa-whatsapp" style="color:var(--good)"></i>
                                        <input class="input" type="text" id="whatsapp" name="contact[whatsapp]" placeholder="+91 98290 16542">
                                    </div>
                                    <small class="muted">Used for direct WhatsApp floating chat button.</small>
                                </div>
                                <div class="field">
                                    <label for="address">Showroom & Corporate Address</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <input class="input" type="text" id="address" name="contact[address]" placeholder="Ghordourchati More, Below LIC Division Office, Bardhaman, West Bengal 713103">
                                    </div>
                                </div>
                            </div>
                            <div class="form-grid mt-4">
                                <div class="field">
                                    <label for="map_url">Google Maps URL / Direct Link</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                        <input class="input" type="url" id="map_url" name="contact[map_url]" placeholder="https://maps.app.goo.gl/c5sXVm5k3UXsruF26">
                                    </div>
                                    <small class="muted">Short link or URL used for map buttons and social icons.</small>
                                </div>
                                <div class="field">
                                    <label for="map_embed">Google Maps Embed URL</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-map"></i>
                                        <input class="input" type="text" id="map_embed" name="contact[map_embed]" placeholder="https://www.google.com/maps/embed?pb=...">
                                    </div>
                                    <small class="muted">Embed iframe source URL for the interactive contact map.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Tab -->
                    <div class="tab-panel" id="social" role="tabpanel" hidden>
                        <div class="settings-v2-card">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon" style="background:color-mix(in srgb, var(--hazra-orange) 12%, transparent);color:var(--hazra-orange)"><i class="fa-solid fa-share-nodes"></i></div>
                                <div>
                                    <h3>Social Media Profiles</h3>
                                    <p>Official handles linked in public footers and campaign ribbons.</p>
                                </div>
                            </div>
                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="facebook">Facebook URL</label>
                                    <div class="input-icon-group">
                                        <i class="fa-brands fa-facebook" style="color:#1877f2"></i>
                                        <input class="input" type="url" id="facebook" name="social[facebook]" placeholder="https://facebook.com/hazraev">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="instagram">Instagram Handle / URL</label>
                                    <div class="input-icon-group">
                                        <i class="fa-brands fa-instagram" style="color:#e4405f"></i>
                                        <input class="input" type="url" id="instagram" name="social[instagram]" placeholder="https://instagram.com/hazraev">
                                    </div>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="youtube">YouTube Channel</label>
                                    <div class="input-icon-group">
                                        <i class="fa-brands fa-youtube" style="color:#ff0000"></i>
                                        <input class="input" type="url" id="youtube" name="social[youtube]" placeholder="https://youtube.com/@hazraev">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="linkedin">LinkedIn Page</label>
                                    <div class="input-icon-group">
                                        <i class="fa-brands fa-linkedin" style="color:#0a66c2"></i>
                                        <input class="input" type="url" id="linkedin" name="social[linkedin]" placeholder="https://linkedin.com/company/hazraev">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications Tab -->
                    <div class="tab-panel" id="email" role="tabpanel" hidden>
                        <div class="settings-v2-card mb-4">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon"><i class="fa-solid fa-inbox"></i></div>
                                <div>
                                    <h3>Team inbox</h3>
                                    <p>Where staff copies of customer submissions are sent.</p>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="support_email">Team Inbox Address</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-inbox"></i>
                                        <input class="input" type="email" id="support_email" name="email[support_email]" placeholder="sales@hazraev.com">
                                    </div>
                                    <small class="muted">Receives a copy of incoming test drive &amp; dealer applications.</small>
                                </div>
                            </div>
                            <p class="text-sm muted mt-4">
                                <i class="fa-solid fa-circle-info"></i>
                                SMTP host, credentials and the sender address moved to
                                <button type="button" class="msg-clear" data-goto-tab="messaging">Messaging &amp; Webhooks</button>,
                                where the password is stored encrypted rather than in plain text. Test sending is there too, per provider.
                            </p>
                        </div>

                        <!-- Lead Notification Rules -->
                        <div class="settings-v2-card">
                            <div class="settings-v2-card__head">
                                <div class="settings-v2-icon" style="background:color-mix(in srgb, var(--good) 12%, transparent);color:var(--good)"><i class="fa-solid fa-bell"></i></div>
                                <div>
                                    <h3>Lead Notification Dispatch Rules</h3>
                                    <p>Toggle automated email dispatches for customer submissions and status approvals.</p>
                                </div>
                            </div>

                            <div class="col gap-3">
                                <!-- New Lead Submission -->
                                <div class="st-toggle-card">
                                    <div class="st-toggle-info">
                                        <strong>New Lead Submission</strong>
                                        <span>Triggered when a customer requests a test drive, contact, or dealership form.</span>
                                    </div>
                                    <div class="row gap-4 align-center">
                                        <label class="row gap-2 align-center text-xs font-semibold">
                                            <span>Team</span>
                                            <span class="st-switch">
                                                <input type="checkbox" id="notify_team_new_lead" name="email[notify_team_new_lead]" value="1">
                                                <span class="st-slider"></span>
                                            </span>
                                        </label>
                                        <label class="row gap-2 align-center text-xs font-semibold">
                                            <span>Customer</span>
                                            <span class="st-switch">
                                                <input type="checkbox" id="notify_customer_new_lead" name="email[notify_customer_new_lead]" value="1">
                                                <span class="st-slider"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- On Lead Approval -->
                                <div class="st-toggle-card">
                                    <div class="st-toggle-info">
                                        <strong>On Lead Approval</strong>
                                        <span>Triggered when an admin schedules a test drive or approves a dealer lead.</span>
                                    </div>
                                    <div class="row gap-4 align-center">
                                        <label class="row gap-2 align-center text-xs font-semibold">
                                            <span>Team</span>
                                            <span class="st-switch">
                                                <input type="checkbox" id="notify_team_on_approve" name="email[notify_team_on_approve]" value="1">
                                                <span class="st-slider"></span>
                                            </span>
                                        </label>
                                        <label class="row gap-2 align-center text-xs font-semibold">
                                            <span>Customer</span>
                                            <span class="st-switch">
                                                <input type="checkbox" id="notify_customer_on_approve" name="email[notify_customer_on_approve]" value="1">
                                                <span class="st-slider"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- On Lead Rejection -->
                                <div class="st-toggle-card">
                                    <div class="st-toggle-info">
                                        <strong>On Lead Rejection</strong>
                                        <span>Triggered when a lead is marked closed or rejected.</span>
                                    </div>
                                    <div class="row gap-4 align-center">
                                        <label class="row gap-2 align-center text-xs font-semibold">
                                            <span>Team</span>
                                            <span class="st-switch">
                                                <input type="checkbox" id="notify_team_on_reject" name="email[notify_team_on_reject]" value="1">
                                                <span class="st-slider"></span>
                                            </span>
                                        </label>
                                        <label class="row gap-2 align-center text-xs font-semibold">
                                            <span>Customer</span>
                                            <span class="st-switch">
                                                <input type="checkbox" id="notify_customer_on_reject" name="email[notify_customer_on_reject]" value="1">
                                                <span class="st-slider"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sticky Bottom Action Bar -->
                    <div class="settings-v2-bar">
                        <span class="text-sm mid"><i class="fa-solid fa-circle-info"></i> Changes apply immediately to public website & API</span>
                        <button type="submit" class="btn btn--primary" id="saveBtn">
                            <i class="fa-solid fa-floppy-disk"></i> Save All Settings
                        </button>
                    </div>
                </form>
            </div>
        `;

        const tabs = U.wireTabs(document.getElementById('view'));
        media.wire(document.getElementById('view'));
        await loadSettings();

        document.getElementById('settingsForm').addEventListener('submit', handleSave);

        // The messaging panel loads its own state: providers, webhook wiring
        // and the delivery order all come from the server, so nothing about a
        // provider is duplicated in this file.
        await window.HAZRA.messagingTab.mount();

        document.querySelectorAll('[data-goto-tab]').forEach((button) => {
            button.addEventListener('click', () => tabs && tabs.show(button.dataset.gotoTab));
        });
    }

    async function loadSettings() {
        try {
            settingsDoc = await store.getDoc('settings');
        } catch (err) {
            console.warn('[settings] could not load settings doc', err);
            settingsDoc = {};
        }

        for (const [group, items] of Object.entries(settingsDoc)) {
            if (typeof items !== 'object' || !items) continue;
            for (const [key, val] of Object.entries(items)) {
                const el = document.querySelector(`[name="${group}[${key}]"]`);
                if (el) {
                    if (el.type === 'checkbox') {
                        el.checked = String(val) === '1' || val === true;
                    } else {
                        el.value = val ?? '';
                    }
                }
            }
        }

        // show_footer_bg is ON by default if not set in DB
        const footerBgEl = document.getElementById('show_footer_bg');
        if (footerBgEl && (settingsDoc?.general?.show_footer_bg === undefined || settingsDoc?.general?.show_footer_bg === null || settingsDoc?.general?.show_footer_bg === '')) {
            footerBgEl.checked = true;
        }

        media.paintAll(document.getElementById('view'), settingsDoc);
    }

    async function handleSave(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.classList.add('is-busy');

        try {
            const formData = new FormData(e.target);
            const payload = { general: {}, contact: {}, social: {}, email: {} };

            // Default checkboxes to '0' so unchecking saves '0'
            payload.general.show_footer_bg = '0';
            payload.general.wa_enabled = '0';
            payload.general.wa_auto_leads = '0';
            const checkboxes = ['notify_team_new_lead', 'notify_customer_new_lead', 'notify_team_on_approve', 'notify_customer_on_approve', 'notify_team_on_reject', 'notify_customer_on_reject'];
            checkboxes.forEach(cb => { payload.email[cb] = '0'; });

            formData.forEach((val, key) => {
                const match = key.match(/^(\w+)\[(\w+)\]$/);
                if (match) {
                    const [, group, field] = match;
                    payload[group] = payload[group] || {};
                    payload[group][field] = val;
                }
            });

            const flatPayload = {};
            for (const groupItems of Object.values(payload)) {
                Object.assign(flatPayload, groupItems);
            }

            await Promise.all([
                store.setDoc('settings', payload),
                API.post('api/v1/website/settings', flatPayload),
            ]);

            // Messaging saves separately: its credentials go to an endpoint
            // that encrypts them, and its chains are rows, not settings. It is
            // saved after the rest so a validation error there cannot leave
            // half the page unsaved without the toast saying so.
            await window.HAZRA.messagingTab.save();

            toast.success('Settings saved successfully!');
        } catch (err) {
            toast.error('Failed to save settings: ' + (err.message || 'Server error'));
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-busy');
        }
    }

    /* Test sending now lives on the Messaging panel, per provider, so a test
       exercises the same credentials and chain the real sends use. The
       POST /admin/mail/test endpoint it used is still served and unchanged. */
}());
