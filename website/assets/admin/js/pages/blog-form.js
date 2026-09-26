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
            status: 'published',
            category: 'ev-trends',
            tags: '',
            read_minutes: 4,
            is_featured: 0,
            meta_title: '',
            meta_description: '',
        };

        const f = HAZRAForm.create({
            mount: '#view',
            initial,
            fields: [
                { name: 'title', label: 'Blog Title', type: 'text', required: true },
                { name: 'slug', label: 'URL Slug', type: 'text', help: 'Auto-generated from title if left empty' },
                { name: 'author', label: 'Author', type: 'text' },
                { name: 'category', label: 'Category', type: 'select', options: [
                    { value: 'ev-trends', label: 'EV Trends' },
                    { value: 'ownership', label: 'Ownership' },
                    { value: 'company', label: 'Company' },
                    { value: 'product', label: 'Product' },
                    { value: 'safety', label: 'Safety' },
                ]},
                { name: 'tags', label: 'Tags', type: 'text', help: 'Comma-separated tags (e.g. battery-care, city-riding, ev-trends)' },
                { name: 'cover_image', label: 'Cover Image', type: 'image' },
                { name: 'excerpt', label: 'Short Excerpt', type: 'textarea', help: 'Brief summary for listings and SEO' },
                { name: 'content', label: 'Article Content', type: 'wysiwyg' },
                { name: 'read_minutes', label: 'Read Time (minutes)', type: 'number', min: 1, max: 60, help: 'Estimated reading time' },
                { name: 'is_featured', label: 'Featured Post', type: 'checkbox', help: 'Show as featured article on blog listing' },
                { name: 'meta_title', label: 'SEO Title', type: 'text', help: 'Override default title for search engines (max 60 chars)' },
                { name: 'meta_description', label: 'SEO Description', type: 'textarea', help: 'Override default description for search engines (max 160 chars)' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft'] },
            ],
            onCancel: () => { location.href = 'blogs'; },
            onSave: async (data) => {
                data.type = 'blog';
                if (!data.slug && data.title) {
                    data.slug = U.slug(data.title);
                }
                data.is_featured = data.is_featured ? 1 : 0;
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