# mobile-agent — end-of-day closeout, day lock, session auto-close

Date: 2026-08-25
Folder touched: `mobile_app/` only.

## What was built

An end-of-day declaration form, and the day lock that follows it.

The governing distinction: **a session ending is not a day ending.**

- Sessions close often — on a break, or automatically when GPS and network are
  both gone. Reversible; the employee just starts another one.
- The day closes once, when the employee submits their declaration. After that
  Start Day is dead until an admin reopens it.

### Employee flow

1. Tap End Day → `TrackingController.checkEndDay()` gates on three things:
   day still open, device online, live GPS fix. Checked *before* the form opens.
2. Closeout sheet collects: distance travelled (their reading), visits made,
   1–5 rating, quick tags, optional free-text feedback. The GPS-measured
   figures are shown above the inputs, read-only.
3. Submit → `POST /days/me/closeout` → **server accepts first**, device marks
   the day closed second. A failed POST leaves the day open on both sides.

Declared numbers never overwrite `DaySummary.distanceKm` / `companiesVisited`.
Both are stored; the gap is the signal. Consistent with the standing rule that
tracking thresholds are applied at read time and never rewrite stored fixes.

### Offline

The closeout is deliberately **not** queued offline — the lock lives on the
server, so a day closed offline would be a fact only that one device believed.
Start Day is also online-gated for the same reason (one `GET /days/me` proves
connectivity and settles the lock in a single call).

### Auto-close

GPS unusable **and** no network, for `TrackingConfig.autoCloseAfterMinutes`
(default 30), with a session open → the session closes itself, reason `auto`,
**no form shown**. The day stays open. Home shows a one-time dismissible notice.
The server is expected to run the same rule from its side ("no fixes received"),
which is what covers a device that is simply dead.

### Admin

`AdminRepository.reopenDay({employeeId, date, reason})`. Reason is mandatory and
stored. The employee's original declaration is kept, not deleted. Surfaced as a
card on the admin employee day view showing declared vs tracked plus the signed
deviation, with a "Reopen day" button.

## API contract — needs `web-agent`, needs `docs/02-API-PLAN.md`

Three additions. Mobile codes against them today via `HttpEmployeeRepository` /
`HttpAdminRepository`; nothing is served yet.

```
POST /days/me/closeout         {clientId, endedAt, declaredDistanceKm,
                                declaredVisits, rating, tags[], feedback}
                               → {closeout, dayState}
                               Idempotency-Key: clientId
                               409 when already closed and not reopened

GET  /days/{subject}?include=…,closeout   → adds "closeout" + "dayState"

POST /days/{employeeId}/reopen {date, reason}  → dayState: open   (admin only)
```

Also needed server-side, not client-side:

- Idle-session auto-close (no fixes for N minutes).
- Midnight rollover force-closing any still-open day as `closedBySystem`.

New enums on the wire, serialised as the Dart enum `name`:
`dayState` = `open | closedByEmployee | closedBySystem`;
`WorkSession.endReason` = `manual | pause | auto`;
`tags[]` = `traffic | vehicleIssue | customerUnavailable | weather | noLeads | goodDay`.

Suggested tables: `day_closeouts`, `day_reopen_audit` — for
`docs/03-DATABASE-SCHEMA.md`.

## Files

New: `data/models/day_closeout.dart`, `data/mock/day_lock_store.dart`,
`features/home/widgets/day_closeout_sheet.dart`, `widgets/rating_bar.dart`,
`test/day_closeout_test.dart`.

Changed: `data/models/{tracking,home_snapshot,admin,models}.dart`,
`core/config/tracking_config.dart`, `data/api/wire.dart`,
`data/repositories/{employee,admin,mock_employee,mock_admin,http_employee,http_admin}_repository.dart`,
`state/tracking_controller.dart`, `features/home/home_page.dart`,
`features/home/widgets/{session_card,day_dialogs}.dart`,
`features/admin/employees/employee_detail_page.dart`, `main.dart`.

`confirmEndDay()` was removed — the closeout sheet shows the same figures, and
two modals back to back was friction with no payoff.

## Verification

`flutter analyze` clean for these files. `flutter test test/day_closeout_test.dart`
— 9/9 pass (lock, idempotency, deviation, reopen, admin view).

Pre-existing and untouched: `test/widget_test.dart` fails to compile
(`MyApp` scaffold left over from `flutter create`); no `MyApp` exists in `lib/`.
