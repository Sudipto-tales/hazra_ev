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

The extracted dataset is `database/data/dealer-locations.json`. Seeder `007_dealer_locations_seed.php` adds the supplied Burdwan Main Showroom and Ichlabad Outlet addresses as drafts. Run `php vayu db:sync` from `website/` to apply pending migrations and seeders. Exact map pins and branch phone numbers were not supplied; open each draft in **Dealer Locations**, confirm those details and publish it to show it on the locator. Seeder `008_dealer_locations_refresh_seed.php` applies the same dataset for servers where 007 already ran; it inserts only missing records. The seeder preserves later admin changes and never creates duplicate records on rerun. Similarly named Kolkata shops are excluded because their affiliation was not confirmed.

Leaflet 1.9.4 is served from `assets/vendor/leaflet/`, including its CSS and marker images. Deploy that directory with the application. The public map initializes when the page is ready; the admin picker loads the Google SDK asynchronously.

The map configuration is in `config/dealer-map.php`. Optional environment variables:

- `DEALER_GEOCODER_URL`: Photon-compatible service root, default `https://photon.komoot.io/`.
- `DEALER_MAP_TILE_URL`: Leaflet tile URL template, default `https://tile.openstreetmap.org/{z}/{x}/{y}.png` (no API key).
- `DEALER_MAP_ATTRIBUTION`: HTML attribution required by the chosen provider.

Admin search uses Google Places API (New), with a Google map and Geocoding API for current-location address lookup. Configure `GOOGLE_MAPS_BROWSER_KEY` in `.env`; enable Maps JavaScript API, Places API (New), and Geocoding API with billing in Google Cloud. Restrict the browser key to your production domains and permitted APIs. The key is intentionally sent to the admin browser for the Maps JavaScript SDK; it is never hard-coded in source or included in public map configuration. Search runs only on Search or Enter, and stale responses are ignored. Confirm dealer-owned address and coordinate details before publishing; Google search suggestions are not a permanent dealer data feed.

The public map continues to use Leaflet and OpenStreetMap tiles. Dealer cards are a horizontal carousel along the bottom of the map, with previous/next buttons, touch scrolling and marker/card selection. Map attribution remains visible below the carousel.

Standard OpenStreetMap tiles require visible attribution, browser caching and a valid Referer. Do not prefetch or bulk-download tiles; the service has no availability guarantee. See https://operations.osmfoundation.org/policies/tiles/. For higher traffic, configure a suitable hosted tile service. CARTO tiles now require a CARTO key; a keyless CARTO URL may return HTTP 200 with an "API KEY REQUIRED" image, so HTTP status alone does not prove that a map tile is usable.

## Checks

`php tests/dealer-locations.php` exercises the actual controller against a temporary SQLite database: migration, creation, partial update, publication filtering, validation, admin access and CSRF checks.

`node tests/dealer-location-picker.cjs` exercises the editor with browser/provider boundaries stubbed: search selection, current location, measured coordinates, denied permission, stale callbacks, HTTPS fallback and saving.

Manually verify in a browser: search results, location permission, pin dragging, responsive layouts, public marker/card selection, and provider failure fallbacks. Automated checks do not verify live provider availability or browser permission UI.

## Production deployment

`website/.env.example` is the server environment source for the GitHub `PROD_ENV_FILE` secret. The Google browser key is supplied separately through the repository Actions variable `GOOGLE_MAPS_BROWSER_KEY`; deployment replaces its placeholder in the server `.env`. Enable Maps JavaScript API, Places API (New), and Geocoding API and restrict the key to your website origins in Google Cloud.

Publish the `website/` tree to the `website` branch, then dispatch `deploy-server.yml` on `main`. The workflow backs up the current `.env` and commit under `~/deployment-backups/`, preserves runtime files, writes the production environment, installs Composer dependencies, and runs `php vayu db:sync`. Existing dealer edits and publication statuses are preserved. The two source records stay drafts until verified pins and phone numbers are entered.
