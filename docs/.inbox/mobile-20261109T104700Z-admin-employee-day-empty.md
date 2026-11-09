---
agent: mobile-agent
date: 2026-11-09T10:47Z
status: done
needs-api: none
---

# Admin "employee day" showed "No session was opened" for every employee

**Symptom (reported):** When an admin opens an employee's profile/day in the app,
it reads *"No session was opened"* / no record — for every employee, every time —
even though the employee's own app shows that same day's sessions.

**Root cause:** `HttpAdminRepository.employeeDay()` requested the day with the
**device's** local calendar date (`Wire.day(DateTime.now())`), while everything
else that reads a day resolves "today" **on the server, in the org timezone** —
the employee's own `/days/me` (sends no date) and the `memberById` call on the very
same admin screen (sends `date: 'today'`). Whenever the device date and the org
date disagree (device in another timezone, or just after midnight), the admin day
view asked the server for a different calendar day than the one the sessions were
recorded under, so `work_sessions` came back empty and the page fell to its
empty-state. The status badge (from `memberById`, server "today") still read
"working", which is the tell.

Verified against the real API on throwaway SQLite: an off-by-one date returns
`sessions=0`; `date=today` returns the session. The server/API was correct — no
API change needed.

**What changed**
- `mobile_app/lib/data/repositories/http_admin_repository.dart` → `employeeDay()`
  now sends `date: 'today'` when the requested date is the current day (and the
  explicit `Wire.day(date)` only for past, scrubbed days), matching `memberById`
  and the employee's own day fetch. Added the `formatters.dart` import for
  `Fmt.isSameDay`. `dart analyze` clean.

**Needs doing next**
- [ ] (mobile-agent) Consider the same "resolve today server-side" rule for any
      other admin reads that take an explicit date default (e.g. route/map day
      pickers) so the admin and employee can never land on different days.
