---
agent: design-agent
date: 2026-08-27T00:00Z
status: done
handoff-to: web-agent (real hrefs once page routes exist), content owner (7 destination pages)
---

# design-agent — seven-item navbar with dropdowns and a mobile accordion

Folder touched: `html/` only. Hero choreography untouched — the nav still lives
inside `.canvas > header.topbar` and keeps reading `--p` for its chip fade,
text-shadow and shadow ramp.

The old four-item nav (Home / Product / About / Pricing) was replaced with the
seven-item structure supplied by the user. Five items are dropdown groups, two
are direct links.

## Structure shipped

| Item | Type | Children |
| --- | --- | --- |
| Home | link | — |
| About | dropdown | Our Story, Career, FAQ |
| Products | dropdown (wide) | CHALO 1000 V2 *High Speed*, CHALO SMART PRO / PLUS / ECO, CHALO NEO, NJA ~ 7 *(all Low Speed)* |
| Social | dropdown | Contest *Reels Contest*, Blog, News, Battery Use, EV Future |
| Dealers | dropdown | Dealer Locator, Become a Dealer |
| Service | dropdown (wide) | Free Warranty Registration, Paid Warranty Registration |
| Contact | link | — |

Speed/qualifier labels render as a right-aligned `<em>` inside each sub-item, so
"High Speed" reads as metadata rather than part of the product name.

## What changed

**`html/index.html`** — nav markup rebuilt. Each dropdown is one `.nav__grp`
holding a `<button class="nav__i nav__i--t">` trigger (with an `aria-expanded`
that JS keeps true) and a `.nav__menu` panel of `.nav__s` anchors. A `.burger`
button was added ahead of the nav for the ≤1024 panel.

**`html/style.css`** — dropdown, burger and accordion rules, plus width trims.
Trigger and panel share one `.nav__grp` so `pointerleave` never fires while the
pointer crosses into the panel, and a `.nav__menu::before` strip bridges the
10px visual gap. Seven items plus carets is a tight fit against the fixed
top-right `.tab` (`--tab-w:282px`), so `.nav__i` padding went 20px → 15px and
`.nav` gap 6px → 2px, with a further trim to 11px/12.5px at ≤1280.

**`html/script.js`** — the file had no nav code before. One `.is-open` class now
drives both the desktop dropdown and the ≤1024 accordion, so there is a single
state to reason about. Hover-open is gated behind `matchMedia('(hover:none)')`
and `matchMedia('(min-width:1025px)')`; click-open always works, for touch
laptops and keyboards. Outside-click, Escape, choosing a destination, and
crossing the 1025px breakpoint all clear open state.

## Two things worth knowing

**Hrefs are placeholders.** `website/config/route.php` defines no web page
routes yet — only bootstrap and the `api/controllers/*.php` glob — so there was
no route naming to mirror. Every new item points at a `#slug` fragment
(`#our-story`, `#chalo-1000-v2`, `#dealer-locator`, …). These are a rename away
from real URLs. `#about` and `#collection` remain live targets on this page.

**A pre-existing horizontal scrollbar at mobile width was NOT introduced here.**
It reproduces identically on a pre-change backup of `index.html` + `style.css`
at 430×932. Left alone — it is someone's separate bug, not this task's.

## Verification

`node --check script.js` clean; an HTML nesting pass reports no unclosed tags.
Headless Chrome screenshots at 1600px, 1280px and 430×932 (menu open, Products
expanded) confirm the nav clears the `.tab`, the panels are not clipped by
`.stage{overflow:hidden}`, and the burger animates to a centred X — the first
burger attempt centred its bars with a grid row offset plus a translate and
rendered a lopsided `>`; the bars are now absolutely positioned so both rotate
about the same point.
