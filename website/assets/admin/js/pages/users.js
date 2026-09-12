/* =========================================================
   Users & Roles.

   Two tabs: the people, and the roles they are given. The
   super-admin band above the table is the answer to "how do I
   give someone else full panel access" — it names who has it
   today and adds the next one in two clicks.

   Every destructive action routes through session.isLastSuper()
   first. Deleting, suspending or demoting the only active super
   admin locks the hospital out of its own panel, so the panel
   refuses rather than warns.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, table, layout, toast, session, form: formLib, fields: F,
    } = window.TMH;

    const MODULES = [
        ['content', 'Content'],
        ['pages', 'Pages'],
        ['careers', 'Careers'],
        ['growth', 'Growth'],
        ['system', 'System'],
    ];
    const ACTIONS = ['view', 'create', 'edit', 'delete', 'publish'];

    let list = null;

    window.TMH.boot(init);

    async function init() {
        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'Users & Roles' }],
            title: 'Users',
            accent: '& Roles',
            sub: 'Who can sign in to this panel, and how much of it they can reach.',
            actions: `
                <a class="btn btn--ghost" href="activity-log">
                    <i class="fa-solid fa-clock-rotate-left"></i> Activity log</a>
                <a class="btn btn--primary" href="user-form">
                    <i class="fa-solid fa-user-plus"></i> Add user</a>`,
        });

        /* A role cannot be deleted while somebody holds it — the user would be
           left pointing at nothing and silently lose every permission. */
        store.registerDependents('roles', (id) => store.allSync('users')
            .filter((u) => u.roleId === id)
            .map((u) => `Held by ${u.name}`));

        document.getElementById('view').innerHTML = `
            <div id="strip"></div>

            <article class="card card--flush anim-item">
                <div class="tabs" role="tablist" aria-label="Users and roles">
                    <button type="button" role="tab" data-tab="tab-users" aria-selected="false">
                        <i class="fa-solid fa-users"></i> Users
                        <span class="pill" id="userCount">0</span>
                    </button>
                    <button type="button" role="tab" data-tab="tab-roles" aria-selected="false">
                        <i class="fa-solid fa-user-shield"></i> Roles
                        <span class="pill" id="roleCount">0</span>
                    </button>
                </div>

                <div class="tab-panel" id="tab-users" role="tabpanel" hidden>
                    <div id="superBand" class="user-band"></div>
                    <div id="listCard"></div>
                </div>

                <div class="tab-panel" id="tab-roles" role="tabpanel" hidden>
                    <div id="rolesView"></div>
                </div>
            </article>`;

        buildTable();
        U.wireTabs(document.getElementById('view'), {
            onChange: (id) => {
                if (id === 'tab-roles') paintRoles();
            },
        });

        await paintStats();
        paintSuperBand();
        paintRoles();
    }

    /* ---------------------------------------------------------
       Summary strip
       --------------------------------------------------------- */
    async function paintStats() {
        const rows = await store.all('users');
        const roles = await store.all('roles');
        const supers = rows.filter((u) => u.roleId === session.SUPER_ROLE && u.status === 'active');
        const twoFactor = rows.filter((u) => u.twoFactor).length;

        document.getElementById('strip').innerHTML = U.statStrip([
            ['fa-users', 'red', rows.length, 'Accounts', `${rows.filter((u) => u.status === 'active').length} active`],
            ['fa-user-shield', 'navy', supers.length, 'Super admins', supers.length === 1
                ? 'Only one — add a second for cover' : 'Full access to every screen'],
            ['fa-envelope-circle-check', 'blue', rows.filter((u) => u.status === 'invited').length, 'Invited',
                'Have not signed in yet'],
            ['fa-shield-halved', 'magenta', `${twoFactor}/${rows.length}`, 'Two-factor on',
                twoFactor < rows.length ? 'Some accounts rely on a password alone' : 'Every account is covered'],
        ]);

        document.getElementById('userCount').textContent = rows.length;
        document.getElementById('roleCount').textContent = roles.length;
        U.stagger(document.getElementById('strip'));
    }

    /* ---------------------------------------------------------
       Super-admin band — the "add another super admin" entry
       point, and the standing list of who already has one.
       --------------------------------------------------------- */
    function paintSuperBand() {
        const supers = store.allSync('users').filter((u) => u.roleId === session.SUPER_ROLE);
        const active = supers.filter((u) => u.status === 'active');

        const chips = supers.map((u) => `
            <button type="button" class="chip" data-open-user="${U.esc(u.id)}" title="Open ${U.esc(u.name)}">
                <i class="fa-solid fa-user-shield"></i> ${U.esc(u.name)}
                ${u.status === 'active' ? '' : `<span class="muted">· ${U.esc((session.USER_STATUS[u.status] || {}).label || u.status)}</span>`}
                ${u.id === session.CURRENT_ID ? '<span class="muted">· you</span>' : ''}
            </button>`).join(' ');

        document.getElementById('superBand').innerHTML = `
            <div class="banner ${active.length < 2 ? 'banner--warn' : 'banner--info'}">
                <i class="fa-solid ${active.length < 2 ? 'fa-triangle-exclamation' : 'fa-user-shield'}"></i>
                <span class="grow">
                    <b>${active.length} super admin${active.length === 1 ? '' : 's'} can manage this panel.</b>
                    ${active.length < 2
                        ? 'If this account is locked out, nobody else can add users, change settings or publish. Add a second one.'
                        : 'They reach every screen, including users, roles and settings.'}
                    <span class="row gap-2 mt-2" style="flex-wrap:wrap">${chips || '<span class="muted">Nobody yet.</span>'}</span>
                </span>
                <button type="button" class="btn btn--primary btn--sm" id="addSuperBtn">
                    <i class="fa-solid fa-plus"></i> Add super admin</button>
            </div>`;

        document.getElementById('addSuperBtn').addEventListener('click', () => {
            location.href = `user-form?role=${encodeURIComponent(session.SUPER_ROLE)}`;
        });

        document.querySelectorAll('[data-open-user]').forEach((btn) => {
            btn.addEventListener('click', () => {
                location.href = `user-form?id=${encodeURIComponent(btn.dataset.openUser)}`;
            });
        });
    }

    /* ---------------------------------------------------------
       Users table
       --------------------------------------------------------- */
    function buildTable() {
        const roles = store.allSync('roles').map((r) => ({ value: r.id, label: r.name }));

        list = table.create({
            mount: '#listCard',
            entity: 'users',
            searchFields: ['name', 'email', 'phone'],
            searchPlaceholder: 'Search by name or email',
            sort: 'order',
            selectable: false,
            statusOptions: [
                { value: 'all', label: 'All' },
                { value: 'active', label: 'Active' },
                { value: 'invited', label: 'Invited' },
                { value: 'suspended', label: 'Suspended' },
            ],
            filters: [{ key: 'roleId', label: 'Role', options: roles }],
            empty: {
                icon: 'fa-user-plus',
                title: 'No accounts yet',
                text: 'Add the first person who should be able to sign in to this panel.',
                actionLabel: 'Add user',
                onAction: () => { location.href = 'user-form'; },
            },
            columns: [
                {
                    label: 'User', sort: 'name', width: '28%',
                    render: (r, s) => `
                        <div class="cell-media">
                            ${r.avatar
                                ? `<img class="avatar avatar--sq" src="${U.esc(r.avatar)}" alt="" loading="lazy">`
                                : `<span class="avatar avatar--sq" style="display:grid;place-items:center;font-size:11px;font-weight:700;color:var(--text-mid)">${U.esc(U.initials(r.name))}</span>`}
                            <span>
                                <span class="cell-main">${U.mark(r.name, s.q)}${
                                    r.id === session.CURRENT_ID ? ' <span class="pill">You</span>' : ''}</span>
                                <span class="cell-sub">${U.mark(r.email, s.q)}</span>
                            </span>
                        </div>`,
                },
                {
                    label: 'Role', sort: 'roleId', width: '18%',
                    render: (r) => (r.roleId === session.SUPER_ROLE
                        ? `<span class="tag info"><i class="fa-solid fa-user-shield"></i> ${U.esc(session.roleName(r.roleId))}</span>`
                        : `<span class="pill">${U.esc(session.roleName(r.roleId))}</span>`)
                        + (r.customPermissions ? '<span class="cell-sub">Custom permissions</span>' : ''),
                },
                {
                    label: 'Last active', sort: 'lastActiveAt', width: '14%',
                    render: (r) => (r.lastActiveAt
                        ? U.esc(U.ago(r.lastActiveAt))
                        : '<span class="muted">Never signed in</span>'),
                },
                {
                    label: 'Password', width: '13%',
                    render: (r) => (r.passwordUpdatedAt
                        ? `<span class="text-sm">Changed ${U.esc(U.ago(r.passwordUpdatedAt))}</span>`
                        : '<span class="muted">Never changed</span>'),
                },
                {
                    label: '2FA', sort: 'twoFactor', width: '8%',
                    render: (r) => (r.twoFactor
                        ? '<span class="tag ok">On</span>'
                        : '<span class="tag off">Off</span>'),
                },
                {
                    label: 'Status', sort: 'status', width: '11%',
                    render: (r) => session.statusTag(r.status),
                },
            ],
            rowActions: (row) => rowActions(row),
            onRowClick: (row) => { location.href = `user-form?id=${encodeURIComponent(row.id)}`; },
        });

        const created = U.param('created');
        if (created) {
            setTimeout(() => list.flash(created), 500);
            U.setParams({ created: '' });
        }
    }

    function refresh() {
        list.load();
        paintStats();
        paintSuperBand();
    }

    /* A guard that explains itself. Returns true when the action must stop. */
    async function blockedAsLastSuper(row, what) {
        if (!session.isLastSuper(row)) return false;
        await window.TMH.confirm({
            title: `Cannot ${what} ${row.name}`,
            body: 'They are the only active super admin. Give someone else the Super Admin role first, '
                + 'or nobody will be able to manage users and settings.',
            blocked: true,
            danger: true,
            icon: 'fa-user-shield',
        });
        return true;
    }

    function rowActions(row) {
        const isSuper = row.roleId === session.SUPER_ROLE;
        const out = [
            {
                label: 'Edit', icon: 'fa-pen',
                onClick: () => { location.href = `user-form?id=${encodeURIComponent(row.id)}`; },
            },
            {
                label: isSuper ? 'Remove super admin' : 'Make super admin',
                icon: isSuper ? 'fa-user-minus' : 'fa-user-shield',
                onClick: () => (isSuper ? demote(row) : promote(row)),
            },
            {
                label: 'Set a new password', icon: 'fa-key',
                onClick: () => resetPassword(row),
            },
        ];

        if (row.status === 'invited') {
            out.push({
                label: 'Resend invite', icon: 'fa-paper-plane',
                onClick: () => toast.success(`Invite resent to ${row.email}`, {
                    body: 'Email delivery lands with the backend.',
                }),
            });
        }

        out.push({ divider: true });

        out.push(row.status === 'suspended'
            ? {
                label: 'Restore access', icon: 'fa-user-check',
                onClick: async () => {
                    await store.update('users', row.id, { status: 'active' });
                    toast.success(`${row.name} can sign in again`);
                    refresh();
                },
            }
            : {
                label: 'Suspend', icon: 'fa-user-lock', danger: true,
                onClick: async () => {
                    if (await blockedAsLastSuper(row, 'suspend')) return;
                    if (row.id === session.CURRENT_ID) {
                        await window.TMH.confirm({
                            title: 'Cannot suspend your own account',
                            body: 'You would be signed out with no way back in. Ask another super admin to do it.',
                            blocked: true, danger: true, icon: 'fa-user-lock',
                        });
                        return;
                    }
                    const ok = await window.TMH.confirm({
                        title: `Suspend ${row.name}?`,
                        body: 'They keep their account and their permissions, but cannot sign in until you restore them.',
                        danger: true,
                        confirmLabel: 'Suspend',
                        icon: 'fa-user-lock',
                    });
                    if (!ok) return;
                    await store.update('users', row.id, { status: 'suspended' });
                    toast.success(`${row.name} suspended`);
                    refresh();
                },
            });

        out.push({
            label: 'Delete', icon: 'fa-trash-can', danger: true,
            onClick: async () => {
                if (await blockedAsLastSuper(row, 'delete')) return;
                if (row.id === session.CURRENT_ID) {
                    await window.TMH.confirm({
                        title: 'Cannot delete your own account',
                        body: 'Ask another super admin to remove it, so you are not signed out mid-edit.',
                        blocked: true,
                        danger: true,
                        icon: 'fa-circle-user',
                    });
                    return;
                }
                const done = await list.confirmDelete(row, {
                    body: 'Their account is removed. Anything they published stays on the website.',
                });
                if (done) {
                    paintStats();
                    paintSuperBand();
                }
            },
        });

        return out;
    }

    /* ---------------------------------------------------------
       Promote / demote
       --------------------------------------------------------- */
    async function promote(row) {
        const ok = await window.TMH.confirm({
            title: `Make ${row.name} a super admin?`,
            body: 'They get every permission in the panel, including users, roles, settings and deleting content. '
                + 'This is the only role that can add other super admins.',
            confirmLabel: 'Make super admin',
            icon: 'fa-user-shield',
        });
        if (!ok) return;
        await store.update('users', row.id, {
            roleId: session.SUPER_ROLE,
            customPermissions: false,
            permissions: null,
        });
        toast.success(`${row.name} is now a super admin`, {
            action: { label: 'Undo', onClick: async () => {
                await store.update('users', row.id, { roleId: row.roleId });
                refresh();
            } },
        });
        refresh();
    }

    async function demote(row) {
        if (await blockedAsLastSuper(row, 'demote')) return;

        const roles = store.allSync('roles').filter((r) => r.id !== session.SUPER_ROLE);
        const picked = await formLib.editModal({
            title: `Remove super admin from ${row.name}`,
            subtitle: row.id === session.CURRENT_ID
                ? 'This is your own account — you will lose Users, Roles and Settings as soon as you save. '
                    + 'Only another super admin can give it back.'
                : 'Pick the role they keep. They lose access to users, roles and settings.',
            icon: 'fa-user-minus',
            saveLabel: 'Change role',
            record: { roleId: roles[0] ? roles[0].id : '' },
            html: F.section({
                fields: [
                    F.select({
                        name: 'roleId', label: 'New role', required: true, wide: true,
                        options: roles.map((r) => ({ value: r.id, label: `${r.name} — ${r.description}` })),
                    }),
                ],
            }),
        });
        if (!picked) return;

        await store.update('users', row.id, {
            roleId: picked.roleId,
            customPermissions: false,
            permissions: null,
        });
        toast.success(`${row.name} is now ${session.roleName(picked.roleId)}`);
        refresh();
    }

    /* An admin setting someone else's password never sees a current password —
       they are overriding it, not changing their own. The new one is shown
       once, to be handed over, and the account is flagged to change it on the
       next sign-in. */
    async function resetPassword(row) {
        const suggested = session.suggest();
        const data = await formLib.editModal({
            title: `Set a new password for ${row.name}`,
            subtitle: 'They are asked to change it the next time they sign in.',
            icon: 'fa-key',
            saveLabel: 'Set password',
            record: { newPassword: suggested },
            html: F.section({
                fields: [
                    F.text({
                        name: 'newPassword', label: 'New password', required: true, wide: true,
                        hint: 'At least 10 characters, with a letter and a number. Copy it before you close this box — it is not shown again.',
                    }),
                    F.toggle({
                        name: 'notify', label: `Email ${row.email} to say the password changed`,
                    }),
                ],
            }),
        });
        if (!data) return;

        const problem = session.passwordProblem(data.newPassword);
        if (problem) {
            toast.error(problem);
            return resetPassword(row);
        }

        await session.changePassword(row.id, data.newPassword);
        await store.update('users', row.id, { mustChangePassword: true });
        toast.success(`Password set for ${row.name}`, {
            body: data.notify ? `A note went to ${row.email}.` : 'Hand it over in person.',
            action: { label: 'Copy password', onClick: () => U.copy(data.newPassword) },
        });
        refresh();
    }

    /* ---------------------------------------------------------
       Roles tab
       --------------------------------------------------------- */
    function paintRoles() {
        const roles = store.allSync('roles');
        const users = store.allSync('users');

        document.getElementById('rolesView').innerHTML = `
            <div class="role-list">
                ${roles.map((role) => {
                    const members = users.filter((u) => u.roleId === role.id);
                    const isSuper = role.id === session.SUPER_ROLE;
                    return `
                    <article class="card card--quiet anim-item">
                        <div class="row gap-4" style="align-items:flex-start">
                            <span class="stat__icon ${isSuper ? 'red' : 'navy'}">
                                <i class="fa-solid ${isSuper ? 'fa-user-shield' : 'fa-user-tag'}"></i></span>
                            <span class="grow">
                                <h3 style="font-size:var(--fs-h3)">${U.esc(role.name)}</h3>
                                <p class="text-sm mid">${U.esc(role.description)}</p>
                                <span class="row gap-2 mt-2" style="flex-wrap:wrap">
                                    ${MODULES.map(([key, label]) => {
                                        const granted = (role.permissions && role.permissions[key]) || [];
                                        return `<span class="pill" title="${U.esc(granted.join(', ') || 'No access')}">
                                            ${U.esc(label)}: ${granted.length ? U.esc(`${granted.length}/${ACTIONS.length}`) : '—'}</span>`;
                                    }).join('')}
                                </span>
                            </span>
                            <span class="role-card__count">
                                <b>${members.length}</b>
                                <span class="cell-sub">member${members.length === 1 ? '' : 's'}</span>
                                ${isSuper ? `
                                    <button type="button" class="btn btn--soft btn--sm mt-2" data-add-super>
                                        <i class="fa-solid fa-plus"></i> Add</button>` : ''}
                            </span>
                        </div>
                        ${members.length ? `
                            <div class="card__foot row gap-2" style="flex-wrap:wrap">
                                ${members.map((m) => `
                                    <a class="chip" href="user-form?id=${encodeURIComponent(m.id)}">
                                        ${U.esc(m.name)}
                                        ${m.status === 'active' ? '' : `<span class="muted">· ${U.esc((session.USER_STATUS[m.status] || {}).label || m.status)}</span>`}
                                    </a>`).join('')}
                            </div>` : ''}
                    </article>`;
                }).join('')}
            </div>

            <p class="text-sm muted" style="padding:0 var(--s5) var(--s5)">
                Roles themselves are fixed in Phase 1. A single account can still be given a
                permission its role does not carry — open the user and edit the matrix.
            </p>`;

        const addSuper = document.querySelector('[data-add-super]');
        if (addSuper) {
            addSuper.addEventListener('click', () => {
                location.href = `user-form?role=${encodeURIComponent(session.SUPER_ROLE)}`;
            });
        }

        U.stagger(document.getElementById('rolesView'));
    }
}());
