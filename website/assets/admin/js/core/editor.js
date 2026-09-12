/* =========================================================
   The writing pad.

   A contenteditable region with a sticky toolbar. No third-
   party dependency, because the one behaviour that actually
   matters here — sanitising paste — is the thing a generic
   editor gets wrong for this site. Text pasted from Word or
   Google Docs arrives carrying inline font stacks and colour;
   left alone it silently breaks the article typography on
   the article page, /blog/{slug}.

   Upgrades <div data-editor="body"> in place. core/form.js
   already reads and writes .editor__body, so nothing else
   needs to know this ran.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.TMH.util;
    const esc = U.esc;

    const ALLOWED = new Set(['P', 'BR', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'U',
        'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'IMG', 'HR', 'FIGURE', 'FIGCAPTION', 'DIV']);

    const TOOLS = [
        { cmd: 'bold', icon: 'fa-bold', title: 'Bold (Ctrl+B)' },
        { cmd: 'italic', icon: 'fa-italic', title: 'Italic (Ctrl+I)' },
        { cmd: 'underline', icon: 'fa-underline', title: 'Underline' },
        { sep: true },
        { cmd: 'insertUnorderedList', icon: 'fa-list-ul', title: 'Bulleted list' },
        { cmd: 'insertOrderedList', icon: 'fa-list-ol', title: 'Numbered list' },
        { block: 'BLOCKQUOTE', icon: 'fa-quote-left', title: 'Quote' },
        { sep: true },
        { action: 'link', icon: 'fa-link', title: 'Insert link' },
        { action: 'image', icon: 'fa-image', title: 'Insert image' },
        { action: 'callout', icon: 'fa-circle-info', title: 'Callout box' },
        { action: 'hr', icon: 'fa-minus', title: 'Divider' },
        { sep: true },
        { cmd: 'removeFormat', icon: 'fa-eraser', title: 'Clear formatting' },
        { cmd: 'undo', icon: 'fa-rotate-left', title: 'Undo' },
        { cmd: 'redo', icon: 'fa-rotate-right', title: 'Redo' },
    ];

    /* ---------------------------------------------------------
       Paste sanitiser. Walks the pasted fragment and rebuilds
       it from the allowed tag set, dropping every attribute
       except href/src/alt.
       --------------------------------------------------------- */
    function sanitise(html) {
        const src = document.createElement('div');
        src.innerHTML = html;

        (function walk(node) {
            [...node.childNodes].forEach((child) => {
                if (child.nodeType === Node.COMMENT_NODE) {
                    child.remove();
                    return;
                }
                if (child.nodeType !== Node.ELEMENT_NODE) return;

                walk(child);

                if (!ALLOWED.has(child.tagName)) {
                    /* Unwrap rather than delete — the text inside a <span> is
                       usually the whole point of the paste. */
                    child.replaceWith(...child.childNodes);
                    return;
                }

                [...child.attributes].forEach((a) => {
                    const keep = (child.tagName === 'A' && a.name === 'href')
                        || (child.tagName === 'IMG' && (a.name === 'src' || a.name === 'alt'))
                        || (child.tagName === 'DIV' && a.name === 'class' && a.value === 'callout');
                    if (!keep) child.removeAttribute(a.name);
                });

                /* javascript: URLs never survive a paste. */
                if (child.tagName === 'A') {
                    const href = child.getAttribute('href') || '';
                    if (/^\s*javascript:/i.test(href)) child.removeAttribute('href');
                }
            });
        }(src));

        return src.innerHTML.replace(/\s+/g, ' ').replace(/>\s+</g, '><');
    }

    function toolbarHtml() {
        return `
        <div class="editor__bar">
            <select data-block aria-label="Text style">
                <option value="P">Paragraph</option>
                <option value="H2">Heading 2</option>
                <option value="H3">Heading 3</option>
                <option value="H4">Heading 4</option>
            </select>
            ${TOOLS.map((t) => (t.sep
                ? '<span class="sep"></span>'
                : `<button type="button" title="${esc(t.title)}" aria-label="${esc(t.title)}"
                        ${t.cmd ? `data-cmd="${esc(t.cmd)}"` : ''}
                        ${t.block ? `data-blockcmd="${esc(t.block)}"` : ''}
                        ${t.action ? `data-action="${esc(t.action)}"` : ''}>
                        <i class="fa-solid ${esc(t.icon)}"></i></button>`)).join('')}
            <span class="grow"></span>
            <button type="button" data-action="full" title="Full screen" aria-label="Full screen">
                <i class="fa-solid fa-expand"></i></button>
        </div>`;
    }

    function upgrade(host) {
        if (host.dataset.upgraded) return;
        host.dataset.upgraded = '1';

        const name = host.dataset.editor;
        const placeholder = host.dataset.placeholder || 'Start writing…';
        const initial = host.innerHTML;

        host.className = 'editor';
        host.innerHTML = `
            ${toolbarHtml()}
            <div class="editor__body" contenteditable="true" role="textbox" aria-multiline="true"
                 aria-label="${esc(name)}" data-placeholder="${esc(placeholder)}">${initial}</div>
            <div class="editor__foot">
                <span data-words>0 words</span>
                <span data-chars>0 characters</span>
                <span data-read>0 min read</span>
            </div>`;

        const body = host.querySelector('.editor__body');
        const bar = host.querySelector('.editor__bar');
        const blockSel = host.querySelector('[data-block]');

        const exec = (cmd, value) => {
            body.focus();
            document.execCommand(cmd, false, value || null);
            sync();
        };

        function sync() {
            const words = U.plain(body.innerHTML).split(/\s+/).filter(Boolean).length;
            host.querySelector('[data-words]').textContent = `${words} words`;
            host.querySelector('[data-chars]').textContent = `${U.plain(body.innerHTML).length} characters`;
            host.querySelector('[data-read]').textContent = `${Math.max(1, Math.round(words / 200))} min read`;
            host.dispatchEvent(new Event('input', { bubbles: true }));
            paintActive();
        }

        function paintActive() {
            bar.querySelectorAll('[data-cmd]').forEach((b) => {
                let on = false;
                try {
                    on = document.queryCommandState(b.dataset.cmd);
                } catch (e) { on = false; }
                b.classList.toggle('is-active', on);
            });
            const block = document.queryCommandValue('formatBlock');
            if (block) blockSel.value = String(block).toUpperCase().replace(/[<>]/g, '') || 'P';
        }

        bar.addEventListener('click', async (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            e.preventDefault();

            if (btn.dataset.cmd) {
                exec(btn.dataset.cmd);
                return;
            }
            if (btn.dataset.blockcmd) {
                exec('formatBlock', `<${btn.dataset.blockcmd}>`);
                return;
            }

            switch (btn.dataset.action) {
                case 'hr':
                    exec('insertHorizontalRule');
                    break;

                case 'full':
                    host.classList.toggle('is-full');
                    btn.querySelector('i').className =
                        `fa-solid fa-${host.classList.contains('is-full') ? 'compress' : 'expand'}`;
                    break;

                case 'callout': {
                    const text = String(root.getSelection()) || 'Something worth pulling out of the flow.';
                    exec('insertHTML', `<div class="callout">${esc(text)}</div><p><br></p>`);
                    break;
                }

                case 'link': {
                    const selection = String(root.getSelection());
                    const url = await promptFor('Insert link', 'https://', selection);
                    if (!url) return;
                    if (selection) exec('createLink', url);
                    else exec('insertHTML', `<a href="${esc(url)}">${esc(url)}</a>`);
                    break;
                }

                case 'image': {
                    const url = await root.TMH.media.pick('');
                    if (!url) return;
                    exec('insertHTML',
                        `<figure><img src="${esc(url)}" alt=""><figcaption>Add a caption</figcaption></figure><p><br></p>`);
                    break;
                }

                default:
                    break;
            }
        });

        blockSel.addEventListener('change', () => exec('formatBlock', `<${blockSel.value}>`));

        /* The whole reason this editor exists. */
        body.addEventListener('paste', (e) => {
            e.preventDefault();
            const dt = e.clipboardData || root.clipboardData;
            const html = dt.getData('text/html');
            const text = dt.getData('text/plain');
            if (html) {
                const cleaned = sanitise(html);
                document.execCommand('insertHTML', false, cleaned);
                root.TMH.toast.info('Pasted text was cleaned', {
                    body: 'Fonts, colours and inline styles were stripped so the article matches the site.',
                    id: 'paste-clean',
                });
            } else {
                document.execCommand('insertText', false, text);
            }
            sync();
        });

        /* Markdown shortcuts — the muscle memory most writers already have. */
        body.addEventListener('keyup', (e) => {
            if (e.key !== ' ') return;
            const sel = root.getSelection();
            if (!sel.anchorNode) return;
            const line = sel.anchorNode.textContent || '';
            const map = { '## ': 'H2', '### ': 'H3', '#### ': 'H4' };
            Object.entries(map).forEach(([prefix, tag]) => {
                if (line.startsWith(prefix)) {
                    sel.anchorNode.textContent = line.slice(prefix.length);
                    exec('formatBlock', `<${tag}>`);
                }
            });
            if (line.startsWith('- ')) {
                sel.anchorNode.textContent = line.slice(2);
                exec('insertUnorderedList');
            }
            if (line.startsWith('> ')) {
                sel.anchorNode.textContent = line.slice(2);
                exec('formatBlock', '<BLOCKQUOTE>');
            }
        });

        body.addEventListener('input', sync);
        body.addEventListener('keyup', paintActive);
        body.addEventListener('mouseup', paintActive);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && host.classList.contains('is-full')) {
                host.classList.remove('is-full');
            }
        });

        sync();
    }

    /* Small prompt modal — window.prompt() is blocked in some embeds and
       looks nothing like the rest of the panel. */
    function promptFor(title, placeholder, subtitle) {
        return root.TMH.modal.open({
            title,
            subtitle: subtitle || '',
            icon: 'fa-link',
            html: `<div class="field"><label for="lnk">URL</label>
                    <input type="text" id="lnk" data-autofocus placeholder="${esc(placeholder)}"></div>`,
            footer: `<button type="button" class="btn btn--ghost" data-close>Cancel</button>
                     <button type="button" class="btn btn--primary" data-ok>Insert</button>`,
            onMount(panel, close) {
                const input = panel.querySelector('#lnk');
                panel.querySelector('[data-ok]').addEventListener('click', () => close(input.value.trim()));
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') close(input.value.trim());
                });
            },
        });
    }

    function upgradeAll(scope) {
        (scope || document).querySelectorAll('[data-editor]').forEach(upgrade);
    }

    root.TMH.editor = { upgradeAll, upgrade, sanitise };
}(window));
