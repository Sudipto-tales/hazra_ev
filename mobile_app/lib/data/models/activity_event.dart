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

extension ActivityTypeOrder on ActivityType {
  /// Order for two events that fall on the *same* instant, so the timeline
  /// reads in the sequence they actually happened.
  ///
  /// A departure and the trip that follows it are both stamped at the moment
  /// of leaving — `travelling.time` is when movement began, which is when you
  /// `left`. Sorting those by [Enum.index] would surface `travelling` first
  /// (it is declared earlier), reading "on the move" *above* "left ABC", i.e.
  /// travelling before you have left. Declaration order cannot fix this on its
  /// own — `travelling` must precede `arrived` at a trip's start yet follow
  /// `left` at its end — so the same-instant rank is spelled out here. It is
  /// only ever consulted on a tie; distinct timestamps already sort by time.
  int get timelineRank => switch (this) {
        ActivityType.dayStarted => 0,
        ActivityType.sessionStarted => 1,
        ActivityType.arrived => 2,
        ActivityType.stayed => 3,
        ActivityType.reportSubmitted => 4,
        ActivityType.left => 5,
        ActivityType.travelling => 6,
        ActivityType.sessionEnded => 7,
        ActivityType.dayEnded => 8,
        ActivityType.trackingIssue => 9,
      };
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
