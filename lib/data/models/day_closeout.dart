/// End-of-day declaration, and the lock it puts on the day.
///
/// The numbers the employee types here are a **claim**, not a measurement. The
/// GPS figures live on `DaySummary` and are never overwritten by this — the gap
/// between what was measured and what was declared is the only thing worth
/// looking at, and merging the two would destroy it.

/// Who closed the day, and whether the employee can still work it.
///
/// A session ending is not a day ending. Sessions close all the time — on a
/// break, or automatically when GPS and network both drop. The day closes once,
/// when the employee submits their declaration, and after that only an admin
/// can reopen it.
enum DayState { open, closedByEmployee, closedBySystem }

extension DayStateX on DayState {
  bool get isLocked => this != DayState.open;

  String get label => switch (this) {
        DayState.open => 'Open',
        DayState.closedByEmployee => 'Closed',
        DayState.closedBySystem => 'Auto-closed',
      };

  /// Shown when a locked day blocks Start Day.
  String get lockMessage => switch (this) {
        DayState.open => '',
        DayState.closedByEmployee =>
          'You closed today and submitted your end-of-day report. Ask your '
              'admin to reopen the day if you need to keep working.',
        DayState.closedBySystem =>
          'Today was closed automatically because it was never ended. Ask your '
              'admin to reopen the day if you need to keep working.',
      };
}

/// Why a session stopped. Only [manual] is followed by the closeout form —
/// [auto] happens with nobody watching, so there is nothing to ask.
enum SessionEndReason { manual, pause, auto }

extension SessionEndReasonX on SessionEndReason {
  String get label => switch (this) {
        SessionEndReason.manual => 'Ended by you',
        SessionEndReason.pause => 'Break',
        SessionEndReason.auto => 'Closed automatically',
      };
}

/// Quick reasons, so a rating does not have to be explained in prose.
enum DayFeedbackTag {
  traffic,
  vehicleIssue,
  customerUnavailable,
  weather,
  noLeads,
  goodDay,
}

extension DayFeedbackTagX on DayFeedbackTag {
  String get label => switch (this) {
        DayFeedbackTag.traffic => 'Heavy traffic',
        DayFeedbackTag.vehicleIssue => 'Vehicle issue',
        DayFeedbackTag.customerUnavailable => 'Customer unavailable',
        DayFeedbackTag.weather => 'Bad weather',
        DayFeedbackTag.noLeads => 'No leads',
        DayFeedbackTag.goodDay => 'Good day',
      };
}

/// Bounds the form validates against. Kept next to the model so the widget and
/// any test agree on one set of numbers.
class DayCloseoutLimits {
  const DayCloseoutLimits._();

  static const double maxDistanceKm = 1000;
  static const int maxVisits = 50;
  static const int feedbackMaxChars = 500;

  /// Declared distance this far from the GPS figure earns a warning. It never
  /// blocks: a soft heuristic must not be what stops someone ending their day.
  static const double deviationWarnPercent = 25;
}

/// What the closeout form collects before it becomes a [DayCloseout].
class DayCloseoutDraft {
  const DayCloseoutDraft({
    required this.clientId,
    required this.endedAt,
    required this.declaredDistanceKm,
    required this.declaredVisits,
    required this.rating,
    this.tags = const <DayFeedbackTag>[],
    this.feedback,
  });

  /// Idempotency key. A double-tap or a retry returns the same record rather
  /// than closing the day twice.
  final String clientId;
  final DateTime endedAt;
  final double declaredDistanceKm;
  final int declaredVisits;

  /// 1..5.
  final int rating;
  final List<DayFeedbackTag> tags;
  final String? feedback;
}

/// The stored declaration. Carries the measured figures alongside the declared
/// ones so the admin view never has to join two payloads to show the gap.
class DayCloseout {
  const DayCloseout({
    required this.id,
    required this.date,
    required this.submittedAt,
    required this.declaredDistanceKm,
    required this.declaredVisits,
    required this.measuredDistanceKm,
    required this.measuredVisits,
    required this.rating,
    required this.tags,
    required this.feedback,
  });

  final String id;
  final DateTime date;
  final DateTime submittedAt;
  final double declaredDistanceKm;
  final int declaredVisits;

  /// GPS-derived, copied from the day's summary at submit time.
  final double measuredDistanceKm;
  final int measuredVisits;
  final int rating;
  final List<DayFeedbackTag> tags;
  final String? feedback;

  /// How far the claim sits from the measurement, signed. Null when there is
  /// no measurement to compare against.
  double? get distanceDeviationPercent {
    if (measuredDistanceKm <= 0) return null;
    return (declaredDistanceKm - measuredDistanceKm) / measuredDistanceKm * 100;
  }

  bool get distanceLooksOff {
    final double? d = distanceDeviationPercent;
    return d != null && d.abs() > DayCloseoutLimits.deviationWarnPercent;
  }
}
