# Admin dashboard tells one coherent story

**Agent:** mobile-agent · **Date:** 2026-11-06

## What changed (mobile_app/ only)

1. **`headcount` / `attendancePercent` now come from `meta.totals`, not the paged
   member list.** `TeamOverview` turned these two into fields read from the server
   envelope (the other totals already were). The numerator (`presentCount`) is
   full-roster server-side while `members` is one page (limit 200), so the old
   getters divided a full-roster count by a paged one — silently wrong past 200
   field staff. `wire.dart` and the mock repo updated.

2. **Fixed the incoherent attendance bar.** The `ProportionBar` mixed two axes —
   live status (Working/Idle/Offline) plus an attendance-axis **Absent** segment
   that is always 0 today (no `attendance_days` row is written until someone
   works), while the bucket people actually sit in before clocking on —
   **Not started** — had no segment. So the bar never summed to the roster and
   read "0%" at 9am. Replaced the dead Absent segment with Not started.

3. **"Not started" alert now excludes deactivated staff** (`m.active &&`), matching
   how the long-stop and offline alerts already filter inactive — otherwise an
   ex-employee is named "not started" forever. (Left server-side headcount alone:
   `EmployeesController::totals` deliberately keeps inactive in the roster and out
   of alerts — documented intent, not touched.)

4. **Relabelled the distinct-company count.** Dashboard "Visits today" →
   "Companies visited" and team-analytics "Visits" → "Companies", because the
   value is `COUNT(DISTINCT company_id)` (what Home already calls "Companies
   visited"), not a visit count. Genuine visit lists (employee detail, route map)
   keep "Visits".

5. **`Fmt.percent` floors instead of rounds** — 99.6% no longer displays as a flat
   "100%"; a shown 100 now means exactly 100.

## Contract

No wire-shape change — consumes `meta.totals.headcount` / `.attendancePercent`,
which `EmployeesController::totals` already sends.

## Verification

`flutter test` → 268 pass / 23 skip / 0 fail. `flutter analyze` → 15 pre-existing
infos, none new.
