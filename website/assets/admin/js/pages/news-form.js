(function () {
    'use strict';

    const { util: U, store, form: TMHForm, layout, toast } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        const id = U.param('id');
        const isEdit = Boolean(id);
        let record = isEdit ? await store.get('posts', id) : null;

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
            author: 'Admin',
            excerpt: '',
            content: '',
            cover_image: '',
            status: 'published'
        };

        const f = TMHForm.create({
            mount: '#view',
            initial,
            fields: [
                { name: 'title', label: 'News Headline', type: 'text', required: true },
                { name: 'slug', label: 'Slug', type: 'text' },
                { name: 'author', label: 'Author', type: 'text' },
                { name: 'cover_image', label: 'Image URL', type: 'text' },
                { name: 'excerpt', label: 'Headline Summary', type: 'textarea' },
                { name: 'content', label: 'Body Content', type: 'wysiwyg' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft'] },
            ],
            onSave: async (data) => {
                data.type = 'news';
                if (isEdit) {
                    await store.update('posts', id, data);
                    toast.success('News updated');
                } else {
                    await store.create('posts', data);
                    toast.success('News created');
                }
                location.href = 'news';
            }
        });
    }
}());
