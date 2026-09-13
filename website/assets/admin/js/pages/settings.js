(function () {
    'use strict';

    const { util: U, layout, toast, store } = window.HAZRA;
    const API = window.HAZRA.api;

    let settingsDoc = {};

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Settings' }],
            title: 'Website & Email Settings',
            sub: 'Configure site copy, contact details, SMTP mailer, and lead notification rules.',
        });

        document.getElementById('view').innerHTML = `
            <div class="card card--flush max-w-760">
                <div class="tabs" role="tablist" aria-label="Settings sections">
                    <button type="button" role="tab" data-tab="general" aria-selected="true">
                        <i class="fa-solid fa-sliders"></i> General</button>
                    <button type="button" role="tab" data-tab="contact" aria-selected="false">
                        <i class="fa-solid fa-address-book"></i> Contact Info</button>
                    <button type="button" role="tab" data-tab="social" aria-selected="false">
                        <i class="fa-solid fa-share-nodes"></i> Social Links</button>
                    <button type="button" role="tab" data-tab="email" aria-selected="false">
                        <i class="fa-solid fa-envelope"></i> Email & SMTP</button>
                </div>

                <div class="p-4">
                    <form id="settingsForm" novalidate>
                        <!-- General Tab -->
                        <div class="tab-panel" id="general" role="tabpanel">
                            <h3 class="mb-3 font-bold text-lg">General Settings</h3>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="site_name">Site Name</label>
                                    <input class="input" type="text" id="site_name" name="general[site_name]" placeholder="Hazra EV">
                                </div>
                                <div class="field">
                                    <label for="tagline">Tagline</label>
                                    <input class="input" type="text" id="tagline" name="general[tagline]" placeholder="Powering the Future of Mobility">
                                </div>
                            </div>
                        </div>

                        <!-- Contact Tab -->
                        <div class="tab-panel" id="contact" role="tabpanel" hidden>
                            <h3 class="mb-3 font-bold text-lg">Public Contact Details</h3>
                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="phone">Phone Number</label>
                                    <input class="input" type="text" id="phone" name="contact[phone]" placeholder="+91 98000 00000">
                                </div>
                                <div class="field">
                                    <label for="email_contact">Public Email</label>
                                    <input class="input" type="email" id="email_contact" name="contact[email]" placeholder="info@hazraev.com">
                                </div>
                            </div>
                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="whatsapp">WhatsApp Number</label>
                                    <input class="input" type="text" id="whatsapp" name="contact[whatsapp]" placeholder="+91 98000 00000">
                                </div>
                                <div class="field">
                                    <label for="address">Showroom Address</label>
                                    <input class="input" type="text" id="address" name="contact[address]" placeholder="Bardhaman, West Bengal, India">
                                </div>
                            </div>
                        </div>

                        <!-- Social Tab -->
                        <div class="tab-panel" id="social" role="tabpanel" hidden>
                            <h3 class="mb-3 font-bold text-lg">Social Media Profiles</h3>
                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="facebook"><i class="fa-brands fa-facebook"></i> Facebook URL</label>
                                    <input class="input" type="url" id="facebook" name="social[facebook]" placeholder="https://facebook.com/hazraev">
                                </div>
                                <div class="field">
                                    <label for="instagram"><i class="fa-brands fa-instagram"></i> Instagram URL</label>
                                    <input class="input" type="url" id="instagram" name="social[instagram]" placeholder="https://instagram.com/hazraev">
                                </div>
                            </div>
                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="youtube"><i class="fa-brands fa-youtube"></i> YouTube Channel</label>
                                    <input class="input" type="url" id="youtube" name="social[youtube]" placeholder="https://youtube.com/@hazraev">
                                </div>
                                <div class="field">
                                    <label for="linkedin"><i class="fa-brands fa-linkedin"></i> LinkedIn Page</label>
                                    <input class="input" type="url" id="linkedin" name="social[linkedin]" placeholder="https://linkedin.com/company/hazraev">
                                </div>
                            </div>
                        </div>

                        <!-- Email Tab -->
                        <div class="tab-panel" id="email" role="tabpanel" hidden>
                            <div class="flex-between align-center mb-3">
                                <h3 class="font-bold text-lg">SMTP Mailer Settings</h3>
                                <button type="button" class="btn btn--ghost btn--sm" id="testMailBtn">
                                    <i class="fa-solid fa-paper-plane"></i> Send Test Email
                                </button>
                            </div>

                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="smtp_host">SMTP Host</label>
                                    <input class="input" type="text" id="smtp_host" name="email[smtp_host]" placeholder="smtp.gmail.com">
                                </div>
                                <div class="field">
                                    <label for="smtp_port">Port</label>
                                    <input class="input" type="text" id="smtp_port" name="email[smtp_port]" placeholder="587">
                                </div>
                            </div>

                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="smtp_encryption">Encryption</label>
                                    <select class="input" id="smtp_encryption" name="email[smtp_encryption]">
                                        <option value="tls">TLS (STARTTLS - 587)</option>
                                        <option value="ssl">SSL (465)</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="support_email">Team / Support Inbox</label>
                                    <input class="input" type="email" id="support_email" name="email[support_email]" placeholder="sales@hazraev.com">
                                </div>
                            </div>

                            <div class="form-grid mb-3">
                                <div class="field">
                                    <label for="smtp_username">SMTP Username</label>
                                    <input class="input" type="text" id="smtp_username" name="email[smtp_username]" placeholder="bcasudipta@gmail.com">
                                </div>
                                <div class="field">
                                    <label for="smtp_password">SMTP Password / App Password</label>
                                    <input class="input" type="password" id="smtp_password" name="email[smtp_password]" placeholder="••••••••">
                                </div>
                            </div>

                            <div class="form-grid mb-4">
                                <div class="field">
                                    <label for="smtp_from_email">From Email</label>
                                    <input class="input" type="email" id="smtp_from_email" name="email[smtp_from_email]" placeholder="info@hazraev.com">
                                </div>
                                <div class="field">
                                    <label for="smtp_from_name">From Sender Name</label>
                                    <input class="input" type="text" id="smtp_from_name" name="email[smtp_from_name]" placeholder="Hazra EV">
                                </div>
                            </div>

                            <hr class="my-4" style="border:0;border-top:1px solid var(--hairline);">

                            <h3 class="mb-2 font-bold text-lg">Lead Email Notification Toggles</h3>
                            <p class="text-sm mid mb-3">Control automatically dispatched emails for incoming leads, approvals, and rejections.</p>

                            <div class="table-wrap mb-4">
                                <table class="table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Trigger Event</th>
                                            <th style="text-align:center;">Notify Team Inbox</th>
                                            <th style="text-align:center;">Notify Customer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>New Lead Submission</strong> (Test drive / Contact / Dealer)</td>
                                            <td style="text-align:center;">
                                                <input type="checkbox" id="notify_team_new_lead" name="email[notify_team_new_lead]" value="1">
                                            </td>
                                            <td style="text-align:center;">
                                                <input type="checkbox" id="notify_customer_new_lead" name="email[notify_customer_new_lead]" value="1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>On Lead Approval</strong> (Test-drive date scheduled)</td>
                                            <td style="text-align:center;">
                                                <input type="checkbox" id="notify_team_on_approve" name="email[notify_team_on_approve]" value="1">
                                            </td>
                                            <td style="text-align:center;">
                                                <input type="checkbox" id="notify_customer_on_approve" name="email[notify_customer_on_approve]" value="1">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>On Lead Rejection</strong></td>
                                            <td style="text-align:center;">
                                                <input type="checkbox" id="notify_team_on_reject" name="email[notify_team_on_reject]" value="1">
                                            </td>
                                            <td style="text-align:center;">
                                                <input type="checkbox" id="notify_customer_on_reject" name="email[notify_customer_on_reject]" value="1">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="btn btn--primary" id="saveBtn">
                                <i class="fa-solid fa-floppy-disk"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        U.wireTabs(document.getElementById('view'));
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

        // Populate fields
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
    }

    async function handleSave(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.classList.add('is-busy');

        try {
            const formData = new FormData(e.target);
            const payload = { general: {}, contact: {}, social: {}, email: {} };

            // Ensure unchecked checkboxes send '0'
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

            // Flatten for WebsiteController key-value update as well
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
        const testEmail = prompt('Enter recipient email address for test mail:', 'test@hazraev.com');
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
