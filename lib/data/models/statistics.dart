/// Aggregates for Home summary + Calendar statistics.

/// Everything Home needs for "Today's Summary".
class DaySummary {
  const DaySummary({
    required this.joiningTime,
    required this.endTime,
    required this.workedDuration,
    required this.sessionCount,
    required this.distanceKm,
    required this.companiesVisited,
    required this.reportsSubmitted,
    required this.stopDuration,
    required this.longestStop,
  });

  final DateTime? joiningTime;
  final DateTime? endTime;
  final Duration workedDuration;
  final int sessionCount;
  final double distanceKm;
  final int companiesVisited;
  final int reportsSubmitted;
  final Duration stopDuration;
  final Duration longestStop;

  static const DaySummary empty = DaySummary(
    joiningTime: null,
    endTime: null,
    workedDuration: Duration.zero,
    sessionCount: 0,
    distanceKm: 0,
    companiesVisited: 0,
    reportsSubmitted: 0,
    stopDuration: Duration.zero,
    longestStop: Duration.zero,
  );
}

/// Period aggregate behind the Calendar statistics tab.
class PeriodStatistics {
  const PeriodStatistics({
    required this.rangeLabel,
    required this.averageJoiningTime,
    required this.averageWorkedDuration,
    required this.averageDistanceKm,
    required this.totalDistanceKm,
    required this.averageVisitsPerDay,
    required this.totalVisits,
    required this.totalReports,
    required this.averageReportsPerDay,
    required this.workingDays,
    required this.presentDays,
    required this.absentDays,
    required this.attendancePercent,
    required this.dailyDistance,
  });

  final String rangeLabel;

  /// Time-of-day average, carried as a DateTime whose date part is ignored.
  final DateTime averageJoiningTime;
  final Duration averageWorkedDuration;
  final double averageDistanceKm;
  final double totalDistanceKm;
  final double averageVisitsPerDay;
  final int totalVisits;
  final int totalReports;
  final double averageReportsPerDay;
  final int workingDays;
  final int presentDays;
  final int absentDays;
  final double attendancePercent;

  /// Ordered series for the bar chart — oldest first.
  final List<DailyMetric> dailyDistance;
}

class DailyMetric {
  const DailyMetric({
    required this.date,
    required this.value,
    required this.secondaryValue,
  });

  final DateTime date;

  /// Distance in km.
  final double value;

  /// Visit count for the same day.
  final int secondaryValue;
}

enum StatsRange { thisWeek, thisMonth, lastMonth, custom }

extension StatsRangeX on StatsRange {
  String get label => switch (this) {
        StatsRange.thisWeek => 'This week',
        StatsRange.thisMonth => 'This month',
        StatsRange.lastMonth => 'Last month',
        StatsRange.custom => 'Custom',
      };
}
