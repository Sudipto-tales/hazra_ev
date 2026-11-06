/// Flattened, chronological view of the day for the Home timeline.
/// Built by the repository from sessions + stops + visits + reports so the UI
/// never joins those itself.

enum ActivityType {
  dayStarted,
  sessionStarted,
  travelling,
  arrived,
  stayed,
  reportSubmitted,
  left,
  sessionEnded,
  dayEnded,
  trackingIssue,
}

class ActivityEvent {
  const ActivityEvent({
    required this.id,
    required this.type,
    required this.time,
    required this.title,
    this.endTime,
    this.subtitle,
    this.companyName,
    this.branchName,
    this.duration,
    this.reportId,
    this.visitId,
    this.isAlert = false,
    this.provisional = false,
  });

  final String id;
  final ActivityType type;
  final DateTime time;
  final DateTime? endTime;
  final String title;
  final String? subtitle;
  final String? companyName;
  final String? branchName;
  final Duration? duration;
  final String? reportId;
  final String? visitId;

  /// Renders the row in the warning colour (e.g. GPS lost).
  final bool isAlert;

  /// A row the device synthesized locally (a session boundary it already owns
  /// the facts for) that the server has not confirmed yet. Rendered dimmed, and
  /// dropped the moment the server's own row for the same event arrives. The
  /// device never synthesizes stops, visits or reports — those are derived from
  /// the fix chain server-side, and duplicating that is the two-sources-of-truth
  /// problem §3.14's `activity` exists to avoid.
  final bool provisional;
}
