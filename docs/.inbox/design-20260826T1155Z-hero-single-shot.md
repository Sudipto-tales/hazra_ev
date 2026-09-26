# design-agent — hero: drop variant deck, single theme-driven product shot

**Folder:** `html/` (index.html, style.css, script.js)

## What changed
- Removed the Classic/Retro/Manual thumbnail deck from the bottom shelf.
- Right column now holds two stacked `<img>` shots cross-faded by `[data-theme]`:
  `assets/scutie_light.webp` (light) / `assets/dark_scutie.webp` (dark). No JS `src` swap.
- Shelf rebuilt: pager `01/02` + lime-square `DAY & NIGHT FINISH` + prev/next arrows.
  Arrows now flip the finish (= flip the theme) instead of paging the deck.
  `--shelf-h` 200px → 124px, `--shelf-w` 63% → 57% (keeps the fillet notch).
- `.pill--a` ("Soft / Touch") moved out of `.photo` (which is `overflow:hidden`) into
  `.canvas` so it straddles the photo's left edge instead of being clipped.
- Dead references cleaned: `assets/scooter.png`, `assets/bg-light.jpg`, `assets/bg-dark.jpg`
  did not exist; the backdrop now uses the real PNGs.

## Open item for whoever ships this
Both PNGs are ~6.2 MB (2390x1792). Far too heavy for a hero. Needs WebP/AVIF +
responsive `srcset` before this leaves the prototype.
