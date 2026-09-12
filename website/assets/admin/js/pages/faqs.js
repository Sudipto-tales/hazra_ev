/* =========================================================
   FAQs.

   The screen looks like the accordion it feeds, because the
   thing that goes wrong with an FAQ list is not a bad answer —
   it is twelve questions in an order nobody would ask them in.
   Editing happens in place: expand the question, fix the
   answer, save. A separate form page for two fields would hide
   the ordering, which is the part worth seeing.

   Order is per group. Dragging a Contact question past a Home
   one is not possible, because the public page renders one
   group at a time.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast, editor,
    } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    /* [stored value, label, icon, subtitle]. The column holds the lower-case
       key the public site reads; the heading is the readable half. */
    const GROUPS = [
        ['home', 'Home', 'fa-house', 'The accordion on the home page.'],
        ['contact', 'Contact', 'fa-phone', 'Shown under the contact form.'],
        ['department', 'Department', 'fa-hospital', 'Appears on the department page it is tagged with.'],
    ];

    /* One open editor at a time. Two half-finished answers on screen is a
       way to lose one of them. */
    let openId = null;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'FAQs' }],
            title: 'FAQs',
            sub: 'Grouped exactly as the public accordions are. Expand a question to edit it; drag to reorder within a group.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}#faq" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <button type="button" class="btn btn--primary" id="addBtn">
                    <i class="fa-solid fa-plus"></i> Add question</button>`,
        });

        document.getElementById('addBtn').addEventListener('click', () => add('Home'));
        await render();
    }

    /* Group order first, then the row's own order — so a drag inside one
       group can be persisted as one flat sequence without disturbing the
       others. */
    function sorted(rows) {
        const rank = (g) => {
            const i = GROUPS.findIndex(([key]) => key === g);
            return i === -1 ? GROUPS.length : i;
        };
        return rows.slice().sort((a, b) =>
            rank(a.group) - rank(b.group) || (a.order || 0) - (b.order || 0));
    }

    async function render() {
        const rows = sorted(await store.all('faqs'));
        const live = rows.filter((r) => r.status === 'published');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-circle-question', 'red', rows.length, 'Questions', `${live.length} published`],
                ['fa-house', 'navy', rows.filter((r) => r.group === 'home').length, 'On the home page', 'The main accordion'],
                ['fa-phone', 'blue', rows.filter((r) => r.group === 'contact').length, 'On contact', 'Under the enquiry form'],
                ['fa-hospital', 'magenta', rows.filter((r) => r.group === 'department').length, 'Department-specific', 'Tagged to one department'],
            ])}
            <div class="bento">
                ${GROUPS.map(([key, label, icon, sub]) => groupHtml(key, label, icon, sub, rows.filter((r) => r.group === key))).join('')}
            </div>`;

        U.stagger(document.getElementById('view'));
        wire(rows);
    }

    function groupHtml(key, label, icon, sub, list) {
        return `
        <article class="card card--flush c12 anim-item mb-4">
            <div class="card__head">
                <div>
                    <h3><i class="fa-solid ${icon}" style="color:var(--brand-red)"></i> ${U.esc(label)}</h3>
                    <p>${U.esc(sub)}</p>
                </div>
                <span class="pill">${list.length} question${list.length === 1 ? '' : 's'}</span>
                <button type="button" class="btn btn--ghost btn--sm" data-add-group="${U.esc(key)}">
                    <i class="fa-solid fa-plus"></i> Add here</button>
            </div>

            ${list.length ? `
                <div class="faq-list" data-group="${U.esc(key)}">
                    ${list.map(itemHtml).join('')}
                </div>` : `
                <div class="empty empty--sm">
                    <p>Nothing in this group. The public accordion renders nothing at all when it is empty.</p>
                </div>`}
        </article>`;
    }

    function itemHtml(row) {
        const dept = row.departmentId
            ? store.allSync('departments').find((d) => d.id === row.departmentId)
            : null;
        const isOpen = openId === row.id;

        return `
        <section class="faq${isOpen ? ' is-open' : ''}${row.status !== 'published' ? ' faq--muted' : ''}" data-id="${U.esc(row.id)}">
            <div class="faq__head" role="button" tabindex="0" aria-expanded="${isOpen}">
                <span class="drag-handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                <span class="faq__q grow">${U.esc(row.question)}</span>
                ${dept ? `<span class="pill pill--soft">${U.esc(dept.name)}</span>` : ''}
                ${row.status !== 'published' ? U.statusTag(row.status) : ''}
                <i class="fa-solid fa-chevron-down faq__chev" aria-hidden="true"></i>
            </div>
            <div class="faq__body">
                ${isOpen ? '' : `<div class="faq__preview">${U.esc(U.plain(row.answer)) || '<span class="muted">No answer written yet.</span>'}</div>`}
            </div>
        </section>`;
    }

    /* ---------------------------------------------------------
       Wiring
       --------------------------------------------------------- */
    function wire(rows) {
        const view = document.getElementById('view');
        const byId = (id) => rows.find((r) => r.id === id);

        view.querySelectorAll('[data-add-group]').forEach((b) =>
            b.addEventListener('click', () => add(b.dataset.addGroup)));

        view.querySelectorAll('.faq__head').forEach((head) => {
            const card = head.closest('.faq');
            const open = () => toggleOpen(byId(card.dataset.id));
            head.addEventListener('click', (e) => {
                if (e.target.closest('.drag-handle')) return;
                open();
            });
            head.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    open();
                }
            });
        });

        /* Reorder is per group, but persisted as one sequence: the ids of
           every other group are kept in their existing order around the
           group that moved, so store.reorder() never renumbers a group it
           was not asked about. */
        view.querySelectorAll('.faq-list').forEach((list) => {
            U.sortable(list, '.faq', async (idsInGroup) => {
                const group = list.dataset.group;
                const queue = idsInGroup.slice();
                const full = sorted(rows).map((r) => (r.group === group ? queue.shift() : r.id));
                await store.reorder('faqs', full);
                toast.success('Order saved', { body: `The ${group} accordion now reads in this sequence.`, id: 'faq-order' });
                render();
            });
        });

        if (openId) {
            const card = view.querySelector(`.faq[data-id="${CSS.escape(openId)}"]`);
            if (card) mountEditor(card, byId(openId));
        }
    }

    function toggleOpen(row) {
        if (!row) return;
        openId = openId === row.id ? null : row.id;
        render();
    }

    /* ---------------------------------------------------------
       The inline editor
       --------------------------------------------------------- */
    function mountEditor(card, row) {
        const departments = store.allSync('departments');
        const body = card.querySelector('.faq__body');

        body.innerHTML = `
            <form class="faq__form" novalidate>
                ${F.section({
                    fields: [
                        F.text({
                            name: 'question', label: 'Question', required: true, wide: true,
                            placeholder: 'Do I need an appointment for the emergency department?',
                        }),
                        F.editor({
                            name: 'answer', label: 'Answer', required: true,
                            placeholder: 'Answer it the way you would at the reception desk.',
                            hint: 'Short paragraphs. Links and lists are allowed; headings are not, because the accordion supplies its own.',
                        }),
                        F.select({
                            name: 'group', label: 'Group',
                            options: GROUPS.map(([key, label]) => ({ value: key, label })),
                            hint: 'Which public accordion this belongs to.',
                        }),
                        F.select({
                            name: 'departmentId', label: 'Department', placeholderOption: 'Not department-specific',
                            options: departments.map((d) => ({ value: d.id, label: d.name })),
                            hint: 'Only read when the group above is Department.',
                        }),
                        F.status({}),
                    ],
                })}
                <div class="faq__actions">
                    <button type="button" class="btn btn--primary btn--sm" data-save>
                        <i class="fa-solid fa-check"></i> Save answer</button>
                    <button type="button" class="btn btn--ghost btn--sm" data-cancel>Cancel</button>
                    <span class="grow"></span>
                    <button type="button" class="btn btn--ghost btn--sm text-bad" data-delete>
                        <i class="fa-solid fa-trash-can"></i> Delete</button>
                </div>
            </form>`;

        /* Upgrade before bind: editor.upgrade() rebuilds the pad from its own
           innerHTML, so an answer written in first would be thrown away. */
        const form = body.querySelector('form');
        if (editor) editor.upgradeAll(form);
        formLib.bind(form, row);

        form.querySelector('[data-save]').addEventListener('click', () => save(form, row));
        form.querySelector('[data-cancel]').addEventListener('click', () => {
            openId = null;
            render();
        });
        form.querySelector('[data-delete]').addEventListener('click', () => remove(row));

        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    async function save(form, row) {
        const result = formLib.validate(form, {});
        if (!result.ok) {
            formLib.reportInvalid(form, result);
            return;
        }

        const data = formLib.collect(form);

        /* The editor hands back markup; an empty pad still returns a <br> or
           an empty <p>, so emptiness is judged on the text. */
        if (!U.plain(data.answer).trim()) {
            toast.error('The answer is empty', { body: 'A question with no answer renders as a blank accordion row.' });
            return;
        }

        /* Moving between groups puts it at the end of the new one — anywhere
           else would be a guess about where the editor wanted it. */
        const patch = row.group === data.group
            ? data
            : Object.assign({}, data, { order: nextOrder(data.group) });

        await store.update('faqs', row.id, patch);
        openId = null;
        toast.success('Answer saved', {
            body: row.group === data.group ? '' : `Moved to the end of the ${data.group} group.`,
        });
        render();
    }

    function nextOrder(group) {
        const inGroup = store.allSync('faqs').filter((r) => r.group === group);
        return inGroup.length ? Math.max(...inGroup.map((r) => r.order || 0)) + 1 : 1;
    }

    async function add(group) {
        const row = await store.create('faqs', {
            question: 'New question',
            answer: '',
            group: group || 'Home',
            departmentId: '',
            order: nextOrder(group || 'Home'),
            status: 'draft',
        });
        openId = row.id;
        toast.success('Question added', { body: 'It stays a draft until you publish it.' });
        render();
    }

    async function remove(row) {
        const ok = await window.TMH.confirm({
            title: 'Delete this question?',
            body: `“${U.plain(row.question).slice(0, 90)}” disappears from the ${row.group} accordion.`,
            danger: true,
            confirmLabel: 'Delete question',
        });
        if (!ok) return;

        const removed = await store.remove('faqs', row.id);
        openId = null;
        toast.success('Question deleted', {
            undo: async () => {
                await store.restore('faqs', removed.row, removed.index);
                toast.success('Restored');
                render();
            },
        });
        render();
    }
}());
