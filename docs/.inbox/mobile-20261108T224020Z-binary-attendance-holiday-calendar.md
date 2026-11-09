---
agent: mobile-agent
date: 2026-11-08T22:40Z
status: done
needs-api: true
---

## What changed
- lib/data/models/admin.dart — new `Holiday` model + `HolidayRecurrence { oneTime, yearly }` (with `.wire`/`.label`/tolerant `fromWire`).
- lib/data/api/wire.dart — `Wire.holiday` and `Wire.weekendDays` decoders; `homeSnapshot` now reads a tolerant `nonWorkingDay` bool.
- lib/data/models/home_snapshot.dart — added `isNonWorkingDay` (default false, additive).
- lib/data/repositories/admin_repository.dart + http_admin_repository.dart — `holidays(year)`, `saveHoliday()`, `deleteHoliday()`, `weekendDays()`, `saveWeekendDays()` against the live admin routes.
- lib/data/mock/holiday_store.dart — new shared mock calendar (holidays + weekend set) read by both mock repos; mirrors the server's yearly projection / upsert / delete semantics.
- lib/data/repositories/mock_admin_repository.dart + mock_employee_repository.dart + main.dart — wired the shared `HolidayStore`; mock `home()` now reports `isNonWorkingDay`.
- lib/features/admin/calendar/holiday_calendar_page.dart — new admin Holiday Calendar screen (read/write month grid + day editor sheet). Linked from Admin → Manage → "Holiday calendar".
- lib/state/tracking_controller.dart — new `StartDayOutcome.nonWorkingDay`; proactive Start-Day guard; `NON_WORKING_DAY`/`DAY_CLOSED` 409 handled on one path (rolls the local session back, raises one notice, never crashes/loops).
- lib/features/home/* — SessionCard shows a disabled "Not a working day" action; Home shows the non-working dialog and a "Could not start work" dismiss banner.
- Retired `partial` from the live UI (MonthLegend, team-attendance legend). Enum value + JSON parsing + swatch colour kept so historical payloads still decode/render; `partialDays` still parsed (always 0).
- Mock attendance generators are now binary (present/absent/holiday/weekend) — no `partial` produced.
- tests: test/holiday_calendar_test.dart, test/non_working_day_test.dart. Full suite: 284 passed, 23 skipped (live-server contract tests), `flutter analyze` clean (only pre-existing infos).

## Recurrence → endpoint mapping
- **One-time** → `POST /admin/holidays {recurrence:"one_time"}`.
- **Yearly** → `POST /admin/holidays {recurrence:"yearly"}`.
- **Weekly** → NOT a holiday: adds the tapped date's ISO weekday to the weekend set and `PUT /admin/weekend-days`. Unchecking a weekly day removes that weekday from the set (clears it across the calendar). Unchecking a dated holiday → `DELETE /admin/holidays?date=`.

## API I need from web-agent
- `GET /days/me` (employee home) — please add a `nonWorkingDay: bool` to the payload (you already have `Engine::isNonWorkingDay`). Mobile reads it tolerantly today (defaults false), so this is additive and non-breaking. Without it the employee Start-Work guard is reactive only (relies on the `NON_WORKING_DAY` 409); with it the button can be disabled/annotated before the tap. All five admin holiday/weekend routes and the two 409 codes match the shapes you shipped — no mismatch found.

## Needs doing next
- [ ] web-agent: add `nonWorkingDay` to the employee home/day payload (see above).
- [ ] design-agent (optional polish): new UI states worth a look — the admin holiday month grid + day-editor sheet (recurrence chips), the disabled "Not a working day" hero button, and the "Could not start work" banner. All use existing tokens/`AttendanceSwatch`; no new shared widgets were introduced.

## Blocked on
- Nothing. Mobile is unblocked via `lib/data/mock/holiday_store.dart` and the tolerant `nonWorkingDay` parse; swap-in is automatic once the server sends the field.
