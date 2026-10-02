# Employee account deletion

Mobile: Settings → Delete account. The first confirmed request creates one ticket
for the authenticated employee, captures their identity for administrator review,
and fixes a deadline exactly 30 days after submission. Retries return the same
ticket and deadline. Reopening the page shows the status card instead of a new form.
The account remains usable until deletion. There is no rejection or cancellation
workflow; unapproved requests expire automatically.

Mobile admin: Profile → Account deletion requests. Open a ticket and type exactly
`delete account` to enable **Delete account**. The server validates the phrase,
admin role and organisation independently of the mobile UI.

Deletion runs in one transaction: retain the user ID, change the current name to
`Unknown`, set `account_status = deleted` and `active = 0`, replace the email and
password, clear current contact/profile images, revoke refresh and push tokens,
and close open sessions/stops/visits. Attendance hours are updated without
rebuilding historic routes or stops. Historical reports, sales, attendance and
routes retain their employee references. The ticket and a unique deletion event
remain restricted to administrators. This is account anonymisation and access
removal; it is not erasure of all personal information from historical records.

Login, token refresh and authenticated API access enforce deleted status and the
deadline. Admin employee edits/password resets cannot reactivate deleted users.
On receiving `ACCOUNT_DELETED`, the mobile app clears tokens and queued GPS fixes,
stops tracking and returns to sign-in. An offline phone observes server-side
deletion when it reconnects; remote deletion cannot directly stop an offline device.

## Deploy

Deploy the PHP changes before releasing the mobile app. From the website folder:

```sh
php vayu migrate
php vayu accounts:delete-due --dry
```

Install a scheduler that runs every minute (replace the absolute website path):

```cron
* * * * * cd /absolute/path/to/website && php vayu accounts:delete-due >> storage/logs/accounts-delete.log 2>&1
```

This job is required for unattended deletion even when employees never sign in
again. Monitor failures and ensure it is enabled. The command is idempotent and
safe to rerun. A late job records the fixed deadline as the effective deletion
time. Authentication also enforces due requests if the scheduler is temporarily
unavailable. No migration or scheduler has been applied to production by this
implementation task.

The website serves `privacy-policy` and `terms-and-conditions`. Mobile external
links use `WEBSITE_BASE_URL` (default `https://hazraelectricalbike.com`); override it
for staging/local builds. Product links use the public slug, falling back to ID.
Product image paths resolve against `API_BASE_URL`'s origin, use the selected
colour's real gallery, then the hero photo, and show an honest placeholder when
no photo exists.

## Verification

```sh
php tests/account-deletion.php
flutter test test/account_deletion_test.dart
```

The PHP test uses an in-memory SQLite database and checks additive migration,
deadline stability, role/org isolation, idempotency, token revocation, anonymisation,
session closure, attendance rollup and automatic expiry. Existing app data is not
used. Deployments using MySQL should also validate migration and lifecycle on their
staging database before release.
