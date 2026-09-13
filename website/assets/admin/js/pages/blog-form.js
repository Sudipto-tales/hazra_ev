(function () {
    'use strict';

    const { util: U, store, form: HAZRAForm, layout, toast } = window.HAZRA;

    window.HAZRA.boot(init);

    async function init() {
        const id = U.param('id');
        const isEdit = Boolean(id);
        let record = isEdit ? await store.get('blogs', id) : null;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Blogs', href: 'blogs' }, { label: isEdit ? 'Edit Blog Post' : 'New Blog Post' }],
            title: isEdit ? record?.title || 'Edit Blog Post' : 'New Blog Post',
            accent: isEdit ? 'Edit' : 'Create',
            sub: 'Create and publish blog articles.',
            actions: `<a class="btn btn--ghost" href="blogs"><i class="fa-solid fa-arrow-left"></i> Back to Blogs</a>`,
        });

        const initial = record || {
            type: 'blog',
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
                { name: 'title', label: 'Blog Title', type: 'text', required: true },
                { name: 'slug', label: 'URL Slug', type: 'text' },
                { name: 'author', label: 'Author', type: 'text' },
                { name: 'cover_image', label: 'Cover Image', type: 'image' },
                { name: 'excerpt', label: 'Short Excerpt', type: 'textarea' },
                { name: 'content', label: 'Article Content', type: 'wysiwyg' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft'] },
            ],
            onCancel: () => { location.href = 'blogs'; },
            onSave: async (data) => {
                data.type = 'blog';
                if (!data.slug && data.title) {
                    data.slug = U.slug(data.title);
                }
                if (isEdit) {
                    await store.update('blogs', id, data);
                    toast.success('Blog post updated');
                } else {
                    await store.create('blogs', data);
                    toast.success('Blog post created');
                }
                location.href = 'blogs';
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
