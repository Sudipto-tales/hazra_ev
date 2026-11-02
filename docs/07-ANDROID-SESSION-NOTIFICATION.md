# Android session notification — plan (2026-11-01)

A PLAN, not yet built. Goal: from **Start Day** to **End Day**, a persistent
status-bar notification that survives backgrounding and swipe-away, shows the live
work status, and offers action buttons — **Break / Resume / End Day** — where tapping a
button opens the app and performs the action. **ANDROID ONLY**: there is no `ios/` folder
in `mobile_app/`; the iOS equivalent (Live Activities) is explicitly **deferred**, not
in scope. Owner: mobile-agent.

## Battery constraint (hard requirement — read first)

The notification must add **no measurable battery drain**. This is a gate, not a nice-to-have.
It is met by *reusing what tracking already runs*, never by adding cost:
- **No second foreground service**, no extra wake-lock, no extra timer. The one service
  that already keeps GPS alive owns the notification too.
- **Updates only on state change** (Working ↔ On break ↔ Offline) or on the existing coarse
  **30–60 s tick**, reusing the P1.1 `clock` `ValueListenable` pattern
  (`tracking_controller.dart`). **Never per-second** — a per-second notification rebuild would
  reintroduce exactly the loop P1.1 removed.
- **GPS cost is unchanged**: the notification rides the existing session stream; it requests
  no fixes of its own.
- **Validation**: a before/after `dumpsys batterystats` comparison over a full session must
  show no regression. Same bar as perf-plan **P1.3** — route completeness unchanged, power
  flat.

## What exists today & the gap

geolocator already raises a foreground notification —
`ForegroundNotificationConfig` at `mobile_app/lib/services/location_service.dart:635`
(only when `stream` is true). But that notification:
- is **static** text, tied to the **GPS stream lifecycle**, not the Start Day → End Day
  work lifecycle;
- has **no action buttons**;
- **dies on process kill** along with the stream.

So there is no work-status notification, no Break/Resume/End-Day affordance, and nothing
that reconciles an open day after a kill.

## The notification

Contextual states, each a few short lines (status + elapsed + context) plus buttons:

| State | Lines | Buttons |
| --- | --- | --- |
| Working | "Working" · elapsed since Start Day · current context | Break, End Day |
| On break | "On break" · elapsed on break | Resume, End Day |
| Offline | "Offline — fixes queued" · last-known context | Resume/Break as applicable, End Day |

Buttons map to the verified controller methods (`mobile_app/lib/state/tracking_controller.dart`):
- **Break** → `pauseSession()` (`:400`)
- **Resume** → `startDay()` (`:293`)
- **End Day** → `endDay()` (`:454`), which routes to the **existing closeout sheet** —
  **never a one-tap end**. Ending a day is a reviewed action; the button opens closeout.

## Action flow

1. User taps a notification action button.
2. Android fires a `PendingIntent` carrying an **action id** (break / resume / end-day).
3. The intent **launches the app to Home** with a routing payload.
4. The app reads the payload and invokes the controller: Break and Resume **execute**
   directly; End Day **opens the closeout sheet** (no auto-finalize).
5. The resulting **state change refreshes the notification** (state-change update, not a tick).

Phase 1 always opens the app to perform the action. Background execution without opening
the app is Phase 2 (optional).

## Architecture

A **single foreground service** (recommend `flutter_foreground_task`) owns the rich,
action-button notification. geolocator streams **inside that kept-alive process** with its
**own foreground notification disabled**, so the user ever sees **exactly one** notification.

**KEY RISK — spike before Phase 1.** geolocator's Android stream currently relies on its
*own* foreground service. Hosting it inside another foreground service with geolocator's
notification suppressed must be proven to keep fixes flowing reliably (no stream pause, no
OS reclaim). A consolidation spike confirms this before any Phase 1 work starts.

## Resilience

- **Background + swipe-away**: covered by the foreground service plus a restart flag.
- **Force-kill**: survival is **OEM-dependent** (aggressive battery managers kill foreground
  services). Mitigate two ways:
  - prompt the user for a **battery-optimization exemption**;
  - **reconcile on relaunch** — the **server is the source of truth** for an open day, so on
    next launch the app asks the server whether a day is open and rebuilds state/notification
    to match. Nothing is lost even when the service is killed.

## New permissions / platform

Today's manifest (`mobile_app/android/app/src/main/AndroidManifest.xml`) has
`FOREGROUND_SERVICE`, `FOREGROUND_SERVICE_LOCATION`, and `WAKE_LOCK` — but **not**
`POST_NOTIFICATIONS`. The plan adds:
- **`POST_NOTIFICATIONS`** — Android 13+ **runtime** permission; without it no notification
  shows on A13+. Requested at the permissions step alongside location.
- A dedicated **notification channel** for the session notification.
- Optional: **battery-optimization exemption** prompt, and **`RECEIVE_BOOT_COMPLETED`** if
  boot-time reconciliation is pursued.

## Phasing

- **Phase 0 (recommended first): geolocator-consolidation spike** — prove geolocator streams
  reliably inside one foreground service with its own notification off. Gates Phase 1.
- **Phase 1**: Android session notification + action buttons that **open the app and perform**
  the action (Break/Resume execute, End Day opens closeout) + **relaunch reconciliation**
  against the server.
- **Phase 2 (optional)**: execute Break/Resume in the background without opening the app.

Owner: mobile-agent. iOS Live Activities deferred.
