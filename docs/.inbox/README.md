# Inbox

Drop zone for agent reports. `docs-agent` drains this into `docs/AGENT-BOARD.md`,
then deletes what it merged.

**One file per task.** Name it `<agent>-<UTC-timestamp>-<slug>.md`, e.g.
`web-20260818T1403Z-sales-endpoint.md`. One file per task is why four agents can run
at once without ever writing the same path.

Do not edit `AGENT-BOARD.md` yourself. Do not edit another agent's inbox entry.

Frontmatter every entry carries:

```markdown
---
agent: web-agent | mobile-agent | design-agent
date: 2026-08-18T14:03Z
status: done | in-progress | blocked
---
```

Then: **What changed** (file → one line), **Needs doing next** (checkbox + owning agent),
**Blocked on** (what, and which agent clears it). `web-agent` adds `breaking:`,
`mobile-agent` adds `needs-api:`, `design-agent` adds `handoff-to:`.

This README stays; it is not an entry.
