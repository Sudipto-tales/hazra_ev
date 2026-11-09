---
agent: web-agent
date: 2026-11-08T22:43Z
status: done
breaking: false
---

## What changed
- `website/api/controllers/DaysController.php` — `show()` now adds an always-present `nonWorkingDay` boolean to the day snapshot, computed via `Engine::isNonWorkingDay(Ctx::orgId(), <that day's date>)` — the same single rule the `openSession` NON_WORKING_DAY 409 guard uses.

## Why
Lets the mobile client PROACTIVELY grey out Start Work on a holiday or weekend before the tap, instead of only learning from the 409 after the fact. Purely additive.

## API contract impact — ADDITIVE (non-breaking)
- `GET /api/v1/days/{subject}` (both `/days/me` and the admin `/days/{id}` mirror) — NEW top-level field `nonWorkingDay` (boolean), always present, camelCase to match the existing `dayState`/`locationHealth`/`movement` keys. `true` on a holiday (one_time or yearly) or a weekend day per `organizations.weekend_days`; `false` on an ordinary working day. It is a property of the date + org, so it rides on both the employee payload and the admin-mirror payload. No existing field changed.
- Mobile reads JSON key `nonWorkingDay`, tolerant/defaults false — confirmed the wire casing of that response is camelCase, so `nonWorkingDay` is the correct key.

## Tests
- Extended `website/tests/attendance-binary-holidays.php` (section E3): `GET /days/me` returns `nonWorkingDay=false` on an ordinary weekday, `true` on a weekend day, and `true` on a holiday. Full PHP suite re-run: 0 failures.

## Needs doing next
- [ ] docs-agent: record the new `nonWorkingDay` field on `GET /api/v1/days/{subject}` in `docs/02-API-PLAN.md` / the days-snapshot shape.
- [ ] mobile-agent: use `nonWorkingDay` for the proactive Start-Work disable; keep the NON_WORKING_DAY 409 handling as the backstop.

## Blocked on
- none
