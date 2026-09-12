/* =========================================================
   Counters & Numbers.

   Every animated figure on the public site in one table, so
   "640 beds" is changed once rather than hunted through
   the home page, the about page and twelve department pages.

   Values are edited inline: click, type, tab out, toast. A
   modal for changing one number is friction with no payoff.
   ========================================================= */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    const SCOPES = [
        { value: 'global', label: 'Site-wide' },
        { value: 'home', label: 'Home page' },
        { value: 'about', label: 'About page' },
        { value: 'department', label: 'Department page' },
    ];

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Pages' }, { label: 'Counters & Numbers' }],
            title: 'Counters',
            accent: '& Numbers',
            sub: 'Every animated figure on the website. Department counters are the same rows as that department’s Counters tab.',
            actions: '<button type="button" class="btn btn--primary" id="addBtn"><i class="fa-solid fa-plus"></i> Add counter</button>',
        });

        document.getElementById('addBtn').addEventListener('click', () => edit(null));
        await render();
    }

    async function render() {
        const rows = (await store.all('counters')).sort((a, b) => (a.order || 0) - (b.order || 0));
        const departments = store.allSync('departments');

        const group = (scope) => {
            const items = rows.filter((r) => r.scope === scope);
            const label = SCOPES.find((s) => s.value === scope).label;
            if (!items.length) return '';

            return `
            <article class="card card--flush c12 anim-item mb-4">
                <div class="card__head">
                    <div>
                        <h3>${U.esc(label)}</h3>
                        <p>${scope === 'department'
                            ? 'Fixed at four per department — the layout has four columns.'
                            : 'Click a value to edit it in place.'}</p>
                    </div>
                    <span class="pill">${items.length} counter${items.length === 1 ? '' : 's'}</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr>
                            <th style="width:44px"></th>
                            <th>Label</th>
                            <th style="width:120px">Value</th>
                            <th style="width:90px">Suffix</th>
                            <th>Note</th>
                            ${scope === 'department' ? '<th style="width:150px">Department</th>' : ''}
                            <th style="width:96px"></th>
                        </tr></thead>
                        <tbody>
                            ${items.map((r) => {
                                const dept = departments.find((d) => d.id === r.departmentId);
                                return `
                                <tr data-id="${U.esc(r.id)}">
                                    <td data-label=""><span class="stat__icon red" style="width:30px;height:30px;font-size:12px;border-radius:var(--radius-xs)">
                                        <i class="fa-solid ${U.esc(r.icon || 'fa-hashtag')}"></i></span></td>
                                    <td data-label="Label"><span class="cell-main">${U.esc(r.label)}</span>
                                        <span class="cell-sub">${U.esc(r.key)}</span></td>
                                    <td data-label="Value">
                                        <input type="text" data-inline="value" value="${U.esc(r.value)}"
                                            aria-label="Value for ${U.esc(r.label)}"
                                            style="width:100%;padding:5px 8px;border:1px solid transparent;border-radius:var(--radius-xs);background:transparent;font-weight:600">
                                    </td>
                                    <td data-label="Suffix">
                                        <input type="text" data-inline="suffix" value="${U.esc(r.suffix || '')}"
                                            aria-label="Suffix for ${U.esc(r.label)}"
                                            style="width:100%;padding:5px 8px;border:1px solid transparent;border-radius:var(--radius-xs);background:transparent">
                                    </td>
                                    <td data-label="Note">
                                        <input type="text" data-inline="note" value="${U.esc(r.note || '')}"
                                            aria-label="Note for ${U.esc(r.label)}"
                                            style="width:100%;padding:5px 8px;border:1px solid transparent;border-radius:var(--radius-xs);background:transparent;color:var(--text-mid)">
                                    </td>
                                    ${scope === 'department' ? `<td data-label="Department">${dept
                                        ? `<a href="department-form?id=${U.esc(dept.id)}&tab=tab-stats">${U.esc(dept.name)}</a>`
                                        : '<span class="tag warn">Orphaned</span>'}</td>` : ''}
                                    <td class="cell-actions" data-label="">
                                        <button type="button" class="icon-btn" data-edit="${U.esc(r.id)}" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                                        <button type="button" class="icon-btn" data-del="${U.esc(r.id)}" aria-label="Delete"><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </article>`;
        };

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-arrow-up-9-1', 'red', rows.length, 'Counters', 'Across the whole site'],
                ['fa-globe', 'navy', rows.filter((r) => r.scope === 'global').length, 'Site-wide', 'Reused in header, footer and about'],
                ['fa-house', 'blue', rows.filter((r) => r.scope === 'home' || r.scope === 'about').length, 'Page counters', 'Home and about bands'],
                ['fa-hospital', 'magenta', rows.filter((r) => r.scope === 'department').length, 'Department counters', 'Four per department page'],
            ])}
            <div class="bento">
                ${SCOPES.map((s) => group(s.value)).join('')}
            </div>`;
        U.stagger(document.getElementById('view'));

        wireInline(rows);

        document.querySelectorAll('[data-edit]').forEach((b) =>
            b.addEventListener('click', () => edit(rows.find((r) => r.id === b.dataset.edit))));
        document.querySelectorAll('[data-del]').forEach((b) =>
            b.addEventListener('click', () => remove(rows.find((r) => r.id === b.dataset.del))));
    }

    /* Inline editing: save on blur, revert on Escape, and only write when the
       value actually changed — a tab through the table should not fire twenty
       toasts. */
    function wireInline(rows) {
        document.querySelectorAll('[data-inline]').forEach((input) => {
            const id = input.closest('tr').dataset.id;
            const key = input.dataset.inline;
            const original = input.value;

            input.addEventListener('focus', () => {
                input.style.borderColor = 'var(--brand-blue)';
                input.style.background = 'var(--surface)';
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    input.value = original;
                    input.blur();
                }
                if (e.key === 'Enter') input.blur();
            });

            input.addEventListener('blur', async () => {
                input.style.borderColor = 'transparent';
                input.style.background = 'transparent';
                if (input.value === original) return;

                const row = rows.find((r) => r.id === id);
                const value = key === 'value' && input.value !== '' && !isNaN(Number(input.value))
                    ? Number(input.value) : input.value;
                await store.update('counters', id, { [key]: value });
                toast.success(`${row.label} updated`, {
                    body: `${key} is now ${input.value || '(empty)'}`,
                    id: `counter-${id}`,
                });
                render();
            });
        });
    }

    async function edit(record) {
        const departments = store.allSync('departments');

        const data = await formLib.editModal({
            title: record ? `Edit ${record.label}` : 'Add a counter',
            icon: 'fa-arrow-up-9-1',
            record,
            defaults: { scope: 'home', suffix: '' },
            html: F.section({
                fields: [
                    F.text({ name: 'label', label: 'Label', required: true, placeholder: 'Beds' }),
                    F.text({ name: 'key', label: 'Key', required: true, rule: 'slug',
                        hint: 'How the template refers to this number.' }),
                    F.icon({ name: 'icon', label: 'Icon', value: record && record.icon }),
                    F.text({ name: 'value', label: 'Value', required: true, placeholder: '210' }),
                    F.text({ name: 'suffix', label: 'Suffix', placeholder: '+' }),
                    F.text({ name: 'note', label: 'Note', wide: true, placeholder: 'Across 20 units' }),
                    F.select({ name: 'scope', label: 'Where it appears', options: SCOPES }),
                    F.select({
                        name: 'departmentId', label: 'Department', placeholderOption: 'Not department-scoped',
                        options: departments.map((d) => ({ value: d.id, label: d.name })),
                        hint: 'Only used when the scope above is “Department page”.',
                    }),
                ],
            }),
        });
        if (!data) return;

        if (data.scope === 'department' && !data.departmentId) {
            toast.error('Pick a department', { body: 'A department-scoped counter has to belong to one.' });
            return;
        }

        if (record) {
            await store.update('counters', record.id, data);
            toast.success(`${data.label} updated`);
        } else {
            await store.create('counters', Object.assign({ status: 'published' }, data));
            toast.success(`${data.label} added`);
        }
        render();
    }

    async function remove(record) {
        const where = record.scope === 'department'
            ? 'that department page'
            : `the ${record.scope === 'global' ? 'header, footer and about page' : `${record.scope} page`}`;

        const ok = await window.TMH.confirm({
            title: `Delete “${record.label}”?`,
            body: `The number disappears from ${where}. If the layout expects four counters, it will render three.`,
            danger: true,
            confirmLabel: 'Delete counter',
        });
        if (!ok) return;

        const removed = await store.remove('counters', record.id);
        toast.success(`${record.label} deleted`, {
            undo: async () => {
                await store.restore('counters', removed.row, removed.index);
                toast.success('Restored');
                render();
            },
        });
        render();
    }
}());
