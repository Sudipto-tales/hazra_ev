---
agent: mobile-agent
date: 2026-08-19T13:30Z
status: done
breaking: yes — the seeded dataset moved country; see "Boundary crossing"
---

# Seed data relocated to Bardhaman, Linux desktop target added

Follow-up to `mobile-20260819T1200Z-osm-osrm-route-map.md`. That report flagged
that `website/database/seed.php` was still on Dhaka coordinates, so the running
app showed Dhaka on the new OSM basemap even though the Flutter mock data had
moved. Verified against the live API before the change:
`GET /api/v1/routes?subject=team` returned points at `23.78, 90.41`.

## Boundary crossing — please note

**`website/` is `web-agent`'s folder and I wrote in it.** `docs/AGENT-BOARD.md`
should record that. The user was shown the boundary explicitly and chose
"relocate `website/database/seed.php`" over the two alternatives (run the app
with `USE_MOCKS=true`, or leave the seeder on Dhaka). One file was touched:

| File | Change |
| --- | --- |
| `website/database/seed.php` | coordinates, place names, timezone, weekend |

No controller, no migration, no schema change. The API contract in
`docs/02-API-PLAN.md` is untouched — only the rows it serves moved.

## What changed in the seeder

| Was | Now | Coordinates |
| --- | --- | --- |
| Gulshan Branch | Khosbagan Branch | 23.2432, 87.8567 |
| Banani Branch | Curzon Gate Branch | 23.2370, 87.8635 |
| Dhanmondi Store | Golapbag Store | 23.2262, 87.8482 |
| Motijheel Office | B.C. Road Office | 23.2350, 87.8640 |
| Uttara Sector 7 | Nababhat Showroom | 23.2565, 87.8772 |

These match the Bardhaman coordinates already in `mobile_app/lib/data/mock/`,
so mocks and the live API now put the same customer on the same street.

Employee track origins moved from `23.7510 + i*0.006, 90.3760 + i*0.006` to
`23.2280 + i*0.006, 87.8500 + i*0.006` — same spacing, inside Bardhaman town.
Regions became Bardhaman North / Bardhaman South / Memari; the profile address
became `Bardhaman, West Bengal`.

### Two changes that are not coordinates

1. **Timezone `Asia/Dhaka` → `Asia/Kolkata`** (3 places: the `organizations`
   row and both `DateTimeZone` instances in the day builders). UTC+06:00 →
   UTC+05:30, so every derived timestamp shifts by 30 minutes. An org in West
   Bengal keeping Dhaka time would put day boundaries in the wrong place.
2. **`weekend_days` `[5,6]` → `[7]`.** ISO weekday numbers: Friday+Saturday was
   the Bangladesh weekend; West Bengal's is Sunday. This changes what
   `AttendanceController` and `Engine` count as a working day, so attendance
   figures across every screen will differ from before. **Revert with one
   value if that is not wanted.**

`website/api/support/Ctx.php:91` and `:193` still carry `'Asia/Dhaka'` and
`[5, 6]` as their hardcoded fallbacks for a missing org row. Left alone —
that is API support code, not seed data, and it is `web-agent`'s call.

### Not changed

Company names (Meghna, Padma, Jamuna, Turag — Bangladeshi rivers), person
names, and `+880` phone numbers. Same decision as the mobile mock data: the
ask was coordinates, and renaming people and brands is a bigger call. They now
read oddly against an Indian town.

## Re-seeding

`php vayu migrate --fresh --seed` — drops and rebuilds everything. The previous
database was backed up to `/tmp/database.sqlite.pre-bardhaman.bak` first.
**Existing tokens are invalidated by the reseed**; clients have to sign in
again.

Verified after: 163 points per employee-day, first fix `23.2291, 87.8505`,
four days of tracks.

## Linux desktop target

`mobile_app` had only an `android/` platform folder. `flutter create
--platforms=linux .` added `linux/` (8 files).

`ApiConfig` needed **no code change** — its default was already
`http://localhost:8000/api/v1`, which is correct for a desktop build. The app
is run with `--dart-define=API_HOST=127.0.0.1` anyway, because
`getent hosts localhost` answers `::1` first on stock Ubuntu while
`php -S 127.0.0.1:8000` listens on IPv4 only. That row was added to the
`ApiConfig` doc table.

## Security note

A PHP dev server left over from an earlier session was still listening on
`0.0.0.0:8000` — the tracking API, with employee location data, reachable from
the whole LAN. It was killed and restarted on `127.0.0.1`. Worth a line in the
board: `php vayu run` defaults to `localhost`, and `--host 0.0.0.0` should only
be used when a physical handset genuinely needs it.
