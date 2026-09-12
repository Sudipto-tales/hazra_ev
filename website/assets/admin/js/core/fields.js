/* =========================================================
   Field builders.

   doctor-form writes its fields as literal HTML — that
   file is the readable reference for what the markup looks
   like. Every other form builds the same markup through these
   helpers, because thirty screens of hand-written <div
   class="field"> drifts within a week.

   Output is identical either way, so core/form.js binds,
   validates and collects both without knowing the difference.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.TMH.util;
    const esc = U.esc;

    /* Common attributes every control shares. */
    function attrs(o) {
        const out = [];
        if (o.name) out.push(`name="${esc(o.name)}"`);
        if (o.id) out.push(`id="${esc(o.id)}"`);
        if (o.required) out.push('required');
        if (o.requiredToPublish) out.push('data-required-to-publish');
        if (o.requiredMessage) out.push(`data-required-message="${esc(o.requiredMessage)}"`);
        if (o.rule) out.push(`data-rule="${esc(o.rule)}"`);
        /* `max` means two different things depending on the control, and
           conflating them put a "3 / 730" character counter under a number
           box. On a number input it is the largest allowed value; on anything
           that holds prose it is the soft character limit the meter counts
           against. */
        if (o.max !== undefined) {
            out.push(o.type === 'number'
                ? `max="${esc(o.max)}" data-max-value="${esc(o.max)}"`
                : `data-max="${esc(o.max)}"`);
        }
        /* Both spellings: the attribute so the control's own spinner respects
           it, and the data- copy that core/form.js validates against — the
           forms are all novalidate, so the attribute alone enforces nothing. */
        if (o.min !== undefined) out.push(`min="${esc(o.min)}" data-min="${esc(o.min)}"`);
        if (o.step) out.push(`step="${esc(o.step)}"`);
        if (o.placeholder) out.push(`placeholder="${esc(o.placeholder)}"`);
        /* The id of a <datalist> the caller printed beside the field: a free
           text box that still suggests the values already in use. Not a
           select — the gallery's albums are invented by whoever is filing the
           photographs, and a fixed list would mean a code change to add one. */
        if (o.list) out.push(`list="${esc(o.list)}"`);
        if (o.matchAfter) out.push(`data-match-after="${esc(o.matchAfter)}"`);
        if (o.readonly) out.push('readonly');
        if (o.disabled) out.push('disabled');
        return out.join(' ');
    }

    function shell(o, control) {
        return `
        <div class="field${o.wide ? ' field--wide' : ''}">
            ${o.label ? `<label for="${esc(o.id || o.name)}">${esc(o.label)}${o.required ? ' <span class="field__req">*</span>' : ''}${
                o.help ? ` <span class="help" title="${esc(o.help)}">?</span>` : ''}</label>` : ''}
            ${control}
            ${o.hint ? `<small>${o.hint}</small>` : ''}
        </div>`;
    }

    const F = {

        text(o) {
            const c = Object.assign({ id: o.name }, o);
            return shell(c, `<input type="${esc(o.type || 'text')}" ${attrs(c)}>`);
        },

        number(o) {
            return F.text(Object.assign({}, o, { type: 'number' }));
        },

        date(o) {
            return F.text(Object.assign({}, o, { type: 'date' }));
        },

        email(o) {
            return F.text(Object.assign({}, o, { type: 'email', rule: 'email' }));
        },

        url(o) {
            return F.text(Object.assign({}, o, { type: 'url', rule: 'url' }));
        },

        colour(o) {
            const c = Object.assign({ id: o.name }, o);
            return shell(c, `
                <div class="input-group">
                    <span class="input-group__addon" data-swatch="${esc(o.name)}"
                          style="width:44px;background:${esc(o.value || '#C1272D')}"></span>
                    <input type="text" ${attrs(c)}>
                </div>`);
        },

        textarea(o) {
            const c = Object.assign({ id: o.name, wide: true }, o);
            return shell(c, `<textarea ${attrs(c)} rows="${esc(o.rows || 4)}"></textarea>`);
        },

        select(o) {
            if (o.multiple) return F.multiselect(o);
            const c = Object.assign({ id: o.name }, o);
            const opts = (o.options || []).map((op) => {
                const v = typeof op === 'string' ? op : op.value;
                const l = typeof op === 'string' ? op : op.label;
                return `<option value="${esc(v)}">${esc(l)}</option>`;
            }).join('');
            return shell(c, `<select ${attrs(c)}>${
                o.placeholderOption ? `<option value="">${esc(o.placeholderOption)}</option>` : ''}${opts}</select>`);
        },

        /**
         * Many-of-a-list, as chips rather than a Ctrl-click list box.
         * core/multiselect.js builds the control inside this host and keeps
         * the hidden input — the thing core/form.js binds and validates — in
         * step. Reached through select({multiple: true}) as well.
         */
        multiselect(o) {
            const options = (o.options || []).map((op) => (typeof op === 'string'
                ? { value: op, label: op }
                : { value: op.value, label: op.label }));
            const json = (v) => JSON.stringify(v).replace(/'/g, '&#39;');

            return `
            <div class="field${o.wide ? ' field--wide' : ''}">
                ${o.label ? `<label>${esc(o.label)}${o.required ? ' <span class="field__req">*</span>' : ''}${
                    o.help ? ` <span class="help" title="${esc(o.help)}">?</span>` : ''}</label>` : ''}
                <div class="multiselect" data-multiselect="${esc(o.name)}"
                     data-options='${json(options)}'
                     data-placeholder="${esc(o.placeholder || 'Choose')}"
                     data-search-placeholder="${esc(o.searchPlaceholder || 'Search')}">
                    <input type="hidden" name="${esc(o.name)}"
                        ${o.required ? 'required' : ''}
                        ${o.requiredToPublish ? 'data-required-to-publish' : ''}
                        data-required-message="${esc(o.requiredMessage || 'Pick at least one')}">
                </div>
                ${o.hint ? `<small>${o.hint}</small>` : ''}
            </div>`;
        },

        /* The status select every content form ends with. */
        status(o) {
            return F.select(Object.assign({
                name: 'status',
                label: 'Status',
                options: [
                    { value: 'draft', label: 'Draft — not on the site' },
                    { value: 'published', label: 'Published — live' },
                    { value: 'hidden', label: 'Hidden — kept, but not shown' },
                ],
            }, o || {}));
        },

        toggle(o) {
            return `
            <div class="field${o.wide === false ? '' : ' field--wide'}">
                <label class="toggle">
                    <input type="checkbox" name="${esc(o.name)}">
                    <span class="toggle__track"></span>
                    <span class="toggle__text">${esc(o.label)}</span>
                </label>
                ${o.hint ? `<small>${o.hint}</small>` : ''}
            </div>`;
        },

        media(o) {
            return `
            <div class="field${o.wide === false ? '' : ' field--wide'}">
                <label>${esc(o.label)}${o.required ? ' <span class="field__req">*</span>' : ''}</label>
                <div class="media-pick" data-media="${esc(o.name)}"></div>
                <input type="hidden" name="${esc(o.name)}" ${o.required ? 'required' : ''}
                    data-required-message="${esc(o.requiredMessage || 'An image is required')}">
                ${o.hint ? `<small>${o.hint}</small>` : ''}
            </div>`;
        },

        icon(o) {
            const c = Object.assign({ id: o.name, placeholder: 'fa-heart-pulse' }, o);
            return shell(c, `
                <div class="input-group">
                    <span class="input-group__addon" data-icon-preview="${esc(o.name)}">
                        <i class="fa-solid ${esc(o.value || 'fa-circle')}"></i></span>
                    <input type="text" ${attrs(c)}>
                </div>`);
        },

        /**
         * repeater({name, label, cols, addLabel, min, max, fields:[…]})
         * `fields` is the same shape core/repeater.js expects.
         */
        repeater(o) {
            return `
            <div class="field field--wide">
                ${o.label ? `<label>${esc(o.label)}</label>` : ''}
                <div class="repeater" data-repeater="${esc(o.name)}"
                     data-cols="${esc(o.cols || (o.fields || [{}]).length)}"
                     data-add-label="${esc(o.addLabel || 'Add row')}"
                     ${o.min ? `data-min="${esc(o.min)}"` : ''}
                     ${o.max ? `data-max="${esc(o.max)}"` : ''}
                     data-fields='${JSON.stringify(o.fields || [{ key: 'text', placeholder: 'Type here' }]).replace(/'/g, '&#39;')}'></div>
                ${o.hint ? `<small>${o.hint}</small>` : ''}
            </div>`;
        },

        /* Rich-text pad. core/editor.js upgrades this in place. */
        editor(o) {
            return `
            <div class="field field--wide">
                ${o.label ? `<label>${esc(o.label)}${o.required ? ' <span class="field__req">*</span>' : ''}</label>` : ''}
                <div data-editor="${esc(o.name)}" data-placeholder="${esc(o.placeholder || 'Start writing…')}"></div>
                ${o.hint ? `<small>${o.hint}</small>` : ''}
            </div>`;
        },

        /* Read-only value with a link to wherever it is actually edited —
           used so a page never becomes a second source of truth. */
        mirror(o) {
            return `
            <div class="field${o.wide === false ? '' : ' field--wide'}">
                <label>${esc(o.label)}</label>
                <div class="row" style="padding:9px 12px;border:1px dashed var(--hairline-strong);border-radius:var(--radius-sm);background:var(--surface-2)">
                    <span class="grow text-sm">${esc(o.value || '—')}</span>
                    <a class="btn btn--link text-xs" href="${esc(o.href)}">Edit in ${esc(o.source)}</a>
                </div>
                ${o.hint ? `<small>${o.hint}</small>` : ''}
            </div>`;
        },

        /* ---- composition ---- */

        section(o) {
            return `
            <section class="form-section">
                ${o.title ? `
                <div class="form-section__head">
                    <h3>${o.icon ? `<i class="fa-solid ${esc(o.icon)}" style="color:var(--brand-red)"></i> ` : ''}${esc(o.title)}</h3>
                    ${o.sub ? `<p>${esc(o.sub)}</p>` : ''}
                </div>` : ''}
                <div class="form-grid">${(o.fields || []).join('')}</div>
            </section>`;
        },

        divider() {
            return '<div class="divider"></div>';
        },

        /**
         * A collapsible section card for the page editors
         * (page-home, page-about, page-contact, page-careers).
         *
         * Field names inside are namespaced `<key>.<field>` so a flat form
         * can be split back into the page's sections[] array on save.
         */
        sect(o) {
            return `
            <section class="sect${o.open ? ' is-open' : ''}${o.enabled === false ? ' is-off' : ''}" data-sect="${esc(o.key)}">
                <div class="sect__head" role="button" tabindex="0" aria-expanded="${!!o.open}">
                    <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
                    <span class="sect__title grow">${esc(o.label)}<small>${esc(o.sub || `Section key: ${o.key}`)}</small></span>
                    <label class="toggle">
                        <input type="checkbox" name="${esc(o.key)}.__enabled">
                        <span class="toggle__track"></span>
                        <span class="sr-only">Show this section on the page</span>
                    </label>
                    <i class="fa-solid fa-chevron-down sect__chev"></i>
                </div>
                <div class="sect__body">
                    <div class="form-grid">${(o.fields || []).join('')}</div>
                </div>
            </section>`;
        },

        /* Expand/collapse + drag-reorder for the cards above. */
        wireSections(scope, onReorder) {
            const host = scope || document;

            host.querySelectorAll('.sect__head').forEach((head) => {
                const toggle = () => {
                    const card = head.closest('.sect');
                    card.classList.toggle('is-open');
                    head.setAttribute('aria-expanded', String(card.classList.contains('is-open')));
                };
                head.addEventListener('click', (e) => {
                    if (e.target.closest('.toggle, .drag-handle')) return;
                    toggle();
                });
                head.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggle();
                    }
                });
            });

            /* An enabled toggle greys the card, so a disabled section reads as
               disabled without having to open it. */
            host.querySelectorAll('.sect input[name$=".__enabled"]').forEach((cb) => {
                const sync = () => cb.closest('.sect').classList.toggle('is-off', !cb.checked);
                cb.addEventListener('change', sync);
                sync();
            });

            const cards = [...host.querySelectorAll('.sect')];
            cards.forEach((card) => {
                const handle = card.querySelector('.drag-handle');
                if (!handle) return;
                handle.addEventListener('mousedown', () => { card.draggable = true; });
                card.addEventListener('dragend', () => {
                    card.draggable = false;
                    card.classList.remove('is-dragging');
                    if (onReorder) {
                        onReorder([...host.querySelectorAll('.sect')].map((c) => c.dataset.sect));
                    }
                });
                card.addEventListener('dragstart', (e) => {
                    card.classList.add('is-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.dataset.sect);
                });
                card.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    const dragging = host.querySelector('.sect.is-dragging');
                    if (!dragging || dragging === card) return;
                    const rect = card.getBoundingClientRect();
                    const after = e.clientY > rect.top + rect.height / 2;
                    card.parentElement.insertBefore(dragging, after ? card.nextSibling : card);
                });
            });
        },

        /* The sticky Cancel / Save draft / Publish bar. */
        bar(o) {
            const c = o || {};
            return `
            <div class="form-bar" id="formBar">
                <div class="form-bar__status"></div>
                ${c.noCancel ? '' : '<button type="button" class="btn btn--ghost" data-cancel>Cancel</button>'}
                ${c.singleSave
                    ? `<button type="button" class="btn btn--primary" data-publish>
                        <i class="fa-solid fa-floppy-disk"></i> <span id="publishLabel">${esc(c.saveLabel || 'Save changes')}</span></button>`
                    : `<button type="button" class="btn btn--soft" data-save-draft>Save draft</button>
                       <button type="button" class="btn btn--primary" data-publish>
                        <i class="fa-solid fa-cloud-arrow-up"></i> <span id="publishLabel">${esc(c.saveLabel || 'Publish')}</span></button>`}
            </div>`;
        },

        /* SEO block — identical on every entity that has a public URL. */
        seo(o) {
            const c = o || {};
            return F.section({
                title: 'Search appearance',
                icon: 'fa-magnifying-glass-chart',
                sub: 'How this page reads in a Google result.',
                fields: [
                    F.text({
                        name: 'metaTitle', label: 'Meta title', wide: true, max: 60,
                        requiredToPublish: !c.optional,
                        placeholder: c.titlePlaceholder || '',
                    }),
                    F.textarea({ name: 'metaDescription', label: 'Meta description', max: 155, rows: 3 }),
                ],
            });
        },
    };

    /* Keeps colour swatches and icon previews in step with their input. */
    function wirePreviews(scope) {
        (scope || document).querySelectorAll('[data-swatch]').forEach((sw) => {
            const input = (scope || document).querySelector(`[name="${CSS.escape(sw.dataset.swatch)}"]`);
            if (!input) return;
            const paint = () => { sw.style.background = input.value || 'transparent'; };
            input.addEventListener('input', paint);
            paint();
        });
        (scope || document).querySelectorAll('[data-icon-preview]').forEach((box) => {
            const input = (scope || document).querySelector(`[name="${CSS.escape(box.dataset.iconPreview)}"]`);
            if (!input) return;
            const paint = () => {
                box.innerHTML = `<i class="fa-solid ${esc(input.value || 'fa-circle')}"></i>`;
            };
            input.addEventListener('input', paint);
            paint();
        });
    }

    F.wirePreviews = wirePreviews;
    root.TMH.fields = F;
}(window));
