/// Date / duration / distance formatting.
///
/// Deliberately dependency-free (no `intl`) so the UI package stays light.
/// If localisation lands later, swap the bodies here only.
class Fmt {
  const Fmt._();

  static const List<String> _months = <String>[
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];

  static const List<String> _monthsShort = <String>[
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];

  static const List<String> _weekdaysShort = <String>[
    'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun',
  ];

  static String _two(int v) => v.toString().padLeft(2, '0');

  /// Every formatter below reads wall-clock components (`.hour`, `.day`,
  /// `.weekday`), which are relative to the DateTime's own UTC flag. A
  /// UTC-flagged value therefore renders the UTC wall clock — which is how a
  /// session started at 08:35 IST came out as "03:05".
  ///
  /// The producers are fixed at source (`LocationService._toLog`,
  /// `LocationLog.fromJson`, `Wire.dateOrNull`), so this is the backstop, not
  /// the fix: it is a no-op on an already-local value and costs nothing. It
  /// lives here because every future caller gets it for free, and a display
  /// formatter has no business caring which flag its input carries.
  static DateTime _local(DateTime t) => t.isUtc ? t.toLocal() : t;

  /// `09:12 AM`
  static String time(DateTime? t) {
    if (t == null) return '--:--';
    t = _local(t);
    final int h24 = t.hour;
    final int h = h24 % 12 == 0 ? 12 : h24 % 12;
    final String suffix = h24 < 12 ? 'AM' : 'PM';
    return '${_two(h)}:${_two(t.minute)} $suffix';
  }

  /// `10:05 – 10:47 AM`
  static String timeRange(DateTime from, DateTime? to) {
    if (to == null) return '${time(from)} – ongoing';
    return '${time(from)} – ${time(to)}';
  }

  /// `Saturday, 16 August 2026`
  static String longDate(DateTime d) {
    d = _local(d);
    return '${weekdayLong(d)}, ${d.day} ${_months[d.month - 1]} ${d.year}';
  }

  /// `16 Aug 2026`
  static String mediumDate(DateTime d) {
    d = _local(d);
    return '${d.day} ${_monthsShort[d.month - 1]} ${d.year}';
  }

  /// `August 16`
  static String monthDay(DateTime d) {
    d = _local(d);
    return '${_months[d.month - 1]} ${d.day}';
  }

  /// `August 2026`
  static String monthYear(DateTime d) {
    d = _local(d);
    return '${_months[d.month - 1]} ${d.year}';
  }

  static String monthShort(int month) => _monthsShort[month - 1];

  static String weekdayShort(DateTime d) =>
      _weekdaysShort[_local(d).weekday - 1];

  static String weekdayLong(DateTime d) => const <String>[
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday',
      ][_local(d).weekday - 1];

  /// `04:32:18` — used by the live session timer.
  static String clock(Duration d) {
    final int h = d.inHours;
    final int m = d.inMinutes.remainder(60);
    final int s = d.inSeconds.remainder(60);
    return '${_two(h)}:${_two(m)}:${_two(s)}';
  }

  /// `6h 42m`, `42m`, `48s`
  static String duration(Duration d) {
    if (d.inMinutes < 1) return '${d.inSeconds}s';
    if (d.inHours < 1) return '${d.inMinutes}m';
    final int m = d.inMinutes.remainder(60);
    return m == 0 ? '${d.inHours}h' : '${d.inHours}h ${m}m';
  }

  /// `42 minutes` — long form for timeline copy.
  static String durationLong(Duration d) {
    if (d.inMinutes < 1) return '${d.inSeconds} seconds';
    if (d.inHours < 1) return '${d.inMinutes} minutes';
    final int m = d.inMinutes.remainder(60);
    final String hours = '${d.inHours} hour${d.inHours == 1 ? '' : 's'}';
    return m == 0 ? hours : '$hours $m min';
  }

  /// `42.6 km`
  static String km(double v) => '${v.toStringAsFixed(1)} km';

  /// `12 m` accuracy readout.
  static String metres(double v) => '${v.toStringAsFixed(0)} m';

  /// `23.2402, 87.8615`
  static String latLng(double lat, double lng) =>
      '${lat.toStringAsFixed(5)}, ${lng.toStringAsFixed(5)}';

  /// `2 min ago`
  static String relative(DateTime t, {DateTime? now}) {
    final Duration diff = (now ?? DateTime.now()).difference(t);
    if (diff.inSeconds < 45) return 'just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) return '${diff.inHours} hr ago';
    return '${diff.inDays} d ago';
  }

  /// `8s ago` / `3 min ago` — a *freshness* stamp, not a casual one.
  ///
  /// [relative] rounds everything under 45 s to "just now", which is exactly
  /// the window a live screen needs to be precise about: "just now" frozen on
  /// screen is indistinguishable from a screen that stopped updating. This
  /// counts the seconds instead, so a stalled feed is visible.
  static String freshness(DateTime t, {DateTime? now}) {
    final Duration diff = (now ?? DateTime.now()).difference(t);
    if (diff.isNegative) return 'just now';
    if (diff.inSeconds < 60) return '${diff.inSeconds}s ago';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) return '${diff.inHours} hr ago';
    return '${diff.inDays} d ago';
  }

  /// Floored, not rounded: 99.6% must not read as a flat "100%" on an
  /// attendance card — a shown 100 should mean exactly 100.
  static String percent(double v) => '${v.floor()}%';

  /// `—` for a field the employee never filled in. Optional text fields
  /// (department, region, blood group, address) travel as empty strings, and a
  /// blank value in a label/value row reads as a rendering bug.
  static String orDash(String? v) =>
      (v == null || v.trim().isEmpty) ? '—' : v.trim();

  static bool isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  static DateTime dayOnly(DateTime d) => DateTime(d.year, d.month, d.day);
}
