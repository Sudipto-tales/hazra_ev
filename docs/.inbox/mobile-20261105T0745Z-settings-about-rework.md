# mobile-agent — Settings restructure, About rewrite, backend address removed from the UI

Date: 2026-11-05
Folder touched: `mobile_app/` only.
Verification: `flutter analyze` **14 issues** (baseline 15 — one pre-existing
`prefer_const_constructors` in `session_card.dart` was cleaned in passing), **0
errors** · `flutter test -j 1` **193 passed / 23 skipped / 0 failed** (was
175/23; +18 new).

---

## 1. The API address is no longer rendered anywhere

Six sites, not the four found in the first survey. The two extra were
`AppInfo.labelFor(live:)` callers that print "connected to server":

| Site | Was |
| --- | --- |
| `features/profile/info_pages.dart` (About) | `KeyValueRow('Backend', ApiConfig.baseUrl)` + `KeyValueRow('Data source', 'Live server')` |
| `features/auth/login_page.dart` | `'${AppInfo.label} · ${ApiConfig.describe()}'` |
| `features/auth/admin_login_page.dart` | same |
| `features/auth/role_select_page.dart` | `AppInfo.labelFor(live:)` |
| `features/admin/profile/admin_profile_page.dart` | `AppInfo.labelFor(live:)` |
| `features/profile/support_page.dart` | `'Data source': 'Live server' / 'Demo data'` |

All six now render `AppInfo.label` (version · build) or nothing.
`AppInfo.labelFor` is **deleted** — it had no other purpose.

`ApiConfig.describe()` is kept but is now reachable only from
`main.dart`'s `debugPrint`. The remaining `ApiConfig.baseUrl` /
`originUrl` references are request construction (`api_client`,
`update_controller`, `product_artwork`), never text.

The sign-in screens were the ones worth flagging: the login page is the
most-screenshotted surface in the app, and it was printing the host on every
screenshot.

## 2. About

- `Powered by `*`Hazra EV`* and `Developed by `*`Promix`*, italic on the
  company names only. Built as `Text.rich` spans rather than `KeyValueRow`
  because the emphasis belongs to part of a sentence, not to the value of a
  field.
- `© <year> Hazra EV. All rights reserved.`, where the year is
  `DateTime.now().year`. The old line was a literal `'© 2026 Company Ltd.'` —
  wrong company, and wrong altogether from 1 Jan.
- **Website** row: globe icon, label, chevron → opens `WebsiteLinks.baseUrl`
  (`https://hazraelectricalbike.com`). No new plumbing; `WebsiteLinks.open`
  already existed with its own failure snackbar.
- New `core/config/brand.dart` holds `product`, `developer`, `appName`,
  `year`, `copyright`. Deliberately holds **no** URLs or hosts.

## 3. Settings: six groups and a version card

Was: Account · Tracking (5 tiles incl. org thresholds and a release-visible
location simulator) · Notifications · App · Security · Support, with the
version as a tile.

Now: **Account · Permissions · Notifications · App · Security ·
About & support · Advanced**, then the version card.

Nothing was dropped. Everything diagnostic moved to a new
**`DeveloperOptionsPage`**, where the 15 config values are grouped by what
they govern — **Capture / Movement / Stops / Upload / Health** — plus "Live
diagnostics" (the tracking sheet) and "This device" (the three real
device-local switches).

Two deliberate changes there:

- The thresholds are rendered from `scope.tracking.config` — the live
  server-driven config — **not** `TrackingConfig.defaults`, which is what the
  old Settings screen showed. Showing defaults looked like an answer and was a
  guess, which is the same class of mistake as the report-image placeholder.
  They stay read-only: the admin owns them over `PUT /config`.
- "Simulate location state" is now `kDebugMode`-gated. It was visible in
  release builds, one tap from putting a real employee's day into a simulated
  location state.

## 4. Version card → version screen (four taps)

`VersionTapCard` counts taps with a 2 s reset window, so a stray tap while
scrolling does not accumulate toward the reveal across a whole session.

