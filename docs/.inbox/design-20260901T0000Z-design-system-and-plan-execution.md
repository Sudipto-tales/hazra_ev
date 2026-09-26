# design-agent — design system extraction, asset/SEO pass, plan retired

**Date:** 2026-09-01
**Folders touched:** `html/` only (plus two archive docs handed to `docs/`)

## What shipped

**Design-token layer (new `html/styles/`)**
- `tokens.css` — brand palette, light/dark surface + ink, spacing, radius, shadow, motion.
  Dark is an intentional palette that redefines only what changes, not an inversion.
- `typography.css` — font stacks, fluid type scale, line-height, tracking, base headings.
- `global.css` — reset, `color-scheme`, `prefers-reduced-motion` guard, focus rings.
  Adds `body{overflow-x:hidden}`, which the old stylesheet did not have.
- `utilities.css` — `.container`, `.section`, `.stack`, `.sr-only`, `.skip-link`, `.text-grad`.
- `components/page-chrome.css` — header, brand, theme toggle, hero and footer chrome that
  had been copy-pasted inline into 9 pages. 188 duplicated rules (~30 KB) removed.

**Self-hosted fonts (`html/assets/fonts/`)**
- Inter + Montserrat as variable woff2, `unicode-range`-subset latin / latin-ext,
  `font-display:swap`, preloaded. 236 KB total, 86 KB on the latin path.
- No external font requests remain on any page.

**Images**
- Hero PNGs converted to WebP: 12.9 MB → 392 KB (-98%) across 48 references.
- Source PNGs are still on disk and still git-tracked, now unreferenced — safe to prune
  in a separate commit once someone confirms nothing else wants them.

**SEO / head**
- Canonical, description, Open Graph and Twitter card on all 18 pages.
- JSON-LD: Organization (home), Product (model pages), Blog (blog).
- The lucide CDN script was unpinned; it is now pinned to a fixed version.

**Two real bug fixes**
- `index.html` wrote its theme preference to `vm-theme` while every other page read
  `theme`, so dark mode was lost on every navigation. Unified on `theme`, with a
  one-time read of the old key so existing visitors keep their choice.
- Added a tiny inline pre-paint theme applier to all 18 pages, so a dark-mode visitor
  no longer gets a light flash. It is inline on purpose — an external file would race
  the paint it exists to prevent.

## Built, then removed at the user's request

Two home-page sections from the plan — "Our Journey" (curved-path timeline) and
"More Than a Ride" (asymmetric editorial gallery) — were built, QA'd, and then removed
on 2026-09-01 when the user asked for them to be taken off the home page. Their
markup, `styles/components/journey.css`, `styles/components/editorial.css`, the nav
links and the JS hooks are all gone; `index.html` is 4137 px shorter and reports no
console errors. Nothing else depended on them. Recoverable from git history if wanted.

## QA

Headless Chrome, six breakpoints (1440 / 1280 / 1024 / 768 / 480 / 360) across
index, our-story, chalo-neo, blog and contest. No JS errors on any page. Marquee and
hero-background elements measure wider than the viewport by design and are clipped —
`scrollX` stays 0 at every width, so there is no horizontal scroll.

Caveat: headless Chrome clamps its window to 485 px, so the 480 and 360 rows are the
same measurement. 360 has not been verified independently.

## Handoffs

`html/EV_Website_Redesign_Next_Day_Plan.md` has been deleted, as the user asked. Its
unbuilt halves were archived first, so nothing was lost:

- **`docs/04-ADMIN-PANEL-SPEC.md`** — plan §15-27, STEPs 14-18, admin DoD.
  Owner is `web-agent`. Nothing in it is built. `docs/02-API-PLAN.md` stays
  authoritative for the API contract; that spec does not override it.
- **`docs/05-PRODUCT-ARCHITECTURE.md`** — plan §7-14, STEPs 9-13, products DoD.
  Shared design/web work.

## Known gaps

- The lifestyle photography the plan calls for (city riding, people, roads, charging)
  does not exist in `assets/`. Nothing was substituted with stock.
- The original hero PNGs remain on disk, unreferenced.
