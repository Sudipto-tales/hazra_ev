---
agent: web-agent
date: 2026-11-06T17:55Z
status: done
breaking: false
---

## What changed

- `website/core/Console/WorkedRebuildCommand.php` — NEW. `php vayu worked:rebuild`, the
  worked-seconds backfill for `09` Phase 1. Writes `attendance_days.worked_seconds`,
  `status` and `computed_at` and nothing else. Does **not** call `Engine::recompute`,
  `refreshSessions`, `refreshSession`, `deriveStops`, `promoteVisits`, `buildDayRoute`,
  `rebuildActivity` or even `rollupDay`. Options `--dry` / `--date` / `--employee` /
  `--from` / `--to` / `--days=N` / `--batch=N`, `option()` helper and
  `Ctx::impersonate()` per employee, following `CloseDaysCommand`.
- `website/vayu` — registered `worked:rebuild` with a one-line description. `distance:rebuild`
  is left unclaimed for the other, still-undecided backfill.
- `website/api/support/Engine.php` — extracted `Engine::dayWorkedSeconds()` (public, read-only)
  out of `rollupDay()`; `rollupDay()` now calls it with the sessions it already fetched, so
  zero extra queries and **one** definition of the day's worked time. No behaviour change —
  `session-worked-time.php` and `worked-time-blast-radius.php` still pass untouched.
- `website/api/controllers/AttendanceController.php` — the `cache()` ruling (`09` §7.5):
  a past window now gets an **ETag + `private, no-cache`** instead of `max-age=86400`.
  New private `validator()` builds the tag from the covered rollup rows, the window's
  holidays, the org's weekend days and the subject/shape. **The falsified premise sentence
  is gone** — the docblock now says what is actually true (stable *until something
  re-derives it*) and lists the six things that re-derive a past day.
- `website/api/support/Envelope.php` — new `Envelope::REVALIDATE = 'private, no-cache'`,
  documented as "cache the body, check every time", for a response that is stable until
  something changes it rather than genuinely immutable.
- `website/tests/worked-rebuild.php` — NEW, 7 sections (A–G). Green.
- `website/tests/attendance-cache.php` — NEW, 7 sections (A–G), including a child-process
  check that a matching `If-None-Match` really does answer **304** with no body (needed
  because `Envelope::freshness()` exits on a hit). Green.

## The claim about `rollupDay` — verified, and one caveat found

Verified against the code before building: `Engine::rollupDay()` (`Engine.php:938`) reads
`work_sessions`, `location_fixes` (via `sessionLastFixes`), `company_visits`,
`visit_reports`, `stop_records`, `day_routes`, `tracking_configs`, `holidays`,
`organizations` — and **writes exactly one statement**, the `INSERT … ON CONFLICT` on
`attendance_days`. It does not re-derive distance: it sums the stored
`$session['distance_km']`. No path from it mutates `work_sessions.distance_km`,
`incomplete_distance_km`, `point_count`, stops, visits or route geometry. **The
justification holds.**

One caveat worth recording, because it is the only write hiding on that path:
`Ctx::config()` (`Ctx.php:152-178`) **INSERTs a `tracking_configs` row** when the org has
none. Not a distance write, but it would have broken "`--dry` writes nothing". The command
therefore checks for the row itself and **skips** an org without one, printing a line —
which also matches the blast-radius SQL, whose `JOIN tracking_configs` drops such an org.

## I went one step narrower than the brief, deliberately — please sanity-check this

The brief said a `rollupDay`-only backfill. I built a **`dayWorkedSeconds` + `classifyDay`,
two-column UPDATE** backfill instead, which is strictly narrower. Two reasons:

1. `rollupDay` rewrites the **whole** `attendance_days` row, including `distance_km`
   re-summed from the sessions. That re-sum normally reproduces the stored number, but it
   is still a distance write on a row the decision says to leave alone — and if a day's
   stored figure ever disagreed with its session sum, `rollupDay` would silently restate it.
2. `rollupDay`'s `stop_seconds` / `longest_stop_seconds` measure an **unclosed** stop
   against `$now` (`Engine.php:995`), so for a past day with a `departure IS NULL` stop,
   `rollupDay` is not time-stable: calling it twice writes two different numbers. A backfill
   that claimed to be idempotent while quietly growing `stop_seconds` would be lying.

The test pins that the narrowing costs nothing: section B asserts the written
`worked_seconds` and `status` are **identical** to what `Engine::rollupDay` then writes for
the same day. Both come from the same two Engine calls, so they cannot diverge by
construction. If you would rather it ran the whole `rollupDay`, it is a two-line change in
`apply()` — but `tests/worked-rebuild.php` section C will then fail, by design.

