---
agent: design-agent
date: 2026-08-28T00:00Z
status: done
handoff-to: content owner (four real scooter renders + true specs), web-agent (wire slide data once the product API exists)
---

# design-agent — scroll-scrubbed "feature window" section

Folder touched: `html/` only. New `#features` section sits between `#collection`
and `.why` in `html/index.html`, with its CSS appended to `html/style.css` and a
self-contained IIFE appended to `html/script.js`. No existing rule or handler was
modified except two one-line widenings noted below.

The brief was the Shinola Detroit product-window layout — a full-viewport framed
window, one product centred inside it, an arc dial of model names along the
bottom, item code bottom-left, finish name bottom-right, a vertical rail on the
left and dot pagination on the right — rebuilt for EV scooters, driven by scroll.

## Interaction shipped

Above 901px the section is a **sticky pin, scroll-scrubbed** slider:

- `.feat__drive` is `height:420vh`; `.feat__pin` is `position:sticky; top:0;
  height:100svh`. The surplus scroll is the driver.
- `script.js` writes exactly one custom property, `--fi` (fractional slide
  index, 0→3), onto `#features`. Every piece of motion in the section is a
  `calc()` off it — the same single-number idiom the hero uses with `--p`.
- A `HOLD = .88` constant reserves the last 12% of the driver so the fourth
  slide gets a beat centred in frame before the pin releases.
- Each animated child carries `--n` (its own index) and derives
  `--d: calc(var(--n) - var(--fi))`. Position is linear in `--d`; opacity and
  scale are quadratic (`var(--d) * var(--d)`), which gives a symmetric
  neighbour fade without needing CSS `abs()` and without a single JS-toggled
  state class. Quadratic opacities are wrapped in `max(0, …)` so items two or
  more slides away clamp at zero instead of going negative and disappearing.
- The scroll listener is passive and `requestAnimationFrame`-throttled.

Below 901px the driver collapses (`height:auto`), the pin goes static, the arc
and rail hide, and `.feat__deck` becomes an `overflow-x:auto` scroll-snap
carousel with per-slide name and spec chips revealed. The dots become a
horizontal row and a deck `scroll` listener keeps `--fi` in sync.

`prefers-reduced-motion` drops the transforms and leaves a plain opacity fade.

## Parts

| Element | Behaviour |
| --- | --- |
| `.feat__window` | the frame — `rgb(var(--chip-rgb) / .55)`, `1px solid var(--hair-0)`, `overflow:hidden` |
| `.feat__head` | eyebrow + `.feat__title` "One frame, / four ways to ride." — inset to clear the rail and the dot column |
| `.feat__rail` | RANGE / TOP SPEED / BATTERY, `writing-mode:vertical-rl` + `rotate(180deg)`; each value stack swaps on `--d` |
| `.feat__deck` | the four `.fslide` articles, absolutely stacked, spread by `--travel:78%` |
| `.feat__dots` | four buttons; `::before` ring + `::after` core both driven by `--d`. Click scrolls the driver to that index (or `scrollIntoView` on mobile) |
| `.feat__item` / `.feat__finish` | ITEM: 10009601–04 and LIME GLOSS / MATTE GRAPHITE / SIGNAL RED / CHALK WHITE, each a stack of `.swap` spans |
| `.feat__arc` | a `2r` circle hung below the window floor so only its crown shows. `.arc__ticks` and `.arc__cursor` stay fixed; only `.arc__ring` rotates by `calc(var(--fi) * -1 * var(--arc-step))`, so names travel along a static arc |

Tunable tokens live on `.feat`: `--arc-r`, `--arc-step`, `--travel`,
`--arc-lift`.

## Tokens reused, nothing new invented

Inter for body and meta, Montserrat for the display faces; `--ink-0`,
`--ink-soft-0`, `--hair-0`, `--surface-0`, `--chip-rgb`, `--lime`, `--sh-a`,
`--ease`. The section inherits `html[data-theme="dark"]` for free because it
touches only tokens.

## Known gap — the imagery

`html/assets/` holds two renders (`scutie_light.webp`, `dark_scutie.webp`), and
both are **full scene photos at 2390×1792, not cutouts**. Two consequences:

1. Each slide frames its photo in a `.fslide__media` plate (`aspect-ratio:4/3`,
   `overflow:hidden`, rounded, shadowed, `object-fit:cover`) — the same framing
   idiom `.card__media` already uses on this page. Without the plate the photo
   goes full-bleed and reads as a backdrop rather than a product.
2. Four slides share two files. Slides 3 and 4 carry a per-slide `--fx` filter
   (`hue-rotate(258deg) saturate(1.5)` for Signal Red, `saturate(.15)
   brightness(1.28)` for Chalk White) to fake the finish.

**When real renders land**: swap the `src` on each `.fslide__img` and set that
slide's `--fx` to `none`. Cutouts on transparent PNG would also let
`.fslide__media` be dropped entirely for a floating-product look closer to the
reference. Model names, item codes, finishes and rail specs are placeholders and
need the real product data.

## Two existing lines touched

- `script.js` — the reveal-up IntersectionObserver selector was widened to
  include `.feat .reveal-up`.
- `index.html` — the section was inserted, nothing removed.

## Verified

`node --check html/script.js` passes; HTML tag balance checks out; the composition
was confirmed by headless-Chrome screenshot at `--fi:1` (arc seated at the window
floor, all four dots visible, media plate centred, title clear of the spec rail).
Not yet checked on a real touch device or in dark theme.
