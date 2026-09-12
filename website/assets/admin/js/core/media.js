/* =========================================================
   Media picker + uploader.

   Markup:
     <div class="field">
       <label>Photo <span class="field__req">*</span></label>
       <div class="media-pick" data-media="photo"></div>
       <input type="hidden" name="photo" required data-required-message="A photo is required">
     </div>

   Clicking the tile opens the gallery in a drawer: search,
   folder filter and an Upload tab. Selecting writes the URL
   back into the hidden input, so the picker needs no special
   handling in form.js collect().

   Phase 1 stores uploads as data URLs in localStorage. Phase 2
   posts to /api/media — see docs/07-api-contract.md.
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.TMH.util;
    const esc = U.esc;

    const MAX_BYTES = 5 * 1024 * 1024;
    const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    function paint(el, url) {
        const has = !!url;
        el.innerHTML = `
            ${has
                ? `<img class="media-pick__thumb" src="${esc(url)}" alt="">`
                : '<span class="media-pick__thumb"><i class="fa-solid fa-image"></i></span>'}
            <div class="media-pick__meta">
                <b>${has ? 'Change image' : 'Choose an image'}</b>
                <small>${has ? esc(String(url).split('/').pop().split('?')[0]).slice(0, 46) : 'Pick from the gallery or upload a new file'}</small>
            </div>
            ${has ? '<button type="button" class="icon-btn" data-clear aria-label="Remove image"><i class="fa-solid fa-xmark"></i></button>' : ''}`;
    }

    function hiddenFor(el) {
        const scope = el.closest('form') || document;
        return scope.querySelector(`[name="${CSS.escape(el.dataset.media)}"]`);
    }

    function paintAll(scope, record) {
        [...(scope || document).querySelectorAll('[data-media]')].forEach((el) => {
            const input = hiddenFor(el);
            const url = (record && record[el.dataset.media]) || (input && input.value) || '';
            if (input) input.value = url;
            paint(el, url);
        });
    }

    /* ---------------------------------------------------------
       Upload — POST /api/media, one multipart request per file.

       The prototype read each file into a data URL, because a
       mock backed by localStorage had nowhere else to put the
       bytes. There is somewhere now: the file goes to the server
       as a file, which is also the only version of this that can
       reject a PHP script renamed .png by looking at its bytes.
       The checks below stay as the first, friendlier refusal —
       the server makes the same ones and is the one that counts.
       --------------------------------------------------------- */
    function reject(file) {
        if (!TYPES.includes(file.type)) {
            return `${file.name}: ${file.type || 'that file type'} is not an image`;
        }
        if (file.size > MAX_BYTES) {
            return `${file.name} is ${U.bytes(file.size)} — the limit is 5 MB`;
        }
        return '';
    }

    async function upload(files, folder) {
        const list = [...files];
        const created = [];
        const failed = [];

        for (const file of list) {
            const problem = reject(file);

            if (problem) {
                failed.push(problem);
                continue;
            }

            const form = new FormData();
            form.append('file', file, file.name);
            form.append('folder', folder || 'Uploads');
            form.append('alt', '');
            form.append('caption', '');

            try {
                const res = await root.TMH.api.request('POST', 'api/media', { form });
                created.push(res.data);
            } catch (err) {
                failed.push(`${file.name}: ${err.message}`);
            }
        }

        if (created.length) {
            root.TMH.toast.success(`${created.length} file${created.length === 1 ? '' : 's'} uploaded`, {
                body: created.some((c) => !c.alt) ? 'Add alt text before using them on a published page.' : '',
            });
        }
        if (failed.length) {
            root.TMH.toast.error(`${failed.length} file${failed.length === 1 ? '' : 's'} rejected`, {
                action: {
                    label: 'Why',
                    onClick: () => root.TMH.confirm({
                        title: 'Rejected files',
                        blocked: true,
                        danger: true,
                        icon: 'fa-file-circle-xmark',
                        dependents: failed,
                    }),
                },
            });
        }
        return created;
    }

    /* ---------------------------------------------------------
       The picker drawer
       --------------------------------------------------------- */
    async function pick(current) {
        const all = await root.TMH.store.all('media');
        const folders = [...new Set(all.map((m) => m.folder || 'Uploads'))];
        let filter = { q: '', folder: 'all' };
        let selected = current || '';

        return root.TMH.modal.drawer({
            title: 'Media gallery',
            html: `
                <div class="col gap-4">
                    <div class="row gap-2">
                        <div class="toolbar__search grow" style="max-width:none">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" data-q placeholder="Search filenames and alt text">
                        </div>
                        <select data-folder style="height:36px;padding:0 28px 0 10px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                            <option value="all">All folders</option>
                            ${folders.map((f) => `<option value="${esc(f)}">${esc(f)}</option>`).join('')}
                        </select>
                    </div>

                    <label class="dropzone" data-drop style="cursor:pointer;padding:var(--s5)">
                        <input type="file" multiple accept="image/*" hidden data-file>
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size:20px"></i>
                        <div class="mt-2 text-sm"><b>Drop images here</b> or click to browse</div>
                        <small class="muted">JPG, PNG, WebP or SVG · up to 5 MB each</small>
                    </label>

                    <div class="media-grid" data-grid></div>
                </div>`,
            footer: `
                <button type="button" class="btn btn--ghost grow" data-close>Cancel</button>
                <button type="button" class="btn btn--primary grow" data-use disabled>Use image</button>`,
            onMount(panel, close) {
                const grid = panel.querySelector('[data-grid]');
                const useBtn = panel.querySelector('[data-use]');
                const drop = panel.querySelector('[data-drop]');
                const fileInput = panel.querySelector('[data-file]');
                let rows = all;

                function render() {
                    const list = rows.filter((m) => {
                        if (filter.folder !== 'all' && (m.folder || 'Uploads') !== filter.folder) return false;
                        if (!filter.q) return true;
                        /* Joined through a filter: a missing alt would
                           otherwise put the literal "undefined" in the
                           haystack and match anyone searching for it. */
                        const hay = [m.filename, m.alt, m.caption]
                            .filter(Boolean).join(' ').toLowerCase();
                        return hay.includes(filter.q.toLowerCase());
                    });

                    grid.innerHTML = list.length ? list.map((m) => `
                        <div class="media-tile" data-url="${esc(m.url)}"
                             aria-selected="${selected === m.url}" role="button" tabindex="0">
                            <img src="${esc(m.url)}" alt="${esc(m.alt || m.filename)}" loading="lazy">
                            ${!m.alt ? '<span class="media-tile__flag">No alt</span>' : ''}
                            <span class="media-tile__bar">${esc(m.filename)}</span>
                        </div>`).join('')
                        : '<p class="muted text-sm">Nothing in this folder yet.</p>';

                    [...grid.querySelectorAll('.media-tile')].forEach((tile) => {
                        const choose = () => {
                            selected = tile.dataset.url;
                            useBtn.disabled = false;
                            render();
                        };
                        tile.addEventListener('click', choose);
                        tile.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                e.preventDefault();
                                choose();
                            }
                        });
                        tile.addEventListener('dblclick', () => close(tile.dataset.url));
                    });
                }

                panel.querySelector('[data-q]').addEventListener('input', U.debounce((e) => {
                    filter.q = e.target.value.trim();
                    render();
                }, 180));

                panel.querySelector('[data-folder]').addEventListener('change', (e) => {
                    filter.folder = e.target.value;
                    render();
                });

                async function handleFiles(files) {
                    if (!files || !files.length) return;
                    const created = await upload(files, filter.folder === 'all' ? 'Uploads' : filter.folder);
                    rows = await root.TMH.store.all('media');
                    if (created.length) {
                        selected = created[0].url;
                        useBtn.disabled = false;
                    }
                    render();
                }

                fileInput.addEventListener('change', () => handleFiles(fileInput.files));

                ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => {
                    e.preventDefault();
                    drop.classList.add('is-over');
                }));
                ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => {
                    e.preventDefault();
                    drop.classList.remove('is-over');
                }));
                drop.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));

                useBtn.addEventListener('click', () => close(selected));

                render();
            },
        });
    }

    /* ---------------------------------------------------------
       Wiring
       --------------------------------------------------------- */
    function wire(scope) {
        [...(scope || document).querySelectorAll('[data-media]')].forEach((el) => {
            if (el.dataset.wired) return;
            el.dataset.wired = '1';

            el.addEventListener('click', async (e) => {
                const input = hiddenFor(el);

                if (e.target.closest('[data-clear]')) {
                    if (input) {
                        input.value = '';
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    paint(el, '');
                    return;
                }

                const url = await pick(input ? input.value : '');
                if (url === undefined) return;
                if (input) {
                    input.value = url;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
                paint(el, url);
            });
        });
    }

    root.TMH.media = { paintAll, wire, pick, upload };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => wire(document));
    } else {
        wire(document);
    }
}(window));