## The cross-check against `tools/blast-radius-worked-seconds.sql`

The summary prints the four names statement 1 reports — `days_examined`,
`days_that_move`, `hours_removed_total`, `days_present_to_partial` — so they can be read
side by side. **They can disagree by construction, in four ways, all printed or documented
in the command's own header so a difference is diagnosable:**

1. **Range.** The SQL has no lower bound; the command defaults to 30 days. Use
   `--from=2020-01-01` to compare like with like. The dry-run footer says so.
2. **The today boundary.** The SQL uses `CURDATE()` (the DB server's date, UTC on prod);
   the command uses each org's **own** local today, as `days:close` does. For Asia/Kolkata
   on a UTC server those differ between 18:30 and 24:00 UTC — one day at the window's end.
3. **Status.** The SQL predicts only `present → partial`. The command takes status from
   `classifyDay`, which also re-reads holidays and weekend days, so a holiday added after
   the fact can move a status for a non-worked-time reason. Those are counted and printed
   **separately** as `other status changes`; the SQL predicts 0, and a non-zero number is a
   real thing to look at.
4. **Net vs gross hours.** `hours_removed_total` is the SQL's `SUM(stored - evidence)`,
   i.e. net. The command also prints how many days went **up**, which a net sum hides.

## Logging: audit row, not a timeline event

One `audit_log` row per **changed** day — `entity = 'attendance_day'`,
`entity_id = '<employee-id>:<date>'`, `action = 'worked_rebuild'`, `before`/`after` carrying
both fields, `actor_id` **NULL** because no human did it (the column is nullable with an FK
to `users`, so a sentinel would not insert). Indexed by `idx_audit_entity (entity,
entity_id, occurred_at DESC)`, so "why did this day change" is one query.

**Deliberately NOT `Engine::logAlert`.** That writes an `activity_events` row, which is the
**employee's own timeline**. A burst of "your hours were recalculated" rows across months of
history would read as a fresh incident on every one of those days, to the person most
likely to dispute it. An admin-side correction belongs in the admin-side trail. The test
asserts `activity_events` is byte-identical after a run, so this stays a decision rather
than drifting.

## API contract impact

- `GET /api/v1/attendance` — **header-level change, no body/shape/status change for any
  current client.** A window ending before today was `Cache-Control: private,
  max-age=86400`; it is now `private, no-cache` + `ETag: "att-<md5>"`, and a request
  carrying a matching `If-None-Match` now gets **304** (it previously got 200 — attendance
  never called `Envelope::freshness`). Additive in practice: **nothing in either client
  sends `If-None-Match`** (zero occurrences in `mobile_app/lib/` and in
  `website/assets/admin/js/`), and `package:http` has no cache layer, so the Flutter app
  always re-fetches and always gets a 200. **Not breaking.**
- Honest limit of that fix: because the app never cached this response, `max-age=86400` was
  probably never honoured by the only live consumer. The ETag is the right durable answer
  and removes a false claim from the contract, but it is **not** what makes the backfill
  observable on mobile — the app was already re-fetching.
- `02-API-PLAN.md` §1.4 lists the cache values and will need `private, no-cache`
  added, plus the `GET /attendance` line changed from immutable to validator. **docs-agent's
  call, not mine — I did not edit `docs/`.**
- `Engine::dayWorkedSeconds()` is new and public, but `Engine` is internal support, not wire.
- No migration. No new column. No new route. No config field.

## Sequencing — the order in the brief, with one correction and two additions

The brief's order is right, with one change at the end:

1. Run `tools/blast-radius-worked-seconds.sql` on prod (read-only) → the prediction.
2. Deploy Phase 1 (`fce01e1`). **Correction: this is the step that matters most and the
   brief understates it.** Until Phase 1 is deployed, prod's `Engine` still computes worked
   time the old way, so **anything that re-derives a day in the meantime — a late fix
   batch, a reopen, `days:close` — rewrites it with the OLD number.** The prediction in
   step 1 decays until step 2 lands. Keep the gap short, or re-run step 1 after deploying.
3. `php vayu worked:rebuild --from=2020-01-01 --dry` and compare against the prediction.
   **Use `--from`, not the default**, or the counts legitimately differ (divergence 1).
4. Run for real in batches. **Suggested: `--days=30` first** (the current payroll month,
   the rows most likely to be looked at), confirm a re-run reports `days_that_move 0`, then
   widen with `--from`. `--batch=50` on prod if you want shorter transactions.
5. The cache header. **This one I would move EARLIER — before step 4, or ship it with
   step 2.** It is independent of the backfill and it is the thing that stops a corrected
   day being hidden behind a 24-hour-stale cached month. Deploying it after the burst means
   the burst happens under the header it was meant to fix. It carries no migration and no
   client change.

One thing not in the brief: **this deploy is not code-only in the sense that matters** —
`fce01e1` and this tranche are both plain code, but the working tree history on the board
says the server is still at `main@7c0de0b`. Everything between that and `fce01e1` ships
together. Decide that boundary before step 2; it is the same "sequencing is the first thing
to settle" item already on the board.

## Verification

- `php tests/worked-rebuild.php` and `php tests/attendance-cache.php` — green.
- **Every** PHP suite in `website/tests/` run individually, **exit code checked** (not
  grepped for "passed", because `app-release-publish.php` and `product-content.php` print
  descriptive headers): **27/27 exit 0**. `node tests/message-delivery-ui.js` exit 0.
- `php -l` clean on all seven touched/added files plus `vayu`.
- **Mutation-checked, four ways** — each mutant was applied, the suite failed on the
  intended assertion, and the file was restored:
  - `apply()` → `Engine::recompute` ⇒ *"work_sessions MUST be byte-identical"* fails.
  - `apply()` → `Engine::rollupDay` ⇒ *"attendance_days.distance_km MUST NOT move"* fails.
  - `--dry` write guard removed ⇒ *"--dry wrote to attendance_days"* fails.
  - today's boundary moved from yesterday to today ⇒ the examined-day count fails.
  - `cache()` reverted to `IMMUTABLE` ⇒ *"a past window must use the validator path"* fails.
  - `computed_at` dropped from the validator ⇒ *"a corrected past day MUST invalidate its
    own month"* fails.
- The byte-identity guard is section **C** of `tests/worked-rebuild.php` and is written to
  be hard to delete by accident: the fixture's stored day distance (41.4142) deliberately
  **disagrees** with the sum of its sessions (17.8321), the section carries a
  `*** DO NOT DELETE ***` banner, and the file header says "if section C fails, STOP".
- Not provable here, consistent with what the board already records for
  `tests/admin-session-guard.php`: **the literal `Cache-Control` / `ETag` headers on the
  wire.** `header()` is a no-op under CLI. The 304 short-circuit *is* proven (child
  process, `http_response_code()` read in a shutdown handler); the header bytes need a
  served instance. Worth one curl on prod after deploy:
  `curl -sI '…/api/v1/attendance?subject=me&month=<past>'`.

## Needs doing next

- [ ] **user** — the five-step sequence above. Step 1 is the only one that can be done now.
- [ ] **user** — the distance backfill is still **your open decision**. Nothing here touches
      it, `distance:rebuild` is still an unclaimed name, and the board's TODO for it is
      intact. The two are now genuinely separable: `work_sessions` is proven untouched.
- [ ] **docs-agent** — `02` §1.4 + the `GET /attendance` cache row (see API contract impact).
      `09` §7.5 can be marked **resolved: ETag**, and §7.6 resolved **for worked seconds
      only**, with the distance half explicitly still open.
- [ ] **web-agent** (me, later, not blocking) — `GET /statistics` and `GET /days` send no
      cache header at all, which is safe but means the attendance calendar is now the only
      one of the three that revalidates. Worth one pass for consistency once Phase 2 lands.
- [ ] **web-agent** — pre-existing, found while reading `rollupDay`: `stop_seconds` for a
      day with an unclosed `stop_records.departure` is measured against `$now`, so
      `rollupDay` is **not idempotent** on such a day. Harmless today (`days:close` caps
      departures), but it is why this command does not call `rollupDay`, and it will bite
      whoever builds the distance backfill on `recompute`. Decide whether an unclosed stop
      on a **past** day should clamp at the session end.
- [ ] **mobile-agent** — no action. Nothing the app reads changed shape, it sends no
      conditional headers, and 304 cannot reach it.

## Blocked on

- Nothing. Both deliverables are code-complete, tested and in the working tree.
- Not blocked, but stated so nobody re-discovers it: `--dry` on **production** is the only
  honest way to size the blast radius, and it needs a shell on the server. I have run it
  only against throwaway SQLite fixtures.

## Parallel work

None inside `website/`. `git status` shows **only** my seven paths dirty/untracked there
(4 modified, 3 new). `docs/03-DATABASE-SCHEMA.md` and `docs/AGENT-BOARD.md` were already
modified when I started — docs-agent's, untouched by me.

One stale thing for the drain: the board's web-agent row still says *"`HEAD` is `b817287`
and 102 paths are dirty or untracked"*. `HEAD` is now `fce01e1` and the working tree is
clean apart from the above, so migrations 032–036, `core/Secrets.php`, `core/messaging/`
and Phase 1 **are** committed. Several Contract-status rows still say "UNTRACKED IN GIT /
working tree only" and are now wrong about that (still right about "not deployed").
