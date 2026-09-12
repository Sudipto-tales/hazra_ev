/* =========================================================
   My Profile — the signed-in user's own account.

   Three tabs: who they are, how they sign in, and how the
   panel behaves for them. The password form lives here rather
   than on user-form because changing your own password
   asks for the current one, and overriding somebody else's
   does not — two different flows that only look alike.

   profile?tab=security opens straight on the password
   form; the account menu in the topbar links to it.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast, media, session,
    } = window.TMH;

    let me = null;
    let ctrl = null;

    window.TMH.boot(init);

    async function init() {
        me = await session.current();

        if (!me) {
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-user-slash"></i></div>
                    <h3>No account is signed in</h3>
                    <p>Reset the demo data from the account menu to restore the seeded accounts.</p>
                </div></article>`;
            return;
        }

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'My Profile' }],
            title: 'My',
            accent: 'Profile',
            sub: 'Your own account. Everyone else is managed under Users & Roles.',
            actions: session.isSuper(me)
                ? `<a class="btn btn--ghost" href="users">
                       <i class="fa-solid fa-user-shield"></i> Manage users</a>`
                : '',
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <div>
                    <article class="card card--flush anim-item">
                        <div class="tabs" role="tablist" aria-label="Profile sections">
                            <button type="button" role="tab" data-tab="account" aria-selected="false">
                                <i class="fa-solid fa-id-card"></i> Account</button>
                            <button type="button" role="tab" data-tab="security" aria-selected="false">
                                <i class="fa-solid fa-key"></i> Sign-in & security</button>
                            <button type="button" role="tab" data-tab="preferences" aria-selected="false">
                                <i class="fa-solid fa-sliders"></i> Panel preferences</button>
                        </div>

                        <div class="tab-panel profile-panel" id="account" role="tabpanel" hidden>
                            <form id="accountForm" novalidate>
                                ${F.section({
                                    title: 'Your details', icon: 'fa-id-card',
                                    sub: 'The name here is what shows on the activity log beside everything you edit.',
                                    fields: [
                                        F.text({ name: 'name', label: 'Full name', required: true }),
                                        F.email({
                                            name: 'email', label: 'Email', required: true,
                                            hint: 'Also your sign-in address.',
                                        }),
                                        F.text({ name: 'phone', label: 'Phone', rule: 'phone', placeholder: '+91 90460 05557' }),
                                        F.media({ name: 'avatar', label: 'Photo', hint: 'Optional. Your initials are used when there is none.' }),
                                    ],
                                })}
                                ${F.bar({ singleSave: true, saveLabel: 'Save profile', noCancel: true })}
                            </form>
                        </div>

                        <div class="tab-panel profile-panel" id="security" role="tabpanel" hidden>
                            <form id="pwForm" novalidate>
                                ${F.section({
                                    title: 'Change your password', icon: 'fa-key',
                                    sub: 'You stay signed in on this device. Every other device is signed out.',
                                    fields: [
                                        F.text({
                                            name: 'currentPassword', type: 'password', label: 'Current password',
                                            required: true, wide: true,
                                        }),
                                        F.text({
                                            name: 'newPassword', type: 'password', label: 'New password',
                                            required: true, wide: true,
                                            hint: `At least 10 characters, with a letter and a number.
                                                <span class="pw-meter" id="pwMeter" data-score="0" aria-hidden="true">
                                                    <span></span><span></span><span></span><span></span></span>`,
                                        }),
                                        F.text({
                                            name: 'confirmPassword', type: 'password', label: 'Repeat the new password',
                                            required: true, wide: true,
                                        }),
                                    ],
                                })}
                                <div class="row gap-2">
                                    <button type="button" class="btn btn--primary" id="pwSaveBtn">
                                        <i class="fa-solid fa-key"></i> Change password</button>
                                    <button type="button" class="btn btn--ghost" id="pwSuggestBtn">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> Suggest one</button>
                                </div>
                            </form>

                            ${F.divider()}

                            <section class="form-section">
                                <div class="form-section__head">
                                    <h3><i class="fa-solid fa-shield-halved" style="color:var(--brand-red)"></i> Two-factor authentication</h3>
                                    <p>A code from your phone on top of the password.</p>
                                </div>
                                <div class="form-grid">
                                    <div class="field field--wide">
                                        <label class="toggle">
                                            <input type="checkbox" id="twoFactorBox">
                                            <span class="toggle__track"></span>
                                            <span class="toggle__text">Ask for a code when I sign in</span>
                                        </label>
                                        <small id="twoFactorNote"></small>
                                    </div>
                                </div>
                            </section>

                            ${F.divider()}

                            <section class="form-section">
                                <div class="form-section__head">
                                    <h3><i class="fa-solid fa-laptop" style="color:var(--brand-red)"></i> Where you are signed in</h3>
                                    <p>Signing out everywhere ends every session except this one.</p>
                                </div>
                                <div class="form-grid">
                                    <div class="field field--wide" id="sessionList"></div>
                                </div>
                            </section>
                        </div>

                        <div class="tab-panel profile-panel" id="preferences" role="tabpanel" hidden>
                            <form id="prefForm" novalidate>
                                ${F.section({
                                    title: 'How the panel behaves', icon: 'fa-sliders',
                                    sub: 'Yours alone — these change nothing for anyone else and nothing on the website.',
                                    fields: [
                                        F.select({
                                            name: 'language', label: 'Panel language',
                                            options: [
                                                { value: 'en', label: 'English' },
                                                { value: 'bn', label: 'বাংলা (Bengali)' },
                                            ],
                                        }),
                                        F.select({
                                            name: 'timezone', label: 'Timezone',
                                            options: [
                                                { value: 'Asia/Kolkata', label: 'Asia/Kolkata (IST)' },
                                                { value: 'Asia/Dhaka', label: 'Asia/Dhaka' },
                                                { value: 'UTC', label: 'UTC' },
                                            ],
                                            hint: 'Every date and time in the panel is shown in this zone.',
                                        }),
                                        F.select({
                                            name: 'landingPage', label: 'Open the panel on',
                                            options: [
                                                { value: 'dashboard', label: 'Dashboard' },
                                                { value: 'enquiries', label: 'Enquiries' },
                                                { value: 'appointments', label: 'Appointments' },
                                                { value: 'blog', label: 'Blog & News' },
                                            ],
                                        }),
                                        F.select({
                                            name: 'emailDigest', label: 'Email digest',
                                            options: [
                                                { value: 'off', label: 'Off' },
                                                { value: 'daily', label: 'Daily — new enquiries and appointments' },
                                                { value: 'weekly', label: 'Weekly summary' },
                                            ],
                                        }),
                                    ],
                                })}
                                <div class="row gap-2">
                                    <button type="button" class="btn btn--primary" id="prefSaveBtn">
                                        <i class="fa-solid fa-floppy-disk"></i> Save preferences</button>
                                </div>
                            </form>
                        </div>
                    </article>
                </div>

                <aside class="split__rail">
                    <article class="card" id="whoCard"></article>
                    <article class="card card--quiet" id="accessCard"></article>
                </aside>
            </div>`;

        ctrl = formLib.create({
            el: '#accountForm',
            bar: '#formBar',
            onSave: saveAccount,
        });
        ctrl.bind(me);
        media.wire(document);

        formLib.bind(document.getElementById('prefForm'), {
            language: me.language || 'en',
            timezone: me.timezone || 'Asia/Kolkata',
            landingPage: me.landingPage || 'dashboard',
            emailDigest: me.emailDigest || 'daily',
        });

        U.wireTabs(document.getElementById('view'));
        wireSecurity();
        wirePreferences();

        paintWho();
        paintAccess();
        paintSessions();
    }

    /* ---------------------------------------------------------
       Account
       --------------------------------------------------------- */
    async function saveAccount(data) {
        me = await store.update('users', me.id, {
            name: data.name,
            email: data.email,
            phone: data.phone,
            avatar: data.avatar,
        });
        toast.success('Profile saved', { body: 'The name in the sidebar updates on the next screen you open.' });
        paintWho();
    }

    /* ---------------------------------------------------------
       Security
       --------------------------------------------------------- */
    function wireSecurity() {
        const scope = document.getElementById('pwForm');
        const newPw = scope.querySelector('[name="newPassword"]');
        const meter = document.getElementById('pwMeter');

        newPw.addEventListener('input', () => {
            meter.dataset.score = String(session.strength(newPw.value));
        });

        document.getElementById('pwSuggestBtn').addEventListener('click', async () => {
            const suggested = session.suggest();
            newPw.value = suggested;
            scope.querySelector('[name="confirmPassword"]').value = suggested;
            meter.dataset.score = String(session.strength(suggested));
            const copied = await U.copy(suggested);
            toast.info('Suggested a password', {
                body: copied ? 'Copied to your clipboard.' : `Write it down: ${suggested}`,
            });
        });

        document.getElementById('pwSaveBtn').addEventListener('click', changePassword);

        const box = document.getElementById('twoFactorBox');
        box.checked = !!me.twoFactor;
        paintTwoFactorNote();
        box.addEventListener('change', async () => {
            me = await store.update('users', me.id, { twoFactor: box.checked });
            paintTwoFactorNote();
            paintAccess();
            toast.success(box.checked
                ? 'Two-factor authentication is on'
                : 'Two-factor authentication is off');
        });
    }

    function paintTwoFactorNote() {
        document.getElementById('twoFactorNote').innerHTML = me.twoFactor
            ? 'On. The code app is paired when the backend lands; nothing to do here yet.'
            : '<span class="text-bad">Off. Your password is the only thing between this account and the website.</span>';
    }

    async function changePassword() {
        const scope = document.getElementById('pwForm');
        const btn = document.getElementById('pwSaveBtn');

        const result = formLib.validate(scope, {});
        if (!result.ok) {
            formLib.reportInvalid(scope, result);
            return;
        }

        const data = formLib.collect(scope);
        const problem = session.passwordProblem(data.newPassword);

        if (problem) {
            formLib.setError(scope.querySelector('[name="newPassword"]'), problem);
            toast.error(problem);
            return;
        }
        if (data.newPassword === data.currentPassword) {
            const msg = 'The new password has to differ from the current one';
            formLib.setError(scope.querySelector('[name="newPassword"]'), msg);
            toast.error(msg);
            return;
        }
        if (data.newPassword !== data.confirmPassword) {
            const msg = 'The two passwords do not match';
            formLib.setError(scope.querySelector('[name="confirmPassword"]'), msg);
            toast.error(msg);
            return;
        }

        btn.classList.add('is-busy');
        try {
            const ok = await session.verifyPassword(data.currentPassword);
            if (!ok) {
                formLib.setError(scope.querySelector('[name="currentPassword"]'), 'That is not your current password');
                toast.error('Current password is wrong');
                return;
            }

            me = await session.changePassword(me.id, data.newPassword);

            /* Emptied by hand rather than through validate(), which would
               immediately mark all three as required again. */
            scope.querySelectorAll('input').forEach((input) => { input.value = ''; });
            document.getElementById('pwMeter').dataset.score = '0';
            scope.querySelectorAll('.field.is-invalid').forEach((field) => {
                field.classList.remove('is-invalid');
                const slot = field.querySelector('.field__error');
                if (slot) slot.innerHTML = '';
            });

            toast.success('Password changed', {
                body: 'Other devices are signed out. Use the new password next time you sign in.',
            });
            paintAccess();
            paintSessions();
        } finally {
            btn.classList.remove('is-busy');
        }
    }

    /* Phase 1 has no session table, so this is the one honest thing the panel
       knows: when this account was last seen, and on what. */
    function paintSessions() {
        document.getElementById('sessionList').innerHTML = `
            <div class="row gap-4" style="padding:9px 12px;border:1px solid var(--hairline);border-radius:var(--radius-sm);background:var(--surface-2)">
                <i class="fa-solid fa-laptop" style="color:var(--brand-red)"></i>
                <span class="grow">
                    <b>This browser</b>
                    <span class="cell-sub">Last active ${U.esc(me.lastActiveAt ? U.ago(me.lastActiveAt) : 'now')}</span>
                </span>
                <span class="tag ok">Current</span>
            </div>
            <button type="button" class="btn btn--ghost btn--sm mt-2" id="signOutAllBtn">
                <i class="fa-solid fa-right-from-bracket"></i> Sign out everywhere else</button>`;

        document.getElementById('signOutAllBtn').addEventListener('click', async () => {
            const ok = await window.TMH.confirm({
                title: 'Sign out every other device?',
                body: 'Anyone signed in as you elsewhere has to sign in again. This browser is unaffected.',
                confirmLabel: 'Sign out everywhere else',
                icon: 'fa-right-from-bracket',
            });
            if (!ok) return;
            toast.success('Other sessions ended', { body: 'Session records land with the backend.' });
        });
    }

    /* ---------------------------------------------------------
       Preferences
       --------------------------------------------------------- */
    function wirePreferences() {
        const scope = document.getElementById('prefForm');
        const btn = document.getElementById('prefSaveBtn');

        btn.addEventListener('click', async () => {
            btn.classList.add('is-busy');
            try {
                const data = formLib.collect(scope);
                me = await store.update('users', me.id, data);
                toast.success('Preferences saved');
            } finally {
                btn.classList.remove('is-busy');
            }
        });
    }

    /* ---------------------------------------------------------
       Rail
       --------------------------------------------------------- */
    function paintWho() {
        document.getElementById('whoCard').innerHTML = `
            <div style="text-align:center;padding:var(--s4) 0">
                ${me.avatar
                    ? `<img src="${U.esc(me.avatar)}" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto var(--s3)">`
                    : `<span style="width:96px;height:96px;border-radius:50%;background:var(--surface-3);display:grid;place-items:center;margin:0 auto var(--s3);font-weight:700;font-size:26px;color:var(--text-mid)">${U.esc(U.initials(me.name))}</span>`}
                <h4 style="font-family:var(--font-head)">${U.esc(me.name)}</h4>
                <p class="text-sm mid">${U.esc(me.email)}</p>
                <p class="mt-2">${session.isSuper(me)
                    ? `<span class="tag info"><i class="fa-solid fa-user-shield"></i> ${U.esc(session.roleName(me.roleId))}</span>`
                    : `<span class="pill">${U.esc(session.roleName(me.roleId))}</span>`}</p>
            </div>`;
    }

    function paintAccess() {
        const supers = session.superAdmins();

        document.getElementById('accessCard').innerHTML = `
            <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s3)">Access</h3>
            <dl class="kv">
                <dt>Status</dt><dd>${session.statusTag(me.status)}</dd>
                <dt>Password</dt><dd>${U.esc(me.passwordUpdatedAt ? `Changed ${U.ago(me.passwordUpdatedAt)}` : 'Never changed')}</dd>
                <dt>Two-factor</dt><dd>${me.twoFactor ? '<span class="tag ok">On</span>' : '<span class="tag off">Off</span>'}</dd>
                <dt>Last active</dt><dd>${U.esc(me.lastActiveAt ? U.ago(me.lastActiveAt) : '—')}</dd>
            </dl>
            ${session.isSuper(me) ? `
                <div class="card__foot">
                    <p class="text-sm mid">${supers.length === 1
                        ? 'You are the only active super admin. If you lose this account, nobody can manage the panel.'
                        : `${supers.length} super admins can manage the panel.`}</p>
                    <a class="btn btn--soft btn--sm mt-2" href="user-form?role=${U.esc(session.SUPER_ROLE)}">
                        <i class="fa-solid fa-user-plus"></i> Add another super admin</a>
                </div>` : `
                <div class="card__foot">
                    <p class="text-sm mid">Need more access? A super admin can change your role
                        under Users &amp; Roles.</p>
                </div>`}`;
    }
}());
