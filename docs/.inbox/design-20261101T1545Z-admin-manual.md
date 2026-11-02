---
agent: design-agent
date: 2026-11-01T15:45Z
status: done
handoff-to: web-agent
---

## What changed
- `html/admin-manual.html` — new page: the web admin-panel user manual, mirroring
  the canonical employee shell exactly (chrome, theme toggle, hero, sticky section
  index, lucide icons, reveal-on-scroll JS, media-placeholder system). 9 instructional
  sections with real copy grounded in `website/app/` and `website/api/`.
- `html/styles/pages/admin-manual.css` — a THIN layer on top of the shared shell.
  Adds only the `.manual-media--desktop` browser-frame slot (16:10 + faux toolbar +
  three brand dots) for desktop admin screenshots. Token-only; light + dark free.
- `website/assets/css/styles/pages/admin-manual.css` — identical promoted copy.
- `html/assets/manual/README.md` — updated to document both manuals (phone slots for
  employee, desktop slots prefixed `admin-` for admin) and the grep-the-slots command.

## CSS decision — SHARED, not renamed
- The admin page **reuses `pages/employee-manual.css` unchanged** as the shared shell
  (hero, TOC, sections, callouts, FAQ, `.manual-media` + `--phone`/`--video`). I did
  **not** rename it to `manual.css`: the employee inbox note already told `web-agent`
  the employee classes live in `employee-manual.css`, so renaming would break that
  pending handoff. Instead the admin page links `employee-manual.css` THEN a thin
  `admin-manual.css` on top for the one admin-only need (desktop frame).
- **Both copies are in sync** — verified `diff -q` clean for BOTH
  `employee-manual.css` and `admin-manual.css` across `html/styles/pages/` and
  `website/assets/css/styles/pages/`.
- No hardcoded hex; all values resolve through `tokens.css`. Light + dark, responsive
  single-column collapse at 960px, `prefers-reduced-motion` fallback — all inherited
  from the shared shell, so they hold on this page too.

## The 9 sections (real copy, grounded in the panel + API)
1. Signing in & first password — `/admin` sign-in card, admin-role/deactivated guards,
   the `must_change_password` forced first-login change (temp password shown once).
2. Dashboard & live team map — totals strip / lead inbox / quick actions, the live
   roster with working/idle/offline/location-unavailable statuses, SSE live feed with
   polling fallback, date picker, long-stop flag.
3. Employees — add (EMP-#### code, required fields, generated-or-typed password, temp
   shown once), reset password (signs out all devices), deactivate (reversible, keeps
   history), unique email/code conflicts.
4. Route history & stops — matched track, stops with arrival/departure, detected
   visits, team-or-individual, rules-driven derivation re-deriving open days only.
5. Report review — pending/approved/rejected filter, approve vs send-back-with-note,
   sidebar pending badge, decision recorded with reviewer + time, audit trail.
6. Product catalog — add/edit, specs/featured/hero images, published vs hidden,
   "View on site", note that the same catalog feeds the app's product picker.
7. Tracking rules — interval, min accuracy, stop radius/threshold, long-stop,
   movement speed, max jump, offline/location-unavailable thresholds, sync batch;
   read-time application, open-day re-derive, closed days untouched, guard rails.
8. Messaging & dealer locations — delivery statuses + retry failed; dealer editor with
   Google Maps search/pin, types, visibility (draft/published/inactive), publish reqs.
9. Troubleshooting & FAQ — `<details>` accordion, 8 common admin issues.

## Admin media-slot list (16 slots — `.manual-media--desktop` for stills, `--video`)
Grep `data-media=` on `html/admin-manual.html`. All assets live under
`html/assets/manual/` (desktop PNGs are 16:10, browser-frame; prefixed `admin-`):
- img/admin-login.png
- img/admin-first-password.png
- img/admin-dashboard.png
- img/admin-live-map.png
- video/admin-live-map.mp4
- img/admin-employees.png
- img/admin-employee-add.png
- img/admin-route-history.png
- video/admin-route-replay.mp4
- img/admin-report-queue.png
- img/admin-report-review.png
- img/admin-products.png
- img/admin-product-form.png
- img/admin-tracking-rules.png
- img/admin-message-delivery.png
- img/admin-dealer-locations.png
(14 desktop screenshots + 2 walkthrough videos.)

## Handoff
- `web-agent`: if/when this is ported into PHP, the shell classes live in
  `website/assets/css/styles/pages/employee-manual.css` and the admin-only desktop
  frame in `website/assets/css/styles/pages/admin-manual.css` — link both, in that
  order, plus `page-chrome.css` as the other pages do. Logo: `assets/hazraevLogo.jpg`.
- New dependency: none (lucide + existing shared stylesheets only).

## Needs doing next
- [ ] Capture pass — desktop admin screenshots on SEEDED data, light + dark, at a
      fixed browser viewport (Doc 08 Phase B). Drop into `html/assets/manual/{img,video}/`
      matching the `data-media` names above. Owner: whoever runs the capture step.
- [ ] To go live per slot: drop the file and swap `.manual-media__frame`'s inner for
      `<img>` / `<video poster=… preload="none">` (lazy-load videos). Owner: design-agent.

## Blocked on
- Nothing blocking. Media stays as placeholders until the capture step runs on seeded
  data. Note: the website admin panel (`website/app/`) today ships the CMS screens
  (products, dealer-locations, message-delivery, settings, etc.); the field-ops screens
  the API fully backs (live roster/stream, employees, routes, report review, tracking
  config) are documented here per Doc 08 and the API contract — if any field-ops screen
  is not yet mounted in the PHP UI, its section is forward-accurate and needs no copy
  change, only the screenshot when the screen is reachable.
