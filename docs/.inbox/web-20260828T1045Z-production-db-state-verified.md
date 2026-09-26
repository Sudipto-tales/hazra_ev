---
agent: web-agent
date: 2026-08-28T10:45Z
status: done — no change made
handoff-to: user (rotate the production admin password)
---

# Production database — asked to wipe and seed; it was already in that exact state

Task as given: clean the production database and seed the admin. **Nothing was
wiped.** An inventory taken first showed the database already holds exactly what
a clean install plus the bootstrap seed produces, so dropping it would have
destroyed a working install to rebuild the identical thing.

Target read from `website/.env` — `APP_ENV=production`, MariaDB 11.8.8 at
`193.203.184.191:3306`, schema `u402700136_hazra_new`.

## What is actually in there

| Table | Rows |
| --- | --- |
| `users` | 1 — `admin@hazraev.com`, role `admin`, active |
| `organizations` | 1 — "Hazra EV", created 2026-08-25 |
| `tracking_configs` | 1 |
| `admin_profiles` | 1 — `ADM-001` |
| `user_preferences` | 1 |
| `employee_profiles`, `companies`, `products`, `work_sessions`, `location_fixes`, `visit_reports`, `attendance_days`, `refresh_tokens`, and every other table | 0 |

`migrations` holds all 10 rows, matching `database/migrations/` exactly —
`001_core_identity_tables` through `010_password_credentials`, so the deployed
schema is current with main. `seeders` holds `001_bootstrap_admin`, batch
1787655951.

That is `001_bootstrap_admin.php`'s output and nothing else: the org, its
tracking rulebook, one admin. No demo rows reached the server — the
`--demo` guard in `MigrateCommand.php` (refuses when `APP_ENV=production`) held.

## End-to-end verified

`POST https://hazraelectricalbike.com/api/v1/auth/login` with the seeded
credentials returns **200** and a full principal:

```json
{"principal":{"type":"admin","adminCode":"ADM-001","name":"Admin",
 "email":"admin@hazraev.com","role":"Administrator","mustChangePassword":false}}
```

So routing, PHP 8.3.31, MySQL, bcrypt verification and JWT issue all work on the
live host. The refresh token that login minted was deleted immediately
afterwards; `refresh_tokens` is back to 0.

## Security — the admin password is `admin123` on a live server

`001_bootstrap_admin.php` seeds `password_hash('admin123', PASSWORD_BCRYPT)` and
its own comment calls it "a first-login credential, not a secret". It is now
sitting on a public production host, and `must_change_password` is **0**, so
nothing forces a change at login. Anyone who reads the repo can sign in as
administrator.

Two fixes, both worth doing:

1. Rotate the password now, through `POST /api/v1/me/password` or the admin UI.
2. Change the seeder to set `must_change_password = 1` so a fresh install
   cannot repeat this. That is a code change in `website/` and was not made
   here.
