/* Admin → Settings → Messaging & Webhooks.
   ---------------------------------------------------------------------------
   Loaded alongside pages/settings.js, which owns the tab shell and calls
   HAZRA.messagingTab.html() / .mount() for this panel. It lives in its own file
   for the same reason product-editor.js does: the panel is a screen's worth of
   UI, and settings.js stays readable without it.

   Everything on this panel is generated from GET /admin/messaging/config —
   the provider list, each provider's fields, its webhook instructions and the
   per-event chains. Nothing about a provider is hardcoded here, so adding one
   server-side makes it appear with no change to this file.

   No credential value ever arrives from the server. A secret field shows
   whether it is configured and a masked hint; the input is write-only, and an
   empty input on save means "leave the stored secret alone". */
(function () {
    'use strict';

    const { util: U, api, toast, modal, confirm: confirmDialog } = window.HAZRA;
    const ENDPOINT = 'api/v1/admin/messaging/config';

    const CHANNELS = [
        ['email', 'Email', 'fa-envelope'],
        ['whatsapp', 'WhatsApp', 'fa-whatsapp', true],
        ['sms', 'SMS', 'fa-comment-sms'],
    ];

    const SOURCE_LABELS = {
        database: ['Saved here', 'ok'],
        settings: ['From the old Settings tab', 'info'],
        environment: ['From the server .env', 'warn'],
        default: ['Default', 'off'],
        none: ['Not set', 'off'],
        unreadable: ['Stored but unreadable — APP_KEY changed', 'bad'],
    };

    let config = null;
    /* Working copies, so reordering is local until Save. */
    let chains = {};
    let modes = {};

    window.HAZRA.messagingTab = { html, mount, save, isDirty: () => config !== null };

    function html() {
        return `
            <div class="settings-v2-card mb-4" id="msgStatus"></div>
            <div id="msgChains"></div>
            <div id="msgProviders"></div>
            <div class="settings-v2-card" id="msgOptions"></div>`;
    }

    async function mount() {
        const host = document.getElementById('msgProviders');
        if (!host) return;
        host.innerHTML = '<p class="muted">Loading messaging configuration…</p>';

        try {
            config = (await api.get(ENDPOINT)).data;
        } catch (error) {
            host.innerHTML = `<p class="tag bad">Could not load messaging configuration: ${U.esc(error.message || 'server error')}</p>`;
            return;
        }

        config.events.forEach((event) => {
            chains[event.key] = event.steps.map((step) => ({ provider: step.provider, enabled: step.enabled }));
            modes[event.key] = event.mode;
        });

        render();
    }

    function render() {
        document.getElementById('msgStatus').innerHTML = statusHtml();
        document.getElementById('msgChains').innerHTML = chainsHtml();
        document.getElementById('msgProviders').innerHTML = providersHtml();
        document.getElementById('msgOptions').innerHTML = optionsHtml();
        wire();
    }

    /* ------------------------------------------------------------ status */

    function statusHtml() {
        const keyed = config.appKeyConfigured;
        const ready = config.providers.filter((p) => p.configured);

        return `
            <div class="settings-v2-card__head">
                <div class="settings-v2-icon"><i class="fa-solid fa-tower-broadcast"></i></div>
                <div>
                    <h3>Messaging &amp; Webhooks</h3>
                    <p>Credentials, webhook setup and the delivery order for every customer message.</p>
                </div>
            </div>
            ${keyed ? '' : `<p class="msg-alert bad"><i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>APP_KEY is not set on this server, so credentials cannot be saved.</strong>
                Run <code>php vayu key:generate</code>, add the key to the server's <code>.env</code> and to the
                <code>PROD_ENV_FILE</code> deployment secret, then reload this page. Keep a copy of the key:
                without it, everything saved here becomes unreadable. Template names and routes can still be saved.</span></p>`}
            <div class="msg-chips">
                <span class="tag ${ready.length ? 'ok' : 'warn'}">${ready.length} of ${config.providers.length} providers configured</span>
                <span class="tag off">Environment: ${U.esc(config.environment)}</span>
                <span class="tag ${config.appUrl ? 'off' : 'bad'}">Webhook base: ${U.esc(config.appUrl || 'APP_URL is not set')}</span>
            </div>`;
    }

    /* ------------------------------------------------------------ chains */

    function chainsHtml() {
        return `<div class="settings-v2-card mb-4">
            <div class="settings-v2-card__head">
                <div class="settings-v2-icon" style="background:color-mix(in srgb, var(--hazra-blue) 12%, transparent);color:var(--hazra-blue)"><i class="fa-solid fa-list-ol"></i></div>
                <div>
                    <h3>Delivery order</h3>
                    <p>Each message is tried in this order. A step with no credentials, no template or no address
                       for that customer is skipped, never failed — so you can leave a provider in the list before you set it up.</p>
                </div>
            </div>
            ${config.events.map(chainCard).join('')}
        </div>`;
    }

    function chainCard(event) {
        const steps = chains[event.key];
        const mode = modes[event.key];

        return `<div class="msg-chain" data-event="${U.esc(event.key)}">
            <div class="msg-chain__head">
                <strong>${U.esc(event.label)}</strong>
                <label class="msg-chain__mode">
                    <span>When a step succeeds</span>
                    <select class="input" data-mode="${U.esc(event.key)}">
                        <option value="first_success"${mode === 'first_success' ? ' selected' : ''}>Stop — the rest are a fallback</option>
                        <option value="all"${mode === 'all' ? ' selected' : ''}>Keep going — send on every channel</option>
                    </select>
                </label>
            </div>
            <ol class="msg-steps">
                ${steps.map((step, index) => stepRow(event, step, index, steps.length)).join('')}
            </ol>
        </div>`;
    }

    function stepRow(event, step, index, total) {
        const provider = config.providers.find((p) => p.key === step.provider) || { label: step.provider, channel: '', kind: '' };
        const live = event.steps.find((s) => s.provider === step.provider);
        /* The server's own reading of this step, which is the honest one: it
           knows the credentials. It is stale only for a reorder not yet saved. */
        const reason = live ? live.skipReason : '';
        const runs = live ? live.willRun : false;

        return `<li class="msg-step${step.enabled ? '' : ' is-off'}">
            <span class="msg-step__order">
                <button type="button" class="btn btn--ghost btn--sm" data-move="up" data-index="${index}" ${index === 0 ? 'disabled' : ''} aria-label="Move earlier"><i class="fa-solid fa-arrow-up"></i></button>
                <button type="button" class="btn btn--ghost btn--sm" data-move="down" data-index="${index}" ${index === total - 1 ? 'disabled' : ''} aria-label="Move later"><i class="fa-solid fa-arrow-down"></i></button>
            </span>
            <span class="msg-step__body">
                <strong>${U.esc(provider.label)}</strong>
                ${step.enabled
                    ? (runs ? '<span class="tag ok">Will send</span>' : `<span class="tag warn">Skipped — ${U.esc(reason)}</span>`)
                    : '<span class="tag off">Switched off</span>'}
            </span>
            <label class="st-switch msg-step__switch">
                <input type="checkbox" data-step="${U.esc(step.provider)}" ${step.enabled ? 'checked' : ''} aria-label="Use ${U.esc(provider.label)} for ${U.esc(event.label)}">
                <span class="st-slider"></span>
            </label>
        </li>`;
    }

    /* --------------------------------------------------------- providers */

    function providersHtml() {
        return CHANNELS.map(([channel, label, icon, brand]) => {
            const providers = config.providers.filter((p) => p.channel === channel);
            if (!providers.length) return '';

            return `<div class="settings-v2-card mb-4">
                <div class="settings-v2-card__head">
                    <div class="settings-v2-icon"><i class="${brand ? 'fa-brands' : 'fa-solid'} ${U.esc(icon)}"></i></div>
                    <div>
                        <h3>${U.esc(label)} providers</h3>
                        <p>${channel === 'sms'
                            ? 'SMS carries verification codes only — the providers here expose OTP endpoints, not a template per event.'
                            : channel === 'email'
                                ? 'Used for every event, and the cheapest channel you already own.'
                                : 'Send directly through Meta, or indirectly through a provider that holds the Meta integration for you.'}</p>
                    </div>
                </div>
                ${providers.map(providerCard).join('')}
            </div>`;
        }).join('');
    }

    function providerCard(provider) {
        const health = provider.health || {};

        return `<section class="msg-provider" data-provider="${U.esc(provider.key)}">
            <header class="msg-provider__head">
                <div>
                    <strong>${U.esc(provider.label)}</strong>
                    <span class="tag ${provider.kind === 'indirect' ? 'info' : 'off'}">${provider.kind === 'indirect' ? 'Indirect' : 'Direct'}</span>
                    <span class="tag ${provider.configured ? 'ok' : 'warn'}">${provider.configured ? 'Configured' : 'Incomplete'}</span>
                    ${provider.webhook ? `<span class="tag ${provider.webhookReady ? 'ok' : 'warn'}">${provider.webhookReady ? 'Webhook ready' : 'Webhook not set up'}</span>` : '<span class="tag off">No delivery reports</span>'}
                </div>
                <button type="button" class="btn btn--ghost btn--sm" data-test="${U.esc(provider.key)}" ${provider.configured ? '' : 'disabled'}>
                    <i class="fa-solid fa-paper-plane"></i> Send test
                </button>
            </header>

            <dl class="msg-health">
                <div><dt>Last send</dt><dd>${U.esc(health.lastSendAt ? U.fmtDateTime(health.lastSendAt) : 'never')}</dd></div>
                <div><dt>Last accepted</dt><dd>${U.esc(health.lastSuccessAt ? U.fmtDateTime(health.lastSuccessAt) : 'never')}</dd></div>
                <div><dt>Failures (24h)</dt><dd class="${health.failures24h ? 'tag bad' : ''}">${U.esc(String(health.failures24h || 0))}</dd></div>
                ${provider.webhook ? `<div><dt>Last callback</dt><dd>${U.esc(health.lastWebhookAt ? U.fmtDateTime(health.lastWebhookAt) : 'never')}</dd></div>` : ''}
            </dl>

            <div class="form-grid">${provider.fields.map((field) => fieldHtml(provider, field)).join('')}</div>
            ${provider.webhook ? webhookHtml(provider) : ''}
            ${provider.notes && provider.notes.length
                ? `<ul class="msg-notes">${provider.notes.map((note) => `<li>${U.esc(note)}</li>`).join('')}</ul>`
                : ''}
        </section>`;
    }

    function fieldHtml(provider, field) {
        const id = `f-${provider.key}-${field.key}`.replace(/[^\w-]/g, '-');
        const secret = field.type === 'secret' || field.type === 'generated';
        const [sourceLabel, sourceTone] = SOURCE_LABELS[field.source] || SOURCE_LABELS.none;
        const name = `${provider.key}|${field.key}`;

        let control;
        if (field.type === 'select') {
            control = `<select class="input" id="${id}" data-field="${U.esc(name)}">
                ${(field.options || []).map(([value, optionLabel]) => `<option value="${U.esc(value)}"${field.value === value ? ' selected' : ''}>${U.esc(optionLabel)}</option>`).join('')}
            </select>`;
        } else if (field.type === 'generated') {
            control = `<div class="msg-generated">
                <input class="input" id="${id}" type="text" value="${U.esc(field.configured ? field.hint : '')}" placeholder="Not generated yet" readonly>
                <button type="button" class="btn btn--ghost btn--sm" data-rotate="${U.esc(name)}">
                    <i class="fa-solid fa-rotate"></i> ${field.configured ? 'Regenerate' : 'Generate'}
                </button>
            </div>`;
        } else if (secret) {
            control = `<input class="input" id="${id}" type="password" autocomplete="new-password"
                data-field="${U.esc(name)}" placeholder="${U.esc(field.configured ? field.hint + ' — leave blank to keep' : 'Not set')}">`;
        } else {
            control = `<input class="input" id="${id}" type="${field.type === 'number' ? 'text' : 'text'}"
                inputmode="${field.type === 'number' ? 'numeric' : 'text'}"
                data-field="${U.esc(name)}" value="${U.esc(field.value || '')}" placeholder="${U.esc(field.placeholder || '')}">`;
        }

        return `<div class="field">
            <label for="${id}">${U.esc(field.label)}
                ${field.required ? '<span class="msg-req" title="Required">*</span>' : ''}
                ${field.requiredFor === 'webhook' ? '<span class="msg-req" title="Required for delivery reports">webhook</span>' : ''}
            </label>
            ${control}
            <small class="muted">
                <span class="tag ${sourceTone}">${U.esc(sourceLabel)}</span>
                ${field.envKey ? `<code>${U.esc(field.envKey)}</code>` : ''}
                ${U.esc(field.help || '')}
                ${field.configured && field.source === 'database' ? `<button type="button" class="msg-clear" data-clear="${U.esc(name)}">Clear</button>` : ''}
            </small>
        </div>`;
    }

    function webhookHtml(provider) {
        const hook = provider.webhook;

        return `<div class="msg-webhook">
            <h4><i class="fa-solid fa-link"></i> Webhook setup</h4>
            <div class="msg-webhook__url">
                <input class="input" type="text" readonly value="${U.esc(provider.webhookUrl)}" aria-label="Callback URL">
                <button type="button" class="btn btn--ghost btn--sm" data-copy="${U.esc(provider.webhookUrl)}"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <p class="text-sm muted">${U.esc(hook.methods)} · ${U.esc(hook.auth)}</p>
            <ol class="msg-steps-list">${hook.steps.map((step) => `<li>${U.esc(step)}</li>`).join('')}</ol>
        </div>`;
    }

    /* ------------------------------------------------------------ options */

    function optionsHtml() {
        const reveal = config.settings.otp_reveal_enabled === '1';

        return `
            <div class="settings-v2-card__head">
                <div class="settings-v2-icon" style="background:color-mix(in srgb, var(--bad) 12%, transparent);color:var(--bad)"><i class="fa-solid fa-eye"></i></div>
                <div>
                    <h3>Support options</h3>
                    <p>Two settings that change what staff can see and how long records are kept.</p>
                </div>
            </div>
            <div class="st-toggle-card">
                <div class="st-toggle-info">
                    <strong id="revealLabel">Let admins reveal a live OTP</strong>
                    <span>Adds an eye to the Message Delivery page for a code that has <em>not yet</em> been used,
                          so support can read it back to a customer who says it never arrived. The code is wiped the
                          moment it is verified or expires, and every reveal is recorded against the admin who did it.
                          Leave this off unless support needs it.</span>
                </div>
                <label class="st-switch">
                    <input type="checkbox" id="otpReveal" ${reveal ? 'checked' : ''} aria-labelledby="revealLabel">
                    <span class="st-slider"></span>
                </label>
            </div>
            <div class="form-grid mt-4">
                <div class="field">
                    <label for="webhookRetention">Keep webhook records for (days)</label>
                    <input class="input" id="webhookRetention" type="text" inputmode="numeric"
                        value="${U.esc(config.settings.webhook_retention_days)}">
                    <small class="muted">Applied by <code>php vayu webhooks:prune</code>. Between 1 and 365.</small>
                </div>
            </div>`;
    }

    /* -------------------------------------------------------------- wiring */

    function wire() {
        const root = document.getElementById('messaging');
        if (!root) return;

        root.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(button.dataset.copy);
                    toast.success('Callback URL copied.');
                } catch (error) {
                    toast.info('Copy failed — select the URL and copy it by hand.');
                }
            });
        });

        root.querySelectorAll('[data-rotate]').forEach((button) => {
            button.addEventListener('click', () => rotate(button.dataset.rotate, button));
        });

        root.querySelectorAll('[data-test]').forEach((button) => {
            button.addEventListener('click', () => sendTest(button.dataset.test));
        });

        root.querySelectorAll('[data-clear]').forEach((button) => {
            button.addEventListener('click', () => clearField(button.dataset.clear));
        });

        root.querySelectorAll('.msg-chain').forEach((card) => {
            const event = card.dataset.event;

            card.querySelectorAll('[data-move]').forEach((button) => {
                button.addEventListener('click', () => {
                    const index = Number(button.dataset.index);
                    const to = button.dataset.move === 'up' ? index - 1 : index + 1;
                    const steps = chains[event];
                    if (to < 0 || to >= steps.length) return;
                    [steps[index], steps[to]] = [steps[to], steps[index]];
                    render();
                });
            });

            card.querySelectorAll('[data-step]').forEach((input) => {
                input.addEventListener('change', () => {
                    const step = chains[event].find((s) => s.provider === input.dataset.step);
                    if (step) step.enabled = input.checked;
                    render();
                });
            });

            const mode = card.querySelector('[data-mode]');
            if (mode) mode.addEventListener('change', () => { modes[event] = mode.value; });
        });
    }

    async function rotate(name, button) {
        const [provider, field] = splitName(name);
        const approved = await confirmDialog({
            title: 'Generate a new secret?',
            body: `A new secret replaces the current one immediately. Delivery reports from this provider will be
                   rejected until you paste the new value into its panel.`,
            confirmLabel: 'Generate',
        });
        if (!approved) return;

        button.disabled = true;
        try {
            const result = (await api.post(`api/v1/admin/messaging/secrets/${encodeURIComponent(provider)}/rotate`, { field })).data;
            await showSecret(result);
            await mount();
        } catch (error) {
            toast.error(error.message || 'Could not generate a secret.');
        } finally {
            button.disabled = false;
        }
    }

    /* The generated secret is shown once, in a dialog, because this is the only
       moment it is readable — afterwards the panel has a masked hint only.
       confirm() escapes its body, so this needs modal.open to render a field
       the operator can actually copy out of. */
    async function showSecret(result) {
        await modal.open({
            title: 'Copy this secret now',
            subtitle: 'It cannot be shown again.',
            icon: 'fa-key',
            html: `<p class="text-sm">Paste it into the provider's panel and save there.</p>
                   <div class="msg-webhook__url">
                       <input class="input" type="text" readonly value="${U.esc(result.secret)}" data-autofocus aria-label="Generated secret">
                       <button type="button" class="btn btn--ghost btn--sm" data-secret-copy><i class="fa-regular fa-copy"></i> Copy</button>
                   </div>
                   ${result.webhookUrl ? `<p class="text-sm muted mt-4">Callback URL: <code>${U.esc(result.webhookUrl)}</code></p>` : ''}`,
            footer: '<button type="button" class="btn btn--primary" data-close>I have copied it</button>',
            onMount: (panel) => {
                const field = panel.querySelector('input');
                const button = panel.querySelector('[data-secret-copy]');
                if (field) field.select();
                if (button) {
                    button.addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(field.value);
                            toast.success('Secret copied.');
                        } catch (error) {
                            field.select();
                            toast.info('Copy failed — the value is selected, copy it by hand.');
                        }
                    });
                }
            },
        });
    }

    async function sendTest(providerKey) {
        const provider = config.providers.find((p) => p.key === providerKey);
        const events = provider.events;
        const to = window.prompt(
            provider.recipient === 'email'
                ? `Send a test ${provider.label} message to which email address?`
                : `Send a test ${provider.label} message to which mobile number?`,
            ''
        );
        if (!to || !to.trim()) return;

        try {
            toast.info('Sending test message…');
            const result = (await api.post('api/v1/admin/messaging/test', {
                provider: providerKey,
                to: to.trim(),
                event: events.includes('otp') ? 'otp' : events[0],
            })).data;
            toast.success(result.message || 'Test message accepted.');
            await mount();
        } catch (error) {
            toast.error(error.message || 'The test message was not accepted.');
        }
    }

    async function clearField(name) {
        const [provider, field] = splitName(name);
        const approved = await confirmDialog({
            title: 'Clear this value?',
            body: 'The stored value is deleted. If the server still sets it in .env, that value takes over again.',
            confirmLabel: 'Clear',
            danger: true,
        });
        if (!approved) return;

        try {
            await api.request('PUT', ENDPOINT, { body: { clear: [`${provider}.${field}`] } });
            toast.success('Value cleared.');
            await mount();
        } catch (error) {
            toast.error(error.message || 'Could not clear the value.');
        }
    }

    function splitName(name) {
        const cut = name.lastIndexOf('|');
        return [name.slice(0, cut), name.slice(cut + 1)];
    }

    /**
     * Called by settings.js when the page is saved. Returns a message for the
     * single toast that save shows, or throws so the page reports one failure.
     */
    async function save() {
        if (!config) return null;

        const root = document.getElementById('messaging');
        const providers = {};

        root.querySelectorAll('[data-field]').forEach((input) => {
            const [provider, field] = splitName(input.dataset.field);
            const value = (input.value || '').trim();
            /* A blank secret means "keep the stored one"; the server ignores
               blanks, but there is no reason to send them either. */
            if (value === '') return;
            providers[provider] = providers[provider] || {};
            providers[provider][field] = value;
        });

        const payload = {
            providers,
            chains: {},
            modes,
            settings: {
                otp_reveal_enabled: document.getElementById('otpReveal').checked ? '1' : '0',
                webhook_retention_days: (document.getElementById('webhookRetention').value || '30').trim(),
            },
        };

        Object.keys(chains).forEach((event) => {
            payload.chains[event] = chains[event].map((step, index) => ({
                provider: step.provider,
                position: (index + 1) * 10,
                enabled: step.enabled,
            }));
        });

        await api.request('PUT', ENDPOINT, { body: payload });
        await mount();

        return 'Messaging configuration saved.';
    }
}());
