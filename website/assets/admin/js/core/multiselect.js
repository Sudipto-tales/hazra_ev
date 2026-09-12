/* =========================================================
   Token multi-select.

   Replaces <select multiple size="6">, which asked the user to
   Ctrl-click inside a scrolling list box: there is no affordance
   for it, deselecting is guesswork, and one stray plain click
   wipes every previous pick without warning.

   Instead: a closed control that opens a searchable checkbox
   menu, and the chosen rows as removable chips underneath —
   the .chip rule in components.css was written for exactly this.

   Declared in markup, filled from JS — same contract as
   core/repeater.js:

     <div class="multiselect" data-multiselect="tags"
          data-options='[{"value":"heart","label":"Heart"}]'
          data-placeholder="Choose tags">
         <input type="hidden" name="tags">
     </div>

     TMH.multiselect.paintAll(scope, record);
     TMH.multiselect.collectAll(scope);   // -> {tags: ['heart', …]}

   The hidden input is the value store, so core/form.js validates
   it like any other named control. It holds a JSON array, or the
   empty string when nothing is picked — empty rather than '[]'
   so a `required` multi-select fails the plain emptiness test in
   checkOne() with no special case there.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.TMH.util;
    const esc = U.esc;

    let seq = 0;

    function optionsOf(el) {
        let raw;
        try {
            raw = JSON.parse(el.dataset.options || '[]');
        } catch (e) {
            console.warn('[multiselect] bad data-options on', el);
            raw = [];
        }
        return raw.map((o) => (typeof o === 'string'
            ? { value: o, label: o }
            : { value: String(o.value), label: String(o.label == null ? o.value : o.label) }));
    }

    function store(el) {
        return el.querySelector('input[type="hidden"]');
    }

    /* Read straight off the hidden input so a value survives a repaint and a
       caller never has to know about the chips. */
    function value(el) {
        const raw = store(el).value;
        if (!raw) return [];
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (e) {
            return [];
        }
    }

    function write(el, values) {
        const opts = optionsOf(el);
        const known = new Set(opts.map((o) => o.value));
        /* Values whose option has since been deleted are dropped rather than
           kept invisibly — a chip you cannot see is a chip you cannot remove. */
        const clean = [...new Set(values.map(String))].filter((v) => known.has(v));
        store(el).value = clean.length ? JSON.stringify(clean) : '';
    }

    /* ---------------------------------------------------------
       render
       --------------------------------------------------------- */
    function build(el) {
        if (el.dataset.built === '1') return;
        el.dataset.built = '1';

        seq += 1;
        const menuId = `ms-menu-${seq}`;
        const hidden = store(el);
        const label = el.dataset.placeholder || 'Choose';

        hidden.insertAdjacentHTML('beforebegin', `
            <button type="button" class="multiselect__control" aria-haspopup="listbox"
                    aria-expanded="false" aria-controls="${menuId}">
                <span class="multiselect__value">${esc(label)}</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="multiselect__menu" id="${menuId}" hidden>
                <div class="multiselect__search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" autocomplete="off" spellcheck="false"
                           placeholder="${esc(el.dataset.searchPlaceholder || 'Search')}"
                           aria-label="${esc(el.dataset.searchPlaceholder || 'Search options')}">
                </div>
                <div class="multiselect__list" role="listbox" aria-multiselectable="true"
                     aria-label="${esc(label)}"></div>
            </div>`);

        el.insertAdjacentHTML('beforeend', '<div class="multiselect__chips"></div>');

        wire(el);
    }

    function paint(el, values) {
        build(el);
        if (values !== undefined) write(el, values || []);
        paintList(el);
        paintChips(el);
        paintControl(el);
    }

    function paintControl(el) {
        const picked = value(el);
        const box = el.querySelector('.multiselect__value');
        box.textContent = picked.length
            ? `${picked.length} selected`
            : (el.dataset.placeholder || 'Choose');
        box.classList.toggle('is-empty', !picked.length);
    }

    function paintList(el) {
        const list = el.querySelector('.multiselect__list');
        const picked = new Set(value(el));
        const q = (el.querySelector('.multiselect__search input').value || '').trim().toLowerCase();
        const rows = optionsOf(el).filter((o) => !q || o.label.toLowerCase().includes(q));

        list.innerHTML = rows.length
            ? rows.map((o) => `
                <div class="multiselect__opt" role="option" tabindex="-1"
                     data-value="${esc(o.value)}" aria-selected="${picked.has(o.value)}">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    <span>${esc(o.label)}</span>
                </div>`).join('')
            : '<p class="multiselect__none">Nothing matches that.</p>';
    }

    function paintChips(el) {
        const wrap = el.querySelector('.multiselect__chips');
        const byValue = new Map(optionsOf(el).map((o) => [o.value, o.label]));
        const picked = value(el);

        wrap.innerHTML = picked.map((v) => `
            <span class="chip" data-chip="${esc(v)}">
                ${esc(byValue.get(v) || v)}
                <button type="button" data-remove="${esc(v)}"
                        aria-label="Remove ${esc(byValue.get(v) || v)}">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </span>`).join('');
        wrap.hidden = !picked.length;
    }

    /* ---------------------------------------------------------
       behaviour
       --------------------------------------------------------- */
    function isOpen(el) {
        return !el.querySelector('.multiselect__menu').hidden;
    }

    function open(el) {
        /* One open menu at a time — two overlapping panels in the same column
           is the state where a click lands on the wrong field. */
        document.querySelectorAll('.multiselect').forEach((other) => {
            if (other !== el && other.dataset.built === '1' && isOpen(other)) close(other);
        });
        el.querySelector('.multiselect__menu').hidden = false;
        el.querySelector('.multiselect__control').setAttribute('aria-expanded', 'true');
        el.classList.add('is-open');
        const search = el.querySelector('.multiselect__search input');
        search.value = '';
        paintList(el);
        search.focus();
    }

    function close(el, refocus) {
        el.querySelector('.multiselect__menu').hidden = true;
        el.querySelector('.multiselect__control').setAttribute('aria-expanded', 'false');
        el.classList.remove('is-open');
        if (refocus) el.querySelector('.multiselect__control').focus();
    }

    function toggleValue(el, v) {
        const picked = value(el);
        const at = picked.indexOf(String(v));
        if (at === -1) picked.push(String(v));
        else picked.splice(at, 1);
        write(el, picked);
        paintList(el);
        paintChips(el);
        paintControl(el);
        /* Bubbles, so the form controller's dirty tracking and any page-level
           preview see it the same way they see a native change. */
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function moveFocus(el, dir) {
        const opts = [...el.querySelectorAll('.multiselect__opt')];
        if (!opts.length) return;
        const at = opts.indexOf(document.activeElement);
        const next = at === -1
            ? (dir > 0 ? 0 : opts.length - 1)
            : (at + dir + opts.length) % opts.length;
        opts[next].focus();
    }

    function wire(el) {
        const control = el.querySelector('.multiselect__control');
        const search = el.querySelector('.multiselect__search input');

        control.addEventListener('click', () => {
            if (isOpen(el)) close(el, true);
            else open(el);
        });

        control.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!isOpen(el)) open(el);
            }
        });

        search.addEventListener('input', () => paintList(el));

        /* Enter in the search box picks the only remaining match — the whole
           point of typing three letters into a list of forty. */
        search.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close(el, true);
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                moveFocus(el, 1);
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                const opts = el.querySelectorAll('.multiselect__opt');
                if (opts.length === 1) toggleValue(el, opts[0].dataset.value);
            }
        });

        el.querySelector('.multiselect__list').addEventListener('click', (e) => {
            const opt = e.target.closest('.multiselect__opt');
            if (opt) toggleValue(el, opt.dataset.value);
        });

        el.querySelector('.multiselect__list').addEventListener('keydown', (e) => {
            const opt = e.target.closest('.multiselect__opt');
            if (!opt) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleValue(el, opt.dataset.value);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                moveFocus(el, 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                moveFocus(el, -1);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                close(el, true);
            }
        });

        el.querySelector('.multiselect__chips').addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove]');
            if (btn) toggleValue(el, btn.dataset.remove);
        });

        el.addEventListener('focusout', (e) => {
            if (!isOpen(el)) return;
            if (e.relatedTarget && el.contains(e.relatedTarget)) return;
            close(el);
        });
    }

    /* A click anywhere else closes the open menu. Capture, because a page may
       stop propagation on its own containers. */
    document.addEventListener('mousedown', (e) => {
        document.querySelectorAll('.multiselect.is-open').forEach((el) => {
            if (!el.contains(e.target)) close(el);
        });
    }, true);

    /* ---------------------------------------------------------
       the bits core/form.js calls
       --------------------------------------------------------- */
    function paintAll(scope, record) {
        [...(scope || document).querySelectorAll('[data-multiselect]')].forEach((el) => {
            const v = record ? record[el.dataset.multiselect] : undefined;
            paint(el, Array.isArray(v) ? v : (v ? [v] : []));
        });
    }

    function collectAll(scope) {
        const out = {};
        [...(scope || document).querySelectorAll('[data-multiselect]')].forEach((el) => {
            out[el.dataset.multiselect] = el.dataset.built === '1' ? value(el) : [];
        });
        return out;
    }

    /* Options that are only known after a fetch (departments, tags, authors). */
    function setOptions(el, options) {
        el.dataset.options = JSON.stringify(options || []);
        if (el.dataset.built !== '1') return;
        write(el, value(el));
        paintList(el);
        paintChips(el);
        paintControl(el);
    }

    root.TMH.multiselect = {
        mount: paint, paintAll, collectAll, value, set: (el, v) => paint(el, v), setOptions,
    };
}(window));
