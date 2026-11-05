# mobile-agent — location health is now watched, and every prompt names its own remedy

Date: 2026-11-05
Folder touched: `mobile_app/` only.
Verification: `flutter analyze` **14 issues** (baseline 15), **0 errors** ·
`flutter test -j 1` **204 passed / 23 skipped / 0 failed** (was 193/23; +11 new).

Closes the three items left open by
`mobile-20261105T0745Z-settings-about-rework.md`.

---

## 1. Health is pushed, not polled on a session timer

New `LocationService.healthState()` — `health()` as a stream, mirroring the
existing `movementState()` precedent — implemented in `MockLocationService`,
`GeoLocationService` and the two test fakes.

`GeoLocationService` drives it from `Geolocator.getServiceStatusStream()`,
which fires the instant the GPS master switch moves. `refreshHealth()`
recomputes and publishes only on a real change, so an unchanged recompute
wakes nobody.

`TrackingController` subscribes **in its constructor**, for the controller's
whole life rather than the session's. That is the actual fix: the only health
check used to hang off the per-second session ticker (`_tick % 15`), and
`_pollHealth` returned early unless a session was open — so

- GPS switched off **before** Start Day said nothing until the tap was refused;
- GPS switched off **on a break** said nothing at all, because
  `pauseSession` → `_stopTicker()` cancels that ticker outright.

`_pollHealth` is kept as the backstop. A runtime permission revoked from the
settings app has no equivalent stream on Android, so the poll still earns its
place; the stream only makes the common case immediate instead of up to 15 s
late.

### Two readings, deliberately not merged

- `locationHealth` (on the snapshot) — session state, and what gets pushed to
  the server via `_pushHealth`.
- `idleLocationHealth` — a new field, set only when **no** session is open.

They are separate because writing an idle reading into the snapshot would send
`_statusForHealth` down the degraded path for a day that has not started.
`effectiveLocationHealth` picks the right one and is what the UI reads.

### ⚠️ One API-shape consequence

`healthState()` is a new **abstract** member on `LocationService`, so any other
implementation must add it. Both fakes in `test/` were updated
(`_ScriptedLocationService` in `live_values_test.dart` gained `emitHealth`).
Nothing outside `mobile_app/` implements this interface.

## 2. The banner now fixes the problem instead of describing it

Home already raised an `AlertBanner` for degraded health, but it read the
snapshot's `locationHealth` (meaningless with no session open) and its button
said **"Details"** and opened a read-only sheet.

Now it reads `effectiveLocationHealth`, and the button is
`health.actionLabel` wired to `_fixLocation`, which calls
`requestPermission` and then re-reads so the banner clears without waiting for
a poll. `showLocationBlockedDialog` takes the same label.

New on `LocationHealthX` (added in the previous task, now consumed in three
places):

| Health | Button | Route |
| --- | --- | --- |
| `serviceDisabled` | Turn on location | `openLocationSettings()` |
| `permissionDenied` | Allow location | in-app re-request |
| `permissionDeniedForever` | Go to settings | `openAppSettings()` |
| `backgroundDenied` | Allow all the time | `openAppSettings()` |
| `noInternet` | Retry | re-read |
| `ok` / `poorAccuracy` | — | no banner |

`requestPermission` already routed correctly; this only makes the label and the
outcome agree. Every state Home banners for now has an action — asserted in
the tests, so a new `LocationHealth` value cannot be added without one.

`_HomePageState` is now a `WidgetsBindingObserver` and calls
`refreshLocationHealth()` on resume — the moment someone comes back from
Android settings, and the one change this process gets no event for.
`PermissionsPage` calls the same method so Home's banner clears in step with it
rather than on its next poll.

## 3. A regression this work found and fixed

Arming the service-status watcher **in the constructor** broke
`location_queue_persistence_test.dart`:

```
Binding has not yet been initialized.
  package:flutter/src/services/platform_channel.dart  EventChannel.receiveBroadcastStream
  .../location_service.dart  GeoLocationService._watchServiceStatus
  .../location_service.dart  new GeoLocationService
```

`getServiceStatusStream()` is an `EventChannel`, so subscribing reaches for
`ServicesBinding.instance`. Constructing `GeoLocationService` anywhere without
a platform binding — a plain Dart test, an isolate — threw, and because the
throw arrives after an async gap the surrounding `try`/`catch` did not contain
it.

Arming is now **lazy**, on the first `healthState()` subscription, and
idempotent. Construction is platform-free again and the cost is only paid when
something actually listens. This was a real robustness bug, not only a test
artefact.

## Tests

New `test/location_health_watch_test.dart`, 11 cases, built on a
`_PushyLocationService` that counts pulls so a test can prove the **push** path
did the work:

- a pushed change is seen with no session open (the previously-silent case);
- still seen after Break stops the ticker;
- listeners notified, and **not** notified on an unchanged value;
- with a session open it lands on the snapshot, not the idle slot;
- an idle reading never degrades a not-started day (`isDegraded` stays false);
- `refreshLocationHealth` re-reads on demand and is safe after `dispose`;
- every state Home banners for has an `actionLabel`, and `ok`/`poorAccuracy`
  have none;
- assigning `MockLocationService.simulatedHealth` publishes on `healthState`
  (it is now a setter, so Developer options exercises the push path too).

Harness note: `TrackingController.dispose()` is **not idempotent** — it trips
a `ValueNotifier` assert on a second call. The after-dispose test therefore
builds its own controller instead of using the shared `setUp` one.

## Not hardware-verified

No Android SDK on this machine. `getServiceStatusStream()` has never been
exercised against a real GPS toggle, and the four permission CTAs have never
actually reached the Android settings screens — those are the two things worth
checking first on a device. Toggling location off mid-session, while idle, and
while on break are three separate manual cases.

## Still open elsewhere (unchanged by this task)

- **web-agent** — the `activity` key collision in `DaysController` (see
  `mobile-20261105T0620Z`). Not blocking; the `dayState` inference is correct.
- **web-agent** — config defaults migration, `Engine::refreshSession` distance
  gates, and `include=images` + `saleLine.imageUrls`.
- **Content decision** — `SupportContacts` is still
  `support@company.com` / `+880 9600 123456` (a Bangladesh dialling code for a
  West Bengal company), shown to employees as the real support contact and now
  on About too.
