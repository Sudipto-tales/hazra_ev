---
agent: design-agent
date: 2026-11-01T14:30Z
status: done
handoff-to: web-agent
---

## What changed
- `html/admin-manual.html` — filled all **16** empty `.manual-media` slots
  (14 `--desktop` browser-frame stills + 2 `--video` walkthroughs) with illustrative,
  clearly-labelled mockups of the admin **web** panel. No real captures. Slot identity
  preserved on every figure: each keeps its `manual-media--desktop`/`--video` class and
  its `data-media="img/admin-…png"|"video/admin-…mp4"` path, with `manual-media--mock`
  added, so a future real-capture pass still swaps them. Added
  `<link rel="stylesheet" href="styles/pages/manual-mockups.css">` after `admin-manual.css`.
- `html/styles/pages/manual-mockups.css` — extended the shared kit with the new `.mka*`
  admin-desktop namespace (additive; the phone `.mk*` kit is untouched except two
  selector lists that now also drive `.mka` reel screens). Also added
  `.mka-omni` (faux browser omnibox) and `.mka-stage__win` (desktop walkthrough window).
- `website/assets/css/styles/pages/manual-mockups.css` — promoted copy, re-synced
  **byte-identical** to the prototype (`cmp` clean).

## Admin mock kit (`.mka*`, additive to `.mk*`)
Inside each `--desktop` browser frame a small on-brand admin web app is rendered in
HTML/CSS/SVG. Unlike the phone app (which carries the Flutter blue tokens), the admin
panel IS a website surface, so `.mka` carries the **website** brand hues (violet →
magenta → flame from `tokens.css`) pinned as a fixed LIGHT palette — the mock reads like
a capture even when the manual page is in dark mode; only the browser chrome (frame,
40px toolbar, dots, omnibox) follows the page tokens and adapts, exactly like the phone
bezel does on the employee page.

Key classes to reuse: `.mka` (screen + palette), `.mka-side`/`.mka-side__i(--on)`/`__ct`
(sidebar + red count badge), `.mka-main`/`.mka-top`/`.mka-canvas`, `.mka-stat(s)`,
`.mka-card`, `.mka-row2`/`.mka-col`, `.mka-table`/`.mka-tablewrap`, `.mka-who`/`.mka-av`,
`.mka-dot`/`.mka-badge` (status + decision + visibility tones), `.mka-roster`, `.mka-map`
+ `.mka-pin`(`--working|idle|offline|flag`)/`.mka-route`/`.mka-stop`(`--visit`)/`.mka-legend`/
`.mka-flagcard`, `.mka-form`/`.mka-label2`/`.mka-input`(`--focus|--area|--select`),
`.mka-btn`(`--primary|solid|ghost|green|red|amber|sm`), `.mka-banner`, `.mka-kv`,
`.mka-photos`/`.mka-photo`, `.mka-toggle`, `.mka-check`/`.mka-box`, `.mka-linked`,
`.mka--auth`/`.mka-auth` (centred sign-in / password card). Walkthroughs reuse the phone
kit's `mk-stage mk-stage--3` + `mk-reel` keyframes (~9s loop) with a wide
`.mka-stage__win` browser window instead of the phone; the reel driver now lists `.mka`
alongside `.mk`. **No JS, no library, no mp4.** `prefers-reduced-motion` freezes both
walkthroughs to their first representative frame (the existing gate already covers `.mka`).

## Grounding (seeded/demo data only — no real PII)
Field names, statuses and defaults are taken from `website/app`, `website/assets/admin/js`,
`docs/02-API-PLAN.md` and `docs/03-DATABASE-SCHEMA.md`:
- work statuses `working / idle / offline / location_unavailable`; long-stop flag; live
  counts (6 working / 2 idle / 2 long stops / 75% attendance from the API-plan example).
- employees: `EMP-####` codes, designation/department/region, forced-change flag
  `must_change_password`, temporary-password-shown-once.
- report decisions `pending / approved / rejected`; auto-attached session/GPS/timestamp.
- products: name, model_code, category, brand, featured, Published/Hidden, hero images.
- tracking rules with real DEFAULTS (interval 30 s, min accuracy 50 m, stop radius 75 m,
  stop 10 min, long-stop 45 min, movement 2 km/h, max jump 180 km/h, offline 10 min,
  location-unavailable 15 min, sync batch 50) and the real guard copy.
- message delivery statuses (Sending / Accepted by provider / Sent / Delivered / Read /
  Failed / Development log only) + Retry; dealer visibility Draft/Published/Inactive.

Honest about "intended design": the manual documents several admin field-ops screens
(Live team, Employees, Route history, Report review, Tracking rules) that are **not yet
built as PHP UI** — the real nav today is Dashboard/Products/Blogs/…/Message Delivery/
Dealer Locations (`website/assets/admin/js/core/nav.js`). The field-ops mocks therefore
show the INTENDED design and every slot carries the "Mockup" pill + "· example mockup"
footnote, so nothing reads as a real capture.

## Slot status (all 16 now mockups; none awaiting an asset to render)
Stills: admin-login, admin-first-password (both centred auth cards); admin-dashboard,
admin-live-map; admin-employees, admin-employee-add; admin-route-history;
admin-report-queue, admin-report-review; admin-products, admin-product-form;
admin-tracking-rules; admin-message-delivery, admin-dealer-locations.
Videos: admin-live-map.mp4 (team working → positions stream → long stop flagged) and
admin-route-replay.mp4 (open route → step stops → open a stop detail) as looping
CSS/SVG walkthroughs. All remain swappable for real captures via their unchanged
`data-media` path.

## Handoff
- Port the filled media markup from `html/admin-manual.html` into the PHP admin-manual
  view and add the stylesheet link `styles/pages/manual-mockups.css` AFTER
  `admin-manual.css` (promoted copy already at
  `website/assets/css/styles/pages/manual-mockups.css`). This is the same kit the
  employee-manual handoff already references — link once, reused by both pages.
- New dependency: **none** (vanilla CSS/SVG; icons use the lucide CDN already on the page).

## Needs doing next
- [ ] web-agent: wire the updated admin-manual media markup + the kit link into the PHP view.
- [ ] anyone (future): replace mocks with real browser captures by dropping assets at each
      slot's `data-media` path and reverting that figure to an `<img>`/`<video>`.
- [ ] design-agent (future, optional): once the field-ops admin screens ship as real PHP
      UI, re-shoot the five "intended design" slots and drop the "intended" framing.

## Verified
- Light + dark website themes (admin mock stays light by design; browser chrome + omnibox
  adapt via page tokens).
- Desktop + phone widths; `.mka` content clips inside each frame like a screenshot crop;
  no horizontal page scroll (every frame is `overflow:hidden`).
- `prefers-reduced-motion`: both walkthroughs freeze to their first frame.
- Tag balance: 14 "Mockup" pills + 2 "Example walkthrough" pills = 16; 16 "· example
  mockup" figcaption footnotes; 0 leftover placeholders; all 16 `data-media` slots preserved.
- Both `manual-mockups.css` copies `cmp`-identical; employee-manual page unaffected
  (changes are additive; it renders no `.mka`).

## Admin page path
`html/admin-manual.html`

## Blocked on
- Nothing.
