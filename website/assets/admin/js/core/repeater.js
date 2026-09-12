/* =========================================================
   Repeater — the array field used everywhere docs/02-content-
   model.md says "repeater": phones, emails, chips, checks,
   procedures, conditions, responsibilities, milestones.

   Declared in markup, filled from JS:

     <div class="repeater" data-repeater="phones" data-min="1" data-cols="3"
          data-add-label="Add phone"
          data-fields='[{"key":"label","placeholder":"Label"},
                        {"key":"number","placeholder":"+91 …"},
                        {"key":"isPrimary","type":"checkbox","label":"Primary"}]'></div>

     TMH.repeater.mount(el, rows);
     TMH.repeater.value(el);   // -> [{label, number, isPrimary}, …]

   A single-value repeater (one field, key "text") accepts a
   multiline paste and splits it into one row per line — which
   is how a twelve-item responsibilities list gets entered in
   one action instead of twelve.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.TMH.util;
    const esc = U.esc;

    function fieldsOf(el) {
        try {
            return JSON.parse(el.dataset.fields || '[{"key":"text","placeholder":"Type here"}]');
        } catch (e) {
            console.warn('[repeater] bad data-fields on', el);
            return [{ key: 'text', placeholder: 'Type here' }];
        }
    }

    function controlHtml(f, value) {
        const v = value == null ? '' : value;
        if (f.type === 'checkbox') {
            return `<label class="checkbox" style="height:36px">
                <input type="checkbox" data-key="${esc(f.key)}" ${v ? 'checked' : ''}>
                <span>${esc(f.label || f.key)}</span></label>`;
        }
        if (f.type === 'select') {
            return `<select data-key="${esc(f.key)}" aria-label="${esc(f.label || f.key)}">
                ${(f.options || []).map((o) => {
                    const val = typeof o === 'string' ? o : o.value;
                    const lab = typeof o === 'string' ? o : o.label;
                    return `<option value="${esc(val)}" ${String(v) === String(val) ? 'selected' : ''}>${esc(lab)}</option>`;
                }).join('')}</select>`;
        }
        if (f.type === 'textarea') {
            return `<textarea data-key="${esc(f.key)}" rows="2" placeholder="${esc(f.placeholder || '')}"
                aria-label="${esc(f.label || f.key)}" style="min-height:56px">${esc(v)}</textarea>`;
        }
        if (f.type === 'icon') {
            return `<div class="input-group">
                <span class="input-group__addon"><i class="fa-solid ${esc(v || 'fa-circle')}"></i></span>
                <input type="text" data-key="${esc(f.key)}" value="${esc(v)}"
                    placeholder="fa-heart-pulse" aria-label="${esc(f.label || 'Icon')}"></div>`;
        }
        return `<input type="${esc(f.type || 'text')}" data-key="${esc(f.key)}" value="${esc(v)}"
            placeholder="${esc(f.placeholder || '')}" aria-label="${esc(f.label || f.key)}">`;
    }

    function rowHtml(el, fields, row) {
        return `
        <div class="repeater__row" draggable="true">
            <span class="drag-handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
            <div class="repeater__fields" style="--cols:${esc(el.dataset.cols || fields.length)}">
                ${fields.map((f) => controlHtml(f, row ? row[f.key] : (f.default || ''))).join('')}
            </div>
            <button type="button" class="repeater__del" aria-label="Remove row"><i class="fa-solid fa-xmark"></i></button>
        </div>`;
    }

    function render(el, rows) {
        const fields = fieldsOf(el);
        const min = Number(el.dataset.min || 0);
        const max = Number(el.dataset.max || 0);
        const list = rows && rows.length ? rows : Array.from({ length: Math.max(min, 1) }, () => ({}));

        el.innerHTML = `
            ${list.map((r) => rowHtml(el, fields, r)).join('')}
            <button type="button" class="btn btn--soft btn--sm repeater__add" data-add>
                <i class="fa-solid fa-plus"></i> ${esc(el.dataset.addLabel || 'Add row')}</button>`;

        refreshButtons(el, min, max);
        wire(el, fields, min, max);
    }

    function refreshButtons(el, min, max) {
        const rows = [...el.querySelectorAll('.repeater__row')];
        rows.forEach((r) => {
            r.querySelector('.repeater__del').disabled = rows.length <= min;
        });
        const add = el.querySelector('[data-add]');
        if (add) add.disabled = !!max && rows.length >= max;
    }

    function wire(el, fields, min, max) {
        const notify = () => el.dispatchEvent(new Event('change', { bubbles: true }));

        el.querySelector('[data-add]').addEventListener('click', () => {
            const add = el.querySelector('[data-add]');
            add.insertAdjacentHTML('beforebegin', rowHtml(el, fields, null));
            const row = add.previousElementSibling;
            wireRow(el, row, fields, min, max, notify);
            refreshButtons(el, min, max);
            const first = row.querySelector('input, textarea, select');
            if (first) first.focus();
            notify();
        });

        [...el.querySelectorAll('.repeater__row')]
            .forEach((row) => wireRow(el, row, fields, min, max, notify));
    }

    function wireRow(el, row, fields, min, max, notify) {
        row.querySelector('.repeater__del').addEventListener('click', () => {
            row.remove();
            refreshButtons(el, min, max);
            notify();
        });

        row.addEventListener('input', notify);
        row.addEventListener('change', notify);

        /* Paste-a-list. Only for single-text-field repeaters, where splitting
           lines is unambiguous. */
        if (fields.length === 1 && !fields[0].type) {
            const input = row.querySelector('input[data-key]');
            if (input) {
                input.addEventListener('paste', (e) => {
                    const text = (e.clipboardData || root.clipboardData).getData('text');
                    const lines = text.split(/\r?\n/).map((l) => l.replace(/^[-•*\d.)\s]+/, '').trim()).filter(Boolean);
                    if (lines.length < 2) return;
                    e.preventDefault();
                    input.value = lines[0];
                    const add = el.querySelector('[data-add]');
                    lines.slice(1).forEach((line) => {
                        add.insertAdjacentHTML('beforebegin', rowHtml(el, fields, { [fields[0].key]: line }));
                        wireRow(el, add.previousElementSibling, fields, min, max, notify);
                    });
                    refreshButtons(el, min, max);
                    notify();
                    root.TMH.toast.info(`${lines.length} rows added from your paste`);
                });
            }
        }

        /* drag reorder */
        row.addEventListener('dragstart', (e) => {
            row.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'row');
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            notify();
        });
        row.addEventListener('dragover', (e) => {
            e.preventDefault();
            const dragging = el.querySelector('.is-dragging');
            if (!dragging || dragging === row) return;
            const rect = row.getBoundingClientRect();
            const after = e.clientY > rect.top + rect.height / 2;
            row.parentElement.insertBefore(dragging, after ? row.nextSibling : row);
        });
    }

    function value(el) {
        const fields = fieldsOf(el);
        return [...el.querySelectorAll('.repeater__row')].map((row) => {
            const out = {};
            fields.forEach((f) => {
                const ctrl = row.querySelector(`[data-key="${CSS.escape(f.key)}"]`);
                if (!ctrl) return;
                if (ctrl.type === 'checkbox') {
                    out[f.key] = ctrl.checked;
                    return;
                }
                const raw = ctrl.value.trim();
                /* Empty stays empty. Number('') is 0, which made a blank
                   counter row read as a real counter worth zero — and the
                   all-empty filter below then kept the row, because 0 is
                   neither '' nor false. */
                out[f.key] = f.type === 'number'
                    ? (raw === '' || isNaN(Number(raw)) ? '' : Number(raw))
                    : raw;
            });
            return out;
        }).filter((r) => Object.values(r).some((v) => v !== '' && v !== false));
    }

    /* Mounts every repeater inside `scope` from a record. */
    function mountAll(scope, record) {
        [...(scope || document).querySelectorAll('[data-repeater]')].forEach((el) => {
            render(el, (record && record[el.dataset.repeater]) || []);
        });
    }

    function collectAll(scope) {
        const out = {};
        [...(scope || document).querySelectorAll('[data-repeater]')].forEach((el) => {
            out[el.dataset.repeater] = value(el);
        });
        return out;
    }

    root.TMH.repeater = { mount: render, mountAll, value, collectAll };
}(window));
