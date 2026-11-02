# User manuals — plan (employee app + admin panel) (2026-11-01)

A PLAN, not yet built. Goal: **two** polished, on-brand help pages — one for the **employee
mobile app**, one for the **website admin panel** — each with screenshots and short animated
video walkthroughs. Split ownership: **docs-agent** writes this plan and later the manual
**copy** (section text); **design-agent** builds the HTML/CSS pages and embeds the media.

## Shared design

Both pages reuse the existing brand system — **no hardcoded hex, ever**:
- Consume the token variables in `website/assets/css/styles/tokens.css` — brand palette
  (indigo `#3a1a6b`, violet `#7b2ff7`, magenta `#a41fbf`, blue `#12a5e0`, orange `#f7941d`,
  flame `#f0532b`), accent orange, ink `#180f2c`, surface `#faf8fd`, light **and** dark.
- Typography per `website/assets/css/styles/typography.css`: **Montserrat** headings
  (`--font-display`), **Inter** body (`--font-body`).
- Brand-gradient section headers; **orange accent** CTAs; card / radius / shadow tokens.
- Logo top-left (`website/assets/hazraevLogo.jpg`).
- **Mobile-first responsive**; light + dark both supported.
- Reuse the shared nav/footer `website/assets/css/styles/components/page-chrome.css`.
- Pages live in **`html/`** (where the website prototype already lives); media under
  **`html/assets/manual/{img,video}/`**.

These pages are **design-agent territory to build** — docs-agent does not write `html/`.

## Employee manual — sections

1. **Install & sign in** — note the new placeholder login (no pre-filled credentials).
2. **Permissions (location + notifications) & Start Day.**
3. **The session notification** — Break / Resume / End Day from the status bar.
   **Depends on Doc 07 shipping**; mark **"coming soon"** until the Android session
   notification is built.
4. **Logging visits & submitting reports** with photos.
5. **Breaks & pauses.**
6. **End of day / closeout.**
7. **Weak-signal / offline behavior** — fixes are queued, nothing is lost.
8. **Profile, settings, change password.**
9. **Troubleshooting & FAQ.**

## Admin manual — sections

1. **Sign in + forced first-login password change.**
2. **Dashboard / live team map.**
3. **Employees** — add, reset password, deactivate.
4. **Route history & stops.**
5. **Report review** — approve / send back.
6. **Product catalog.**
7. **Tracking rules** — the config screen.
8. **Messaging + dealer locations.**
9. **Troubleshooting.**

## Screenshot & video capture plan

- **Mobile screenshots**: from an emulator at a **fixed device frame**.
- **Admin screenshots**: from a browser at a **fixed viewport**.
- **Data**: **SEEDED / DEMO data only**, with any real PII blurred.
- **Light + dark** captured for the key screens.
- **Video**: short per-task recordings, trimmed, compressed to web-friendly **WebM/MP4**,
  each with a **poster image**, **lazy-loaded** to keep the pages fast; captions optional.
- All media stored under **`html/assets/manual/`**.

## Ownership & phasing

- **docs-agent**: this plan now; manual **copy** (section text) as a later task.
- **design-agent**: builds the HTML/CSS pages and embeds media on the token system.
- A **capture step** needs the **app + admin panel running on seeded data** — this gates all
  media.

Phases:
- **A** — information architecture + branded page shell (design-agent, on the token system).
- **B** — capture media (needs app + admin on seeded data).
- **C** — author copy + embed media + QA (responsive, dark, performance, accessibility).

**Dependencies**: the employee **session-notification section depends on Doc 07** shipping;
until then that section stays "coming soon".
