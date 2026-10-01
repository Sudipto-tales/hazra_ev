# Dealer locations

Open **Admin → Dealer Locations** (`admin/dealer-locations`). Applications remain under **Dealership**.

1. Choose **Add location**.
2. Search for a showroom, street, city or PIN code and select a result, or use **Use current location**.
3. Click the map or drag the pin to the showroom entrance. Coordinates can also be entered manually.
4. Verify the state, district, city, full address and PIN code. Search results can omit or misidentify address fields.
5. Add the dealer name, public phone, location type and optional opening hours.
6. Save as **Draft**, or select **Published** and save to show it on the public locator. **Inactive** hides an existing location.

Current location requires HTTPS (or localhost), browser support and location permission. The measured position is preserved when a nearby address is returned by reverse lookup. Nothing is saved until **Save location** is clicked.

## Deployment

Run `php vayu migrate` from `website/` before opening the new screens. Migration 026 creates the location table; it does not import the former sample dealers. Add verified dealership records through admin.

Seeder `007_dealer_locations_seed.php` adds the supplied Burdwan Main Showroom and Ichlabad Outlet addresses as drafts. Run `php vayu db:sync` from `website/` to apply pending migrations and seeders. Exact map pins and branch phone numbers were not supplied; open each draft in **Dealer Locations**, confirm those details and publish it to show it on the locator. The seeder preserves later admin changes and never creates duplicate records on rerun. Similarly named Kolkata shops are excluded because their affiliation was not confirmed.

Leaflet 1.9.4 is served from `assets/vendor/leaflet/`, including its CSS and marker images. Deploy that directory with the application. The map initializes when the editor is ready, without waiting for every external resource on the page.

The map configuration is in `config/dealer-map.php`. Optional environment variables:

- `DEALER_GEOCODER_URL`: Photon-compatible service root, default `https://photon.komoot.io/`.
- `DEALER_MAP_TILE_URL`: Leaflet tile URL template, default `https://tile.openstreetmap.org/{z}/{x}/{y}.png` (no API key).
- `DEALER_MAP_ATTRIBUTION`: HTML attribution required by the chosen provider.

Search happens only when Search is clicked or Enter is pressed. Photon requests go directly from the browser to the configured service, use a 12-second timeout, and cancel superseded requests. The public Photon service permits reasonable request volumes, may throttle requests, and offers no availability guarantee; use your own Photon instance or another compatible hosted service if traffic grows. See https://github.com/komoot/photon. No Google API key is used; directions open Google Maps URLs.

Standard OpenStreetMap tiles require visible attribution, browser caching and a valid Referer. Do not prefetch or bulk-download tiles; the service has no availability guarantee. See https://operations.osmfoundation.org/policies/tiles/. For higher traffic, configure a suitable hosted tile service. CARTO tiles now require a CARTO key; a keyless CARTO URL may return HTTP 200 with an "API KEY REQUIRED" image, so HTTP status alone does not prove that a map tile is usable.

## Checks

`php tests/dealer-locations.php` exercises the actual controller against a temporary SQLite database: migration, creation, partial update, publication filtering, validation, admin access and CSRF checks.

`node tests/dealer-location-picker.cjs` exercises the editor with browser/provider boundaries stubbed: search selection, current location, measured coordinates, denied permission, stale callbacks, HTTPS fallback and saving.

Manually verify in a browser: search results, location permission, pin dragging, responsive layouts, public marker/card selection, and provider failure fallbacks. Automated checks do not verify live provider availability or browser permission UI.
