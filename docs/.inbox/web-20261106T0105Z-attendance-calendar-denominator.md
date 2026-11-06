# Attendance % divides by the working calendar, not by stored rows

**Agent:** web-agent · **Date:** 2026-11-06

## The bug

`Engine::rollupDay` writes an `attendance_days` row only when a session exists
(it is only ever reached through `recompute()`), so a day nobody worked has **no
row**. `StatisticsController` computed its denominator by counting rows:
`workingDays = present + partial + absent`. With no absent rows, `workingDays`
equalled `attended`, so **every employee read 100% attendance** and `absentDays`
was always 0 — `02`/`03` call attendance a headline figure, and it was fiction.

## The fix (website/api/controllers/StatisticsController.php only)

Read-time calendar denominator — no new table, no migration, no nightly job, no
historical backfill (same "don't rewrite signed history" caution as the open
distance-backfill decision). New private `calendarWorkingDays(from, to, joinedOn)`:
weekdays in range that are neither a weekend (`Ctx::weekendDays()`) nor a holiday
(`holidays` table), never past `Ctx::today()`, never before the employee's
`joined_on`.

- `period()` (one employee): `workingDays` = calendar count; `presentDays` =
  present+partial; `absentDays` = max(0, workingDays − attended);
  `attendancePercent` = attended / workingDays. Honours `joined_on`.
- `team()` and its `perEmployee` ranking: same calendar denominator; the team
  percentage divides attended employee-days by `calendarWorkingDays × headcount`.
  Per-employee rows honour each head's `joined_on`.

Wire shape unchanged — same keys, corrected values. Not retroactive by nature
(it is read-time): retuning the weekend/holiday calendar re-derives and rewrites
nothing.

## Known approximation (flagged, not fudged)

`team()`'s single aggregate denominator does **not** subtract per-employee joining
dates (only the per-employee rows do), so a month with recent hires reads a little
low at the team-total level. Doing it exactly means one calendar pass per head.

## Verification

New `website/tests/attendance-stats.php` (throwaway SQLite, real migrations,
reflection on the real private methods): 21 working days over Oct 2026 minus a
holiday; present/partial/absent split; **no rows → 0%, not 100%** (the regression);
`joined_on` clips to 9; future range → 0; team → attended / (calendar × heads).
**All 24 PHP suites pass**, `php -l` clean.

## Handoff to docs-agent

`03-DATABASE-SCHEMA.md:612` says `attendance_days` is filled "by a nightly job
that also fills absent / holiday / weekend." **No such job exists** — it never
has. Please correct that line: absent/holiday/weekend are now derived at read time
in the statistics path (and the live dashboard shows "not started", not "absent",
for today — see the dashboard inbox entry). The schema's stored `status` enum
still only ever holds the worked-day verdicts the rollup writes.
