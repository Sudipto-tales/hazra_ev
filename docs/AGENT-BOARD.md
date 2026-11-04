# Agent board

The single shared surface for four agents working in parallel on this repo. Read this
first, every session. `docs-agent` is the only writer — everyone else reports through
`docs/.inbox/`.

## Ownership map

| Agent | Writes | Reads | Never touches |
| --- | --- | --- | --- |
| `web-agent` | `website/` — PHP app, `website/api/` (serves web **and** mobile), core, config, database | `mobile_app/`, `docs/`, `html/` | `mobile_app/`, `html/`, `website/assets/` |
| `mobile-agent` | `mobile_app/` — Flutter lib, test, android, pubspec | `website/api/`, `docs/` | `website/`, `html/` |
| `design-agent` | `html/`, `website/assets/{css,js,image}` | everything | PHP logic, Dart, `docs/` |
| `docs-agent` | `docs/` only | everything | all code |

No two agents share a writable path. That is what makes parallel runs safe.

The one seam that needs care: `website/api/` is written by `web-agent` and consumed by
`mobile-agent`. It is a contract, tracked under **Contract status** below.

## How reporting works

1. An agent finishes a task → writes ONE file to `docs/.inbox/<agent>-<timestamp>-<slug>.md`.
2. One file per task means concurrent agents never write the same file.
3. `docs-agent` drains the inbox, verifies each claim against the code, merges here,
   deletes the entry.

Nobody but `docs-agent` edits this file.

---

## 1. Status

| Agent | Folder | Current work | Last update |
| --- | --- | --- | --- |
| web-agent | `website/` | Full `/api/v1` built and deployed on MySQL (Hostinger). **Latest 2026-11-04: `TrackingConfig` v9 server half BUILT, NOT DEPLOYED** — retargeted migration (renamed `031_tracking_config_v8.php` → `031_tracking_config_v9.php`, because the ledger keys on filename and the v8 name is already applied in prod), four config columns + CHECKs + history mirror, `location_fixes.heading_deg` (`-1`/`NaN` → NULL, `0` preserved as due north), `tracking_watches`, `POST /tracking/watch`, `liveUntil` in `meta`, `Engine::movement()` dead band (read-time walk-back over ≤20 fixes, no stored state) and `Engine::activity()` per §3.14. `tests/config-v9.php` + `tests/tracking-live.php` added (`config-v8.php` removed); all 13 suites green, `php -l` clean. | 2026-11-04 |
| mobile-agent | `mobile_app/` | De-mocked employee+admin app on the live API; P0.1 durable queue, P1.1 ticker decouple, P1.2 server-driven config, adaptive GPS cadence + heartbeat. **2026-11-04 (a): live values + status-bar icon** — movement published from the one debounced source instead of hard-coded `stationary`, local `distanceKm` accumulation, 15s lifecycle-paused admin poll, bolt-only `ic_stat_hazra.xml` on both `startService`/`updateService`. **2026-11-04 (b): live team map** — new `live_team_map_page.dart` + `live_marker.dart`/`live_pin.dart`/`map_chrome.dart`; colour=state, glyph=`activity`, notch-only rotation never built on a null heading, dwell ring, 30-min fading trails, grid clustering < z12, stale markers greyed with `Fmt.freshness` per second via the AutoRefresh clock (P1.1 intact); watch loop at ttl/2; `_drain` swaps on `meta.liveUntil`, GPS capture untouched. Basemap default moved OSM → CARTO Positron/Dark Matter. `flutter test` 160 passed / 23 skipped; `flutter analyze` 15 issues = baseline. | 2026-11-04 |
| design-agent | `html/`, `website/assets/` | Built out the full `html/` prototype: hero scroll-zoom, about/collection/features sections, 7-item navbar, ~18 content/product/dealer/service pages, a design-token layer (`html/styles/`), self-hosted fonts and a full SEO pass. `html/` is NOT empty (the old baseline line was wrong). | 2026-09-01 (last report) |
| docs-agent | `docs/` | **Contract-first spec for live tracking on the admin map — `TrackingConfig` v9, locked 2026-11-04.** `02` §3.11 (fix `heading`, `POST /tracking/watch`, `liveUntil` in `meta`), §3.12 (v9 = the four fields, movement dead band, per-rule clamp/422, version skew both ways), §3.14 (derived `activity` enum); `03` §4 (four config columns + CHECKs), §4a (`tracking_watches`), §5 (`location_fixes.heading_deg`, NULL≠0), §2 (activity is derived, never a column), §12/§14 (index + one atomic migration). v8 is **folded into v9** — migration 031 was written but never deployed, so production goes 7→9 without ever serving 8. Inbox: the 2026-11-04 mobile report and the two v8 reports are merged below; **six older entries (4 design manual/mockups, 1 web admin-password, 1 Aug-31 18-pages) are still undrained** and need a proper verification pass. | 2026-11-04 |

