/* =========================================================
   Small shared helpers. Loaded before every other core file.
   ========================================================= */
(function (root) {
    'use strict';

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    const util = {

        /* Escape for interpolation into innerHTML. Used on every value that
           came from the store — mock data today, user input tomorrow. */
        esc(s) {
            return String(s == null ? '' : s)
                .replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));
        },

        /* Strip tags — for rendering a rich-text excerpt in a table cell. */
        plain(html) {
            const d = document.createElement('div');
            d.innerHTML = html || '';
            return (d.textContent || '').replace(/\s+/g, ' ').trim();
        },

        slug(s) {
            return String(s || '')
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .slice(0, 70);
        },

        /* ---- query string ---- */

        params() {
            return new URLSearchParams(location.search);
        },

        param(key, fallback) {
            const v = new URLSearchParams(location.search).get(key);
            return v === null ? (fallback === undefined ? null : fallback) : v;
        },

        /* Mirrors toolbar/tab state into the URL without adding history
           entries — a filtered list stays shareable and survives reload. */
        setParams(patch) {
            const p = new URLSearchParams(location.search);
            Object.entries(patch).forEach(([k, v]) => {
                if (v === null || v === undefined || v === '' || v === 'all') p.delete(k);
                else p.set(k, v);
            });
            const qs = p.toString();
            history.replaceState(null, '', qs ? `?${qs}` : location.pathname);
        },

        /* ---- dates ---- */

        fmtDate(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            if (isNaN(d)) return String(iso);
            return `${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
        },

        fmtDateTime(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            if (isNaN(d)) return String(iso);
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            return `${d.getDate()} ${MONTHS[d.getMonth()]}, ${hh}:${mm}`;
        },

        /* "3 days ago" — for activity feeds and last-active columns. */
        ago(iso) {
            if (!iso) return '—';
            const then = new Date(iso).getTime();
            if (isNaN(then)) return String(iso);
            const secs = Math.round((Date.now() - then) / 1000);
            if (secs < 60) return 'just now';
            const mins = Math.round(secs / 60);
            if (mins < 60) return `${mins} min ago`;
            const hrs = Math.round(mins / 60);
            if (hrs < 24) return `${hrs} h ago`;
            const days = Math.round(hrs / 24);
            if (days < 30) return `${days} day${days === 1 ? '' : 's'} ago`;
            return util.fmtDate(iso);
        },

        daysUntil(iso) {
            if (!iso) return null;
            const d = new Date(iso).getTime();
            if (isNaN(d)) return null;
            return Math.ceil((d - Date.now()) / 86400000);
        },

        /* yyyy-mm-dd for <input type="date"> */
        dateInput(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d)) return '';
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        },

        /* ---- numbers ---- */

        num(n) {
            return Number(n || 0).toLocaleString('en-IN');
        },

        bytes(n) {
            if (!n) return '—';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            let v = n;
            while (v >= 1024 && i < units.length - 1) {
                v /= 1024;
                i += 1;
            }
            return `${v.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
        },

        /* ---- status badge ---- */

        STATUS: {
            published: { tone: 'ok', label: 'Published' },
            draft: { tone: 'warn', label: 'Draft' },
            hidden: { tone: 'off', label: 'Hidden' },
            scheduled: { tone: 'info', label: 'Scheduled' },
        },

        statusTag(status) {
            const s = util.STATUS[status] || { tone: 'off', label: status || 'Unknown' };
            return `<span class="tag ${s.tone}">${util.esc(s.label)}</span>`;
        },

        /* ---- timing ---- */

        debounce(fn, ms) {
            let t;
            return function (...args) {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), ms || 200);
            };
        },

        /* ---- misc ---- */

        initials(name) {
            return String(name || '?')
                .replace(/^(dr|prof)\.?\s+/i, '')
                .split(/\s+/).slice(0, 2)
                .map((w) => w[0])
                .join('')
                .toUpperCase();
        },

        /* Highlights the matched substring in a search result cell. */
        mark(text, q) {
            const safe = util.esc(text);
            if (!q) return safe;
            const needle = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return safe.replace(new RegExp(`(${needle})`, 'ig'), '<mark>$1</mark>');
        },

        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (e) {
                /* clipboard API needs a secure context; file:// is not one */
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                let ok = false;
                try {
                    ok = document.execCommand('copy');
                } catch (e2) { ok = false; }
                ta.remove();
                return ok;
            }
        },

        /* ---- page-section editors ----
           A page record holds sections[{key, enabled, order, data}]. The form
           is flat, so section data is namespaced `<key>.<field>` on the way in
           and folded back on the way out. */

        flattenSections(page) {
            const flat = {};
            (page.sections || []).forEach((s) => {
                flat[`${s.key}.__enabled`] = s.enabled !== false;
                Object.entries(s.data || {}).forEach(([k, v]) => {
                    flat[`${s.key}.${k}`] = v;
                });
            });
            flat.metaTitle = page.metaTitle || '';
            flat.metaDescription = page.metaDescription || '';
            return flat;
        },

        applySections(page, flat, orderedKeys) {
            const sections = (page.sections || []).map((s) => {
                const data = {};
                Object.keys(flat).forEach((k) => {
                    if (!k.startsWith(`${s.key}.`) || k.endsWith('.__enabled')) return;
                    data[k.slice(s.key.length + 1)] = flat[k];
                });
                return Object.assign({}, s, {
                    enabled: flat[`${s.key}.__enabled`] !== false,
                    data: Object.assign({}, s.data, data),
                });
            });

            if (orderedKeys && orderedKeys.length) {
                sections.sort((a, b) => orderedKeys.indexOf(a.key) - orderedKeys.indexOf(b.key));
            }
            sections.forEach((s, i) => { s.order = i + 1; });

            return Object.assign({}, page, {
                sections,
                metaTitle: flat.metaTitle,
                metaDescription: flat.metaDescription,
            });
        },

        /* Reads one section's data off a page record, for building its fields. */
        sectionData(page, key) {
            const s = (page.sections || []).find((x) => x.key === key);
            return (s && s.data) || {};
        },

        sectionEnabled(page, key) {
            const s = (page.sections || []).find((x) => x.key === key);
            return !s || s.enabled !== false;
        },

        /* Tab wiring. Buttons carry data-tab="panel-id"; panels are
           .tab-panel elements with that id. The active tab is mirrored into
           ?tab= so it is linkable and survives a reload — core/form.js relies
           on that when it focuses a failing field on a hidden tab. */
        wireTabs(scope, opts) {
            const o = opts || {};
            const host = scope || document;
            const buttons = [...host.querySelectorAll('[data-tab]')];
            const panels = [...host.querySelectorAll('.tab-panel')];
            if (!buttons.length) return null;

            const show = (id, push) => {
                buttons.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === id)));
                panels.forEach((p) => { p.hidden = p.id !== id; });
                if (push !== false) util.setParams({ tab: id === buttons[0].dataset.tab ? '' : id });
                if (o.onChange) o.onChange(id);
            };

            buttons.forEach((b) => b.addEventListener('click', () => show(b.dataset.tab)));

            const wanted = util.param('tab');
            const initial = buttons.some((b) => b.dataset.tab === wanted)
                ? wanted : buttons[0].dataset.tab;
            show(initial, false);

            return { show };
        },

        /* The four-tile strip every list screen opens with.
           cards = [[icon, tone, value, label, note], …] */
        statStrip(cards) {
            return `<div class="bento mb-4">${cards.map(([icon, tone, value, label, note]) => `
                <article class="card stat c3 anim-item">
                    <div class="stat__icon ${util.esc(tone)}"><i class="fa-solid ${util.esc(icon)}"></i></div>
                    <h3>${util.esc(value)}</h3>
                    <p>${util.esc(label)}</p>
                    <span class="delta flat">${util.esc(note || '')}</span>
                </article>`).join('')}</div>`;
        },

        /**
         * sortable(container, itemSelector, onDrop)
         * Drag-to-reorder for anything that is not a table — the facility
         * grid, the FAQ accordion. table.js keeps its own copy because a
         * <tr> drop has to respect the tbody; everything else is this.
         *
         * onDrop receives the ids in their new order and is expected to
         * persist them. The DOM is moved first so the drop looks instant;
         * callers re-render afterwards.
         */
        sortable(container, itemSelector, onDrop) {
            if (!container) return;
            let dragged = null;

            const items = () => [...container.querySelectorAll(itemSelector)];

            items().forEach((el) => {
                el.setAttribute('draggable', 'true');

                el.addEventListener('dragstart', (e) => {
                    dragged = el;
                    el.classList.add('is-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    /* Firefox refuses to start a drag with no payload. */
                    e.dataTransfer.setData('text/plain', el.dataset.id || '');
                });

                el.addEventListener('dragend', () => {
                    el.classList.remove('is-dragging');
                    items().forEach((n) => n.classList.remove('drop-target'));
                });

                el.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    if (el !== dragged) el.classList.add('drop-target');
                });

                el.addEventListener('dragleave', () => el.classList.remove('drop-target'));

                el.addEventListener('drop', (e) => {
                    e.preventDefault();
                    el.classList.remove('drop-target');
                    if (!dragged || el === dragged) return;
                    const parent = el.parentElement;
                    const all = [...parent.children];
                    const from = all.indexOf(dragged);
                    const to = all.indexOf(el);
                    parent.insertBefore(dragged, from < to ? el.nextSibling : el);
                    onDrop([...parent.children].map((n) => n.dataset.id).filter(Boolean));
                });
            });
        },

        /* Card entry animation — called after any view renders. */
        stagger(scope) {
            const items = (scope || document).querySelectorAll('.anim-item');
            items.forEach((el, i) => {
                el.style.animationDelay = `${Math.min(i * 45, 400)}ms`;
            });
        },
    };

    root.TMH = root.TMH || {};
    root.TMH.util = util;
}(window));
