/* =========================================================
   Our Gallery — the public wall at /gallery.

   Not the Media Gallery screen next to it in the sidebar.
   That one is the asset library: every file anybody has ever
   uploaded, addressed by picker. This is a curated, ordered,
   published list, and the two have different lifetimes — hiding
   a gallery item must not delete the photo a doctor's profile
   is also using.

   A card grid rather than a table, for the same reason
   facilities.js is: the cards look like what the page renders.
   Order matters — the site prints them in this sequence and the
   album chips read left to right in the same order.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast, api,
    } = window.TMH;

    /* The public site's root, absolute — see core/layout.js. */
    const SITE = api.base;

    const TYPES = {
        image: { label: 'Photograph', icon: 'fa-image' },
        video: { label: 'Video file', icon: 'fa-clapperboard' },
        youtube: { label: 'YouTube', icon: 'fa-youtube' },
    };

    /* Which album the grid is showing. '' is everything. */
    let album = '';

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'Content' }, { label: 'Our Gallery' }],
            title: 'Our Gallery',
            sub: 'Photographs, video and YouTube talks on /gallery. Drag a card to change the order — the page prints them in this sequence, and the album chips follow it.',
            actions: `
                <a class="btn btn--ghost" href="${SITE}gallery" target="_blank" rel="noopener">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View on site</a>
                <button type="button" class="btn btn--primary" id="addBtn">
                    <i class="fa-solid fa-plus"></i> Add item</button>`,
        });

        document.getElementById('addBtn').addEventListener('click', () => edit(null));
        await render();
    }

    /* ---------------------------------------------------------
       The grid
       --------------------------------------------------------- */
    async function render() {
        const all = (await store.all('gallery'))
            .sort((a, b) => (a.order || 0) - (b.order || 0));

        const albums = [...new Set(all.map((r) => (r.album || '').trim()).filter(Boolean))];

        /* An album emptied while the screen was open would otherwise leave the
           grid showing nothing and no obvious way back. */
        if (album && !albums.includes(album)) album = '';

        const rows = album ? all.filter((r) => r.album === album) : all;
        const live = all.filter((r) => r.status === 'published');

        document.getElementById('view').innerHTML = `
            ${U.statStrip([
                ['fa-photo-film', 'navy', all.length, 'Items', `${live.length} published`],
                ['fa-image', 'blue', all.filter((r) => r.type === 'image').length, 'Photographs', 'Open full size'],
                ['fa-clapperboard', 'red', all.filter((r) => r.type === 'video').length, 'Video files', 'Compressed on upload'],
                ['fa-youtube', 'magenta', all.filter((r) => r.type === 'youtube').length, 'YouTube', 'Embedded on click'],
            ])}
            <article class="card anim-item">
                <div class="card__head">
                    <div>
                        <h3>Everything on the wall</h3>
                        <p>Click a card to edit it. Drag to reorder — the page prints them in this sequence.</p>
                    </div>
                    ${albums.length ? albumFilter(albums) : ''}
                </div>
                ${rows.length ? `<div class="tile-grid" id="grid">${rows.map(cardHtml).join('')}</div>` : emptyHtml(album !== '')}
            </article>`;

        U.stagger(document.getElementById('view'));
        wire(all, rows);
    }

    function albumFilter(albums) {
        return `
        <div class="field" style="margin:0;min-width:180px">
            <select id="albumFilter" aria-label="Filter by album">
                <option value="">All albums</option>
                ${albums.map((a) => `<option value="${U.esc(a)}"${a === album ? ' selected' : ''}>${U.esc(a)}</option>`).join('')}
            </select>
        </div>`;
    }

    function cardHtml(row) {
        const kind = TYPES[row.type] || TYPES.image;
        const poster = row.image
            || (row.youtubeId ? `https://img.youtube.com/vi/${encodeURIComponent(row.youtubeId)}/hqdefault.jpg` : '');

        return `
        <article class="tile ${row.status !== 'published' ? 'tile--muted' : ''}"
                 data-id="${U.esc(row.id)}" tabindex="0" role="button"
                 aria-label="Edit ${U.esc(row.title)}">
            <span class="drag-handle tile__grip" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
            ${poster
                ? `<img class="tile__thumb" src="${U.esc(poster)}" alt="" loading="lazy">`
                : `<div class="tile__icon"><i class="fa-${row.type === 'youtube' ? 'brands' : 'solid'} ${U.esc(kind.icon)}"></i></div>`}
            <div class="tile__body">
                <h4>${U.esc(row.title)}</h4>
                <p>${U.esc(row.caption || '')}</p>
            </div>
            <div class="tile__foot">
                ${U.statusTag(row.status)}
                <span class="pill pill--soft"><i class="fa-${row.type === 'youtube' ? 'brands' : 'solid'} ${U.esc(kind.icon)}"></i> ${U.esc(kind.label)}</span>
                ${row.album ? `<span class="pill pill--soft"><i class="fa-solid fa-folder"></i> ${U.esc(row.album)}</span>` : ''}
                ${poster ? '' : '<span class="pill pill--soft"><i class="fa-solid fa-triangle-exclamation"></i> No poster</span>'}
                ${row.sizeBytes ? `<span class="pill pill--soft">${U.esc(U.bytes(row.sizeBytes))}</span>` : ''}
                <span class="grow"></span>
                <button type="button" class="icon-btn" data-edit="${U.esc(row.id)}" aria-label="Edit ${U.esc(row.title)}">
                    <i class="fa-solid fa-pen"></i></button>
                <button type="button" class="icon-btn" data-toggle="${U.esc(row.id)}"
                        aria-label="${row.status === 'published' ? 'Hide' : 'Publish'} ${U.esc(row.title)}">
                    <i class="fa-solid ${row.status === 'published' ? 'fa-eye-slash' : 'fa-cloud-arrow-up'}"></i></button>
                <button type="button" class="icon-btn" data-del="${U.esc(row.id)}" aria-label="Delete ${U.esc(row.title)}">
                    <i class="fa-solid fa-trash-can"></i></button>
            </div>
        </article>`;
    }

    function emptyHtml(filtered) {
        return `
        <div class="empty">
            <div class="empty__art"><i class="fa-solid fa-photo-film"></i></div>
            <h3>${filtered ? 'Nothing in that album' : 'The gallery is empty'}</h3>
            <p>${filtered
                ? 'Pick All albums to see everything, or add an item to this one.'
                : 'The /gallery page renders its own "nothing here yet" panel until something is published.'}</p>
            <button type="button" class="btn btn--primary" id="emptyAdd"><i class="fa-solid fa-plus"></i> Add item</button>
        </div>`;
    }

    function wire(all, rows) {
        const byId = (id) => all.find((r) => r.id === id);
        const view = document.getElementById('view');

        const empty = document.getElementById('emptyAdd');
        if (empty) empty.addEventListener('click', () => edit(null));

        const filter = document.getElementById('albumFilter');
        if (filter) {
            filter.addEventListener('change', () => {
                album = filter.value;
                render();
            });
        }

        view.querySelectorAll('[data-edit]').forEach((b) =>
            b.addEventListener('click', (e) => { e.stopPropagation(); edit(byId(b.dataset.edit)); }));

        view.querySelectorAll('[data-toggle]').forEach((b) =>
            b.addEventListener('click', (e) => { e.stopPropagation(); toggle(byId(b.dataset.toggle)); }));

        view.querySelectorAll('[data-del]').forEach((b) =>
            b.addEventListener('click', (e) => { e.stopPropagation(); remove(byId(b.dataset.del)); }));

        view.querySelectorAll('.tile').forEach((tile) => {
            tile.addEventListener('click', (e) => {
                if (e.target.closest('.icon-btn')) return;
                edit(byId(tile.dataset.id));
            });
            tile.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    edit(byId(tile.dataset.id));
                }
            });
        });

        const grid = document.getElementById('grid');
        if (!grid) return;

        U.sortable(grid, '.tile', async (ids) => {
            await store.reorder('gallery', album ? merge(all, rows, ids) : ids);
            toast.success('Order saved', { body: 'The page prints them in this sequence.', id: 'gal-order' });
            render();
        });
    }

    /**
     * Splice a filtered album's new sequence back into the whole list.
     *
     * Reorder is sent for the entire collection, so a filtered view has to
     * hand back the rows it is not showing as well. The rows outside the album
     * keep their positions and the ones inside are dealt, in their new order,
     * into the slots the album already occupied. Sending only the visible ids
     * would put every hidden row at the end of the gallery.
     */
    function merge(all, shown, ids) {
        const inAlbum = new Set(shown.map((r) => r.id));
        const queue = ids.slice();

        return all.map((row) => (inAlbum.has(row.id) ? queue.shift() : row.id));
    }

    /* ---------------------------------------------------------
       Add / edit

       One dialog for three kinds of item. The fields that do not
       apply are hidden rather than absent, so switching the kind
       back does not lose what was already typed — and blanked on
       save, so an image record cannot keep a stale YouTube id.
       --------------------------------------------------------- */
    async function edit(record) {
        const known = [...new Set((store.allSync('gallery') || [])
            .map((r) => (r.album || '').trim()).filter(Boolean))];

        const data = await formLib.editModal({
            title: record ? `Edit ${record.title}` : 'Add a gallery item',
            subtitle: 'A photograph, a video file, or a YouTube talk.',
            icon: 'fa-photo-film',
            record,
            defaults: { status: 'published', type: 'image', album },
            html: F.section({
                fields: [
                    F.select({
                        name: 'type', label: 'Kind', required: true,
                        options: [
                            { value: 'image', label: 'Photograph' },
                            { value: 'video', label: 'Video file — uploaded and compressed here' },
                            { value: 'youtube', label: 'YouTube — embedded on click' },
                        ],
                    }),
                    F.text({
                        name: 'album', label: 'Album', list: 'galAlbums',
                        placeholder: 'The Hospital',
                        hint: 'Becomes a filter chip on the page. Left empty, the item only appears under All.',
                    }),
                    `<datalist id="galAlbums">${known.map((a) => `<option value="${U.esc(a)}"></option>`).join('')}</datalist>`,
                    F.text({
                        name: 'title', label: 'Title', required: true, wide: true,
                        placeholder: 'Modular theatre',
                    }),
                    F.textarea({
                        name: 'caption', label: 'Caption', wide: true, rows: 2, max: 180,
                        placeholder: 'Laminar airflow, and a scrub area that opens straight onto it.',
                        hint: 'Shown under the tile, and again in the viewer.',
                    }),

                    /* display:contents, so the wrapper does not become a row of
                       its own — the fields inside have to sit in the same grid
                       tracks as the ones above them. */
                    `<div data-when="youtube" style="display:contents">${F.text({
                        name: 'youtubeId', label: 'YouTube link or ID', wide: true,
                        placeholder: 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
                        hint: 'Paste the whole link — the id is taken out of it, and the thumbnail fills itself in.',
                    })}</div>`,

                    `<div data-when="video" style="display:contents">${videoField()}</div>`,

                    F.media({
                        name: 'image', label: 'Poster',
                        hint: 'The still on the tile. A video fills this from its own first second; a YouTube item from its thumbnail.',
                    }),

                    F.status({}),
                ],
            }),
            onReady(scope) {
                wireKind(scope);
                wireYouTube(scope);
                wireVideo(scope);
            },
        });
        if (!data) return;

        blankUnused(data);

        if (record) {
            await store.update('gallery', record.id, data);
            toast.success(`${data.title} updated`);
        } else {
            await store.create('gallery', data);
            toast.success(`${data.title} added`, { body: 'It goes to the end of the list — drag it where it belongs.' });
        }
        render();
    }

    function videoField() {
        return `
        <div class="field field--wide">
            <label>Video file</label>
            <div class="dropzone" data-video-drop>
                <p><i class="fa-solid fa-clapperboard"></i> mp4, mov, webm or mkv — drop one here or choose it.</p>
                <input type="file" accept="video/*" hidden data-video-input>
                <button type="button" class="btn btn--ghost" data-video-pick>
                    <i class="fa-solid fa-upload"></i> Choose file</button>
            </div>
            <div class="progress mt-2" data-video-bar hidden><i style="width:0"></i></div>
            <small data-video-note>Re-encoded to 720p H.264 on the server, which is what keeps the page quick. A long clip can take a minute — leave this dialog open while it runs.</small>

            <!-- Written by the upload, never typed, and inside this .field so
                 that "a video item needs a video" reports itself against the
                 drop zone rather than against nothing. core/form.js focuses a
                 hidden control's button for exactly this case. -->
            <input type="hidden" name="videoPath" data-required-message="Upload the video file first">
            <input type="hidden" name="duration">
            <input type="hidden" name="sizeBytes">
        </div>`;
    }

    /**
     * Shows only the fields the chosen kind uses, and makes the two that the
     * kind cannot do without required while they are on screen.
     *
     * `required` moves with the kind rather than being declared once: a
     * YouTube id marked required in the markup would refuse to save a
     * photograph, from a field the editor cannot even see.
     */
    function wireKind(scope) {
        const kind = scope.querySelector('[name="type"]');
        if (!kind) return;

        const needs = {
            youtube: scope.querySelector('[name="youtubeId"]'),
            video: scope.querySelector('[name="videoPath"]'),
        };

        const apply = () => {
            scope.querySelectorAll('[data-when]').forEach((box) => {
                box.style.display = box.dataset.when === kind.value ? 'contents' : 'none';
            });

            Object.entries(needs).forEach(([name, control]) => {
                if (control) control.toggleAttribute('required', name === kind.value);
            });
        };

        kind.addEventListener('change', apply);
        apply();
    }

    /* A pasted watch / share / embed link becomes an id, and the id fills the
       poster in — an editor who pasted a link should not then have to go and
       find the thumbnail by hand. */
    function wireYouTube(scope) {
        const field = scope.querySelector('[name="youtubeId"]');
        const poster = scope.querySelector('[name="image"]');
        if (!field) return;

        field.addEventListener('change', () => {
            const id = youtubeId(field.value);
            if (!id) return;

            field.value = id;

            if (poster && !poster.value) {
                poster.value = `https://img.youtube.com/vi/${id}/hqdefault.jpg`;
                repaintMedia(scope);
            }
        });
    }

    function youtubeId(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';

        /* Already an id: eleven characters of the URL-safe alphabet. */
        if (/^[\w-]{11}$/.test(raw)) return raw;

        const m = raw.match(/(?:youtu\.be\/|v=|\/embed\/|\/shorts\/|\/live\/)([\w-]{11})/);

        return m ? m[1] : '';
    }

    /**
     * The upload.
     *
     * XMLHttpRequest rather than TMH.api, for the one thing fetch cannot do:
     * report progress. A 100 MB clip on a hospital's connection is a minute of
     * silence otherwise, and silence is indistinguishable from a hung dialog.
     * The transcode that follows is not something the bar can see, so the note
     * under it says what is happening once the bytes are up.
     */
    function wireVideo(scope) {
        const input = scope.querySelector('[data-video-input]');
        if (!input) return;

        const drop = scope.querySelector('[data-video-drop]');
        const pick = scope.querySelector('[data-video-pick]');
        const bar = scope.querySelector('[data-video-bar]');
        const note = scope.querySelector('[data-video-note]');

        const path = scope.querySelector('[name="videoPath"]');
        const poster = scope.querySelector('[name="image"]');
        const duration = scope.querySelector('[name="duration"]');
        const size = scope.querySelector('[name="sizeBytes"]');

        if (path && path.value) {
            note.textContent = `Holding ${path.value.split('/').pop()} — choosing another file replaces it.`;
        }

        if (pick) pick.addEventListener('click', () => input.click());
        input.addEventListener('change', () => send(input.files && input.files[0]));

        if (drop) {
            ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => {
                e.preventDefault();
                drop.classList.add('is-over');
            }));
            ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => {
                e.preventDefault();
                drop.classList.remove('is-over');
            }));
            drop.addEventListener('drop', (e) => send(e.dataTransfer.files && e.dataTransfer.files[0]));
        }

        function send(file) {
            if (!file) return;

            const body = new FormData();
            body.append('file', file, file.name);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', `${api.base}api/gallery/video`);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-Token', csrf());
            xhr.withCredentials = true;

            bar.hidden = false;
            paint(0);
            note.textContent = `Uploading ${U.bytes(file.size)}…`;

            xhr.upload.addEventListener('progress', (e) => {
                if (!e.lengthComputable) return;
                paint(Math.round((e.loaded / e.total) * 100));
                if (e.loaded === e.total) note.textContent = 'Uploaded — compressing on the server…';
            });

            xhr.addEventListener('load', () => {
                bar.hidden = true;

                let payload = null;
                try {
                    payload = JSON.parse(xhr.responseText);
                } catch (err) {
                    payload = null;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    const message = (payload && (payload.message
                        || (payload.errors && payload.errors.file)))
                        || `The upload was refused (${xhr.status})`;
                    note.textContent = message;
                    toast.error(message);
                    return;
                }

                const d = (payload && payload.data) || {};

                if (path) path.value = d.videoPath || '';
                if (duration) duration.value = d.duration == null ? '' : d.duration;
                if (size) size.value = d.sizeBytes == null ? '' : d.sizeBytes;

                /* The extracted frame only fills an empty poster: an editor who
                   already chose one meant it. */
                if (poster && !poster.value && d.poster) {
                    poster.value = d.poster;
                    repaintMedia(scope);
                }

                note.textContent = d.compressed
                    ? `${file.name}: ${U.bytes(d.originalSize)} in, ${U.bytes(d.sizeBytes)} out.`
                    : `${file.name} stored as uploaded (${U.bytes(d.sizeBytes)}). ${d.note || ''}`.trim();

                toast.success('Video ready', { body: 'Save the item to keep it.' });
            });

            xhr.addEventListener('error', () => {
                bar.hidden = true;
                note.textContent = 'The upload did not reach the server.';
                toast.error('Upload failed');
            });

            xhr.send(body);
        }

        function paint(pct) {
            const fill = bar.querySelector('i');
            if (fill) fill.style.width = `${pct}%`;
        }
    }

    function csrf() {
        const el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') || '' : '';
    }

    /** Repaints a media picker's visible half after its hidden input is set. */
    function repaintMedia(scope) {
        if (window.TMH.media && window.TMH.media.paintAll) window.TMH.media.paintAll(scope);
    }

    /**
     * Blank the fields the chosen kind does not own.
     *
     * The dialog hides them rather than removing them, so they are still
     * collected — and a record that says `image` while carrying a video path
     * is a record the page renders one way and the panel another.
     */
    function blankUnused(data) {
        if (data.type !== 'youtube') data.youtubeId = '';

        if (data.type !== 'video') {
            data.videoPath = '';
            data.duration = '';
            data.sizeBytes = '';
        }
    }

    async function toggle(row) {
        const next = row.status === 'published' ? 'hidden' : 'published';
        await store.update('gallery', row.id, { status: next });
        toast.success(`${row.title} ${next === 'published' ? 'published' : 'hidden'}`, {
            undo: async () => {
                await store.update('gallery', row.id, { status: row.status });
                toast.success('Reverted');
                render();
            },
        });
        render();
    }

    async function remove(row) {
        const ok = await window.TMH.confirm({
            title: `Delete “${row.title}”?`,
            body: 'It disappears from /gallery. The uploaded file stays on the server; hiding it keeps the record too.',
            danger: true,
            confirmLabel: 'Delete item',
        });
        if (!ok) return;

        const removed = await store.remove('gallery', row.id);
        toast.success(`${row.title} deleted`, {
            undo: async () => {
                await store.restore('gallery', removed.row, removed.index);
                toast.success('Restored');
                render();
            },
        });
        render();
    }
}());
