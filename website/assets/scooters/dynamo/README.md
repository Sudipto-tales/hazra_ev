# Dynamo catalogue images

Official source: https://dynamoindia.com/electric-scooters

The 19 original PNGs were downloaded on 2026-10-01. `sources.json` records
the remote filename, local filename and matching catalogue model codes.
To refresh them from the website directory on Windows:

```powershell
& ./assets/scooters/dynamo/download.ps1
```

Deploy this entire asset directory with
`database/seeds/005_dynamo_product_images_seed.php`. The existing runner
automatically discovers the seed. Review all pending database changes first:

```sh
php vayu db:sync --dry-run
php vayu db:sync
```

The sync command applies every pending migration and seed, not just 005.
Seed 005 updates existing Dynamo products and inserts missing primary image
rows. It does not insert products or invent their specifications. Missing
model codes are reported and skipped. Smiley retains its existing images.
`DYN-XLLOADER` uses the XL WITH SEAT shot; `DYN-3XLLOADER` uses 3XL Loader.

Each colour uses the same official model shot at position zero. These are
model images, not verified photographs of every named colour. Existing
gallery entries at later positions are preserved.

All 19 PNGs must be present and valid before the seed writes anything.
Only changed image paths update product timestamps. The seed supports being
called again without duplicating rows; the normal runner records it once.
If new models are added after that, explicitly rerun this seed through the
deployment's database tooling, as the runner will skip its recorded entry.

Verify without changing the configured database:

```sh
php scratch/check_dynamo_seed.php
```
