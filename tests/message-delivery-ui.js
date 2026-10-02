// node tests/message-delivery-ui.js — API/DOM harness; no browser or live server.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const elements = new Map();
function element(id) {
    if (!elements.has(id)) elements.set(id, {
        innerHTML: '', textContent: '', disabled: false, handlers: {}, elements: { q: { value: '' } },
        addEventListener(event, fn) { this.handlers[event] = fn; },
        setAttribute() {}, replaceChildren() { this.innerHTML = ''; }, reset() {},
    });
    return elements.get(id);
}
const escaped = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
let requests = [], posts = [], result, rejectRequest = false, bootPromise;
const context = {
    document: { getElementById: element, hidden: false },
    FormData: class { constructor() { this.entries = [['q', '90029'], ['channel', 'sms'], ['status', 'failed'], ['from', '2026-10-01'], ['to', '2026-10-02']]; } [Symbol.iterator]() { return this.entries[Symbol.iterator](); } },
    setInterval: () => 1, clearInterval() {}, console,
    window: { addEventListener() {}, HAZRA: {
        util: { esc: escaped, fmtDateTime: value => value, statStrip: cards => JSON.stringify(cards) },
        layout: { pageHead: options => options.title }, toast: { success() {}, error() {} },
        confirm: async () => true,
        api: {
            async get(path, query) { requests.push({ path, query }); if (rejectRequest) throw new Error('Unavailable'); return { data: result }; },
            async post(path, body) { posts.push({ path, body }); return { data: { message: 'Retry accepted.' } }; },
        },
        boot(fn) { bootPromise = fn(); },
    } },
};
result = { total: 1, pageSize: 25, summary: { delivered: 0, read: 0, accepted: 0, sent: 0, sending: 0, failed: 1 }, items: [{
    id: 'id', channel: 'sms', provider: 'twilio', mobile: '919002921509', purpose: 'warranty_free', status: 'failed',
    reference_id: 'reference', provider_id: 'SM', error_code: 'code', error_detail: '<script>bad()</script>',
    created_at: '2026-10-02T00:00:00Z', updated_at: '2026-10-02T00:00:00Z', canRetry: true, retryLabel: 'Send fresh OTP',
}] };
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../assets/admin/js/pages/message-delivery.js'), 'utf8'), context);
(async () => {
    await bootPromise;
    assert.equal(requests[0].query.channel, 'sms', 'Filter parameters must be passed directly to HAZRA.api.get');
    assert.equal(requests[0].query.to, '2026-10-02');
    assert.equal(requests[0].query.page, 1);
    const html = element('deliveryTable').innerHTML;
    assert(html.includes('Failed') && html.includes('Send fresh OTP'), 'Failed status and retry action missing');
    assert(html.includes('&lt;script&gt;') && !html.includes('<script>bad'), 'Provider error text must be escaped');
    assert(element('deliveryPrevious').disabled && element('deliveryNext').disabled, 'Single-page pagination');
    const retryButton = { dataset: { retry: 'sms:id' }, disabled: false };
    element('deliveryTable').handlers.click({ target: { closest: selector => selector === '[data-retry]' ? retryButton : null } });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(posts[0].path, 'api/v1/admin/message-delivery/sms/id/retry', 'Retry action must call the selected channel endpoint');
    assert.equal(Object.keys(posts[0].body).length, 0, 'Retry must not post a client-provided phone or OTP');
    result.items[0].status = 'logged'; result.items[0].canRetry = false;
    await element('deliveryRefresh').handlers.click();
    assert(element('deliveryTable').innerHTML.includes('Development log only'), 'Development sends must not appear delivered');
    result.items = []; result.total = 0;
    await element('deliveryRefresh').handlers.click();
    assert(element('deliveryTable').innerHTML.includes('No messages match'), 'Empty state missing');
    rejectRequest = true;
    await element('deliveryRefresh').handlers.click();
    assert.equal(element('deliveryNotice').textContent, 'Unavailable');
    assert.equal(element('deliveryTable').innerHTML, '', 'Failed requests must clear stale rows');
    console.log('Message delivery UI filters, status labels, errors, pagination and empty states passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
