(function () {
    'use strict';
    const {util: U, api, fields, media} = window.HAZRA;
    const esc = U.esc;
    const groups = [
        ['hero', 'Hero', ['hero_kicker', 'hero_title', 'hero_description', 'hero_image_label', 'primary_cta', 'secondary_cta']],
        ['showcase', 'Product showcase', ['showcase_kicker', 'showcase_title', 'showcase_description', 'show_showcase']],
        ['range', 'Riding range', ['range_kicker', 'range_title', 'range_note', 'show_range']],
        ['cinematic', 'Cinematic banner', ['cinematic_kicker', 'cinematic_title', 'cinematic_image', 'cinematic_link', 'cinematic_cta', 'show_cinematic']],
        ['features', 'Feature gallery', ['features_kicker', 'features_title', 'show_features']],
        ['specs', 'Specifications', ['specs_kicker', 'specs_title', 'specs_description']],
        ['ownership', 'Ownership & support', ['ownership_kicker', 'ownership_title', 'ownership_description', 'dealer_cta', 'show_ownership']],
        ['related', 'Related models', ['related_kicker', 'related_title', 'related_cta', 'show_related']],
        ['booking', 'Test ride', ['booking_kicker', 'booking_title', 'booking_description']]
    ];

    function mount(controller, record, getColors) {
        const form = controller.scope;
        const page = Object.assign({}, window.HAZRA.productPageDefaults, record?.page_content || {});
        let cards = structuredClone(record?.feature_cards || []);
        let uploads = 0;
        let defaultColorId = record?.default_color_id || getColors()[0]?.id || '';
        const styles = document.createElement('style');
        styles.textContent = `
          .product-editor-tabs {display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;border-bottom:1px solid var(--hairline);padding-bottom:16px}
          .product-editor-tabs button[aria-selected=true] {background:var(--surface-3);border-color:var(--brand-blue);color:var(--text-main)}
          .product-editor-panel[hidden] {display:none!important}
          .product-editor-panel .form-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
          .product-editor-panel .field {min-width:0}
          .product-editor-panel .field>input,.product-editor-panel .field>textarea,.product-editor-panel .field>select {width:100%;border:1px solid var(--hairline);background:var(--surface-2);color:var(--text-main);border-radius:8px;padding:10px;font:inherit}
          .product-editor-panel label {display:block;font-size:12px;margin-bottom:8px}
          .product-feature-edit {display:grid;grid-template-columns:160px 1fr;gap:24px;padding:20px;margin:18px 0;border:1px solid var(--hairline);border-radius:12px;background:var(--surface-2)}
          .product-feature-edit img {width:100%;height:160px;object-fit:contain;border-radius:8px;background:var(--surface-1)}
          .product-feature-edit .field {margin-bottom:14px}
          .product-feature-actions {display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
          .product-content-group {padding:20px 0;border-bottom:1px solid var(--hairline)}
          .product-content-group h3 {margin-bottom:18px}
          .product-editor-help {font-size:13px;line-height:1.7;color:var(--text-mid);margin:0 0 20px}
          .product-lead-row {padding:16px 0;border-bottom:1px solid var(--hairline);display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}
          @media(max-width:650px){.product-feature-edit,.product-editor-panel .form-grid{grid-template-columns:1fr}.product-feature-edit img{height:210px}}
        `;
        document.head.append(styles);
        const tabNames = [['info', 'Product information'], ['colors', 'Colors & galleries'], ['features', 'Feature cards'], ['content', 'Page content'], ['specs', 'Specifications & warranty'], ['requests', 'Test-ride requests']];
        const tabs = document.createElement('div');
        tabs.className = 'product-editor-tabs';
        tabs.setAttribute('role', 'tablist');
        tabs.setAttribute('aria-label', 'Product editor');
        tabs.innerHTML = tabNames.map(([id, label]) => `<button type="button" class="btn btn--ghost btn--sm" id="product-tab-${id}" role="tab" aria-controls="product-panel-${id}" aria-selected="false" tabindex="-1" data-tab="${id}">${label}</button>`).join('');
        form.prepend(tabs);
        const panels = {};
        const actions = form.querySelector('#formSubmitBtn').parentElement;
        tabNames.forEach(([id]) => {
            const panel = document.createElement('section');
            panel.className = 'product-editor-panel';
            panel.id = 'product-panel-' + id;
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', 'product-tab-' + id);
            panel.hidden = true;
            form.insertBefore(panel, actions);
            panels[id] = panel;
        });
        const grid = form.querySelector('.form-grid');
        const infoGrid = document.createElement('div'); infoGrid.className = 'form-grid'; panels.info.append(infoGrid);
        const specsGrid = document.createElement('div'); specsGrid.className = 'form-grid'; panels.specs.append(specsGrid);
        const specNames = ['range_km', 'top_speed_kmph', 'battery_capacity', 'motor_power', 'charging_time', 'load_capacity_kg', 'warranty_years', 'warranty_note', 'rating'];
        [...grid.children].forEach(field => {
            const name = field.querySelector('[name]')?.name;
            (specNames.includes(name) ? specsGrid : infoGrid).append(field);
        });
        grid.remove();
        panels.colors.innerHTML = `<p class="product-editor-help">Upload each scooter color's own photographs below. The first image is its primary image. The product fallback is used only when that color has no photographs.</p><div class="field"><label for="productDefaultColor">Default color</label><select id="productDefaultColor"></select></div>`;
        panels.colors.append(form.querySelector('#productColorsSection'));
        const stateInput = document.createElement('input'); stateInput.type = 'hidden'; stateInput.name = '_product_editor_state'; form.append(stateInput);
        function sync() {
            stateInput.value = JSON.stringify({cards, defaultColorId, colors: getColors()});
            stateInput.dispatchEvent(new Event('input', {bubbles: true}));
        }
        function colorOptions(value, allowAll) {
            return (allowAll ? '<option value="">All colors / shared feature</option>' : '') + getColors().map(color => `<option value="${esc(color.id)}" ${value === color.id ? 'selected' : ''}>${esc(color.name)}</option>`).join('');
        }
        function refreshColors() {
            const colors = getColors();
            if (!colors.some(c => c.id === defaultColorId)) defaultColorId = colors[0]?.id || '';
            const select = panels.colors.querySelector('#productDefaultColor');
            select.innerHTML = colorOptions(defaultColorId, false);
            panels.features.querySelectorAll('[data-card-color]').forEach(select => {
                const card = cards[Number(select.dataset.cardColor)];
                if (card.color_id && !colors.some(c => c.id === card.color_id)) card.color_id = '';
                select.innerHTML = colorOptions(card.color_id, true);
            });
            sync();
        }
        panels.colors.querySelector('#productDefaultColor').addEventListener('change', event => {defaultColorId = event.target.value; sync();});
        panels.colors.addEventListener('input', event => {
            if (event.target.closest('#productColorsSection')) refreshColors();
        });
        panels.colors.addEventListener('change', event => {
            if (event.target.closest('#productColorsSection')) refreshColors();
        });
        function open(id) {
            tabNames.forEach(([key]) => {
                panels[key].hidden = key !== id;
                const tab = tabs.querySelector(`[data-tab="${key}"]`);
                tab.setAttribute('aria-selected', String(key === id));
                tab.tabIndex = key === id ? 0 : -1;
            });
            if (id === 'requests' && record?.id && !panels.requests.dataset.loaded) loadRequests();
        }
        tabs.addEventListener('click', event => {const tab = event.target.closest('[data-tab]'); if (tab) open(tab.dataset.tab);});
        tabs.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const buttons = [...tabs.querySelectorAll('[data-tab]')];
            const index = buttons.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
            event.preventDefault(); open(buttons[next].dataset.tab); buttons[next].focus();
        });
        // Validation can reveal a tab before focusing its invalid field.
        form.addEventListener('product-editor-invalid', event => open(event.detail));

        function renderCards() {
            panels.features.innerHTML = `<p class="product-editor-help">Manage the photographs and titles in “Thoughtfully engineered”. Shared cards stay the same when a customer changes scooter color. A color-specific card appears only for that color.</p><button type="button" class="btn btn--soft" data-add-feature>Add feature card</button><div>${cards.map((card, index) => `
                <article class="product-feature-edit" data-feature-index="${index}">
                    <div><img src="${esc(U.resolveUrl(card.image || record?.hero_image || getColors().flatMap(c => c.imageUrls)[0] || 'assets/scutie_light.webp'))}" alt="Feature preview"><label>Upload feature image<input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-feature-upload="${index}"></label></div>
                    <div>
                        <div class="field"><label>Title</label><input data-card-field="title" value="${esc(card.title || '')}" maxlength="250" aria-label="Feature title"></div>
                        <div class="field"><label>Image URL / existing image path</label><input data-card-field="image" value="${esc(card.image || '')}" aria-label="Feature image URL"></div>
                        <div class="field"><label>Description (optional)</label><textarea data-card-field="description" rows="2">${esc(card.description || '')}</textarea></div>
                        <div class="field"><label>Image alt text</label><input data-card-field="alt" value="${esc(card.alt || '')}" maxlength="500"></div>
                        <div class="field"><label>Applies to color</label><select data-card-color="${index}">${colorOptions(card.color_id, true)}</select></div>
                        <label><input type="checkbox" data-card-field="visible" ${card.visible !== false ? 'checked' : ''}> Show this card</label>
                        <div class="product-feature-actions"><button type="button" class="btn btn--ghost btn--sm" data-card-action="up" ${index === 0 ? 'disabled' : ''}>Move up</button><button type="button" class="btn btn--ghost btn--sm" data-card-action="down" ${index === cards.length - 1 ? 'disabled' : ''}>Move down</button><button type="button" class="btn btn--danger btn--sm" data-card-action="remove">Remove card</button></div>
                    </div>
                </article>`).join('')}</div>`;
        }
        panels.features.addEventListener('input', event => {
            const article = event.target.closest('[data-feature-index]');
            if (!article) return;
            const card = cards[Number(article.dataset.featureIndex)];
            if (event.target.dataset.cardField) {
                const key = event.target.dataset.cardField;
                card[key] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
                if (key === 'image') {card.legacy_crop = false; article.querySelector('img').src = U.resolveUrl(card.image);}
            }
            if (event.target.dataset.cardColor !== undefined) card.color_id = event.target.value;
            sync();
        });
        panels.features.addEventListener('click', event => {
            if (event.target.closest('[data-add-feature]')) {
                cards.push({id: crypto.randomUUID(), title: '', image: '', description: '', alt: '', color_id: '', visible: true}); renderCards(); sync();
            }
            const button = event.target.closest('[data-card-action]');
            if (!button) return;
            const index = Number(button.closest('[data-feature-index]').dataset.featureIndex);
            if (button.dataset.cardAction === 'remove') cards.splice(index, 1);
            else {
                const next = index + (button.dataset.cardAction === 'up' ? -1 : 1);
                if (next >= 0 && next < cards.length) [cards[index], cards[next]] = [cards[next], cards[index]];
            }
            renderCards(); sync();
        });
        panels.features.addEventListener('change', async event => {
            if (event.target.dataset.featureUpload === undefined || !event.target.files[0]) return;
            const card = cards[Number(event.target.dataset.featureUpload)];
            const input = event.target; input.disabled = true; uploads++;
            try {
                const formData = new FormData(); formData.append('file', input.files[0]);
                const response = await api.request('POST', 'api/v1/upload', {form: formData});
                const image = response.data || response;
                if (!image.url) throw new Error('Upload did not return an image URL');
                card.image = image.url; card.legacy_crop = false;
                // Keep the same card object if another card is moved during upload.
                if (cards.includes(card)) {
                    const currentArticle = panels.features.querySelector(`[data-feature-index="${cards.indexOf(card)}"]`);
                    currentArticle.querySelector('[data-card-field="image"]').value = card.image;
                    currentArticle.querySelector('img').src = U.resolveUrl(card.image);
                    sync();
                }
                window.HAZRA.toast.success('Feature image uploaded');
            } catch (error) {window.HAZRA.toast.error(error.message || 'Image upload failed');}
            finally {uploads--; input.disabled = false;}
        });
        renderCards();
        const labelOf = key => key.replace(/_/g, ' ').replace(/^show /, 'Show ').replace(/\bcta\b/g, 'button label').replace(/^./, c => c.toUpperCase());
        panels.content.innerHTML = `<p class="product-editor-help">Use {name}, {brand}, and {model} to insert product information. New lines in headings create line breaks. Leave the banner image empty to use the product fallback image.</p>` + groups.map(([, label, keys]) => `<div class="product-content-group"><h3>${label}</h3><div class="form-grid">${keys.map(key => {
            const name = '_pc_' + key; const value = page[key];
            if (key.startsWith('show_')) return `<label><input type="checkbox" name="${name}" ${value ? 'checked' : ''}> ${esc(labelOf(key))}</label>`;
            if (key === 'cinematic_image') return fields.media({name, label: 'Banner image', type: 'image'});
            return `<div class="field"><label for="${name}">${esc(labelOf(key))}</label><textarea id="${name}" name="${name}" rows="${/title|description|note/.test(key) ? 3 : 1}">${esc(value || '')}</textarea></div>`;
        }).join('')}</div></div>`).join('');
        const related = document.createElement('div'); related.className = 'field';
        related.innerHTML = `<label for="productRelatedModels">Related models (leave empty for automatic selection)</label><select id="productRelatedModels" name="_related_ids" multiple size="5">${window.HAZRA.store.allSync('products').filter(p => p.id !== record?.id).map(p => `<option value="${esc(p.id)}" ${(page.related_ids || []).includes(p.id) ? 'selected' : ''}>${esc(p.brand + ' ' + p.name)}</option>`).join('')}</select>`;
        panels.content.append(related);
        media.wire(panels.content); media.paintAll(panels.content, {_pc_cinematic_image: page.cinematic_image});
        panels.requests.innerHTML = '<p class="product-editor-help">Save this product first to view its test-ride requests.</p>';
        async function loadRequests() {
            panels.requests.innerHTML = '<p class="product-editor-help">Loading test-ride requests...</p>';
            try {
                const result = await api.request('GET', 'api/v1/website/leads', {query: {type: 'test_drive', product_id: record.id}});
                const rows = result.data || [];
                panels.requests.innerHTML = `<a class="btn btn--ghost" href="test-drive?product_id=${encodeURIComponent(record.id)}">Manage and schedule requests</a><p class="product-editor-help" style="margin-top:16px">${rows.length} request(s) for this product.</p>` + rows.map(row => `<div class="product-lead-row"><div><strong>${esc(row.name)}</strong><p>${esc(row.phone)} / ${esc(row.details?.city || '')}</p><p>${esc(row.details?.color_name || 'No color selected')}</p></div><div><strong>${esc(row.status)}</strong><p>${esc(U.fmtDateTime(row.created_at))}</p></div></div>`).join('');
                panels.requests.dataset.loaded = 'true';
            } catch (error) {panels.requests.innerHTML = `<p>${esc(error.message)}</p><button type="button" class="btn btn--ghost" data-retry-requests>Retry</button>`;}
        }
        panels.requests.addEventListener('click', event => {if (event.target.closest('[data-retry-requests]')) loadRequests();});
        if (record?.id) {
            const preview = document.createElement('a'); preview.className = 'btn btn--ghost'; preview.textContent = 'View saved product';
            preview.href = api.base + 'product-detail?id=' + encodeURIComponent(record.id); preview.target = '_blank'; preview.rel = 'noopener'; actions.append(preview);
        }
        refreshColors(); open('info'); controller.markClean();
        return {
            refreshColors,
            collect(data) {
                if (uploads) throw new Error('Please wait for feature image uploads to finish');
                for (const card of cards) if (!card.title.trim()) {open('features'); throw new Error('Every feature card needs a title');}
                const content = {};
                groups.forEach(([, , keys]) => keys.forEach(key => {content[key] = data['_pc_' + key];}));
                content.related_ids = [...panels.content.querySelector('#productRelatedModels').selectedOptions].map(option => option.value);
                Object.keys(data).filter(key => key.startsWith('_')).forEach(key => delete data[key]);
                data.page_content = content; data.feature_cards = cards; data.default_color_id = defaultColorId;
            }
        };
    }
    window.HAZRA.productEditor = {mount};
})();
