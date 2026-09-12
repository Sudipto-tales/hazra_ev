/* =========================================================
   Modals, confirms and drawers.

   Deletes are never one-click anywhere in the panel — they
   route through TMH.confirm(), which is also where a blocked
   delete explains itself by listing its dependents.
   See docs/04-crud-flows.md.
   ========================================================= */
(function (root) {
    'use strict';

    const esc = (s) => String(s == null ? '' : s)
        .replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

    let openCount = 0;

    /* Traps Tab inside `panel` and restores focus to whatever opened it. */
    function trap(root_, panel) {
        const previous = document.activeElement;

        const onKey = (e) => {
            if (e.key !== 'Tab') return;
            const nodes = [...panel.querySelectorAll(FOCUSABLE)].filter((n) => n.offsetParent !== null);
            if (!nodes.length) return;
            const first = nodes[0];
            const last = nodes[nodes.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };

        root_.addEventListener('keydown', onKey);

        const target = panel.querySelector('[data-autofocus]')
            || panel.querySelector(FOCUSABLE);
        if (target) setTimeout(() => target.focus(), 30);

        return () => {
            root_.removeEventListener('keydown', onKey);
            if (previous && previous.focus) previous.focus();
        };
    }

    function lockScroll(on) {
        openCount += on ? 1 : -1;
        openCount = Math.max(0, openCount);
        document.body.style.overflow = openCount ? 'hidden' : '';
    }

    /* Overlays stack in the order they were opened, rather than by which
       class they carry. The media picker is a drawer raised from inside a
       form modal, and the stylesheet puts .drawer-root below .modal-root:
       the picker rendered behind the modal's scrim, which blurred it and
       ate every click on a tile. Each overlay now sits one step above the
       one that opened it, still under the toasts at 100. */
    const Z_BASE = 80;

    function raise(el) {
        el.style.zIndex = String(Z_BASE + openCount);
    }

    /* ---------------------------------------------------------
       confirm() — resolves true/false. Never throws.
       --------------------------------------------------------- */
    function confirmDialog(opts) {
        const o = Object.assign({
            title: 'Are you sure?',
            body: '',
            confirmLabel: 'Confirm',
            cancelLabel: 'Cancel',
            danger: false,
            icon: null,
            dependents: null,      /* array of strings — renders the blocked-delete list */
            blocked: false,        /* true = only a Close button */
            typeToConfirm: null,   /* string the user must type before confirming */
        }, opts || {});

        return new Promise((resolve) => {
            const rootEl = document.createElement('div');
            rootEl.className = 'modal-root';
            rootEl.innerHTML = `
                <div class="modal-root__scrim" data-close></div>
                <div class="modal ${o.danger ? 'modal--danger' : ''}" role="dialog" aria-modal="true" aria-labelledby="mdl-t">
                    <div class="modal__head">
                        <span class="modal__icon"><i class="fa-solid ${o.icon || (o.danger ? 'fa-trash-can' : 'fa-circle-question')}"></i></span>
                        <div>
                            <h3 id="mdl-t">${esc(o.title)}</h3>
                            ${o.body ? `<p>${esc(o.body)}</p>` : ''}
                        </div>
                        <button type="button" class="modal__close" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    ${o.dependents && o.dependents.length ? `
                    <div class="modal__body">
                        <ul class="modal__deps">
                            ${o.dependents.map((d) => `<li>${esc(d)}</li>`).join('')}
                        </ul>
                    </div>` : ''}
                    ${o.typeToConfirm ? `
                    <div class="modal__body">
                        <div class="field">
                            <label for="mdl-type">Type <b>${esc(o.typeToConfirm)}</b> to confirm</label>
                            <input type="text" id="mdl-type" data-autofocus autocomplete="off">
                        </div>
                    </div>` : ''}
                    <div class="modal__foot">
                        <button type="button" class="btn btn--ghost" data-close>${esc(o.blocked ? 'Close' : o.cancelLabel)}</button>
                        ${o.blocked ? '' : `<button type="button" class="btn ${o.danger ? 'btn--danger' : 'btn--primary'}" data-ok ${o.typeToConfirm ? 'disabled' : ''}>${esc(o.confirmLabel)}</button>`}
                    </div>
                </div>`;

            document.body.appendChild(rootEl);
            lockScroll(true);
            raise(rootEl);
            const release = trap(rootEl, rootEl.querySelector('.modal'));

            const close = (result) => {
                release();
                lockScroll(false);
                rootEl.remove();
                resolve(result);
            };

            rootEl.querySelectorAll('[data-close]').forEach((n) =>
                n.addEventListener('click', () => close(false)));

            const okBtn = rootEl.querySelector('[data-ok]');
            if (okBtn) okBtn.addEventListener('click', () => close(true));

            const typeInput = rootEl.querySelector('#mdl-type');
            if (typeInput && okBtn) {
                typeInput.addEventListener('input', () => {
                    okBtn.disabled = typeInput.value.trim().toLowerCase()
                        !== o.typeToConfirm.trim().toLowerCase();
                });
                typeInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !okBtn.disabled) close(true);
                });
            }

            rootEl.addEventListener('keydown', (e) => {
                /* Esc is deliberately still allowed with typeToConfirm — the
                   friction is on confirming, not on backing out. */
                if (e.key === 'Escape') close(false);
            });
        });
    }

    /* ---------------------------------------------------------
       modal.open() — arbitrary content. Resolves with whatever
       the caller passes to the supplied close(value).
       --------------------------------------------------------- */
    function open(opts) {
        const o = Object.assign({
            title: '',
            subtitle: '',
            html: '',
            wide: false,
            icon: 'fa-pen',
            footer: null,          /* html string; buttons wired via data-act */
            onMount: null,         /* fn(panelEl, close) */
        }, opts || {});

        return new Promise((resolve) => {
            const rootEl = document.createElement('div');
            rootEl.className = 'modal-root';
            rootEl.innerHTML = `
                <div class="modal-root__scrim" data-close></div>
                <div class="modal ${o.wide ? 'modal--wide' : ''}" role="dialog" aria-modal="true" aria-labelledby="mdl-t">
                    <div class="modal__head">
                        <span class="modal__icon"><i class="fa-solid ${o.icon}"></i></span>
                        <div>
                            <h3 id="mdl-t">${esc(o.title)}</h3>
                            ${o.subtitle ? `<p>${esc(o.subtitle)}</p>` : ''}
                        </div>
                        <button type="button" class="modal__close" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="modal__body">${o.html}</div>
                    ${o.footer ? `<div class="modal__foot">${o.footer}</div>` : ''}
                </div>`;

            document.body.appendChild(rootEl);
            lockScroll(true);
            raise(rootEl);
            const panel = rootEl.querySelector('.modal');
            const release = trap(rootEl, panel);

            const close = (value) => {
                release();
                lockScroll(false);
                rootEl.remove();
                resolve(value);
            };

            rootEl.querySelectorAll('[data-close]').forEach((n) =>
                n.addEventListener('click', () => close(undefined)));

            rootEl.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close(undefined);
            });

            if (o.onMount) o.onMount(panel, close);
        });
    }

    /* ---------------------------------------------------------
       drawer() — right sheet for record detail without leaving
       the list. Bottom sheet under 640px (CSS).
       --------------------------------------------------------- */
    function drawer(opts) {
        const o = Object.assign({
            title: '',
            html: '',
            footer: null,
            onMount: null,
        }, opts || {});

        return new Promise((resolve) => {
            const rootEl = document.createElement('div');
            rootEl.className = 'drawer-root';
            rootEl.innerHTML = `
                <div class="modal-root__scrim" data-close></div>
                <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="drw-t">
                    <div class="drawer__head">
                        <h3 id="drw-t" class="grow">${esc(o.title)}</h3>
                        <button type="button" class="icon-btn" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="drawer__body">${o.html}</div>
                    ${o.footer ? `<div class="drawer__foot">${o.footer}</div>` : ''}
                </aside>`;

            document.body.appendChild(rootEl);
            lockScroll(true);
            raise(rootEl);
            const panel = rootEl.querySelector('.drawer');
            const release = trap(rootEl, panel);

            const close = (value) => {
                release();
                lockScroll(false);
                rootEl.remove();
                resolve(value);
            };

            rootEl.querySelectorAll('[data-close]').forEach((n) =>
                n.addEventListener('click', () => close(undefined)));

            rootEl.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close(undefined);
            });

            if (o.onMount) o.onMount(panel, close);
        });
    }

    root.TMH = root.TMH || {};
    root.TMH.confirm = confirmDialog;
    root.TMH.modal = { open, drawer };
}(window));
