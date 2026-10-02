/// Google encoded-polyline codec, precision 6.
///
/// The server sends road-matched geometry this way rather than as JSON
/// coordinate pairs: a matched day is a few thousand vertices, which is ~40 KB
/// of floats and ~8 KB encoded, and it decodes in one pass instead of
/// allocating a list of two-element lists.
///
/// Precision 6 (~11 cm), not the classic 5 (~1.1 m) — five visibly stair-steps
/// a snapped road at street zoom, and six is what OSRM's `polyline6` and
/// Valhalla both emit.
class PolylineCodec {
  const PolylineCodec._();

  static const double _factor = 1e6;

  /// Returns `[latitude, longitude]` pairs.
  ///
  /// A truncated or malformed string yields the vertices decoded so far rather
  /// than throwing: geometry is a rendering input, and half a route drawn beats
  /// a crashed map screen.
  static List<List<double>> decode(String encoded) {
    final List<List<double>> out = <List<double>>[];
    int index = 0;
    int lat = 0;
    int lng = 0;

    while (index < encoded.length) {
      final (int dLat, int afterLat) = _value(encoded, index);
      if (afterLat == index) break;

      final (int dLng, int afterLng) = _value(encoded, afterLat);

      lat += dLat;
      lng += dLng;
      index = afterLng;

      out.add(<double>[lat / _factor, lng / _factor]);
    }

    return out;
  }

  /// One varint, and where it ended.
  static (int, int) _value(String encoded, int index) {
    int result = 0;
    int shift = 0;
    int byte = 0;

    while (index < encoded.length) {
      byte = encoded.codeUnitAt(index++) - 63;
      result |= (byte & 0x1f) << shift;
      shift += 5;
      if (byte < 0x20) break;
    }

    // Bit 0 carries the sign (zig-zag), so an odd value is a negative delta.
    return ((result & 1) != 0 ? ~(result >> 1) : (result >> 1), index);
  }
}
