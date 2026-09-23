(function () {
    'use strict';

    const { util: U, store, form: HAZRAForm, layout, toast } = window.HAZRA;
    const SITE = window.HAZRA.api.base;

    window.HAZRA.boot(init);

    function argbToHex(argb) {
        if (argb === undefined || argb === null || argb === '') return '#1a1a1a';
        const num = Number(argb);
        const hex = (num & 0xFFFFFF).toString(16).padStart(6, '0');
        return '#' + hex;
    }

    function hexToArgb(hex) {
        if (!hex) return -14671840;
        const clean = hex.replace('#', '');
        const num = parseInt(clean, 16) || 0;
        return (0xFF000000 | num) >> 0;
    }

    const SCOOTER_PALETTE = [
        { name: 'Matte Black', hex: '#202020' },
        { name: 'Pearl White', hex: '#efeaf8' },
        { name: 'Midnight Indigo', hex: '#241640' },
        { name: 'Ocean Blue', hex: '#12a5e0' },
        { name: 'Cyan', hex: '#00d4ff' },
        { name: 'Flame Orange', hex: '#f0532b' },
        { name: 'Sunset Flame', hex: '#ea580c' },
        { name: 'Amber Gold', hex: '#f7941d' },
        { name: 'Champagne Gold', hex: '#b45309' },
        { name: 'Magenta', hex: '#a41fbf' },
        { name: 'Violet', hex: '#7b2ff7' },
        { name: 'Forest Green', hex: '#166534' },
    ];

    async function init() {
        if (!document.getElementById('hazra-product-color-styles')) {
            const style = document.createElement('style');
            style.id = 'hazra-product-color-styles';
            style.textContent = `
                .color-picker-wrap {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex-wrap: wrap;
                }
                .color-circle-preview {
                    position: relative;
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    overflow: hidden;
                    border: 2px solid var(--hairline);
                    flex-shrink: 0;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                    cursor: pointer;
                    transition: transform 0.15s ease, box-shadow 0.15s ease;
                }
                .color-circle-preview:hover {
                    transform: scale(1.08);
                    box-shadow: 0 3px 10px rgba(0,0,0,0.22);
                }
                .color-hex-input {
                    position: absolute;
                    top: -10px;
                    left: -10px;
                    width: 60px;
                    height: 60px;
                    border: none;
                    cursor: pointer;
                    padding: 0;
                    opacity: 0;
                }
                .btn-eyedropper {
                    background: var(--surface-1);
                    border: 1px solid var(--hairline);
                    border-radius: 8px;
                    width: 36px;
                    height: 36px;
                    display: grid;
                    place-items: center;
                    cursor: pointer;
                    color: var(--brand-blue, #12a5e0);
                    transition: all 0.15s ease;
                    flex-shrink: 0;
                }
                .btn-eyedropper:hover {
                    background: var(--surface-2);
                    border-color: var(--brand-blue, #12a5e0);
                    transform: translateY(-1px);
                    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
                }
                .color-palette-strip {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    background: var(--surface-1);
                    padding: 4px 10px;
                    border-radius: 24px;
                    border: 1px solid var(--hairline);
                    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
                    flex-wrap: wrap;
                    max-width: 340px;
                }
                .palette-swatch {
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    border: 1.5px solid rgba(0,0,0,0.2);
                    cursor: pointer;
                    transition: transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.15s ease;
                    padding: 0;
                    outline: none;
                    position: relative;
                    flex-shrink: 0;
                }
                .palette-swatch:hover {
                    transform: scale(1.3);
                    box-shadow: 0 0 0 2px var(--brand-blue, #12a5e0), 0 3px 8px rgba(0,0,0,0.25);
                    z-index: 2;
                }
                .palette-swatch.is-active {
                    box-shadow: 0 0 0 2px #fff, 0 0 0 3.5px var(--brand-blue, #12a5e0);
                    transform: scale(1.15);
                    z-index: 1;
                }
                .palette-swatch.is-active::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 5px;
                    height: 5px;
                    background: #fff;
                    border-radius: 50%;
                    transform: translate(-50%, -50%);
                    box-shadow: 0 0 2px rgba(0,0,0,0.6);
                }
                .color-hex-badge {
                    font-family: monospace;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.05em;
                    color: var(--text-main);
                    background: var(--surface-1);
                    padding: 6px 10px;
                    border-radius: 6px;
                    border: 1px solid var(--hairline);
                    min-width: 72px;
                    text-align: center;
                    user-select: all;
                }
                .eyedropper-modal-backdrop {
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.85);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    backdrop-filter: blur(4px);
                    animation: edFadeIn 0.2s ease;
                }
                @keyframes edFadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                .eyedropper-modal-box {
                    background: var(--surface-1, #1e1e1e);
                    border: 1px solid var(--hairline, #333);
                    border-radius: 16px;
                    max-width: 95vw;
                    max-height: 92vh;
                    display: flex;
                    flex-direction: column;
                    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
                    overflow: hidden;
                    width: 820px;
                }
                .eyedropper-modal-head {
                    padding: 14px 20px;
                    border-bottom: 1px solid var(--hairline, #333);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                }
                .eyedropper-modal-body {
                    padding: 16px;
                    overflow: auto;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    min-height: 320px;
                    background: #111;
                }
                .eyedropper-loupe {
                    position: absolute;
                    pointer-events: none;
                    width: 110px;
                    height: 110px;
                    border-radius: 50%;
                    border: 3px solid #fff;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.6);
                    overflow: hidden;
                    display: none;
                    background: #000;
                    z-index: 10;
                }
                .eyedropper-loupe canvas {
                    width: 100%;
                    height: 100%;
                    image-rendering: pixelated;
                }
                .eyedropper-loupe-reticle {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    width: 12px;
                    height: 12px;
                    transform: translate(-50%, -50%);
                    border: 1.5px solid #fff;
                    box-shadow: 0 0 2px #000;
                    border-radius: 2px;
                    pointer-events: none;
                }
                .eyedropper-live-badge {
                    position: absolute;
                    bottom: 8px;
                    left: 50%;
                    transform: translateX(-50%);
                    background: rgba(0,0,0,0.85);
                    color: #fff;
                    font-family: monospace;
                    font-size: 11px;
                    padding: 3px 8px;
                    border-radius: 4px;
                    pointer-events: none;
                    white-space: nowrap;
                    font-weight: 700;
                    border: 1px solid rgba(255,255,255,0.2);
                }
            `;
            document.head.appendChild(style);
        }

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
            sub: 'Manage scooter details, technical specifications, and per-colour image galleries.',
            actions: `
                <a class="btn btn--ghost" href="products">
                    <i class="fa-solid fa-arrow-left"></i> Back to Products</a>`,
        });

        // Initialize colors state
        let colorsState = [];
        if (record && Array.isArray(record.colors) && record.colors.length > 0) {
            colorsState = record.colors.map((c, i) => ({
                id: c.id || ('col_' + (i + 1)),
                name: c.name || `Color ${i + 1}`,
                argb: c.argb !== undefined ? Number(c.argb) : hexToArgb('#202020'),
                hex: argbToHex(c.argb),
                inStock: c.inStock !== false && c.in_stock !== 0,
                imageUrls: Array.isArray(c.imageUrls) ? [...c.imageUrls] : (Array.isArray(c.images) ? [...c.images] : [])
            }));
        } else {
            colorsState = [
                {
                    id: 'col_1',
                    name: 'Matte Black',
                    argb: hexToArgb('#202020'),
                    hex: '#202020',
                    inStock: true,
                    imageUrls: []
                }
            ];
        }

        const initial = record || {
            name: '',
            brand: 'Hazra EV',
            model_code: '',
            category: 'scooty',
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

        const f = HAZRAForm.create({
            mount: '#view',
            initial,
            fields: [
                { name: 'name', label: 'Product Name', type: 'text', required: true },
                { name: 'brand', label: 'Brand', type: 'text', required: true },
                { name: 'model_code', label: 'Model Code', type: 'text', required: true },
                { name: 'slug', label: 'URL Slug', type: 'text' },
                { name: 'category', label: 'Category', type: 'select', options: [
                    { value: 'scooty', label: 'Scooty' },
                    { value: 'bike', label: 'Bike' },
                    { value: 'bicycle', label: 'E-Bicycle' },
                    { value: 'others', label: 'Others' }
                ] },
                { name: 'range_km', label: 'Range (km)', type: 'text' },
                { name: 'top_speed_kmph', label: 'Top Speed (km/h)', type: 'text' },
                { name: 'battery_capacity', label: 'Battery Capacity', type: 'text' },
                { name: 'motor_power', label: 'Motor Power', type: 'text' },
                { name: 'warranty_years', label: 'Warranty (Years)', type: 'number' },
                { name: 'warranty_note', label: 'Warranty Note', type: 'text' },
                { name: 'hero_image', label: 'Hero Image (Fallback if no colour selected)', type: 'image' },
                { name: 'is_featured', label: 'Featured Product (Show on Homepage)', type: 'checkbox' },
                { name: 'featured_order', label: 'Featured Display Order', type: 'number' },
                { name: 'status', label: 'Status', type: 'select', options: ['published', 'draft', 'hidden'] },
            ],
            onSave: async (data) => {
                data.modelCode = data.model_code || data.modelCode;
                data.rangeKm = parseInt(data.range_km || data.rangeKm || 0, 10);
                data.topSpeedKmph = parseInt(data.top_speed_kmph || data.topSpeedKmph || 0, 10);
                data.warrantyYears = parseInt(data.warranty_years || data.warrantyYears || 0, 10);
                data.batteryCapacity = data.battery_capacity || data.batteryCapacity || '';
                data.motorPower = data.motor_power || data.motorPower || '';
                data.warrantyNote = data.warranty_note || data.warrantyNote || '';

                // Build clean colors payload with multiple images
                data.colors = colorsState.map((c, idx) => ({
                    id: c.id && !c.id.startsWith('col_') ? c.id : undefined,
                    name: (c.name || '').trim() || `Color ${idx + 1}`,
                    argb: hexToArgb(c.hex),
                    inStock: Boolean(c.inStock),
                    position: idx,
                    imageUrls: Array.isArray(c.imageUrls) ? c.imageUrls.filter(Boolean) : []
                }));

                if (!data.hero_image && data.colors[0]?.imageUrls?.[0]) {
                    data.hero_image = data.colors[0].imageUrls[0];
                }

                if (isEdit) {
                    await store.update('products', id, data);
                    toast.success('Product updated with colour galleries');
                } else {
                    const created = await store.create('products', data);
                    toast.success('Product created with colour galleries');
                    location.href = `products?created=${encodeURIComponent(created.id)}`;
                    return;
                }
                location.href = 'products';
            }
        });

        // Insert Colours & Image Galleries section into the form
        const formEl = document.getElementById('autoGeneratedForm');
        if (formEl) {
            const submitBtnContainer = formEl.querySelector('div[style*="display:flex;gap:12px"]');
            const colorsContainer = document.createElement('div');
            colorsContainer.id = 'productColorsSection';
            colorsContainer.style.marginTop = '28px';
            colorsContainer.style.marginBottom = '28px';
            colorsContainer.style.borderTop = '1px solid var(--hairline)';
            colorsContainer.style.paddingTop = '24px';

            if (submitBtnContainer) {
                formEl.insertBefore(colorsContainer, submitBtnContainer);
            } else {
                formEl.appendChild(colorsContainer);
            }

            renderColorsUI(colorsContainer);
        }

        function renderColorsUI(container) {
            container.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-palette" style="color: var(--brand-blue, #12a5e0);"></i> Colours & Image Galleries
                        </h3>
                        <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--muted, #888);">
                            Each colour has its own independent gallery for the 360° carousel and full-screen lightbox.
                        </p>
                    </div>
                    <button type="button" class="btn btn--soft btn--sm" id="addNewColorBtn" style="font-weight: 700;">
                        <i class="fa-solid fa-plus"></i> Add New Colour
                    </button>
                </div>

                <div id="colorsList" style="display: flex; flex-direction: column; gap: 20px;">
                    ${colorsState.map((color, index) => renderSingleColorCard(color, index)).join('')}
                </div>
            `;

            // Wire add color button
            const addBtn = container.querySelector('#addNewColorBtn');
            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    colorsState.push({
                        id: 'col_' + Date.now(),
                        name: 'New Colourway',
                        argb: hexToArgb('#12a5e0'),
                        hex: '#12a5e0',
                        inStock: true,
                        imageUrls: []
                    });
                    renderColorsUI(container);
                });
            }

            wireColorEvents(container);
        }

        function applyColorToCard(idx, hex, container, colorName = null) {
            if (!hex) return;
            const cleanHex = hex.startsWith('#') ? hex : ('#' + hex);
            colorsState[idx].hex = cleanHex;
            colorsState[idx].argb = hexToArgb(cleanHex);

            // Auto-update colorway name if empty or generic default
            if (colorName && (!colorsState[idx].name || colorsState[idx].name.trim() === '' || colorsState[idx].name.startsWith('Color ') || colorsState[idx].name === 'New Colourway')) {
                colorsState[idx].name = colorName;
                const nameInput = container.querySelector(`.color-name-input[data-index="${idx}"]`);
                if (nameInput) nameInput.value = colorName;
            }

            const cardEl = container.querySelector(`.color-card[data-index="${idx}"]`);
            if (cardEl) {
                const hexInput = cardEl.querySelector('.color-hex-input');
                const hexBadge = cardEl.querySelector('.color-hex-badge');
                const circlePreview = cardEl.querySelector('.color-circle-preview');

                if (hexInput) hexInput.value = cleanHex;
                if (hexBadge) hexBadge.textContent = cleanHex.toUpperCase();
                if (circlePreview) circlePreview.style.backgroundColor = cleanHex;

                // Update active state across palette swatches on this card
                cardEl.querySelectorAll('.palette-swatch').forEach(sw => {
                    if (sw.dataset.hex.toLowerCase() === cleanHex.toLowerCase()) {
                        sw.classList.add('is-active');
                    } else {
                        sw.classList.remove('is-active');
                    }
                });
            }
        }

        function renderSingleColorCard(color, index) {
            const hasImages = color.imageUrls && color.imageUrls.length > 0;
            const curHex = (color.hex || '#202020').toLowerCase();

            const paletteHtml = SCOOTER_PALETTE.map(p => {
                const isActive = p.hex.toLowerCase() === curHex;
                return `
                    <button type="button" 
                            class="palette-swatch ${isActive ? 'is-active' : ''}" 
                            data-hex="${p.hex}" 
                            data-name="${p.name}" 
                            data-index="${index}" 
                            title="${p.name} (${p.hex})" 
                            style="background-color: ${p.hex};" 
                            aria-label="${p.name}">
                    </button>
                `;
            }).join('');

            return `
                <div class="card color-card" data-index="${index}" style="padding: 18px; border: 1px solid var(--hairline); border-radius: 14px; background: var(--surface-2, rgba(0,0,0,0.02));">
                    <!-- Top row: Name, color picker, palette, eyedropper, stock, ordering, delete -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 320px; flex-wrap: wrap;">
                            <div class="color-picker-wrap">
                                <div class="color-circle-preview" data-index="${index}" title="Click to open custom color picker" style="background-color: ${U.esc(color.hex)};">
                                    <input type="color" class="color-hex-input" data-index="${index}" value="${U.esc(color.hex)}">
                                </div>

                                <button type="button" class="btn-eyedropper" data-index="${index}" title="Pick colour from screen or image (Eye-dropper)">
                                    <i class="fa-solid fa-eye-dropper"></i>
                                </button>

                                <div class="color-palette-strip" title="Predefined scooter colours">
                                    ${paletteHtml}
                                </div>
                            </div>

                            <input type="text" class="input color-name-input" data-index="${index}" value="${U.esc(color.name)}" placeholder="Colour Name (e.g. Matte Black)" style="height: 38px; font-weight: 700; font-size: 14px; flex: 1; min-width: 140px;">
                            <span class="color-hex-badge" data-index="${index}">${U.esc((color.hex || '').toUpperCase())}</span>
                        </div>

                        <div style="display: flex; align-items: center; gap: 12px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; cursor: pointer; user-select: none;">
                                <input type="checkbox" class="color-stock-input" data-index="${index}" ${color.inStock ? 'checked' : ''}>
                                <span>In Stock</span>
                            </label>

                            <div style="display: flex; gap: 4px;">
                                <button type="button" class="btn btn--ghost btn--sm color-move-up" data-index="${index}" title="Move Up" ${index === 0 ? 'disabled style="opacity:0.3;"' : ''}>
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn--ghost btn--sm color-move-down" data-index="${index}" title="Move Down" ${index === colorsState.length - 1 ? 'disabled style="opacity:0.3;"' : ''}>
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                            </div>

                            <button type="button" class="btn btn--ghost btn--sm text-danger color-delete-btn" data-index="${index}" title="Delete Colour" style="color: var(--brand-red, #e53e3e);">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Images Gallery Strip -->
                    <div style="background: var(--surface-1); padding: 14px; border-radius: 10px; border: 1px dashed var(--hairline);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted);">
                                Gallery Images (${color.imageUrls.length})
                            </span>
                            <div style="display: flex; gap: 8px;">
                                <label class="btn btn--soft btn--sm" style="cursor: pointer; margin: 0; padding: 4px 12px; font-size: 12px;">
                                    <i class="fa-solid fa-upload"></i> Upload Images
                                    <input type="file" class="color-file-input" data-index="${index}" multiple accept="image/*,.webp,.jpg,.jpeg,.png,.gif,.svg" style="display: none;">
                                </label>
                                <button type="button" class="btn btn--ghost btn--sm color-add-url-btn" data-index="${index}" style="padding: 4px 10px; font-size: 12px;">
                                    <i class="fa-solid fa-link"></i> Add URL
                                </button>
                            </div>
                        </div>

                        <!-- Dropzone / Thumbnails area -->
                        <div class="gallery-dropzone" data-index="${index}" style="min-height: 90px; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 6px;">
                            ${hasImages ? color.imageUrls.map((url, imgIdx) => `
                                <div class="thumb-card" style="position: relative; width: 90px; height: 90px; border-radius: 10px; overflow: hidden; border: 2px solid ${imgIdx === 0 ? 'var(--brand-blue, #12a5e0)' : 'var(--hairline)'}; background: #fff; box-shadow: 0 3px 8px rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: center; group;">
                                    <img src="${U.esc(U.resolveUrl(url))}" alt="Gallery image" style="width: 100%; height: 100%; object-fit: contain; padding: 4px;">
                                    
                                    ${imgIdx === 0 ? `<span style="position: absolute; bottom: 2px; left: 2px; right: 2px; background: rgba(18, 165, 224, 0.9); color: #fff; font-size: 9px; font-weight: 800; text-align: center; border-radius: 4px; padding: 2px 0; text-transform: uppercase;">Hero</span>` : ''}

                                    <!-- Hover actions overlay -->
                                    <div class="thumb-actions" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.65); display: flex; flex-direction: column; justify-content: space-between; padding: 4px; opacity: 0; transition: opacity 0.2s;" onmouseenter="this.style.opacity='1'" onmouseleave="this.style.opacity='0'">
                                        <div style="display: flex; justify-content: flex-end;">
                                            <button type="button" class="thumb-remove-btn" data-color-idx="${index}" data-img-idx="${imgIdx}" style="background: var(--brand-red, #e53e3e); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; display: grid; place-items: center; font-size: 11px;" title="Remove image">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <button type="button" class="thumb-move-left" data-color-idx="${index}" data-img-idx="${imgIdx}" style="background: rgba(255,255,255,0.85); color: #222; border: none; border-radius: 4px; width: 22px; height: 20px; cursor: pointer; font-size: 10px; ${imgIdx === 0 ? 'visibility:hidden;' : ''}" title="Move left">
                                                <i class="fa-solid fa-chevron-left"></i>
                                            </button>
                                            <button type="button" class="thumb-move-right" data-color-idx="${index}" data-img-idx="${imgIdx}" style="background: rgba(255,255,255,0.85); color: #222; border: none; border-radius: 4px; width: 22px; height: 20px; cursor: pointer; font-size: 10px; ${imgIdx === color.imageUrls.length - 1 ? 'visibility:hidden;' : ''}" title="Move right">
                                                <i class="fa-solid fa-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('') : `
                                <div class="drop-hint" style="width: 100%; padding: 20px 10px; text-align: center; color: var(--muted); font-size: 12.5px;">
                                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 22px; margin-bottom: 6px; display: block; opacity: 0.6;"></i>
                                    Drag and drop images here, or click <strong>Upload Images</strong> above.
                                </div>
                            `}
                        </div>
                    </div>
                </div>
            `;
        }

        function wireColorEvents(container) {
            // Hex color picker input (native)
            container.querySelectorAll('.color-hex-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const idx = Number(e.target.dataset.index);
                    applyColorToCard(idx, e.target.value, container);
                });
            });

            // Name input
            container.querySelectorAll('.color-name-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const idx = Number(e.target.dataset.index);
                    colorsState[idx].name = e.target.value;
                });
            });

            // In Stock checkbox
            container.querySelectorAll('.color-stock-input').forEach(input => {
                input.addEventListener('change', (e) => {
                    const idx = Number(e.target.dataset.index);
                    colorsState[idx].inStock = e.target.checked;
                });
            });

            // Move colour up
            container.querySelectorAll('.color-move-up').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = Number(btn.dataset.index);
                    if (idx > 0) {
                        const temp = colorsState[idx];
                        colorsState[idx] = colorsState[idx - 1];
                        colorsState[idx - 1] = temp;
                        renderColorsUI(container);
                    }
                });
            });

            // Move colour down
            container.querySelectorAll('.color-move-down').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = Number(btn.dataset.index);
                    if (idx < colorsState.length - 1) {
                        const temp = colorsState[idx];
                        colorsState[idx] = colorsState[idx + 1];
                        colorsState[idx + 1] = temp;
                        renderColorsUI(container);
                    }
                });
            });

            // Delete colour
            container.querySelectorAll('.color-delete-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const idx = Number(btn.dataset.index);
                    if (colorsState.length <= 1) {
                        toast.error('A product must have at least one colourway.');
                        return;
                    }
                    const confirmed = await (window.HAZRA.confirm ? window.HAZRA.confirm({
                        title: 'Delete colourway?',
                        body: `Are you sure you want to remove "${colorsState[idx].name}" and its images?`,
                        danger: true
                    }) : Promise.resolve(confirm(`Delete colourway "${colorsState[idx].name}"?`)));

                    if (confirmed) {
                        colorsState.splice(idx, 1);
                        renderColorsUI(container);
                    }
                });
            });

            // File upload input
            container.querySelectorAll('.color-file-input').forEach(fileInput => {
                fileInput.addEventListener('change', async (e) => {
                    const idx = Number(e.target.dataset.index);
                    const files = Array.from(e.target.files || []);
                    if (!files.length) return;

                    await uploadImagesForColor(idx, files, container);
                    fileInput.value = '';
                });
            });

            // Drag & Drop on dropzone
            container.querySelectorAll('.gallery-dropzone').forEach(dropzone => {
                const idx = Number(dropzone.dataset.index);

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropzone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.style.background = 'rgba(18, 165, 224, 0.08)';
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropzone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.style.background = '';
                    }, false);
                });

                dropzone.addEventListener('drop', async (e) => {
                    const dt = e.dataTransfer;
                    const files = Array.from(dt.files || []).filter(f => f.type.startsWith('image/'));
                    if (files.length) {
                        await uploadImagesForColor(idx, files, container);
                    }
                });
            });

            // Add URL directly
            container.querySelectorAll('.color-add-url-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const idx = Number(btn.dataset.index);
                    const url = prompt('Enter image URL (or relative path like assets/...):');
                    if (url && url.trim()) {
                        colorsState[idx].imageUrls.push(url.trim());
                        renderColorsUI(container);
                    }
                });
            });

            // Remove thumbnail
            container.querySelectorAll('.thumb-remove-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const colorIdx = Number(btn.dataset.colorIdx);
                    const imgIdx = Number(btn.dataset.imgIdx);
                    colorsState[colorIdx].imageUrls.splice(imgIdx, 1);
                    renderColorsUI(container);
                });
            });

            // Move thumbnail left
            container.querySelectorAll('.thumb-move-left').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const colorIdx = Number(btn.dataset.colorIdx);
                    const imgIdx = Number(btn.dataset.imgIdx);
                    if (imgIdx > 0) {
                        const arr = colorsState[colorIdx].imageUrls;
                        const temp = arr[imgIdx];
                        arr[imgIdx] = arr[imgIdx - 1];
                        arr[imgIdx - 1] = temp;
                        renderColorsUI(container);
                    }
                });
            });

            // Move thumbnail right
            container.querySelectorAll('.thumb-move-right').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const colorIdx = Number(btn.dataset.colorIdx);
                    const imgIdx = Number(btn.dataset.imgIdx);
                    const arr = colorsState[colorIdx].imageUrls;
                    if (imgIdx < arr.length - 1) {
                        const temp = arr[imgIdx];
                        arr[imgIdx] = arr[imgIdx + 1];
                        arr[imgIdx + 1] = temp;
                        renderColorsUI(container);
                    }
                });
            });

            // Palette swatches: 1-click select preset color
            container.querySelectorAll('.palette-swatch').forEach(swatch => {
                swatch.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const idx = Number(swatch.dataset.index);
                    const hex = swatch.dataset.hex;
                    const name = swatch.dataset.name;
                    applyColorToCard(idx, hex, container, name);
                });
            });

            // Eye-dropper color picker: Native EyeDropper API with Canvas Modal fallback
            container.querySelectorAll('.btn-eyedropper[data-index]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const idx = Number(btn.dataset.index);
                    const color = colorsState[idx];

                    // Check if gallery images or hero image exist
                    const heroInput = document.querySelector('input[name="hero_image"]');
                    const hasLocalImages = color.imageUrls && color.imageUrls.length > 0;
                    const hasAnyImages = hasLocalImages || Boolean(heroInput && heroInput.value) || colorsState.some(c => c.imageUrls && c.imageUrls.length > 0);

                    // If browser supports EyeDropper API (Chromium / Edge / Opera), use it directly
                    if (window.EyeDropper) {
                        try {
                            const eyeDropper = new EyeDropper();
                            const result = await eyeDropper.open();
                            if (result && result.sRGBHex) {
                                applyColorToCard(idx, result.sRGBHex, container);
                                toast.success(`Sampled colour: ${result.sRGBHex.toUpperCase()}`);
                                return;
                            }
                        } catch (err) {
                            if (err.name === 'AbortError') return; // User canceled
                        }
                    }

                    // Open Canvas Image Sampler Modal
                    if (hasAnyImages) {
                        openEyedropperModal(idx, container);
                    } else {
                        toast.info('Upload at least one gallery image first to sample from the image canvas.');
                    }
                });
            });
        }

        function openEyedropperModal(colorIdx, container) {
            const color = colorsState[colorIdx];

            // Collect all possible images (color gallery, hero image, other colors)
            const heroInput = document.querySelector('input[name="hero_image"]');
            const availableImages = [];

            if (Array.isArray(color.imageUrls)) {
                color.imageUrls.filter(Boolean).forEach(url => {
                    if (!availableImages.includes(url)) availableImages.push(url);
                });
            }
            if (heroInput && heroInput.value && !availableImages.includes(heroInput.value)) {
                availableImages.push(heroInput.value);
            }
            colorsState.forEach(c => {
                if (Array.isArray(c.imageUrls)) {
                    c.imageUrls.filter(Boolean).forEach(url => {
                        if (!availableImages.includes(url)) availableImages.push(url);
                    });
                }
            });

            if (availableImages.length === 0) {
                toast.error('Upload at least one image first to sample a colour from it.');
                return;
            }

            let activeImageUrl = availableImages[0];

            const modal = document.createElement('div');
            modal.className = 'eyedropper-modal-backdrop';
            modal.innerHTML = `
                <div class="eyedropper-modal-box">
                    <div class="eyedropper-modal-head">
                        <div>
                            <div style="font-weight: 800; font-size: 16px; color: var(--text-main, #fff); display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-eye-dropper" style="color: var(--brand-blue, #12a5e0);"></i>
                                Pick Colour from Image
                            </div>
                            <div style="font-size: 12.5px; color: var(--muted, #aaa); margin-top: 2px;">
                                Move your cursor over the image and click any pixel to sample the exact colourway.
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            ${window.EyeDropper ? `
                                <button type="button" class="btn btn--soft btn--sm btn-trigger-native-ed" style="font-size: 12px;">
                                    <i class="fa-solid fa-crosshairs"></i> Screen Eye-dropper
                                </button>
                            ` : ''}
                            <button type="button" class="btn btn--ghost btn--sm btn-close-ed-modal" style="width: 32px; height: 32px; padding: 0; display: grid; place-items: center;">
                                <i class="fa-solid fa-xmark" style="font-size: 16px;"></i>
                            </button>
                        </div>
                    </div>

                    ${availableImages.length > 1 ? `
                        <div style="display: flex; gap: 8px; padding: 10px 20px; border-bottom: 1px solid var(--hairline, #333); overflow-x: auto; background: var(--surface-2, rgba(255,255,255,0.02));">
                            <span style="font-size: 12px; font-weight: 700; color: var(--muted); align-self: center; margin-right: 6px;">Image:</span>
                            ${availableImages.map((u, i) => `
                                <button type="button" class="btn-select-ed-img" data-url="${U.esc(u)}" style="padding: 2px; border-radius: 6px; border: 2px solid ${u === activeImageUrl ? 'var(--brand-blue, #12a5e0)' : 'transparent'}; background: #000; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex-shrink: 0;">
                                    <img src="${U.esc(U.resolveUrl(u))}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </button>
                            `).join('')}
                        </div>
                    ` : ''}

                    <div class="eyedropper-modal-body">
                        <canvas id="eyedropperCanvas" style="max-width: 85vw; max-height: 56vh; border-radius: 8px; cursor: crosshair; box-shadow: 0 4px 20px rgba(0,0,0,0.4);"></canvas>
                        
                        <!-- Magnifier Loupe -->
                        <div class="eyedropper-loupe" id="eyedropperLoupe">
                            <canvas id="loupeCanvas" width="9" height="9"></canvas>
                            <div class="eyedropper-loupe-reticle"></div>
                            <div class="eyedropper-live-badge" id="loupeBadge">#000000</div>
                        </div>

                        <div style="margin-top: 14px; display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: 600px; color: var(--muted, #aaa); font-size: 12.5px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div id="livePreviewColorBox" style="width: 26px; height: 26px; border-radius: 6px; border: 2px solid #fff; background: transparent; box-shadow: 0 2px 6px rgba(0,0,0,0.3);"></div>
                                <span id="livePreviewText" style="font-family: monospace; font-weight: 700; color: #fff; font-size: 13px;">Hover image to sample</span>
                            </div>
                            <div>
                                Press <kbd style="background: rgba(255,255,255,0.15); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 11px;">Esc</kbd> to cancel
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            const canvas = modal.querySelector('#eyedropperCanvas');
            const ctx = canvas.getContext('2d');
            const loupe = modal.querySelector('#eyedropperLoupe');
            const loupeCanvas = modal.querySelector('#loupeCanvas');
            const loupeCtx = loupeCanvas.getContext('2d');
            const loupeBadge = modal.querySelector('#loupeBadge');
            const liveBox = modal.querySelector('#livePreviewColorBox');
            const liveText = modal.querySelector('#livePreviewText');

            let currentImg = new Image();

            function loadImage(url) {
                activeImageUrl = url;
                modal.querySelectorAll('.btn-select-ed-img').forEach(b => {
                    b.style.borderColor = b.dataset.url === url ? 'var(--brand-blue, #12a5e0)' : 'transparent';
                });

                currentImg = new Image();
                currentImg.crossOrigin = 'anonymous';
                currentImg.onload = () => {
                    const maxW = Math.min(window.innerWidth * 0.85, 760);
                    const maxH = Math.min(window.innerHeight * 0.54, 460);
                    let w = currentImg.width, h = currentImg.height;
                    if (w > maxW || h > maxH) {
                        const scale = Math.min(maxW / w, maxH / h);
                        w = Math.round(w * scale);
                        h = Math.round(h * scale);
                    }
                    canvas.width = w;
                    canvas.height = h;
                    ctx.drawImage(currentImg, 0, 0, w, h);
                };
                currentImg.src = U.resolveUrl(url);
            }

            loadImage(activeImageUrl);

            // Handle multi-image switches
            modal.querySelectorAll('.btn-select-ed-img').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    loadImage(btn.dataset.url);
                });
            });

            // Native EyeDropper shortcut inside modal
            const nativeEdBtn = modal.querySelector('.btn-trigger-native-ed');
            if (nativeEdBtn) {
                nativeEdBtn.addEventListener('click', async () => {
                    closeModal();
                    try {
                        const eyeDropper = new EyeDropper();
                        const result = await eyeDropper.open();
                        if (result && result.sRGBHex) {
                            applyColorToCard(colorIdx, result.sRGBHex, container);
                            toast.success(`Sampled colour: ${result.sRGBHex.toUpperCase()}`);
                        }
                    } catch (err) {}
                });
            }

            // Loupe & hover inspector
            canvas.addEventListener('mousemove', (e) => {
                const rect = canvas.getBoundingClientRect();
                const x = Math.floor(e.clientX - rect.left);
                const y = Math.floor(e.clientY - rect.top);

                if (x < 0 || x >= canvas.width || y < 0 || y >= canvas.height) {
                    loupe.style.display = 'none';
                    return;
                }

                const pixel = ctx.getImageData(x, y, 1, 1).data;
                const hex = '#' + [pixel[0], pixel[1], pixel[2]].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();

                // Position loupe centered slightly above cursor
                loupe.style.display = 'block';
                const bodyRect = modal.querySelector('.eyedropper-modal-body').getBoundingClientRect();
                loupe.style.left = `${e.clientX - bodyRect.left - 55}px`;
                loupe.style.top = `${e.clientY - bodyRect.top - 125}px`;

                // Draw zoomed 9x9 pixel patch into loupe
                loupeCtx.clearRect(0, 0, 9, 9);
                loupeCtx.drawImage(canvas, Math.max(0, x - 4), Math.max(0, y - 4), 9, 9, 0, 0, 9, 9);

                loupeBadge.textContent = hex;
                liveBox.style.backgroundColor = hex;
                liveText.textContent = `${hex} (rgb: ${pixel[0]}, ${pixel[1]}, ${pixel[2]})`;
            });

            canvas.addEventListener('mouseleave', () => {
                loupe.style.display = 'none';
            });

            const pickColor = (e) => {
                const rect = canvas.getBoundingClientRect();
                const x = Math.floor(e.clientX - rect.left);
                const y = Math.floor(e.clientY - rect.top);
                if (x < 0 || x >= canvas.width || y < 0 || y >= canvas.height) return;

                const pixel = ctx.getImageData(x, y, 1, 1).data;
                const hex = '#' + [pixel[0], pixel[1], pixel[2]].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
                
                applyColorToCard(colorIdx, hex, container);
                toast.success(`Sampled colour: ${hex}`);
                closeModal();
            };

            const closeModal = () => {
                document.removeEventListener('keydown', onKeydown);
                modal.remove();
            };

            const onKeydown = (e) => {
                if (e.key === 'Escape') closeModal();
            };
            document.addEventListener('keydown', onKeydown);

            canvas.addEventListener('click', pickColor);
            modal.querySelector('.btn-close-ed-modal')?.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }

        async function uploadImagesForColor(colorIdx, files, container) {
            toast.info(`Uploading ${files.length} image${files.length > 1 ? 's' : ''}...`);
            let successCount = 0;

            for (const file of files) {
                try {
                    const form = new FormData();
                    form.append('file', file, file.name);
                    const res = await window.HAZRA.api.request('POST', 'api/v1/upload', { form });
                    const item = res.data || res;
                    const url = item.url || item.image_url || item.path;
                    if (url) {
                        colorsState[colorIdx].imageUrls.push(url);
                        successCount++;
                    }
                } catch (err) {
                    console.error('Failed to upload', file.name, err);
                }
            }

            if (successCount > 0) {
                toast.success(`Uploaded ${successCount} image${successCount > 1 ? 's' : ''}`);
                renderColorsUI(container);
            } else {
                toast.error('Failed to upload image(s). Check file type and size.');
            }
        }
    }
}());
