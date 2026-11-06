# 09 — Session and dark-time accounting

> ## STATUS: PHASE 1 IS CODE-COMPLETE AND NOT DEPLOYED. PHASES 2–5 ARE PLANNED.
>
> Three states, and they are not the same thing:
>
> | Phase | State as of 2026-11-06 |
> | --- | --- |
> | **1 — arithmetic** | **CODE-COMPLETE in the working tree, suites green, NOT committed, NOT deployed.** Written by `web-agent`, verified, reported in `web-20261106T0130Z-session-worked-time.md`, merged. §6 Phase 1 is now a record, not a proposal. |
> | 2 — config unification | PLANNED |
> | 3 — stale-session watchdog | PLANNED |
> | 4 — client backoff + last-evidence stamping | PLANNED |
> | 5 — `gaps[]` rendering + JUMP anchor | PLANNED |
>
> "Code-complete, not deployed" is a third state the board had no row for and
> this document originally had no word for. It is not "planned" — the code
> exists, the suite is green and `php -l` is clean. It is not "shipped" —
> production is still serving the wall-clock arithmetic this document calls
> Defect 1, and will keep doing so until `main` carries the change and a deploy
> runs.
>
> For Phases 2–5: no API described as PLANNED is servable today; no column
> described there exists; no client behaviour described there is in any released
> build.
>
> Written 2026-11-06 by `docs-agent`; §0 and §6 Phase 1 corrected the same day
> after Phase 1's report was drained. Phase owners: web-agent (1, 2, 3, 5 server
> half), mobile-agent (4, 5 client half).
>
> The defects in §2 and the gaps in §4 are verified against the code. Defect 1
> and the server half of Defect 2 are **fixed in the working tree** by Phase 1
> and are retained in the present tense because they describe **production**,
> which still has them. Everything under §3 is intent; §6 Phase 1 is a record and
> Phases 2–5 are intent.

---

## 0. Implementation status — read this first

Three things change how the rest of this document must be read.

**(a) Phase 1 is built. It is `web-agent`'s work, it has a suite, and it is in
the working tree only.**

An earlier revision of this section recorded Phase 1's code as an unexplained
"concurrent, uncommitted, unreported" change and told Phase 1's owner to
*"reconcile before building, do not build on top"*. **That attribution was
wrong and the instruction is withdrawn.** The change *was* Phase 1 — `web-agent`
was dispatched to implement it at the same moment `docs-agent` was dispatched to
write this spec, and `docs-agent` watched the edits land on disk mid-task without
having been told another agent was running. Refusing to treat unreported work as
verified was correct; the inference that a third party had written it was not.
There was never anything to reconcile.

`web-agent` was terminated by a rate limit at the moment it began the
fails-against-pre-fix-code check. Its work was already on disk and intact, and
the orchestrator session re-ran the verification and filed the report.

What exists, verified against the code on 2026-11-06:

| Thing | Where |
| --- | --- |
| `Engine::sessionLastFixes()` — last `recorded_at` per session for a day, one batched read per rollup | `website/api/support/Engine.php:793` |
| `Engine::sessionWorkedSeconds()` — the §3.5 rule verbatim, including the interior-counts / trailing-does-not asymmetry and the strict `>` on a closed session's tail | `:852` |
| `Engine::rollupDay()` summing through that helper instead of `Wire::seconds($session['started_at'], $session['ended_at'] ?? $now)`, with `darkSeconds` read from config per rollup and never stored | `:895-913` |
| `CloseDaysCommand::close()` stamping `min(last fix, local 23:59:59)` per open session, with `stop_records.departure` / `company_visits.departure` taking the **same** capped stamp, through a new `evidenceEnd()`/`dayEnd()` pair | `website/core/Console/CloseDaysCommand.php:217`, `:239`, `:272`, `:300`, `:322` |
| `tests/session-worked-time.php` — the suite the earlier revision said did not exist | `website/tests/session-worked-time.php` |
| `tools/blast-radius-worked-seconds.sql` — **read-only**, three `WITH … SELECT` statements, for the **user** to run on production. In `tools/` because that is where operational one-shots already live | `website/tools/blast-radius-worked-seconds.sql` |
| `tests/worked-time-blast-radius.php` — proves the script runs **and** that its `evidence_seconds` equals what `Engine::rollupDay` actually writes, on all four moving fixture days. Without that the script could be confidently wrong | `website/tests/worked-time-blast-radius.php` |
| `tests/account-deletion.php` — **collateral fix.** Its partial-migration fixture fatalled once `rollupDay` started reading config, and now requires `031` and `036` in production order | `website/tests/account-deletion.php:32-41` |

**A new read dependency, worth knowing on its own:** `Engine::rollupDay()` now
reads `Ctx::config()` for the first time, to get `offline_threshold_minutes`
(`Engine.php:904`). Guaranteed satisfied in production, but calling `rollupDay`
against a partial schema or an org with no `tracking_configs` row now fatals
where it used to work. That is what broke `account-deletion.php`, and it is a
real behaviour note, not just a test fixup.

Verified by `docs-agent` on 2026-11-06, not taken on report: `php
tests/session-worked-time.php` and `php tests/worked-time-blast-radius.php` both
pass; **every PHP suite in `website/tests/` exits 0** (24 files, of which 23 are
web-agent's — see (b) for the 24th); `php -l` is clean on all five touched files;
`node tests/message-delivery-ui.js` is green. `tests/dealer-location-picker.cjs`
still fails — pre-existing, design-agent's, already on the board. (Note for
anyone re-running: `tests/app-release-publish.php` and `tests/product-content.php`
print a descriptive header that a `grep` for "passed" misreads as a failure.
Both exit 0. **Check exit codes, not stdout.**)

**The revert experiment is DONE** — it had completed just before the rate-limit
kill. Against pre-fix behaviour **20 assertions fail**, the first being
`expected 12600 s, got 32400`, plus `present` where `partial` is required, plus
every `days:close` stamp landing at local `23:59:59` instead of last evidence.
The important half is what did **not** move: the interior-gap day still measures
18300 s and the 599 s and 600 s boundary tails pass **identically** before and
after, so the suite is discriminating on the thing that changed and is not
merely sensitive to the fixture. Files restored and md5-verified identical. The
suite also discriminates by construction — `:208-209` asserts a 32400 s (9 h)
wall-clock fixture yields `worked == 12600` (3.5 h) and the pre-fix expression
returns 32400, so it could never have passed against it — but that argument is
now a belt beside braces rather than the only evidence.

