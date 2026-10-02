<?php

/**
 * Google encoded-polyline codec, precision 6.
 *
 * Precision 5 (the classic Maps default) quantises to ~1.1 m, which is coarse
 * enough to visibly stair-step a snapped road at street zoom. Precision 6 is
 * ~11 cm and is what both OSRM (`geometries=polyline6`) and Valhalla emit, so
 * six is not a choice here so much as the wire format the matchers already
 * speak.
 *
 * Why encode at all: a matched day is a few thousand vertices. As JSON floats
 * that is ~40 KB; as polyline6 it is ~8 KB, and the client decodes it in one
 * pass instead of allocating a list of two-element lists.
 */
final class Polyline
{
    private const PRECISION = 6;

    /**
     * @param array<int, array{0: float, 1: float}> $points [lat, lng] pairs;
     *        extra tuple members are ignored, so a route tuple can be passed in
     *        unchanged.
     */
    public static function encode(array $points): string
    {
        $factor = 10 ** self::PRECISION;
        $out = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $point) {
            $lat = (int) round(((float) $point[0]) * $factor);
            $lng = (int) round(((float) $point[1]) * $factor);

            $out .= self::chunk($lat - $prevLat) . self::chunk($lng - $prevLng);

            // Deltas are taken against the *rounded* previous value, never the
            // original float, or the error compounds along the line.
            $prevLat = $lat;
            $prevLng = $lng;
        }

        return $out;
    }

    /** @return array<int, array{0: float, 1: float}> */
    public static function decode(string $encoded): array
    {
        $factor = 10 ** self::PRECISION;
        $length = strlen($encoded);
        $index = 0;
        $lat = 0;
        $lng = 0;
        $out = [];

        while ($index < $length) {
            $lat += self::unchunk($encoded, $index, $length);
            $lng += self::unchunk($encoded, $index, $length);
            $out[] = [$lat / $factor, $lng / $factor];
        }

        return $out;
    }

    /** Zig-zag the sign into bit 0, then emit 5 bits per character. */
    private static function chunk(int $value): string
    {
        $v = $value < 0 ? ~($value << 1) : ($value << 1);
        $out = '';

        while ($v >= 0x20) {
            $out .= chr((0x20 | ($v & 0x1f)) + 63);
            $v >>= 5;
        }

        return $out . chr($v + 63);
    }

    private static function unchunk(string $encoded, int &$index, int $length): int
    {
        $result = 0;
        $shift = 0;
        $byte = 0;

        do {
            if ($index >= $length) {
                break;
            }
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1f) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }
}
