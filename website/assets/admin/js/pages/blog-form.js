/* =========================================================
   Blog post — the writing pad.

   Two columns: the pad on the left, meta rail on the right.
   The pad itself is core/editor.js; everything here is the
   surrounding workflow — autosaved drafts, read-time estimate,
   the single-featured rule, and a live search-result preview.
   ========================================================= */
(function () {
    'use strict';

    const { util: U, store, fields: F, form: formLib, layout, toast, media, editor } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = window.TMH.api.base;

    const id = U.param('id');
    const isEdit = !!id;
    let record = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        record = isEdit ? await store.get('posts', id) : null;

        if (isEdit && !record) {
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-file-circle-xmark"></i></div>
                    <h3>That post no longer exists</h3>
                    <a class="btn btn--primary mt-4" href="blog">Back to the list</a>
                </div></article>`;
            return;
        }

        const categories = (await store.all('categories'));
        const authors = await store.all('doctors');

        paintHead();
        document.getElementById('view').innerHTML = markup(categories, authors);

        editor.upgradeAll(document);
        F.wirePreviews(document);

        ctrl = formLib.create({
            el: '#postForm',
            bar: '#formBar',
            autosaveKey: `post:${id || 'new'}`,
            onCancel: () => { location.href = 'blog'; },
            onSave: save,
        });

        ctrl.bind(record || defaults());
        media.wire(document);

        document.getElementById('publishLabel').textContent =
            record && record.status === 'published' ? 'Update & republish' : 'Publish';

        wireSlug();
        wireReadTime();
        wireSerpPreview();
        offerDraftRestore();
    }

    function defaults() {
        return {
            status: 'draft', featured: false, views: 0,
            readMinutes: 4, tags: [],
            publishedAt: new Date().toISOString(),
        };
    }

    function paintHead() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Content' },
                { label: 'Blog & News', href: 'blog' },
                { label: isEdit ? 'Edit post' : 'New post' },
            ],
            title: isEdit ? 'Edit' : 'Write a',
            accent: 'Post',
            actions: '<a class="btn btn--ghost" href="blog"><i class="fa-solid fa-arrow-left"></i> Back to posts</a>',
        });
    }

    function markup(categories, authors) {
        const cats = categories.filter((c) => c.type === 'category');
        const tags = categories.filter((c) => c.type === 'tag');

        return `
        <form id="postForm" novalidate>
            <div class="split">
                <article class="card">
                    <div class="field field--wide">
                        <label for="title" class="sr-only">Title</label>
                        <input type="text" id="title" name="title" required placeholder="Your headline goes here"
                            style="font-family:var(--font-head);font-size:1.6rem;font-weight:600;border:0;padding:var(--s2) 0;background:transparent">
                    </div>

                    <div class="field field--wide">
                        <label for="excerpt">Excerpt <span class="field__req">*</span></label>
                        <textarea id="excerpt" name="excerpt" required rows="2" data-max="180"
                            placeholder="The sentence that appears on the listing card and in search results."></textarea>
                    </div>

                    ${F.editor({ name: 'body', placeholder: 'Start writing. Type / for blocks, ## for a heading, - for a list.' })}

                    ${F.bar()}
                </article>

                <aside class="split__rail">
                    <article class="card">
                        <div class="card__head"><h3>Publishing</h3></div>
                        <div class="col gap-4">
                            ${F.select({ name: 'status', label: 'Status', options: [
                                { value: 'draft', label: 'Draft' },
                                { value: 'published', label: 'Published' },
                                { value: 'hidden', label: 'Hidden' },
                                { value: 'scheduled', label: 'Scheduled' },
                            ] })}
                            ${F.text({ name: 'publishedAt', type: 'date', label: 'Publish date' })}
                            ${F.toggle({ name: 'featured', label: 'Featured post',
                                hint: 'Only one post can be featured — it is the article /blog/{slug} renders in full.' })}
                        </div>
                    </article>

                    <article class="card">
                        <div class="card__head"><h3>Details</h3></div>
                        <div class="col gap-4">
                            ${F.media({ name: 'coverImage', label: 'Cover image', required: true })}
                            ${F.select({ name: 'categoryId', label: 'Category', required: true,
                                placeholderOption: 'Choose a category',
                                options: cats.map((c) => ({ value: c.id, label: c.name })) })}
                            ${F.select({ name: 'tags', label: 'Tags', multiple: true,
                                placeholder: 'Choose tags', searchPlaceholder: 'Search tags',
                                options: tags.map((t) => ({ value: t.id, label: t.name })),
                                hint: 'Tags drive the related-posts rail at the foot of the article.' })}
                            ${F.select({ name: 'authorId', label: 'Author', required: true,
                                placeholderOption: 'Choose an author',
                                options: authors.map((a) => ({ value: a.id, label: `${a.name} — ${a.role}` })) })}
                            ${F.text({ name: 'id', label: 'URL slug', required: true, rule: 'slug' })}
                            ${F.number({ name: 'readMinutes', label: 'Read time (minutes)', min: 1,
                                hint: '<span id="readHint">Estimated from the body — override if you disagree.</span>' })}
                            ${F.text({ name: 'heading', label: 'Article headline', wide: true,
                                hint: 'Optional title-case variant for the article banner. The listing card always uses the title above.' })}
                        </div>
                    </article>

                    <article class="card">
                        <div class="card__head"><h3>Search appearance</h3></div>
                        <div id="serp" style="border:1px solid var(--hairline);border-radius:var(--radius-sm);padding:var(--s3);margin-bottom:var(--s4)"></div>
                        <div class="col gap-4">
                            ${F.text({ name: 'metaTitle', label: 'Meta title', max: 60, requiredToPublish: true })}
                            ${F.textarea({ name: 'metaDescription', label: 'Meta description', max: 155, rows: 3 })}
                        </div>
                    </article>

                    ${isEdit ? `
                    <article class="card card--quiet">
                        <dl class="kv">
                            <dt>Reads</dt><dd>${U.num(record.views)}</dd>
                            <dt>Last edited</dt><dd>${U.esc(U.ago(record.updatedAt))}</dd>
                        </dl>
                        <div class="card__foot">
                            <button type="button" class="btn btn--ghost btn--sm" id="delBtn">
                                <i class="fa-solid fa-trash-can"></i> Delete this post</button>
                        </div>
                    </article>` : ''}
                </aside>
            </div>
        </form>`;
    }

    function wireSlug() {
        const title = document.getElementById('title');
        const slug = document.querySelector('[name="id"]');
        if (isEdit) return;
        slug.addEventListener('input', () => { slug.dataset.touched = '1'; });
        title.addEventListener('input', () => {
            if (!slug.dataset.touched) slug.value = U.slug(title.value);
        });
    }

    /* The editor already counts words; this only offers its estimate, and
       stops offering once the writer has typed their own number. */
    function wireReadTime() {
        const pad = document.querySelector('[data-editor]');
        const input = document.querySelector('[name="readMinutes"]');
        const hint = document.getElementById('readHint');
        input.addEventListener('input', () => { input.dataset.touched = '1'; });

        pad.addEventListener('input', U.debounce(() => {
            const words = U.plain(pad.querySelector('.editor__body').innerHTML)
                .split(/\s+/).filter(Boolean).length;
            const est = Math.max(1, Math.round(words / 200));
            if (!input.dataset.touched) input.value = est;
            hint.textContent = `${U.num(words)} words · about ${est} min to read.`;
        }, 400));
    }

    function wireSerpPreview() {
        const paint = () => {
            const d = ctrl.collect();
            const title = d.metaTitle || d.title || 'Untitled post';
            const desc = d.metaDescription || d.excerpt || '';
            document.getElementById('serp').innerHTML = `
                <div class="text-xs muted">teresamemorialhospital.com › blog › ${U.esc(d.id || 'slug')}</div>
                <div style="color:#1a0dab;font-size:1rem;line-height:1.3;margin:2px 0">${U.esc(title.slice(0, 60))}${title.length > 60 ? '…' : ''}</div>
                <div class="text-sm mid">${U.esc(desc.slice(0, 155))}${desc.length > 155 ? '…' : ''}</div>`;
        };
        document.getElementById('postForm').addEventListener('input', U.debounce(paint, 250));
        paint();

        if (isEdit) {
            document.getElementById('delBtn').addEventListener('click', async () => {
                const ok = await window.TMH.confirm({
                    title: `Delete “${record.title}”?`,
                    body: 'The article and its card on the blog index are removed.',
                    danger: true, confirmLabel: 'Delete post',
                });
                if (!ok) return;
                const removed = await store.remove('posts', record.id);
                toast.success('Post deleted', {
                    undo: () => store.restore('posts', removed.row, removed.index),
                });
                ctrl.clearDraft();
                setTimeout(() => { location.href = 'blog'; }, 900);
            });
        }
    }

    function offerDraftRestore() {
        const draft = ctrl.restorableDraft();
        if (!draft) return;
        if (record && new Date(record.updatedAt).getTime() >= draft.at) {
            ctrl.clearDraft();
            return;
        }
        toast.warning('Unsaved draft found', {
            body: `Autosaved ${U.ago(new Date(draft.at).toISOString())}.`,
            persistent: true, id: 'draft-restore',
            action: {
                label: 'Restore it',
                onClick: () => {
                    ctrl.bind(draft.data);
                    toast.success('Draft restored');
                },
            },
        });
    }

    async function save(data, opts) {
        const payload = Object.assign({}, data, {
            status: opts.publish ? 'published' : (data.status === 'published' ? 'published' : data.status || 'draft'),
            publishedAt: data.publishedAt || new Date().toISOString(),
            views: (record && record.views) || 0,
        });

        /* Enforce the single-featured rule here as well as on the list, so a
           form save cannot leave two featured posts behind. */
        if (payload.featured) {
            const previous = store.allSync('posts')
                .find((p) => p.featured && p.id !== (isEdit ? id : payload.id));
            if (previous) {
                await store.update('posts', previous.id, { featured: false });
                toast.info(`“${previous.title}” is no longer the featured post`);
            }
        }

        if (isEdit) {
            record = await store.update('posts', id, payload);
            toast.success(opts.publish ? 'Post published' : 'Changes saved', {
                action: opts.publish ? { label: 'View on site', href: `${SITE}blog` } : null,
            });
            if (payload.id !== id) {
                U.setParams({ id: payload.id });
                setTimeout(() => location.reload(), 400);
            }
        } else {
            record = await store.create('posts', payload);
            toast.success(opts.publish ? 'Post published' : 'Saved as draft');
            setTimeout(() => {
                location.href = opts.publish
                    ? `blog?created=${encodeURIComponent(record.id)}`
                    : `blog-form?id=${encodeURIComponent(record.id)}`;
            }, 600);
        }
    }
}());
