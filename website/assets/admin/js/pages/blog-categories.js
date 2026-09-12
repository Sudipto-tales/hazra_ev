/* Categories & tags.
   Two lists side by side. Deleting a term that posts still reference is
   blocked and offers a reassignment, because an orphaned categoryId is how a
   blog index ends up with an "undefined" filter chip. */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Categories & Tags' }],
            title: 'Categories',
            accent: '& Tags',
            sub: 'Categories drive the filter row on the blog index. Tags drive the related-posts picker at the foot of an article.',
        });

        await render();
    }

    async function render() {
        const rows = await store.all('categories');
        const posts = store.allSync('posts');
        const count = (id, key) => posts.filter((p) => (key === 'tags'
            ? (p.tags || []).includes(id)
            : p.categoryId === id)).length;

        const panel = (type, title, icon, hint) => {
            const items = rows.filter((r) => r.type === type)
                .sort((a, b) => (a.order || 0) - (b.order || 0));
            return `
            <article class="card c6 anim-item">
                <div class="card__head">
                    <div>
                        <h3><i class="fa-solid ${icon}" style="color:var(--brand-red)"></i> ${title}</h3>
                        <p>${hint}</p>
                    </div>
                    <button type="button" class="btn btn--soft btn--sm" data-add="${type}">
                        <i class="fa-solid fa-plus"></i> Add</button>
                </div>
                ${items.length ? `
                <ul class="col gap-2">
                    ${items.map((r) => {
                        const n = count(r.id, type === 'tag' ? 'tags' : 'category');
                        return `
                        <li class="row" style="padding:var(--s3);border:1px solid var(--hairline);border-radius:var(--radius-sm)">
                            <span class="grow" style="min-width:0">
                                <b class="text-sm">${U.esc(r.name)}</b>
                                <span class="cell-sub">${U.esc(r.description || `/${r.id}`)}</span>
                            </span>
                            <span class="pill">${n} post${n === 1 ? '' : 's'}</span>
                            <button type="button" class="icon-btn" data-edit="${U.esc(r.id)}" aria-label="Edit ${U.esc(r.name)}">
                                <i class="fa-solid fa-pen"></i></button>
                            <button type="button" class="icon-btn" data-del="${U.esc(r.id)}" aria-label="Delete ${U.esc(r.name)}">
                                <i class="fa-solid fa-trash-can"></i></button>
                        </li>`;
                    }).join('')}
                </ul>` : `
                <div class="empty" style="padding:var(--s10) var(--s4)">
                    <div class="empty__art"><i class="fa-solid ${icon}"></i></div>
                    <h3>None yet</h3>
                    <p>Add the first one.</p>
                </div>`}
            </article>`;
        };

        document.getElementById('view').innerHTML = `
            <div class="bento">
                ${panel('category', 'Categories', 'fa-folder', 'One per post. Shown as a filter chip on the blog index.')}
                ${panel('tag', 'Tags', 'fa-tag', 'Many per post. Used to pick related articles.')}
            </div>`;
        U.stagger(document.getElementById('view'));

        document.querySelectorAll('[data-add]').forEach((b) =>
            b.addEventListener('click', () => edit(null, b.dataset.add)));
        document.querySelectorAll('[data-edit]').forEach((b) =>
            b.addEventListener('click', () => edit(rows.find((r) => r.id === b.dataset.edit))));
        document.querySelectorAll('[data-del]').forEach((b) =>
            b.addEventListener('click', () => remove(rows.find((r) => r.id === b.dataset.del))));
    }

    async function edit(record, type) {
        const kind = record ? record.type : type;
        const data = await formLib.editModal({
            title: record ? `Edit ${record.name}` : `Add a ${kind}`,
            icon: kind === 'tag' ? 'fa-tag' : 'fa-folder',
            record,
            defaults: { type: kind },
            html: F.section({
                fields: [
                    F.text({ name: 'name', label: 'Name', required: true, placeholder: kind === 'tag' ? 'Prevention' : 'Cardiology' }),
                    F.text({ name: 'id', label: 'Slug', required: true, rule: 'slug' }),
                    F.textarea({ name: 'description', label: 'Description', rows: 2,
                        hint: 'Shown under the heading on a category archive page.' }),
                    F.number({ name: 'order', label: 'Display order', min: 1 }),
                ],
            }),
        });
        if (!data) return;

        try {
            if (record) {
                await store.update('categories', record.id, Object.assign({ type: kind }, data));
                toast.success(`${data.name} updated`);
            } else {
                await store.create('categories', Object.assign({ type: kind, status: 'published' }, data));
                toast.success(`${data.name} added`);
            }
            render();
        } catch (err) {
            toast.error(err.message || 'Could not save');
        }
    }

    async function remove(record) {
        const posts = store.allSync('posts');
        const using = posts.filter((p) => (record.type === 'tag'
            ? (p.tags || []).includes(record.id)
            : p.categoryId === record.id));

        if (using.length) {
            const others = store.allSync('categories')
                .filter((c) => c.type === record.type && c.id !== record.id);

            const choice = await formLib.editModal({
                title: `${record.name} is in use`,
                subtitle: `${using.length} post${using.length === 1 ? '' : 's'} reference it. Move them somewhere before deleting.`,
                icon: 'fa-link-slash',
                saveLabel: 'Reassign and delete',
                html: `
                    ${F.select({
                        name: 'target', label: 'Move those posts to', required: true,
                        options: others.map((c) => ({ value: c.id, label: c.name })),
                    })}
                    <div class="modal__deps"><ul>${using.slice(0, 6)
                        .map((p) => `<li>${U.esc(p.title)}</li>`).join('')}${
                        using.length > 6 ? `<li>and ${using.length - 6} more</li>` : ''}</ul></div>`,
            });
            if (!choice) return;

            for (const p of using) {
                if (record.type === 'tag') {
                    const tags = (p.tags || []).filter((t) => t !== record.id);
                    if (!tags.includes(choice.target)) tags.push(choice.target);
                    await store.update('posts', p.id, { tags });
                } else {
                    await store.update('posts', p.id, { categoryId: choice.target });
                }
            }
            await store.remove('categories', record.id);
            toast.success(`${record.name} deleted`, { body: `${using.length} post(s) reassigned.` });
            render();
            return;
        }

        const ok = await window.TMH.confirm({
            title: `Delete ${record.name}?`,
            body: 'No posts reference it, so nothing on the site changes.',
            danger: true, confirmLabel: 'Delete',
        });
        if (!ok) return;

        const removed = await store.remove('categories', record.id);
        toast.success(`${record.name} deleted`, {
            undo: async () => {
                await store.restore('categories', removed.row, removed.index);
                toast.success('Restored');
                render();
            },
        });
        render();
    }
}());
