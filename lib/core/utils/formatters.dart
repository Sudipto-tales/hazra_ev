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

  /// `09:12 AM`
  static String time(DateTime? t) {
    if (t == null) return '--:--';
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
  static String longDate(DateTime d) =>
      '${weekdayLong(d)}, ${d.day} ${_months[d.month - 1]} ${d.year}';

  /// `16 Aug 2026`
  static String mediumDate(DateTime d) =>
      '${d.day} ${_monthsShort[d.month - 1]} ${d.year}';

  /// `August 16`
  static String monthDay(DateTime d) => '${_months[d.month - 1]} ${d.day}';

  /// `August 2026`
  static String monthYear(DateTime d) => '${_months[d.month - 1]} ${d.year}';

  static String monthShort(int month) => _monthsShort[month - 1];

  static String weekdayShort(DateTime d) => _weekdaysShort[d.weekday - 1];

  static String weekdayLong(DateTime d) => const <String>[
        'Monday', 'Tuesday', 'Wednesday', 'Thursday',
        'Friday', 'Saturday', 'Sunday',
      ][d.weekday - 1];

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

  static String percent(double v) => '${v.toStringAsFixed(0)}%';

  /// `—` for a field the employee never filled in. Optional text fields
  /// (department, region, blood group, address) travel as empty strings, and a
  /// blank value in a label/value row reads as a rendering bug.
  static String orDash(String? v) =>
      (v == null || v.trim().isEmpty) ? '—' : v.trim();

  static bool isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  static DateTime dayOnly(DateTime d) => DateTime(d.year, d.month, d.day);
}
