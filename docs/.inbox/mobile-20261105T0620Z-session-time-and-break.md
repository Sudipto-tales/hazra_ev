# mobile-agent — session start time rendered in UTC, and Break left the day unresumable

Date: 2026-11-05
Folder touched: `mobile_app/` only.
Verification: `flutter analyze` 15 issues (baseline, unchanged) · `flutter test -j 1`
**175 passed / 23 skipped / 0 failed** (baseline was 160/23; +15 new).

Two field-reported bugs. Neither was cosmetic and neither was a config or
timezone-setting problem.

---

## 1. "Session started at 08:35 showed 03:05"

**Cause.** `geolocator` builds `Position.timestamp` with
`DateTime.fromMillisecondsSinceEpoch(..., isUtc: true)`, so every fix arrived
UTC-flagged. `Fmt.time` reads `.hour`/`.minute` straight off the DateTime, so
it rendered the **UTC wall clock**: 03:05 for an 08:35 IST start (+05:30).

The instant was always correct — only the flag was wrong, which is why the
value self-healed after the first server refresh: `Wire.dateOrNull` already
calls `.toLocal()`. That intermittency is what made it look like a timezone
setting.

The server half was never implicated: `Wire::TS = 'Y-m-d\TH:i:s\Z'` with
`gmdate()` emits a correct trailing `Z`, and uploads still go out as
`.toUtc().toIso8601String()`. **No wire-contract change.**

**Fixed at three levels:**

| Site | Change |
| --- | --- |
| `lib/services/location_service.dart` `_toLog` | `position.timestamp` → `.toLocal()` — the producer |
| `lib/data/models/tracking.dart` `LocationLog.fromJson` | added `.toLocal()` — same defect via the durable offline queue |
| `lib/core/utils/formatters.dart` | new private `_local()` applied in `time`, `longDate`, `mediumDate`, `monthDay`, `monthYear`, `weekdayShort`, `weekdayLong` — backstop, no-op on local input |

Deliberately **not** changed: `Fmt.clock`/`duration` take a `Duration`;
`Fmt.relative`/`freshness`, `isSameDay`, `dayOnly` and
`LocationQueueData.lastSyncedAt` all compare instants (`.difference`,
`.isAfter`), which is flag-agnostic. `lastSyncedAt` is only ever rendered
through `Fmt.freshness`, so it needs nothing.

## 2. "Break does nothing"

`pauseSession()` worked — the session closed and tracking stopped. The defect
was the status it chose: `WorkStatus.idle`, which **`isSessionOpen` reports as
true**.

`idle` is overloaded. Server-side it means "open session, stationary"
(`Engine::liveStatus`, `02` §3.14 row 7) — where `isSessionOpen == true` is
correct. The device reused the same value for "paused, nothing open", where it
is exactly backwards. Four symptoms from that one line:

1. Button never changed — `_Actions` guards on `!status.isSessionOpen`, so the
   `'Resume session'` label and play icon already in `session_card.dart` were
   **unreachable dead code**.
2. Timer rendered as live while frozen (`_stopTicker()` had already fired).
3. **Resume was impossible** — `startDay()` opens with
   `if (status.isSessionOpen) return alreadyRunning`.
4. On the next poll the server returned `ended` (`Engine.php`:
   `$status = $sessions ? 'ended' : 'not_started'`), so `_Actions` rendered a
   **disabled "Day completed"** on a still-open day. The employee was locked
   out until they restarted the app.

**Fix.** New `WorkStatus.paused`, reporting `isSessionOpen == false`. `idle`
was left alone so the server reading and the admin map's `idle` glyph are
unaffected. Sites updated: `tracking.dart` (enum, `label` → 'On Break',
`isSessionOpen`), `tracking_controller.dart` (`pauseSession`,
`_autoCloseSession`), `session_card.dart` (gradient, subtitle, resume
label + `play_circle_fill_rounded`), `status_badge.dart`,
`team_status_tile.dart`, `live_marker.dart`, `mock_admin_repository.dart`.

Six of those were exhaustive switches, so the compiler enumerated them. One
was **not**: `_idleSubtitle` had a `_ => ''` default, and its
`'On break · tracking paused'` string was keyed to `idle`, where it could never
run. Moved to `paused`.

### Decode-time repair, and why it is not a §3.14 re-derivation

`status` cannot carry paused-vs-ended from the server. New
`TrackingController._reconcileStatus` recovers it: `status == ended` **and**
`dayState` unlocked **and** sessions exist **and** none open → `paused`.
`dayState` is served unconditionally and parsed correctly.

This repairs one lossy enum; it does **not** rebuild `activity`, which stays
the server's job per `02` §3.14.

---

## ⚠️ Two findings for other agents

**1. `web-agent` — `activity` key collision in `DaysController` (blocker).**
The correct source for this distinction already exists server-side:
`Engine.php` does
`dayLock(...)['state'] === 'open' ? 'paused' : 'ended'` (§3.14 rows 8/8b), and
`ActivityState.paused` ('On break') already exists in Dart. But it is
**unreachable on this payload**: `DaysController` sets
`$payload['activity'] = $live['activity']` (a string enum), then
`if ($this->wants('activity'))` **overwrites the same key** with the
activity-event array. Home requests the array, so the §3.14 enum is dropped;
without the include, `Wire.maps()` sees a string and yields `[]`.

One key, two types. Needs a rename on one of them (e.g. `activityState` for
the enum). Once fixed, `_reconcileStatus` can read `activity` directly and the
`dayState` inference can go. Not blocking today — the `dayState` path is
correct and server-independent.

**2. `mobile-agent` (follow-up) — the status bar does not reflect the change.**
`pauseSession` → `_stopTicker()` → `_sessionNotif?.stop()` tears the whole
notification down, and `SessionNotificationService._buttons()` is a `const`
list hardcoded to `Pause` / `End Day` with a literal
`'Work session running'` title. There is no Resume button in the status bar at
all.

It cannot simply be kept alive: the manifest declares only
`android:foregroundServiceType="location"` with only
`FOREGROUND_SERVICE_LOCATION` granted, so the service can legally run only as
a location service — and holding one while deliberately not tracking is
misleading and a Play policy risk. Tearing it down on pause is the *correct*
behaviour; a separate plain "Session paused · tap to resume" notification is a
follow-up needing `flutter_local_notifications`, and its action should
**launch the app** (as End Day already does via `launchApp()`) because a
headless resume needs `_repo.home()` plus a GPS fix after the process may have
been killed.

---

## Tests

New `test/session_pause_resume_test.dart`, 15 cases in four groups: the
`paused`/`idle` vocabulary split; pause → resume opening a genuine second
session with worked time preserved; `_reconcileStatus` including all four
cases that must **not** repair (closed-by-employee, closed-by-system, no
sessions, an open session); and local-time rendering.

One existing assertion updated:
`location_queue_persistence_test.dart:104` asserted
`back.recordedAt == original.recordedAt`. Dart's `DateTime.==` compares
`isUtc` as well as the instant, so it was pinning the UTC flag that was the
bug. Now asserts `isAtSameMomentAs` (the real contract) **plus**
`isUtc == false` (the new invariant) — strengthened, not relaxed.

The timezone test asserts a hard literal (`'08:35 AM'` from
`DateTime(...).toUtc()`) and calls `markTestSkipped` on a UTC host, where a
zero offset cannot distinguish the bug. This dev machine is Asia/Kolkata
(+05:30), so it is meaningful here.

## Not hardware-verified

No Android SDK on this machine. Both fixes are logic-level and covered by
tests, but the Home card's paused state and the "Resume session" button have
not been seen on a device.
