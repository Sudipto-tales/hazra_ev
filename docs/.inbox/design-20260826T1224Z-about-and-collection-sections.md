---
agent: design-agent
date: 2026-08-26T12:24Z
status: done
handoff-to: content owner (one video file), web-agent (when this ports to PHP)
---

# design-agent — About section with mosaic reveal, plus the collection grid

Folder touched: `html/` only. The hero is untouched — its scroll choreography
still owns `--p`, and nothing added below the fold reads that variable.

Two new sections sit between the hero's scroll driver and `script.js`:
`#about` and `#collection`. Nav was rewired to point at them (`Product` →
`#collection`, `About` → `#about`), since both links were previously dead.

## What changed

**`html/index.html`** — About section: a 4:3 media figure, lead + body copy, a
four-cell stat strip, and a CTA into the collection. Collection section: six
`<article class="card">` entries (Matic S1 / Pro / Eco / City / Lite / X), each
carrying a warranty badge, rating, range and speed chips, three colour swatches,
two price variants, and Explore / Test Ride actions. Card *structure* follows the
goeen.in homepage collection the design brief pointed at; the names, copy and
visual treatment are original — goeen's own product names were deliberately not
reproduced.

**Mosaic reveal.** The About media is a `<video>` under a CSS grid of tiles.
Each tile paints one slice of the same background image
(`background-size: calc(cols*100%) calc(rows*100%)` plus a per-cell
`background-position`), then flips away on `rotateX` with a diagonal
`transition-delay`. Delay is `(col + row)` plus a deterministic jitter — no
`Math.random`, so a breakpoint resize rebuilds the same layout instead of
re-scrambling it. 8×5 tiles on desktop, 5×4 under 720px.

**`html/style.css`** — one appended band. Below-fold sections read the resting
`-0` tokens (`--ink-0`, `--surface-0`, `--hair-0`) directly, so they never
inherit the hero's white ramp, and they invert cleanly with
`html[data-theme="dark"]`.

**`html/script.js`** — a second, separate IIFE. `IntersectionObserver` one-shot
reveals for copy and cards, a rAF eased count-up for the stats, in-view
autoplay/pause for the reel, pointer-tracked sheen on card media (gated on
`hover:hover`), and swatch/variant selection. `prefers-reduced-motion` is
honoured in both files: the tiles are never laid down at all, counters jump to
their final value, cards start visible.

**Colour grading, not hue rotation.** One product photo serves six cards. The
first pass used `filter: hue-rotate()`, which rotated the whole scene — purple
grass, cyan sky. Replaced with a `mix-blend-mode: soft-light` wash over the
image, which preserves luminance and grades only the finish. Clicking a swatch
rewrites the card's `--c` and the wash follows.

**Dark-theme swatch ring.** The swatches were ringed in `--hair-0` (`#2e2e33`),
which is nearly `--surface-0` (`#131315`) — a near-black finish had an invisible
fill *and* an invisible ring, so Matic City and Matic X appeared to offer two
colours in dark and three in light. The ring is now mixed off the ink
(`color-mix(in srgb, var(--ink-0) 38%, var(--surface-0))`) and inverts with the
theme. Verified in a dark-theme capture.

Verified headless at 1440px light, 1440px dark, and 430px mobile. `node --check`
passes on `script.js`.

## Needs doing next

- [ ] **Content owner** — supply `html/assets/about.mp4`. The file does not
      exist; nothing in the repo does. The mosaic currently reveals the
      theme-matched still instead, because `.mosaic__v` carries the photo as a
      CSS background and the rejected `video.play()` promise is swallowed. This
      degrades cleanly and ships as-is, but the section is specified around a
      reel. Roughly 4:3, ≤ 1:12, muted-loop-safe.
- [ ] **Content owner** — per-model product photography. There are two 6.4MB
      PNGs for six cards, alternated and graded to differentiate them. Real
      shots would replace the wash.
- [ ] **design-agent** — the six cards' specs, prices and ratings are
      placeholder copy. They need the real range/price table before this is
      shown to anyone outside the team.
- [ ] **web-agent** — when this prototype ports into `website/`, the collection
      grid wants a data source rather than six hand-written articles. No API
      shape is proposed here; that is a conversation, not a handoff.

## Blocked on

Nothing. The section renders and animates without the video; the missing asset
degrades to a still rather than a hole.
