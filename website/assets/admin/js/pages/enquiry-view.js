/* Enquiry detail — the message, the reply thread and a composer on the left;
   everything about the record on the right.

   Every rail control saves the moment it changes rather than waiting for a
   form bar: this screen gets used while on the phone to the person who sent
   the message, and a half-filled form nobody remembered to submit is worse
   than no form at all. */
(function () {
    'use strict';

    const { util: U, store, layout, toast, modal, confirm: confirmDialog } = window.TMH;

    const STATUS = [
        { value: 'new', label: 'New', tone: 'warn' },
        { value: 'replied', label: 'Replied', tone: 'info' },
        { value: 'closed', label: 'Closed', tone: 'ok' },
        { value: 'spam', label: 'Spam', tone: 'off' },
    ];

    const PRIORITY = [
        { value: 'low', label: 'Low' },
        { value: 'normal', label: 'Normal' },
        { value: 'high', label: 'High' },
    ];

    /* Canned replies. Phase 2 moves these into settings so the desk can edit
       them without a developer; the substitution contract stays the same. */
    const TEMPLATES = [
        {
            id: 'ack', label: 'Acknowledge',
            body: 'Dear {{name}},\n\nThank you for writing to Teresa Memorial Hospital. We have received your message and someone from the {{department}} desk will come back to you within one working day.\n\nWarm regards,\n{{me}}\nTeresa Memorial Hospital',
        },
        {
            id: 'appointment', label: 'Offer an appointment',
            body: 'Dear {{name}},\n\nWe can see you at the {{department}} OPD. Please call {{phone}} to confirm a slot, or reply with a day that suits you and we will hold one.\n\nDo bring any previous reports and a photo ID.\n\nWarm regards,\n{{me}}',
        },
        {
            id: 'insurance', label: 'Insurance / billing',
            body: 'Dear {{name}},\n\nOur billing desk handles insurance and package pricing. Please call {{phone}} between 9am and 6pm, or share your policy details here and we will check empanelment for you.\n\nWarm regards,\n{{me}}',
        },
        {
            id: 'reports', label: 'Reports and records',
            body: 'Dear {{name}},\n\nReports can be collected from the records desk on the ground floor, or emailed to the address on file once they are signed off.\n\nWarm regards,\n{{me}}',
        },
    ];

    /* Phase 1 has no session; the panel acts as the Admin Desk account. */
    const ME = { id: 'usr-001', name: 'Admin Desk' };

    let row = null;
    let users = [];
    let departments = [];
    let phone = '+91 90460 05557';

    window.TMH.boot(init);

    async function init() {
        const id = U.param('id');

        const [userRows, deptRows, settings] = await Promise.all([
            store.all('users'),
            store.all('departments'),
            store.getDoc('settings'),
        ]);

        users = userRows.filter((u) => u.status !== 'hidden');
        departments = deptRows;

        const primary = ((settings && settings.phones) || []).find((p) => p.isPrimary);
        if (primary) phone = primary.number;

        row = id ? await store.get('enquiries', id) : null;

        if (!row) {
            document.getElementById('pageHead').innerHTML = layout.pageHead({
                crumb: [{ label: 'Growth' }, { label: 'Enquiries', href: 'enquiries' }, { label: 'Not found' }],
                title: 'Enquiry not found',
            });
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-envelope-circle-check"></i></div>
                    <h3>This enquiry is gone</h3>
                    <p>It was deleted, or the link is stale.</p>
                    <a class="btn btn--primary" href="enquiries">
                        <i class="fa-solid fa-arrow-left"></i> Back to the inbox</a>
                </div></article>`;
            return;
        }

        render();

        /* ?reply=1 comes from the inbox's Reply row action. */
        if (U.param('reply')) {
            const box = document.getElementById('replyBody');
            if (box) {
                box.focus();
                box.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        }
    }

    /* ---------- render ---------- */

    function render() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Growth' },
                { label: 'Enquiries', href: 'enquiries' },
                { label: row.subject || row.name },
            ],
            title: row.subject || '(no subject)',
            sub: `From ${row.name} · ${U.fmtDateTime(row.receivedAt)} · via ${row.source || 'unknown source'}`,
            actions: `
                <a class="btn btn--ghost" href="enquiries"><i class="fa-solid fa-arrow-left"></i> Inbox</a>
                ${row.status === 'closed'
                    ? '<button type="button" class="btn btn--ghost" data-act="reopen"><i class="fa-solid fa-rotate-left"></i> Reopen</button>'
                    : '<button type="button" class="btn btn--primary" data-act="close"><i class="fa-solid fa-circle-check"></i> Mark closed</button>'}`,
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <div class="col gap-4">
                    ${row.status === 'new' ? `
                    <div class="banner banner--warn">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span class="grow"><b>Not answered yet.</b> Sending a reply below marks this enquiry replied.</span>
                    </div>` : ''}

                    <article class="card anim-item">
                        <div class="card__head"><h3>Conversation</h3></div>
                        ${threadHtml()}
                    </article>

                    <article class="card anim-item" id="composerCard">
                        <div class="card__head">
                            <h3>Reply</h3>
                            <div class="grow"></div>
                            <select id="templatePick" aria-label="Insert a template"
                                    style="height:32px;padding:0 26px 0 10px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                                <option value="">Insert a template…</option>
                                ${TEMPLATES.map((t) => `<option value="${U.esc(t.id)}">${U.esc(t.label)}</option>`).join('')}
                            </select>
                        </div>

                        <div class="field field--wide">
                            <label for="replyBody" class="sr-only">Reply</label>
                            <textarea id="replyBody" rows="7" placeholder="Write a reply to ${U.esc(row.name)}…"></textarea>
                            <small>Phase 1 records the reply on the thread. Phase 2 sends it from
                                the address in Contact Details.</small>
                        </div>

                        <div class="row gap-2 wrap">
                            <button type="button" class="btn btn--primary" data-act="send">
                                <i class="fa-solid fa-paper-plane"></i> Send reply</button>
                            <button type="button" class="btn btn--ghost" data-act="note">
                                <i class="fa-solid fa-note-sticky"></i> Add internal note</button>
                            <div class="grow"></div>
                            ${row.email ? `<a class="btn btn--link" href="mailto:${U.esc(row.email)}?subject=${encodeURIComponent(`Re: ${row.subject || ''}`)}">
                                <i class="fa-solid fa-envelope"></i> Open in mail client</a>` : ''}
                        </div>
                    </article>
                </div>

                <div class="split__rail">
                    ${contactCardHtml()}
                    ${recordCardHtml()}
                    ${relatedCardHtml()}
                </div>
            </div>`;

        U.stagger(document.getElementById('view'));
        wire();
    }

    function threadHtml() {
        /* One timeline: the original message, replies out, and internal notes
           interleaved by time, so "we noted X before replying" reads in the
           order it happened. */
        const items = [
            { kind: 'in', by: row.name, at: row.receivedAt, body: row.message },
            ...(row.replies || []).map((r) => ({ kind: 'out', by: r.by, at: r.at, body: r.body })),
            ...(row.internalNotes || []).map((n) => ({ kind: 'note', by: n.by, at: n.at, body: n.body })),
        ].sort((a, b) => new Date(a.at || 0) - new Date(b.at || 0));

        return `<div class="thread">${items.map((i) => `
            <div class="thread__item thread__item--${U.esc(i.kind)}">
                <span class="thread__avatar">${U.esc(U.initials(i.by))}</span>
                <div class="thread__bubble">
                    <div class="thread__meta">
                        <b>${U.esc(i.by)}</b>
                        ${i.kind === 'note' ? '<span class="tag warn">Internal note</span>' : ''}
                        ${i.kind === 'in' ? `<span class="chip">${U.esc(row.source || 'Form')}</span>` : ''}
                        <span title="${U.esc(U.fmtDateTime(i.at))}">${U.esc(U.ago(i.at))}</span>
                    </div>
                    <div class="thread__body">${U.esc(i.body)}</div>
                </div>
            </div>`).join('')}</div>`;
    }

    function contactCardHtml() {
        return `
        <article class="card anim-item">
            <div class="card__head"><h3>Contact</h3></div>
            <dl class="kv">
                <dt>Name</dt><dd>${U.esc(row.name)}</dd>
                <dt>Email</dt>
                <dd>${row.email
                    ? `<a href="mailto:${U.esc(row.email)}">${U.esc(row.email)}</a>
                       <button type="button" class="btn btn--link" data-copy="${U.esc(row.email)}">Copy</button>`
                    : '<span class="muted">Not given</span>'}</dd>
                <dt>Phone</dt>
                <dd>${row.phone
                    ? `<a href="tel:${U.esc(row.phone.replace(/\s+/g, ''))}">${U.esc(row.phone)}</a>
                       <button type="button" class="btn btn--link" data-copy="${U.esc(row.phone)}">Copy</button>`
                    : '<span class="muted">Not given</span>'}</dd>
                <dt>Source</dt><dd>${U.esc(row.source || '—')}</dd>
                <dt>Received</dt><dd>${U.esc(U.fmtDateTime(row.receivedAt))}</dd>
            </dl>
        </article>`;
    }

    function recordCardHtml() {
        const sel = (id, label, options, value) => `
            <div class="field">
                <label for="${id}">${U.esc(label)}</label>
                <select id="${id}">
                    ${options.map((o) => `<option value="${U.esc(o.value)}" ${String(value || '') === String(o.value) ? 'selected' : ''}>${U.esc(o.label)}</option>`).join('')}
                </select>
            </div>`;

        return `
        <article class="card anim-item">
            <div class="card__head"><h3>Handling</h3></div>
            <div class="form-grid">
                ${sel('fStatus', 'Status', STATUS, row.status)}
                ${sel('fPriority', 'Priority', PRIORITY, row.priority || 'normal')}
                ${sel('fOwner', 'Assigned to',
                    [{ value: '', label: 'Unassigned' }].concat(users.map((u) => ({ value: u.id, label: u.name }))),
                    row.assignedTo)}
                ${sel('fDept', 'Department',
                    [{ value: '', label: 'General / not specific' }].concat(departments.map((d) => ({ value: d.id, label: d.name }))),
                    row.departmentId)}
            </div>
            <div class="divider"></div>
            <div class="row gap-2 wrap">
                ${row.status === 'spam'
                    ? '<button type="button" class="btn btn--ghost btn--sm" data-act="notspam"><i class="fa-solid fa-inbox"></i> Not spam</button>'
                    : '<button type="button" class="btn btn--ghost btn--sm" data-act="spam"><i class="fa-solid fa-ban"></i> Mark spam</button>'}
                <button type="button" class="btn btn--danger btn--sm" data-act="remove">
                    <i class="fa-solid fa-trash"></i> Delete</button>
            </div>
        </article>`;
    }

    function relatedCardHtml() {
        /* Matched on email or phone — the same person writing twice from the
           same form does not get a shared id until Phase 2. */
        const related = store.allSync('enquiries')
            .filter((e) => e.id !== row.id
                && ((row.email && e.email === row.email) || (row.phone && e.phone === row.phone)))
            .sort((a, b) => new Date(b.receivedAt || 0) - new Date(a.receivedAt || 0));

        return `
        <article class="card anim-item">
            <div class="card__head"><h3>From the same person</h3></div>
            ${related.length ? `
                <div class="col gap-2">
                    ${related.map((e) => `
                        <a class="row gap-2 row-between" href="enquiry-view?id=${U.esc(e.id)}"
                           style="padding:var(--s3);border:1px solid var(--hairline);border-radius:var(--radius-sm)">
                            <span class="col">
                                <span class="text-sm">${U.esc(e.subject || '(no subject)')}</span>
                                <span class="text-xs muted">${U.esc(U.ago(e.receivedAt))}</span>
                            </span>
                            ${statusTag(e.status)}
                        </a>`).join('')}
                </div>`
                : `<p class="text-sm muted">Matched on email and phone — nothing else from
                   ${U.esc(row.name)}.</p>`}
        </article>`;
    }

    function statusTag(status) {
        const s = STATUS.find((x) => x.value === status) || { tone: 'off', label: status || 'Unknown' };
        return `<span class="tag ${s.tone}">${U.esc(s.label)}</span>`;
    }

    /* ---------- wiring ---------- */

    function wire() {
        const view = document.getElementById('view');
        const head = document.getElementById('pageHead');

        [head, view].forEach((scope) => scope.querySelectorAll('[data-act]').forEach((el) =>
            el.addEventListener('click', () => ACTIONS[el.dataset.act]())));

        view.querySelectorAll('[data-copy]').forEach((el) =>
            el.addEventListener('click', async (e) => {
                e.preventDefault();
                const ok = await U.copy(el.dataset.copy);
                if (ok) toast.success('Copied');
                else toast.error('Could not copy', { body: 'Select the text and copy manually.' });
            }));

        bindField('fStatus', 'status', 'Status updated');
        bindField('fPriority', 'priority', 'Priority updated');
        bindField('fOwner', 'assignedTo', 'Assignment updated');
        bindField('fDept', 'departmentId', 'Department updated');

        const pick = document.getElementById('templatePick');
        pick.addEventListener('change', () => {
            const t = TEMPLATES.find((x) => x.id === pick.value);
            pick.value = '';
            if (!t) return;

            const box = document.getElementById('replyBody');
            const dept = departments.find((d) => d.id === row.departmentId);
            const filled = t.body
                .replace(/\{\{name\}\}/g, row.name)
                .replace(/\{\{department\}\}/g, dept ? dept.name : 'relevant')
                .replace(/\{\{phone\}\}/g, phone)
                .replace(/\{\{me\}\}/g, ME.name);

            /* Never clobber something already typed. */
            box.value = box.value.trim() ? `${box.value.trim()}\n\n${filled}` : filled;
            box.focus();
        });
    }

    function bindField(elId, key, message) {
        const el = document.getElementById(elId);
        el.addEventListener('change', async () => {
            const before = row[key];
            row = await store.update('enquiries', row.id, { [key]: el.value });
            toast.success(message, {
                undo: async () => {
                    row = await store.update('enquiries', row.id, { [key]: before });
                    toast.success('Reverted');
                    render();
                },
            });
            /* Status and owner both show elsewhere on the screen. */
            render();
        });
    }

    const ACTIONS = {
        async send() {
            const box = document.getElementById('replyBody');
            const body = box.value.trim();
            if (!body) {
                toast.warning('Nothing to send', { body: 'Write a reply first.' });
                box.focus();
                return;
            }

            const replies = (row.replies || []).concat([{
                by: ME.name, at: new Date().toISOString(), body,
            }]);

            row = await store.update('enquiries', row.id, {
                replies,
                /* Replying to something filed as spam should not quietly
                   un-file it — that is a deliberate act, not a side effect. */
                status: row.status === 'spam' ? 'spam' : 'replied',
                assignedTo: row.assignedTo || ME.id,
            });

            box.value = '';
            toast.success('Reply recorded', {
                body: row.email
                    ? `Phase 2 emails this to ${row.email}.`
                    : 'No email on file — this is a record of a phone reply.',
            });
            render();
        },

        async note() {
            const body = await modal.open({
                title: 'Internal note',
                icon: 'fa-note-sticky',
                subtitle: 'Only visible in the panel. Never sent to the sender.',
                html: `
                    <div class="field field--wide">
                        <label for="noteBody">Note</label>
                        <textarea id="noteBody" rows="4" placeholder="Context for whoever picks this up next…"></textarea>
                    </div>`,
                footer: `
                    <button type="button" class="btn btn--ghost" data-cancel>Cancel</button>
                    <button type="button" class="btn btn--primary" data-ok>Add note</button>`,
                onMount(panel, close) {
                    panel.querySelector('#noteBody').focus();
                    panel.querySelector('[data-cancel]').addEventListener('click', () => close(undefined));
                    panel.querySelector('[data-ok]').addEventListener('click', () =>
                        close(panel.querySelector('#noteBody').value.trim()));
                },
            });

            if (!body) return;

            const internalNotes = (row.internalNotes || []).concat([{
                by: ME.name, at: new Date().toISOString(), body,
            }]);
            row = await store.update('enquiries', row.id, { internalNotes });
            toast.success('Note added');
            render();
        },

        close() { return setStatus('closed', 'Enquiry closed'); },
        reopen() { return setStatus('new', 'Enquiry reopened'); },
        spam() { return setStatus('spam', 'Marked as spam'); },
        notspam() { return setStatus('new', 'Moved back to the inbox'); },

        async remove() {
            const ok = await confirmDialog({
                title: 'Delete this enquiry?',
                body: `${row.name} — "${row.subject}". The message, its replies and its notes go with it.`,
                danger: true,
                confirmLabel: 'Delete',
            });
            if (!ok) return;

            await store.remove('enquiries', row.id);
            /* No Undo offered here: this screen has nothing left to render, so
               it navigates away. The inbox's own delete does offer Undo. */
            toast.success('Enquiry deleted');
            window.location.href = 'enquiries';
        },
    };

    async function setStatus(status, message) {
        const before = row.status;
        row = await store.update('enquiries', row.id, { status });
        toast.success(message, {
            undo: async () => {
                row = await store.update('enquiries', row.id, { status: before });
                toast.success('Reverted');
                render();
            },
        });
        render();
    }
}());
