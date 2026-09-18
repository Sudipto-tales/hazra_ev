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

        function renderSingleColorCard(color, index) {
            const hasImages = color.imageUrls && color.imageUrls.length > 0;

            return `
                <div class="card color-card" data-index="${index}" style="padding: 18px; border: 1px solid var(--hairline); border-radius: 14px; background: var(--surface-2, rgba(0,0,0,0.02));">
                    <!-- Top row: Name, color picker, stock, ordering, delete -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 240px;">
                            <div style="position: relative; width: 34px; height: 34px; border-radius: 50%; overflow: hidden; border: 2px solid var(--hairline); flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.15);">
                                <input type="color" class="color-hex-input" data-index="${index}" value="${U.esc(color.hex)}" style="position: absolute; top: -10px; left: -10px; width: 60px; height: 60px; border: none; cursor: pointer; padding: 0;">
                            </div>
                            <input type="text" class="input color-name-input" data-index="${index}" value="${U.esc(color.name)}" placeholder="Colour Name (e.g. Matte Black)" style="height: 36px; font-weight: 700; font-size: 14px; flex: 1; min-width: 140px;">
                            <span style="font-family: monospace; font-size: 12px; color: var(--muted); background: var(--surface-1); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--hairline);">${U.esc(color.hex)}</span>
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
            // Hex color picker input
            container.querySelectorAll('.color-hex-input').forEach(input => {
                input.addEventListener('input', (e) => {
                    const idx = Number(e.target.dataset.index);
                    colorsState[idx].hex = e.target.value;
                    colorsState[idx].argb = hexToArgb(e.target.value);
                    const hexSpan = e.target.closest('.card').querySelector('span[style*="font-family: monospace"]');
                    if (hexSpan) hexSpan.textContent = e.target.value;
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
