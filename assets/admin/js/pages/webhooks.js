/* Admin → System → Webhooks.
   ---------------------------------------------------------------------------
   Every inbound provider callback, including the refused ones — which is the
   whole point. A webhook that "does not work" is almost always arriving and
   being rejected for a wrong secret or a wrong header name, and before this
   page a rejection returned 403 and left nothing to look at.

   Three outcomes are worth telling apart, so they are the three filters:
     refused   — authentication failed; the provider is talking to us, we are
                 saying no. Fix the secret.
     unmatched — authenticated, but no recorded send matches. Usually a
                 callback for a message sent before this log existed, or a
                 correlation ID mismatch. Replayable.
     applied   — authenticated and it moved a message's status. Working. */
(function () {
    'use strict';

    const { api, util: U, layout, toast, confirm: confirmDialog } = window.HAZRA;
    const endpoint = 'api/v1/admin/webhook-events';

    let page = 1;
    let generation = 0;
    let rows = [];
    let timer;
    let busy = false;
    const expanded = new Set();

    const $ = (id) => document.getElementById(id);

    window.HAZRA.boot(init);

    async function init() {
        $('pageHead').innerHTML = layout.pageHead({
            title: 'Webhooks',
            crumb: [{ label: 'System' }, { label: 'Webhooks' }],
            sub: 'Every delivery report a provider has sent, including the ones this server refused.',
        });

        $('view').innerHTML = `<div class="webhooks">
            <div id="hookStats"></div>
            <article class="card">
                <form id="hookFilters" class="delivery-filters">
                    <div class="field">
                        <label for="hookSearch">Search</label>
                        <div class="input-icon-group"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input class="input" id="hookSearch" name="q" type="search" maxlength="120" placeholder="Number, message ID, error or IP"></div>
                    </div>
                    <div class="field">
                        <label for="hookProvider">Provider</label>
                        <select class="input" id="hookProvider" name="provider"><option value="">All providers</option></select>
                    </div>
                    <div class="field">
                        <label for="hookResult">Outcome</label>
                        <select class="input" id="hookResult" name="result">
                            <option value="">Any outcome</option>
                            <option value="rejected">Refused — authentication failed</option>
                            <option value="unmatched">Accepted but matched nothing</option>
                            <option value="applied">Applied to a message</option>
                        </select>
                    </div>
                    <div class="field"><label for="hookFrom">From (UTC)</label><input class="input" id="hookFrom" name="from" type="date"></div>
                    <div class="field"><label for="hookTo">Through (UTC)</label><input class="input" id="hookTo" name="to" type="date"></div>
                    <div class="delivery-filter-actions">
                        <button type="submit" class="btn btn--primary">Apply filters</button>
                        <button id="hookReset" type="button" class="btn btn--ghost">Reset</button>
                        <button id="hookRefresh" type="button" class="btn btn--ghost">Refresh</button>
                    </div>
                </form>
                <p class="text-sm muted">Callback URLs and secrets are set per provider in
                    <a href="${U.esc(api.base)}admin/settings?tab=messaging">Settings → Messaging &amp; Webhooks</a>.
                    Secrets and signatures are never stored here — an authentication header is recorded only as its length.</p>
                <p id="hookNotice" role="status" aria-live="polite"></p>
                <div class="delivery-table-wrap" id="hookTable"></div>
                <div class="delivery-pagination">
                    <button id="hookPrevious" type="button" class="btn btn--ghost">Previous</button>
                    <span id="hookPage"></span>
                    <button id="hookNext" type="button" class="btn btn--ghost">Next</button>
                </div>
            </article>
        </div>`;

        await fillProviders();

        $('hookFilters').addEventListener('submit', (event) => { event.preventDefault(); page = 1; load(); });
        $('hookReset').addEventListener('click', () => { $('hookFilters').reset(); page = 1; load(); });
        $('hookRefresh').addEventListener('click', load);
        $('hookPrevious').addEventListener('click', () => { page--; load(); });
        $('hookNext').addEventListener('click', () => { page++; load(); });

        $('hookTable').addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-expand]');
            if (toggle) {
                const id = toggle.dataset.expand;
                if (expanded.has(id)) expanded.delete(id); else expanded.add(id);
                paint();
                return;
            }
            const replay = event.target.closest('[data-replay]');
            if (replay) replayEvent(replay.dataset.replay, replay);
        });

        timer = setInterval(() => { if (!document.hidden && !busy) load(); }, 30000);
        window.addEventListener('pagehide', () => clearInterval(timer), { once: true });

        await load();
    }

    /* The provider list comes from the messaging config, so this page cannot
       drift from the set of providers the server actually knows about. */
    async function fillProviders() {
        try {
            const config = (await api.get('api/v1/admin/messaging/config')).data;
            const select = $('hookProvider');
            config.providers
                .filter((provider) => provider.webhook)
                .forEach((provider) => {
                    const option = document.createElement('option');
                    option.value = provider.key;
                    option.textContent = provider.label;
                    select.appendChild(option);
                });
        } catch (error) {
            /* A missing filter is not worth an error banner; the list still loads. */
        }
    }

    async function load() {
        if (busy) return;
        const version = ++generation;
        $('hookNotice').textContent = 'Loading…';
        $('hookTable').setAttribute('aria-busy', 'true');

        const query = { ...Object.fromEntries(new FormData($('hookFilters'))), page };

        try {
            const result = (await api.get(endpoint, query)).data;
            if (version !== generation) return;

            const pages = Math.max(1, Math.ceil(result.total / result.pageSize));
            if (page > pages) { page = pages; return load(); }

            rows = result.items;
            $('hookStats').innerHTML = U.statStrip([
                ['fa-inbox', 'blue', result.total, 'Calls received', 'Matching these filters'],
                ['fa-circle-check', 'green', result.summary.applied, 'Applied', 'Moved a message status'],
                ['fa-circle-question', 'warn', result.summary.unmatched, 'Matched nothing', 'Authenticated, no send found'],
                ['fa-ban', 'red', result.summary.rejected, 'Refused', 'Authentication failed'],
            ]);
            paint();
            $('hookPage').textContent = `Page ${page} of ${pages} · ${result.total} records`;
            $('hookPrevious').disabled = page <= 1;
            $('hookNext').disabled = page >= pages;
            $('hookNotice').textContent = 'Updated ' + new Date().toLocaleTimeString() + '. Refreshes every 30 seconds.';
        } catch (error) {
            if (version !== generation) return;
            rows = [];
            $('hookTable').replaceChildren();
            $('hookStats').replaceChildren();
            $('hookNotice').textContent = error.message || 'Could not load webhook events. Use Refresh to try again.';
            $('hookPrevious').disabled = true;
            $('hookNext').disabled = true;
        } finally {
            if (version === generation) $('hookTable').setAttribute('aria-busy', 'false');
        }
    }

    function paint() {
        $('hookTable').innerHTML = rows.length
            ? `<table><caption class="sr-only">Inbound provider webhook calls</caption>
                <thead><tr><th>Received</th><th>Provider / Route</th><th>Outcome</th><th>Event</th><th>Detail</th></tr></thead>
                <tbody>${rows.map(renderRow).join('')}</tbody></table>`
            : '<p class="muted">No webhook calls match these filters. A provider that has never called will have nothing here — check its callback URL and secret in Settings → Messaging.</p>';
    }

    function outcome(row) {
        if (Number(row.signature_valid) !== 1) return ['bad', 'Refused'];
        if (Number(row.matched_count) > 0) return ['ok', 'Applied'];
        return ['warn', 'Matched nothing'];
    }

    function renderRow(row) {
        const [tone, label] = outcome(row);
        const isOpen = expanded.has(row.id);

        return `<tr>
            <td><time datetime="${U.esc(row.received_at)}">${U.esc(U.fmtDateTime(row.received_at))}</time>
                <small>${U.esc(row.ip || 'no address')} · ${U.esc(String(row.processing_ms || 0))} ms</small></td>
            <td><strong>${U.esc(row.provider_key)}</strong><small>${U.esc(row.method)} /${U.esc(row.route)}</small></td>
            <td><span class="tag ${tone}">${U.esc(label)}</span><small>HTTP ${U.esc(String(row.http_status))}</small></td>
            <td>${U.esc(row.event_type || '—')}<small>${U.esc(row.recipient || '')}</small></td>
            <td>
                ${row.error_code ? `<strong>${U.esc(row.error_code)}</strong>` : ''}
                ${row.error_detail ? `<small>${U.esc(row.error_detail)}</small>` : ''}
                ${row.matched_message_id ? `<small>Message ${U.esc(row.matched_message_id)}</small>` : ''}
                ${row.replayed_at ? `<small>Replayed ${U.esc(U.fmtDateTime(row.replayed_at))}</small>` : ''}
                <div>
                    <button type="button" class="webhook-row__toggle" data-expand="${U.esc(row.id)}">
                        ${isOpen ? 'Hide payload' : 'Show payload'}
                    </button>
                </div>
                ${isOpen ? `<pre class="webhook-payload">${U.esc(pretty(row.payload_redacted))}

Headers: ${U.esc(pretty(row.headers_redacted))}</pre>` : ''}
                ${row.canReplay
                    ? `<button class="btn btn--ghost btn--sm" type="button" data-replay="${U.esc(row.id)}">Replay</button>`
                    : `<small>${U.esc(row.replayReason || '')}</small>`}
            </td>
        </tr>`;
    }

    function pretty(raw) {
        if (!raw) return '(nothing recorded)';
        try {
            return JSON.stringify(JSON.parse(raw), null, 2);
        } catch (error) {
            return String(raw);
        }
    }

    async function replayEvent(id, button) {
        if (busy) return;
        busy = true;
        ++generation;

        try {
            const approved = await confirmDialog({
                title: 'Replay this callback?',
                body: 'The recorded payload is applied again. It can only move a message forward — a delivered or read message cannot be regressed.',
                confirmLabel: 'Replay',
            });
            if (!approved) return;

            button.disabled = true;
            const result = (await api.post(`${endpoint}/${encodeURIComponent(id)}/replay`, {})).data;
            if (result.applied > 0) toast.success(result.message);
            else toast.info(result.message);
        } catch (error) {
            toast.error(error.message || 'Replay failed.');
        } finally {
            busy = false;
            await load();
        }
    }
}());
