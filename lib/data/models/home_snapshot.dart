import 'activity_event.dart';
import 'day_closeout.dart';
import 'statistics.dart';
import 'tracking.dart';
import 'visit.dart';

/// Everything the Home screen needs in one payload — mirrors
/// `GET /api/employee/home`. One request, one render.
class HomeSnapshot {
  const HomeSnapshot({
    required this.status,
    required this.movement,
    required this.locationHealth,
    required this.sessions,
    required this.summary,
    required this.activity,
    required this.visits,
    required this.stops,
    required this.lastFix,
    required this.sync,
    this.dayState = DayState.open,
    this.closeout,
  });

  final WorkStatus status;
  final MovementStatus movement;
  final LocationHealth locationHealth;
  final List<WorkSession> sessions;
  final DaySummary summary;
  final List<ActivityEvent> activity;
  final List<CompanyVisit> visits;
  final List<StopRecord> stops;
  final LocationLog? lastFix;
  final SyncSnapshot sync;

  /// Whether the day is still workable. The server owns this — the device only
  /// mirrors it, because a stale local copy is how you get two truths.
  final DayState dayState;

  /// Present once the employee has submitted their end-of-day declaration.
  final DayCloseout? closeout;

  WorkSession? get activeSession {
    for (final WorkSession s in sessions) {
      if (s.isOpen) return s;
    }
    return null;
  }

  HomeSnapshot copyWith({
    WorkStatus? status,
    MovementStatus? movement,
    LocationHealth? locationHealth,
    List<WorkSession>? sessions,
    DaySummary? summary,
    List<ActivityEvent>? activity,
    List<CompanyVisit>? visits,
    List<StopRecord>? stops,
    LocationLog? lastFix,
    SyncSnapshot? sync,
    DayState? dayState,
    DayCloseout? closeout,
  }) {
    return HomeSnapshot(
      status: status ?? this.status,
      movement: movement ?? this.movement,
      locationHealth: locationHealth ?? this.locationHealth,
      sessions: sessions ?? this.sessions,
      summary: summary ?? this.summary,
      activity: activity ?? this.activity,
      visits: visits ?? this.visits,
      stops: stops ?? this.stops,
      lastFix: lastFix ?? this.lastFix,
      sync: sync ?? this.sync,
      dayState: dayState ?? this.dayState,
      closeout: closeout ?? this.closeout,
    );
  }
}
