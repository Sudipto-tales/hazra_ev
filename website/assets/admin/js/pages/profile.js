/* =========================================================
   My Profile — the signed-in user's own account.

   Allows updating basic profile details and changing the
   admin password via the HAZRA CMS backend API.
   ========================================================= */
(function () {
    'use strict';

    const {
        util: U, store, fields: F, form: formLib, layout, toast, media, session,
    } = window.HAZRA;

    let me = null;
    let ctrl = null;

    window.HAZRA.boot(init);

    async function init() {
        me = await session.current();

        if (!me) {
            document.getElementById('view').innerHTML = `
                <article class="card"><div class="empty">
                    <div class="empty__art"><i class="fa-solid fa-user-slash"></i></div>
                    <h3>Session Expired</h3>
                    <p>Please sign in again to access your account settings.</p>
                    <a class="btn btn--primary mt-3" href="/admin/login"><i class="fa-solid fa-right-to-bracket"></i> Sign in</a>
                </div></article>`;
            return;
        }

        document.getElementById('pageHead').innerHTML = layout.pageHead({
            crumb: [{ label: 'System' }, { label: 'My Profile' }],
            title: 'My',
            accent: 'Profile',
            sub: 'Manage your administrator account details and security credentials.',
        });

        document.getElementById('view').innerHTML = `
            <div class="split">
                <div>
                    <article class="card card--flush anim-item">
                        <div class="tabs" role="tablist" aria-label="Profile sections">
                            <button type="button" role="tab" data-tab="account" aria-selected="true">
                                <i class="fa-solid fa-id-card"></i> Account</button>
                            <button type="button" role="tab" data-tab="security" aria-selected="false">
                                <i class="fa-solid fa-key"></i> Sign-in & security</button>
                        </div>

                        <div class="tab-panel profile-panel p-4" id="account" role="tabpanel">
                            <form id="accountForm" novalidate>
                                ${F.section({
                                    title: 'Your details', icon: 'fa-id-card',
                                    sub: 'Your admin profile information.',
                                    fields: [
                                        F.text({ name: 'name', label: 'Full name', required: true }),
                                        F.email({
                                            name: 'email', label: 'Email', required: true,
                                            hint: 'Sign-in address for the admin panel.',
                                        }),
                                        F.text({ name: 'phone', label: 'Phone', placeholder: '+91 98000 00000' }),
                                    ],
                                })}
                                ${F.bar({ singleSave: true, saveLabel: 'Save profile', noCancel: true })}
                            </form>
                        </div>

                        <div class="tab-panel profile-panel p-4" id="security" role="tabpanel" hidden>
                            <form id="pwForm" novalidate>
                                ${F.section({
                                    title: 'Change your password', icon: 'fa-key',
                                    sub: 'Enter your current password to update your sign-in credentials.',
                                    fields: [
                                        F.text({
                                            name: 'currentPassword', type: 'password', label: 'Current password',
                                            required: true, wide: true,
                                        }),
                                        F.text({
                                            name: 'newPassword', type: 'password', label: 'New password',
                                            required: true, wide: true,
                                            hint: `At least 6 characters.
                                                <span class="pw-meter" id="pwMeter" data-score="0" aria-hidden="true">
                                                    <span></span><span></span><span></span><span></span></span>`,
                                        }),
                                        F.text({
                                            name: 'confirmPassword', type: 'password', label: 'Repeat new password',
                                            required: true, wide: true,
                                        }),
                                    ],
                                })}
                                <div class="row gap-2 mt-3">
                                    <button type="button" class="btn btn--primary" id="pwSaveBtn">
                                        <i class="fa-solid fa-key"></i> Change password</button>
                                    <button type="button" class="btn btn--ghost" id="pwSuggestBtn">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i> Suggest one</button>
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

        U.wireTabs(document.getElementById('view'));
        wireSecurity();

        paintWho();
        paintAccess();
    }

    /* ---------------------------------------------------------
       Account
       --------------------------------------------------------- */
    async function saveAccount(data) {
        if (store && store.update && store.available && store.available('users')) {
            me = await store.update('users', me.id, data);
        } else {
            Object.assign(me, data);
        }
        toast.success('Profile updated', { body: 'Your account details have been saved.' });
        paintWho();
    }

    /* ---------------------------------------------------------
       Security
       --------------------------------------------------------- */
    function wireSecurity() {
        const scope = document.getElementById('pwForm');
        if (!scope) return;
        const newPw = scope.querySelector('[name="newPassword"]');
        const meter = document.getElementById('pwMeter');

        if (newPw && meter) {
            newPw.addEventListener('input', () => {
                meter.dataset.score = String(session.strength(newPw.value));
            });
        }

        const suggestBtn = document.getElementById('pwSuggestBtn');
        if (suggestBtn) {
            suggestBtn.addEventListener('click', async () => {
                const suggested = session.suggest();
                if (newPw) newPw.value = suggested;
                const confirmPw = scope.querySelector('[name="confirmPassword"]');
                if (confirmPw) confirmPw.value = suggested;
                if (meter) meter.dataset.score = String(session.strength(suggested));
                const copied = await U.copy(suggested);
                toast.info('Suggested a password', {
                    body: copied ? 'Copied to your clipboard.' : `Write it down: ${suggested}`,
                });
            });
        }

        const saveBtn = document.getElementById('pwSaveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', changePassword);
        }
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
        if (data.newPassword === data.currentPassword) {
            const msg = 'The new password must differ from your current one';
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
            await session.changePassword(me.id, data.newPassword, data.currentPassword);

            scope.querySelectorAll('input').forEach((input) => { input.value = ''; });
            const meter = document.getElementById('pwMeter');
            if (meter) meter.dataset.score = '0';
            scope.querySelectorAll('.field.is-invalid').forEach((field) => {
                field.classList.remove('is-invalid');
                const slot = field.querySelector('.field__error');
                if (slot) slot.innerHTML = '';
            });

            toast.success('Password updated successfully');
        } catch (err) {
            toast.error(err.message || 'Failed to change password');
        } finally {
            btn.classList.remove('is-busy');
        }
    }

    /* ---------------------------------------------------------
       Rail
       --------------------------------------------------------- */
    function paintWho() {
        const whoCard = document.getElementById('whoCard');
        if (!whoCard) return;
        const name = me.name || 'Admin User';
        const email = me.email || 'admin@hazraev.com';
        const role = me.role || 'Administrator';

        whoCard.innerHTML = `
            <div style="text-align:center;padding:var(--s4) 0">
                ${me.avatar
                    ? `<img src="${U.esc(me.avatar)}" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto var(--s3)">`
                    : `<span style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg, var(--hazra-purple), var(--hazra-purple-dark));display:grid;place-items:center;margin:0 auto var(--s3);font-weight:700;font-size:24px;color:#fff">${U.esc(U.initials(name))}</span>`}
                <h4 style="font-family:var(--font-head);font-size:1.1rem;margin-bottom:4px;">${U.esc(name)}</h4>
                <p class="text-sm mid">${U.esc(email)}</p>
                <p class="mt-2"><span class="tag info"><i class="fa-solid fa-user-shield"></i> ${U.esc(role)}</span></p>
            </div>`;
    }

    function paintAccess() {
        const accessCard = document.getElementById('accessCard');
        if (!accessCard) return;
        accessCard.innerHTML = `
            <h3 style="font-size:var(--fs-h3);margin-bottom:var(--s3)">Account Security</h3>
            <dl class="kv">
                <dt>Status</dt><dd><span class="tag ok">Active</span></dd>
                <dt>Role</dt><dd>${U.esc(me.role || 'Admin')}</dd>
                <dt>System</dt><dd>Hazra EV CMS</dd>
            </dl>`;
    }
}());
