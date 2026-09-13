(function () {
    'use strict';

    const { util: U, layout, toast } = window.HAZRA;
    const API = window.HAZRA.api;

    window.HAZRA.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Settings' }],
            title: 'Website Settings',
            sub: 'Configure SMTP mailer, public contact details, and notification emails.',
        });

        document.getElementById('view').innerHTML = `
            <div style="max-width:760px;" class="card">
                <form id="settingsForm">
                    <h3 style="margin-bottom:16px;font-size:16px;font-weight:700;color:var(--text-main);">SMTP Configuration</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="field">
                            <label for="smtp_host">SMTP Host</label>
                            <input class="input" type="text" id="smtp_host" name="smtp_host" placeholder="smtp.gmail.com">
                        </div>
                        <div class="field">
                            <label for="smtp_port">Port</label>
                            <input class="input" type="text" id="smtp_port" name="smtp_port" placeholder="587">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="field">
                            <label for="smtp_from_email">Username / From Email</label>
                            <input class="input" type="email" id="smtp_from_email" name="smtp_from_email" placeholder="contact@hazraev.com">
                        </div>
                        <div class="field">
                            <label for="smtp_password">Password / App Key</label>
                            <input class="input" type="password" id="smtp_password" name="smtp_password" placeholder="••••••••">
                        </div>
                    </div>
                    <div class="field mb-lg">
                        <label for="recipient_emails">Notification Recipients (comma-separated)</label>
                        <input class="input" type="text" id="recipient_emails" name="recipient_emails" placeholder="sales@hazraev.com, admin@hazraev.com">
                    </div>

                    <hr style="border:0;border-top:1px solid var(--border-subtle);margin:24px 0;">

                    <h3 style="margin-bottom:16px;font-size:16px;font-weight:700;color:var(--text-main);">Public Contact Info</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input class="input" type="text" id="phone" name="phone" placeholder="+91 98000 00000">
                        </div>
                        <div class="field">
                            <label for="email">Public Email</label>
                            <input class="input" type="email" id="email" name="email" placeholder="contact@hazraev.com">
                        </div>
                    </div>
                    <div class="field mb-lg">
                        <label for="address">Showroom Address</label>
                        <input class="input" type="text" id="address" name="address" placeholder="Bardhaman, West Bengal, India">
                    </div>

                    <div style="display:flex;gap:12px;margin-top:24px;">
                        <button type="submit" class="btn btn--primary" id="saveBtn">
                            <i class="fa-solid fa-floppy-disk"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        `;

        await loadSettings();

        document.getElementById('settingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            try {
                const formData = new FormData(e.target);
                const body = {};
                formData.forEach((value, key) => {
                    body[key] = value;
                });
                await API.post('api/v1/website/settings', body);
                toast.success('Settings saved successfully!');
            } catch (err) {
                toast.error('Failed to save settings: ' + (err.message || 'Server error'));
            } finally {
                btn.disabled = false;
            }
        });
    }

    async function loadSettings() {
        try {
            const res = await API.get('api/v1/website/settings');
            const settings = res.data || {};
            for (const [key, value] of Object.entries(settings)) {
                const el = document.getElementById(key);
                if (el) {
                    el.value = value;
                }
            }
        } catch (err) {
            console.warn('[settings] could not load website settings', err);
        }
    }
}());
