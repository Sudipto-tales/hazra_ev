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
            status: 'published',
            category: 'company',
            tags: '',
            location: '',
            read_minutes: 3,
            is_featured: 0,
            meta_title: '',
            meta_description: '',
        };

        const f = HAZRAForm.create({
            mount: '#view',
            initial,
            fields: [
                { name: 'title', label: 'News Headline', type: 'text', required: true },
                { name: 'slug', label: 'Slug', type: 'text', help: 'Auto-generated from title if left empty' },
                { name: 'author', label: 'Author', type: 'text' },
                { name: 'category', label: 'Category', type: 'select', options: [
                    { value: 'company', label: 'Company' },
                    { value: 'ev-trends', label: 'EV Trends' },
                    { value: 'product', label: 'Product' },
                    { value: 'ownership', label: 'Ownership' },
                    { value: 'safety', label: 'Safety' },
                ]},
                { name: 'tags', label: 'Tags', type: 'text', help: 'Comma-separated tags (e.g. expansion, dealership, charging-network)' },
                { name: 'location', label: 'Location', type: 'text', help: 'City/region for this news item (e.g. Kolkata, Bardhaman)' },
                { name: 'cover_image', label: 'Cover Image', type: 'image' },
                { name: 'excerpt', label: 'Headline Summary', type: 'textarea', help: 'Brief summary for listings and SEO' },
                { name: 'content', label: 'Body Content', type: 'wysiwyg' },
                { name: 'read_minutes', label: 'Read Time (minutes)', type: 'number', min: 1, max: 60, help: 'Estimated reading time' },
                { name: 'is_featured', label: 'Featured News', type: 'checkbox', help: 'Show as featured in flash news sidebar' },
                { name: 'meta_title', label: 'SEO Title', type: 'text', help: 'Override default title for search engines (max 60 chars)' },
                { name: 'meta_description', label: 'SEO Description', type: 'textarea', help: 'Override default description for search engines (max 160 chars)' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft'] },
            ],
            onCancel: () => { location.href = 'news'; },
            onSave: async (data) => {
                data.type = 'news';
                if (!data.slug && data.title) {
                    data.slug = U.slug(data.title);
                }
                data.is_featured = data.is_featured ? 1 : 0;
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