## 2. Contract status

`docs/02-API-PLAN.md` is authoritative. This table tracks drift between what the API
actually serves and what the app codes against.

| Area | Built by web-agent | Consumed by mobile-agent | Notes |
| --- | --- | --- | --- |
| Core `/api/v1` route table | **Built 2026-08-19**: 37 routes / 15 controllers, `api/support/` layer (`Engine`, `Present`, `Wire`, `Ctx`, `Geo`, `Envelope`), seeded. `UserController` is deliberately unrouted scaffold. | De-mocked 2026-08-26: HTTP repositories replace `lib/data/mock/`; real `GeoLocationService` backs live builds | 8 contract deviations from the original plan folded into `02` (see Change log 2026-08-19); board no longer says "route table unbuilt" |
| MySQL port | **Done 2026-08-25**: `config/dialect.php` rewrites upserts/`IS ?`; migrations retyped to dialect tokens; `php vayu db:sync`. Deployed to production MariaDB 11.8.8. | n/a (storage only, contract unchanged) | `03` updated with the MySQL port notes (§5/§14) |
| `GET /routes` road matching | **Done 2026-08-21**: `matched` polyline6 object + `snap=` param, cached per employee-day in `route_geometry` | Consumes server geometry; `OsrmService` is now the fallback (today's open route / `MATCH_ENABLED=false`) | `02` §3.7 updated. Seeder still draws straight-line traces nothing can match — dev demo shows raw trace |
| Day closeout / reopen | **Done 2026-08-27**: `POST /days/{subject}/closeout`, `POST /days/{id}/reopen`, `?include=closeout`, `dayState` always present; `php vayu days:close` midnight auto-close | `HttpEmployeeRepository.submitDayCloseout` / `HttpAdminRepository.reopenDay` live | `02` §3.4 + `03` §8 updated. `days:close` cron NOT installed on prod → `closedBySystem` never emitted yet |
| Passwords | **Done 2026-08-27**: `POST /me/password`, `POST /employees/{id}/password`, generated `temporaryPassword`, `principal.mustChangePassword` | create form, reset, change-password page all wired | No email forgot-password flow exists; admin reset is the only recovery. `02` §3.1/§3.2/§3.3 updated |
| `GET /config` (server-driven thresholds) | **Built all along** — `ConfigController@show`, employee-readable, ETag/304 (`GET:api/v1/config` in gateway). The perf plan's "`GET /api/config` not built" was wrong — it exists at `/api/v1/config` since the 2026-08-19 buildout | **Wired 2026-10-31**: `EmployeeRepository.config()` + `HttpEmployeeRepository`, `TrackingController.applyConfig()`/`_refreshConfig()` at login/resume/session-start; `autoCloseAfterMinutes` falls to the default (server omits it) | P1.2 done; no web-agent change needed |
| `TrackingConfig` **v9** — four config fields + `location_fixes.heading_deg` + derived `activity` + `POST /tracking/watch` / `liveUntil` | **BUILT 2026-11-04, NOT DEPLOYED.** Ships as `031_tracking_config_v9.php` under a NEW filename on purpose: production serves **8** (established from deploy history — `origin/website@f7661de` carried migration 031 and `deploy-server.yml` ran 49 s later), the ledger keys on filename, so reusing the v8 name would have been silently skipped and the new columns would never land. Version floor handles 7→9 and 8→9 identically. | **BUILT 2026-11-04.** Consumes `activity`, `headingDeg`, the watch call and `meta.liveUntil`; falls back cleanly on a v7/v8 server (neutral glyph, no notch, 300 s drain, watch 404 asked once). `movementExitSpeedKmh` is parsed and displayed but deliberately NOT consumed device-side — it is the server's dead band and the map reads `activity`, so nothing is lost | **Both halves built, neither deployed.** Deploy order matters: server first, then the app build that depends on it. `sync.sh` commits the WHOLE tree in one all-or-nothing commit, so both halves go together — confirm `GET /api/v1/config` → `version` before and after |
| auth refresh / session resume | 1h access + 30d rotating refresh token; `TokenStore` persists | `mobile_app/lib/app.dart` resumes session on launch, rotates on first call | No contract change |
| `POST /tracking/locations` reason enum | shipped 2026-10-31: `ACCURACY` removed; reasons now `JUMP\|DUPLICATE\|MALFORMED`; low-accuracy stored, derived at read time; `{accepted,rejected}` shape unchanged (`Engine::ingest`) | P0.2 device half done: uploads low-accuracy fixes flagged, no longer treats them as drop-settled | BEHAVIOUR change, wire shape unchanged; `02` §3.11 + `03` §5 match |

**Known contract gaps still open (reported 2026-08-26, re-verified in code 2026-11-01):**
- Report images upload but cannot be read back — the read path (`ReportsController.php:416`) selects only `COUNT(*)` → `imageCount`; no `include=images` returning the `Present::image` URL array. `report_detail_page` renders empty slots. (web-agent)
- `reportingTo` is a label on read, an id on write (`Present.php:32-33` `reportingTo`/`reportingToId`); echoing read→write stores no manager silently. App sends the admin id as a workaround. (web-agent)
- `Idempotency-Key` ignored on every `/tracking/*` route — `TrackingController` never calls `idempotent()` (plan §1.5 says it applies). Retries are safe in practice via unique keys — plan-vs-code mismatch, decide which side moves. (web-agent + mobile-agent)

The three standing rules (from `docs/README.md`) bind both sides:

1. Employee payloads carry no owner id — `?subject=me` resolves from the token.
2. Tracking thresholds are admin config, applied at read time, never rewriting stored fixes.
3. What the seller typed is what gets stored — free text stays free text; product name
   and colour are denormalised onto the sale line.

## 3. TODO

Live tracking on the admin map (`TrackingConfig` **v9** — spec locked 2026-11-04, `02` §3.11/§3.12/§3.14 · `03` §4/§4a/§5/§12/§14). Supersedes the v8 task pair, which was never built:
- [x] `web-agent` — server half of v9 — **done 2026-11-04** (built, tested, not deployed; see Contract status above)
- [x] `mobile-agent` — client half of v9 — **done 2026-11-04** (live team map + watch loop + drain cadence; unverified on a device)
- [ ] Decided 2026-11-04, recorded so it is not re-litigated: the live map is **Flutter admin only** — no PHP/web map page, `04-ADMIN-PANEL-SPEC.md` stands, and `GET /stream` (SSE) consequently has **no client at all**. Fresher-than-batch data is **pull-on-batch**, not FCM. Entering live mode takes up to one drain interval (~5 min, floored by the heartbeat); it is genuinely 15s once live.
- [ ] `mobile-agent` — **unverified on hardware:** `ic_stat_hazra.xml` (shipped 2026-11-04) has never been through `aapt2` or `setSmallIcon()` — there is no Android SDK on the dev machine. Eyeball the status bar on API 21 / 29 / 34 before release; this is the failure that silently regresses to a white box.

Standing / cross-cutting:
- [ ] **Deploy v9 (nobody has yet).** Server before app. `./sync.sh` then `gh workflow run deploy-server.yml --ref main`; confirm `GET /api/v1/config` → `version` reads 9 afterwards. Breaking bump 8 → 9.
- [ ] **Eyeball on a device — nothing visual in the 2026-11-04 work is hardware-verified.** There is no Android SDK on the dev machine: the bolt notification icon has never been through `aapt2`/`setSmallIcon()`, CARTO tiles have never been fetched, and marker legibility, the dwell ring, the bearing notch, cluster spacing and the bottom sheet's snap feel are all eyeballing jobs. `headingDeg` has never been seen from a live server.
- [ ] `mobile-agent` — two small follow-ups carried over from the 2026-11-04 live-values work: the new 15s admin poll keeps running when another tab is foregrounded if the admin shell is an `IndexedStack` (correct, but wasteful — gate it on route visibility as well as `AppLifecycleState`); and `TrackingController._outbox` is still memory-only, so session opens/closes owed to the server do not survive a process kill (pre-existing, not introduced by that work).
- [ ] `mobile-agent` — **PLAN `07-ANDROID-SESSION-NOTIFICATION.md`** (not built): Start→End-Day persistent Android notification with Break/Resume/End-Day buttons. Hard battery gate (reuse existing foreground service, no per-second updates). Run the geolocator-consolidation spike BEFORE Phase 1. Android only; iOS deferred.
- [ ] `docs-agent` + `design-agent` — **PLAN `08-USER-MANUALS-PLAN.md`** (not built): employee-app + admin-panel help pages on the token system. docs-agent writes the copy; design-agent builds the `html/` pages + media. Employee notification section gated on Doc 07.
- [x] `web-agent` — build out the `02-API-PLAN.md` route table beyond `UserController` — done 2026-08-19 (full v1 API)
- [x] `mobile-agent` — replace `lib/data/mock/` with HTTP repositories — done 2026-08-26 (zero `data/mock/` imports under `lib/features|widgets|state|services`)
- [x] `design-agent` — establish design tokens and the first prototypes — done (`html/styles/tokens.css` + ~18 pages)
- [ ] `web-agent` — install the `days:close` cron on prod; until then `closedBySystem` is never emitted (reported 2026-08-27)
- [ ] `web-agent` + user — rotate the production admin password (`admin123` is live) and set `must_change_password=1` in `001_bootstrap_admin.php` (reported 2026-08-28)
- [ ] `web-agent` — add `include=images` to `GET /reports` so uploaded report photos can be read back (reported 2026-08-26)
- [ ] `web-agent` — resolve `reportingTo` read-label/write-id asymmetry (reported 2026-08-26)
- [ ] `web-agent` + `mobile-agent` — decide `Idempotency-Key` on `/tracking/*`: plan §1.5 requires it, `TrackingController` does not call `idempotent()` (reported 2026-08-26)
- [ ] `web-agent` — nightly job to fill `absent`/`holiday`/`weekend` rollup rows (currently synthesised at read time) (reported 2026-08-19)
- [ ] `web-agent` — `/stream` closes after `ttl`s under the single-threaded PHP dev server; needs a real SAPI before production (reported 2026-08-19)
- [ ] `web-agent` — seeder draws straight-line traces nothing can road-match; dev cannot demo matching until it walks real road geometry (reported 2026-08-21)
- [ ] `web-agent` — delete dead scaffold auth (`core/Auth.php`, `api/controllers/UserController.php`) still referencing non-existent `users_tbl` (reported 2026-08-28)
- [ ] `design-agent` — real product data/photography; reconcile the product-page rename (the Aug-31 note's `chalo-*.html` are now `products.html`/`ev-details.html`) — see Discrepancies
- [ ] `design-agent` — audit `website/assets/css` against the token layer
- [ ] `mobile-agent` — surface `lowAccuracy` in route/stop UI so rough GPS stretches read as rough (follow-up to P0.2)
- [ ] `mobile-agent` — on-device verification of cold-start resume + kill-mid-session (P0.1 not exercised in CI)
- [ ] `mobile-agent` — upgrade the Android launcher to a real adaptive icon once a ≥1024px logo is supplied (currently masked legacy icon)
- [ ] user — back up the upload keystore `hazra-ev-upload.jks` off the dev laptop; losing it makes the Play listing un-updatable
- [ ] `docs-agent` — reconcile `docs/` (product/API) with `website/docs/` (framework docs); they are separate things and should say so

Mobile performance action plan — `docs/06-MOBILE-PERFORMANCE-PLAN.md`:
- [x] `mobile-agent` — **P0.1** persist offline fix queue to disk + reload on launch — done 2026-10-31 (`lib/services/location_queue_store.dart`, `shared_preferences`, debounced writes, restore-on-construct in `location_service.dart`)
- [x] `mobile-agent` + `web-agent` — **P0.2** keep >50 m fixes flagged — done 2026-10-31 (device uploads flagged low-accuracy fixes; server stores + classifies at read time)
- [x] `mobile-agent` — **P1.1** kill the 1s `notifyListeners` rebuild loop — done 2026-10-31 (`tracking_controller.dart` live clock now a `ValueListenable`; only leaf timer widgets repaint per second)
- [x] `mobile-agent` — **P1.2** wire `tracking_config.dart` to server config — done 2026-10-31 (`GET /config` already existed; no web-agent change needed). Thresholds now refresh at login/resume/session-start.
- [ ] `mobile-agent` — P1.3 retune `enableWakeLock`/`LocationAccuracy.high` against route quality (`location_service.dart`); now tunable server-side via config
- [ ] `mobile-agent` — P2.1 adaptive batch size + longer upload timeout (`tracking_config.dart`, `api_client.dart`)
- [ ] `mobile-agent` — P2.2 add a connectivity listener (`location_service.dart`, new dep)
- [ ] `mobile-agent` — P3.1 enable R8 + resource shrinking (`android/app/build.gradle.kts`)
- [ ] `mobile-agent` — P3.2 ship app bundle / ABI-split
- [ ] `mobile-agent` — P3.3 disk-cache admin map tiles (`route_tile_map.dart`)
- [x] `mobile-agent` — resume persisted session on launch for the 30-day refresh-token life (`lib/app.dart`) — done 2026-11-01
- [x] `mobile-agent` — battery round 1: GPS capture cadence 30s → 60s (`locationIntervalSeconds=60`); `_drain` moved from ~30s single-fix uploads to a fixed 5-min batch (~120 → ~12 server calls/hr) — done 2026-11-02 (`tracking_config.dart`, `location_service.dart`). The hard-coded 5-min is what v8 `syncIntervalSeconds` will make server-tunable.
- [x] `mobile-agent` — battery round 2: adaptive moving/stationary GPS (high@60s vs balanced@~3min), local `distanceFilterMetres=20` wired into geolocator settings, 90s transition debounce, and a 5-min heartbeat guardrail that injects a forced fix so a parked/filtered device never trips the 10-min offline threshold — done 2026-11-03 (`location_service.dart`, `tracking_config.dart`; `flutter test` 13 passing). All device-local; no API/DB change. `distanceFilterMetres` is shaped to adopt v8 server config.

## 4. Blockers

| Who | Blocked on | Who clears it |
| --- | --- | --- |
| `closedBySystem` state never emitted on prod | `days:close` cron not installed (command exists) | web-agent + ops |
| production admin account open | `admin123` live with `must_change_password=0` | web-agent + user |

P1.2's old `GET /api/config` blocker is cleared — the endpoint existed at `/api/v1/config` all along and mobile wired to it 2026-10-31.

## 5. Change log

| Date | Agent | Change |
| --- | --- | --- |
| 2026-11-03 | docs-agent | Contract-first spec for server-tunable drain intervals. `02` §3.12: `TrackingConfig` gains `syncIntervalSeconds` (300) + `distanceFilterMetres` (20), config `version` 7 → 8 (marked breaking), bootstrap example bumped to 8; documented the two-knobs distinction (sync vs capture), the `syncIntervalSeconds ≤ offlineThresholdMinutes×60/2` validation, the `syncBatchSize` early-flush interaction, and the heartbeat-vs-distance-filter guarantee. `03` §4: added `sync_interval_seconds`/`distance_filter_metres` columns + a `CHECK`, flagged PENDING web-agent (no migration written), with open questions (CHECK vs app clamp, history mirror, seed version). Queued two gated TODO tasks (web serve+validate+store v8; mobile parse+drive `_drain`+v7 fallback) and a v8 row in Contract status. |
| 2026-11-03 | docs-agent | Drained mobile battery reports. Round 1 (2026-11-02): GPS cadence 30s→60s + fixed 5-min `_drain` batch (~10x fewer server calls). Round 2 (2026-11-03): adaptive moving/stationary accuracy, local `distanceFilterMetres=20`, 90s transition debounce, 5-min heartbeat guardrail against false-offline. Verified against code (`tracking_config.dart:6`, `location_service.dart:512/785`). Both device-local, no API/DB change at the time. |
| 2026-11-01 | docs-agent | Added two PLAN docs (neither built). `07-ANDROID-SESSION-NOTIFICATION.md`: Start→End-Day persistent Android status-bar notification w/ Break/Resume/End-Day buttons, hard no-battery-drain constraint, geolocator-consolidation spike gates Phase 1, iOS deferred — refs verified (`ForegroundNotificationConfig` at `location_service.dart:635`; `startDay`:293/`pauseSession`:400/`endDay`:454 in `lib/state/tracking_controller.dart`; manifest lacks `POST_NOTIFICATIONS`). `08-USER-MANUALS-PLAN.md`: employee + admin help pages on the `tokens.css` system, docs-agent writes copy / design-agent builds `html/` + media, employee notification section gated on Doc 07. |
| 2026-11-01 | docs-agent | Full inbox drain. Merged 24 Aug–Nov notes (7 web, 10 mobile, 7 design — minus the Aug-31 18-pages note held as a discrepancy). Corrected the stale baseline: the full v1 API, MySQL port, road matching, closeout/password routes, de-mock, and the entire `html/` site all shipped months ago. Updated `02` (closeout/reopen, passwords, routes `snap`/`matched`) and `03` (route_geometry, day_closeouts, day_reopen_audit, must_change_password, MySQL port). |
| 2026-10-31 | mobile-agent | P1.2: wired `tracking_config.dart` to the existing `GET /config` — `EmployeeRepository.config()`, `TrackingController.applyConfig()`/`_refreshConfig()` at login/resume/session-start; `autoCloseAfterMinutes` preserved at default. No web-agent change; the "endpoint not built" blocker was a mischaracterization. Verified. |
| 2026-10-31 | mobile-agent | P1.1: removed the per-second `notifyListeners()`; live clock now a `ValueListenable<DateTime>` (`tracking_controller.dart`), only timer leaf widgets repaint (`session_card.dart`, `home_page.dart`, `profile_page.dart`). Verified. |
| 2026-10-31 | mobile-agent | P0.1: durable offline GPS queue via `shared_preferences` (`location_queue_store.dart`), reloaded on launch, debounced writes; low-accuracy fixes now kept + flagged device-side (P0.2 device half). Verified. |
| 2026-10-31 | mobile-agent | App version read at runtime via `package_info_plus` (`app_info.dart` + `main.dart`); both login forms no longer pre-fill credentials. No contract change. Verified. |
| 2026-11-01 | docs-agent | Merged web-agent note (2026-10-31): `Engine::ingest` stores low-accuracy fixes, `ACCURACY` dropped from reject enum (now `JUMP\|DUPLICATE\|MALFORMED`), derived at read time, no DB migration. Updated 02 §3.11 + 03 §5; P0.2 closed. |
| 2026-10-31 | web-agent | `Engine::ingest` no longer rejects on accuracy; low-accuracy stored and classified at read time; `ACCURACY` reason removed; wire shape unchanged; new CLI test `website/tests/tracking-ingest.php`. |
| 2026-11-01 | docs-agent | Added `06-MOBILE-PERFORMANCE-PLAN.md` (P0–P3); renamed from `03-` to clear the prefix collision; recorded P1.2 blocker. |
| 2026-11-01 | mobile-agent | Resume persisted session on launch for the refresh token's 30-day life (`lib/app.dart`); no API change. |
| 2026-09-01 | design-agent | Extracted the design-token layer (`html/styles/` — tokens/typography/global/utilities + `components/page-chrome.css`); self-hosted Inter+Montserrat woff2; SEO/OpenGraph/JSON-LD on 18 pages; hero PNG→WebP (12.9MB→392KB); unified theme localStorage key `theme` (was `vm-theme` on index). "Our Journey"/"More Than a Ride" built then removed at user request. Archived unbuilt plan halves into `docs/04-ADMIN-PANEL-SPEC.md` + `docs/05-PRODUCT-ARCHITECTURE.md`. |
| 2026-08-31 | design-agent | Delivered the animation & design strategy (`design-animation-plan.md`): inventory of 12 animated sections, 6 reusable patterns, enhancement roadmap. Reference doc, not a code change. |
| 2026-08-28 | web-agent | SQL-dialect audit: retracted the "six broken upserts" claim (`config/dialect.php::dml()` already rewrites them); fixed two real 500s — `DaysController` `ORDER BY rowid`→`id` (Activity tab) and `JwtAuth::user()` querying non-existent `users_tbl`→`users` (+`active=1`); `dml()` now rewrites `col IS ?`→`col <=> ?`. Patched PHP not yet on prod. |
| 2026-08-28 | web-agent | Production DB inventory (MariaDB 11.8.8): already clean — 1 admin + 1 org + bootstrap seed, migrations 001–010 applied; nothing wiped. Flagged `admin123`/`must_change_password=0` live. |
| 2026-08-28 | mobile-agent | Admin/employee login-hang fixed: empty body on 4xx/5xx now throws `ApiException` (`api_client.dart`); both sign-in handlers catch all errors; rebuilt signed APKs/AAB. Root cause was `AuthController` `IS ?` 500 (web-agent, fixed). |
| 2026-08-28 | mobile-agent | App identity → "Hazra EV": label, `applicationId`/`namespace` `com.hazraelectricalbike.app`, Kotlin package moved, launcher icon from `hazraev.png`, Linux target; signed release with upload keystore (outside repo). Dart package name left as-is. |
| 2026-08-27 | web-agent | Day closeout gets a server side: `POST /days/{subject}/closeout`, `POST /days/{id}/reopen`, `?include=closeout`, `dayState` always present, `php vayu days:close` midnight auto-close, server-enforced `409 DAY_CLOSED` on session open. Password endpoints: `POST /me/password`, `POST /employees/{id}/password`, generated `temporaryPassword`, `principal.mustChangePassword`. Tables `day_closeouts`/`day_reopen_audit` (009), `users.must_change_password` (010). Deployed to prod. Mobile client wired to match. |
| 2026-08-26 | mobile-agent | De-mocked employee+admin app: real `GeoLocationService`, `TrackingRepository` outbox, `CompanyDirectory`, `GET /me`, report image upload transport. Zero `data/mock/` imports remain in the live UI. Flagged 4 contract gaps (closeout routes, report-image read-back, `reportingTo`, `/tracking/*` idempotency). |
| 2026-08-25 | mobile-agent | Day closeout + day-lock UI built (`day_closeout_sheet.dart`, `day_closeout.dart`, admin reopen). Specified `POST /days/me/closeout`, `/reopen`, new enums — web-agent built the server side 2026-08-27. |
| 2026-08-25 | web-agent | MySQL port: `config/dialect.php` (the one driver-aware file), migrations retyped to tokens, table-level FKs, seed ledger, `php vayu db:sync`/`db:export`. Deployed to prod MariaDB (34 tables, 50 FKs, 96 indexes). SQLite stays the dev default. |
| 2026-08-21 | web-agent | Server-side road matching: `GET /routes` gains `matched` (polyline6) + `snap=` param, cached in `route_geometry` (008); OSRM + Valhalla drivers; `php vayu match` backfill. `02` §3.7 contract addition. |
| 2026-08-21 | mobile-agent | Admin map now draws the server's road match; `OsrmService` demoted to fallback (open day / `MATCH_ENABLED=false`). polyline6 decoder (`core/utils/polyline.dart`), `MatchedRoute` models. |
| 2026-08-19 | web-agent | Built the full `/api/v1`: 37 routes, 15 controllers, `api/support/` layer, 7 migrations, a derived-data seeder. 8 contract deviations from the plan (login moved off `UserController`; §3.11 id-in-tuple; `Attendance` embeds `summary`; `Employee.active`/`reportingToId`; `sync` always 0 server-side; `POST /reports`→200; etc.). SQLite port of the PG schema (no PostGIS/partitioning). |
| 2026-08-19 | mobile-agent | Admin route map moved to OpenStreetMap + OSRM road snapping (`flutter_map`, `osrm_service.dart`, `route_tile_map.dart`); painted canvas kept as offline fallback. Relocated mock dataset Dhaka→Bardhaman; flagged `website/database/seed.php` still on Dhaka (web-agent later relocated it). |
| 2026-08-26..09-01 | design-agent | Built the `html/` prototype across several reports: single-shot theme-driven hero, scroll-zoom reveal (`--p`), about mosaic + collection grid, 7-item navbar with dropdowns/accordion, scroll-scrubbed feature window (`--fi`), and ~18 content/product/dealer/service pages. All `html/` only; hrefs are `#slug` placeholders pending real routes. |
| 2026-08-18 | docs-agent | Board created; ownership map and inbox protocol defined. |

## 6. Discrepancies

Reports whose claims did not match the code. Empty is the goal.

| Date | Agent | Claim | What the code says |
| --- | --- | --- | --- |
| 2026-08-31 | design-agent | `design-2026-08-31-18-pages.md` lists six product pages created as `chalo-1000-v2.html`, `chalo-smart-pro.html`, `chalo-smart-plus.html`, `chalo-smart-eco.html`, `chalo-neo.html` (+ `nja-7.html`) | Only `nja-7.html` exists; the five `chalo-*.html` are absent, consolidated into `html/products.html` + `html/ev-details.html` in later (unreported) design work. The Sep-1 note's QA list also names `chalo-neo`. The rest of the 18 pages verify. Note held in inbox, not deleted. |
