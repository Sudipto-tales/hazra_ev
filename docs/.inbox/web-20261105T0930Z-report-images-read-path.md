# web-agent + mobile-agent — report attachments and sale artwork are readable at last

Date: 2026-11-05
Folders touched: `website/api/`, `website/tests/`, `mobile_app/`.
Verification: 14/14 PHP suites green (13 existing + 1 new) · `flutter analyze`
**14 issues** (baseline 15), **0 errors** · `flutter test -j 1`
**216 passed / 23 skipped / 0 failed** (was 204/23; +12 new).

Closes two of the three standing contract gaps on the board:

> Report images upload but cannot be read back — the read path
> (`ReportsController.php:416`) selects only `COUNT(*)` → `imageCount`; no
> `include=images` returning the `Present::image` URL array.
> `report_detail_page` renders empty slots.

…and the bike photograph, which was the same symptom from a different cause.

---

## ⚠️ API contract change — additive, two consumers

`docs/02-API-PLAN.md` §3.9 needs updating; `docs-agent` please fold in.

### 1. `?include=images` on `GET /reports` and `GET /reports/{id}`

Adds an `images` array of `Present::image` objects to each report.

**`imageCount` is unchanged and still always sent**, so no client has to opt
in and nothing existing breaks.

The important rule, and the one worth writing into the plan:

| payload | means |
| --- | --- |
| `images` key **absent** | the caller did not ask |
| `images: []` | asked, and this report has no photos |

They must stay distinguishable. Collapsing absent to `[]` would make every
list row look like a report with no photos, which is a dressed-up version of
the bug being fixed. `Present::report` therefore omits the key entirely rather
than emitting null, and the Dart side decodes with `containsKey`, not a null
check.

### 2. `imageUrl` on every sale line, unconditionally

`Present::saleLine` gains `imageUrl` — the catalogue photograph for that
product **and colour**, relative to the API origin like every other image URL
here.

Not behind an include: it is one batched query for the whole page, and the
admin needs it wherever a sale line is drawn.

