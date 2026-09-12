(function () {
    'use strict';

    const { util: U, store, form: TMHForm, layout, toast, editor } = window.TMH;

    window.TMH.boot(init);

    async function init() {
        const id = U.param('id');
        const isEdit = Boolean(id);
        let record = isEdit ? await store.get('posts', id) : null;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Blogs', href: 'blogs' }, { label: isEdit ? 'Edit Blog' : 'New Blog' }],
            title: isEdit ? record?.title || 'Edit Blog' : 'New Blog Post',
            accent: isEdit ? 'Edit' : 'Create',
            sub: 'Write and publish articles for your blog section.',
            actions: `<a class="btn btn--ghost" href="blogs"><i class="fa-solid fa-arrow-left"></i> Back to Blogs</a>`,
        });

        const initial = record || {
            type: 'blog',
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
                { name: 'title', label: 'Article Title', type: 'text', required: true },
                { name: 'slug', label: 'Slug', type: 'text' },
                { name: 'author', label: 'Author', type: 'text' },
                { name: 'cover_image', label: 'Cover Image URL', type: 'text' },
                { name: 'excerpt', label: 'Short Excerpt', type: 'textarea' },
                { name: 'content', label: 'Full Article Content', type: 'wysiwyg' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft'] },
            ],
            onSave: async (data) => {
                data.type = 'blog';
                if (isEdit) {
                    await store.update('posts', id, data);
                    toast.success('Blog updated');
                } else {
                    await store.create('posts', data);
                    toast.success('Blog created');
                }
                location.href = 'blogs';
            }
        });
    }
}());
