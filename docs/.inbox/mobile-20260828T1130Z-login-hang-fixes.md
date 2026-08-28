---
agent: mobile-agent
date: 2026-08-28T11:30Z
status: done
handoff-to: web-agent (six more SQLite-dialect statements in `website/api/`), user (upload the PHP fix to Hostinger)
---

# mobile-agent — admin login spun forever; server 500 was being swallowed

Reported symptom: signing in as `admin@hazraev.com` on the production build left
the "Enter admin console" button spinning with no error, forever.

## Chain

1. The app sends `device: {platform, appVersion}` with `POST /auth/login`.
2. `AuthController::registerDevice()` runs
   `... AND (id = ? OR push_token IS ?)`. `IS ?` parses on SQLite and is a
   **syntax error on MySQL** (`IS` takes only NULL/TRUE/FALSE), so the request
   500s. Verified against production:
   `ERROR 1064 (42000) ... near '?)'`.
   Reproduced over HTTP — the payload is what decides it:

   | body | status |
   | --- | --- |
   | `{email, password}` | 200 |
   | `+ "device": {}` | 200 |
   | `+ "device": {"appVersion":"0.1.0"}` | 200 |
   | `+ "device": {"platform":"android"}` | **500** |

3. That 500 comes back with `content-length: 0`. `ApiClient._decode` had

   ```dart
   if (response.statusCode == 204 || response.body.isEmpty) {
     return const ApiResult(null, <String, dynamic>{});
   }
   ```

   so an empty **error** body was read as success and `data` came back null.
4. `ApiResult.map` does `data as Map` → **TypeError**, which is an `Error`, not
   an `Exception`.
5. `_signIn` caught only `on ApiException`, so nothing matched, `_busy` stayed
   `true`, and the spinner ran forever with no message.

Server bug caused it; client bug hid it.

## Fixed in `mobile_app/`

**`lib/data/api/api_client.dart`** — an empty body is success only below 400;
an empty body on a 4xx/5xx now throws `ApiException(code: 'HTTP_<status>')`.

**`lib/features/auth/admin_login_page.dart`** and
**`lib/features/auth/login_page.dart`** — both sign-in handlers gained a
trailing `catch (e)` that clears `_busy` and shows the fault. The employee form
had the identical defect and would have hung the same way.

`flutter analyze` clean; `flutter test` 63 passed, 23 skipped.

## Rebuilt

```sh
flutter build apk --release --split-per-abi \
  --dart-define=API_BASE_URL=https://hazraelectricalbike.com/api/v1
flutter build appbundle --release --dart-define=API_BASE_URL=…
```

Signed with the upload key (`CN=Hazra EV, …`). Artifacts refreshed in
`mobile_app/build/app/outputs/` and `outputs/`.

**The client fixes do not make login work.** They turn a silent hang into a
readable "server returned 500" error. Login only succeeds once the PHP fix is
uploaded to Hostinger.

## The server-side fix is one line, but there are six more like it

`website/api/controllers/AuthController.php` was patched here as part of the
diagnosis (branch on `$pushToken === null` instead of `IS ?`). That file is
web-agent's — flagging rather than claiming it.

The MySQL port (2026-08-25) moved the schema but left SQLite-dialect DML across
the API. Every one of these is a guaranteed 500 on the live server; all verified
by `PREPARE` against the production MariaDB 11.8.8:

| File | Statement | MySQL form |
| --- | --- | --- |
| `api/support/V1Controller.php:178` | `INSERT OR REPLACE INTO idempotency_keys` | `REPLACE INTO` (works on both engines) |
| `api/support/Engine.php:833` | `INSERT OR IGNORE INTO notification_recipients` | `INSERT IGNORE INTO` |
| `api/controllers/TrackingController.php:183` | `ON CONFLICT (employee_id) DO UPDATE` | `ON DUPLICATE KEY UPDATE` |
| `api/support/Engine.php:410` | `ON CONFLICT (employee_id, work_date) DO UPDATE` | same |
| `api/support/Engine.php:513` | `ON CONFLICT (employee_id, work_date) DO UPDATE` | same |
| `api/support/MapMatch.php:340` | `ON CONFLICT (employee_id, work_date) DO UPDATE` | same |
| `api/controllers/ReportsController.php:243` | `ON CONFLICT (report_id) DO UPDATE` | same |

`REPLACE INTO` and `INSERT IGNORE` were both confirmed to parse on MariaDB, and
`REPLACE INTO` was confirmed to upsert correctly on SQLite too, so that one stays
portable.

The idempotency one is the worst of the set: `idempotent()` runs the handler
**before** the insert, so on MySQL a visit report is created, then the request
500s, and the app retries — producing duplicate reports rather than preventing
them. It covers `POST /reports` and `POST /days/{subject}/closeout`.

Between them these cover device health, day rollups, road matching, report
review and notification fan-out — most of the employee flow.
