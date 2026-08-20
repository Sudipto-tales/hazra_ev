# hazra-ev

Four folders, four agents, one board.

| Folder | Owner | What it is |
| --- | --- | --- |
| `website/` | `web-agent` | PHP app + `website/api/` — one API serving both the website and the mobile app |
| `mobile_app/` | `mobile-agent` | Flutter client |
| `html/`, `website/assets/` | `design-agent` | HTML/CSS/JS prototypes, styles, animation |
| `docs/` | `docs-agent` | Product docs + `AGENT-BOARD.md`, the shared state |

## Rules that hold across every agent

1. **Stay in your folder.** No agent writes outside its column above. A task that
   needs another folder is a handoff, not a reach-across — report it and stop.
2. **`docs/AGENT-BOARD.md` is the shared state.** Read it first. Only `docs-agent`
   writes it; everyone else drops one file in `docs/.inbox/` per finished task.
3. **The API is a contract.** `website/api/` has two consumers. `docs/02-API-PLAN.md`
   is authoritative; shape changes are breaking and must be reported before shipping.
4. **`website/docs/` is framework documentation. `docs/` is product documentation.**
   Different things — do not merge them.
5. Never commit `.env`.

Agent definitions live in `.claude/agents/`.
