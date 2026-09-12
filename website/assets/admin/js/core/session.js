/* =========================================================
   The signed-in user.

   Was a constant pointing at a row in assets/data/system.js,
   because there was no auth backend to ask. Now it is whatever
   GET /api/auth/me answered at boot — api.js holds that reply
   and this reads it back, so "who am I" is still a synchronous
   question and the shell can paint a name before it awaits
   anything.

   Everything that asks "may this person manage the panel" goes
   through here, so the super-admin rules live in one file
   rather than being re-derived on three screens.
   ========================================================= */
(function (root) {
    'use strict';

    const SUPER_ROLE = 'role-super';

    /**
     * A user's status, in the words the database uses.
     *
     * The prototype filed users under the content vocabulary — published,
     * draft, hidden — with a comment translating them, because its list
     * component only knew those three. The column has always held `active`,
     * `invited` and `suspended`, which is what the API returns and what the
     * three screens that read it now compare against. Saying "draft" about a
     * colleague was never going to survive contact with a real table.
     */
    const USER_STATUS = {
        active: { tone: 'ok', label: 'Active' },
        invited: { tone: 'warn', label: 'Invited' },
        suspended: { tone: 'off', label: 'Suspended' },
    };

    const store = () => root.TMH && root.TMH.store;
    const identity = () => (root.TMH.api ? root.TMH.api.me() : null) || {};

    const session = {

        SUPER_ROLE,
        USER_STATUS,

        /* A getter, not a constant: the panel no longer decides who is signed
           in, and the answer does not exist until the boot request returns. */
        get CURRENT_ID() {
            return (identity().user || {}).id || null;
        },

        /**
         * Synchronous, because the shell paints the name and initials before
         * it has awaited anything. This is the /api/auth/me payload, which
         * carries everything the chrome shows; `current()` is the full record.
         */
        currentSync() {
            return identity().user || null;
        },

        /** What this account is allowed to do, as the server described it. */
        permissions() {
            return identity().permissions || {};
        },

        current() {
            const id = session.CURRENT_ID;
            return id ? store().get('users', id) : Promise.resolve(null);
        },

        isSuper(user) {
            return !!user && user.roleId === SUPER_ROLE;
        },

        /* Only active super admins count — a suspended or still-invited one
           cannot sign in, so they cannot be the last line of access. */
        superAdmins() {
            const s = store();
            if (!s || !s.available('users')) return [];
            return (s.allSync('users') || [])
                .filter((u) => u.roleId === SUPER_ROLE && u.status === 'active');
        },

        /* True when demoting, suspending or deleting this user would leave
           nobody able to reach Users, Settings or the permission matrix —
           i.e. would lock the hospital out of its own panel. */
        isLastSuper(user) {
            if (!session.isSuper(user) || user.status !== 'active') return false;
            return session.superAdmins().length <= 1;
        },

        roleName(roleId) {
            const s = store();
            const role = s && (s.allSync('roles') || []).find((r) => r.id === roleId);
            return role ? role.name : (roleId || '—');
        },

        statusTag(status) {
            const meta = USER_STATUS[status] || { tone: 'off', label: status || 'Unknown' };
            const esc = root.TMH.util.esc;
            return `<span class="tag ${meta.tone}">${esc(meta.label)}</span>`;
        },

        /* ---------- passwords ----------
           Nothing here hashes anything. The rules below are what the form
           refuses to submit; the server applies its own and does the hashing,
           and a password only ever leaves this file inside a request body. */

        /** '' when the password is acceptable, otherwise the reason. */
        passwordProblem(pw) {
            const v = String(pw || '');
            if (v.length < 10) return 'Use at least 10 characters';
            if (!/[a-z]/i.test(v)) return 'Include at least one letter';
            if (!/\d/.test(v)) return 'Include at least one number';
            return '';
        },

        /** 0–4, for the strength bar. */
        strength(pw) {
            const v = String(pw || '');
            if (!v) return 0;
            let score = 0;
            if (v.length >= 10) score += 1;
            if (v.length >= 16) score += 1;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score += 1;
            if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) score += 1;
            return Math.min(4, score);
        },

        /* A readable temporary password for an invite — no l/1/O/0, because
           it gets read out over the phone. */
        suggest() {
            const words = ['harbour', 'lantern', 'meadow', 'compass', 'thistle', 'granite', 'willow', 'saffron'];
            const pick = () => words[Math.floor(Math.random() * words.length)];
            const digits = String(23 + Math.floor(Math.random() * 76));
            return `${pick()}-${pick()}-${digits}`;
        },

        /**
         * "Confirm your current password", before changing it.
         *
         * POST /api/auth/verify-password answers 200 with {ok} either way — a
         * wrong password is an answer, not a failed request, and a 401 here
         * would send somebody to the sign-in screen for a typo.
         */
        async verifyPassword(plain) {
            const res = await root.TMH.api.post('api/auth/verify-password', { password: String(plain || '') });
            return !!(res && res.data && res.data.ok);
        },

        /**
         * The password is a write-only field on the user record, so this is an
         * ordinary PATCH. `passwordUpdatedAt` and `mustChangePassword` were
         * the mock's own bookkeeping — the server stamps `updatedAt` and there
         * is no forced-change flow to raise.
         */
        async changePassword(userId, plain) {
            const problem = session.passwordProblem(plain);

            if (problem) {
                const err = new Error(problem);
                err.fields = { newPassword: problem };
                throw err;
            }

            return store().update('users', userId, { password: plain });
        },
    };

    root.TMH = root.TMH || {};
    root.TMH.session = session;
}(window));
