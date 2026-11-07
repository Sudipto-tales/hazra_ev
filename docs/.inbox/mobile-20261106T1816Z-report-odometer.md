---
agent: mobile-agent
date: 2026-11-06T18:16Z
status: done
needs-api: measuredDistanceKm on POST /tracking/locations and PATCH /tracking/sessions/{id} — shipped in web-20261106T1816Z-client-odometer-distance.md (migration 037 must be deployed first).
---

# Phone reports its own odometer to the server (v3)

## Why

The device already measures its travel with the identical step gate as the
server, and reads the honest number. With the server now storing the client
odometer as the headline (v3, migration 037), the phone sends that number and
stops following a derived figure that could collapse at Day-End.

## What changed (mobile_app/ only)

- `data/repositories/tracking_repository.dart`:
  - `closeSession(...)` and `pushLocations(...)` gain optional
    `double? measuredDistanceKm`; HTTP impl adds it to the request body when
    non-null; mock/no-op impls accept it.
- `state/tracking_controller.dart`:
  - `_closeSession()` now returns the session's `closedKm`.
  - `_queueClose()` carries `measuredDistanceKm`; `_SessionOutbox` stores it;
    `syncPendingWork()` sends it on the close.
  - `uploadQueuedFixes()` rides the open session's live odometer
    (`_openSessionBaselineKm + _openSessionLocalKm`) on each batch; a draining
    batch for an already-closed session sends nothing (its final value went up
    with its close).
  - `_applyDistance()` restores the **monotonic floor**
    (`max(displayed, measured)`) — safe again because the server stores this
    device's odometer rather than a derived number that could reconcile
    downward. This is what removes the live-high → Day-End-low drop.

## Verification

- `flutter analyze` on the changed files: no issues.
- `flutter test test/tracking_sync_test.dart`: 9/9 pass, incl. a new assertion
  that the close carries a non-null, non-negative `measuredDistanceKm`.

## Needs doing next

- [ ] release: build must ship only after migration 037 is on production
      (older server ignores the field harmlessly, but the headline only becomes
      the odometer once the column exists).

## Paired with

`web-20261106T1816Z-client-odometer-distance.md`.
