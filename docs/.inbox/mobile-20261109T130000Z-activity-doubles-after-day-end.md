---
agent: mobile-agent
date: 2026-11-09T13:00Z
status: done
needs-api: none
---

# Activity timeline doubled every event after the employee ends the day

**Symptom (reported, with screenshot):** On the employee Activity screen, after
tapping End Day, each event showed twice (Session started, Arrived, Stayed,
Session ended, Day ended all doubled; Day started / Back online single). Reported
as happening every day, online or offline.

**Server ruled out.** Reproduced the whole day-end flow against the real code on a
throwaway DB — ingest fixes → live `Engine::recompute` (x2) → the real
`DaysController::closeout()` → the real `DaysController::show()` read. The returned
`activity` array had **exactly one of each event** (7 total). `rebuildActivity`
deletes-then-inserts and is idempotent; stop derivation reconciles by arrival key;
`work_sessions` has `UNIQUE(employee_id, client_id)` and `(employee_id, work_date,
seq)`. No server-side duplication in any path tested.

**Client cause.** `TrackingController.activity` merges the device's *provisional*
session-boundary rows (`_localActivity`: dayStarted/sessionStarted/sessionEnded/
dayEnded) on top of the server's timeline, dropping each provisional row once the
server sends a matching one (`_isSuperseded`, type + 120s window). After End Day the
day is **locked** and the server has already rebuilt and returned the complete,
authoritative timeline — so the provisional rows have nothing to add, and any the
supersede match missed (fix-time vs tap-time drift, device/server clock skew) linger
as a second copy. That is the post-close doubling.

**What changed**
- `mobile_app/lib/state/tracking_controller.dart`
  - `activity` getter: once `isDayLocked`, return the server list alone (no
    provisional merge). The closed day's timeline is server-authoritative.
  - `refresh()`: clear `_localActivity` when the snapshot comes back locked, so no
    provisional rows survive the close.
  - `dart analyze` clean.

**Needs doing next / watch**
- [ ] (mobile-agent, after release) Confirm with the reporter that the doubling is
      gone after End Day. If duplicates still appear — **especially in the admin
      day view** (`EmployeeDetailPage`, which reads server `activity` directly with
      no client merge) — that indicates duplicate rows already in the production DB
      (work_sessions / stop_records), which needs a web-agent server-side pass; the
      current app code does not create them.
