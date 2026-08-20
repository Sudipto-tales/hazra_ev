# Agent board

The single shared surface for four agents working in parallel on this repo. Read this
first, every session. `docs-agent` is the only writer — everyone else reports through
`docs/.inbox/`.

## Ownership map

| Agent | Writes | Reads | Never touches |
| --- | --- | --- | --- |
| `web-agent` | `website/` — PHP app, `website/api/` (serves web **and** mobile), core, config, database | `mobile_app/`, `docs/`, `html/` | `mobile_app/`, `html/`, `website/assets/` |
| `mobile-agent` | `mobile_app/` — Flutter lib, test, android, pubspec | `website/api/`, `docs/` | `website/`, `html/` |
| `design-agent` | `html/`, `website/assets/{css,js,image}` | everything | PHP logic, Dart, `docs/` |
| `docs-agent` | `docs/` only | everything | all code |

No two agents share a writable path. That is what makes parallel runs safe.

The one seam that needs care: `website/api/` is written by `web-agent` and consumed by
`mobile-agent`. It is a contract, tracked under **Contract status** below.

## How reporting works

1. An agent finishes a task → writes ONE file to `docs/.inbox/<agent>-<timestamp>-<slug>.md`.
2. One file per task means concurrent agents never write the same file.
3. `docs-agent` drains the inbox, verifies each claim against the code, merges here,
   deletes the entry.

Nobody but `docs-agent` edits this file.

---

## 1. Status

| Agent | Folder | Current work | Last update |
| --- | --- | --- | --- |
| web-agent | `website/` | not started | 2026-08-18 (board created) |
| mobile-agent | `mobile_app/` | not started | 2026-08-18 (board created) |
| design-agent | `html/`, `website/assets/` | not started — `html/` is empty | 2026-08-18 (board created) |
| docs-agent | `docs/` | board created | 2026-08-18 |

## 2. Contract status

`docs/02-API-PLAN.md` is authoritative. This table tracks drift between what the API
actually serves and what the app codes against.

| Endpoint | Planned | Built by web-agent | Consumed by mobile-agent | Notes |
| --- | --- | --- | --- | --- |
| _(see 02-API-PLAN.md route table)_ | yes | `website/api/controllers/UserController.php` only | mocks in `lib/data/mock/` | Gateway exists; route table largely unbuilt |

The three standing rules (from `docs/README.md`) bind both sides:

1. Employee payloads carry no owner id — `?subject=me` resolves from the token.
2. Tracking thresholds are admin config, applied at read time, never rewriting stored fixes.
3. What the seller typed is what gets stored — free text stays free text; product name
   and colour are denormalised onto the sale line.

## 3. TODO

- [ ] `web-agent` — build out the `02-API-PLAN.md` route table beyond `UserController`
- [ ] `web-agent` — confirm auth/token resolution for `?subject=me`
- [ ] `mobile-agent` — list which repositories are still on `lib/data/mock/` and what each needs
- [ ] `design-agent` — `html/` is empty; establish design tokens and the first prototypes
- [ ] `design-agent` — audit `website/assets/css` against the tokens once they exist
- [ ] `docs-agent` — reconcile `docs/` (product/API) with `website/docs/` (framework docs); they are separate things and should say so

## 4. Blockers

| Who | Blocked on | Who clears it |
| --- | --- | --- |
| — | none recorded | — |

## 5. Change log

| Date | Agent | Change |
| --- | --- | --- |
| 2026-08-18 | docs-agent | Board created; ownership map and inbox protocol defined |

## 6. Discrepancies

Reports whose claims did not match the code. Empty is the goal.

| Date | Agent | Claim | What the code says |
| --- | --- | --- | --- |
| — | — | — | — |
