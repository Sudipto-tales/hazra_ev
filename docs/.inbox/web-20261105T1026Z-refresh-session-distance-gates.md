---
agent: web-agent
date: 2026-11-05T10:26Z
status: done
breaking: false
---

## What changed

Board item §3 `web-agent` — **`Engine::refreshSession` distance gates**. The session
odometer summed *every* consecutive fix pair with no minimum-step gate and no accuracy
filter, so a parked device booked GPS jitter as travel. `maxJumpKmh` only guards ingest.

- `website/api/support/Engine.php` — new `Engine::chainDistanceKm(array $fixes): float`:
  the gated travelled-distance sum over an ordered fix chain. Two gates, both from config:
  **accuracy** (a fix worse than `min_accuracy_metres` does not measure a leg) and
  **minimum step** (a hop shorter than `distance_filter_metres` contributes 0 **and does
  not move the anchor**).
- `website/api/support/Engine.php` — `refreshSession()` now selects `accuracy_m` beside
  `lat, lng` and delegates the sum to the helper. Skipping a fix carries the last
  *accepted* fix forward as the anchor, so a weak-GPS stretch measures one honest leg
  across it rather than a detour out to the bad fix and back. Not moving the anchor on a
  sub-threshold hop is what stops jitter accumulating in small increments, and is also
  what keeps a genuinely slow walk measurable — displacement from the held anchor keeps
  growing until it clears the gate.
- `website/api/support/Engine.php` — `buildDayRoute()` uses the same helper for
  `day_routes.total_distance_km`. It was the identical raw sum; leaving it would have had
  the route card and the day summary disagree about the same day. The polyline is
  untouched — every stored fix is still drawn.
- `website/api/controllers/RoutesController.php` — `livePoints()` (today's
  not-yet-cached route) uses the same helper, so the live `distanceKm` and the cached
  `day_routes` number cannot differ for the same day. Helper is `public` for this reason.
- `website/api/support/Engine.php` — `recompute()` now calls a new private
  `refreshSessions($employeeId, $workDate)` **first**, before `deriveStops` and
  `rollupDay`. Without it a threshold change would regate the route and leave
  `work_sessions.distance_km` / `attendance_days.distance_km` on the old number, and
  `ConfigController::update`'s documented promise that same-day sessions are re-derived
  would only be half true. It also makes `recompute` the single entry point a distance
  backfill needs.
- `website/api/support/Engine.php` — `ingest()` dropped its now-redundant
  `refreshSession()` call; the `recompute()` on the next line covers that session.
- `website/tests/tracking-distance.php` — **new**, 20 checks (see below).

`MapMatch::pathDistanceKm` is deliberately untouched: it measures snapped road geometry,
not raw fixes, so there is no jitter in it to gate.

## Thresholds and where they come from

**No new column, no new `ConfigController::FIELDS` entry, no migration.** Both knobs
already exist and are already admin-tunable:

| Gate | Column | Wire field | Default | Source |
| --- | --- | --- | --- | --- |
| accuracy | `tracking_configs.min_accuracy_metres` | `minAccuracyMetres` | `50` | migration 002 |
| minimum step | `tracking_configs.distance_filter_metres` | `distanceFilterMetres` | `20` | migration 031 (v9) |

`distance_filter_metres` is the right existing tunable rather than a new one: `02` §3.12
defines it as the GPS distance filter the OS was asked to suppress fixes under, so a hop
shorter than it is by definition not travel — it is jitter or the client's ~5-minute
heartbeat fix.

Both are read through `Ctx::configValue()`, i.e. the org's `tracking_configs` row — the
same row `deriveStops()` and `liveStatus()` read — with the documented default only as a
fallback for a column that predates its migration. Nothing is stored about the decision,
so this stays read-time semantics: retuning either threshold re-derives distance on the
next refresh and rewrites no fix. A **non-positive** threshold disables its own gate
rather than swallowing the whole session (a `min_accuracy_metres` of 0 would otherwise
reject every fix and report a real day as 0 km; there is no DB `CHECK` preventing a 0).

## `point_count` decision

**It stays the raw stored fix count.** It answers "how many fixes does this session have"
— which is what the route and the trail draw, and it is the number that makes raw-fix
retention auditable. It is not an odometer-contribution count. The gates shape
`distance_km` only. Locked by assertions: the parked session keeps `point_count = 60`
with `distance_km = 0`, and the low-accuracy route counts the skipped fix (6 fixes,
5 measured).

## API contract impact

- `GET /routes/...` — `distanceKm` / `totalDistanceKm`: **same shape, corrected value.**
  A parked or jittery day now reports fewer kilometres. Not breaking.
- `GET /days/...`, attendance and statistics — `distanceKm` sourced from
  `work_sessions.distance_km` / `attendance_days.distance_km`: same, corrected value.
- `POST /tracking/locations` — `{accepted, rejected}` unchanged. Ingest still rejects on
  **MALFORMED / JUMP / DUPLICATE only**; accuracy is still never a rejection reason and
  low-accuracy fixes are still stored and still drawn (`02` §3.12, `03` §4a). Verified by
  `tests/tracking-ingest.php`, unchanged and passing.
- No new field, no rename, no removal, no status-code or auth change.

