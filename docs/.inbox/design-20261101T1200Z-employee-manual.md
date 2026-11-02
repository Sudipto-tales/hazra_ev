---
agent: design-agent
date: 2026-11-01T12:00Z
status: done
handoff-to: web-agent
---

## What changed
- `html/employee-manual.html` — new page: the canonical, on-brand employee app
  user manual. Sticky on-page section index (desktop sidebar / mobile horizontal
  strip) + 9 instructional sections with real copy grounded in `mobile_app/lib`.
- `html/styles/pages/employee-manual.css` — the reusable help-page shell
  (hero, sticky TOC, section rhythm, callouts, media slots, FAQ accordion).
  Token-only; light + dark both resolve from `tokens.css`.
- `website/assets/css/styles/pages/employee-manual.css` — identical promoted copy
  so the live site and the prototype read the same source.
- `html/assets/manual/{img,video}/` — created (empty) with a `README.md`
  describing the capture convention.

Chrome/pattern mirrors `html/battery-use.html`: `page-chrome.css` + a page
stylesheet, the `.header` / `.hero` / `.footer` chrome, `#theme` toggle wired by
`script.js`, lucide icons. No new runtime dependency.

## The 9 sections (all real copy, grounded in the app)
1. Install & sign in — download, Employee vs Restricted roles, placeholder-only
   login fields, the "use Restricted" guard for admin accounts.
2. Permissions & Start Day — location (allow all the time), notifications note,
   the Start Day flow + the three block reasons (location off / offline / locked).
3. The work-session notification — **rendered "Coming soon"** (styled badge +
   dashed panel). Describes intended behaviour from `docs/07-ANDROID-SESSION-NOTIFICATION.md`
   (Working / On break / Offline states; Break=pause, Resume=new session,
   End Day=opens closeout; Android-only; no extra battery).
4. Logging visits & submitting reports — detected visits, the report form
   sections, photos (camera/gallery, ≤8 MB, upload-after-file + retry), auto-attached
   session/GPS/timestamp, offline-draft.
5. Breaks & pauses — Break closes the session, Resume opens a new one, day stays open.
6. End of day / closeout — online + GPS gate, the declaration sheet, "closing is
   final", auto-closed-session note.
7. Weak signal & offline — queued fixes, low-accuracy points kept, report drafts,
   tracking-status sheet + Sync now, high-accuracy / mobile-data settings.
8. Profile, settings & password — what's editable, change-password rules (current
   pw authorises, ≥8 chars, signs out other devices, admin-reset only).
9. Troubleshooting & FAQ — `<details>` accordion, 7 common issues.

## Media-slot convention (for the capture pass AND the admin build)
Every future screenshot/video is a styled placeholder, easy to find and replace:
- Class `.manual-media` plus a variant:
  - `.manual-media--phone` — phone frame, 9:19.5, for app screenshots.
  - `.manual-media--video` — 16:9 frame, for walkthrough videos.
- A `data-media="img/<name>.png"` (or `video/<name>.mp4`) attribute names the asset,
  **relative to `html/assets/manual/`**. Grep `data-media=` to list every slot.
- Markup per slot: `figure.manual-media[.--phone|.--video] > .manual-media__frame >
  .manual-media__screen` (glyph + type label + name) and a `figcaption.manual-media__cap`.
- To go live: drop the file at `html/assets/manual/<data-media value>` and replace the
  `.manual-media__frame` inner with `<img>` / `<video poster=… preload="none">`
  (lazy-load videos per Doc 08).
- 14 slots exist today: role-select, login, permission-location, start-day-ready,
  start-day (video), session-notification (planned), create-report, report-photos,
  submit-report (video), on-break, closeout-sheet, tracking-status, settings,
  change-password.

## Handoff
- `web-agent`: when you port this into PHP, the shell classes live in
  `website/assets/css/styles/pages/employee-manual.css` (already in your read path);
  reuse `page-chrome.css` exactly as `battery-use` does. The live logo is
  `website/assets/hazraevLogo.jpg` (brand mark used top-left).
- New dependency: none.

## Needs doing next
- [ ] Capture pass — screenshots/videos on seeded data, light + dark for key
      screens (Doc 08 Phase B). Owner: whoever runs the capture step. Drop into
      `html/assets/manual/{img,video}/` matching the `data-media` names.
- [ ] Admin manual — reuse this exact shell/CSS, swap copy for the 9 admin sections
      in Doc 08. Owner: design-agent (next task), copy from docs-agent.
- [ ] Un-mark section 3 "Coming soon" once Doc 07 ships. Owner: design-agent.

## Blocked on
- Nothing blocking. Section 3 stays "Coming soon" until `docs/07` ships (mobile-agent);
  media stays as placeholders until the capture step runs on seeded data.