**What is still open on Phase 1** (and is not a doc problem):

- **The blast radius has a script and no number.** Phase 1 moves
  `worked_seconds` on already-closed historical days; nobody has counted how many
  stored `attendance_days` rows change, by how much, or how many flip `present` →
  `partial`. **The user asked for that number before deploying.** The deliverable
  is `tools/blast-radius-worked-seconds.sql`, which the user runs on production —
  this checkout has no prod access and the only local database has **0 rows in
  every tracking table**, so no number can be produced here. **Do not write a
  number into these docs until the script has been run.**
- Whether anything downstream has already cached or reported the old figure —
  partly answered, see §7.5: `AttendanceController` caches past windows for 24 h
  on a premise Phase 1 falsifies.

**(b) None of it is on `main` — and one file in `website/tests/` is not
web-agent's at all.**

`tests/attendance-stats.php` (untracked) and the modifications to
`api/controllers/StatisticsController.php` are **external in-flight work from a
separate interactive session**, not Phase 1 and not `web-agent`'s. Recorded here
only so nobody attributes them to Phase 1 or files them as another mystery
change — which is the mistake this section exists to correct. They are outside
this session's scope and are deliberately left alone; `Engine::rollupDay` is not
in their path. Both pass as of 2026-11-06 06:25Z. **Line numbers in
`StatisticsController.php` are unstable while that session runs** — cite the
method, not the line.

Phase 1's own two files are unstaged modifications and its three new files are
untracked. Phase 1's two files are unstaged modifications;
`tests/session-worked-time.php` is untracked. See (c) — this is the normal state
of this checkout right now, not a Phase 1 peculiarity.

**(c) The line numbers this record was drafted from are working-tree numbers,
not `main` numbers.** The whole universal-distance-engine tranche (migration
`036`, config v10, `Engine::chainDistanceV2`) is **untracked** in git — not on
`main`, not deployed — and it moves every line in `Engine.php` by several
hundred. §2 and §4 therefore give both anchors. Cite the committed one when
talking to anyone about production.

---

## 1. The core rule

> **`ended_at` is the last moment we have evidence for — never the clock at the
> moment of detection.**

Detection is an observation about *us*, not about the employee. A watchdog that
notices at 14:30 that nothing has arrived since 14:00 has learned something
about 14:00, not about 14:30. Stamping 14:30 — or worse, `23:59:59` — converts
our own latency into someone's working hours.

And the corollary, which is the non-obvious half and the reason this cannot be
implemented as "subtract all dark time":

> **An INTERIOR dark gap — one bracketed by fixes on both sides — COUNTS as
> worked time.** The employee was working; there was simply no signal.
>
> **A TRAILING dark tail — one no fix ever follows — does NOT count.** There is
> no evidence they were working.
>
> Dark time is only "worked" when it sits between two known-good fixes.

Three consequences worth stating, because each one rules out an implementation
that would otherwise look reasonable:

1. **Do not implement this by walking gaps and subtracting them.** A gap-walk
   has to decide, per gap, whether it is interior — and it has to not
   double-count, not drop the last one, and agree with whatever threshold the
   gap detector used. The rule is better encoded directly: worked time is
   `started_at → last evidence`, and an interior gap counts *by construction*
   because it lies inside that span and the span is never subdivided.
2. **The asymmetry is not arbitrary; it mirrors the burden of proof.** An
   interior gap has evidence on both sides, so the employee's presence across it
   is the only reading consistent with the data. A trailing tail has evidence on
   one side only, and "still working" is a guess.
3. **Time and distance go opposite ways across the same gap, and that is
   correct.** The distance engine sends the straight line across an interior gap
   to `incompleteKm` and never into the headline `distance_km`
   (`Engine::chainDistanceV2`, working tree `Engine.php:446-456`) — we do not
   know what route was driven. Worked *time* across that same gap counts in
   full. Not an inconsistency: we know they were there, we do not know where
   they went. Anyone reading a day will see hours that include the gap and
   kilometres that exclude it, and that pairing is the honest one. Say so in any
   UI that shows both.

---

## 2. Confirmed defects

Verified against the code. `main` = `b817287`; "tree" = the uncommitted
working tree described in §0.

### Defect 1 — `worked_seconds` is wall-clock, with no dark-time subtraction (HIGH)

> **FIXED by Phase 1 in the working tree (§0a); still PRESENT on `main` and in
> production.** Everything below describes what is deployed today.

`website/api/support/Engine.php:487` on `main` (tree: was `:792`, now replaced —
see §0):

```php
$worked += Wire::seconds($session['started_at'], $session['ended_at'] ?? $now);
```

For an **open** session this accrues against `$now`. A phone that dies at 14:00
keeps earning hours for as long as the session stays open, while the roster
beside it already reads `offline` (`Engine::activity` rule 3, tree
`Engine.php:1475` / `main:1062`). The roster and the timesheet disagree about the
same employee at the same instant, and the timesheet is the one that gets paid.

`CloseDaysCommand` then stamps `$workDate . ' 23:59:59'` in the org's own
timezone, so the phone that died at 14:00 books roughly ten phantom hours. And
`Engine::classifyDay()` (tree `Engine.php:882-902`) grades the day off
`FULL_DAY_SECONDS = 4 * 3600` (`:31`), so those phantom hours also flip the day
from `partial` to **`present`**. The defect is not confined to a number on a
report; it changes an attendance classification.

### Defect 2 — `ended_at` is stamped at detection, not at last evidence (HIGH)

> **The server half of this — `CloseDaysCommand` stamping a flat local midnight —
> is FIXED by Phase 1 in the working tree (§0a). The client half below is
> UNTOUCHED and is Phase 4's.**

`mobile_app/lib/state/tracking_controller.dart:700` (identical on `main` and in
the tree):

```dart
final DateTime end = endedAt ?? finalFix?.recordedAt ?? DateTime.now();
```

The fallback chain is right, and the auto-pause path defeats it: `:657` calls
`_closeSession(open, WorkStatus.paused, reason: SessionEndReason.auto)` passing
**neither** `endedAt` nor `finalFix`, so `end` falls through to `DateTime.now()`
— the detection moment, including all 30 dark minutes the watchdog just waited
out.

The uploaded close carries the same wrong value independently: `:659` sends
`_queueClose(open.id, reason: SessionEndReason.auto, endedAt: _now)`. So the
server is *told* the detection moment rather than inferring it. Note what this
means for the fix: **no API change is needed.** `PATCH /tracking/sessions/{id}`
already accepts `endedAt` and honours it (tree
`TrackingController.php:141` / `main:110`:
`Wire::ts((string) ($this->input('endedAt') ?? '')) ?? Wire::now()`). The wire is
already capable of last-evidence stamping; the client sends the wrong number
into it.