`VersionPage` runs one 2.6 s `AnimationController` across three intervals: the
rider crosses left→right (`0–0.68`), the exhaust clears (`0.62–0.86`), then the
build identity fades and scales up (`0.84–1.0`). Entirely `CustomPainter` —
**no new dependency and no asset**, matching the precedent already set by
`RoutePainter` and `product_artwork`'s painted fallback. The smoke puffs are
index-driven rather than random so a repaint at the same frame draws the same
thing.

**Update check** is on that screen, reusing the gate's controller rather than
building a second one. `UpdateController` was owned by `_UpdateGateState` and
unreachable from any page; it is now published through a new
`UpdateScope` (`InheritedNotifier`) wrapping the gate's child, so every pushed
route can read it. `maybeOf` is nullable because `app.dart` only installs the
gate on a live Android build — elsewhere the card explains itself instead of
offering a dead button.

Worth knowing: when an update *is* available the gate takes the whole app over,
so the user cannot reach Settings. The card therefore only ever reports
"checking", "up to date", or an error.

## 5. Permissions (partial — see the note)

New `PermissionsPage` under a **Permissions** group: live health, and **one
button that names the actual remedy**. Previously every prompt said "Enable
location" whatever the cause, which is wrong for two of the three location
failures.

Added to `LocationHealthX`:
- `actionLabel` — `Turn on location` / `Allow location` / `Go to settings` /
  `Allow all the time` / `Retry`, null where there is nothing to press
- `needsSystemSettings` — so the UI can warn it is about to leave the app

`LocationService.requestPermission` already routed correctly
(`openLocationSettings` vs `openAppSettings`); this only names the outcome so
the button and the result agree. The page re-polls on open **and on app
resume**, which is the moment the answer changes when someone returns from
Android settings and the moment nothing fires an event for.

### ⚠️ Still outstanding from the permissions workstream

This task covered the Settings-side surface only. Three items from the plan's
Part 5 are **not** done and are not implied by it:

- No proactive banner with a CTA on the **session card** — a mid-session
  revocation still shows a red card with nothing to press.
- `_pollHealth` still returns early unless a session is open, and the ticker
  that drives it stops on pause — so health is unwatched when idle or on break.
- `Geolocator.getServiceStatusStream()` is still unused; the GPS toggle is
  detected up to 15 s late rather than instantly.

## Tests

New `test/settings_about_test.dart`, 18 cases. The load-bearing group is
**"no backend detail is rendered"**, which asserts the *absence* of
`ApiConfig.baseUrl` / `originUrl` / `describe()` / the bare host, and of
"Live server", "Data source", "Backend" and "connected to server", across
About, Settings, Report-a-problem and Developer options. Nothing else in the
suite would catch a regression that puts the host back.

Also covered: the italic styling (walking the `TextSpan` tree — a plain
`findsOneWidget` on the name would pass with no styling at all, and the bare
name matches three widgets because it also appears in the header and the
copyright); the current year; the four-tap gesture and both negative cases
(three taps, and taps spread past the reset window); the reveal being hidden
mid-animation and complete after; and the `actionLabel` /
`needsSystemSettings` branching, including that the three location failures
produce three *distinct* labels.

Two test-harness notes for whoever writes the next widget test here:
`openPage(tall: true)` enlarges the viewport so `ListView` children below the
fold are actually built — without it `findsNothing` passes for the wrong
reason — and `openPage(settle: false)` is required for any page with an entry
animation, because `pumpAndSettle` runs it to completion before the test can
observe a frame. The reveal `Opacity` carries
`ValueKey('version-reveal')` because the update card also renders a
"Version … build …" string.

## Not hardware-verified

No Android SDK on this machine. The animation, the new page layouts and the
permission CTAs are covered by widget tests but have not been seen on a
device. The bikeman silhouette in particular is a judgement call that wants
eyes on it at phone width, in both themes.

## Unrelated finding, unchanged

`SupportContacts` (`features/profile/support_page.dart`) is still placeholder
data: `support@company.com` and `+880 9600 123456` — a **Bangladesh** dialling
code for a West Bengal company. It is shown to employees as the real support
contact and now also on the About page. Left alone because it is a content
decision, not a code one; it needs the real address and number.