**This is a deliberate exception to standing rule 3** ("what the seller typed is
what gets stored — product name and colour are denormalised onto the sale
line"), and it should be recorded as one rather than discovered later:

- `productName` / `colorName` / `colorArgb` stay frozen at sale time, so a
  renamed product cannot rewrite history. Unchanged.
- `imageUrl` is resolved **at read time**, so it is the *current* photograph of
  the thing that was sold. Re-shoot a colour and older reports show the new
  photo.

That is the intended behaviour: a product photo is not history, and storing the
URL at sale time would pin a report to a file that may later be replaced or
deleted — a broken image instead of a current one. It also makes the admin's
view agree with the employee-side sale card, which already reads the live
catalogue, instead of inventing a third behaviour.

Colour is matched by **name**, which is exact rather than a guess:
`product_colors` carries `UNIQUE (product_id, name)` and `color_name` was
copied from it at sale time. Resolution order:

1. first image of that colour, by `position`
2. the product's `hero_image` — covers a colour with no photos, and a colour
   since renamed or deleted
3. null — the client falls back to the painted `ProductArtwork`

## Server implementation

`ReportsController::hydrate()` gained two batched lookups and a new private
`saleImages()` helper. No N+1: the work is a fixed number of queries whatever
the row count, and the new test pins that with a counting PDO —
**7 queries for 2 reports / 6 sale lines / 4 attachments**. The obvious
implementation is one query per sale line and nothing else would have caught
that regression.

`Present::image` already existed in full (url, thumbnail, dimensions, bytes,
capturedAt, lat/lng) and was only ever reachable from the upload response. It
is now reused verbatim on the read path — no new presenter.

## Client implementation

- `ReportImage` **moved** from `data/repositories/employee_repository.dart` to
  `data/models/report.dart`, where a model belongs, and is re-exported through
  `models.dart` — so every existing reference still resolves. It was declared
  as the upload-response type with a docblock reading *"nothing renders it
  yet"*; both halves of that are now false. Its `byteSize` field is renamed
  `bytes` to match the wire. Rather than keep two near-identical decoders, the
  HTTP upload path now calls `Wire.reportImage` too.
- `VisitReport.images` is `List<ReportImage>?` with `hasFetchedImages` and
  `visibleImageCount`. The latter prefers the array over `imageCount`, so the
  UI never draws more slots than it has URLs to fill — the two can disagree
  for a moment when an upload lands between a list read and a detail read.
- `ReportImageTile` gains a third state, `.network`, alongside `.file` and the
  placeholder. `ReportImageTile.resolve` is the single place a document-root
  path becomes a URL: `storage/uploads/...` resolves against
  `ApiConfig.originUrl`, **not** `baseUrl`. Resolving against the latter yields
  `/api/v1/storage/...` — a 404 that reads as a missing file rather than a
  wrong path, which is why it is one function with a test on it.
- Decoding is capped with `cacheWidth` at the drawn size. An 8 MB phone photo
  decoded at full resolution into an 88 px tile will trip the image cache once
  there are a few of them.
- New `ReportGallery`: a single photo gets the full width at its own aspect
  ratio (the server's `width`/`height` reserve the box so nothing reflows when
  the bytes land); several get a 2-up grid; tapping either opens a full-screen
  swipeable `InteractiveViewer` with pinch-zoom.
- New `SaleLineRow` replaces the admin screen's plain
  `"City Rider · Crimson" → "× 2"` `KeyValueRow` with photo, name, a real
  colour swatch and a unit pill. Falls back to the painted artwork when
  `imageUrl` is null *or* when the catalogue photo 404s.
- Both detail screens request the include. The **list** calls deliberately do
  not — `imageCount` is all a card needs.
- The employee page's *"Photos … cannot be previewed in the app yet"* note is
  now shown only when `hasFetchedImages` is false. Left in place because it is
  still true for a build pointed at a server without the include, and removing
  it outright would have been the same lie in the other direction.

## Tests

`website/tests/report-images.php` (new, 14th suite) — the absent/empty/populated
distinction, attachment ordering by `position` rather than insertion, all three
sale-image fallback tiers, relative-URL discipline on both, and the N+1 guard.

`mobile_app/test/report_images_test.dart` (new, 12 cases) — the same
absent-vs-empty distinction through `Wire`, `visibleImageCount` preferring the
array, `aspectRatio` refusing zero/missing dimensions, all four URL-resolution
cases including that a blank path resolves to **null and never to a bare
origin** (which would request the site root and render the HTML as a broken
image), and `copyWith` keeping the artwork — the sale editor calls it on every
units/colour change and dropping the field there would blank the photo.

## Not verified

- **No server with real uploads was exercised.** Both suites run against
  in-memory SQLite with hand-inserted rows; no file was ever fetched over
  HTTP. The one thing most likely to be wrong in production is whether
  `storage/uploads/` is actually reachable at the origin — if the web root is
  `website/` rather than its parent, or a rule blocks `storage/`, every
  attachment will 404 and the UI will correctly show "Unavailable". Worth one
  `curl` against a real report's image URL before calling this done.
- No device: the grid, the full-screen viewer's pinch-zoom and the sale rows
  have not been seen at phone width in either theme.
- `thumbnailUrl` is plumbed end to end but **nothing generates thumbnails**, so
  it is null on every row today and the grid loads full images. If attachment
  grids feel heavy on a slow connection, generating thumbnails at upload time
  is the fix, and the client needs no change for it.

## Deploy order

Server first, as always. The client degrades correctly against a server without
either field (null `imageUrl` → painted artwork; absent `images` → the honest
placeholder plus the explanatory note), so the app half is safe to ship either
side of it — but the images will not appear until the server half is live.

## Still open

- **web-agent** — the `activity` key collision in `DaysController`
  (`mobile-20261105T0620Z`), the config-defaults migration, and the
  `Engine::refreshSession` distance gates.
- The third standing contract gap, `reportingTo` label-on-read /
  id-on-write, is untouched.
