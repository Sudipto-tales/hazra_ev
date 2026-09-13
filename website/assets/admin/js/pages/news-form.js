(function () {
    'use strict';

    const { util: U, store, form: HAZRAForm, layout, toast } = window.HAZRA;

    window.HAZRA.boot(init);

    async function init() {
        const id = U.param('id');
        const isEdit = Boolean(id);
        let record = isEdit ? await store.get('news', id) : null;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'News', href: 'news' }, { label: isEdit ? 'Edit News' : 'New News' }],
            title: isEdit ? record?.title || 'Edit News' : 'New News Post',
            accent: isEdit ? 'Edit' : 'Create',
            sub: 'Create press releases and news items.',
            actions: `<a class="btn btn--ghost" href="news"><i class="fa-solid fa-arrow-left"></i> Back to News</a>`,
        });

        const initial = record || {
            type: 'news',
            title: '',
            slug: '',
            author: 'Hazra EV Team',
            excerpt: '',
            content: '',
            cover_image: '',
            status: 'published'
        };

        const f = HAZRAForm.create({
            mount: '#view',
            initial,
            fields: [
                { name: 'title', label: 'News Headline', type: 'text', required: true },
                { name: 'slug', label: 'Slug', type: 'text' },
                { name: 'author', label: 'Author', type: 'text' },
                { name: 'cover_image', label: 'Cover Image', type: 'image' },
                { name: 'excerpt', label: 'Headline Summary', type: 'textarea' },
                { name: 'content', label: 'Body Content', type: 'wysiwyg' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft'] },
            ],
            onCancel: () => { location.href = 'news'; },
            onSave: async (data) => {
                data.type = 'news';
                if (!data.slug && data.title) {
                    data.slug = U.slug(data.title);
                }
                if (isEdit) {
                    await store.update('news', id, data);
                    toast.success('News updated');
                } else {
                    await store.create('news', data);
                    toast.success('News created');
                }
                location.href = 'news';
            }
        });

        if (!isEdit) {
            const titleEl = document.getElementById('title');
            const slugEl = document.getElementById('slug');
            if (titleEl && slugEl) {
                titleEl.addEventListener('input', () => {
                    if (!slugEl.dataset.touched) {
                        slugEl.value = U.slug(titleEl.value);
                    }
                });
                slugEl.addEventListener('input', () => {
                    slugEl.dataset.touched = '1';
                });
            }
        }
    }
}());
