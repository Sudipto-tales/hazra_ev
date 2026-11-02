---
agent: design-agent
date: 2026-11-01T10:30Z
status: done
handoff-to: web-agent
---

## What changed
- `html/employee-manual.html` — filled all **14** empty `.manual-media` slots with
  illustrative, clearly-labelled mockups (no real captures). Slot identity kept:
  every figure retains its `manual-media--phone`/`--video` class, its `data-media="img/…"|"video/…"`
  attribute and its original `<figcaption>`, so a future real-capture pass still swaps them.
  Added `<link rel="stylesheet" href="styles/pages/manual-mockups.css">` after the existing
  manual CSS.
- `html/styles/pages/manual-mockups.css` — NEW shared mockup/animation kit (the `.mk*`
  namespace). Does not touch `employee-manual.css`.
- `website/assets/css/styles/pages/manual-mockups.css` — promoted copy, byte-identical
  to the prototype copy (kept in sync).

## The mockup kit (reusable — admin manual links the same file next)
Inside each phone frame a small on-brand app screen is rendered in HTML/CSS/SVG using the
APP's own tokens (lib/core/theme/app_colors.dart + dimens.dart) as CSS custom properties on
`.mk`. The app is shown in its LIGHT theme on every mock (a real screenshot would not invert
with the website); the surrounding page keeps the website brand tokens. Seeded demo data only
(name "Arindam Das", shop "Mohakhali EV Point", EMP-2041) — no real PII.

Key classes to reuse:
- `.mk` (app screen; carries the app palette), `.mk__status` / `.mk__appbar` / `.mk__body` / `.mk-nav`
- `.mk-hero` + `--ready|--working|--break|--ended` (the Home session card gradients)
- `.mk-btn` (`--white|--ghost|--primary|--danger|--outline`), `.mk-actions`
- `.mk-card`, `.mk-field`, `.mk-chip`, `.mk-tile(s)`, `.mk-kv`, `.mk-banner` (`--info|--warn|--success|--danger`)
- `.mk-sheet` + `.mk-sheet__scrim` (bottom-sheet screens), `.mk-dialog` + `.mk-map` (Android perm dialog),
  `.mk-lock` + `.mk-notif` (lockscreen notification), `.mk-role`, `.mk-stars`, `.mk-list`/`.mk-tilerow`
- Honesty: `.manual-media__tag` (corner "Mockup"/"Example walkthrough" pill) +
  `.manual-media__exmp` (italic "· example mockup" appended to the kept figcaption).
- Walkthrough: `.mk-stage` / `.mk-stage--3` / `.mk-stage__screens` / `.mk-stage__caps` / `.mk-stage__dots`
  — a pure-CSS 3-step reel (`@keyframes mk-reel`, ~9s loop), gated behind
  `prefers-reduced-motion: no-preference`; reduced motion freezes to step 1 (still frame).
  **No JS, no library, no mp4.**

## Slot status (all 14 now mockups; none awaiting a real asset to render)
Phones: role-select, login, permission-location (Android dialog), start-day-ready (Home/Ready),
session-notification (lockscreen — labelled "planned, not built yet"), create-report, report-photos
(picker sheet), on-break, closeout-sheet, tracking-status (sync queue, offline state), settings,
change-password. Videos: start-day.mp4 (Allow location → Start Day → Working) and
submit-report.mp4 (write → attach photos → submitted) as looping CSS walkthroughs.
All remain swappable for real captures later via their unchanged `data-media` path.

## Handoff
- Port the updated media markup from `html/employee-manual.html` into the PHP view
  (`website/app/.../employee-manual*.php`) and add the stylesheet link
  `styles/pages/manual-mockups.css` (promoted copy already at
  `website/assets/css/styles/pages/manual-mockups.css`) AFTER `employee-manual.css`.
  The existing `employee-manual.css` handoff is untouched.
- New dependency: **none** (vanilla CSS/SVG; icons use the lucide CDN already loaded on the page).

## Needs doing next
- [ ] web-agent: wire the markup + link into the PHP employee-manual view.
- [ ] design-agent (future): reuse the `.mk*` kit to mock the admin manual's slots.
- [ ] anyone (future): replace mocks with real device captures by dropping assets at each
      slot's `data-media` path and reverting that figure to an `<img>`/`<video>`.

## Verified
- Light + dark website themes (app mock stays light by design; frame bezel adapts).
- Phone + desktop widths; media-row wraps; no horizontal page scroll (`.mk` content is
  clipped inside each frame like a screenshot crop).
- `prefers-reduced-motion`: walkthroughs freeze to a representative first frame.
- Nothing claims to be a genuine screenshot — every slot carries a "Mockup"/"Example"
  tag and an italic figcaption footnote.

## Blocked on
- Nothing.
