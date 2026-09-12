/* =========================================================
   User — create / edit, including the super-admin grant.

   Three parts: who they are, what they may reach, and the
   permission matrix. Picking a role fills the matrix; touching
   a cell afterwards marks the account as carrying custom
   permissions, so the matrix never silently disagrees with the
   role label on the list screen.

   ?role=role-super opens the form already set to Super Admin —
   the "Add super admin" button on users.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast, media, session,
    } = window.TMH;

    const MODULES = [
        ['content', 'Content', 'Doctors, departments, blog, media'],
        ['pages', 'Pages', 'Home, About, Contact, Careers'],
        ['careers', 'Careers', 'Vacancies and applications'],
        ['growth', 'Growth', 'Enquiries, appointments, SEO'],
        ['system', 'System', 'Settings, users, roles, activity log'],
    ];
    const ACTIONS = ['view', 'create', 'edit', 'delete', 'publish'];

    const id = U.param('id');
    const isEdit = !!id;
    const presetRole = U.param('role');

    let record = null;
    let roles = [];
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        record = isEdit ? await store.get('users', id) : null;

        if (isEdit && !record) {
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-user-slash"></i></div>
                    <h3>That account no longer exists</h3>
                    <a class="btn btn--primary mt-4" href="users">Back to users</a>
                </div></article>`;
            return;
        }

        roles = await store.all('roles');

        const startRole = (record && record.roleId)
            || (roles.some((r) => r.id === presetRole) ? presetRole : (roles[0] && roles[0].id));
        const superStart = startRole === session.SUPER_ROLE;

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [
                { label: 'System' },
                { label: 'Users & Roles', href: 'users' },
                { label: isEdit ? record.name : 'New user' },
            ],
            title: isEdit ? 'Edit' : 'Add a',
            accent: isEdit ? 'Account' : (superStart ? 'Super Admin' : 'User'),
            sub: isEdit
                ? 'Changes to a role take effect the next time they load a screen.'
                : 'They receive an invite email and set their own password on first sign-in.',
            actions: '<a class="btn btn--ghost" href="users"><i class="fa-solid fa-arrow-left"></i> Back</a>',
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <form class="card" id="userForm" novalidate>
                    ${F.section({
                        title: 'Identity', icon: 'fa-id-card',
                        fields: [
                            F.text({ name: 'name', label: 'Full name', required: true, placeholder: 'Riya Sarkar' }),
                            F.email({
                                name: 'email', label: 'Email', required: true,
                                placeholder: 'name@teresamemorialhospital.com',
                                hint: 'This is the sign-in address. The invite goes here.',
                            }),
                            F.text({ name: 'phone', label: 'Phone', rule: 'phone', placeholder: '+91 90460 05557' }),
                            F.media({ name: 'avatar', label: 'Photo', hint: 'Optional. Initials are used when there is none.' }),
                        ],
                    })}
                    ${F.divider()}
                    ${F.section({
                        title: 'Access', icon: 'fa-key',
                        sub: 'The role sets the matrix below. Super Admin is the only role that can manage users, roles and settings.',
                        fields: [
                            F.select({
                                name: 'roleId', label: 'Role', required: true,
                                options: roles.map((r) => ({ value: r.id, label: r.name })),
                            }),
                            F.select({
                                name: 'status', label: 'Account status',
                                options: [
                                    { value: 'invited', label: 'Invited — not signed in yet' },
                                    { value: 'active', label: 'Active — can sign in' },
                                    { value: 'suspended', label: 'Suspended — cannot sign in' },
                                ],
                            }),
                            F.toggle({
                                name: 'twoFactor', label: 'Require two-factor authentication',
                                hint: 'Strongly recommended for anyone who can publish or manage users.',
                            }),
                            ...(isEdit ? [] : [F.toggle({
                                name: 'sendInvite',
                                label: 'Email an invite so they set their own password',
                                hint: 'Turn this off to set a temporary password yourself and hand it over.',
                            })]),
                        ],
                    })}
                    ${isEdit ? '' : `
                        ${F.divider()}
                        <section class="form-section" id="tempPwSection" hidden>
                            <div class="form-section__head">
                                <h3><i class="fa-solid fa-lock" style="color:var(--brand-red)"></i> Temporary password</h3>
                                <p>They are asked to change it the first time they sign in.</p>
                            </div>
                            <div class="form-grid">
                                ${F.text({
                                    name: 'tempPassword', label: 'Password', wide: true,
                                    hint: 'At least 10 characters, with a letter and a number.',
                                })}
                                <div class="field field--wide">
                                    <button type="button" class="btn btn--soft btn--sm" id="suggestBtn">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> Suggest one</button>
                                </div>
                            </div>
                        </section>`}
                    ${F.divider()}
                    <section class="form-section">
                        <div class="form-section__head">
                            <h3><i class="fa-solid fa-table-cells-large" style="color:var(--brand-red)"></i> Permissions</h3>
                            <p>What this account may do in each part of the panel.
                               Editing a cell keeps the role label but marks the account as custom.</p>
                        </div>
                        <div id="permBanner"></div>
                        <div class="table-wrap">
                            <table class="perm-matrix">
                                <thead>
                                    <tr>
                                        <th style="text-align:left">Module</th>
                                        ${ACTIONS.map((a) => `<th>${U.esc(a[0].toUpperCase() + a.slice(1))}</th>`).join('')}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${MODULES.map(([key, label, note]) => `
                                        <tr>
                                            <th scope="row">${U.esc(label)}<small>${U.esc(note)}</small></th>
                                            ${ACTIONS.map((a) => `
                                                <td>
                                                    <input type="checkbox" name="perm.${U.esc(key)}.${U.esc(a)}"
                                                           aria-label="${U.esc(`${a} ${label}`)}">
                                                </td>`).join('')}
                                        </tr>`).join('')}
                                </tbody>
                            </table>
                        </div>
                        <div class="row gap-2 mt-4">
                            <button type="button" class="btn btn--ghost btn--sm" id="resetPermBtn">
                                <i class="fa-solid fa-rotate-left"></i> Reset to the role’s defaults</button>
                        </div>
                    </section>
                    ${F.bar({ singleSave: true, saveLabel: isEdit ? 'Save changes' : 'Create account' })}
                </form>

                <aside class="split__rail">
                    <article class="card" id="summaryCard"></article>
                    <article class="card card--quiet" id="metaCard"></article>
                </aside>
            </div>`;

        ctrl = formLib.create({
            el: '#userForm',
            bar: '#formBar',
            onCancel: () => { location.href = 'users'; },
            onSave: save,
        });

        ctrl.bind(Object.assign(
            {
                status: 'invited',
                twoFactor: true,
                sendInvite: true,
                roleId: startRole,
            },
            record || {},
            permsToFlat(effectivePermissions(record, startRole)),
        ));
        media.wire(document);

        wireRole();
        wireMatrix();
        if (!isEdit) wireTempPassword();

        paintSummary();
        paintMeta();

        const formEl = document.getElementById('userForm');
        formEl.addEventListener('input', U.debounce(paintSummary, 200));
        formEl.addEventListener('change', paintSummary);
    }

    /* ---------------------------------------------------------
       Permissions: record → matrix → record
       --------------------------------------------------------- */
    function roleById(roleId) {
        return roles.find((r) => r.id === roleId) || null;
    }

    /* A user with custom permissions carries its own copy; everyone else
       shows the role's, live, so a role edit is never stale on screen. */
    function effectivePermissions(user, roleId) {
        if (user && user.customPermissions && user.permissions) return user.permissions;
        const role = roleById(roleId || (user && user.roleId));
        return (role && role.permissions) || {};
    }

    function permsToFlat(permissions) {
        const flat = {};
        MODULES.forEach(([key]) => {
            const granted = (permissions && permissions[key]) || [];
            ACTIONS.forEach((a) => { flat[`perm.${key}.${a}`] = granted.includes(a); });
        });
        return flat;
    }

    function flatToPerms(data) {
        const out = {};
        MODULES.forEach(([key]) => {
            out[key] = ACTIONS.filter((a) => data[`perm.${key}.${a}`]);
        });
        return out;
    }

    function matrixMatchesRole(data, roleId) {
        const roleFlat = permsToFlat(effectivePermissions(null, roleId));
        return Object.keys(roleFlat).every((k) => !!roleFlat[k] === !!data[k]);
    }

    function applyPermissions(permissions) {
        const flat = permsToFlat(permissions);
        Object.entries(flat).forEach(([name, on]) => {
            const box = document.querySelector(`[name="${CSS.escape(name)}"]`);
            if (box) box.checked = on;
        });
    }

    /* ---------------------------------------------------------
       Wiring
       --------------------------------------------------------- */
    function wireRole() {
        const select = document.querySelector('[name="roleId"]');
        select.addEventListener('change', () => {
            applyPermissions(effectivePermissions(null, select.value));
            paintSummary();
        });
    }

    function wireMatrix() {
        document.querySelectorAll('.perm-matrix input[type="checkbox"]').forEach((box) => {
            /* Nothing is usable without view, and view alone is a valid grant,
               so the two are kept consistent rather than left to produce an
               account that may edit a screen it cannot open. */
            box.addEventListener('change', () => {
                const [, module, action] = box.name.split('.');
                const view = document.querySelector(`[name="perm.${module}.view"]`);
                if (action !== 'view' && box.checked && view) view.checked = true;
                if (action === 'view' && !box.checked) {
                    ACTIONS.filter((a) => a !== 'view').forEach((a) => {
                        const other = document.querySelector(`[name="perm.${module}.${a}"]`);
                        if (other) other.checked = false;
                    });
                }
                paintSummary();
            });
        });

        document.getElementById('resetPermBtn').addEventListener('click', () => {
            const roleId = document.querySelector('[name="roleId"]').value;
            applyPermissions(effectivePermissions(null, roleId));
            paintSummary();
            toast.info(`Matrix reset to ${session.roleName(roleId)}`);
        });
    }

    function wireTempPassword() {
        const invite = document.querySelector('[name="sendInvite"]');
        const section = document.getElementById('tempPwSection');
        const field = document.querySelector('[name="tempPassword"]');

        const sync = () => {
            section.hidden = invite.checked;
            /* A hidden field is not a required field — core/form.js validates
               every control it can find, so the flag moves with the section. */
            if (invite.checked) field.removeAttribute('required');
            else field.setAttribute('required', '');
        };
        invite.addEventListener('change', sync);
        sync();

        document.getElementById('suggestBtn').addEventListener('click', () => {
            field.value = session.suggest();
            field.dispatchEvent(new Event('input', { bubbles: true }));
            toast.info('Suggested a password', { body: 'Copy it before you save — it is not shown again.' });
        });
    }

    /* ---------------------------------------------------------
       Rail
       --------------------------------------------------------- */
    function paintSummary() {
        const data = ctrl.collect();
        const role = roleById(data.roleId);
        const isSuper = data.roleId === session.SUPER_ROLE;
        const custom = !matrixMatchesRole(data, data.roleId);
        const granted = flatToPerms(data);
        const total = Object.values(granted).reduce((n, list) => n + list.length, 0);

        document.getElementById('permBanner').innerHTML = isSuper ? `
            <div class="banner banner--warn mb-4">
                <i class="fa-solid fa-user-shield"></i>
                <span class="grow"><b>Super admins can do everything.</b>
                    That includes adding other super admins, changing settings and deleting content.
                    Give it only to the people who run the panel.</span>
            </div>` : '';

        document.getElementById('summaryCard').innerHTML = `
            <div class="card__head"><h3>What they will see</h3></div>
            <dl class="kv">
                <dt>Role</dt><dd>${U.esc(role ? role.name : '—')}</dd>
                <dt>Permissions</dt><dd>${total} of ${MODULES.length * ACTIONS.length}${
                    custom ? ' <span class="tag warn">Custom</span>' : ''}</dd>
                <dt>Status</dt><dd>${session.statusTag(data.status)}</dd>
                <dt>Two-factor</dt><dd>${data.twoFactor ? '<span class="tag ok">Required</span>' : '<span class="tag off">Off</span>'}</dd>
            </dl>
            <div class="card__foot">
                <p class="text-sm mid">${U.esc(role ? role.description : '')}</p>
            </div>`;
    }

    function paintMeta() {
        const card = document.getElementById('metaCard');

        if (!isEdit) {
            card.innerHTML = `
                <p class="text-sm mid">A new account starts as <b>Invited</b>. It becomes active the
                first time they sign in, or as soon as you set the status to Active yourself.</p>`;
            return;
        }

        const lastSuper = session.isLastSuper(record);

        card.innerHTML = `
            <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s3)">Account</h3>
            <dl class="kv">
                <dt>Last active</dt><dd>${U.esc(record.lastActiveAt ? U.ago(record.lastActiveAt) : 'Never signed in')}</dd>
                <dt>Password</dt><dd>${U.esc(record.passwordUpdatedAt ? `Changed ${U.ago(record.passwordUpdatedAt)}` : 'Never changed')}</dd>
                <dt>Added</dt><dd>${U.esc(record.createdAt ? U.fmtDate(record.createdAt) : '—')}</dd>
            </dl>
            ${lastSuper ? `
                <div class="banner banner--warn mt-4">
                    <i class="fa-solid fa-user-shield"></i>
                    <span class="grow">The only active super admin. Their role and status are locked
                        until somebody else holds it.</span>
                </div>` : ''}
            <div class="card__foot row gap-2">
                <button type="button" class="btn btn--soft btn--sm" id="pwBtn">
                    <i class="fa-solid fa-key"></i> Set a new password</button>
                ${record.id === session.CURRENT_ID ? '' : `
                    <button type="button" class="btn btn--ghost btn--sm" id="delBtn">
                        <i class="fa-solid fa-trash-can"></i> Delete</button>`}
            </div>`;

        document.getElementById('pwBtn').addEventListener('click', setPassword);

        const del = document.getElementById('delBtn');
        if (del) del.addEventListener('click', remove);
    }

    /* An admin setting somebody else's password is overriding it, not changing
       their own, so no current password is asked for. Their own password lives
       on profile, where the current one is required. */
    async function setPassword() {
        const data = await formLib.editModal({
            title: `Set a new password for ${record.name}`,
            subtitle: 'They are asked to change it the next time they sign in.',
            icon: 'fa-key',
            saveLabel: 'Set password',
            record: { newPassword: session.suggest() },
            html: F.section({
                fields: [
                    F.text({
                        name: 'newPassword', label: 'New password', required: true, wide: true,
                        hint: 'At least 10 characters, with a letter and a number. Copy it before you close this box.',
                    }),
                ],
            }),
        });
        if (!data) return;

        const problem = session.passwordProblem(data.newPassword);
        if (problem) {
            toast.error(problem);
            setPassword();
            return;
        }

        record = await session.changePassword(record.id, data.newPassword);
        record = await store.update('users', record.id, { mustChangePassword: true });
        toast.success('Password set', {
            action: { label: 'Copy password', onClick: () => U.copy(data.newPassword) },
        });
        paintMeta();
    }

    async function remove() {
        if (session.isLastSuper(record)) {
            await window.TMH.confirm({
                title: `Cannot delete ${record.name}`,
                body: 'They are the only active super admin. Give somebody else the role first.',
                blocked: true, danger: true, icon: 'fa-user-shield',
            });
            return;
        }
        const ok = await window.TMH.confirm({
            title: `Delete ${record.name}?`,
            body: 'Their account is removed and they can no longer sign in. Anything they published stays on the website.',
            danger: true, confirmLabel: 'Delete',
        });
        if (!ok) return;
        const removed = await store.remove('users', record.id);
        toast.success('Account deleted', {
            undo: () => store.restore('users', removed.row, removed.index),
        });
        setTimeout(() => { location.href = 'users'; }, 900);
    }

    /* ---------------------------------------------------------
       Save
       --------------------------------------------------------- */
    function nextUserId() {
        const rows = store.allSync('users');
        let n = rows.length + 1;
        let candidate = `usr-${String(n).padStart(3, '0')}`;
        while (rows.some((r) => r.id === candidate)) {
            n += 1;
            candidate = `usr-${String(n).padStart(3, '0')}`;
        }
        return candidate;
    }

    async function save(data) {
        const custom = !matrixMatchesRole(data, data.roleId);

        /* The last super admin cannot be demoted or suspended from this form
           either — otherwise the guard on the list screen is only a speed bump.
           Checked against the stored record, not the form, because the form is
           exactly what is trying to change it. */
        if (isEdit && session.isLastSuper(record)
            && (data.roleId !== session.SUPER_ROLE || data.status !== 'active')) {
            toast.error('This is the only active super admin', {
                body: 'Give somebody else the Super Admin role before changing this account.',
            });
            return;
        }

        const payload = {
            name: data.name,
            email: data.email,
            phone: data.phone,
            avatar: data.avatar,
            roleId: data.roleId,
            status: data.status,
            twoFactor: !!data.twoFactor,
            customPermissions: custom,
            /* Only a custom account stores a matrix of its own; the rest read
               their role's, so a later role edit reaches them. */
            permissions: custom ? flatToPerms(data) : null,
        };

        if (isEdit) {
            record = await store.update('users', id, payload);
            toast.success('Changes saved');
            paintMeta();
            paintSummary();
            return;
        }

        const invited = data.sendInvite !== false;
        const created = await store.create('users', Object.assign(payload, {
            /* store.nextId() would mint "use-008" from the entity name; the
               seeded accounts are usr-001…, and the activity log joins on
               these ids by eye during Phase 1. */
            id: nextUserId(),
            status: data.status || 'invited',
            lastActiveAt: '',
            /* No password, plain or hashed, is written in Phase 1 — see
               core/session.js. Only the fact that one was set is kept. */
            passwordUpdatedAt: invited ? '' : new Date().toISOString(),
            mustChangePassword: !invited,
        }));

        toast.success(`${created.name} added`, {
            body: invited
                ? `An invite goes to ${created.email}.`
                : 'Hand over the temporary password in person.',
            action: !invited && data.tempPassword
                ? { label: 'Copy password', onClick: () => U.copy(data.tempPassword) }
                : null,
        });

        setTimeout(() => {
            location.href = `users?created=${encodeURIComponent(created.id)}`;
        }, 700);
    }
}());
