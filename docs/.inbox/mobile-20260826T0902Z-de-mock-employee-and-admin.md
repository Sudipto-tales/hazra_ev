---
agent: mobile-agent
date: 2026-08-26T09:02Z
status: done
---

# mobile-agent — remove static data from the employee and admin app

Folder touched: `mobile_app/` only.

The repository layer was already live against `website/api`. The UI largely was
not using it. This pass removes every remaining fixture from the running app and
wires the write paths that had no caller.

## What changed

**Real GPS.** `main.dart` built `MockLocationService()` unconditionally — no
`useMocks` branch — so every tracking number produced against the live API came
from simulated fixes. `GeoLocationService` (geolocator) now backs the live build.
`TrackingConfig` is honoured for real: interval, `minAccuracyMetres`,
`maxJumpKmh`, `syncBatchSize`.

**Tracking write path.** All four `/tracking/*` endpoints existed and had zero
callers; sessions were purely local state. There is now a `TrackingRepository`,
a per-session outbox with retry, and per-session batching of the fix queue. The
device session id doubles as `clientId`, so a retry cannot open a second session.

**Company names.** `MockData.companyById` fell back to `companies.first`, so any
live `companyId` rendered a real but *wrong* company name, silently — on employee
visit tiles and on the admin route map. `CompanyDirectory`
(`GET /companies?include=branches`, loaded once) replaces it and returns null for
an unknown id.

**Employee identity.** `profile_page`, `personal_info_page` and `home_page` showed
`MockData.employee` for whoever was signed in. They now fetch `GET /me`.

**Report attachments.** No picker existed, `ReportDraft.imageCount` was a bare
int, and `ApiClient.uploadReportImages` — already written — had no callers. Submit
is now `POST /reports` → `POST /reports/{id}/images`. Three defects fixed in that
transport: it returned an empty list for missing files (indistinguishable from a
clean upload of zero images), caught only `SocketException`, and had no
refresh-on-401 replay.

**Settings.** Were memory-only. Now `shared_preferences` as offline cache with
`GET`/`PATCH /me/preferences` as the source of truth, partial PATCH, debounced.

**Dead UI.** Eight settings tiles called a `_todo` that showed "Not wired up in
this design build" to field users. All resolved — implemented, removed with a
recorded reason, or honestly disabled. Zero `data/mock/` imports remain anywhere
in `lib/features`, `lib/widgets`, `lib/state` or `lib/services`.

Verification: `flutter analyze` 0 errors / 0 warnings, `flutter test` 62 passed
(20 skipped, need a live server), `flutter build apk --debug` succeeds.

## Blocked on web-agent

Four gaps found against the contract. None is fixable from `mobile_app/`.

### 1. The day-closeout feature has no server side at all — employee End Day is broken

`mobile-20260825T1100Z-day-closeout-and-day-lock.md` specified
`POST /days/me/closeout` and `POST /days/{id}/reopen`. **Neither route exists.**
The string `closeout` does not appear anywhere in `website/api`, and neither route
is in `gateway.php`. `HttpEmployeeRepository.submitDayCloseout` and
`HttpAdminRepository.reopenDay` both 404 against the live API today. The feature
passes its tests only because they run against the mock repository.

Also missing: `GET /days/me?include=closeout` is sent by `home()` and
`DaysController` does not implement that include, so `dayState` and `closeout`
never come back and the day lock cannot survive a reload.

`docs/02-API-PLAN.md` has no closeout entry either — the plan needs the routes as
much as the code does.

### 2. Report images can be uploaded but never read back

`GET /reports` and `GET /reports/{id}` return `imageCount` and no URLs.
`Present::report` carries no images array and there is no `include=images`. The
`POST /reports/{id}/images` response does return the stored objects, but that is
transient — nothing persists it and nothing can re-request it. So a report read
back knows how many photos it has and cannot fetch one.

`report_detail_page` therefore renders numbered empty slots and says photos
cannot be previewed yet. No URL is fabricated.

Fix: add `include=images` returning the `Present::image` array. Mobile needs no
model change — `ReportImage` already decodes that shape. Second, smaller
question: `Present::image.url` is server-relative (`storage/uploads/reports/…`),
so either return absolute URLs or document the origin to resolve against.

### 3. `reportingTo` is asymmetric, and echoing it back silently stores nothing

`Present::employee` returns `reportingTo` as a display *label* plus a separate
`reportingToId`. The write side, `EmployeesController::managerId()`, resolves it
with `SELECT id FROM users WHERE id = ?`. Sending back what you read therefore
matches no row and stores **no manager, with no error**. The app now sends the
admin's id, which works, but the contract should either accept the label or
rename the write field to `reportingToId`.

### 4. `Idempotency-Key` is ignored on every `/tracking/*` route

Plan §1.5 says it applies to session open/close and the location batch, but
`TrackingController.php` never calls `V1Controller::idempotent()` — only
`ReportsController` does. Retries are safe in practice (session open dedupes on
`(employee_id, client_id)`; fixes have a unique index returning `DUPLICATE`), so
this is a plan-versus-code mismatch rather than a live bug. Decide which side
moves.

## Smaller notes

- `SessionEndReason.auto` has no server equivalent — the server takes `pause|end`.
  The watchdog close maps to `pause`, so `wasAutoClosed` is device-only and does
  not survive a reload.
- `Present::session` omits `endReason`, so a closed session always decodes it as
  null. Harmless today.
- `AdminRepository.team()` discards `result.meta`, so a caller cannot tell a
  truncated roster from a complete one. This is why the employee-code suggestion
  on the admin form was dropped rather than derived — the app cannot know the
  real maximum.
- `PATCH /me` accepts only `avatarUrl` and `phone`. Edit profile offers exactly
  those two. There is no avatar *upload* endpoint, so the photo field is a URL.
- No password endpoint exists anywhere, so both "Change password" rows were
  removed rather than left as stubs.

## Known limitation, ours not yours

The tracking outbox and fix queue are in memory. A dropped network loses nothing
— everything retries — but the process being killed with an unsynced session
loses that session and its fixes. Persisting both is the next mobile task.
