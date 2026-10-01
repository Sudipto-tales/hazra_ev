(function () {
    'use strict';

    let BASE, CSRF;

    window.HAZRA.boot(init);

    function init() {
        const { util: U, store, table, layout, toast, modal } = window.HAZRA;
        BASE = (document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '/').replace(/\/+$/, '') + '/';
        CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Page Header
        document.getElementById('pageHead').innerHTML = layout.pageHead({
        crumb: [{ label: 'System' }, { label: 'App Releases' }],
        title: 'App Releases',
        accent: 'Mobile',
        sub: 'Manage Android APK releases — upload, publish, and track app versions.',
        actions: `
            <button class="btn btn--primary" id="uploadBtn">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload APK
            </button>`,
    });

    // View Container
    document.getElementById('view').innerHTML = `
        <div id="statStrip" class="grid-stats mb-lg"></div>
        <div id="listCard"></div>
    `;

    // Data Table
    const list = table.create({
        mount: '#listCard',
        entity: 'app-releases',
        bulkActions: [],
        selectable: false,
        statusOptions: [{value:'all',label:'All statuses'},{value:'published',label:'Latest'},{value:'archived',label:'Previous releases'},{value:'withdrawn',label:'Withdrawn'},{value:'draft',label:'Drafts'}],
        searchFields: ['version_name', 'git_tag', 'status'],
        searchPlaceholder: 'Search by version or tag',
        sort: 'created_at',
        dir: 'desc',
        empty: {
            icon: 'fa-mobile-screen',
            title: 'No app releases yet',
            text: 'Upload your first APK or push a tag to trigger a CI build.',
            actionLabel: 'Upload APK',
            onAction: () => openUploadModal(),
        },
        columns: [
            {
                label: 'Version', sort: 'version_name',
                render: (r, s) => `
                    <div>
                        <span class="cell-main">${U.mark('v' + U.esc(r.version_name || r.versionName || ''), s.q)}</span>
                        <span class="cell-sub">+${U.esc(r.version_code || r.versionCode || '')}</span>
                    </div>`,
            },
            {
                label: 'Status', sort: 'status',
                render: (r) => statusBadge(r.status),
            },
            {
                label: 'Size',
                render: (r) => `<span class="muted">${fmtSize(r.file_size || r.fileSize || 0)}</span>`,
            },
            {
                label: 'Source',
                render: (r) => {
                    const src = r.build_source || r.buildSource || 'admin_upload';
                    if (src === 'github_actions') return '<span class="pill"><i class="fa-brands fa-github"></i> CI</span>';
                    return '<span class="pill"><i class="fa-solid fa-upload"></i> Manual</span>';
                },
            },
            {
                label: 'Tag',
                render: (r) => {
                    const tag = r.git_tag || r.gitTag;
                    return tag ? `<code class="text-xs">${U.esc(tag)}</code>` : '<span class="muted">—</span>';
                },
            },
            {
                label: 'Created', sort: 'created_at',
                render: (r) => `<span class="muted">${U.ago(r.created_at || r.createdAt)}</span>`,
            },
        ],
        rowActions: (row) => {
            const actions = [];
            const st = row.status;
            const id = row.id;
            const vn = row.version_name || row.versionName;

            // Publish (if draft or archived)
            if (st === 'draft' || st === 'archived') {
                actions.push({
                    label: 'Publish',
                    icon: 'fa-circle-check',
                    onClick: () => publishRelease(id, vn),
                });
            }

            // Archive (if published)
            if (st === 'published') {
                actions.push({
                    label: 'Move to Previous Versions',
                    icon: 'fa-box-archive',
                    onClick: () => archiveRelease(id, vn),
                });
            }

            if (st === 'published' || st === 'archived') {
                actions.push({label: 'Withdraw Release', icon: 'fa-ban', danger: true, onClick: () => changeAvailability(id, vn, 'withdrawn')});
            }
            if (st === 'withdrawn') {
                actions.push({label: 'Restore to Previous Versions', icon: 'fa-rotate-left', onClick: () => changeAvailability(id, vn, 'archived')});
            }

            // Edit notes
            actions.push({
                label: 'Edit Notes',
                icon: 'fa-pen',
                onClick: () => editNotes(id, row.release_notes || row.releaseNotes || ''),
            });

            actions.push({ divider: true });

            // Share the verification page for any downloadable release.
            if (st === 'published' || st === 'archived') {
                actions.push({
                    label: 'Copy Download Page',
                    icon: 'fa-link',
                    onClick: () => {
                        const url = `${BASE}download?release=${encodeURIComponent(id)}#changelog`;
                        navigator.clipboard.writeText(url).then(() => toast.success('Download page copied')).catch(() => toast.error('Could not copy the link'));
                    },
                });
            }

            // Download (admin — any status)
            actions.push({
                label: 'Download APK',
                icon: 'fa-download',
                onClick: () => {
                    window.open(`${BASE}api/v1/admin/app-releases/${id}/download`, '_blank');
                },
            });

            // Delete (draft only)
            if (st === 'draft') {
                actions.push({ divider: true });
                actions.push({
                    label: 'Delete',
                    icon: 'fa-trash-can',
                    danger: true,
                    onClick: async () => {
                        const ok = await HAZRA.confirm({
                            title: `Delete v${vn}?`,
                            body: 'This will permanently remove the APK file from the server.',
                            danger: true,
                            confirmLabel: 'Delete',
                        });
                        if (ok) {
                            await store.remove('app-releases', id);
                            toast.success(`v${vn} deleted`);
                            list.load();
                            paintStats();
                        }
                    },
                });
            }

            return actions;
        },
    });

    // Wire up event listeners
    document.getElementById('uploadBtn').addEventListener('click', openUploadModal);
    
    // Initial fetch
    paintStats();

    // -- Sub-functions --

    async function paintStats() {
        try {
            const counts = await Promise.all(['', 'published', 'draft', 'archived', 'withdrawn'].map(status => store.list('app-releases', {pageSize: 0, status})));
            const [total, published, drafts, archived, withdrawn] = counts.map(result => result.total || 0);
            document.getElementById('statStrip').innerHTML = [
                statCard('fa-box-archive', 'Total', total, 'var(--info)'),
                statCard('fa-circle-check', 'Published', published, 'var(--good)'),
                statCard('fa-pen-ruler', 'Drafts', drafts, 'var(--warn)'),
                statCard('fa-archive', 'Previous releases', archived, 'var(--text-mid)'),
                statCard('fa-ban', 'Withdrawn', withdrawn, 'var(--bad)'),
            ].join('');
        } catch (e) { /* silent */ }
    }

    function statCard(icon, label, value, color) {
        return `
            <div class="card stat-card">
                <div class="stat-card__icon" style="color:${color}"><i class="fa-solid ${icon}"></i></div>
                <div class="stat-card__body">
                    <span class="stat-card__value">${value}</span>
                    <span class="stat-card__label">${label}</span>
                </div>
            </div>`;
    }

    async function publishRelease(id, vn) {
        const ok = await HAZRA.confirm({
            title: `Publish v${vn}?`,
            body: 'This becomes the live public download. Any previously published version will be archived automatically.',
            confirmLabel: 'Publish',
        });
        if (!ok) return;
        try {
            await store.update('app-releases', id, { status: 'published' });
            toast.success(`v${vn} is now live. Previous version archived.`);
            list.load();
            paintStats();
        } catch (err) {
            toast.error('Publish failed', { body: err.message || 'Check that the APK file exists on the server.' });
        }
    }

    async function archiveRelease(id, vn) {
        const ok = await HAZRA.confirm({
            title: `Archive v${vn}?`,
            body: 'This moves the release into Previous Versions. Employees can still verify and download it. Use Withdraw Release to disable downloads.',
            confirmLabel: 'Archive',
        });
        if (!ok) return;
        try {
            await store.update('app-releases', id, { status: 'archived' });
            toast.success(`v${vn} archived`);
            list.load();
            paintStats();
        } catch (err) {
            toast.error('Archive failed', { body: err.message || 'Unknown error' });
        }
    }

    async function changeAvailability(id, vn, status) {
        const withdrawn = status === 'withdrawn';
        const ok = await HAZRA.confirm({ title: `${withdrawn ? 'Withdraw' : 'Restore'} v${vn}?`, body: withdrawn ? 'Employees will no longer see or download this release. Its APK and history remain available to administrators.' : 'This release will appear in Previous Versions and become downloadable after employee verification.', danger: withdrawn, confirmLabel: withdrawn ? 'Withdraw' : 'Restore' });
        if (!ok) return;
        try {
            await store.update('app-releases', id, {status});
            toast.success(withdrawn ? 'Release withdrawn' : 'Release restored');
            list.load(); paintStats();
        } catch (err) { toast.error('Could not update release', {body: err.message}); }
    }

    async function editNotes(id, currentNotes) {
        HAZRA.modal.open({
            title: 'Edit Release Notes',
            html: `
                <div class="col gap-2">
                    <label class="label">Release Notes</label>
                    <textarea id="editNotesArea" class="input" rows="8" placeholder="What's new in this release...">${U.esc(currentNotes)}</textarea>
                </div>`,
            footer: `
                <button class="btn btn--ghost" data-close>Cancel</button>
                <button class="btn btn--primary" id="saveNotesBtn">Save Notes</button>`,
            onMount: (el, close) => {
                el.querySelector('#saveNotesBtn').addEventListener('click', async () => {
                    const notes = el.querySelector('#editNotesArea').value;
                    await store.update('app-releases', id, { release_notes: notes });
                    toast.success('Release notes updated');
                    close();
                    list.load();
                });
            },
        });
    }

    function openUploadModal() {
        HAZRA.modal.open({
            title: 'Upload APK',
            wide: true,
            html: `
                <div class="col gap-3">
                    <div>
                        <label class="label">APK File <span class="text-bad">*</span></label>
                        <input type="file" id="apkFile" class="input" accept=".apk">
                        <small class="muted text-xs">Maximum 100 MB</small>
                    </div>
                    <div class="row gap-3">
                        <div class="grow">
                            <label class="label">Version Name <span class="text-bad">*</span></label>
                            <input type="text" id="apkVersionName" class="input" placeholder="1.2.0">
                        </div>
                        <div class="grow">
                            <label class="label">Version Code <span class="text-bad">*</span></label>
                            <input type="number" id="apkVersionCode" class="input" placeholder="12" min="1">
                        </div>
                    </div>
                    <div>
                        <label class="label">Minimum Android API (optional)</label>
                        <input type="number" id="apkMinSdk" class="input" min="1" max="999" placeholder="e.g. 26 for Android 8.0">
                    </div>
                    <div>
                        <label class="label">Channel</label>
                        <select id="apkChannel" class="input"><option value="production">Production</option><option value="beta">Beta</option></select>
                    </div>
                    <div>
                        <label class="label">Release Notes</label>
                        <textarea id="apkNotes" class="input" rows="4" placeholder="What's new..."></textarea>
                    </div>
                    <div id="uploadProgress" style="display:none">
                        <div class="row gap-2 items-center">
                            <div class="spinner"></div>
                            <span id="uploadStatus">Uploading...</span>
                        </div>
                    </div>
                </div>`,
            footer: `
                <button class="btn btn--ghost" data-close>Cancel</button>
                <button class="btn btn--primary" id="doUploadBtn"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>`,
            onMount: (el, close) => {
                el.querySelector('#doUploadBtn').addEventListener('click', () => doUpload(el, close));
            },
        });
    }

    async function doUpload(el, close) {
        const fileInput = el.querySelector('#apkFile');
        const versionName = el.querySelector('#apkVersionName').value.trim();
        const versionCode = el.querySelector('#apkVersionCode').value.trim();
        const notes = el.querySelector('#apkNotes').value.trim();

        if (!fileInput.files.length) { toast.error('Please select an APK file'); return; }
        if (!versionName) { toast.error('Version name is required'); return; }
        if (!versionCode) { toast.error('Version code is required'); return; }

        const btn = el.querySelector('#doUploadBtn');
        const progress = el.querySelector('#uploadProgress');
        btn.disabled = true;
        progress.style.display = '';

        const fd = new FormData();
        fd.append('file', fileInput.files[0]);
        fd.append('version_name', versionName);
        fd.append('version_code', versionCode);
        fd.append('platform', 'android');
        fd.append('channel', el.querySelector('#apkChannel').value);
        if (el.querySelector('#apkMinSdk').value) fd.append('min_android_sdk', el.querySelector('#apkMinSdk').value);
        if (notes) fd.append('release_notes', notes);

        try {
            const res = await fetch(`${BASE}api/v1/admin/app-releases`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF },
                body: fd,
            });

            const json = await res.json();

            if (!res.ok) {
                throw new Error(json?.error?.message || 'Upload failed');
            }

            toast.success(`v${versionName} uploaded as draft`);
            close();
            list.load();
            paintStats();
        } catch (err) {
            toast.error('Upload failed', { body: err.message });
            btn.disabled = false;
            progress.style.display = 'none';
        }
    }

    function fmtSize(bytes) {
        if (!bytes || bytes === 0) return '—';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function statusBadge(status) {
        switch (status) {
            case 'published': return '<span class="tag ok"><i class="fa-solid fa-circle-check"></i> Published</span>';
            case 'withdrawn': return '<span class="tag warn"><i class="fa-solid fa-ban"></i> Withdrawn</span>';
            case 'draft':     return '<span class="tag warn"><i class="fa-solid fa-pen-ruler"></i> Draft</span>';
            case 'archived':  return '<span class="tag muted"><i class="fa-solid fa-box-archive"></i> Previous release</span>';
            default:          return `<span class="tag">${U.esc(status)}</span>`;
        }
    }
}
})();

