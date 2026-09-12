/* =========================================================
   Media gallery.

   The screen that makes deletion safe. Every other list can
   delete a record and leave a hole somebody notices; a deleted
   image leaves a broken box on a published page and nobody
   notices for a month. So `usedBy` is worked out here by
   scanning every entity that can hold a file, and a file in use
   cannot be deleted — the confirm names the records instead of
   asking again more sternly.

   The back-references are computed, never stored. A cached
   `usedBy` array is wrong the moment somebody swaps a doctor's
   photo, and a stale list is worse than none: it would block a
   delete that is fine, or allow one that is not. Phase 2
   replaces the scan with a join, not with a cached column.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, layout, toast, modal, media,
    } = window.TMH;

    /* Every place a media URL can end up, and where an editor goes to change
       it. Adding an image field to a form means adding it here too — the
       alternative is a delete that silently breaks a page. */
    const REFS = [
        { entity: 'doctors', label: 'Doctor', fields: ['photo'], name: (r) => r.name, href: (r) => `doctor-form?id=${encodeURIComponent(r.id)}` },
        { entity: 'leadership', label: 'Leadership', fields: ['photo'], name: (r) => r.name, href: (r) => `leadership-form?id=${encodeURIComponent(r.id)}` },
        { entity: 'departments', label: 'Department', fields: ['banner', 'image'], name: (r) => r.name, href: (r) => `department-form?id=${encodeURIComponent(r.id)}` },
        { entity: 'posts', label: 'Blog post', fields: ['coverImage'], name: (r) => r.title, href: (r) => `blog-form?id=${encodeURIComponent(r.id)}` },
        { entity: 'testimonials', label: 'Testimonial', fields: ['photo'], name: (r) => `${r.name}’s quote`, href: () => 'testimonials' },
        { entity: 'facilities', label: 'Facility', fields: ['image'], name: (r) => r.title, href: () => 'facilities' },
        { entity: 'jobs', label: 'Vacancy', fields: ['image'], name: (r) => r.title, href: (r) => `job-form?id=${encodeURIComponent(r.id)}` },
    ];

    const FILTERS = [
        ['all', 'All files', 'fa-photo-film'],
        ['images', 'Images', 'fa-image'],
        ['documents', 'Documents', 'fa-file-lines'],
        ['unused', 'Unused', 'fa-link-slash'],
        ['noalt', 'Missing alt', 'fa-circle-exclamation'],
    ];

    const state = {
        q: U.param('q', '') || '',
        folder: U.param('folder', 'all') || 'all',
        kind: U.param('kind', 'all') || 'all',
        selected: new Set(),
    };

    let rows = [];
    let settings = {};
    let usage = {};   /* fileKey -> [{label, name, href}] */

    /* The library is local files now, so the path alone identifies a picture.
       The Unsplash branch stays for URLs pasted into the panel by hand, where
       a 500px and a 1600px rendition of one photo differ only by query
       string and must not read as two files. */
    function fileKey(url) {
        const m = /photo-([A-Za-z0-9_-]+)/.exec(url || '');
        return m ? m[1] : String(url || '').split('?')[0];
    }

    const isImage = (row) => String(row.mime || 'image/jpeg').startsWith('image/');
    const extOf = (row) => (String(row.filename || '').split('.').pop() || 'file').toUpperCase();

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Media' }],
            title: 'Media',
            accent: 'Gallery',
            sub: 'Every picture and document the site uses. A file in use cannot be deleted until whatever uses it lets go.',
            actions: `
                <button type="button" class="btn btn--ghost" id="newFolderBtn">
                    <i class="fa-solid fa-folder-plus"></i> New folder</button>
                <button type="button" class="btn btn--primary" id="uploadBtn">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>`,
        });

        wirePageDrop();
        await load();
    }

    async function load() {
        rows = (await store.all('media')).sort((a, b) => (a.order || 0) - (b.order || 0));
        settings = await store.getDoc('settings');
        usage = buildUsage();
        render();
    }

    /* ---------------------------------------------------------
       Back-reference scan
       --------------------------------------------------------- */
    function buildUsage() {
        const map = {};
        const add = (url, ref) => {
            if (!url) return;
            const k = fileKey(url);
            (map[k] = map[k] || []).push(ref);
        };

        REFS.forEach((def) => {
            (store.allSync(def.entity) || []).forEach((r) => {
                def.fields.forEach((f) => add(r[f], {
                    label: def.label, name: def.name(r) || r.id, href: def.href(r),
                }));
            });
        });

        /* Page sections nest their data one level deep and every page editor
           is a different screen, so this walks the values rather than naming
           the fields one by one. */
        (store.allSync('pages') || []).forEach((page) => {
            (page.sections || []).forEach((s) => {
                Object.values(s.data || {}).forEach((v) => {
                    if (typeof v === 'string' && /^(https?:|\.\.\/|data:image)/.test(v)) {
                        add(v, {
                            label: 'Page section',
                            name: `${page.title} — ${s.label}`,
                            href: `page-${page.id}`,
                        });
                    }
                });
            });
        });

        /* The logo and favicon live in the settings document, not an entity. */
        const general = settings.general || {};
        [['logo', 'Header logo'], ['logoDark', 'Dark-theme logo'], ['favicon', 'Favicon']]
            .forEach(([field, label]) => add(general[field], {
                label: 'Site setting', name: label, href: 'settings-general',
            }));

        return map;
    }

    function usedBy(row) {
        return usage[fileKey(row.url)] || [];
    }

    /* ---------------------------------------------------------
       Render
       --------------------------------------------------------- */
    function visible() {
        return rows.filter((r) => {
            if (state.folder !== 'all' && (r.folder || 'Uploads') !== state.folder) return false;
            if (state.kind === 'images' && !isImage(r)) return false;
            if (state.kind === 'documents' && isImage(r)) return false;
            if (state.kind === 'unused' && usedBy(r).length) return false;
            if (state.kind === 'noalt' && r.alt) return false;
            const q = state.q.trim().toLowerCase();
            if (q) {
                /* Filtered before joining — an absent alt or caption would
                   otherwise read as the string "undefined" and match. */
                const hay = [r.filename, r.alt, r.caption, r.folder]
                    .filter(Boolean).join(' ').toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function folders() {
        return [...new Set(rows.map((r) => r.folder || 'Uploads'))].sort()
            .map((name) => ({ name, count: rows.filter((r) => (r.folder || 'Uploads') === name).length }));
    }

    function render() {
        const list = visible();
        const noAlt = rows.filter((r) => !r.alt).length;
        const unused = rows.filter((r) => !usedBy(r).length).length;
        const bytes = rows.reduce((n, r) => n + (r.sizeBytes || 0), 0);

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-photo-film', 'red', rows.length, 'Files', `${rows.filter(isImage).length} images`],
                ['fa-hard-drive', 'navy', U.bytes(bytes), 'Stored', 'Phase 1 keeps uploads in the browser'],
                ['fa-circle-exclamation', 'blue', noAlt, 'Missing alt text', noAlt ? 'Unreadable to a screen reader' : 'Every file described'],
                ['fa-link-slash', 'magenta', unused, 'Unused', 'Nothing on the site points at these'],
            ])}

            <label class="dropzone anim-item mb-4" id="drop">
                <input type="file" multiple accept="image/*" hidden id="fileInput">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size:22px"></i>
                <div class="mt-2"><b>Drop files anywhere on this page</b> — or click to browse</div>
                <small class="muted">JPG, PNG, WebP or SVG · up to 5 MB each · they land in
                    ${U.esc(state.folder === 'all' ? 'Uploads' : state.folder)}</small>
            </label>

            <div class="gallery anim-item">
                <aside class="gallery__rail">
                    <article class="card">
                        <h4 class="rail-title">Folders</h4>
                        <nav class="rail-list">
                            <button type="button" data-folder="all" aria-pressed="${state.folder === 'all'}">
                                <i class="fa-solid fa-folder-open"></i><span class="grow">All folders</span>
                                <span class="pill">${rows.length}</span></button>
                            ${folders().map((f) => `
                                <button type="button" data-folder="${U.esc(f.name)}" aria-pressed="${state.folder === f.name}">
                                    <i class="fa-solid fa-folder"></i><span class="grow">${U.esc(f.name)}</span>
                                    <span class="pill">${f.count}</span></button>`).join('')}
                        </nav>
                    </article>

                    <article class="card">
                        <h4 class="rail-title">Show</h4>
                        <nav class="rail-list">
                            ${FILTERS.map(([key, label, icon]) => `
                                <button type="button" data-kind="${key}" aria-pressed="${state.kind === key}">
                                    <i class="fa-solid ${icon}"></i><span class="grow">${label}</span></button>`).join('')}
                        </nav>
                    </article>
                </aside>

                <article class="card card--flush">
                    ${state.selected.size ? selectionBar() : toolbarHtml(list.length)}
                    <div class="gallery__body">
                        ${list.length ? `<div class="media-grid" id="grid">${list.map(tileHtml).join('')}</div>` : emptyHtml()}
                    </div>
                </article>
            </div>`;

        U.stagger(document.getElementById('view'));
        wire();
    }

    /* Ticking a box swaps the toolbar for the bulk bar and marks the tile —
       and nothing else. A full render() here would rebuild every tile in the
       grid between two clicks, which is both wasteful and how a shift-select
       loses the box you are still on. */
    function syncSelection() {
        const view = document.getElementById('view');

        view.querySelectorAll('.media-tile').forEach((tile) => {
            tile.setAttribute('aria-selected', String(state.selected.has(tile.dataset.id)));
        });

        const bar = view.querySelector('.gallery .bulk-bar, .gallery .toolbar');
        const wantBulk = state.selected.size > 0;
        if (bar.classList.contains('bulk-bar') === wantBulk) {
            /* Same bar, only the count changed. */
            if (wantBulk) bar.querySelector('[data-count]').innerHTML = countLabel();
            return;
        }

        bar.outerHTML = wantBulk ? selectionBar() : toolbarHtml(visible().length);
        wireBar();
    }

    function wireBar() {
        const view = document.getElementById('view');

        const q = view.querySelector('#q');
        if (q) q.addEventListener('input', U.debounce(onSearch, 220));

        const BULK = {
            move: bulkMove,
            delete: bulkDelete,
            clear: () => { state.selected.clear(); syncSelection(); },
        };
        view.querySelectorAll('[data-bulk]').forEach((b) =>
            b.addEventListener('click', () => BULK[b.dataset.bulk]()));
    }

    function onSearch(e) {
        /* Held raw. Trimming here would render the box without the space the
           user just typed between two words, and the next letter would land
           against the previous one. */
        state.q = e.target.value;
        U.setParams({ q: state.q.trim() });
        /* Re-rendering blows away the box being typed in, so the caret is put
           back where it was. */
        const start = e.target.selectionStart;
        const end = e.target.selectionEnd;
        render();
        const next = document.getElementById('q');
        if (next) {
            next.focus();
            try {
                next.setSelectionRange(start, end);
            } catch (err) { /* no selection range on this control */ }
        }
    }

    function toolbarHtml(shown) {
        return `
        <div class="toolbar">
            <div class="toolbar__search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="q" value="${U.esc(state.q)}"
                       placeholder="Search filenames, alt text and captions"
                       aria-label="Search the media library">
            </div>
            <span class="grow"></span>
            <span class="muted text-sm">${shown} of ${rows.length} file${rows.length === 1 ? '' : 's'}</span>
        </div>`;
    }

    function countLabel() {
        const n = state.selected.size;
        return `<b>${n}</b> file${n === 1 ? '' : 's'} selected`;
    }

    function selectionBar() {
        return `
        <div class="bulk-bar" role="status">
            <span class="grow" data-count>${countLabel()}</span>
            <button type="button" class="btn btn--ghost btn--sm" data-bulk="move">
                <i class="fa-solid fa-folder-tree"></i> Move to folder</button>
            <button type="button" class="btn btn--ghost btn--sm text-bad" data-bulk="delete">
                <i class="fa-solid fa-trash-can"></i> Delete</button>
            <button type="button" class="icon-btn" data-bulk="clear" aria-label="Clear selection">
                <i class="fa-solid fa-xmark"></i></button>
        </div>`;
    }

    function tileHtml(row) {
        const uses = usedBy(row);
        const picked = state.selected.has(row.id);

        return `
        <div class="media-tile ${isImage(row) ? '' : 'media-tile--doc'}"
             data-id="${U.esc(row.id)}" role="button" tabindex="0"
             aria-selected="${picked}" aria-label="${U.esc(row.filename)}">
            ${isImage(row)
                ? `<img src="${U.esc(row.url)}" alt="${U.esc(row.alt || row.filename)}" loading="lazy">`
                : `<span class="media-tile__doc"><i class="fa-solid fa-file-lines"></i><b>${U.esc(extOf(row))}</b></span>`}

            <label class="media-tile__check">
                <input type="checkbox" data-pick="${U.esc(row.id)}" ${picked ? 'checked' : ''}
                       aria-label="Select ${U.esc(row.filename)}">
            </label>

            ${!row.alt ? '<span class="media-tile__flag">No alt</span>' : ''}
            ${uses.length
                ? `<span class="media-tile__used" title="Used by ${U.esc(uses.map((u) => u.name).join(', '))}"><i class="fa-solid fa-link"></i> ${uses.length}</span>`
                : '<span class="media-tile__used media-tile__used--none" title="Nothing on the site points at this file"><i class="fa-solid fa-link-slash"></i></span>'}

            <span class="media-tile__bar">${U.esc(row.filename)}</span>
        </div>`;
    }

    function emptyHtml() {
        const filtered = state.q.trim() || state.folder !== 'all' || state.kind !== 'all';
        if (!filtered) {
            return `
            <div class="empty">
                <div class="empty__art"><i class="fa-solid fa-photo-film"></i></div>
                <h3>Nothing uploaded yet</h3>
                <p>Drop a file on the zone above and it becomes available to every picker in the panel.</p>
            </div>`;
        }
        return `
        <div class="empty">
            <div class="empty__art"><i class="fa-solid fa-filter-circle-xmark"></i></div>
            <h3>No matches</h3>
            <p>Nothing here fits the current folder, filter and search.</p>
            <button type="button" class="btn btn--ghost" id="clearFilters">
                <i class="fa-solid fa-rotate-left"></i> Clear filters</button>
        </div>`;
    }

    /* ---------------------------------------------------------
       Wiring
       --------------------------------------------------------- */
    function wire() {
        const view = document.getElementById('view');

        view.querySelectorAll('[data-folder]').forEach((b) =>
            b.addEventListener('click', () => {
                state.folder = b.dataset.folder;
                U.setParams({ folder: state.folder === 'all' ? '' : state.folder });
                render();
            }));

        view.querySelectorAll('[data-kind]').forEach((b) =>
            b.addEventListener('click', () => {
                state.kind = b.dataset.kind;
                U.setParams({ kind: state.kind === 'all' ? '' : state.kind });
                render();
            }));

        wireBar();

        const clear = document.getElementById('clearFilters');
        if (clear) clear.addEventListener('click', () => {
            state.q = '';
            state.folder = 'all';
            state.kind = 'all';
            U.setParams({ q: '', folder: '', kind: '' });
            render();
        });

        view.querySelectorAll('[data-pick]').forEach((cb) =>
            cb.addEventListener('change', (e) => {
                e.stopPropagation();
                if (cb.checked) state.selected.add(cb.dataset.pick);
                else state.selected.delete(cb.dataset.pick);
                syncSelection();
            }));

        view.querySelectorAll('.media-tile').forEach((tile) => {
            const open = () => {
                const row = rows.find((r) => r.id === tile.dataset.id);
                if (row) detail(row);
            };
            tile.addEventListener('click', (e) => {
                if (e.target.closest('.media-tile__check')) return;
                open();
            });
            tile.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    open();
                }
            });
        });

        const input = document.getElementById('fileInput');
        if (input) input.addEventListener('change', () => handleFiles(input.files));

        const drop = document.getElementById('drop');
        if (drop) {
            ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => {
                e.preventDefault();
                drop.classList.add('is-over');
            }));
            ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => {
                e.preventDefault();
                drop.classList.remove('is-over');
            }));
            drop.addEventListener('drop', (e) => handleFiles(e.dataTransfer.files));
        }
    }

    /* Dragging a file onto the page at all should work, not only onto the
       zone — the zone is a hint, not a target you have to hit. Bound once, on
       document, because render() replaces everything inside #view. */
    function wirePageDrop() {
        let depth = 0;
        const hasFiles = (e) => !!e.dataTransfer && [...e.dataTransfer.types].includes('Files');

        document.addEventListener('dragenter', (e) => {
            if (!hasFiles(e)) return;
            depth += 1;
            document.body.classList.add('is-file-drag');
        });

        document.addEventListener('dragover', (e) => {
            if (hasFiles(e)) e.preventDefault();
        });

        document.addEventListener('dragleave', () => {
            depth = Math.max(0, depth - 1);
            if (!depth) document.body.classList.remove('is-file-drag');
        });

        document.addEventListener('drop', (e) => {
            if (!hasFiles(e)) return;
            e.preventDefault();
            depth = 0;
            document.body.classList.remove('is-file-drag');
            /* The zone has its own handler; without this the same files would
               upload twice. */
            if (e.target.closest('#drop')) return;
            handleFiles(e.dataTransfer.files);
        });

        document.getElementById('uploadBtn').addEventListener('click', () => {
            const input = document.getElementById('fileInput');
            if (input) input.click();
        });
        document.getElementById('newFolderBtn').addEventListener('click', createFolder);
    }

    async function handleFiles(files) {
        if (!files || !files.length) return;
        const created = await media.upload(files, state.folder === 'all' ? 'Uploads' : state.folder);
        await load();

        if (created.length) {
            toast.warning(`${created.length} file${created.length === 1 ? '' : 's'} need alt text`, {
                body: 'Open each one and describe it before it goes on a published page.',
                action: {
                    label: 'Show them',
                    onClick: () => {
                        state.kind = 'noalt';
                        state.folder = 'all';
                        U.setParams({ kind: 'noalt', folder: '' });
                        render();
                    },
                },
            });
        }
    }

    /* ---------------------------------------------------------
       Detail drawer
       --------------------------------------------------------- */
    function detail(row) {
        const uses = usedBy(row);
        const dims = row.width && row.height ? `${row.width} × ${row.height}` : 'Unknown';

        modal.drawer({
            title: row.filename,
            html: `
                <div class="col gap-4">
                    ${isImage(row)
                        ? `<img src="${U.esc(row.url)}" alt="${U.esc(row.alt || row.filename)}"
                                style="width:100%;border-radius:var(--radius-sm);background:var(--surface-3)">`
                        : `<div class="media-tile media-tile--doc" style="aspect-ratio:16/9;cursor:default">
                               <span class="media-tile__doc"><i class="fa-solid fa-file-lines"></i><b>${U.esc(extOf(row))}</b></span>
                           </div>`}

                    <dl class="kv">
                        <dt>Dimensions</dt><dd>${U.esc(dims)}</dd>
                        <dt>Size</dt><dd>${U.esc(U.bytes(row.sizeBytes))}</dd>
                        <dt>Type</dt><dd>${U.esc(row.mime || 'image/jpeg')}</dd>
                        <dt>Uploaded</dt><dd>${U.esc(row.uploadedAt ? U.fmtDate(row.uploadedAt) : '—')}</dd>
                    </dl>

                    <div class="field">
                        <label for="dAlt">Alt text <span class="field__req">*</span></label>
                        <input type="text" id="dAlt" value="${U.esc(row.alt)}"
                               placeholder="What somebody who cannot see it would need told">
                        <small>Describe the picture, not the file. “Nurse checking on a patient” — not “ward.jpg”.</small>
                    </div>

                    <div class="field">
                        <label for="dCaption">Caption</label>
                        <input type="text" id="dCaption" value="${U.esc(row.caption)}"
                               placeholder="Optional — printed under the image where a template shows one">
                    </div>

                    <div class="field">
                        <label for="dFolder">Folder</label>
                        <input type="text" id="dFolder" list="folderList" value="${U.esc(row.folder || 'Uploads')}">
                        <datalist id="folderList">
                            ${folders().map((f) => `<option value="${U.esc(f.name)}"></option>`).join('')}
                        </datalist>
                    </div>

                    <div class="field">
                        <label for="dUrl">URL</label>
                        <div class="input-group">
                            <input type="text" id="dUrl" value="${U.esc(row.url)}" readonly>
                            <button type="button" class="btn btn--ghost btn--sm" data-copy>
                                <i class="fa-solid fa-copy"></i> Copy</button>
                        </div>
                    </div>

                    <div>
                        <h4 class="rail-title">Used by</h4>
                        ${uses.length ? `
                            <ul class="used-list">
                                ${uses.map((u) => `
                                    <li>
                                        <span class="pill pill--soft">${U.esc(u.label)}</span>
                                        <a class="grow" href="${U.esc(u.href)}">${U.esc(u.name)}</a>
                                    </li>`).join('')}
                            </ul>
                            <small class="muted">Deleting is blocked while this list is not empty.</small>`
                            : '<p class="muted text-sm">Nothing on the site points at this file. It is safe to delete.</p>'}
                    </div>
                </div>`,
            footer: `
                <button type="button" class="btn btn--ghost text-bad" data-act="delete">
                    <i class="fa-solid fa-trash-can"></i> Delete</button>
                <span class="grow"></span>
                <button type="button" class="btn btn--primary" data-act="save">Save</button>`,
            onMount(panel, close) {
                panel.querySelector('[data-copy]').addEventListener('click', async () => {
                    await U.copy(row.url);
                    toast.success('URL copied');
                });

                panel.querySelector('[data-act="save"]').addEventListener('click', async () => {
                    const alt = panel.querySelector('#dAlt').value.trim();
                    if (!alt) {
                        toast.error('Alt text is required', {
                            body: 'A published image with no description is unreadable to a screen reader.',
                        });
                        panel.querySelector('#dAlt').focus();
                        return;
                    }
                    await store.update('media', row.id, {
                        alt,
                        caption: panel.querySelector('#dCaption').value.trim(),
                        folder: panel.querySelector('#dFolder').value.trim() || 'Uploads',
                    });
                    close();
                    toast.success(`${row.filename} updated`);
                    load();
                });

                panel.querySelector('[data-act="delete"]').addEventListener('click', async () => {
                    const done = await remove(row);
                    if (done) close();
                });
            },
        });
    }

    /* ---------------------------------------------------------
       Delete, blocked while in use
       --------------------------------------------------------- */
    async function remove(row) {
        const uses = usedBy(row);

        if (uses.length) {
            await window.TMH.confirm({
                title: `${row.filename} is in use`,
                body: 'Change or clear the image on each of these first. Deleting it now would leave a broken picture on a published page.',
                blocked: true,
                danger: true,
                icon: 'fa-link-slash',
                dependents: uses.map((u) => `${u.label}: ${u.name}`),
            });
            return false;
        }

        const ok = await window.TMH.confirm({
            title: `Delete ${row.filename}?`,
            body: 'Nothing points at it today. Anything you paste its URL into later will break.',
            danger: true,
            confirmLabel: 'Delete file',
        });
        if (!ok) return false;

        const removed = await store.remove('media', row.id);
        state.selected.delete(row.id);
        toast.success(`${row.filename} deleted`, {
            undo: async () => {
                await store.restore('media', removed.row, removed.index);
                toast.success('Restored');
                load();
            },
        });
        await load();
        return true;
    }

    /* ---------------------------------------------------------
       Bulk
       --------------------------------------------------------- */
    async function bulkDelete() {
        const ids = [...state.selected];
        const picked = rows.filter((r) => ids.includes(r.id));
        const blocked = picked.filter((r) => usedBy(r).length);
        const free = picked.filter((r) => !usedBy(r).length);

        if (!free.length) {
            await window.TMH.confirm({
                title: 'All of those are in use',
                body: 'None of the selected files can go while something on the site points at them.',
                blocked: true,
                danger: true,
                icon: 'fa-link-slash',
                dependents: blocked.map((r) => `${r.filename} — used by ${usedBy(r).length} record(s)`),
            });
            return;
        }

        const ok = await window.TMH.confirm({
            title: `Delete ${free.length} file${free.length === 1 ? '' : 's'}?`,
            body: blocked.length
                ? `${blocked.length} of the ${picked.length} selected are in use and will be skipped.`
                : 'Nothing on the site points at any of them.',
            danger: true,
            confirmLabel: `Delete ${free.length}`,
            dependents: blocked.length ? blocked.map((r) => `Kept — in use: ${r.filename}`) : null,
        });
        if (!ok) return;

        const res = await store.bulk('media', free.map((r) => r.id), 'delete');
        state.selected.clear();

        /* Partial failure is reported rather than rounded up to success —
           see the bulk rule in docs/04-crud-flows.md. */
        if (res.failed.length) {
            toast.warning(`${res.succeeded.length} deleted, ${res.failed.length} could not be`, {
                action: {
                    label: 'Why',
                    onClick: () => window.TMH.confirm({
                        title: 'Files that were kept',
                        blocked: true,
                        danger: true,
                        icon: 'fa-link-slash',
                        dependents: res.failed.map((f) => `${f.id}: ${f.reason}`),
                    }),
                },
            });
        } else {
            toast.success(`${res.succeeded.length} file${res.succeeded.length === 1 ? '' : 's'} deleted`);
        }
        load();
    }

    async function bulkMove() {
        const ids = [...state.selected];

        const target = await folderPrompt({
            title: `Move ${ids.length} file${ids.length === 1 ? '' : 's'}`,
            subtitle: 'Folders are a filing convenience. No URL changes, so nothing on the site breaks.',
            icon: 'fa-folder-tree',
            okLabel: 'Move',
        });
        if (!target) return;

        const before = rows.filter((r) => ids.includes(r.id)).map((r) => ({ id: r.id, folder: r.folder }));
        await store.bulk('media', ids, 'patch', { folder: target });
        state.selected.clear();

        toast.success(`Moved to ${target}`, {
            body: `${ids.length} file${ids.length === 1 ? '' : 's'}. URLs are unchanged.`,
            undo: async () => {
                /* Each file goes back to its own previous folder, not to one
                   shared guess — the selection may have come from several. */
                for (const r of before) {
                    await store.update('media', r.id, { folder: r.folder || 'Uploads' });
                }
                toast.success('Move undone');
                load();
            },
        });
        load();
    }

    async function createFolder() {
        const name = await folderPrompt({
            title: 'New folder',
            subtitle: 'A folder exists once a file is in it, so this moves the current selection — or waits for the next upload.',
            icon: 'fa-folder-plus',
            okLabel: 'Create',
        });
        if (!name) return;

        if (state.selected.size) {
            await store.bulk('media', [...state.selected], 'patch', { folder: name });
            state.selected.clear();
            toast.success(`${name} created`, { body: 'The selected files were moved into it.' });
            load();
            return;
        }

        state.folder = name;
        U.setParams({ folder: name });
        toast.info(`${name} is ready`, { body: 'Upload something into it — an empty folder is not stored.' });
        render();
    }

    function folderPrompt(o) {
        return modal.open({
            title: o.title,
            subtitle: o.subtitle,
            icon: o.icon,
            wide: false,
            html: `
                <div class="field">
                    <label for="folderName">Folder</label>
                    <input type="text" id="folderName" list="allFolders" data-autofocus autocomplete="off"
                           placeholder="Type a new name, or pick an existing one">
                    <datalist id="allFolders">
                        ${folders().map((f) => `<option value="${U.esc(f.name)}"></option>`).join('')}
                    </datalist>
                </div>`,
            footer: `
                <button type="button" class="btn btn--ghost" data-close>Cancel</button>
                <button type="button" class="btn btn--primary" data-ok>${U.esc(o.okLabel)}</button>`,
            onMount(panel, close) {
                const field = panel.querySelector('#folderName');
                const done = () => close(field.value.trim());
                panel.querySelector('[data-ok]').addEventListener('click', done);
                field.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        done();
                    }
                });
            },
        });
    }
}());
