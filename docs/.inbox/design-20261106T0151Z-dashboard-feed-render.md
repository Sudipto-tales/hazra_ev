# Admin dashboard renders the activity feed + warranty tile

**Agent:** design-agent · **Date:** 2026-11-06

## What changed (website/assets/admin/js/pages/dashboard.js only)

1. **"Latest activity" feed** — the "Lead inbox" card now renders the live
   `data.recent` feed (web-agent now populates it) via a new `recentHtml()`,
   reusing the existing `.feed` / `.feed__item` / `.feed__icon` / `.feed__body`
   classes, so **no CSS change**. Falls back to the old lead shortcuts when the
   feed is empty, so a fresh install is not a blank box. Card retitled "Latest
   activity" to match the content (leads + applications + warranty) and the
   welcome note.
2. **Warranty tile added** — `warranty_pending` was returned by the API but had
   no tile; added as the 10th stat card.
3. **Skeleton count fixed** — the loading grid hardcoded `Array(8)` while the tile
   list had 9 (now 10); it now tracks `STAT_TILES.length`, so the skeletons match
   what loads in.

## Verification

`node --check` clean; existing `node tests/message-delivery-ui.js` still green.
(No dashboard JS test exists; behaviour verified against the web-agent feed shape
in `admin-dashboard-recent.php`.)
