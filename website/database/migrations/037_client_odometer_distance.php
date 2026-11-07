<?php

/**
 * Client-reported odometer becomes the headline distance (algo v3).
 *
 * Why this exists. v2 (migration 036) measures distance server-side from the
 * fix chain and splits it into a VERIFIED figure and an INCOMPLETE one — the
 * latter being distance travelled across a GPS gap, deliberately excluded from
 * the headline. That is correct when coverage is clean, but the device's own
 * moving capture cadence (one fix ~every `location_interval_seconds`) sits
 * right on top of `gps_gap_warning_seconds`, so ordinary driving was being
 * classified as gap travel and the headline collapsed to a fraction of the
 * real distance. The phone, meanwhile, measures its own travel continuously
 * with the identical step gate and reads the honest number.
 *
 * v3 therefore makes the phone's odometer authoritative for the HEADLINE:
 * `work_sessions.client_distance_km` holds the device's measured kilometres for
 * that session, and `Engine` reads it as `distance_km` for a v3 session instead
 * of the fix-derived verified figure. The GPS-derived
 * verified/incomplete/coverage numbers are STILL computed from the chain and
 * kept — they power the admin map's coverage indicator and the gap rows — they
 * are simply no longer the headline. See `Engine::sessionHeadlineKm`.
 *
 * **Nullable, no default.** A session that has not yet reported an odometer
 * (just opened, or an old client) has `client_distance_km = NULL`, and the
 * Engine falls back to the derived verified figure for it — so nothing ever
 * reads 0 for want of a report. NULL is "no report yet", exactly as 036's
 * reporting columns use NULL for "predates the engine".
 *
 * **Monotonic, server-side.** The client reports its ABSOLUTE per-session
 * odometer (its baseline for a resumed session plus what it has measured
 * since), and the controller stores `max(stored, reported)`. That makes a
 * replayed or out-of-order batch harmless and makes a device handoff safe: the
 * resuming phone's baseline IS the server's current value, so it always reports
 * at least as much and the first phone's distance is never lost.
 *
 * **Why `distance_algo_version` and not a flag.** Same contract 036 pinned:
 * a historical distance is never silently restated. A session created before
 * this migration keeps its stamped v1/v2 rule and its fix-derived headline
 * forever; only sessions created from here carry `Engine::DISTANCE_ALGO_VERSION`
 * = 3 and read the odometer. `dayAlgoVersion` is the MIN across a day's
 * sessions, so a day that straddles the migration is measured by the older
 * rule rather than half-restated.
 *
 * No wire-breaking change and no config change: `distanceKm` keeps its meaning
 * (the headline) and its column; only its SOURCE changes for a v3 session. One
 * additive nullable field, `clientDistanceKm`, is exposed for transparency.
 */
class ClientOdometerDistance extends Migration
{
    public function up()
    {
        // The device's measured kilometres for this session. NULL until the
        // first odometer report lands; the Engine falls back to the derived
        // verified figure while it is NULL.
        $this->addColumn('work_sessions', 'client_distance_km', '{float}');
    }

    public function down()
    {
        // Left in place: dropping a column rewrites the table on both drivers
        // and SQLite cannot drop one at all. A rollback leaving an unused
        // nullable column is cheaper and safer — the call 036, 031 and 010 make.
    }
}
