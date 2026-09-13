(function () {
    'use strict';

    const { util: U, layout, toast, store, media, fields: F } = window.HAZRA;
    const API = window.HAZRA.api;

    let settingsDoc = {};

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Settings' }],
            title: 'Website & Email Settings',
            sub: 'Configure site identity, contact channels, social presence, SMTP mailer, and lead notifications.',
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
                    <button type="button" role="tab" data-tab="email" aria-selected="false">
                        <i class="fa-solid fa-envelope"></i> SMTP & Notifications
                    </button>
                </nav>

                <form id="settingsForm" novalidate>
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
                            <div class="form-grid">
                                <div class="field">
                                    <label>Header Logo</label>
                                    <div data-media="general[logo]"></div>
                                </div>
                                <div class="field">
                                    <label>Favicon / App Mark</label>
                                    <div data-media="general[favicon]"></div>
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
                                        <input class="input" type="text" id="address" name="contact[address]" placeholder="Bardhaman, West Bengal, India">
                                    </div>
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

                    <!-- Email & SMTP Tab -->
                    <div class="tab-panel" id="email" role="tabpanel" hidden>
                        <div class="settings-v2-card mb-4">
                            <div class="settings-v2-card__head flex-between align-center">
                                <div class="row gap-3 items-center">
                                    <div class="settings-v2-icon"><i class="fa-solid fa-paper-plane"></i></div>
                                    <div>
                                        <h3>SMTP Mailer Gateway</h3>
                                        <p>Server credentials for sending automated lead confirmations and notifications.</p>
                                    </div>
                                </div>
                                <button type="button" class="btn btn--ghost btn--sm" id="testMailBtn">
                                    <i class="fa-solid fa-paper-plane"></i> Send Test Email
                                </button>
                            </div>

                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="smtp_host">SMTP Host Server</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-server"></i>
                                        <input class="input" type="text" id="smtp_host" name="email[smtp_host]" placeholder="smtp.gmail.com">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="smtp_port">Port</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-network-wired"></i>
                                        <input class="input" type="text" id="smtp_port" name="email[smtp_port]" placeholder="587">
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="smtp_encryption">Encryption Security</label>
                                    <select class="input" id="smtp_encryption" name="email[smtp_encryption]">
                                        <option value="tls">TLS (STARTTLS - Port 587)</option>
                                        <option value="ssl">SSL (Port 465)</option>
                                        <option value="none">None (Plaintext)</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="support_email">Team Inbox Address</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-inbox"></i>
                                        <input class="input" type="email" id="support_email" name="email[support_email]" placeholder="sales@hazraev.com">
                                    </div>
                                    <small class="muted">Receives copy of incoming test drive & dealer applications.</small>
                                </div>
                            </div>

                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="smtp_username">SMTP Username / Account</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-user"></i>
                                        <input class="input" type="text" id="smtp_username" name="email[smtp_username]" placeholder="user@domain.com">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="smtp_password">SMTP Password / App Secret</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-key"></i>
                                        <input class="input" type="password" id="smtp_password" name="email[smtp_password]" placeholder="••••••••">
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label for="smtp_from_email">Sender "From" Address</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-at"></i>
                                        <input class="input" type="email" id="smtp_from_email" name="email[smtp_from_email]" placeholder="info@hazraev.com">
                                    </div>
                                </div>
                                <div class="field">
                                    <label for="smtp_from_name">Sender Display Name</label>
                                    <div class="input-icon-group">
                                        <i class="fa-solid fa-id-badge"></i>
                                        <input class="input" type="text" id="smtp_from_name" name="email[smtp_from_name]" placeholder="Hazra EV">
                                    </div>
                                </div>
                            </div>
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

        U.wireTabs(document.getElementById('view'));
        media.wire(document.getElementById('view'));
        await loadSettings();

        document.getElementById('settingsForm').addEventListener('submit', handleSave);
        document.getElementById('testMailBtn').addEventListener('click', handleTestMail);
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
                API.post('api/v1/website/settings', flatPayload).catch(() => {}),
            ]);

            toast.success('Settings saved successfully!');
        } catch (err) {
            toast.error('Failed to save settings: ' + (err.message || 'Server error'));
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-busy');
        }
    }

    async function handleTestMail() {
        const testEmail = prompt('Enter recipient email address for test mail:', 'sales@hazraev.com');
        if (!testEmail || !testEmail.trim()) return;

        try {
            toast.info('Sending test email...');
            const res = await API.post('api/v1/admin/mail/test', { email: testEmail.trim() });
            toast.success(res.data?.message || 'Test email sent successfully!');
        } catch (err) {
            toast.error('Failed to send test email: ' + (err.message || 'Check SMTP configuration'));
        }
    }
}());
