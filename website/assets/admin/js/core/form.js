/* =========================================================
   Form plumbing: bind, collect, validate, dirty-guard and the
   sticky action bar.

   Deliberately not a framework. Page JS still owns its save
   logic — this owns the parts every form gets wrong when they
   are rewritten 30 times: validation timing, the leave guard,
   and the busy state on the button.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.HAZRA.util;

    /* ---------------------------------------------------------
       Validation rules. `data-rule` on a control picks one.
       --------------------------------------------------------- */
    const RULES = {
        slug: {
            test: (v) => !v || /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(v),
            message: 'Lowercase letters, numbers and hyphens only',
        },
        email: {
            test: (v) => !v || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v),
            message: 'That does not look like an email address',
        },
        phone: {
            test: (v) => !v || /^[+]?[\d\s()-]{7,20}$/.test(v),
            message: 'Digits, spaces, + ( ) and - only',
        },
        url: {
            test: (v) => {
                if (!v) return true;
                if (/^[/.]/.test(v)) return true;   /* relative links are fine */
                try {
                    return !!new URL(v);
                } catch (e) {
                    return false;
                }
            },
            message: 'Enter a full URL, or a path starting with /',
        },
        number: {
            test: (v) => !v || !isNaN(Number(v)),
            message: 'Numbers only',
        },
    };

    function fieldOf(control) {
        return control.closest('.field') || control.parentElement;
    }

    function setError(control, message) {
        const field = fieldOf(control);
        if (!field) return;
        field.classList.toggle('is-invalid', !!message);
        let slot = field.querySelector('.field__error');
        if (!slot && message) {
            slot = document.createElement('small');
            slot.className = 'field__error';
            field.appendChild(slot);
        }
        if (slot) {
            slot.innerHTML = message
                ? `<i class="fa-solid fa-circle-exclamation"></i> ${U.esc(message)}`
                : '';
        }
        control.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    /* Returns an error string, or '' when the control is fine. */
    function checkOne(control, opts) {
        const o = opts || {};
        const value = control.type === 'checkbox' ? control.checked : String(control.value || '').trim();

        const requiredNow = control.hasAttribute('required')
            || (o.publish && control.dataset.requiredToPublish !== undefined);

        if (requiredNow && (value === '' || value === false)) {
            return control.dataset.requiredMessage || 'This field is required';
        }

        const ruleName = control.dataset.rule;
        if (ruleName && RULES[ruleName] && !RULES[ruleName].test(value)) {
            return RULES[ruleName].message;
        }

        /* Compared against '' rather than truthiness: `min: 0` is the common
           case on a price or a salary, and `if (dataset.min)` skipped it. */
        if (value !== '' && control.dataset.min !== undefined && control.dataset.min !== '') {
            if (Number(value) < Number(control.dataset.min)) {
                return `Must be at least ${control.dataset.min}`;
            }
        }

        if (value !== '' && control.dataset.maxValue !== undefined && control.dataset.maxValue !== '') {
            if (Number(value) > Number(control.dataset.maxValue)) {
                return `Must be ${control.dataset.maxValue} or less`;
            }
        }

        if (control.dataset.matchAfter) {
            const other = control.form && control.form.querySelector(`[name="${control.dataset.matchAfter}"]`);
            if (other && other.value && value && new Date(value) <= new Date(other.value)) {
                return 'Must be after the date above';
            }
        }

        return '';
    }

    function controls(scope) {
        return [...scope.querySelectorAll('input, select, textarea')]
            .filter((c) => c.name && c.type !== 'search' && !c.disabled);
    }

    /* ---------------------------------------------------------
       bind / collect
       --------------------------------------------------------- */
    function bind(scope, record) {
        const data = record || {};
        controls(scope).forEach((c) => {
            const v = data[c.name];
            if (c.type === 'checkbox') c.checked = !!v;
            else if (c.type === 'radio') c.checked = String(c.value) === String(v);
            else if (c.type === 'date') c.value = U.dateInput(v);
            else if (c.multiple && c.tagName === 'SELECT') {
                const set = new Set((v || []).map(String));
                [...c.options].forEach((o) => { o.selected = set.has(o.value); });
            } else if (c.closest('[data-multiselect]')) {
                /* Left alone — multiselect.paintAll below writes the hidden
                   input itself. Assigning an array here would stringify it to
                   "a,b" and the chips would repaint from nonsense. */
            } else c.value = v == null ? '' : v;
        });

        /* contenteditable editors and media pickers carry their value in a
           hidden input, so they are covered by the loop above; this repaints
           their visible half. */
        scope.querySelectorAll('[data-editor]').forEach((el) => {
            const name = el.dataset.editor;
            const body = el.querySelector('.editor__body');
            if (body) body.innerHTML = data[name] || '';
        });

        if (root.HAZRA.repeater) root.HAZRA.repeater.mountAll(scope, data);
        if (root.HAZRA.media) root.HAZRA.media.paintAll(scope, data);
        if (root.HAZRA.multiselect) root.HAZRA.multiselect.paintAll(scope, data);

        refreshMeters(scope);
    }

    function collect(scope) {
        const out = {};
        controls(scope).forEach((c) => {
            if (c.type === 'checkbox') out[c.name] = c.checked;
            else if (c.type === 'radio') {
                if (c.checked) out[c.name] = c.value;
            } else if (c.multiple && c.tagName === 'SELECT') {
                out[c.name] = [...c.selectedOptions].map((o) => o.value);
            } else if (c.type === 'number') {
                out[c.name] = c.value === '' ? null : Number(c.value);
            } else out[c.name] = c.value.trim();
        });

        scope.querySelectorAll('[data-editor]').forEach((el) => {
            const body = el.querySelector('.editor__body');
            if (body) out[el.dataset.editor] = body.innerHTML.trim();
        });

        /* Both overwrite the raw control value the loop above already wrote:
           the hidden input holds a JSON string, the record wants the array. */
        if (root.HAZRA.multiselect) Object.assign(out, root.HAZRA.multiselect.collectAll(scope));
        if (root.HAZRA.repeater) Object.assign(out, root.HAZRA.repeater.collectAll(scope));
        return out;
    }

    /* ---------------------------------------------------------
       validate — returns {ok, errors, first}
       --------------------------------------------------------- */
    function validate(scope, opts) {
        const errors = {};
        let first = null;

        controls(scope).forEach((c) => {
            const msg = checkOne(c, opts);
            setError(c, msg);
            if (msg) {
                errors[c.name] = msg;
                if (!first) first = c;
            }
        });

        return { ok: !first, errors, first, count: Object.keys(errors).length };
    }

    /* Focus the first bad field, open the tab it lives in, and say how many
       there are. The toast alone is not enough — the user needs to be taken
       to the problem. */
    /* A hidden input cannot take focus and has no box to scroll to, so a bad
       media picker or multi-select used to report "1 field needs attention"
       and then leave the user on whatever part of the form they were on.
       Media pickers, multi-selects and editors all keep their value in one,
       so this is the normal case, not an edge one. */
    function focusTarget(control) {
        if (control.type !== 'hidden') return control;
        const field = fieldOf(control);
        return (field && field.querySelector(
            '.multiselect__control, .media-pick, .editor__body, button, a',
        )) || field || control;
    }

    function reportInvalid(scope, result) {
        if (result.ok) return;
        const panel = result.first.closest('.tab-panel');
        if (panel && panel.id) {
            const trigger = document.querySelector(`[data-tab="${panel.id}"]`);
            if (trigger) trigger.click();
        }
        const target = focusTarget(result.first);
        if (typeof target.focus === 'function') target.focus();
        target.scrollIntoView({ block: 'center', behavior: 'smooth' });
        root.HAZRA.toast.error(
            `${result.count} field${result.count === 1 ? '' : 's'} need${result.count === 1 ? 's' : ''} attention`,
        );
    }

    /* ---------------------------------------------------------
       character meters
       --------------------------------------------------------- */
    function refreshMeters(scope) {
        scope.querySelectorAll('[data-max]').forEach((c) => {
            const field = fieldOf(c);
            let meter = field.querySelector('.field__meter');
            if (!meter) {
                meter = document.createElement('small');
                meter.className = 'field__meter';
                field.appendChild(meter);
            }
            const max = Number(c.dataset.max);
            const len = String(c.value || '').length;
            meter.textContent = `${len} / ${max}`;
            meter.classList.toggle('is-over', len > max);
        });
    }

    /* ---------------------------------------------------------
       controller — dirty tracking, guard, action bar
       --------------------------------------------------------- */
    function create(config) {
        const cfg = Object.assign({
            el: null,
            mount: null,
            fields: null,
            initial: null,
            bar: null,            /* the .form-bar element */
            onSave: null,         /* async fn(data, {publish}) */
            onCancel: null,
            autosaveKey: null,
            validateOnBlur: true,
        }, config);

        if (cfg.mount && cfg.fields && !cfg.el) {
            const mountEl = typeof cfg.mount === 'string' ? document.querySelector(cfg.mount) : cfg.mount;
            if (mountEl) {
                const fieldsHtml = cfg.fields.map(f => {
                    const req = f.required ? 'required' : '';
                    const label = f.label ? `<label for="${U.esc(f.name)}" style="font-size:12px;font-weight:700;margin-bottom:6px;display:block;color:var(--text-main);">${U.esc(f.label)} ${f.required ? '<span style="color:var(--accent-red)">*</span>' : ''}</label>` : '';
                    let inputHtml = '';
                    if (f.type === 'wysiwyg' || f.type === 'editor') {
                        inputHtml = `<div data-editor="${U.esc(f.name)}" data-placeholder="${U.esc(f.placeholder || 'Start writing…')}"></div>`;
                    } else if (f.type === 'textarea') {
                        inputHtml = `<textarea id="${U.esc(f.name)}" name="${U.esc(f.name)}" class="input" rows="4" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--hairline);background:var(--surface-2);font-family:inherit;font-size:13px;" ${req}></textarea>`;
                    } else if (f.type === 'select') {
                        const opts = (f.options || []).map(o => {
                            const val = typeof o === 'object' ? o.value : o;
                            const lbl = typeof o === 'object' ? o.label : o;
                            return `<option value="${U.esc(val)}">${U.esc(lbl)}</option>`;
                        }).join('');
                        inputHtml = `<select id="${U.esc(f.name)}" name="${U.esc(f.name)}" class="input" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--hairline);background:var(--surface-2);font-family:inherit;font-size:13px;">${opts}</select>`;
                    } else if (f.type === 'image' || f.type === 'media') {
                        return root.HAZRA.fields ? root.HAZRA.fields.media(f) : `
                            <div class="field" style="margin-bottom:16px;">
                                ${label}
                                <div class="media-pick" data-media="${U.esc(f.name)}"></div>
                                <input type="hidden" name="${U.esc(f.name)}" ${req}>
                            </div>`;
                    } else if (f.type === 'checkbox') {
                        return `<div class="field" style="margin-bottom:16px;">
                            <label class="checkbox" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="${U.esc(f.name)}" name="${U.esc(f.name)}" value="1">
                                <span style="font-weight:600;font-size:13px;color:var(--text-main);">${U.esc(f.label)}</span>
                            </label>
                        </div>`;
                    } else {
                        inputHtml = `<input type="${U.esc(f.type || 'text')}" id="${U.esc(f.name)}" name="${U.esc(f.name)}" class="input" style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--hairline);background:var(--surface-2);font-family:inherit;font-size:13px;" ${req}>`;
                    }
                    return `<div class="field" style="margin-bottom:16px;">
                        ${label}
                        ${inputHtml}
                    </div>`;
                }).join('');

                mountEl.innerHTML = `
                    <form id="autoGeneratedForm" novalidate style="max-width:760px;padding:28px;background:var(--surface-1);border:1px solid var(--hairline);border-radius:18px;" class="card">
                        ${fieldsHtml}
                        <div style="display:flex;gap:12px;margin-top:24px;">
                            <button type="submit" class="btn btn--primary" id="formSubmitBtn" style="padding:10px 24px;border-radius:999px;font-weight:700;cursor:pointer;">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                            ${cfg.onCancel ? `<button type="button" class="btn btn--ghost" id="formCancelBtn" style="padding:10px 20px;border-radius:999px;cursor:pointer;">Cancel</button>` : ''}
                        </div>
                    </form>
                `;
                cfg.el = mountEl.querySelector('#autoGeneratedForm');
                if (root.HAZRA && root.HAZRA.editor) {
                    root.HAZRA.editor.upgradeAll(cfg.el);
                }
                if (root.HAZRA && root.HAZRA.media) {
                    root.HAZRA.media.wire(cfg.el);
                }
                if (cfg.onCancel) {
                    const cancelBtn = mountEl.querySelector('#formCancelBtn');
                    if (cancelBtn) cancelBtn.addEventListener('click', cfg.onCancel);
                }
            }
        }

        const scope = typeof cfg.el === 'string' ? document.querySelector(cfg.el) : cfg.el;
        if (root.HAZRA && root.HAZRA.editor && scope) {
            root.HAZRA.editor.upgradeAll(scope);
        }
        if (root.HAZRA && root.HAZRA.media && scope) {
            root.HAZRA.media.wire(scope);
        }
        if (cfg.initial && scope) {
            bind(scope, cfg.initial);
        }
        if (scope) {
            scope.addEventListener('submit', (e) => {
                e.preventDefault();
                submit(true, scope.querySelector('button[type="submit"]'));
            });
        }
        const bar = typeof cfg.bar === 'string' ? document.querySelector(cfg.bar) : cfg.bar;

        let snapshot = '';
        let dirty = false;
        let saving = false;

        const statusEl = bar && bar.querySelector('.form-bar__status');

        function markClean() {
            snapshot = JSON.stringify(collect(scope));
            dirty = false;
            paintDirty();
        }

        function paintDirty() {
            if (statusEl) {
                statusEl.innerHTML = dirty
                    ? '<span class="dot"></span> Unsaved changes'
                    : '<i class="fa-solid fa-check" style="color:var(--good)"></i> All changes saved';
            }
            scope.dataset.dirty = dirty ? 'true' : 'false';
        }

        function checkDirty() {
            dirty = JSON.stringify(collect(scope)) !== snapshot;
            paintDirty();
        }

        const onChange = U.debounce(() => {
            refreshMeters(scope);
            checkDirty();
        }, 120);

        scope.addEventListener('input', onChange);
        scope.addEventListener('change', onChange);

        if (cfg.validateOnBlur) {
            scope.addEventListener('blur', (e) => {
                const c = e.target;
                if (!c.name || !/^(INPUT|SELECT|TEXTAREA)$/.test(c.tagName)) return;
                setError(c, checkOne(c, {}));
            }, true);
        }

        /* Leave guards. The in-app one covers sidebar clicks; beforeunload
           covers the tab close and the browser back button. */
        root.addEventListener('beforeunload', (e) => {
            if (!dirty || saving) return;
            e.preventDefault();
            e.returnValue = '';
        });

        document.addEventListener('click', async (e) => {
            const link = e.target.closest('a[href]');
            if (!link || !dirty || saving) return;
            if (link.target === '_blank' || link.getAttribute('href').startsWith('#')) return;
            e.preventDefault();
            const leave = await root.HAZRA.confirm({
                title: 'Discard your changes?',
                body: 'This form has edits that have not been saved.',
                danger: true,
                icon: 'fa-triangle-exclamation',
                confirmLabel: 'Discard and leave',
                cancelLabel: 'Keep editing',
            });
            if (leave) {
                dirty = false;
                location.href = link.href;
            }
        }, true);

        /* Autosave a local draft so a closed tab does not lose an hour of
           writing. Cleared on a successful save. */
        let autosaveTimer = null;
        if (cfg.autosaveKey) {
            autosaveTimer = setInterval(() => {
                if (!dirty) return;
                try {
                    localStorage.setItem(`hazra-draft:${cfg.autosaveKey}`,
                        JSON.stringify({ at: Date.now(), data: collect(scope) }));
                } catch (e) { /* quota — nothing useful to do */ }
            }, 20000);
        }

        function restorableDraft() {
            if (!cfg.autosaveKey) return null;
            try {
                const raw = localStorage.getItem(`hazra-draft:${cfg.autosaveKey}`);
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        }

        function clearDraft() {
            if (cfg.autosaveKey) localStorage.removeItem(`hazra-draft:${cfg.autosaveKey}`);
        }

        async function submit(publish, btn) {
            if (saving) return;
            const result = validate(scope, { publish });
            if (!result.ok) {
                reportInvalid(scope, result);
                return;
            }

            saving = true;
            if (btn) btn.classList.add('is-busy');
            try {
                await cfg.onSave(collect(scope), { publish });
                clearDraft();
                markClean();
            } catch (err) {
                /* Field-level errors from the store (duplicate slug) land back
                   on the control that caused them. */
                if (err && err.fields) {
                    Object.entries(err.fields).forEach(([name, msg]) => {
                        const c = scope.querySelector(`[name="${name}"]`);
                        if (c) setError(c, msg);
                    });
                }
                root.HAZRA.toast.error(err && err.message ? err.message : 'Could not save', {
                    action: { label: 'Retry', onClick: () => submit(publish, btn) },
                });
            } finally {
                saving = false;
                if (btn) btn.classList.remove('is-busy');
            }
        }

        if (bar) {
            const draftBtn = bar.querySelector('[data-save-draft]');
            const pubBtn = bar.querySelector('[data-publish]');
            const cancelBtn = bar.querySelector('[data-cancel]');
            if (draftBtn) draftBtn.addEventListener('click', () => submit(false, draftBtn));
            if (pubBtn) pubBtn.addEventListener('click', () => submit(true, pubBtn));
            if (cancelBtn) cancelBtn.addEventListener('click', () => {
                if (cfg.onCancel) cfg.onCancel();
            });
        }

        /* Ctrl/Cmd+S saves a draft — the reflex every editor has. */
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                submit(false, bar && bar.querySelector('[data-save-draft]'));
            }
        });

        return {
            scope,
            bind(record) {
                bind(scope, record);
                markClean();
            },
            collect: () => collect(scope),
            validate: (opts) => validate(scope, opts),
            markClean,
            get dirty() { return dirty; },
            restorableDraft,
            clearDraft,
            submit,
            destroy() {
                if (autosaveTimer) clearInterval(autosaveTimer);
            },
        };
    }

    /* ---------------------------------------------------------
       editModal — add/edit inside a dialog rather than on a page
       of its own. Used by every entity whose whole record fits
       on one screen: facilities, FAQs, testimonials, lab tests,
       categories, counters, redirects, nav links.

         const saved = await HAZRA.form.editModal({
             title: 'Add facility', icon: 'fa-bed-pulse',
             html: HAZRA.fields.section({fields: […]}),
             record,                       // null = create
             onReady(scope) { … },         // optional; after fields are bound
         });
         // -> collected data object, or undefined if cancelled
       --------------------------------------------------------- */
    function editModal(opts) {
        const o = opts || {};
        return root.HAZRA.modal.open({
            title: o.title || (o.record ? 'Edit' : 'Add'),
            subtitle: o.subtitle || '',
            icon: o.icon || (o.record ? 'fa-pen' : 'fa-plus'),
            wide: o.wide !== false,
            html: `<form id="modalForm" novalidate>${o.html}</form>`,
            footer: `
                <button type="button" class="btn btn--ghost" data-close>Cancel</button>
                <button type="button" class="btn btn--primary" data-ok>
                    ${U.esc(o.saveLabel || (o.record ? 'Save changes' : 'Add'))}</button>`,
            onMount(panel, close) {
                const scope = panel.querySelector('#modalForm');

                /* Editors are upgraded before the record is bound, never
                   after: upgrade() rebuilds the host from its own innerHTML,
                   so binding first would write the value into a div that is
                   about to be thrown away, and the field would open empty.
                   blog-form.js does the same in the same order. */
                if (root.HAZRA.editor) root.HAZRA.editor.upgradeAll(scope);
                bind(scope, o.record || o.defaults || {});
                if (root.HAZRA.fields) root.HAZRA.fields.wirePreviews(scope);
                if (root.HAZRA.media) root.HAZRA.media.wire(scope);

                /* For a dialog whose fields depend on each other — the gallery
                   asks for a YouTube id or a video file or neither, depending
                   on what kind of item it is. Called after binding, so the
                   hook sees the record's own values and can hide the two
                   thirds of the form that do not apply to it. Screens without
                   that problem pass nothing and never notice. */
                if (typeof o.onReady === 'function') o.onReady(scope, panel);

                scope.addEventListener('blur', (e) => {
                    const c = e.target;
                    if (!c.name || !/^(INPUT|SELECT|TEXTAREA)$/.test(c.tagName)) return;
                    setError(c, checkOne(c, {}));
                }, true);

                const ok = panel.querySelector('[data-ok]');
                ok.addEventListener('click', () => {
                    const result = validate(scope, {});
                    if (!result.ok) {
                        focusTarget(result.first).focus();
                        root.HAZRA.toast.error(
                            `${result.count} field${result.count === 1 ? '' : 's'} need${result.count === 1 ? 's' : ''} attention`,
                        );
                        return;
                    }
                    close(collect(scope));
                });

                /* Enter submits from any single-line input — a six-field
                   dialog should not need a mouse. Not from inside a
                   multi-select though: there Enter picks the row you have
                   filtered down to, and submitting the dialog instead would
                   close it on the keystroke that was meant to choose. */
                scope.addEventListener('keydown', (e) => {
                    if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') return;
                    if (e.target.closest('.multiselect')) return;
                    e.preventDefault();
                    ok.click();
                });
            },
        });
    }

    root.HAZRA.form = {
        create, bind, collect, validate, reportInvalid, setError, editModal, RULES,
    };
}(window));
