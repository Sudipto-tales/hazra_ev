# Activity timeline keeps pace with Start Day (local/server hybrid)

**Agent:** mobile-agent · **Date:** 2026-11-06

## The problem the user raised

Start Day is already local-first — the session, joining time, worked clock and
distance all appear the instant it is tapped, and the outbox syncs later. The
**activity timeline** was the one panel that waited for the server's
`rebuildActivity` round trip, so on a slow network the tiles read "Working, 0:03"
while the timeline sat empty. One screen, two truths.

## Chosen option: hybrid, not a full local mirror

The device now synthesizes **only the boundaries it already owns the facts for** —
`dayStarted`, `sessionStarted`, `sessionEnded`, `dayEnded` — as *provisional* rows,
stamped with the fix time it also sends as `startedAt`, so the server's own row
later lands on the same instant and the timeline does not jump. It **never**
synthesizes `arrived`/`stayed`/`left`/`travelling`/`reportSubmitted`: those are
derived from the fix chain server-side (`deriveStops`/`promoteVisits`), and
duplicating that clustering on the client is exactly the two-sources-of-truth
problem §3.14's `activity` exists to prevent. Pure-server was the status quo; a
full local mirror would re-derive stops wrongly. The hybrid is the only one that
makes the timeline as immediate as the rest of Home.

## What changed (mobile_app/ only)

- `ActivityEvent.provisional` flag (model).
- `TrackingController`: a `_localActivity` list; a merged `activity` getter
  (server rows + un-superseded provisional rows, sorted); `_addProvisional` emits
  on Start Day (day+session), resume (session), pause/auto-close/End Day (session),
  and End Day (day); `_pruneLocalActivity` on refresh, clear on load/reset.
  Supersession = same `type` within 120 s (boundaries sit minutes apart).
- Home reads `controller.activity` instead of `snapshot.activity`.
- `ActivityTimeline` dims provisional rows (opacity 0.55) so "settling" is visible.

## Deliberately left out (flagged, not forgotten)

Offline / Back-online rows are still server-only. The device reports health
transitions via `POST /tracking/health` and they appear on the next refresh; the
alert-vs-recovery pairing and the "down for N minutes" copy are server-computed,
and synthesizing a half version locally would risk disagreeing with it. Worth
doing next, as a `trackingIssue` provisional reconciled on `(isAlert, instant)`.

Also relevant: the server-side `activity` **key collision** in `DaysController`
(enum at `:53` overwritten by the event array at `:114`) is still open on the
board. When it is renamed (`activityState`), `_reconcileStatus`'s `dayState`
inference can read the enum directly.

## Verification

`flutter test` → 271 pass / 23 skip / 0 fail (3 new hybrid tests in
`live_values_test.dart`: provisional rows appear on Start Day; a server row
supersedes its provisional twin on refresh rather than stacking; a session
boundary never synthesizes a stop/visit/report). `flutter analyze` → 15
pre-existing infos, none new.
