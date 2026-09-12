/* =========================================================
   Toast notifications.

   Every mutation in the panel ends in one of these — there is
   no silent success. See docs/05-components.md for the rules
   and docs/04-crud-flows.md for the message table.

     TMH.toast.success('Doctor published', {
         action: { label: 'View on site', href: '…' }
     });
     TMH.toast.success('Doctor deleted', { undo: () => restore() });
     TMH.toast.error('Could not save', { action: { label: 'Retry', onClick: save } });
     TMH.toast.warning('Site is in maintenance mode', { persistent: true, id: 'maint' });
   ========================================================= */
(function (root) {
    'use strict';

    const MAX_VISIBLE = 3;
    const ICONS = {
        success: 'fa-check',
        error: 'fa-triangle-exclamation',
        warning: 'fa-circle-exclamation',
        info: 'fa-circle-info',
    };

    const queue = [];
    const live = new Map();   /* id -> {el, timer} */
    let seq = 0;
    let mount = null;

    const esc = (s) => String(s == null ? '' : s)
        .replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function container() {
        if (mount && document.body.contains(mount)) return mount;
        mount = document.createElement('div');
        mount.className = 'toast-root';
        /* polite, not assertive: a save confirmation should not interrupt a
           screen reader mid-sentence. show() upgrades errors to assertive. */
        mount.setAttribute('role', 'region');
        mount.setAttribute('aria-label', 'Notifications');
        mount.setAttribute('aria-live', 'polite');
        document.body.appendChild(mount);
        return mount;
    }

    function dismiss(id) {
        const entry = live.get(id);
        if (!entry) return;
        clearTimeout(entry.timer);
        live.delete(id);
        entry.el.classList.add('is-leaving');
        entry.el.addEventListener('animationend', () => {
            entry.el.remove();
            drain();
        }, { once: true });
        /* Belt and braces — if the animation is suppressed by reduced motion
           the animationend may never fire. */
        setTimeout(() => {
            if (entry.el.isConnected) {
                entry.el.remove();
                drain();
            }
        }, 400);
    }

    function drain() {
        while (live.size < MAX_VISIBLE && queue.length) render(queue.shift());
    }

    function render(opts) {
        const id = opts.id || `t${++seq}`;

        /* Same id replaces rather than stacks — this is what stops a
           repeated autosave toast from filling the screen. */
        if (live.has(id)) dismiss(id);

        const hasAction = !!(opts.action || opts.undo);
        const duration = opts.persistent ? 0
            : (opts.duration || (hasAction ? 8000 : 4000));

        const el = document.createElement('div');
        el.className = `toast toast--${opts.type}`;
        el.dataset.id = id;
        if (opts.type === 'error') el.setAttribute('aria-live', 'assertive');

        const actionLabel = opts.undo ? 'Undo' : (opts.action && opts.action.label);
        const actionTag = opts.action && opts.action.href ? 'a' : 'button';
        const actionAttrs = opts.action && opts.action.href
            ? ` href="${esc(opts.action.href)}" target="_blank" rel="noopener"`
            : ' type="button"';

        el.innerHTML = `
            <span class="toast__icon"><i class="fa-solid ${ICONS[opts.type]}"></i></span>
            <div class="toast__main">
                <div class="toast__title">${esc(opts.title)}</div>
                ${opts.body ? `<div class="toast__body">${esc(opts.body)}</div>` : ''}
                ${actionLabel ? `<${actionTag} class="toast__action" data-act${actionAttrs}>${esc(actionLabel)}</${actionTag}>` : ''}
            </div>
            <button type="button" class="toast__close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
            ${duration ? `<span class="toast__progress" style="animation-duration:${duration}ms"></span>` : ''}`;

        el.querySelector('.toast__close').addEventListener('click', () => dismiss(id));

        const actEl = el.querySelector('[data-act]');
        if (actEl) {
            actEl.addEventListener('click', () => {
                if (opts.undo) opts.undo();
                else if (opts.action && opts.action.onClick) opts.action.onClick();
                dismiss(id);
            });
        }

        /* Esc closes the toast the user is focused inside, not all of them. */
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') dismiss(id);
        });

        container().appendChild(el);

        const entry = { el, timer: null };
        live.set(id, entry);

        if (duration) {
            let remaining = duration;
            let startedAt = Date.now();
            const start = () => {
                startedAt = Date.now();
                entry.timer = setTimeout(() => dismiss(id), remaining);
            };
            const pause = () => {
                clearTimeout(entry.timer);
                remaining -= Date.now() - startedAt;
            };
            /* The CSS progress bar pauses on :hover/:focus-within already;
               these keep the JS timer in step with it. */
            el.addEventListener('mouseenter', pause);
            el.addEventListener('mouseleave', start);
            el.addEventListener('focusin', pause);
            el.addEventListener('focusout', start);
            start();
        }

        return id;
    }

    function show(type, title, opts) {
        const config = Object.assign({ type, title }, opts || {});
        if (live.size >= MAX_VISIBLE && !config.persistent) {
            queue.push(config);
            return config.id || null;
        }
        return render(config);
    }

    root.TMH = root.TMH || {};
    root.TMH.toast = {
        success: (title, opts) => show('success', title, opts),
        error: (title, opts) => show('error', title, opts),
        warning: (title, opts) => show('warning', title, opts),
        info: (title, opts) => show('info', title, opts),
        dismiss,
        clear() {
            queue.length = 0;
            [...live.keys()].forEach(dismiss);
        },
    };
}(window));
