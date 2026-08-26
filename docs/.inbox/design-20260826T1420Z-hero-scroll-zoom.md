# design-agent — hero scroll-zoom reveal

**Date:** 2026-08-26
**Folder:** `html/` (design-agent column)
**Files:** `html/index.html`, `html/style.css`, `html/script.js`
**Follows:** `design-20260826T1155Z-hero-single-shot.md`

## Ask

Hero should stop reading as a floating card. On scroll the card hides, the
background behind it is revealed, and the card's content survives — now sitting
on the fullscreen photo. Zoom-parallax feel. Explicitly: **no section shifting**,
scroll drives the zoom only.

## What shipped

**Sticky pin — the "no section shifting" guarantee.**
`.scroll{height:260vh}` is a tall driver; `.stage{position:sticky;top:0;height:100svh}`
pins inside it. The surplus scroll is consumed by the effect, so the page never
advances to another section while the zoom runs.

**One variable drives everything.**
JS writes a single custom property `--p` (0→1) on `:root`. Every geometry and
colour rule in the stylesheet is a `calc()` off that number. JS writes no
styles, no classes, no transforms — just the scalar. rAF-throttled, passive
listener, `getBoundingClientRect().top` for progress. `HOLD = .72` so `--p`
reaches 1 at 72% of the driver and the full-bleed state gets a beat to sit in.

**Box growth, not `transform: scale()`.**
`.canvas` interpolates its own width/height from the inset card box
(`min(1520px,93vw) × min(900px,88vh)`) to the full viewport (`--vw`/`--vh`,
JS-measured, not `100vw`/`100vh` — avoids the scrollbar-gutter mismatch).
Scaling would blur and squash the copy; growing the box keeps every absolutely
positioned child at native size and legible at every frame while the card edge
sweeps outward. `border-radius` rides the same ramp to 0.

**Plates fade, their content survives.**
Each surface token is split into a resting `-0` value and a live `--p`-ramped
value. Surfaces use space-separated RGB triplets so the *plate* can fade
independently of its *children*:

```css
background: rgb(var(--surface-rgb) / calc(1 - var(--p)));
```

Ink/hair tokens ramp toward white via `color-mix(... #fff calc(var(--p) * 100%))`
declared on `.canvas`, cascading to descendants. Result: at `--p:1` there are
zero white plates left, but the nav, tab, headline, meter, blurb, chips, pager
and arrows are all still there, sitting on the photo.

**Ink-filled chips are pinned, not ramped.**
`.nav__i.is-on` and `.cart` fill with `--ink` and label with `--on-ink`. Ramping
both converged them on the same mid-grey around `p≈0.5` and the label vanished.
Both pinned to the non-ramped resting pair `--ink-0` / `--surface-0`.

**Sky-corner legibility scrim.**
"CONTACT US" lands over the moon (dark) and bright sky (light) at `--p:1`;
ramped `text-shadow` alone was not enough. Added `.tab::before`, a
`--p`-opacity radial scrim. First cut clipped its own gradient and drew a hard
rectangle over the bright sky — the gradient must reach full transparency
*inside* the box. Fixed by oversizing the box (`inset:-70px -46px -132px -168px`)
and pulling the transparent stop inward (`58% 46% at 74% 62%`, transparent by 82%).

**Fades use `filter: opacity()`, not the `opacity` property**, so scroll-driven
fades compose with the `.reveal-pop` / `.reveal-up` entrance classes instead of
fighting them.

**Guards.** `≤1024px` forces `--p:0!important` (no zoom on tablet/mobile);
mouse parallax is damped by `1 - p` and gated on `desktop.matches &&
!reduce.matches`; `prefers-reduced-motion` disables the parallax.

## Verified

Headless Chrome at 1600×900, four states, all viewed:

| State | Result |
| --- | --- |
| light `--p:0` | Rest card unchanged from the previous task. Scrim invisible (opacity gate 0). |
| light `--p:0.45` | Mid-transition: card edge past the viewport corners, plates translucent, Home chip still black-on-white — grey convergence defect gone. |
| light `--p:1` | Full-bleed daylight Vespa, all content surviving, no plate surfaces, scrim is a soft vignette with no box edge. |
| dark `--p:1` | Full-bleed night shot, CONTACT US legible over the moon, all content present. |

Capture note: `--headless=new` reserves ~87px of window chrome, so `--window-size`
must be viewport+chrome (`1600,987`) or the shot gets a black band. Not a CSS bug.

## Open

- **Both hero PNGs are ~6.2 MB (2390×1792).** Carried over from the previous
  report, still unresolved. Far too heavy for a hero, and the zoom holds them
  on screen at full-bleed for the whole scroll — the weight matters more now
  than it did. Needs WebP/AVIF + responsive `srcset` before this leaves the
  prototype.
- Effect is desktop-only by design. If mobile needs an equivalent, that's a
  separate decision, not a port.