### Defect 3 — the server-side stale-session watchdog does not exist, and a docstring says it does (HIGH)

`mobile_app/lib/state/tracking_controller.dart:1174-1177`:

```
/// ... Only losing both leaves a
/// session that records nothing and can be told nothing — a fiction. The
/// server runs the same rule from its side ("no fixes received"), which is
/// what covers a device that is simply dead.
```

It does not. There is no stale-session sweep anywhere in `website/`: nothing
closes a session on "no fixes received", and the only thing that force-closes
anything is `CloseDaysCommand`, which runs at local midnight and is **not
installed on production** (board, carried since 2026-08-27). The sentence is a
false docstring, and it is load-bearing in the worst way — it is the stated
justification for the client's deliberately conservative `blind = GPS unusable
AND offline` condition (`:1185`). A reader checking whether the conservative
client rule is safe is told that a server rule covers the rest. Nothing does.

Record it as a defect in its own right: the docstring must either become true
(Phase 3) or be corrected (Phase 4), and whichever happens first must not leave
the other half saying the opposite.

---

## 3. Design decisions, with the reasoning

### 3.1 `offline` and `location_unavailable` stay split for rendering and are identical for arithmetic

`02-API-PLAN.md` §3.14 keeps them as separate values of the `activity` enum, and
keeps `2 before 3` in the precedence order: a revoked permission is not a network
failure. We know *why* a device with `permission_denied` is silent, and the
admin's next action differs — call the employee about permissions, not about
coverage. A known cause beats an inferred one, and the two timestamps that
distinguish them (`received_at` vs `recorded_at`) exist on `location_fixes`
precisely so the distinction is derivable (§3.14's note on `3 before 4`).

**For duration arithmetic the two are the same thing: no evidence.** A GPS that
is off and a radio that is off produce the identical hole in the record, and
worked time cannot be computed from the reason a fix is missing — only from
whether it is missing. One distinction for display, one for maths. Any
implementation that branches on `locationHealth` inside the worked-seconds
calculation is wrong; the calculation takes fixes and timestamps and nothing
else.

### 3.2 `offline` stays a read-time observation; `paused` is the written state

Do **not** promote `offline` to a stored state. The standing rule it would break
is board rule 2 — thresholds are admin config applied at read time and never
rewrite stored fixes — and the property that rule buys is the one `02` §3.11 and
§3.14 both lean on: retuning `offlineThresholdMinutes` reclassifies every past
and present response on the next read, touching no row. Storing `offline` freezes
one threshold into history, exactly as a stored `lowAccuracy` boolean would have
(the decision already recorded on the board for 2026-11-05).

`paused` is different and is correctly written, because it is the consequence of
an action: a session row gets an `ended_at`. The distinction to hold onto is
**observation versus event**. `offline` is us noticing nothing arrived. `paused`
is a session that ended. Only the second one is a fact about the employee's day.

### 3.3 The ladder

| Elapsed since last evidence | Reading | Written? |
| --- | --- | --- |
| 0–10 min | `working` / `idle` (movement decides) | no |
| ≥ 10 min (`offlineThresholdMinutes`, default 10) | `offline` | **no** — read-time only |
| ≥ 30 min (`autoCloseAfterMinutes`, client-side today) | auto-pause: session closed, day stays open | **yes** |
| local midnight | day closeout, `state = closed_by_system` | **yes** |

Defaults verified: `offline_threshold_minutes` = 10 and
`location_unavailable_threshold_minutes` = 15
(`website/database/migrations/002_tracking_config_tables.php:24-25`);
`autoCloseAfterMinutes` = 30
(`mobile_app/lib/core/config/tracking_config.dart:19`, a client constant — see
gap 4).

Each rung does strictly less damage than the next, and the ordering of the three
thresholds is an invariant, not a coincidence — see Phase 2.

### 3.4 Resume needs no new mechanism

Verified. `Engine::activity()` already returns `paused` when there is no open
session but `dayLock` still reads `open` (tree `Engine.php:1455` / `main:1042`),
and `dayLock` reads `open` whenever there is no closeout row or the last one was
reopened (`Engine::dayLock`, tree `:1250-1264`). Resume is therefore just a new
session: `TrackingController::startSession` allocates
`seq = COALESCE(MAX(seq), 0) + 1` per employee-day (tree `:81-82`).

So nothing in this plan needs a resume endpoint, a resume state or a
session-reopen path. **Do not build one.** Phase 3's watchdog reopening a
provisionally-closed session (§3.6) is a different operation on a different row
and must not be conflated with an employee resuming work.

### 3.5 Duration arithmetic, stated as the rule to implement

```
sessionWorked(session, lastFix, now, darkSeconds):
  lastFix == null                     → 0
  ended_at == null                    → min(started_at→lastFix, started_at→now)
  (lastFix → ended_at) >  darkSeconds → started_at→lastFix      # tail trimmed
  (lastFix → ended_at) <= darkSeconds → started_at→ended_at     # declaration stands
