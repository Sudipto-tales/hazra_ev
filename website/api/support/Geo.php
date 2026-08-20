<?php

/**
 * The geometry PostGIS would have done. Stop clustering and route simplification
 * both run in PHP here, which is why the SQLite port needs no spatial extension.
 */
final class Geo
{
    private const EARTH_KM = 6371.0088;

    /** Great-circle distance in kilometres. */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return self::distanceKm($lat1, $lng1, $lat2, $lng2) * 1000;
    }

    /**
     * Implied speed between two fixes. Feeds the max_jump_kmh check that
     * rejects a GPS teleport at ingest.
     */
    public static function impliedKmh(float $km, int $seconds): float
    {
        return $seconds <= 0 ? 0.0 : $km / ($seconds / 3600);
    }

    /**
     * Douglas-Peucker over [lat, lng, ...] tuples, tolerance in metres.
     * A 1 000-point day comes out at roughly 150 points with no visible change
     * to the polyline. Extra tuple members ride along untouched.
     */
    public static function simplify(array $points, float $toleranceM): array
    {
        if ($toleranceM <= 0 || count($points) < 3) {
            return $points;
        }

        $keep = array_fill(0, count($points), false);
        $keep[0] = true;
        $keep[count($points) - 1] = true;

        self::douglasPeucker($points, 0, count($points) - 1, $toleranceM, $keep);

        $out = [];
        foreach ($points as $i => $point) {
            if ($keep[$i]) {
                $out[] = $point;
            }
        }

        return $out;
    }

    private static function douglasPeucker(array $pts, int $first, int $last, float $tol, array &$keep): void
    {
        if ($last <= $first + 1) {
            return;
        }

        $maxDist = 0.0;
        $index = $first;

        for ($i = $first + 1; $i < $last; $i++) {
            $d = self::perpendicularM($pts[$i], $pts[$first], $pts[$last]);
            if ($d > $maxDist) {
                $maxDist = $d;
                $index = $i;
            }
        }

        if ($maxDist <= $tol) {
            return;
        }

        $keep[$index] = true;
        self::douglasPeucker($pts, $first, $index, $tol, $keep);
        self::douglasPeucker($pts, $index, $last, $tol, $keep);
    }

    /** Point-to-segment distance in metres, on a local equirectangular plane. */
    private static function perpendicularM(array $p, array $a, array $b): float
    {
        $latScale = 111_320.0;
        $lngScale = 111_320.0 * cos(deg2rad($a[0]));

        $px = ($p[1] - $a[1]) * $lngScale;
        $py = ($p[0] - $a[0]) * $latScale;
        $bx = ($b[1] - $a[1]) * $lngScale;
        $by = ($b[0] - $a[0]) * $latScale;

        $lenSq = $bx * $bx + $by * $by;

        if ($lenSq == 0.0) {
            return sqrt($px * $px + $py * $py);
        }

        $t = max(0.0, min(1.0, ($px * $bx + $py * $by) / $lenSq));
        $dx = $px - $t * $bx;
        $dy = $py - $t * $by;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /** Centroid of a set of [lat, lng, ...] tuples. */
    public static function centroid(array $points): array
    {
        $n = count($points);
        if ($n === 0) {
            return [0.0, 0.0];
        }

        $lat = 0.0;
        $lng = 0.0;
        foreach ($points as $p) {
            $lat += $p[0];
            $lng += $p[1];
        }

        return [$lat / $n, $lng / $n];
    }
}
