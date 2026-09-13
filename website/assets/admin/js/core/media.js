/* =========================================================
   Media picker + uploader (Dual Image Upload Support).

   Supports:
   1. Direct image file upload (WebP, JPG, PNG, GIF, SVG)
   2. Direct image URL input
   3. Gallery modal drawer pick
   ========================================================= */
(function (root) {
    'use strict';

    const U = root.HAZRA.util;
    const esc = U.esc;

    const MAX_BYTES = 10 * 1024 * 1024;
    const TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    function hiddenFor(el) {
        const scope = el.closest('form') || el.closest('.field') || document;
        return scope.querySelector(`input[name="${CSS.escape(el.dataset.media)}"]`);
    }

    function paint(el, url) {
        const has = !!url;
        el.innerHTML = `
            <div class="media-pick-wrapper col gap-2">
                <div class="media-pick-preview row gap-3 items-center" style="padding:8px 12px; border:1px dashed var(--hairline); border-radius:var(--radius-sm); background:var(--surface-2)">
                    <div class="media-pick__thumb-box" style="width:48px; height:48px; border-radius:var(--radius-xs); overflow:hidden; background:var(--surface-1); display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1px solid var(--hairline);">
                        ${has
                            ? `<img class="media-pick__thumb" src="${esc(url)}" alt="" style="width:100%; height:100%; object-fit:cover;" onError="this.style.display='none';this.nextElementSibling.style.display='block';">
                               <i class="fa-solid fa-triangle-exclamation" style="display:none; color:var(--brand-red);" title="Invalid image URL"></i>`
                            : '<i class="fa-solid fa-image" style="font-size:18px; color:var(--muted);"></i>'}
                    </div>
                    <div class="media-pick__inputs grow col gap-1">
                        <div class="row gap-2 items-center">
                            <input type="text" class="input media-pick__url" placeholder="Paste image URL (https://... or assets/...)" value="${esc(url)}" style="font-size:13px; height:34px; flex:1;">
                            <label class="btn btn--soft btn--sm row items-center gap-1" style="cursor:pointer; white-space:nowrap; margin:0; height:34px; padding:0 10px;">
                                <i class="fa-solid fa-upload"></i> <span>Upload</span>
                                <input type="file" class="media-pick__file" accept="image/*,.webp,.jpg,.jpeg,.png,.gif,.svg" style="display:none">
                            </label>
                            <button type="button" class="btn btn--ghost btn--sm media-pick__gallery-btn" style="height:34px; padding:0 8px;" title="Pick from gallery">
                                <i class="fa-solid fa-images"></i>
                            </button>
                            ${has ? `<button type="button" class="btn btn--ghost btn--sm media-pick__clear-btn" style="height:34px; padding:0 8px; color:var(--brand-red);" title="Remove image"><i class="fa-solid fa-xmark"></i></button>` : ''}
                        </div>
                        <small class="muted text-xs">Supports WebP, PNG, JPG, GIF, SVG or direct image URL</small>
                    </div>
                </div>
            </div>`;
    }

    function paintAll(scope, record) {
        [...(scope || document).querySelectorAll('[data-media]')].forEach((el) => {
            const input = hiddenFor(el);
            const url = (record && record[el.dataset.media]) || (input && input.value) || '';
            if (input) input.value = url;
            paint(el, url);
        });
    }

    function reject(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const validExts = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'];
        if (!TYPES.includes(file.type) && !validExts.includes(ext)) {
            return `${file.name}: ${file.type || ext} is not a supported image file`;
        }
        if (file.size > MAX_BYTES) {
            return `${file.name} is ${U.bytes(file.size)} — limit is 10 MB`;
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

            try {
                const res = await root.HAZRA.api.request('POST', 'api/v1/upload', { form });
                const item = res.data || res;
                created.push(item);
            } catch (err) {
                // Fallback attempt to api/media
                try {
                    const form2 = new FormData();
                    form2.append('file', file, file.name);
                    const res2 = await root.HAZRA.api.request('POST', 'api/media', { form: form2 });
                    created.push(res2.data || res2);
                } catch (err2) {
                    failed.push(`${file.name}: ${err2.message || err.message}`);
                }
            }
        }

        if (created.length) {
            root.HAZRA.toast.success(`${created.length} image${created.length === 1 ? '' : 's'} uploaded successfully.`);
        }
        if (failed.length) {
            root.HAZRA.toast.error(`${failed.length} file(s) failed: ${failed.join(', ')}`);
        }
        return created;
    }

    /* ---------------------------------------------------------
       Media Gallery Modal Drawer
       --------------------------------------------------------- */
    async function pick(current) {
        let all = [];
        try {
            all = await root.HAZRA.store.all('media') || [];
        } catch (e) {
            all = [];
        }
        const folders = [...new Set(all.map((m) => m.folder || 'Uploads'))];
        let filter = { q: '', folder: 'all' };
        let selected = current || '';

        return root.HAZRA.modal.drawer({
            title: 'Media gallery',
            html: `
                <div class="col gap-4">
                    <div class="field" style="margin:0;">
                        <label class="text-xs font-semibold" style="display:block;margin-bottom:4px;">Direct Image URL</label>
                        <div class="row gap-2">
                            <input type="text" data-direct-url placeholder="Paste image URL (https://... or assets/...)" value="${esc(selected)}" style="height:36px;font-size:13px;border-radius:var(--radius-sm);border:1px solid var(--hairline);padding:0 10px;" class="grow">
                        </div>
                    </div>

                    <div class="row gap-2">
                        <div class="toolbar__search grow" style="max-width:none">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" data-q placeholder="Search filenames">
                        </div>
                        <select data-folder style="height:36px;padding:0 28px 0 10px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2);font-size:var(--fs-sm)">
                            <option value="all">All folders</option>
                            ${folders.map((f) => `<option value="${esc(f)}">${esc(f)}</option>`).join('')}
                        </select>
                    </div>

                    <label class="dropzone" data-drop style="cursor:pointer;padding:var(--s5)">
                        <input type="file" multiple accept="image/*,.webp,.jpg,.jpeg,.png,.gif,.svg" hidden data-file>
                        <i class="fa-solid fa-cloud-arrow-up" style="font-size:20px"></i>
                        <div class="mt-2 text-sm"><b>Drop images here</b> or click to browse</div>
                        <small class="muted">WebP, JPG, PNG, GIF or SVG · up to 10 MB each</small>
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
                        const hay = [m.filename, m.title, m.url].filter(Boolean).join(' ').toLowerCase();
                        return hay.includes(filter.q.toLowerCase());
                    });

                    grid.innerHTML = list.length ? list.map((m) => {
                        const imgUrl = m.url || m.image_url;
                        return `
                        <div class="media-tile" data-url="${esc(imgUrl)}"
                             aria-selected="${selected === imgUrl}" role="button" tabindex="0">
                            <img src="${esc(imgUrl)}" alt="${esc(m.filename || 'Image')}" loading="lazy">
                            <span class="media-tile__bar">${esc(m.filename || m.title || 'Image')}</span>
                        </div>`;
                    }).join('')
                    : '<p class="muted text-sm">No images uploaded yet.</p>';

                    [...grid.querySelectorAll('.media-tile')].forEach((tile) => {
                        const choose = () => {
                            selected = tile.dataset.url;
                            useBtn.disabled = false;
                            render();
                        };
                        tile.addEventListener('click', choose);
                        tile.addEventListener('dblclick', () => close(tile.dataset.url));
                    });
                }

                panel.querySelector('[data-q]').addEventListener('input', U.debounce((e) => {
                    filter.q = e.target.value.trim();
                    render();
                }, 180));

                const directUrlInput = panel.querySelector('[data-direct-url]');
                if (directUrlInput) {
                    directUrlInput.addEventListener('input', (e) => {
                        const val = e.target.value.trim();
                        selected = val;
                        useBtn.disabled = !val;
                    });
                }

                panel.querySelector('[data-folder]').addEventListener('change', (e) => {
                    filter.folder = e.target.value;
                    render();
                });

                async function handleFiles(files) {
                    if (!files || !files.length) return;
                    const created = await upload(files, filter.folder === 'all' ? 'Uploads' : filter.folder);
                    try { rows = await root.HAZRA.store.all('media'); } catch (e) {}
                    if (created.length) {
                        selected = created[0].url || created[0].path;
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
       Wiring Dual Input Component
       --------------------------------------------------------- */
    function wire(scope) {
        [...(scope || document).querySelectorAll('[data-media]')].forEach((el) => {
            if (el.dataset.wired) return;
            el.dataset.wired = '1';

            const input = hiddenFor(el);
            const currentUrl = input ? input.value : '';
            paint(el, currentUrl);

            // Handle URL text change
            el.addEventListener('input', (e) => {
                if (e.target.classList.contains('media-pick__url')) {
                    const val = e.target.value.trim();
                    if (input) {
                        input.value = val;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    // Update preview thumbnail
                    const thumb = el.querySelector('.media-pick__thumb-box');
                    if (thumb) {
                        thumb.innerHTML = val
                            ? `<img class="media-pick__thumb" src="${esc(val)}" alt="" style="width:100%; height:100%; object-fit:cover;" onError="this.style.display='none';this.nextElementSibling.style.display='block';">
                               <i class="fa-solid fa-triangle-exclamation" style="display:none; color:var(--brand-red);" title="Invalid image URL"></i>`
                            : '<i class="fa-solid fa-image" style="font-size:18px; color:var(--muted);"></i>';
                    }
                }
            });

            // Handle File Upload input selection
            el.addEventListener('change', async (e) => {
                if (e.target.classList.contains('media-pick__file')) {
                    const files = e.target.files;
                    if (files && files.length) {
                        const created = await upload(files);
                        if (created && created.length) {
                            const newUrl = created[0].url || created[0].path;
                            if (input) {
                                input.value = newUrl;
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                            paint(el, newUrl);
                        }
                    }
                }
            });

            // Handle Clicks (Clear / Gallery)
            el.addEventListener('click', async (e) => {
                if (e.target.closest('.media-pick__clear-btn')) {
                    e.preventDefault();
                    if (input) {
                        input.value = '';
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    paint(el, '');
                    return;
                }

                if (e.target.closest('.media-pick__gallery-btn')) {
                    e.preventDefault();
                    const chosen = await pick(input ? input.value : '');
                    if (chosen !== undefined && chosen !== null) {
                        if (input) {
                            input.value = chosen;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        paint(el, chosen);
                    }
                }
            });

            // Handle Drag & Drop on the preview container
            ['dragenter', 'dragover'].forEach((ev) => el.addEventListener(ev, (e) => {
                e.preventDefault();
                el.classList.add('is-over');
            }));
            ['dragleave', 'drop'].forEach((ev) => el.addEventListener(ev, (e) => {
                e.preventDefault();
                el.classList.remove('is-over');
            }));
            el.addEventListener('drop', async (e) => {
                const files = e.dataTransfer ? e.dataTransfer.files : null;
                if (files && files.length) {
                    const created = await upload(files);
                    if (created && created.length) {
                        const newUrl = created[0].url || created[0].path;
                        if (input) {
                            input.value = newUrl;
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        paint(el, newUrl);
                    }
                }
            });
        });
    }

    root.HAZRA.media = { paintAll, wire, pick, upload };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => wire(document));
    } else {
        wire(document);
    }
}(window));
