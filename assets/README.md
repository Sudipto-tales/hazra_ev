# assets

Drop three files here — the CSS and markup already point at these exact names:

| File | Used by | Notes |
| --- | --- | --- |
| `bg-light.jpg` | `--bg-img` in `:root` | light-mode stage backdrop (blurred behind the card) |
| `bg-dark.jpg`  | `--bg-img` in `html[data-theme="dark"]` | dark-mode stage backdrop |
| `scooter.png`  | `.photo__img` in `index.html` | the product shot inside the photo panel |

Until they exist the page falls back: the stage uses a CSS gradient
(`--bg-fallback`) and the photo swaps to a remote placeholder via `onerror`.

Backdrops are blurred 46px and scaled 1.08, so a ~1600px-wide image is plenty.
