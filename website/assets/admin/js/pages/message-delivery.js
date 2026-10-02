(function () {
    'use strict';
    const { api, util: U, layout, toast, confirm: confirmDialog } = window.HAZRA;
    const endpoint = 'api/v1/admin/message-delivery';
    const labels = { sending: 'Sending', accepted: 'Accepted by provider', sent: 'Sent', delivered: 'Delivered', read: 'Read', failed: 'Failed', logged: 'Development log only' };
    const tones = { sending: 'warn', accepted: 'info', sent: 'info', delivered: 'ok', read: 'ok', failed: 'bad', logged: 'off' };
    let page = 1, generation = 0, timer, rows = [], retrying = false;
    const $ = id => document.getElementById(id);
    window.HAZRA.boot(init);

    async function init() {
        $('pageHead').innerHTML = layout.pageHead({
            title: 'Message Delivery', crumb: [{ label: 'System' }, { label: 'Message Delivery' }],
            sub: 'Track WhatsApp and SMS sends, review delivery failures, and retry failed messages.',
        });
        $('view').innerHTML = `<div class="message-delivery">
            <div id="deliveryStats"></div>
            <article class="card">
                <form id="deliveryFilters" class="delivery-filters">
                    <div class="field">
                        <label for="deliverySearch">Search</label>
                        <div class="input-icon-group"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input class="input" id="deliverySearch" name="q" type="search" maxlength="120" placeholder="Phone, purpose, or reference"></div>
                    </div>
                    <div class="field"><label for="deliveryChannel">Channel</label><select class="input" id="deliveryChannel" name="channel"><option value="">All channels</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option></select></div>
                    <div class="field"><label for="deliveryStatus">Status</label><select class="input" id="deliveryStatus" name="status"><option value="">All statuses</option>${Object.entries(labels).map(([key, label]) => `<option value="${key}">${label}</option>`).join('')}</select></div>
                    <div class="field"><label for="deliveryFrom">From (UTC)</label><input class="input" id="deliveryFrom" name="from" type="date"></div>
                    <div class="field"><label for="deliveryTo">Through (UTC)</label><input class="input" id="deliveryTo" name="to" type="date"></div>
                    <div class="delivery-filter-actions">
                        <button type="submit" class="btn btn--primary">Apply filters</button>
                        <button id="deliveryReset" type="button" class="btn btn--ghost">Reset</button>
                        <button id="deliveryRefresh" type="button" class="btn btn--ghost">Refresh</button>
                    </div>
                </form>
                <p class="text-sm muted">Accepted means the provider received the request. Delivered requires a delivery report. Development logs do not send messages.</p>
                <p id="deliveryNotice" role="status" aria-live="polite"></p>
                <div class="delivery-table-wrap" id="deliveryTable"></div>
                <div class="delivery-pagination"><button id="deliveryPrevious" type="button" class="btn btn--ghost">Previous</button><span id="deliveryPage"></span><button id="deliveryNext" type="button" class="btn btn--ghost">Next</button></div>
            </article>
        </div>`;
        $('deliveryFilters').addEventListener('submit', event => { event.preventDefault(); page = 1; load(); });
        $('deliveryReset').addEventListener('click', () => { $('deliveryFilters').reset(); page = 1; load(); });
        $('deliveryRefresh').addEventListener('click', load);
        $('deliveryPrevious').addEventListener('click', () => { page--; load(); });
        $('deliveryNext').addEventListener('click', () => { page++; load(); });
        $('deliveryTable').addEventListener('click', event => {
            const button = event.target.closest('[data-retry]');
            if (button) retry(rows.find(row => `${row.channel}:${row.id}` === button.dataset.retry), button);
            const reference = event.target.closest('[data-reference]');
            if (reference) { $('deliveryFilters').elements.q.value = reference.dataset.reference; page = 1; load(); }
        });
        timer = setInterval(() => { if (!document.hidden && !retrying) load(); }, 30000);
        window.addEventListener('pagehide', () => clearInterval(timer), { once: true });
        await load();
    }

    async function load() {
        if (retrying) return;
        const version = ++generation;
        $('deliveryNotice').textContent = 'Loading messages…';
        $('deliveryTable').setAttribute('aria-busy', 'true');
        const query = { ...Object.fromEntries(new FormData($('deliveryFilters'))), page };
        try {
            const result = (await api.get(endpoint, query)).data;
            if (version !== generation) return;
            const pages = Math.max(1, Math.ceil(result.total / result.pageSize));
            if (page > pages) { page = pages; return load(); }
            rows = result.items;
            $('deliveryStats').innerHTML = U.statStrip([
                ['fa-paper-plane', 'blue', result.total, 'Total sends', 'Matching these filters'],
                ['fa-check-double', 'green', result.summary.delivered + result.summary.read, 'Delivered / Read', 'Confirmed by provider'],
                ['fa-clock', 'warn', result.summary.accepted + result.summary.sent + result.summary.sending, 'Awaiting delivery', 'Delivery not confirmed'],
                ['fa-triangle-exclamation', 'red', result.summary.failed, 'Failed', 'Review errors below'],
            ]);
            $('deliveryTable').innerHTML = rows.length ? `<table><caption class="sr-only">WhatsApp and SMS delivery records</caption><thead><tr><th>Channel / Provider</th><th>Recipient / Purpose</th><th>Status</th><th>Sent / Updated</th><th>Reference / Provider ID</th><th>Error / Retry</th></tr></thead><tbody>${rows.map(renderRow).join('')}</tbody></table>` : '<p class="muted">No messages match these filters. New sends will appear here once messaging is used.</p>';
            $('deliveryPage').textContent = `Page ${page} of ${pages} · ${result.total} records`;
            $('deliveryPrevious').disabled = page <= 1;
            $('deliveryNext').disabled = page >= pages;
            $('deliveryNotice').textContent = 'Updated ' + new Date().toLocaleTimeString() + '. Refreshes every 30 seconds.';
        } catch (error) {
            if (version !== generation) return;
            rows = [];
            $('deliveryTable').replaceChildren();
            $('deliveryStats').replaceChildren();
            $('deliveryNotice').textContent = error.message || 'Could not load messages. Use Refresh to try again.';
            $('deliveryPrevious').disabled = $('deliveryNext').disabled = true;
        } finally {
            if (version === generation) $('deliveryTable').setAttribute('aria-busy', 'false');
        }
    }

    function renderRow(row) {
        return `<tr><td><strong>${row.channel === 'whatsapp' ? 'WhatsApp' : 'SMS'}</strong><small>${U.esc(row.provider)}</small></td>
            <td><strong>${U.esc(row.mobile)}</strong><small>${U.esc(row.purpose.replace(/_/g, ' '))}</small>${row.template_name ? `<small>${U.esc(row.template_name)}</small>` : ''}</td>
            <td><span class="tag ${tones[row.status] || 'off'}">${U.esc(labels[row.status] || row.status)}</span></td>
            <td><time datetime="${U.esc(row.created_at)}">${U.esc(U.fmtDateTime(row.created_at))}</time><small>Updated ${U.esc(U.fmtDateTime(row.updated_at))}</small></td>
            <td><button class="delivery-reference" type="button" data-reference="${U.esc(row.reference_id)}" title="Show sends for this reference">${U.esc(row.reference_id)}</button><small>${U.esc(row.provider_id || 'No provider ID')}</small></td>
            <td>${row.error_code ? `<strong>${U.esc(row.error_code)}</strong>` : ''}${row.error_detail ? `<small>${U.esc(row.error_detail)}</small>` : ''}
                ${row.canRetry ? `<button class="btn btn--ghost btn--sm" type="button" data-retry="${U.esc(row.channel + ':' + row.id)}">${U.esc(row.retryLabel)}</button>` : row.status === 'failed' ? `<small>${U.esc(row.retryReason)}</small>` : ''}</td></tr>`;
    }

    async function retry(row, button) {
        if (!row || retrying) return;
        retrying = true; ++generation;
        $('deliveryTable').setAttribute('aria-busy', 'false');
        try {
            const otp = row.retryLabel === 'Send fresh OTP';
            const approved = await confirmDialog({ title: row.retryLabel + '?',
                body: otp ? `Send a fresh verification code to ${row.mobile}? This replaces the previous code and respects OTP limits.` : `Send the configured ${row.purpose.replace(/_/g, ' ')} message to ${row.mobile} again?`,
                confirmLabel: row.retryLabel });
            if (!approved) return;
            button.disabled = true;
            const result = await api.post(`${endpoint}/${row.channel}/${encodeURIComponent(row.id)}/retry`, {});
            toast.success(result.data.message || 'Retry accepted for delivery.');
        } catch (error) { toast.error(error.message || 'Retry failed.'); }
        finally { retrying = false; await load(); }
    }
}());