```

Four decisions inside those four lines:

- **No fixes at all → 0, not the wall-clock span.** A session with no evidence
  whatsoever is not a session anyone can be paid for. This is the one case where
  the rule produces a visibly smaller number than anyone might expect, and it is
  the intended answer.
- **An open session is capped at the last fix unconditionally, with no
  threshold.** `$now` is not evidence of anything. The `min()` against `$now` is
  there only to defend against a fix timestamped in the future by a skewed
  device clock.
- **A closed session's `ended_at` is a declaration and stands** — unless the
  tail from the last fix to it *exceeds* `darkSeconds`. A strict `>` so a tail
  landing exactly on the threshold is not trimmed and nothing moves by an
  off-by-one second. Short tails are normal (a session ends between two sync
  ticks); trimming them would make every honest day read a minute or two short,
  which is the fastest way to make employees distrust the whole number.
- **`darkSeconds` is read from config at rollup time, never stored.** Rule 2
  again: an admin who retunes `offlineThresholdMinutes` re-derives worked time on
  the next recompute rather than leaving a frozen decision behind.

`Wire::seconds()` floors at 0 (`website/api/support/Wire.php:72-79`), which this
rule relies on in one place: a fix recorded *after* `ended_at` reads as no tail
at all, and the declaration stands.

### 3.6 A server-side watchdog MUST be provisional and reversible

This is the decision most likely to be got wrong, so the reasoning first.

**The server cannot distinguish "offline but still queuing fixes locally" from
"dead".** Both look identical from the server: nothing arrives. The client's own
rule requires `blind = GPS unusable AND offline`
(`tracking_controller.dart:1185`) for exactly this reason, and its docstring at
`:1173-1174` spells it out: "Either condition alone is survivable and must not
trigger this: offline still queues fixes on the device." A device on a dead
network with working GPS is recording a perfect trace to
`LocationQueueStore` — up to ~41 hours of it (gap 10) — and will backfill every
fix with its true `recorded_at` on reconnect. A server watchdog that closes that
session *permanently* destroys a day of real work on the basis of a network
outage.

So the design is:

1. Close the session at **last-evidence time** with `end_reason =
   'stale_provisional'` — a marker meaning "we closed this because nothing
   arrived, not because anyone said so."
2. If a late batch arrives carrying fixes **after** that `ended_at`: **reopen,
   extend, and `Engine::recompute()`**.

Two properties already in the code make this safe, and they are the reason the
design is viable at all:

- **Replay is already idempotent.** `location_fixes` has `UNIQUE (employee_id,
  client_id)` and `Engine::ingest()` catches the `PDOException` and reports the
  fix as `DUPLICATE` rather than duplicating it (tree `Engine.php:104-109`). A
  late batch that overlaps what was already stored is reported, never
  double-counted.
- **`recompute()` is already idempotent and is already the complete entry
  point.** It re-derives every session in the day before stops, route and rollup
  (recorded on the board for 2026-11-05). Reopening a session and calling
  `recompute` yields the same numbers as if the watchdog had never fired.

What makes it *reversible* rather than merely re-runnable is that
`stale_provisional` is a distinguishable value. A session closed by an employee
must never be silently reopened by a late batch — that would overwrite a
declaration. Only `stale_provisional` is reopenable. Which means the marker is
not cosmetic; it is the permission check.

**This needs a new column, not a new enum value.** Verified: there is **no
`end_reason` column anywhere on the server** — zero occurrences across
`website/api/` and all 36 migrations — and `work_sessions`
(`website/database/migrations/004_session_location_tables.php:19-37`) has no
reason-ish field. `Present::session()` (tree `Present.php:180-205`) exposes no
`endReason`. The Dart `SessionEndReason { manual, pause, auto }`
(`mobile_app/lib/data/models/day_closeout.dart:39`) is **client-local and never
round-trips**. See gap 6.

### 3.7 Retry backoff

```
30 → 60 → 120 → 240 → 300   capped at syncIntervalSeconds
full jitter on every delay
reset to 30 on success
reset on connectivity-regained — not on the timer expiring
```

The cap at `syncIntervalSeconds` (default 300 s, `02` §3.12) rather than at some
new constant: there is already a number that means "how long is it acceptable for
the server not to hear from this device", and inventing a second one lets the two
drift.

The long cap is only safe **because of the connectivity-regained reset**. A 300 s
backoff with no reset means a device whose network returns one second after a
failed attempt sits silent for five more minutes for no reason — which is the
same latency bug the live-watch design already works around, and it would make
the backoff feel like a regression to anybody watching a device come back.
Resetting on the regain signal is what makes a long cap a battery saving rather
than a delay. **See §7.3 — the app does not currently have that signal, and this
is an open question, not a solved one.**

Full jitter (uniform over `[0, delay]`, not `delay ± 10 %`) because the failure
mode being defended against is correlated: a tower, a server restart or a
deploy takes the whole fleet down at once, and a fleet that backs off in lockstep
returns in lockstep. Fixed intervals are what produce the stampede (gap 5).

Error classes — one decision each, because "retry" is not one behaviour:

| Class | Behaviour | Why |
| --- | --- | --- |
| network error, timeout, 5xx | back off and retry | the request may succeed unchanged |
| 401 | refresh the token, retry **once**, then back off | the retry is a different request; retrying the same expired token is a loop |
| 409 / `DUPLICATE` | treat as **SUCCESS** | idempotency already did its job; the data is on the server. Retrying is the one thing that cannot help |
| 422 / `MALFORMED` | **never retried.** Park it, report `failed` | a malformed body is malformed on every attempt. Retrying is an infinite loop that also hides the bug |

One wire-shape correction to carry into the implementation: on
`POST /tracking/locations` these are **not HTTP statuses**. The endpoint returns
`200` with per-fix `rejected[]` entries carrying `reason`
(`JUMP` | `DUPLICATE` | `MALFORMED`, tree `Engine.php:78-109`); `422` is reserved
for envelope-level validation (`fixes` not a non-empty array, batch over
`syncBatchSize` — tree `TrackingController.php:182-193`) and `409` is never
returned by any `/tracking/*` route (`Envelope::conflict` exists at
`Envelope.php:81-84` and no tracking route calls it). So the table above applies
to **two** layers: HTTP status for the request, and `rejected[].reason` for
individual fixes inside a `200`. See §7 and gap 5 for what today's client already
does with each.

---

## 4. The ten gaps

Severity is about the size of the wrong number a human would act on, not about
how hard the fix is.

| # | Gap | Where | Sev | Phase |
| --- | --- | --- | --- | --- |
| 1 | **`worked_seconds` counts dark time.** Wall-clock `started_at → ended_at ?? now`, no dark-time subtraction; an open session accrues against the clock, and `classifyDay` then grades the inflated total against `FULL_DAY_SECONDS` | `Engine.php:487` (`main`); tree `:905-913` is mid-change — §0 | **high** | 1 |
| 2 | **`ended_at` is stamped at detection, not at evidence time.** `_closeSession`'s fallback chain is sound; the auto-pause caller passes neither argument, and the uploaded close sends `_now` | `tracking_controller.dart:700`, caller `:657`, upload `:659` | **high** | 4 |
| 3 | **No server-side stale-session watchdog exists, and a docstring asserts one does.** Nothing in `website/` closes a session on "no fixes received" | `tracking_controller.dart:1177` (the false claim); absence verified across `website/` | **high** | 3 |
| 4 | **`autoCloseAfterMinutes` is client-only.** Not in `ConfigController::FIELDS` (verified absent on `main` **and** in the tree), no column, so the server never serves it and every device falls back to its hard-coded 30. Client and server cannot agree on one number because only one of them has one | `tracking_config.dart:19` (the constant); `ConfigController.php:18-48` (the absence); `http_employee_repository.dart:84` documents the fallback | medium | 2 |
| 5 | **Fixed retry interval, no backoff and no jitter.** `outboxRetryInterval = const Duration(seconds: 30)`, armed as a bare `Timer.periodic`. The fix drain is likewise a flat `syncIntervalSeconds` with no jitter. On a fleet-wide recovery everyone returns in lockstep | `tracking_controller.dart:59`, timer `:1028`; drain `location_service.dart:578-586` | medium | 4 |
| 6 | **The server cannot tell auto-pause from a human pause — and in fact cannot tell `pause` from `end`.** `PATCH /tracking/sessions/{id}` validates `reason ∈ {pause, end}` and then **never uses `$reason` again**; both paths do the identical thing. The client's `auto` is flattened to `pause` before it leaves the device. No `end_reason` column exists | validation tree `TrackingController.php:128` / `main:97`; `$reason` unused after; flattening `tracking_controller.dart:658-659` + `tracking_repository.dart:283` | medium | 3 |
| 7 | **A night shift crossing midnight is truncated at `23:59:59`** and the remainder is orphaned against yesterday's `work_date`. `CloseDaysCommand` stamps the org-local end of `work_date`; nothing carries the remainder into the next day | `CloseDaysCommand::close()` (`$midnight`/`$endedAt`) | medium | 1 |
| 8 | **Within-session geometry gaps are chorded silently.** `MapMatch::build()` groups fixes by `seq` — per *session*, not per continuous stretch — and hands each group to OSRM whole, so an interior hole inside one session becomes a straight line on the map with nothing marking it | tree `MapMatch.php:270` / `main:272`; `gaps=ignore` at `:467`; `02` §3.12 | medium | 5 |
| 9 | **The `JUMP` filter's `$previous` anchor does not advance on rejection.** The `continue` precedes the `$previous = …` assignment, so after one real jump every subsequent fix is compared against the pre-jump position and rejected too, until elapsed time alone brings the implied speed under `maxJumpKmh` (~13 min for a 40 km displacement at the default). The filter widens the gap it is measuring | tree `Engine.php:89-92` / `main:78-81`; anchor advanced only at tree `:111` | low | 5 |
| 10 | **Queue overflow drops the oldest fix** at `queueCapacity = 5000` — about 41 h at the default interval. Counted, but not explained: `_failed++` flows into `SyncSnapshot.failed`, which Home renders as a bare number | drop `location_service.dart:1158-1161` and `:1184-1187`; cap `:379`, docstring `:404-407`; surfaced `tracking_sheet.dart:138` | low | 5 |

Two corrections to the framing these gaps were raised with, both verified:

- **Gap 4 is already on the board** as the open "config-defaults migration" task
  (web-agent). Phase 2 subsumes it; it should not be built twice.
- **Gap 10 is not silent.** The dropped fix increments `_failed`, which Home's
  tracking sheet shows in the danger colour when non-zero
  (`tracking_sheet.dart:138-141`). What is missing is not the count but the
  *explanation* — nothing tells the employee which fixes went or why. Severity
  stays low; the wording "silently" should not be carried forward.

---

## 5. A plain network outage produces NO geometry gap

Load-bearing, counterintuitive, and the thing most likely to be got wrong by
someone implementing gap rendering.

When the network drops, the device keeps capturing and queues every fix to
`LocationQueueStore`, durably, through a process kill — then backfills on
reconnect **with each fix's true `recorded_at`** (`Engine::ingest` takes
`recordedAt` from the payload, tree `Engine.php:83`). The trace is complete. It
arrived late; it is not missing. This is exactly why `MapMatch` passes
`gaps => 'ignore'` to OSRM (tree `MapMatch.php:445-467`): a tracking trace
legitimately has holes, and the matcher must not invent road geometry across
them.

Only four things make a **real** hole in the record:

1. **GPS off** — nothing was captured.
2. **A dead or killed phone** — nothing was captured.
3. **Queue overflow** — captured, then discarded (gap 10).
4. **A rejected fix** — captured and uploaded, then refused (`JUMP`,
   `MALFORMED`; gap 9 for why one rejection cascades).

Therefore: **a straight-line bridge over a network outage would double-count a
path that is already fully traced.** Any future `gaps[]` work must key on
**absent fixes**, never on absent uploads. The two are not the same event and
only one of them is a gap.

The in-tree distance engine already gets this right, and in a way worth copying
rather than re-deriving: a quiet stretch is only classified as a gap when
**something moved across it** — `elapsed >= gpsGapWarningSeconds` **and**
displacement greater than the accuracy-scaled step (tree
`Engine.php:434-456`). Elapsed time alone cannot separate a parked vehicle from
a tunnel, and the ~5-minute heartbeat means a parked device is quiet for longer
than any sane threshold. Displacement is what says whether distance is
unaccounted for.

Note the interaction with §1's corollary, because it is a genuine subtlety:
**gap detection is displacement-gated, worked-time is not.** A dark stretch where
the employee did not move produces no `session_gps_gaps` row at all — and still
counts, in full, as worked time, because it is interior to
`started_at → last evidence`. The two mechanisms answer different questions and
must not be wired to each other. This is the strongest argument for §3.5's
"never subdivide the span": a worked-time calculation built on gap rows would
inherit the displacement gate and silently stop counting stationary dark time.

---

## 6. The five phases

### Phase 1 — arithmetic only, no API change (gaps 1, 2 server-side, 7)

**Owner: web-agent. CODE-COMPLETE 2026-11-06, suites green, NOT committed, NOT
deployed. See §0(a) for the file-and-line record and what is still open.**

Built as specced, with no deviation found on verification:

- `worked_seconds` derived per §3.5, through one helper — `sessionWorkedSeconds()`
  — that `rollupDay()` reads and whose last-evidence source `sessionLastFixes()`
  `CloseDaysCommand::evidenceEnd()` mirrors, so a session's stamped end and the
  worked time derived from it cannot disagree. Two definitions of "last evidence"
  was the failure mode and it was avoided.
- `CloseDaysCommand` stamps each open session at `min(last fix, local
  23:59:59)`, and `day_closeouts.ended_at` follows the sessions rather than being
  hardcoded to midnight. **One thing the spec did not ask for and the
  implementation added:** `stop_records.departure` and
  `company_visits.departure` take the same capped stamp, so a stop cannot outlive
  the session it belongs to. Correct, and worth knowing it is in the blast
  radius.
- **Gap 7 (night shift) was NOT decided** and is explicitly out of the scope that
  was dispatched. It remains open: either the post-midnight remainder is carried
  into the next `work_date` as a new session, or midnight truncation is recorded
  as accepted behaviour with its consequence stated. Do not leave it undecided —
  a truncation nobody chose reads as a bug forever.

**No wire shape changes.** Same fields, same types, same keys. `worked_seconds`
and the `ended_at` values change. This is the identical not-breaking framing the
distance gates used and the board already accepted: **same shape, corrected
value.** A day whose device went dark and never came back will report fewer
hours, and any test or copy asserting "hours keep growing while the phone is
dead" was asserting the bug.

Two consequences to state plainly, because they differ from each other:

- **The `rollupDay` fix takes effect immediately on deploy.** `recompute()` runs
  on every accepted batch, so every open and recently-touched day re-derives on
  its next ingest.
- **The midnight stamping is latent until ops installs the cron.** `days:close`
  is **not installed on production** (board, since 2026-08-27), so
  `closedBySystem` is never emitted and nothing stamps anything at midnight
  today. Phase 1 makes that command correct; it does not make it run.
- **Neither is retroactive, and that is a decision rather than a detail — see
  §7.6.** `rollupDay` rewrites a row only when something re-derives the day: an
  accepted fix batch, a report submit, a closeout, a reopen, a config change,
  `days:close`, or an explicit backfill. A closed historical day therefore keeps
  its inflated `worked_seconds` **indefinitely**, which means a `present` →
  `partial` flip can surface weeks later, the first time that day happens to be
  reopened for an unrelated reason. All-at-once or never are both defensible; a
  drip is the one option nobody chose on purpose.

Three further findings from building it, which are not consequences of the
deploy but facts about the system that Phase 1 made load-bearing:

- **`day_closeouts` does not snapshot worked time — so nothing an employee
  signed moves.** It freezes `measured_distance_km` and `measured_visits` and
  nothing else (`009_day_closeouts.php:43-44`). This is the sharpest difference
  from the distance-backfill question, where a backfill moves one side of a
  comparison an employee has already signed. Here there is no signed declaration
  to contradict. Only two readers of `worked_seconds` exist —
  `Present::daySummary` (`Present.php:109`, as `workedDuration`) and
  `StatisticsController`'s rollup loop — and statistics is computed at read time
  with no cache header, so a corrected day also moves `averageWorkedDuration`
  and the `present`/`partial` counters the moment it is recomputed.
- **`end_time` and `joining_time` are deliberately untouched.** They are the
  **declaration** — `rollupDay` still takes `min(started_at)` and
  `max(ended_at)` across the day's sessions (`Engine.php:919-924`). So a day can
  legitimately read "joined 09:00, ended 18:00" with **3.5 h worked**, and that
  is honest: one pair is what was declared, the other is what there is evidence
  for. But the two numbers now disagree *by design* and nothing on any screen
  explains why. **That is a copy/UI question for `design-agent`**, and it is
  exactly the kind of unexplained discrepancy that generates a support ticket if
  it ships silently.
- **The trim uses a strict `>` on the threshold, which is one second out of step
  with `liveStatus`.** A tail of exactly `offlineThresholdMinutes` is **not**
  trimmed (`Engine::sessionWorkedSeconds`, pinned by the 599/600/601 trio at
  `tests/session-worked-time.php:310-314`), while the `offline` **display**
  verdict uses `>=` (`Engine.php:1469` and `:1596`). Both claim to implement "the
  offline threshold" and they differ at exactly one value. Deliberate and
  documented — §3.5's reasoning is that short tails are normal and trimming them
  makes every honest day read short — but `web-agent` flagged it for challenge
  rather than leaving it to be discovered, and it is worth either aligning or
  renaming one of the two so they stop looking like the same rule.

**One unresolved product decision came out of building it — see §7.4.** An
**open** session now caps at its last fix with no threshold grace, so a perfectly
healthy in-progress day reports worked time up to one `syncIntervalSeconds`
(300 s) behind wall clock.

### Phase 2 — config unification (gap 4)

**Owner: web-agent. Depends on nothing. Blocks Phase 3.**

Add two fields, as columns in `tracking_configs` and entries in
`ConfigController::FIELDS`:

| Field | Default | Meaning |
| --- | --- | --- |
| `autoCloseAfterMinutes` | 30 | Client auto-pause threshold. Exists as a client constant today and is served by nobody |
| `staleCloseAfterMinutes` | > 30 | Phase 3's server watchdog threshold. Must be strictly greater than the client's, so the device that is still alive always closes its own session first |

**The ordering invariant:**

```
staleCloseAfterMinutes > autoCloseAfterMinutes > offlineThresholdMinutes
```

Enforced the way `syncIntervalSeconds <= offlineThresholdMinutes * 30` already is
(tree `ConfigController.php:164-173`): a **422 on the field**, judged against
**post-write** values so a PUT moving both sides of a pair is evaluated on what
it is about to become rather than on half of it — the `$effective()` closure at
tree `:105-115` is the existing idiom and should be reused, not reinvented. Plus
a **DB CHECK as the backstop**, exactly as
`CHECK (sync_interval_seconds <= offline_threshold_minutes * 30)` is today.

Why an invariant and not a convention: if `staleClose <= autoClose`, the server
closes sessions out from under devices that were about to close them themselves,
and every one of those closes is a `stale_provisional` that then has to be
reopened by the client's own close arriving a minute later. The ordering is what
keeps the server's watchdog a backstop instead of a competitor.

**Unresolved — see §7.1:** whether these fold into the unshipped v10 or land as
v11.

### Phase 3 — server-side stale-session watchdog (gap 3, gap 6)

**Owner: web-agent. Depends on Phase 2 for its threshold.**

- Close at **last-evidence time** with `end_reason = 'stale_provisional'`
  (new column — §3.6).
- Reopen, extend and `recompute()` on a late batch carrying fixes after that
  `ended_at`. Only `stale_provisional` is reopenable.
- `end_reason` persists what the client already sends and the server currently
  discards (gap 6): `manual` / `pause` / `auto` / `stale_provisional`. This is
  the evidence anyone would want in an hours dispute, and today it does not exist
  on either side of the wire — the client's enum never leaves the device.
- Makes `tracking_controller.dart:1177`'s docstring true. Report it so
  mobile-agent can stop treating that line as a known falsehood.

**Subsumes the dangling-outage item raised by the offline-replaces-weak-signal
work (reported 2026-11-05T19:07Z, drained 2026-11-06): "a gap only closes when
the device comes back and says so … the midnight sweeper (`CloseDaysCommand`) is
the natural place to close a dangling outage."** That is the same problem from
the activity-timeline side: an `Offline` row written by `Engine::logAlert` with
`is_alert = 1` and never closed by the matching `Engine::logRecovery` `Back
online` row, because the only thing that calls `logRecovery` is the device
reporting a non-outage health value — which a dead phone never does.

Phase 3 subsumes it. A watchdog that knows a session's last evidence is exactly
what can close the dangling outage row. **Build it once, and stamp both at the
same timestamp:** the session's `ended_at` and the `Back online` row's `occurred_at`
must come from the same last-evidence value, read through the same helper
(`Engine::sessionLastFixes`, which Phase 1 already added), or the timeline and
the timesheet will disagree about when the employee stopped — and they will be
read side by side, on the same day view, in exactly the dispute this is for.

### Phase 4 — client backoff and last-evidence stamping (gaps 2, 5, 6)

**Owner: mobile-agent. Independent of Phases 1–3 but pointless to ship before
Phase 1**, since the server would recompute over whatever the client sends.

- `_closeSession` and `_queueClose` on the auto-pause path stamp the **last
  fix's `recordedAt`**, not `_now`. No API change needed — `endedAt` is already
  accepted and honoured (defect 2).
- Backoff per §3.7, replacing `outboxRetryInterval`'s flat 30 s and the
  unjittered fix drain.
- Send `auto` distinguishably instead of flattening it to `pause`, once Phase 3
  has somewhere to put it.
- Correct or delete the false half of `:1177`'s docstring depending on whether
  Phase 3 has landed.

### Phase 5 — `gaps[]` rendering and the JUMP anchor decision (gaps 8, 9, 10)

**Owner: web-agent (payload) + mobile-agent (rendering).** Last on purpose:
it is the only phase that is purely about what a human sees, and it depends on
the arithmetic underneath being right first.

- Expose gaps on the route payload. The server already **measures and stores**
  them — `session_gps_gaps`, one row per non-normal stretch, written by
  `Engine::storeGaps()` (tree `:207-220`) — and exposes **nothing**: no `gaps[]`
  on any route or session payload, only the aggregates
  `incompleteDistanceKm` / `gpsCoveragePercent` (tree `Present.php:201-203`).
  Phase 5 is largely a presentation layer over data that already exists, which is
  worth knowing before it is scoped as a large piece of work.
- Render gaps rather than chording them (gap 8). §5 is the constraint: key on
  absent fixes, never on absent uploads.
- **Decide** the JUMP anchor (gap 9): advance `$previous` on rejection, or keep
  the hold and accept the cascade. Both are defensible — advancing trusts a
  single outlier enough to measure from it; holding widens the gap but never
  measures from a fix it rejected. It is a decision, not a bug fix, and it should
  be recorded as one.
- Explain overflow drops rather than only counting them (gap 10).

---

## 7. Six things this record does not resolve

Flagged rather than guessed. §7.1–7.3 each need a decision before their phase
starts. §7.4–7.6 came out of Phase 1 **after** it was built and all three need a
decision before Phase 1 deploys.

### 7.1 Does Phase 2 fold into v10, or land as v11?

**The facts, verified.** Config v10 exists only in the **working tree**:
`036_universal_distance_engine.php` is **untracked in git** — not committed, not
on `main`, not deployed — and it floors `tracking_configs.version` to 10
(`:229-237`). Its five fields are already in `ConfigController::FIELDS`
(`:33-47`). Production serves **v9** (deployed 2026-11-04T18:20:03Z). The board's
own framing of v10 as "on `main`, NOT deployed" is **wrong on the first half**;
see the Discrepancies row added 2026-11-06.

**The consequence for held ETags.** `version` backs the ETag
(`ConfigController.php:63-68`), so any bump invalidates every ETag every device
holds and costs the fleet exactly one full `GET /config` each.

- **Fold into v10** — zero additional invalidations. v10's floor already forces
  every v9 device to re-pull once; adding two fields to that same bump is free.
  One migration, one deploy.
- **Land as v11** — a second fleet-wide re-pull, a second migration, and a
  window in which a device can see v10 *without* `autoCloseAfterMinutes` and
  fall back to its hard-coded 30. That is today's bug surviving one more
  release.

**The recommendation, with its own test.** Fold into v10 **if and only if** 036
is still uncommitted-and-undeployed when Phase 2 starts. The point of no return
is a production `db:sync` reporting `migrated 036_universal_distance_engine`;
after that the ledger keys on the filename and a retarget under the same name is
**silently skipped** — the exact trap `02` §3.12 documents for the v8 → v9
rename. After that moment it must be v11 (and a new filename), whatever the
ETag cost.

### 7.2 Phase 1's two halves land at different times, and that is not a choice

Already stated under Phase 1 and repeated here because it is the thing ops will
ask about: the `rollupDay` fix is live on deploy, the midnight stamping is latent
until `days:close` is installed on production (a web-agent/ops carry-over since
2026-08-27). Between those two moments, a day whose phone died reports honest
hours while its session stays open with a `NULL` `ended_at` — correct, but it
means **nobody should read the absence of midnight stamping as Phase 1 having
failed.** Say which half is being tested before testing it.

### 7.3 "Reset on connectivity-regained" requires a signal the app does not have

**This contradicts §3.7 and needs resolving in Phase 4.** There is no
connectivity plugin in `mobile_app/pubspec.yaml` — no `connectivity_plus`,
nothing equivalent — and `_isOnline` is derived from
`_snapshot.sync.isOnline` (`tracking_controller.dart:262`), which is set by
`GeoLocationService.flushQueue()` only as a **result of attempting an upload**
(`location_service.dart:1275-1286`: any thrown exception → `_online = false`;
reaching the server at all → `_online = true`). The device learns it is online
**by trying**, which is precisely the thing a long backoff stops it doing.

Three ways out, none free, all needing a decision:

1. Add `connectivity_plus` and listen for the OS event. A new dependency, and OS
   connectivity events are notoriously "connected to a captive portal" liars —
   so it is a hint that triggers an attempt, not proof.
2. Keep a cheap floor under the backoff (never exceed, say, 60 s) so the
   discover-by-trying loop stays alive. Loses most of the battery benefit a
   300 s cap was for.
3. Reset on any *other* successful call — health ping, config read, token
   refresh — since those prove reachability without the fix batch. Free, and
   partial: a device with nothing else to say stays dark.

Until this is decided, **the `30 → 60 → 120 → 240 → 300` ladder in §3.7 should
not be implemented with its full cap.** The cap is only safe with the reset, and
the reset does not exist yet.

---

### 7.4 Should an **open** session get threshold grace, or cap hard at its last fix?

**Raised by Phase 1, 2026-11-06. The user has not ruled on it. Phase 1 shipped
the stricter reading because that is what §3.5 states, not because the question
was settled.**

As built, `sessionWorkedSeconds()` caps an open session at `min(started_at →
lastFix, started_at → now)` with **no threshold grace** — unlike a *closed*
session, whose declared `ended_at` stands unless the tail exceeds
`offlineThresholdMinutes`. The consequence is arithmetic and unavoidable: the
device uploads on a `syncIntervalSeconds` tick (300 s today), so a perfectly
healthy in-progress day reports worked time up to **five minutes behind wall
clock**, sawtoothing back to zero lag on each batch.

- **For a timesheet this is correct and defensible.** It is exactly "the last
  moment we have evidence for", and it is the only reading under which a dead
  phone cannot accrue hours. Nothing about the number ever has to be explained
  away.
- **On a live roster it may read as a stalled clock.** An admin watching a
  working employee sees a figure that is always a few minutes stale and
  occasionally jumps. That is a presentation problem caused by a correct number.

**The alternative, if the live reading matters:** let an open session run to
`$now` while its last fix is *within* the dark threshold, and cap at the last fix
only once it is not. That restores a smooth live clock and keeps the dead-phone
fix, at the cost of symmetry with the closed-session rule and of worked time that
is briefly optimistic by up to `offlineThresholdMinutes`.

**Do not resolve this by shortening `syncIntervalSeconds`.** That trades the lag
for battery and call volume and leaves the asymmetry in place. It is also the
lever the universal-distance work deliberately left alone.

If the answer is the alternative, it is a change to `sessionWorkedSeconds()`
alone plus one more case in `tests/session-worked-time.php`. Nothing else moves.

### 7.5 `AttendanceController` caches past windows for 24 h on a premise Phase 1 falsifies

**Found while building Phase 1, 2026-11-06. Needs a ruling before deploy.**

`AttendanceController::cache()` sends `Cache-Control: private, max-age=86400`
(`Envelope::IMMUTABLE`) for any window ending before today, justified by its own
comment: *"A window that ends before today can no longer change."*

**Phase 1 makes that false.** A past day's `status` can now move — `present` →
`partial` is the whole point of the fix — and because Phase 1 is non-retroactive
(§7.6) the move happens at an arbitrary later moment, whenever that day is
re-derived. A client that fetched the month on Monday can show a stale
attendance verdict for up to 24 hours after the correction lands.

Scope is narrow and worth stating: **`GET /days` and `GET /statistics` send no
cache header and are unaffected.** This is the attendance calendar and the team
matrix only.

Three options, none obviously right:

- **Leave it.** A day-old attendance verdict is a small error and the window
  self-heals. Cheapest, and dishonest in exactly the way the comment is.
- **Drop to `no-store` for past windows during a backfill, restore after.** Keeps
  the cache for the steady state and pays only while numbers are actually moving.
  Requires knowing when a backfill is running.
- **Replace `max-age` with an ETag** over the rollup rows the window covers, so a
  corrected day invalidates its own month. Correct, and the most work.

At minimum **the comment must change**, because it is now the reason someone
would reintroduce the bug.

### 7.6 All-at-once or never: the worked-seconds backfill, and whether it is one question with the distance backfill

**Phase 1 is non-retroactive and the board already carries the identical
question for distance. Record them as the same decision.**

A closed historical day keeps its inflated `worked_seconds` until something
re-derives it, which may be never, or may be weeks from now when someone reopens
that day for an unrelated reason. So the realistic choices are:

- **All at once** — a backfill over `Engine::recompute`, the same shape as the
  `vayu distance:rebuild --days/--employee/--dry` already proposed on the board.
  Both would be the same command with the same risk profile, and building one
  without the other means doing this twice.
- **Never** — accept that history is what it was recorded as, and that the fix
  applies from the deploy forward.

**A drip is the option to avoid**, and it is the default if nobody decides: days
flipping `present` → `partial` one at a time, in no order anyone can predict,
each one looking like a fresh bug to whoever notices it.

**One thing makes this easier than the distance version**, and it is worth
weighing: as recorded in §6 Phase 1, `day_closeouts` does **not** snapshot worked
time. The distance backfill moves `attendance_days.distance_km` against a frozen
`day_closeouts.measured_distance_km` an employee signed; a worked-seconds
backfill has no signed counterpart to contradict. The arguments against the two
backfills are therefore **not** the same strength, even though the mechanism is
identical — which is a reason to decide them together rather than assume one
answer covers both.

---

## 8. What this changes on the contract

Summarised in `02-API-PLAN.md` §3.16. Items 1 and 2 are PLANNED; item 3 is
**built and not deployed**. Three items:

1. **Two new config fields** — `autoCloseAfterMinutes`, `staleCloseAfterMinutes`
   — with the ordering invariant and its 422 (Phase 2).
2. **A new `end_reason` field** carrying a new `stale_provisional` value, on a
   **new column** (Phase 3). Not a new value on an existing enum: no
   `end_reason` exists on the server today.
3. **Phase 1 changes `worked_seconds` VALUES without changing the wire shape.**
   Same shape, corrected value — the framing the distance gates established and
   the board accepted. Not breaking, and not invisible: consumers that assert on
   specific hour totals for dark days will see different numbers. **Built
   2026-11-06, in the working tree only, not deployed** — so the values on the
   wire have not moved yet. Verified end to end: `Session.workedSeconds` /
   `DaySummary.workedSeconds` keep their key, type and nullability, and no
   presenter signature changed.

## 9. How to tell this design is wrong

Written down so the next reader has something to check rather than a document to
agree with.

- If an interior dark gap ever stops counting, §3.5's span was subdivided
  somewhere. Look for a gap-walk.
- If a worked-time figure ever depends on *why* a device was silent, §3.1's
  display/maths split has leaked.
- If retuning `offlineThresholdMinutes` leaves an old day's hours unchanged
  after a recompute, something stored a threshold decision and broke rule 2.
- If a late backfill batch ever fails to restore a provisionally-closed
  session's hours, either the reopen path or `recompute`'s idempotency is not
  what §3.6 claims.
- If an employee's own `ended_at` is ever overwritten by a server sweep, the
  `stale_provisional` permission check in §3.6 is not being honoured.