**Doc follow-up for `docs-agent`** (I did not edit `docs/`): `02` §3.12's
`distanceFilterMetres` row and `03` §4's column note both describe it as device-side
only. It is now **also** the server's minimum-step gate for derived distance, which means
widening it to save battery also coarsens the odometer. That is worth one sentence in
both, plus a line that `minAccuracyMetres` now has a second read-time use beyond the
low-accuracy flag.

## Test results

New suite `website/tests/tracking-distance.php` (modelled on `tests/tracking-ingest.php`
— in-memory SQLite, every migration, no application DB). Each fixture asserts the old
raw sum would have been wrong before asserting the new one is right:

1. **Parked device** — 60 good-accuracy fixes inside a ~6 m circle. Old sum 0.52 km;
   now exactly `0.0`, `point_count` 60.
2. **Straight-line walk** — 21 fixes, 60 m apart. ~1.2 km, matching the first-to-last
   great circle to within 5 m, and identical to the raw sum (above-threshold hops are
   untouched).
3. **Slow crawl** — 41 fixes 8 m apart, every hop *under* the 20 m gate. Still measures
   0.31 km of the 0.32 km walked (at most one trailing sub-gate chunk unbanked). Zero here would mean the anchor was being advanced on a rejected hop.
4. **Low-accuracy fix mid-route** — 5 fixes 100 m apart plus one accuracy-500 m fix
   300 m off the line. Old sum 0.93 km; now 0.40 km, and the leg across the skipped fix
   is still measured once (not dropped, not doubled). `point_count` 6.
5. **Degenerate** — empty session `0.0 / 0`; one-fix session `0.0 / 1`; an all-low-accuracy
   session `0.0` with all 5 fixes still counted.
6. **Read-time semantics** — relaxing `min_accuracy_metres` to 1000 re-admits the stored
   fix on the next refresh and tightening it back restores the gated sum; a 0 m filter
   falls back to the raw sum; a 200 m filter still measures the walk without inflating it
   and still holds the parked device at 0.
7. **Coherence + backfill** — a session hand-set to a stale 9.5 km is repaired by
   `Engine::recompute` alone; `day_routes.total_distance_km`,
   `attendance_days.distance_km` and `Engine::chainDistanceKm` over the raw rows (the
   live-route path) all agree.

```
tests/account-deletion.php         PASS      tests/report-images.php            PASS
tests/app-release-access.php       PASS      tests/tracking-distance.php        PASS  (new)
tests/app-release-publish.php      PASS      tests/tracking-ingest.php          PASS
tests/app-release-update.php       PASS      tests/tracking-live.php            PASS
tests/config-v9.php                PASS      tests/user-password.php            PASS
tests/dealer-locations.php         PASS      tests/whatsapp.php                 PASS
tests/dealer-locations-seed.php    PASS
tests/message-delivery.php         PASS      15/15 PHP suites green
tests/product-content.php          PASS
```

`node tests/message-delivery-ui.js` passes. **`node tests/dealer-location-picker.cjs`
fails** at line 39 (`el('placeResults').children.length` is 0, expected 1) — pre-existing
and unrelated: it exercises `website/assets/admin/js/pages/dealer-locations.js`, which
this task did not touch and which is `design-agent`'s file. Flagging, not fixing.

## Backfill

Yes — already-stored sessions keep their inflated `distance_km` until something
re-derives them, and the fix is **not** retroactive on its own.

- **Open / still-reporting sessions self-heal.** `refreshSession` rescans the session's
  whole chain, so the next accepted batch rewrites the number correctly.
- **`Engine::recompute($employeeId, $workDate)` is now the complete path**, and it was
  not before this change — it rebuilt stops, route, timeline and rollup but never touched
  `work_sessions.distance_km`. With `refreshSessions()` added it re-derives the odometer,
  the route total and `attendance_days.distance_km` in one call. A backfill is a loop over
  distinct `(employee_id, work_date)` in `work_sessions` calling `Engine::recompute` inside
  `Ctx::forOrganization(...)`, exactly as `database/demo/seed_road_days.php` and
  `CloseDaysCommand` already do.
- **Nothing was run against production** and no backfill command was added. Two things
  need a human decision first: (a) whether closed historical days should be re-derived at
  all, since the Engine header's rule is that a config change leaves closed days as they
  were classified, and (b) that a backfill will visibly *lower* historical distance
  figures, which may already have been read, exported or reconciled against
  `day_closeouts.declared_*`. The declared-versus-tracked card compares a stored
  declaration against a tracked number, so a backfill moves one side of a comparison an
  employee already signed.

## Needs doing next

- [ ] `docs-agent` — the two doc sentences under "API contract impact" above
      (`02` §3.12 `distanceFilterMetres`, `03` §4 column note, `minAccuracyMetres`'s
      second read-time use).
- [ ] **user** — decide whether historical days get a distance backfill (see above). If
      yes, `web-agent` can add a `vayu distance:rebuild --days/--employee/--dry` command
      on top of `Engine::recompute`; it is ~40 lines and needs no new logic.
- [ ] `mobile-agent` — no action required, but be aware a parked day now reports ~0 km
      instead of a few hundred metres. If any client-side copy or test asserts "distance
      grows while parked", it was asserting the bug.

## Blocked on

- Nothing.
