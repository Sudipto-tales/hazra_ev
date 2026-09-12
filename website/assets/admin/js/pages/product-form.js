(function () {
    'use strict';

    const { util: U, store, form: TMHForm, layout, toast } = window.TMH;
    const SITE = window.TMH.api.base;

    window.TMH.boot(init);

    async function init() {
        const id = U.param('id');
        const isEdit = Boolean(id);
        let record = isEdit ? await store.get('products', id) : null;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'Catalog', href: 'products' },
                { label: 'Products', href: 'products' },
                { label: isEdit ? 'Edit Product' : 'Add Product' }
            ],
            title: isEdit ? record?.name || 'Edit Product' : 'Add Product',
            accent: isEdit ? 'Edit' : 'Create',
            sub: 'Fill out scooter details, specs, images, and feature flags.',
            actions: `
                <a class="btn btn--ghost" href="products">
                    <i class="fa-solid fa-arrow-left"></i> Back to Products</a>`,
        });

        const initial = record || {
            name: '',
            brand: 'Hazra EV',
            model_code: '',
            category: 'High Speed',
            range_km: '100',
            top_speed_kmph: '65',
            battery_capacity: '72V 30Ah',
            motor_power: '1200W',
            warranty_years: '3',
            warranty_note: '3 Years Comprehensive Warranty',
            is_featured: 0,
            featured_order: 0,
            hero_image: '',
            slug: '',
            status: 'published'
        };

        const f = TMHForm.create({
            mount: '#view',
            initial,
            fields: [
                { name: 'name', label: 'Product Name', type: 'text', required: true },
                { name: 'brand', label: 'Brand', type: 'text', required: true },
                { name: 'model_code', label: 'Model Code', type: 'text', required: true },
                { name: 'slug', label: 'URL Slug', type: 'text' },
                { name: 'category', label: 'Category', type: 'select', options: ['High Speed', 'Low Speed', 'Eco'] },
                { name: 'range_km', label: 'Range (km)', type: 'text' },
                { name: 'top_speed_kmph', label: 'Top Speed (km/h)', type: 'text' },
                { name: 'battery_capacity', label: 'Battery Capacity', type: 'text' },
                { name: 'motor_power', label: 'Motor Power', type: 'text' },
                { name: 'warranty_years', label: 'Warranty (Years)', type: 'number' },
                { name: 'warranty_note', label: 'Warranty Note', type: 'text' },
                { name: 'hero_image', label: 'Hero Image URL', type: 'text' },
                { name: 'is_featured', label: 'Featured Product (Show on Homepage)', type: 'checkbox' },
                { name: 'featured_order', label: 'Featured Display Order', type: 'number' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft', 'hidden'] },
            ],
            onSave: async (data) => {
                if (isEdit) {
                    await store.update('products', id, data);
                    toast.success('Product updated');
                } else {
                    const created = await store.create('products', data);
                    toast.success('Product created');
                    location.href = `products?created=${encodeURIComponent(created.id)}`;
                    return;
                }
                location.href = 'products';
            }
        });
    }
}());
